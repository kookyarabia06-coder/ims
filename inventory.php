<?php
ob_start();
require_once 'auth.php';
require_login();
include 'header.php';

// Include barcode generation library from Composer vendor directory
require_once __DIR__ . '/../vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;

// Helper function to convert numbers to ordinal (1st, 2nd, 3rd, etc.)
function ordinal($number) {
    $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) return $number.'th';
    return $number.$ends[$number % 10];
}

// Database connection
$mysqli = new mysqli('localhost', 'root', '', 'inventory_db');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// ============================================================================
// AUTO-SELECT EQUIPMENT TYPE BASED ON URL PARAMETER
// ============================================================================
$selected_type = isset($_GET['type']) ? $_GET['type'] : '';
$default_equipment_type = '';
$filter_condition = '';
$page_title = 'Inventory List';

if ($selected_type == 'semi-expendable') {
    $default_equipment_type = 'Semi-expendable Equipment';
    $filter_condition = "WHERE inv.type_equipment = 'Semi-expendable Equipment'";
    $page_title = 'Semi-expendable Equipment';
} elseif ($selected_type == 'ppe') {
    $default_equipment_type = 'Property Plant Equipment (50K Above)';
    $filter_condition = "WHERE inv.type_equipment = 'Property Plant Equipment (50K Above)'";
    $page_title = 'Property Plant Equipment (50K Above)';
}

// Fetch dropdown data for forms
$dept_res = $mysqli->query("SELECT d.id AS dept_id, d.name AS dept_name, b.name AS building_name, b.floor AS building_floor 
                            FROM departments d 
                            LEFT JOIN buildings b ON d.building_id = b.id 
                            ORDER BY b.name, b.floor, d.name");
$equipment_res = $mysqli->query("SELECT id, name, category FROM equipment ORDER BY name");
$employees_res = $mysqli->query("SELECT id, firstname, lastname FROM employees ORDER BY firstname");
$sections_res = $mysqli->query("SELECT s.id, s.name AS sname, d.name AS dname, b.name AS bname
                                FROM sections s
                                LEFT JOIN departments d ON s.department_id = d.id
                                LEFT JOIN buildings b ON d.building_id = b.id
                                ORDER BY b.name, d.name, s.name");

// Handle delete action
if(isset($_GET['action']) && $_GET['action'] === 'delete'){
    $id = intval($_GET['id']);
    $mysqli->query("DELETE FROM inventory WHERE id = $id");
    header('Location: inventory.php' . ($selected_type ? '?type=' . $selected_type : ''));
    exit;
}

// Handle Add/Edit form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;

    // Collect and sanitize POST data
    $article_name = trim($_POST['article_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $property_no = trim($_POST['property_no'] ?? '');
    $uom = trim($_POST['uom'] ?? '');
    
    $qty_property_card = floatval($_POST['qty_property_card'] ?? 0);
    $qty_physical_count = floatval($_POST['qty_physical_count'] ?? 0);
    $unit_value = floatval($_POST['unit_value'] ?? 0);

    $location_id = isset($_POST['location_id']) && $_POST['location_id'] !== '' ? intval($_POST['location_id']) : 0;
    $condition_text = trim($_POST['condition_text'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $fund_cluster = trim($_POST['fund_cluster'] ?? '');
    $type_equipment = trim($_POST['type_equipment'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    $approved_by = isset($_POST['approved_by']) && $_POST['approved_by'] !== '' ? intval($_POST['approved_by']) : 0;
    $verified_by = isset($_POST['verified_by']) && $_POST['verified_by'] !== '' ? intval($_POST['verified_by']) : 0;
    $section_id = isset($_POST['section_id']) && $_POST['section_id'] !== '' ? intval($_POST['section_id']) : 0;
    $equipment_id = isset($_POST['equipment_id']) && $_POST['equipment_id'] !== '' ? intval($_POST['equipment_id']) : 0;
    $allocate_to = isset($_POST['allocate_to']) && $_POST['allocate_to'] !== '' ? intval($_POST['allocate_to']) : 0;

    // Handle multi-select certified correct field (store as JSON array)
    $certified = $_POST['certified_correct'] ?? [];
    $cert_ids = array_map('intval', $certified);
    $cert_json = !empty($cert_ids) ? json_encode($cert_ids) : null;

    // AUTO-GENERATE BARCODE SECTION
    $barcode_data = null;
    $barcode_image = null;
    
    if ($property_no) {
        try {
            // Create barcode generator instance
            $generator = new BarcodeGeneratorPNG();
            
            // Generate barcode image using CODE 128 format
            $barcode_image_data = $generator->getBarcode($property_no, $generator::TYPE_CODE_128);
            
            // Convert binary image to base64 for database storage
            $barcode_image = 'data:image/png;base64,' . base64_encode($barcode_image_data);
            $barcode_data = $property_no; // Store the property number as barcode data
            
        } catch (Exception $e) {
            error_log("Barcode generation failed: " . $e->getMessage());
        }
    }

    try {
        if($id){
            // UPDATE EXISTING RECORD
            $old_property_stmt = $mysqli->prepare("SELECT property_no FROM inventory WHERE id = ?");
            $old_property_stmt->bind_param("i", $id);
            $old_property_stmt->execute();
            $old_property_stmt->bind_result($old_property_no);
            $old_property_stmt->fetch();
            $old_property_stmt->close();
            
            // Only regenerate barcode if property number changed
            if ($old_property_no !== $property_no && $property_no) {
                $stmt = $mysqli->prepare("
                    UPDATE inventory SET
                        article_name=?, description=?, property_no=?, uom=?, 
                        qty_property_card=?, qty_physical_count=?,
                        location_id=?, condition_text=?, remarks=?,
                        certified_correct=?, approved_by=?, verified_by=?,
                        section_id=?, fund_cluster=?, unit_value=?,
                        equipment_id=?, type_equipment=?, category=?,
                        allocate_to=?, barcode_data=?, barcode_image=?,
                        date_updated=NOW()
                    WHERE id=?
                ");
                $stmt->bind_param(
                    "ssssddisssiiisdississi",
                    $article_name,
                    $description,
                    $property_no,
                    $uom,
                    $qty_property_card,
                    $qty_physical_count,
                    $location_id,
                    $condition_text,
                    $remarks,
                    $cert_json,
                    $approved_by,
                    $verified_by,
                    $section_id,
                    $fund_cluster,
                    $unit_value,
                    $equipment_id,
                    $type_equipment,
                    $category,
                    $allocate_to,
                    $barcode_data,
                    $barcode_image,
                    $id 
                );
            } else {
                // Keep existing barcode
                $stmt = $mysqli->prepare("
                    UPDATE inventory SET
                        article_name=?, description=?, property_no=?, uom=?, 
                        qty_property_card=?, qty_physical_count=?,
                        location_id=?, condition_text=?, remarks=?,
                        certified_correct=?, approved_by=?, verified_by=?,
                        section_id=?, fund_cluster=?, unit_value=?,
                        equipment_id=?, type_equipment=?, category=?,
                        allocate_to=?,
                        date_updated=NOW()
                    WHERE id=?
                ");
                $stmt->bind_param(
                    "ssssddisssiiisdissisi",
                    $article_name,
                    $description,
                    $property_no,
                    $uom,
                    $qty_property_card,
                    $qty_physical_count,
                    $location_id,
                    $condition_text,
                    $remarks,
                    $cert_json,
                    $approved_by,
                    $verified_by,
                    $section_id,
                    $fund_cluster,
                    $unit_value,
                    $equipment_id,
                    $type_equipment,
                    $category,
                    $allocate_to,
                    $id
                );
            }

            if($stmt->execute()) {
                header('Location: inventory.php' . ($selected_type ? '?type=' . $selected_type : '?success=1'));
                exit;
            } else {
                throw new Exception('Database error: ' . $stmt->error);
            }
            $stmt->close();

        } else {
            // INSERT NEW RECORD
            $stmt = $mysqli->prepare("
                INSERT INTO inventory (
                    article_name, description, property_no, uom,
                    qty_property_card, qty_physical_count,
                    location_id, condition_text, remarks,
                    certified_correct, approved_by, verified_by,
                    section_id, fund_cluster, unit_value,
                    equipment_id, type_equipment, category,
                    allocate_to, barcode_data, barcode_image,
                    date_added, date_updated
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            if(!$stmt) throw new Exception($mysqli->error);

            $stmt->bind_param(
                "ssssddisssiiisdississ", 
                $article_name,
                $description,
                $property_no,
                $uom,
                $qty_property_card,
                $qty_physical_count,
                $location_id,
                $condition_text,
                $remarks,
                $cert_json,
                $approved_by,
                $verified_by,
                $section_id,
                $fund_cluster,
                $unit_value,
                $equipment_id,
                $type_equipment,
                $category,
                $allocate_to,
                $barcode_data,
                $barcode_image
            );

            if($stmt->execute()) {
                header('Location: inventory.php' . ($selected_type ? '?type=' . $selected_type : '?success=1'));
                exit;
            } else {
                throw new Exception('Database error: ' . $stmt->error);
            }
            $stmt->close();
        }

    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $error_message = "Property No should be unique. You have entered an existing Property No.";
        } else {
            $error_message = "Database Error: " . $e->getMessage();
        }
    } catch (Exception $ex) {
        $error_message = "Error: " . $ex->getMessage();
    }
}

// Fetch inventory data for display in table - WITH FILTER
$inventory_res = $mysqli->query("
    SELECT inv.*, 
           s.name as section_name, 
           d.name as department_name, 
           b.name AS building_name,
           b.floor AS building_floor,
           e1.firstname AS approved_first,
           e1.lastname AS approved_last,
           e2.firstname AS verified_first,
           e2.lastname AS verified_last,
           e3.firstname AS allocate_first,
           e3.lastname AS allocate_last,
           eq.name AS equip_name, 
           eq.category AS equip_category
    FROM inventory inv
    LEFT JOIN sections s ON inv.section_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN buildings b ON d.building_id = b.id
    LEFT JOIN employees e1 ON inv.approved_by = e1.id
    LEFT JOIN employees e2 ON inv.verified_by = e2.id
    LEFT JOIN employees e3 ON inv.allocate_to = e3.id
    LEFT JOIN equipment eq ON inv.equipment_id = eq.id
    $filter_condition
    ORDER BY inv.id DESC
");

// Fetch items for batch barcode generation modal
$all_inventory = $mysqli->query("SELECT id, property_no, description FROM inventory ORDER BY property_no");
?>

<!-- CSS Styles for the inventory interface -->
<style>
    .table-responsive {
        max-height: 650px;
        overflow-y: auto;
        border-radius: 6px;
    }
    thead th {
        position: sticky;
        top: 0;
        background: #1e1f55ff !important;
        color: white !important;
        z-index: 10;
    }
    .table td, .table th {
        vertical-align: middle;
        font-size: 13px;
        white-space: nowrap;
    }
    .barcode-container {
        text-align: center;
        padding: 5px;
    }
    .barcode-image {
        max-width: 150px;
        height: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 3px;
        background: white;
    }
    .barcode-text {
        font-size: 11px;
        margin-top: 2px;
        font-family: monospace;
    }
    .auto-generate-btn {
        margin-top: 8px;
    }
    .generate-btn-group {
        display: flex;
        gap: 5px;
        margin-top: 5px;
        flex-wrap: wrap;
    }
    .wrap-cell {
        white-space: normal !important;
        word-wrap: break-word;
        max-width: 200px;
    }
    #barcodePreview {
        min-height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .modal-xl {
        max-width: 90%;
    }
    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
    /* Multiple barcode modal styles */
    .barcode-preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 10px;
    }
    .barcode-preview-item {
        padding: 8px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        text-align: center;
        background: white;
        word-break: break-all;
    }
    .barcode-preview-img {
        max-width: 100%;
        height: 40px;
        object-fit: contain;
    }
    .barcode-list-item {
        display: flex;
        align-items: center;
        padding: 5px;
        border-bottom: 1px solid #eee;
    }
    .barcode-list-item:last-child {
        border-bottom: none;
    }
    /* QUANTITY SYNC STYLES */
    .qty-hint {
        font-size: 12px;
        padding: 4px 8px;
        margin-top: 5px;
        border-radius: 4px;
    }
    #invQtyPhy[value]:not([value=""]):not([value="0"]):not([value="1"]) {
        border-color: #ffc107;
        background-color: #fff8e1;
    }
    .quantity-badge {
        display: inline-block;
        width: 20px;
        height: 20px;
        background: #ffc107;
        color: #000;
        border-radius: 50%;
        text-align: center;
        font-size: 12px;
        line-height: 20px;
        margin-left: 5px;
        font-weight: bold;
    }
    .alert-sm {
        font-size: 12px;
        padding: 4px 8px;
        margin-bottom: 5px;
    }
    .btn-xs {
        padding: 1px 5px;
        font-size: 10px;
        line-height: 1.2;
    }
    /* Property No Sync Indicator */
    .sync-indicator {
        display: inline-flex;
        align-items: center;
        font-size: 12px;
        margin-top: 5px;
    }
    .sync-badge {
        background: #17a2b8;
        color: white;
        border-radius: 10px;
        padding: 2px 8px;
        margin-right: 5px;
        cursor: pointer;
    }
    .sync-badge:hover {
        background: #138496;
    }
    /* Page title badge */
    .page-title-badge {
        font-size: 14px;
        font-weight: normal;
        margin-left: 10px;
        padding: 5px 10px;
    }
</style>

<!-- MAIN INVENTORY INTERFACE -->
<div class="container-fluid mt-4">
    <div class="card shadow-lg rounded-4 p-3">
        <div class="card-header bg-primary text-white rounded-4 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-boxes"></i> <?= $page_title ?>
                <?php if($selected_type): ?>
                    <span class="badge bg-light text-dark page-title-badge">
                        <i class="fas fa-filter"></i> Filtered: <?= $selected_type == 'semi-expendable' ? 'Semi-expendable' : 'PPE' ?>
                        <a href="inventory.php" class="text-dark ms-2" style="text-decoration: none;">✕</a>
                    </span>
                <?php endif; ?>
            </h5>
            <div>
                <!-- Action buttons for inventory management -->
                <button type="button" class="btn btn-light me-2" onclick="printBarcodeLabels()">
                    <i class="fas fa-print"></i> Print Labels
                </button>
                <?php if($u['role'] === 'admin'): ?>
                <!-- Button to generate missing barcodes for existing items -->
                <button type="button" class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#barcodeBatchModal">
                    <i class="fas fa-barcode"></i> Generate Missing Barcodes
                </button>
                <!-- Button to add new inventory item -->
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#inventoryModal" onclick="openInventoryModal('', {}, '<?= $default_equipment_type ?>')">
                    <i class="fas fa-plus"></i> Add <?= $selected_type ? str_replace(' Equipment', '', str_replace(' (50K Above)', '', $default_equipment_type)) : 'Inventory' ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Inventory item saved successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if($selected_type && $inventory_res->num_rows == 0): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No <?= $selected_type == 'semi-expendable' ? 'Semi-expendable Equipment' : 'Property Plant Equipment (50K Above)' ?> found. 
                <a href="#" data-bs-toggle="modal" data-bs-target="#inventoryModal" onclick="openInventoryModal('', {}, '<?= $default_equipment_type ?>')">Click here to add one</a>.
            </div>
            <?php endif; ?>
            
            <!-- Inventory table with barcode display -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle shadow-sm rounded-4">
                    <thead class="table-primary rounded-4">
                        <tr>
                            <th>ID</th>
                            <th>Barcode</th>
                            <th>Property No</th>
                            <th>Location</th>
                            <th class="wrap-cell">Type of Equipment</th>
                            <th>Article</th>
                            <th>Type of PPE</th>
                            <th class="wrap-cell">Description</th>
                            <th>Qty (Prop)</th>
                            <th>Qty (Phy)</th>
                            <th>Unit Value</th>
                            <th>Unit Measure</th>
                            <th>Accountable</th>
                            <th class="wrap-cell">Certified Correct</th>
                            <th>Approved</th>
                            <th>Verified</th>
                            <th>Fund Cluster</th>
                            <th>Condition</th>
                            <th class="wrap-cell">Remarks</th>
                            <?php if($u['role'] === 'admin'): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($inventory_res->num_rows > 0): ?>
                            <?php while($r = $inventory_res->fetch_assoc()):
                                // Format employee names for display
                                $approved = $r['approved_first'] ? e($r['approved_first'].' '.$r['approved_last']) : '';
                                $verified = $r['verified_first'] ? e($r['verified_first'].' '.$r['verified_last']) : '';
                                $allocated = $r['allocate_first'] ? e($r['allocate_first'].' '.$r['allocate_last']) : '';

                                // Parse certified correct JSON and get employee names
                                $cert = '';
                                if(!empty($r['certified_correct'])){
                                    $ids = json_decode($r['certified_correct'], true);
                                    if(!empty($ids)){
                                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                                        $types = str_repeat('i', count($ids));
                                        $stmt = $mysqli->prepare("SELECT firstname, lastname FROM employees WHERE id IN ($placeholders)");
                                        if($stmt){
                                            $stmt->bind_param($types, ...$ids);
                                            $stmt->execute();
                                            $res = $stmt->get_result();
                                            $names = [];
                                            while($row = $res->fetch_assoc()){
                                                $names[] = $row['firstname'].' '.$row['lastname'];
                                            }
                                            $cert = implode(', ', $names);
                                            $stmt->close();
                                        }
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= $r['id'] ?></td>
                                <td class="barcode-container">
                                    <?php if(!empty($r['barcode_image'])): ?>
                                        <!-- Display existing barcode with print option -->
                                        <img src="<?= $r['barcode_image'] ?>" class="barcode-image" alt="Barcode <?= e($r['property_no']) ?>">
                                        <div class="barcode-text"><?= e($r['property_no']) ?></div>
                                        <button class="btn btn-sm btn-outline-primary mt-1" onclick="printSingleBarcode('<?= e($r['property_no']) ?>', '<?= e($r['barcode_image']) ?>', '<?= e($r['description']) ?>')">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    <?php else: ?>
                                        <!-- Button to generate missing barcode -->
                                        <button class="btn btn-sm btn-warning" onclick="generateBarcodeForItem(<?= $r['id'] ?>, '<?= e($r['property_no']) ?>')">
                                            <i class="fas fa-barcode"></i> Generate
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($r['property_no']) ?></td>
                                <td><?= e($r['building_name'] . ' (' . ordinal($r['building_floor']) . ' Floor) - ' . $r['department_name']) ?></td>
                                <td class="wrap-cell"><?= e($r['type_equipment']) ?></td>
                                <td><?= e($r['equip_name']) ?></td>
                                <td><?= e($r['equip_category']) ?></td>
                                <td class="wrap-cell"><?= e($r['description']) ?></td>
                                <td><?= e($r['qty_property_card']) ?></td>
                                <td><?= e($r['qty_physical_count']) ?></td>
                                <td><?= number_format($r['unit_value'], 2) ?></td>
                                <td><?= e($r['uom']) ?></td>
                                <td><?= $allocated ?></td>
                                <td class="wrap-cell"><?= $cert ?></td>
                                <td><?= $approved ?></td>
                                <td><?= $verified ?></td>
                                <td><?= e($r['fund_cluster']) ?></td>
                                <td><?= e($r['condition_text']) ?></td>
                                <td class="wrap-cell"><?= e($r['remarks']) ?></td>
                                <?php if($u['role'] === 'admin'): ?>
                                <td>
                                    <!-- Edit button with data attributes for JavaScript -->
                                    <button 
                                        class="btn btn-sm btn-primary edit-btn"
                                        data-id="<?= $r['id'] ?>"
                                        data-item='<?= htmlspecialchars(json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                        Edit
                                    </button>
                                    <!-- Delete button with confirmation -->
                                    <a href="inventory.php?action=delete&id=<?= $r['id'] ?><?= $selected_type ? '&type=' . $selected_type : '' ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this item?')">Delete</a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="20" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-box-open fa-2x mb-2"></i>
                                        <p>No inventory items found<?= $selected_type ? ' for this equipment type' : '' ?>.</p>
                                        <?php if($selected_type): ?>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#inventoryModal" onclick="openInventoryModal('', {}, '<?= $default_equipment_type ?>')" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Add <?= $default_equipment_type ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if(!empty($error_message)): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================================
MAIN INVENTORY MODAL (Add/Edit Single Item)
============================================================================ -->
<div class="modal fade" id="inventoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form method="POST" class="modal-content border-primary shadow-lg rounded-3 p-3">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="inventoryModalLabel">Add Inventory Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row">
          <input type="hidden" name="id" id="invId">
          
          <!-- Article Name -->
          <div class="col-md-6 mb-2">
              <label class="form-label">Article Name</label>
              <input type="text" name="article_name" id="invArticleName" class="form-control">
          </div>

          <!-- Type of Equipment - AUTO SELECTED FROM URL -->
          <div class="col-md-6 mb-2">
            <label class="form-label">Type of Equipment</label>
            <select name="type_equipment" id="invType" class="form-select" <?= $selected_type ? 'disabled' : '' ?>>
                <option value="">-- select type --</option>
                <option value="Semi-expendable Equipment" <?= ($default_equipment_type == 'Semi-expendable Equipment') ? 'selected' : '' ?>>Semi-expendable Equipment</option>
                <option value="Property Plant Equipment (50K Above)" <?= ($default_equipment_type == 'Property Plant Equipment (50K Above)') ? 'selected' : '' ?>>Property Plant Equipment (50K Above)</option>
            </select>
            <?php if($selected_type): ?>
                <input type="hidden" name="type_equipment" value="<?= $default_equipment_type ?>">
                <small class="text-muted">Equipment type is fixed based on your selection</small>
            <?php endif; ?>
          </div>

          <!-- Equipment -->
          <div class="col-md-4 mb-2">
              <label class="form-label">Equipment</label>
              <select name="equipment_id" id="invEquipment" class="form-select">
                  <option value=''>-- select --</option>
                  <?php
                  $equipment_res->data_seek(0);
                  while($e = $equipment_res->fetch_assoc()){
                      echo "<option value='{$e['id']}' data-code='".substr(strtoupper(str_replace(' ', '', $e['name'])), 0, 4)."' data-category='{$e['category']}'>".e($e['name'])." ({$e['category']})</option>";
                  }
                  ?>
              </select>
          </div>

          <!-- Category -->
          <div class="col-md-4 mb-2">
              <label class="form-label">Type of PPE</label>
              <input type="text" name="category" id="invCategory" class="form-control">
          </div>

          <!-- Description -->
          <div class="col-md-4 mb-2">
              <label class="form-label">Description</label>
              <input type="text" name="description" id="invDescription" class="form-control">
          </div>

          <!-- Unit Measure -->
          <div class="col-md-3 mb-2">
              <label class="form-label">Unit Measure</label>
              <select name="uom" id="invUom" class="form-select">
                  <option value="">-- select --</option>
                  <option value="Unit">Unit</option>
                  <option value="Lot">Lot</option>
                  <option value="Per PC">Per PC</option>
                  <option value="Set">Set</option>
                  <option value="Pair">Pair</option>
                  <option value="Box">Box</option>
              </select>
          </div>

          <!-- Qty Property Card -->
          <div class="col-md-3 mb-2">
              <label class="form-label">Qty (Property Card)</label>
              <input type="number" step="0.01" name="qty_property_card" id="invQtyCard" class="form-control">
          </div>

          <!-- Qty Physical Count -->
          <div class="col-md-3 mb-2">
              <label class="form-label">Qty (Physical Count)</label>
              <div class="input-group">
                  <input type="number" step="0.01" name="qty_physical_count" id="invQtyPhy" class="form-control">
                  <button type="button" class="btn btn-outline-info" onclick="openMultipleBarcodeModal()" title="Generate multiple barcodes">
                      <i class="fas fa-layer-group"></i> Generate
                  </button>
              </div>
              <div id="qtyHint" class="qty-hint mt-1"></div>
          </div>

          <!-- Unit Value -->
          <div class="col-md-3 mb-2">
              <label class="form-label">Unit Value</label>
              <input type="number" step="0.01" name="unit_value" id="invUnitValue" class="form-control">
          </div>

          <!-- Location / Department -->
          <div class="col-md-6 mb-2">
              <label class="form-label">Location / Whereabouts</label>
              <select name="location_id" id="invLocation" class="form-select" required>
                  <option value="">-- Select Location --</option>
                  <?php $dept_res->data_seek(0); while($d = $dept_res->fetch_assoc()): ?>
                      <option value="<?= $d['dept_id'] ?>" data-code="<?= substr(strtoupper(str_replace(' ', '', $d['dept_name'])), 0, 3) ?>-<?= $d['building_floor'] ?>">
                          <?= e($d['building_name']) . ' (' . ordinal($d['building_floor']) . ' Floor) - ' . e($d['dept_name']) ?>
                      </option>
                  <?php endwhile; ?>
              </select>
          </div>

          <!-- Condition -->
          <div class="col-md-6 mb-2">
            <label class="form-label">Condition</label>
            <select name="condition_text" id="invCondition" class="form-select">
                <option value="">-- select --</option>
                <option value="Serviceable">Serviceable</option>
                <option value="Non-Serviceable">Non-Serviceable</option>
                <option value="For Condemn">For Condemn</option>
                <option value="Under Repair">Under Repair</option>
                <option value="For Disposal">For Disposal</option>
            </select>
          </div>

          <!-- Fund Cluster -->
          <div class="col-md-4 mb-2">
              <label class="form-label">Fund Cluster</label>
              <select name="fund_cluster" id="invFund" class="form-select">
                  <option value="">-- select --</option>
                  <option value="IGF">IGF</option>
                  <option value="RAF">RAF</option>
                  <option value="HI">HI</option>
                  <option value="TR">TR</option>
                  <option value="TF">TF</option>
                  <option value="Donation">Donation</option>
              </select>
          </div>
          
          <!-- Allocate To -->
          <div class="col-md-4 mb-2">
              <label class="form-label">Accountable By</label>
              <select name="allocate_to" id="invAllocate" class="form-select">
                  <option value="">-- Select --</option>
                  <?php
                  $employees_res->data_seek(0);
                  while($e = $employees_res->fetch_assoc()){
                      echo "<option value='{$e['id']}'>{$e['firstname']} {$e['lastname']}</option>";
                  }
                  ?>
              </select>
          </div>

          <!-- Certified Correct (Multi-select) -->
          <div class="col-md-4 mb-2">
              <label class="form-label">Certified Correct</label>
              <select name="certified_correct[]" id="invCertified" class="form-select" multiple size="4">
                  <?php 
                  $employees_res->data_seek(0);
                  while($e=$employees_res->fetch_assoc()){
                      echo "<option value='{$e['id']}'>".e($e['firstname'].' '.$e['lastname'])."</option>";
                  }
                  ?>
              </select>
          </div>

          <!-- Approved By -->
          <div class="col-md-4 mb-2">
              <label class="form-label">Approved By</label>
              <select name="approved_by" id="invApproved" class="form-select">
                  <option value=''>-- none --</option>
                  <?php
                  $employees_res->data_seek(0);
                  while($e=$employees_res->fetch_assoc()){
                      echo "<option value='{$e['id']}'>".e($e['firstname'].' '.$e['lastname'])."</option>";
                  }
                  ?>
              </select>
          </div>

          <!-- Verified By -->
          <div class="col-md-4 mb-2">
              <label class="form-label">Verified By</label>
              <select name="verified_by" id="invVerified" class="form-select">
                  <option value=''>-- none --</option>
                  <?php
                  $employees_res->data_seek(0);
                  while($e=$employees_res->fetch_assoc()){
                      echo "<option value='{$e['id']}'>".e($e['firstname'].' '.$e['lastname'])."</option>";
                  }
                  ?>
              </select>
          </div>

          <!-- Section -->
          <div class="col-md-4 mb-2">
              <label class="form-label">Section</label>
              <select name="section_id" id="invSection" class="form-select">
                  <option value="">-- none --</option>
                  <?php
                  $sections_res->data_seek(0);
                  while($s=$sections_res->fetch_assoc()){
                      $label = trim(
                          ($s['bname'] ? $s['bname'].' / ' : '') .
                          ($s['dname'] ? $s['dname'].' / ' : '') .
                          $s['sname']
                      );
                      echo "<option value='{$s['id']}'>".e($label)."</option>";
                  }
                  ?>
              </select>
          </div>

          <!-- Remarks -->
          <div class="col-md-12 mb-2">
              <label class="form-label">Remarks</label>
              <textarea name="remarks" id="invRemarks" class="form-control" rows="2"></textarea>
          </div>

        <!-- ============================================================================
        PROPERTY NUMBER & BARCODE SECTION
        ============================================================================ -->
        <div class="col-md-6 mb-2">
            <label class="form-label">Property No <small class="text-muted">(Auto-generate barcode based on this)</small></label>
            <div class="input-group mb-2">
                <input type="text" name="property_no" id="invPropertyNo" class="form-control" required>
                <!-- Single auto-generate button -->
                <button type="button" class="btn btn-outline-secondary" onclick="autoGeneratePropertyNo()">
                    <i class="fas fa-sync-alt"></i> Auto
                </button>
                <!-- Multiple barcode generator button -->
                <button type="button" class="btn btn-outline-warning" onclick="openMultipleBarcodeModal()">
                    <i class="fas fa-layer-group"></i> Multiple
                </button>
            </div>
            
            <!-- Property No Sync Indicator -->
            <div id="propertyNoSyncIndicator" class="sync-indicator" style="display: none;">
                <span class="sync-badge" onclick="syncPrefixToModal()" title="Click to use this prefix in Multiple Barcode modal">
                    <i class="fas fa-sync-alt"></i> Prefix: <span id="currentPrefix"></span>
                </span>
                <small class="text-muted">Prefix detected. Click to sync to Multiple Barcode modal.</small>
            </div>
            
            <div class="generate-btn-group">
                <!-- Different generation methods -->
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="generateFromEquipment()">
                    <i class="fas fa-cogs"></i> From Equipment
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="generateFromDepartment()">
                    <i class="fas fa-building"></i> From Dept
                </button>
                <button type="button" class="btn btn-sm btn-outline-info" onclick="generateSequential()">
                    <i class="fas fa-sort-numeric-up"></i> Sequential
                </button>
            </div>
        </div>
        
        <!-- Barcode Preview Area -->
        <div class="col-md-6 mb-2">
            <label class="form-label">Barcode Preview</label>
            <div id="barcodePreview" class="border p-3 text-center rounded" style="min-height: 100px; background: #f8f9fa;">
                <p class="text-muted mb-0">Enter Property No to see barcode preview</p>
            </div>
        </div>

      </div>
      <div class="modal-footer">
          <!-- Print current barcode button -->
          <button type="button" class="btn btn-success" onclick="printCurrentBarcode()">
              <i class="fas fa-print"></i> Print Barcode
          </button>
          <!-- Save form button -->
          <button type="submit" class="btn btn-primary">Save</button>
          <!-- Cancel button -->
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================================
MULTIPLE BARCODE GENERATION MODAL
This modal allows generating multiple barcodes at once with batch settings
============================================================================ -->
<div class="modal fade" id="multipleBarcodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-barcode"></i> Generate Multiple Barcodes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Number Generation Settings -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Prefix <small class="text-muted">(Sync from Property No)</small></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="multiPrefix" placeholder="e.g., INV-" value="INV-">
                        </div>
                        <div class="form-text">Current Property No prefix will be auto-detected</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Start Number</label>
                        <input type="number" class="form-control" id="multiStartNum" value="1" min="1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Count <small class="text-muted">(Auto-synced from Quantity)</small></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="multiCount" value="1" min="1" max="100">
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Digits to Pad</label>
                        <select class="form-select" id="multiPadDigits">
                            <option value="3">3 Digits (001)</option>
                            <option value="4" selected>4 Digits (0001)</option>
                            <option value="5">5 Digits (00001)</option>
                            <option value="6">6 Digits (000001)</option>
                        </select>
                    </div>
                </div>

                <!-- Preview Format Selection -->
                <div class="mb-3">
                    <label class="form-label">Preview Format</label>
                    <select class="form-select" id="multiPreviewFormat">
                        <option value="list">List View</option>
                        <option value="grid">Grid View</option>
                    </select>
                </div>

                <!-- ============================================================================
                BATCH SETTINGS SECTION
                ============================================================================ -->
                <div id="batchSettings">
                    <hr>
                    <h6>Batch Item Settings <small class="text-muted">(Auto-filled from main form)</small></h6>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Description for All Items</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="batchDescription" placeholder="e.g., Desktop Computer">
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Location</label>
                            <div class="input-group">
                                <select class="form-select" id="batchLocation">
                                    <option value="">-- Select Location --</option>
                                    <?php 
                                    $dept_res->data_seek(0); 
                                    while($d = $dept_res->fetch_assoc()): ?>
                                    <option value="<?= $d['dept_id'] ?>">
                                        <?= e($d['building_name']) ?> - <?= e($d['dept_name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Unit of Measure</label>
                            <div class="input-group">
                                <select class="form-select" id="batchUOM">
                                     <option value="">-- select --</option>
                                    <option value="Unit">Unit</option>
                                    <option value="Lot">Lot</option>
                                    <option value="Per PC">Per PC</option>
                                    <option value="Set">Set</option>
                                    <option value="Pair">Pair</option>
                                    <option value="Box">Box</option>
                                </select>
                            </div>
                        </div>

                        <!-- CONDITION Dropdown -->
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Condition</label>
                            <div class="input-group">
                                <select class="form-select" id="batchCondition">
                                    <option value="Serviceable">Serviceable</option>
                                    <option value="Non-Serviceable">Non-Serviceable</option>
                                    <option value="For Condemn">For Condemn</option>
                                    <option value="Under repair">Under repair</option>
                                    <option value="For Disposal">For Disposal</option>
                                </select>
                            </div>
                        </div>

                        <!-- Fund Cluster Dropdown -->
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Fund Cluster</label>
                            <div class="input-group">
                                <select class="form-select" id="batchFund">
                                    <option value="IGF">IGF</option>
                                    <option value="RAF">RAF</option>
                                    <option value="HI">HI</option>
                                    <option value="TR">TR</option>
                                    <option value="TF">TF</option>
                                    <option value="">Donation</option>
                                </select>
                            </div>
                        </div>

                        <!-- Equipment Type Dropdown - AUTO SELECTED FROM URL -->
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Equipment Type</label>
                            <div class="input-group">
                                <select class="form-select" id="batchEquipmentType" <?= $selected_type ? 'disabled' : '' ?>>
                                    <option value="Semi-expendable Equipment" <?= ($default_equipment_type == 'Semi-expendable Equipment') ? 'selected' : '' ?>>Semi-expendable Equipment</option>
                                    <option value="Property Plant Equipment (50K Above)" <?= ($default_equipment_type == 'Property Plant Equipment (50K Above)') ? 'selected' : '' ?>>Property Plant Equipment (50K Above)</option>
                                </select>
                                <?php if($selected_type): ?>
                                    <input type="hidden" id="batchEquipmentTypeHidden" value="<?= $default_equipment_type ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-12 mb-2">
                            <label class="form-label">Remarks (Optional)</label>
                            <div class="input-group">
                                <textarea class="form-control" id="batchRemarks" rows="2" placeholder="Batch generated items"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Generated Barcodes Preview Area -->
                <div class="mt-3">
                    <label class="form-label">Generated Barcodes Preview</label>
                    <div id="multiBarcodePreview" class="border p-3 rounded" style="max-height: 300px; overflow-y: auto; background: #f8f9fa;">
                        <p class="text-muted mb-0 text-center">Click "Generate & Preview" to see barcodes</p>
                    </div>
                    <div class="mt-2">
                        <!-- Action buttons for preview -->
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="generateAndPreviewMultiple()">
                            <i class="fas fa-eye"></i> Generate & Preview
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="useFirstBarcode()">
                            <i class="fas fa-check"></i> Use First
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="copyAllBarcodes()">
                            <i class="fas fa-copy"></i> Copy All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="syncAllFromMainForm()">
                            <i class="fas fa-sync"></i> Sync All
                        </button>
                    </div>
                </div>

                <!-- Text area showing generated property numbers -->
                <div class="mt-3">
                    <label class="form-label">Generated Property Numbers</label>
                    <textarea class="form-control" id="multiGeneratedList" rows="3" readonly 
                              style="font-family: monospace; font-size: 12px;"></textarea>
                    <div class="form-text">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updatePropertyNoFromFirst()">
                            <i class="fas fa-arrow-right"></i> Update Main Property No with First
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <!-- Save button -->
                <button type="button" class="btn btn-primary" onclick="saveMultipleBarcodes()">
                    <i class="fas fa-save"></i> Save to Inventory
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================================
BATCH BARCODE GENERATION MODAL (For existing items without barcodes)
============================================================================ -->
<div class="modal fade" id="barcodeBatchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title"><i class="fas fa-barcode"></i> Generate Missing Barcodes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <p>Items without barcodes:</p>
          <div class="table-responsive" style="max-height: 300px;">
              <table class="table table-sm">
                  <thead>
                      <tr>
                          <th><input type="checkbox" id="selectAllItems"></th>
                          <th>ID</th>
                          <th>Property No</th>
                          <th>Description</th>
                      </tr>
                  </thead>
                  <tbody id="missingBarcodesList">
                      <?php 
                      // Fetch items that don't have barcodes yet
                      $missing_res = $mysqli->query("SELECT id, property_no, description FROM inventory WHERE barcode_image IS NULL OR barcode_image = '' ORDER BY id");
                      while($item = $missing_res->fetch_assoc()): ?>
                      <tr>
                          <td><input type="checkbox" class="item-checkbox" value="<?= $item['id'] ?>" data-property="<?= e($item['property_no']) ?>"></td>
                          <td><?= $item['id'] ?></td>
                          <td><?= e($item['property_no']) ?></td>
                          <td><?= e($item['description']) ?></td>
                      </tr>
                      <?php endwhile; ?>
                  </tbody>
              </table>
          </div>
          <!-- Progress bar for batch generation -->
          <div class="mt-3">
              <div class="progress" style="height: 20px; display: none;" id="batchProgressContainer">
                  <div class="progress-bar progress-bar-striped progress-bar-animated" id="batchProgressBar" style="width: 0%">0%</div>
              </div>
              <div id="batchResult" class="mt-2"></div>
          </div>
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-primary" onclick="generateSelectedBarcodes()">
              <i class="fas fa-barcode"></i> Generate Selected
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          
      </div>
    </div>
  </div>
</div>

<!-- ============================================================================
JAVASCRIPT FUNCTIONS
============================================================================ -->
<script>
// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

// Set default equipment type from URL parameter
const defaultEquipmentType = '<?= $default_equipment_type ?>';

/**
 * Sets value for form elements
 * @param {string|Element} selectorOrEl - CSS selector or DOM element
 * @param {any} value - Value to set
 */
function setVal(selectorOrEl, value) {
    if (!selectorOrEl) return;
    const el = (typeof selectorOrEl === 'string') ? document.querySelector(selectorOrEl) : selectorOrEl;
    if (!el) return;
    if ('value' in el) el.value = (value !== undefined && value !== null) ? value : '';
}

/**
 * Safely parse JSON string
 * @param {string} jsonStr - JSON string to parse
 * @returns {object} Parsed object or empty object on error
 */
function safeParse(jsonStr){
    try {
        return jsonStr ? JSON.parse(jsonStr) : {};
    } catch (e) {
        console.error('Failed to parse JSON', e, jsonStr);
        return {};
    }
}

/**
 * Extracts prefix from property number
 * @param {string} propertyNo - Property number
 * @returns {string} Extracted prefix
 */
function extractPrefix(propertyNo) {
    if (!propertyNo) return '';
    
    // Remove any trailing numbers and dashes
    const match = propertyNo.match(/^([A-Za-z\-]+)/);
    if (match) {
        return match[1];
    }
    
    // Try to extract numeric prefix like "INV-" or "EQP-"
    const dashIndex = propertyNo.lastIndexOf('-');
    if (dashIndex > 0) {
        return propertyNo.substring(0, dashIndex + 1);
    }
    
    return '';
}

/**
 * Updates the prefix sync indicator
 */
function updatePrefixSyncIndicator() {
    const propertyNo = document.getElementById('invPropertyNo').value;
    const prefix = extractPrefix(propertyNo);
    const indicator = document.getElementById('propertyNoSyncIndicator');
    
    if (prefix && prefix.length > 0) {
        document.getElementById('currentPrefix').textContent = prefix;
        indicator.style.display = 'flex';
    } else {
        indicator.style.display = 'none';
    }
}

/**
 * Syncs prefix from property number to multiple barcode modal
 */
function syncPrefixToModal() {
    const propertyNo = document.getElementById('invPropertyNo').value;
    const prefix = extractPrefix(propertyNo);
    
    if (prefix) {
        document.getElementById('multiPrefix').value = prefix;
        showAlert(`Prefix "${prefix}" synced to Multiple Barcode modal`, 'success');
    } else {
        showAlert('No prefix detected in Property No', 'warning');
    }
}

/**
 * Shows alert message
 */
function showAlert(message, type = 'info') {
    // Remove existing alerts
    document.querySelectorAll('.temp-alert').forEach(el => el.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} temp-alert alert-dismissible fade show mt-2`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    const modalBody = document.querySelector('#multipleBarcodeModal .modal-body') || 
                     document.querySelector('#inventoryModal .modal-body');
    if (modalBody) {
        modalBody.insertBefore(alertDiv, modalBody.firstChild);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 3000);
    }
}

// ============================================================================
// QUANTITY HINT FUNCTIONS
// ============================================================================

/**
 * Shows quantity hint when quantity > 1
 */
function showQuantityHint() {
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    const hintDiv = document.getElementById('qtyHint');
    
    // Remove existing hint
    if (hintDiv) hintDiv.remove();
    
    if (quantity > 1) {
        // Remove highlighting
        const qtyField = document.getElementById('invQtyPhy');
        if (qtyField) {
            qtyField.style.borderColor = '';
            qtyField.style.backgroundColor = '';
        }
    } else {
        // Remove highlighting
        const qtyField = document.getElementById('invQtyPhy');
        if (qtyField) {
            qtyField.style.borderColor = '';
            qtyField.style.backgroundColor = '';
        }
    }
}

/**
 * Syncs quantity to count field when modal is open
 */
function syncQuantityToCount() {
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    const modalCountField = document.getElementById('multiCount');
    
    // If modal is open, update the count field
    const modalElement = document.getElementById('multipleBarcodeModal');
    if (modalElement && modalElement.classList.contains('show')) {
        if (modalCountField) {
            modalCountField.value = quantity;
        }
    }
}

// ============================================================================
// SYNC FUNCTIONS BETWEEN MAIN FORM AND MULTIPLE BARCODE MODAL
// ============================================================================

/**
 * Syncs all fields from main form to multiple barcode modal
 */
function syncAllFromMainForm() {
    syncDescriptionFromMainForm();
    syncLocationFromMainForm();
    syncUOMFromMainForm();
    syncConditionFromMainForm();
    syncFundFromMainForm();
    syncEquipmentTypeFromMainForm();
    syncRemarksFromMainForm();
    syncQuantityFromMainForm();
    syncFromPropertyNo();
    
    showAlert('All fields synced from main form!', 'success');
}

/**
 * Syncs description from main form
 */
function syncDescriptionFromMainForm() {
    const mainDesc = document.getElementById('invDescription').value;
    const batchDesc = document.getElementById('batchDescription');
    if (mainDesc && batchDesc) {
        batchDesc.value = mainDesc;
    }
}

/**
 * Syncs location from main form
 */
function syncLocationFromMainForm() {
    const mainLocation = document.getElementById('invLocation').value;
    const batchLocation = document.getElementById('batchLocation');
    if (mainLocation && batchLocation) {
        batchLocation.value = mainLocation;
    }
}

/**
 * Syncs UOM from main form
 */
function syncUOMFromMainForm() {
    const mainUOM = document.getElementById('invUom').value;
    const batchUOM = document.getElementById('batchUOM');
    if (mainUOM && batchUOM) {
        batchUOM.value = mainUOM;
    }
}

/**
 * Syncs condition from main form
 */
function syncConditionFromMainForm() {
    const mainCondition = document.getElementById('invCondition').value;
    const batchCondition = document.getElementById('batchCondition');
    if (mainCondition && batchCondition) {
        batchCondition.value = mainCondition;
    }
}

/**
 * Syncs fund cluster from main form
 */
function syncFundFromMainForm() {
    const mainFund = document.getElementById('invFund').value;
    const batchFund = document.getElementById('batchFund');
    if (mainFund && batchFund) {
        batchFund.value = mainFund;
    }
}

/**
 * Syncs equipment type from main form
 */
function syncEquipmentTypeFromMainForm() {
    // If type is disabled (filtered), use hidden field or default value
    const mainEquipmentTypeSelect = document.getElementById('invType');
    const batchEquipmentType = document.getElementById('batchEquipmentType');
    
    if (batchEquipmentType) {
        if (mainEquipmentTypeSelect && !mainEquipmentTypeSelect.disabled) {
            const mainEquipmentType = mainEquipmentTypeSelect.value;
            if (mainEquipmentType) {
                batchEquipmentType.value = mainEquipmentType;
            }
        } else if (defaultEquipmentType) {
            batchEquipmentType.value = defaultEquipmentType;
        }
    }
}

/**
 * Syncs remarks from main form
 */
function syncRemarksFromMainForm() {
    const mainRemarks = document.getElementById('invRemarks').value;
    const batchRemarks = document.getElementById('batchRemarks');
    if (batchRemarks) {
        batchRemarks.value = mainRemarks;
    }
}

/**
 * Syncs quantity from main form
 */
function syncQuantityFromMainForm() {
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    const modalCountField = document.getElementById('multiCount');
    if (modalCountField) {
        modalCountField.value = quantity;
    }
}

/**
 * Syncs prefix from property number in main form
 */
function syncFromPropertyNo() {
    const propertyNo = document.getElementById('invPropertyNo').value;
    const prefix = extractPrefix(propertyNo);
    
    if (prefix) {
        document.getElementById('multiPrefix').value = prefix;
        
        // Also try to extract start number
        const numericPart = propertyNo.replace(prefix, '');
        const numMatch = numericPart.match(/\d+/);
        if (numMatch) {
            const startNum = parseInt(numMatch[0]);
            document.getElementById('multiStartNum').value = startNum;
        }
        
        showAlert(`Prefix "${prefix}" synced from Property No`, 'success');
    } else {
        showAlert('No prefix detected in Property No', 'warning');
    }
}

/**
 * Extracts prefix from property number and shows it
 */
function extractPrefixFromPropertyNo() {
    const propertyNo = document.getElementById('invPropertyNo').value;
    const prefix = extractPrefix(propertyNo);
    
    if (prefix) {
        showAlert(`Detected prefix: "${prefix}"`, 'info');
        document.getElementById('multiPrefix').value = prefix;
    } else {
        showAlert('No prefix pattern found (e.g., "INV-", "EQP-")', 'warning');
    }
}

/**
 * Updates main Property No with first generated barcode
 */
function updatePropertyNoFromFirst() {
    if (generatedBarcodes.length === 0) {
        alert('No barcodes generated yet');
        return;
    }
    
    const firstBarcode = generatedBarcodes[0];
    if (firstBarcode.success) {
        document.getElementById('invPropertyNo').value = firstBarcode.property_no;
        updateBarcodePreview(firstBarcode.property_no);
        updatePrefixSyncIndicator();
        showAlert(`Property No updated to: ${firstBarcode.property_no}`, 'success');
    } else {
        alert('First barcode generation failed');
    }
}

// ============================================================================
// SINGLE BARCODE FUNCTIONS
// ============================================================================

/**
 * Updates barcode preview when property number changes
 * @param {string} propertyNo - Property number to generate barcode for
 */
function updateBarcodePreview(propertyNo) {
    const previewDiv = document.getElementById('barcodePreview');
    if (!propertyNo || propertyNo.trim() === '') {
        previewDiv.innerHTML = '<p class="text-muted mb-0">Enter Property No to see barcode preview</p>';
        updatePrefixSyncIndicator();
        return;
    }
    
    // Update prefix sync indicator
    updatePrefixSyncIndicator();
    
    // Show loading indicator
    previewDiv.innerHTML = '<p class="text-info"><i class="fas fa-spinner fa-spin"></i> Generating preview...</p>';
    
    // Call PHP barcode generator via AJAX
    fetch('generate_barcode.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'property_no=' + encodeURIComponent(propertyNo)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.barcode_image) {
            // Display generated barcode image
            previewDiv.innerHTML = `
                <img src="${data.barcode_image}" style="max-width: 100%; height: 60px;">
                <div class="barcode-text mt-1" style="font-family: monospace; font-size: 12px;">${propertyNo}</div>
            `;
        } else {
            previewDiv.innerHTML = '<p class="text-danger">Failed to generate preview</p>';
        }
    })
    .catch(error => {
        previewDiv.innerHTML = '<p class="text-danger">Error generating preview</p>';
    });
}

/**
 * Auto-generates a single property number in YYMM-RANDOM format
 */
function autoGeneratePropertyNo() {
    const date = new Date();
    const year = date.getFullYear().toString().substr(-2);
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    
    if (quantity > 1) {
        // Ask user if they want multiple barcodes
        if (confirm(`Quantity is ${quantity}. Generate ${quantity} sequential barcodes instead?`)) {
            openMultipleBarcodeModal();
            return;
        }
    }
    
    // Single barcode generation
    const random = Math.floor(1000 + Math.random() * 9000);
    const propertyNo = `${year}${month}-${random}`;
    setVal('#invPropertyNo', propertyNo);
    updateBarcodePreview(propertyNo);
    updatePrefixSyncIndicator();
}

/**
 * Generates property number based on selected equipment and location
 */
function generateFromEquipment() {
    const equipmentSelect = document.getElementById('invEquipment');
    const locationSelect = document.getElementById('invLocation');
    
    if (!equipmentSelect || !locationSelect || !equipmentSelect.value || !locationSelect.value) {
        alert('Please select equipment and location first');
        return;
    }
    
    // Get codes from data attributes
    const equipmentCode = equipmentSelect.options[equipmentSelect.selectedIndex].getAttribute('data-code') || 'EQP';
    const locationCode = locationSelect.options[locationSelect.selectedIndex].getAttribute('data-code') || 'LOC';
    const seq = Math.floor(100 + Math.random() * 900);
    
    const propertyNo = `${equipmentCode}-${locationCode}-${seq}`;
    setVal('#invPropertyNo', propertyNo);
    updateBarcodePreview(propertyNo);
    updatePrefixSyncIndicator();
}

/**
 * Generates property number based on selected department
 */
function generateFromDepartment() {
    const locationSelect = document.getElementById('invLocation');
    
    if (!locationSelect || !locationSelect.value) {
        alert('Please select location first');
        return;
    }
    
    const locationCode = locationSelect.options[locationSelect.selectedIndex].getAttribute('data-code') || 'DEPT';
    const date = new Date();
    const timestamp = date.getTime().toString().substr(-6);
    
    const propertyNo = `${locationCode}-${date.getFullYear()}-${timestamp}`;
    setVal('#invPropertyNo', propertyNo);
    updateBarcodePreview(propertyNo);
    updatePrefixSyncIndicator();
}

/**
 * Generates sequential property number based on last used number
 */
function generateSequential() {
    // Fetch last property number from server
    fetch('get_last_property_no.php')
    .then(response => response.json())
    .then(data => {
        let nextNum = 1;
        if (data.last_property_no) {
            // Extract number from property no (assuming format like INV-0001)
            const matches = data.last_property_no.match(/\d+/);
            if (matches) {
                nextNum = parseInt(matches[0]) + 1;
            }
        }
        const paddedNum = nextNum.toString().padStart(4, '0');
        const propertyNo = `INV-${paddedNum}`;
        setVal('#invPropertyNo', propertyNo);
        updateBarcodePreview(propertyNo);
        updatePrefixSyncIndicator();
    })
    .catch(error => {
        console.error('Error:', error);
        autoGeneratePropertyNo(); // Fallback
    });
}

/**
 * Generates barcode for existing item
 * @param {number} id - Item ID
 * @param {string} propertyNo - Property number
 */
function generateBarcodeForItem(id, propertyNo) {
    if (!confirm('Generate barcode for this item?')) return;
    
    fetch('generate_barcode.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'item_id=' + id + '&property_no=' + encodeURIComponent(propertyNo)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Barcode generated successfully!');
            location.reload(); // Refresh page to show new barcode
        } else {
            alert('Error: ' + (data.message || 'Failed to generate barcode'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

// ============================================================================
// PRINT FUNCTIONS
// ============================================================================

/**
 * Prints a single barcode label
 * @param {string} propertyNo - Property number
 * @param {string} barcodeImage - Base64 barcode image
 * @param {string} description - Item description
 */
function printSingleBarcode(propertyNo, barcodeImage, description) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Barcode - ${propertyNo}</title>
            <style>
                @media print {
                    @page { size: auto; margin: 0; }
                    body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
                    .barcode-label {
                        width: 2in;
                        height: 1in;
                        padding: 5px;
                        text-align: center;
                        border: 1px dotted #ccc;
                        margin: 2px;
                        display: inline-block;
                    }
                    .company-name {
                        font-size: 8px;
                        font-weight: bold;
                        margin-bottom: 2px;
                    }
                    .item-description {
                        font-size: 7px;
                        margin-bottom: 2px;
                        height: 14px;
                        overflow: hidden;
                    }
                    .barcode-img {
                        width: 100%;
                        height: 30px;
                        object-fit: contain;
                    }
                    .property-no {
                        font-size: 9px;
                        font-family: monospace;
                        margin-top: 2px;
                    }
                }
            </style>
        </head>
        <body onload="window.print(); setTimeout(() => window.close(), 1000)">
            <div class="barcode-label">
                <div class="company-name">INVENTORY SYSTEM</div>
                <div class="item-description">${(description || '').substring(0, 30)}</div>
                <img class="barcode-img" src="${barcodeImage}" alt="Barcode">
                <div class="property-no">${propertyNo}</div>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

/**
 * Prints current barcode from the main form
 */
function printCurrentBarcode() {
    const propertyNo = document.getElementById('invPropertyNo').value;
    const previewDiv = document.getElementById('barcodePreview');
    const barcodeImg = previewDiv.querySelector('img');
    
    if (!propertyNo || !barcodeImg) {
        alert('Please generate a barcode first');
        return;
    }
    
    const description = document.getElementById('invDescription').value || '';
    printSingleBarcode(propertyNo, barcodeImg.src, description);
}

// ============================================================================
// MULTIPLE BARCODE FUNCTIONS
// ============================================================================

let generatedBarcodes = []; // Array to store generated barcodes

/**
 * Opens multiple barcode modal with quantity sync
 */
function openMultipleBarcodeModal() {
    // Get quantity from main form
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    
    // Open modal
    const modal = new bootstrap.Modal(document.getElementById('multipleBarcodeModal'));
    modal.show();
    
    // Auto-populate count with quantity from main form
    document.getElementById('multiCount').value = quantity;
    
    // Auto-sync prefix from property number
    const propertyNo = document.getElementById('invPropertyNo').value;
    const prefix = extractPrefix(propertyNo);
    if (prefix) {
        document.getElementById('multiPrefix').value = prefix;
    }
    
    // Auto-sync all fields
    setTimeout(() => {
        syncAllFromMainForm();
    }, 300);
    
    // Clear previous data
    generatedBarcodes = [];
    document.getElementById('multiGeneratedList').value = '';
    document.getElementById('multiBarcodePreview').innerHTML = 
        '<p class="text-muted mb-0 text-center">Click "Generate & Preview" to see barcodes</p>';
    
    // Auto-generate if quantity is reasonable
    if (quantity > 1 && quantity <= 50) {
        setTimeout(() => {
            generateAndPreviewMultiple();
        }, 500);
    }
}

/**
 * Generates and previews multiple barcodes
 */
async function generateAndPreviewMultiple() {
    // Get user inputs - priority: modal count, then form quantity
    let count = parseInt(document.getElementById('multiCount').value);
    const formQuantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    
    // If count is not set or is 0, use form quantity
    if (!count || count <= 0) {
        count = formQuantity;
        document.getElementById('multiCount').value = count;
    }
    
    const prefix = document.getElementById('multiPrefix').value || '';
    const startNum = parseInt(document.getElementById('multiStartNum').value) || 1;
    const padDigits = parseInt(document.getElementById('multiPadDigits').value) || 4;
    const previewFormat = document.getElementById('multiPreviewFormat').value;
    
    // Validate count
    if (count > 100) {
        alert('Maximum 100 barcodes at a time');
        return;
    }
    
    // Show loading
    const previewDiv = document.getElementById('multiBarcodePreview');
    previewDiv.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Generating ' + count + ' barcodes...</div>';
    
    // Generate property numbers
    generatedBarcodes = [];
    const propertyNos = [];
    
    for (let i = 0; i < count; i++) {
        const num = startNum + i;
        const paddedNum = num.toString().padStart(padDigits, '0');
        const propertyNo = prefix + paddedNum;
        propertyNos.push(propertyNo);
    }
    
    // Update textarea
    document.getElementById('multiGeneratedList').value = propertyNos.join('\n');
    
    try {
        // Generate barcodes in batches of 10
        const batchSize = 10;
        for (let i = 0; i < propertyNos.length; i += batchSize) {
            const batch = propertyNos.slice(i, i + batchSize);
            const batchPromises = batch.map(propertyNo => generateSingleBarcodeForBatch(propertyNo));
            const batchResults = await Promise.all(batchPromises);
            generatedBarcodes.push(...batchResults);
            
            // Update progress
            const progress = Math.round((i + batch.length) / propertyNos.length * 100);
            previewDiv.innerHTML = `<div class="text-center py-3">
                <div class="spinner-border spinner-border-sm"></div>
                <div>Generating barcodes... ${progress}%</div>
            </div>`;
        }
        
        // Display preview
        displayBarcodePreview(previewFormat);
        
    } catch (error) {
        console.error('Error generating barcodes:', error);
        previewDiv.innerHTML = '<div class="alert alert-danger">Error generating barcodes. Please try again.</div>';
    }
}

/**
 * Generates single barcode for batch processing
 * @param {string} propertyNo - Property number
 * @returns {object} Barcode data object
 */
async function generateSingleBarcodeForBatch(propertyNo) {
    try {
        const response = await fetch('generate_barcode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'property_no=' + encodeURIComponent(propertyNo)
        });
        
        const data = await response.json();
        
        if (data.success && data.barcode_image) {
            return {
                property_no: propertyNo,
                barcode_image: data.barcode_image,
                success: true
            };
        } else {
            return {
                property_no: propertyNo,
                barcode_image: null,
                success: false,
                error: data.message || 'Unknown error'
            };
        }
    } catch (error) {
        return {
            property_no: propertyNo,
            barcode_image: null,
            success: false,
            error: 'Network error'
        };
    }
}

/**
 * Displays barcode preview in selected format
 * @param {string} format - 'grid' or 'list'
 */
function displayBarcodePreview(format) {
    const previewDiv = document.getElementById('multiBarcodePreview');
    
    if (generatedBarcodes.length === 0) {
        previewDiv.innerHTML = '<p class="text-muted mb-0 text-center">No barcodes generated</p>';
        return;
    }
    
    let previewHTML = '';
    const successCount = generatedBarcodes.filter(b => b.success).length;
    const failCount = generatedBarcodes.length - successCount;
    
    // Grid view
    if (format === 'grid') {
        previewHTML = '<div class="barcode-preview-grid">';
        generatedBarcodes.forEach(barcode => {
            if (barcode.success) {
                previewHTML += `
                    <div class="barcode-preview-item">
                        <img src="${barcode.barcode_image}" class="barcode-preview-img" alt="${barcode.property_no}">
                        <div class="small text-muted mt-1">${barcode.property_no}</div>
                    </div>
                `;
            } else {
                previewHTML += `
                    <div class="barcode-preview-item border-danger">
                        <div class="text-danger small">${barcode.property_no}</div>
                        <div class="text-danger very-small">Failed</div>
                    </div>
                `;
            }
        });
        previewHTML += '</div>';
    } else {
        // List view
        previewHTML = '<div class="barcode-list-container">';
        generatedBarcodes.forEach(barcode => {
            if (barcode.success) {
                previewHTML += `
                    <div class="barcode-list-item">
                        <div style="width: 100px; margin-right: 10px;">
                            <img src="${barcode.barcode_image}" style="max-width: 100%; height: 30px;">
                        </div>
                        <div style="flex: 1;">
                            <code>${barcode.property_no}</code>
                        </div>
                        <span class="badge bg-success">✓</span>
                    </div>
                `;
            } else {
                previewHTML += `
                    <div class="barcode-list-item text-danger">
                        <div style="width: 100px; margin-right: 10px; text-align: center;">
                            <i class="fas fa-times text-danger"></i>
                        </div>
                        <div style="flex: 1;">
                            <code>${barcode.property_no}</code>
                            <div class="very-small">${barcode.error || 'Failed'}</div>
                        </div>
                        <span class="badge bg-danger">✗</span>
                    </div>
                `;
            }
        });
        previewHTML += '</div>';
    }
    
    // Add summary
    previewHTML += `
        <div class="mt-2 p-2 bg-light border rounded">
            <div class="row">
                <div class="col">
                    <small>Total: <strong>${generatedBarcodes.length}</strong></small>
                </div>
                <div class="col">
                    <small>Success: <span class="text-success"><strong>${successCount}</strong></span></small>
                </div>
                <div class="col">
                    <small>Failed: <span class="text-danger"><strong>${failCount}</strong></span></small>
                </div>
            </div>
        </div>
    `;
    
    previewDiv.innerHTML = previewHTML;
}

/**
 * Uses first generated barcode in main form
 */
function useFirstBarcode() {
    if (generatedBarcodes.length === 0) {
        alert('No barcodes generated yet');
        return;
    }
    
    const firstBarcode = generatedBarcodes[0];
    if (firstBarcode.success) {
        document.getElementById('invPropertyNo').value = firstBarcode.property_no;
        updateBarcodePreview(firstBarcode.property_no);
        updatePrefixSyncIndicator();
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('multipleBarcodeModal'));
        if (modal) {
            modal.hide();
        }
        
        // Show success message
        showAlert(`Using barcode: ${firstBarcode.property_no}`, 'success');
    } else {
        alert('First barcode generation failed');
    }
}

/**
 * Copies all generated property numbers to clipboard
 */
function copyAllBarcodes() {
    if (generatedBarcodes.length === 0) {
        alert('No barcodes to copy');
        return;
    }
    
    const propertyNos = generatedBarcodes.map(b => b.property_no).join('\n');
    
    navigator.clipboard.writeText(propertyNos).then(() => {
        showAlert('All property numbers copied to clipboard!', 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy to clipboard');
    });
}

/**
 * Saves multiple barcodes to inventory with batch settings
 */
/**
 * Saves multiple barcodes to inventory with batch settings
 */
async function saveMultipleBarcodes() {
    if (generatedBarcodes.length === 0) {
        alert('No barcodes to save');
        return;
    }
    
    // Get batch settings - mirroring your single form submission
    const batchData = {
        article_name: document.getElementById('batchDescription').value,
        description: document.getElementById('batchDescription').value,
        location_id: document.getElementById('batchLocation').value,
        uom: document.getElementById('batchUOM').value,
        condition_text: document.getElementById('batchCondition').value,
        fund_cluster: document.getElementById('batchFund').value,
        type_equipment: document.getElementById('batchEquipmentType').value || defaultEquipmentType || 'Semi-expendable Equipment',
        remarks: document.getElementById('batchRemarks').value || 'Batch generated',
        category: 'Batch Generated',
        // Set these to empty strings - will become 0 in PHP (like your single insert)
        approved_by: '',
        verified_by: '',
        allocate_to: '',
        section_id: '',
        equipment_id: '',
        unit_value: 0,
        qty_property_card: 1,
        qty_physical_count: 1
    };
    
    // Validate required fields
    if (!batchData.description || !batchData.location_id) {
        alert('Please fill in Description and Location in Batch Settings');
        return;
    }
    
    const successBarcodes = generatedBarcodes.filter(b => b.success);
    if (successBarcodes.length === 0) {
        alert('No successful barcodes to save');
        return;
    }
    
    if (!confirm(`Save ${successBarcodes.length} inventory items with these settings?`)) {
        return;
    }
    
    const previewDiv = document.getElementById('multiBarcodePreview');
    previewDiv.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Saving to database...</div>';
    
    try {
        // Prepare items for saving - exactly matching your single insert structure
        const itemsToSave = successBarcodes.map((barcode, index) => ({
            ...batchData,
            property_no: barcode.property_no,
            barcode_image: barcode.barcode_image,
            // Keep description simple, don't add unit number if not needed
            article_name: batchData.description,
            description: batchData.description
        }));
        
        // Send to PHP for saving
        const response = await fetch('save_multiple_inventory.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                items: itemsToSave,
                user_id: <?= $u['id'] ?? 0 ?>
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            previewDiv.innerHTML = `
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> Success!</h5>
                    <p>Saved ${result.saved} items to inventory.</p>
                    <p>Failed: ${result.failed}</p>
                    <button class="btn btn-sm btn-primary" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Refresh Page
                    </button>
                </div>
            `;
            
            // Clear generated barcodes
            generatedBarcodes = [];
            document.getElementById('multiGeneratedList').value = '';
            
            // Close modal after 2 seconds
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('multipleBarcodeModal'));
                if (modal) {
                    modal.hide();
                }
            }, 2000);
        } else {
            previewDiv.innerHTML = `<div class="alert alert-danger">Error: ${result.message}</div>`;
            if (result.errors && result.errors.length > 0) {
                console.error('Errors:', result.errors);
            }
        }
        
    } catch (error) {
        console.error('Save error:', error);
        previewDiv.innerHTML = '<div class="alert alert-danger">Network error saving items</div>';
    }
}

// ============================================================================
// BATCH BARCODE GENERATION (For existing items)
// ============================================================================

// Select all checkboxes
document.getElementById('selectAllItems').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

/**
 * Generates barcodes for selected existing items
 */
async function generateSelectedBarcodes() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Please select items to generate barcodes for');
        return;
    }
    
    if (!confirm(`Generate barcodes for ${checkboxes.length} selected items?`)) return;
    
    const progressBar = document.getElementById('batchProgressBar');
    const progressContainer = document.getElementById('batchProgressContainer');
    const resultDiv = document.getElementById('batchResult');
    
    // Show progress bar
    progressContainer.style.display = 'block';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    resultDiv.innerHTML = '';
    
    let successCount = 0;
    let failCount = 0;
    
    // Process items one by one
    for (let i = 0; i < checkboxes.length; i++) {
        const checkbox = checkboxes[i];
        const id = checkbox.value;
        const propertyNo = checkbox.getAttribute('data-property');
        
        try {
            const response = await fetch('generate_barcode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'item_id=' + id + '&property_no=' + encodeURIComponent(propertyNo)
            });
            
            const data = await response.json();
            if (data.success) {
                successCount++;
            } else {
                failCount++;
            }
        } catch (error) {
            failCount++;
        }
        
        // Update progress
        const progress = Math.round(((i + 1) / checkboxes.length) * 100);
        progressBar.style.width = progress + '%';
        progressBar.textContent = progress + '%';
    }
    
    // Show results
    resultDiv.innerHTML = `
        <div class="alert ${failCount > 0 ? 'alert-warning' : 'alert-success'}">
            <strong>Batch generation complete!</strong><br>
            Successful: ${successCount}<br>
            Failed: ${failCount}
        </div>
    `;
    
    // Reload page if all successful
    if (failCount === 0) {
        setTimeout(() => {
            location.reload();
        }, 2000);
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Populates inventory modal for editing
 * @param {string} id - Item ID
 * @param {object} data - Item data
 * @param {string} defaultType - Default equipment type from URL
 */
window.openInventoryModal = function (id = '', data = {}, defaultType = '') {
    const isEdit = id ? true : false;
    setVal('#inventoryModalLabel', isEdit ? 'Edit Item' : 'Add Item');
    setVal('#invId', id || '');
    
    // Populate all form fields
    setVal('#invArticleName', data.article_name || '');
    
    // Handle equipment type - use default from URL if adding new and no data
    if (!isEdit && defaultType && !data.type_equipment) {
        setVal('#invType', defaultType);
    } else {
        setVal('#invType', data.type_equipment || '');
    }
    
    setVal('#invPropertyNo', data.property_no || '');
    setVal('#invEquipment', data.equipment_id || '');
    setVal('#invCategory', data.equip_category || data.category || '');
    setVal('#invDescription', data.description || '');
    setVal('#invUnitValue', data.unit_value || '');
    setVal('#invUom', data.uom || '');
    setVal('#invQtyCard', data.qty_property_card || '');
    setVal('#invQtyPhy', data.qty_physical_count || '');
    setVal('#invLocation', data.location_id || '');
    setVal('#invSection', data.section_id || '');
    setVal('#invCondition', data.condition_text || '');
    setVal('#invAllocate', data.allocate_to || '');
    setVal('#invFund', data.fund_cluster || '');
    setVal('#invRemarks', data.remarks || '');
    setVal('#invApproved', data.approved_by || '');
    setVal('#invVerified', data.verified_by || '');
    
    // Handle certified correct multi-select
    const certSelect = document.querySelector('[name="certified_correct[]"]');
    try {
        if (certSelect && data.certified_correct) {
            const parsed = JSON.parse(data.certified_correct);
            if (Array.isArray(parsed)) {
                Array.from(certSelect.options).forEach(opt => {
                    opt.selected = parsed.includes(parseInt(opt.value));
                });
            }
        }
    } catch (e) {
        console.warn('Error populating certified_correct:', e);
    }
    
    // Update barcode preview
    if (data.property_no) {
        updateBarcodePreview(data.property_no);
    }
    
    // Trigger equipment change to update category
    const equipmentSelect = document.getElementById('invEquipment');
    if (equipmentSelect && data.equipment_id) {
        equipmentSelect.dispatchEvent(new Event('change'));
    }
    
    // Show quantity hint if quantity > 1
    setTimeout(() => {
        showQuantityHint();
    }, 100);
};

// ============================================================================
// EVENT LISTENERS
// ============================================================================

document.addEventListener('DOMContentLoaded', function () {
    // Update barcode preview when property number changes
    const propertyNoInput = document.getElementById('invPropertyNo');
    if (propertyNoInput) {
        propertyNoInput.addEventListener('input', function() {
            updateBarcodePreview(this.value);
        });
    }
    
    // Update category when equipment changes
    const equipmentSelect = document.getElementById('invEquipment');
    if (equipmentSelect) {
        equipmentSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const categoryInput = document.getElementById('invCategory');
            if (categoryInput && selected) {
                categoryInput.value = selected.getAttribute('data-category') || '';
            }
        });
    }
    
    // Sync quantity when quantity field changes
    const qtyInput = document.getElementById('invQtyPhy');
    if (qtyInput) {
        qtyInput.addEventListener('input', function() {
            showQuantityHint();
            syncQuantityToCount();
        });
        
        qtyInput.addEventListener('change', function() {
            showQuantityHint();
            syncQuantityToCount();
        });
    }
    
    // Edit buttons
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id || '';
            const data = safeParse(this.dataset.item);
            openInventoryModal(id, data, defaultEquipmentType);
            const modalEl = document.getElementById('inventoryModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        });
    });
    
    // If default type is set from URL, pre-select it in the form when adding new
    if (defaultEquipmentType) {
        // This will be used when opening the modal for new items
        console.log('Default equipment type from URL:', defaultEquipmentType);
    }
});


// ============================================================================
// PRINT BARCODE LABELS (Stub function)
// ============================================================================
function printBarcodeLabels() {
    alert('Select items and use the Print button next to each barcode, or implement bulk printing as needed.');
}
</script>

<?php 
$mysqli->close();
ob_end_flush(); 
?>