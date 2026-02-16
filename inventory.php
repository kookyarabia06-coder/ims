<?php
ob_start();
require_once 'auth.php';
require_login();
include 'header.php';

// KOOKY KAPAG NAG ERROR SA BARCODE GENERATION PAT 
require_once __DIR__ . '/vendor/autoload.php';
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

// =============================================================================
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

// dropdown dataaaaa =====================================================================
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

// Handle delete action  =====================================================================
if(isset($_GET['action']) && $_GET['action'] === 'delete'){
    $id = intval($_GET['id']);
    $mysqli->query("DELETE FROM inventory WHERE id = $id");
    header('Location: inventory.php' . ($selected_type ? '?type=' . $selected_type : ''));
    exit;
}

// Handle Add/Edit form submission  =====================================================================
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;

    // Collect and sanitize POST data - FIXED: Use NULL instead of 0 for foreign keys
    $article_name = trim($_POST['article_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $property_no = trim($_POST['property_no'] ?? '');
    $uom = trim($_POST['uom'] ?? '');
    
    $qty_property_card = floatval($_POST['qty_property_card'] ?? 0);
    $qty_physical_count = floatval($_POST['qty_physical_count'] ?? 0);
    $unit_value = floatval($_POST['unit_value'] ?? 0);

    $location_id = isset($_POST['location_id']) && $_POST['location_id'] !== '' ? intval($_POST['location_id']) : null;
    $condition_text = trim($_POST['condition_text'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $fund_cluster = trim($_POST['fund_cluster'] ?? '');
    $type_equipment = trim($_POST['type_equipment'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    $approved_by = isset($_POST['approved_by']) && $_POST['approved_by'] !== '' ? intval($_POST['approved_by']) : null;
    $verified_by = isset($_POST['verified_by']) && $_POST['verified_by'] !== '' ? intval($_POST['verified_by']) : null;
    $section_id = isset($_POST['section_id']) && $_POST['section_id'] !== '' ? intval($_POST['section_id']) : null;
    $equipment_id = isset($_POST['equipment_id']) && $_POST['equipment_id'] !== '' ? intval($_POST['equipment_id']) : null;
    $allocate_to = isset($_POST['allocate_to']) && $_POST['allocate_to'] !== '' ? intval($_POST['allocate_to']) : null;

    // Handle multi-select certified correct  =====================================================================
    $certified = $_POST['certified_correct'] ?? [];
    $cert_ids = array_map('intval', $certified);
    $cert_json = !empty($cert_ids) ? json_encode($cert_ids) : null;

    // AUTO-GENERATE BARCODE SECTION  =====================================================================
    $barcode_data = null;
    $barcode_image = null;
    
    if ($property_no) {
        try {
            // Create barcode generator instance  =====================================================================
            $generator = new BarcodeGeneratorPNG();
            
            // Generate barcode image using CODE 128 format  =====================================================================
            $barcode_image_data = $generator->getBarcode($property_no, $generator::TYPE_CODE_128);
            
            // Convert binary image to base64 for database storage  =====================================================================
            $barcode_image = 'data:image/png;base64,' . base64_encode($barcode_image_data);
            $barcode_data = $property_no; // Store the property number as barcode data  =====================================================================
            
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
                // UPDATE WITH BARCODE REGENERATION - 22 parameters
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
                
                if (!$stmt) {
                    throw new Exception($mysqli->error);
                }
                
                $stmt->bind_param(
                    "ssssddisssiiisdississi", // 22 characters for 22 parameters
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
                    $id // 22nd parameter
                );
            } else {
                // Keep existing barcode - NO barcode fields - 20 parameters
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
                
                if (!$stmt) {
                    throw new Exception($mysqli->error);
                }
                
                $stmt->bind_param(
                    "ssssddisssiiisdissii", // 20 characters for 20 parameters
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
                    $id // 20th parameter
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
            // INSERT NEW RECORD - 21 parameters
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
                "ssssddisssiiisdississ", // 21 characters for 21 parameters
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

// Re-fetch employees for dropdowns (need to restart pagkatapos ng petch)
$employees_res->data_seek(0);
$employees_for_batch = $mysqli->query("SELECT id, firstname, lastname FROM employees ORDER BY firstname");
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
    .page-title-badge {
        font-size: 14px;
        font-weight: normal;
        margin-left: 10px;
        padding: 5px 10px;
    }
    .employee-section {
        background: #f8f9fa;
        border-radius: 5px;
        padding: 15px;
        margin-top: 15px;
        border-left: 4px solid #007bff;
    }
</style>
<!-- Add this after your existing CSS styles and before the main inventory interface -->

<!-- Enhanced Barcode Scanner Modal (Camera + USB Scanner) -->
<div class="modal fade" id="barcodeScannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-camera"></i> Barcode Scanner
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Mode Selection Tabs -->
                <ul class="nav nav-tabs mb-3" id="scannerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="camera-tab" data-bs-toggle="tab" data-bs-target="#camera" type="button" role="tab">
                            <i class="fas fa-camera me-2"></i>Phone Camera
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="usb-tab" data-bs-toggle="tab" data-bs-target="#usb" type="button" role="tab">
                            <i class="fas fa-usb me-2"></i>USB Scanner
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">
                            <i class="fas fa-keyboard me-2"></i>Manual Entry
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="scannerTabContent">
                    <!-- Camera Mode (For Phones) -->
                    <div class="tab-pane fade show active" id="camera" role="tabpanel">
                        <div class="row">
                            <div class="col-md-7">
                                <div class="scanner-container border rounded p-2 bg-dark text-center" style="min-height: 300px;">
                                    <div id="scanner-viewport" style="width: 100%; height: 280px;"></div>
                                    <div id="scanner-status" class="mt-2 text-white">
                                        <span class="spinner-border spinner-border-sm text-light me-2"></span>
                                        Initializing camera...
                                    </div>
                                </div>
                                
                                <div class="scanner-controls mt-3 text-center">
                                    <button class="btn btn-sm btn-primary" onclick="startScanner()">
                                        <i class="fas fa-play"></i> Start Camera
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="stopScanner()">
                                        <i class="fas fa-stop"></i> Stop Camera
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="switchCamera()">
                                        <i class="fas fa-sync-alt"></i> Switch Camera
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Scan Results Panel -->
                            <div class="col-md-5">
                                <div class="scan-results-panel border rounded p-3" style="height: 400px; overflow-y: auto;">
                                    <h6 class="border-bottom pb-2">
                                        <i class="fas fa-history"></i> Recent Scans
                                        <span class="badge bg-secondary" id="scanCount">0</span>
                                    </h6>
                                    <div id="scanHistory" class="mb-3">
                                        <p class="text-muted text-center">No scans yet</p>
                                    </div>
                                    
                                    <h6 class="border-bottom pb-2 mt-3">
                                        <i class="fas fa-info-circle"></i> Current Item
                                    </h6>
                                    <div id="currentItemDetails" class="mt-2">
                                        <p class="text-muted text-center">Scan a barcode to view details</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- USB Scanner Mode (For Desktop with USB Scanner) -->
                    <div class="tab-pane fade" id="usb" role="tabpanel">
                        <div class="text-center mb-3">
                            <i class="fas fa-usb fa-3x text-primary mb-2"></i>
                            <h5>USB Scanner Mode</h5>
                            <p class="text-muted">Connect your USB barcode scanner and start scanning</p>
                        </div>

                        <!-- USB Scanner Status -->
                        <div class="alert alert-info" id="usbStatus">
                            <i class="fas fa-info-circle"></i>
                            USB Scanner Mode Active - Scan a barcode
                        </div>

                        <!-- Last Scanned Barcode -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <i class="fas fa-barcode"></i> Last Scanned Barcode
                            </div>
                            <div class="card-body text-center">
                                <h3 id="lastScannedBarcode" class="text-primary">-</h3>
                                <div id="lastScanTime" class="small text-muted"></div>
                            </div>
                        </div>

                        <!-- Auto-focus input for USB scanner -->
                        <div class="form-group">
                            <label for="usbScannerInput" class="form-label">
                                <i class="fas fa-magic"></i> Scanner Input Field
                                <small class="text-muted">(Click here to activate USB scanner)</small>
                            </label>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="usbScannerInput" 
                                       placeholder="Scan barcode with USB scanner..."
                                       autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" onclick="clearUsbInput()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-lightbulb text-warning"></i>
                                Click the input field, then scan with your USB scanner
                            </div>
                        </div>

                        <!-- Scanner Settings -->
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6><i class="fas fa-cog"></i> USB Scanner Settings</h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="autoSubmit" checked>
                                <label class="form-check-label" for="autoSubmit">
                                    Auto-submit after scan
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="playBeep" checked>
                                <label class="form-check-label" for="playBeep">
                                    Play beep sound on scan
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="clearAfterScan" checked>
                                <label class="form-check-label" for="clearAfterScan">
                                    Clear input after scan
                                </label>
                            </div>
                        </div>

                        <!-- Quick Test Buttons -->
                        <div class="mt-3">
                            <label class="form-label">Quick Test:</label>
                            <div>
                                <button class="btn btn-sm btn-outline-primary me-2" onclick="testUsbScan('INV-001')">
                                    Test INV-001
                                </button>
                                <button class="btn btn-sm btn-outline-primary me-2" onclick="testUsbScan('INV-0023')">
                                    Test INV-0023
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="testUsbScan('TEST-001')">
                                    Test TEST-001
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Entry Mode -->
                    <div class="tab-pane fade" id="manual" role="tabpanel">
                        <div class="text-center mb-4">
                            <i class="fas fa-keyboard fa-3x text-secondary mb-2"></i>
                            <h5>Manual Entry</h5>
                            <p class="text-muted">Type or paste the barcode number manually</p>
                        </div>

                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="manualBarcodeInput" 
                                           placeholder="Enter barcode number..."
                                           autocomplete="off"
                                           onkeypress="if(event.key==='Enter') lookupManualBarcode()">
                                    <button class="btn btn-primary" type="button" onclick="lookupManualBarcode()">
                                        <i class="fas fa-search"></i> Lookup
                                    </button>
                                </div>
                                <div class="form-text text-center mt-2">
                                    Enter property number (e.g., INV-001, MED-123, etc.)
                                </div>

                                <!-- Recent Manual Entries -->
                                <div class="mt-4">
                                    <h6><i class="fas fa-history"></i> Recent Manual Entries</h6>
                                    <div id="recentManualList" class="list-group">
                                        <!-- Will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer with Common Actions -->
            <div class="modal-footer">
                <div class="me-auto">
                    <span class="badge bg-secondary" id="currentMode">Camera Mode</span>
                </div>
                <button type="button" class="btn btn-success" onclick="printScannedBarcode()" disabled id="printScannedBtn">
                    <i class="fas fa-print"></i> Print Label
                </button>
                <button type="button" class="btn btn-primary" onclick="viewFullDetails()" disabled id="viewDetailsBtn">
                    <i class="fas fa-external-link-alt"></i> View Details
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Scanner Button (floating) -->
<button class="btn btn-success rounded-pill shadow-lg position-fixed bottom-0 end-0 m-4" 
        style="z-index: 1000; padding: 15px 25px;" 
        onclick="openScanner()">
    <i class="fas fa-camera me-2"></i> Scan Barcode
</button>

<!-- Add QuaggaJS for barcode scanning -->
<script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>

<script>
// Barcode Scanner Variables
let scannerActive = false;
let currentCamera = 'environment'; // Default to back camera
let lastScannedCode = '';
let currentItemData = null;
let scanHistory = [];

// USB Scanner Variables
let usbScannerBuffer = '';
let usbScannerTimeout = null;
const SCANNER_DELAY = 50; // Milliseconds to wait for complete scan

// Initialize scanner when modal opens
function openScanner() {
    const modal = new bootstrap.Modal(document.getElementById('barcodeScannerModal'));
    modal.show();
    
    // Start scanner automatically after modal is shown
    setTimeout(() => {
        startScanner();
    }, 500);
}

// Start barcode scanner
function startScanner() {
    if (scannerActive) return;
    
    const statusDiv = document.getElementById('scanner-status');
    statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-light me-2"></span> Accessing camera...';
    
    Quagga.init({
        inputStream: {
            name: "Live",
            type: "LiveStream",
            target: document.querySelector('#scanner-viewport'),
            constraints: {
                facingMode: currentCamera,
                width: 640,
                height: 480
            },
        },
        decoder: {
            readers: [
                "code_128_reader",
                "ean_reader",
                "ean_8_reader",
                "code_39_reader",
                "code_39_vin_reader",
                "codabar_reader",
                "upc_reader",
                "upc_e_reader",
                "i2of5_reader"
            ],
            debug: {
                showCanvas: true,
                showPatches: true,
                showFoundPatches: true,
                showSkeleton: true,
                showLabels: true,
                patchSize: "medium",
                showBoxes: true
            }
        },
        locate: true,
        locator: {
            halfSample: true,
            patchSize: "medium",
            showCanvas: true,
            showPatches: true,
            showFoundPatches: true,
            showSkeleton: true,
            showLabels: true
        }
    }, function(err) {
        if (err) {
            console.error(err);
            statusDiv.innerHTML = '<span class="text-danger">❌ Camera error: ' + err.message + '</span>';
            return;
        }
        
        Quagga.start();
        scannerActive = true;
        statusDiv.innerHTML = '<span class="text-success">✅ Scanner ready - Point camera at barcode</span>';
        
        // Add click-to-focus functionality
        document.querySelector('#scanner-viewport').addEventListener('click', function() {
            Quagga.start();
        }, false);
    });

    // Handle detected barcodes
    Quagga.onDetected(function(data) {
        const code = data.codeResult.code;
        
        // Avoid duplicate scans of same code
        if (code !== lastScannedCode) {
            lastScannedCode = code;
            
            // Beep on successful scan
            beep();
            
            // Look up the barcode in database
            lookupBarcodeByPropertyNo(code);
        }
    });

    // Handle processing errors
    Quagga.onProcessed(function(result) {
        var drawingCtx = Quagga.canvas.ctx.overlay,
            drawingCanvas = Quagga.canvas.dom.overlay;

        if (result) {
            if (result.boxes) {
                drawingCtx.clearRect(0, 0, parseInt(drawingCanvas.getAttribute("width")), parseInt(drawingCanvas.getAttribute("height")));
                result.boxes.filter(function (box) {
                    return box !== result.box;
                }).forEach(function (box) {
                    Quagga.ImageDebug.drawPath(box, {x: 0, y: 1}, drawingCtx, {color: "green", lineWidth: 2});
                });
            }

            if (result.box) {
                Quagga.ImageDebug.drawPath(result.box, {x: 0, y: 1}, drawingCtx, {color: "#00F", lineWidth: 2});
            }

            if (result.codeResult && result.codeResult.code) {
                Quagga.ImageDebug.drawPath(result.line, {x: 'x', y: 'y'}, drawingCtx, {color: 'red', lineWidth: 3});
            }
        }
    });
}

// Stop scanner
function stopScanner() {
    if (scannerActive) {
        Quagga.stop();
        scannerActive = false;
        document.getElementById('scanner-status').innerHTML = '<span class="text-warning">⏸️ Scanner stopped</span>';
    }
}

// Switch between front/back camera
function switchCamera() {
    stopScanner();
    currentCamera = currentCamera === 'environment' ? 'user' : 'environment';
    startScanner();
}

// Simple beep sound
function beep() {
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioCtx.createOscillator();
    const gainNode = audioCtx.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioCtx.destination);
    
    oscillator.frequency.setValueAtTime(800, audioCtx.currentTime);
    gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
    
    oscillator.start();
    oscillator.stop(audioCtx.currentTime + 0.1);
}

// Look up barcode in database
function lookupBarcodeByPropertyNo(propertyNo) {
    // Add to scan history
    addToScanHistory(propertyNo);
    
    // Fetch item details via AJAX
    fetch('get_item_by_barcode.php?barcode=' + encodeURIComponent(propertyNo))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.item) {
                displayItemDetails(data.item);
                currentItemData = data.item;
                
                // Enable buttons
                document.getElementById('viewDetailsBtn').disabled = false;
                document.getElementById('printScannedBtn').disabled = false;
            } else {
                displayItemNotFound(propertyNo);
                currentItemData = null;
                
                // Disable buttons
                document.getElementById('viewDetailsBtn').disabled = true;
                document.getElementById('printScannedBtn').disabled = true;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            displayItemNotFound(propertyNo, true);
        });
}

// Manual barcode lookup (for manual tab)
function lookupManualBarcode() {
    const barcode = document.getElementById('manualBarcodeInput').value.trim();
    if (barcode) {
        lookupBarcodeByPropertyNo(barcode);
        addToRecentManual(barcode);
        document.getElementById('manualBarcodeInput').value = '';
    }
}

// Keep original lookup function for backward compatibility
function lookupBarcode() {
    lookupManualBarcode();
}

// Add scan to history
function addToScanHistory(code) {
    const timestamp = new Date().toLocaleTimeString();
    
    // Add to array (keep last 10)
    scanHistory.unshift({
        code: code,
        time: timestamp
    });
    if (scanHistory.length > 10) scanHistory.pop();
    
    // Update display
    const historyDiv = document.getElementById('scanHistory');
    const scanCount = document.getElementById('scanCount');
    
    scanCount.textContent = scanHistory.length;
    
    if (scanHistory.length === 0) {
        historyDiv.innerHTML = '<p class="text-muted text-center">No scans yet</p>';
        return;
    }
    
    let html = '';
    scanHistory.forEach(item => {
        html += `
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                <div>
                    <code>${item.code}</code>
                </div>
                <div>
                    <small class="text-muted">${item.time}</small>
                    <button class="btn btn-xs btn-outline-primary ms-2" onclick="lookupBarcodeByPropertyNo('${item.code}')">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    historyDiv.innerHTML = html;
}

// Display item details when found
function displayItemDetails(item) {
    const detailsDiv = document.getElementById('currentItemDetails');
    
    // Format date if available
    let dateAdded = item.date_added ? new Date(item.date_added).toLocaleDateString() : 'N/A';
    
    // Get names from joined data
    const location = item.building_name ? 
        `${item.building_name} (${ordinal(item.building_floor)} Floor) - ${item.department_name || ''}` : 
        'No location';
    
    const accountable = item.allocate_first ? 
        `${item.allocate_first} ${item.allocate_last}` : 
        'Not assigned';
    
    detailsDiv.innerHTML = `
        <div class="card border-success">
            <div class="card-header bg-success text-white py-2">
                <strong>${item.article_name || item.equip_name || 'Item'}</strong>
            </div>
            <div class="card-body p-2">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th width="40%">Property No:</th>
                        <td><strong>${item.property_no}</strong></td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td>${item.description || 'N/A'}</td>
                    </tr>
                    <tr>
                        <th>Location:</th>
                        <td>${location}</td>
                    </tr>
                    <tr>
                        <th>Accountable:</th>
                        <td>${accountable}</td>
                    </tr>
                    <tr>
                        <th>Condition:</th>
                        <td>
                            <span class="badge ${getConditionBadgeClass(item.condition_text)}">
                                ${item.condition_text || 'N/A'}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Qty (Physical):</th>
                        <td>${item.qty_physical_count || 0}</td>
                    </tr>
                    <tr>
                        <th>Unit Value:</th>
                        <td>₱${parseFloat(item.unit_value || 0).toFixed(2)}</td>
                    </tr>
                </table>
            </div>
        </div>
    `;
}

// Helper function for condition badge
function getConditionBadgeClass(condition) {
    const classes = {
        'Serviceable': 'bg-success',
        'Non-Serviceable': 'bg-danger',
        'For Condemn': 'bg-warning text-dark',
        'Under Repair': 'bg-info',
        'For Disposal': 'bg-secondary'
    };
    return classes[condition] || 'bg-secondary';
}

// Display when item not found
function displayItemNotFound(propertyNo, error = false) {
    const detailsDiv = document.getElementById('currentItemDetails');
    detailsDiv.innerHTML = `
        <div class="alert alert-warning mb-0">
            <i class="fas fa-exclamation-triangle"></i>
            No item found with property number: <strong>${propertyNo}</strong>
        </div>
    `;
}

// View full item details in edit modal
function viewFullDetails() {
    if (!currentItemData) return;
    
    // Close scanner modal
    bootstrap.Modal.getInstance(document.getElementById('barcodeScannerModal')).hide();
    
    // Open inventory modal with item data
    openInventoryModal(currentItemData.id, currentItemData, defaultEquipmentType);
    const modal = new bootstrap.Modal(document.getElementById('inventoryModal'));
    modal.show();
}

// Print scanned item barcode
function printScannedBarcode() {
    if (!currentItemData || !currentItemData.barcode_image) {
        alert('No barcode available for this item');
        return;
    }
    
    printSingleBarcode(
        currentItemData.property_no,
        currentItemData.barcode_image,
        currentItemData.description || currentItemData.article_name || ''
    );
}

// Helper function for ordinal numbers (copied from PHP)
function ordinal(number) {
    const ends = ['th','st','nd','rd','th','th','th','th','th','th'];
    if ((number % 100) >= 11 && (number % 100) <= 13) return number + 'th';
    return number + ends[number % 10];
}

// ============ USB SCANNER FUNCTIONS ============

// Initialize USB Scanner
function initUsbScanner() {
    const usbInput = document.getElementById('usbScannerInput');
    if (!usbInput) return;

    // Focus the input when switching to USB tab
    document.getElementById('usb-tab')?.addEventListener('shown.bs.tab', function() {
        setTimeout(() => {
            usbInput.focus();
            usbInput.select();
            updateCurrentMode('USB Scanner Mode');
        }, 300);
    });

    // Handle USB scanner input
    usbInput.addEventListener('keydown', function(e) {
        // Prevent form submission on Enter
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });

    usbInput.addEventListener('keypress', function(e) {
        // Most USB scanners send Enter key at the end
        if (e.key === 'Enter') {
            e.preventDefault();
            processUsbScan(this.value.trim());
        }
    });

    // Alternative: Handle as fast input (for scanners without Enter)
    usbInput.addEventListener('input', function() {
        const value = this.value;
        
        // Clear previous timeout
        if (usbScannerTimeout) {
            clearTimeout(usbScannerTimeout);
        }
        
        // If value is long enough, process after short delay
        if (value.length >= 3) {
            usbScannerTimeout = setTimeout(() => {
                processUsbScan(value);
            }, SCANNER_DELAY);
        }
    });

    // Handle paste events
    usbInput.addEventListener('paste', function(e) {
        setTimeout(() => {
            processUsbScan(this.value.trim());
        }, 10);
    });
}

// Process USB scanner input
function processUsbScan(barcode) {
    if (!barcode) return;

    // Update display
    document.getElementById('lastScannedBarcode').textContent = barcode;
    document.getElementById('lastScanTime').textContent = 'Just now';

    // Play beep if enabled
    if (document.getElementById('playBeep')?.checked) {
        beep();
    }

    // Look up the barcode
    lookupBarcodeByPropertyNo(barcode);

    // Clear input if enabled
    if (document.getElementById('clearAfterScan')?.checked) {
        document.getElementById('usbScannerInput').value = '';
    } else {
        // Select all for next scan
        document.getElementById('usbScannerInput').select();
    }

    // Add to USB scan history with USB icon
    addUsbScanToHistory(barcode);
}

// Add USB scan to history
function addUsbScanToHistory(barcode) {
    const historyDiv = document.getElementById('scanHistory');
    const timestamp = new Date().toLocaleTimeString();
    
    let html = historyDiv.innerHTML;
    if (html.includes('No scans yet')) {
        html = '';
    }
    
    html = `<div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                <div>
                    <i class="fas fa-usb text-primary me-2"></i>
                    <code>${barcode}</code>
                </div>
                <div>
                    <small class="text-muted">${timestamp}</small>
                    <button class="btn btn-xs btn-outline-primary ms-2" onclick="lookupBarcodeByPropertyNo('${barcode}')">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
            </div>` + (html.includes('No scans yet') ? '' : html);
    
    historyDiv.innerHTML = html;
    
    // Update scan count
    const scanCount = document.querySelectorAll('#scanHistory > div').length;
    document.getElementById('scanCount').textContent = scanCount;
}

// Test function for USB scanner
function testUsbScan(barcode) {
    processUsbScan(barcode);
}

// Clear USB input
function clearUsbInput() {
    document.getElementById('usbScannerInput').value = '';
    document.getElementById('usbScannerInput').focus();
}

// Add to recent manual entries
function addToRecentManual(barcode) {
    const recentList = document.getElementById('recentManualList');
    const timestamp = new Date().toLocaleTimeString();
    
    const item = document.createElement('a');
    item.href = '#';
    item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
    item.onclick = function(e) {
        e.preventDefault();
        document.getElementById('manualBarcodeInput').value = barcode;
        lookupManualBarcode();
    };
    item.innerHTML = `
        <div>
            <code>${barcode}</code>
        </div>
        <small class="text-muted">${timestamp}</small>
    `;
    
    recentList.insertBefore(item, recentList.firstChild);
    
    // Keep only last 5
    while (recentList.children.length > 5) {
        recentList.removeChild(recentList.lastChild);
    }
}

// Update current mode display
function updateCurrentMode(mode) {
    const modeElement = document.getElementById('currentMode');
    if (modeElement) {
        modeElement.textContent = mode;
    }
}

// Clean up on modal close
document.getElementById('barcodeScannerModal').addEventListener('hidden.bs.modal', function () {
    stopScanner();
    lastScannedCode = '';
    currentItemData = null;
    document.getElementById('viewDetailsBtn').disabled = true;
    document.getElementById('printScannedBtn').disabled = true;
    document.getElementById('manualBarcodeInput').value = '';
    document.getElementById('usbScannerInput').value = '';
    document.getElementById('lastScannedBarcode').textContent = '-';
    document.getElementById('lastScanTime').textContent = '';
});

// Add keyboard shortcut (Ctrl+Shift+S) to open scanner
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'S') {
        e.preventDefault();
        openScanner();
    }
});

// Initialize everything when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initUsbScanner();
    
    // Initialize manual entry with Enter key
    document.getElementById('manualBarcodeInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            lookupManualBarcode();
        }
    });
    
    // Update mode when switching tabs
    document.getElementById('camera-tab')?.addEventListener('shown.bs.tab', function() {
        updateCurrentMode('Camera Mode');
    });
    
    document.getElementById('manual-tab')?.addEventListener('shown.bs.tab', function() {
        updateCurrentMode('Manual Entry');
        document.getElementById('manualBarcodeInput').focus();
    });
});
</script>

<!-- Add CSS for scanner and USB mode -->
<style>
    #scanner-viewport {
        position: relative;
        overflow: hidden;
        background: #000;
    }
    
    #scanner-viewport video, 
    #scanner-viewport canvas {
        width: 100%;
        height: 100%;
        position: absolute;
        top: 0;
        left: 0;
    }
    
    #scanner-viewport canvas {
        z-index: 10;
    }
    
    .scanner-container {
        position: relative;
        background: #000;
    }
    
    #scanner-status {
        position: relative;
        z-index: 20;
        background: rgba(0,0,0,0.7);
        padding: 5px 10px;
        border-radius: 20px;
        display: inline-block;
    }
    
    .btn-xs {
        padding: 2px 6px;
        font-size: 11px;
    }
    
    .scan-results-panel {
        background: #f8f9fa;
    }
    
    /* USB Scanner input styling */
    #usbScannerInput:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        background-color: #f8fff8;
    }
    
    #lastScannedBarcode {
        font-family: monospace;
        font-size: 24px;
        letter-spacing: 1px;
    }
    
    .nav-tabs .nav-link {
        font-size: 14px;
        padding: 10px 15px;
    }
    
    .nav-tabs .nav-link i {
        font-size: 16px;
    }
    
    .list-group-item {
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .list-group-item:hover {
        background-color: #e8f0fe;
        transform: translateX(5px);
    }
    
    #currentMode {
        font-size: 12px;
        padding: 5px 10px;
    }
    
    /* Floating scanner button animation */
    .position-fixed.btn {
        animation: pulse 2s infinite;
        transition: all 0.3s;
    }
    
    .position-fixed.btn:hover {
        transform: scale(1.1);
        animation: none;
    }
    
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
        }
        70% {
            box-shadow: 0 0 0 15px rgba(40, 167, 69, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
        }
    }

    @media (max-width: 768px) {
        .nav-tabs .nav-link {
            font-size: 12px;
            padding: 8px 10px;
        }
        
        .nav-tabs .nav-link i {
            margin-right: 5px;
        }
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
                <button type="button" class="btn btn-light me-2" onclick="printBarcodeLabels()">
                    <i class="fas fa-print"></i> Print Labels
                </button>
                <?php if($u['role'] === 'admin'): ?>
                <button type="button" class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#barcodeBatchModal">
                    <i class="fas fa-barcode"></i> Generate Missing Barcodes
                </button>
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
                                        <img src="<?= $r['barcode_image'] ?>" class="barcode-image" alt="Barcode <?= e($r['property_no']) ?>">
                                        <div class="barcode-text"><?= e($r['property_no']) ?></div>
                                        <button class="btn btn-sm btn-outline-primary mt-1" onclick="printSingleBarcode('<?= e($r['property_no']) ?>', '<?= e($r['barcode_image']) ?>', '<?= e($r['description']) ?>')">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    <?php else: ?>
                                       
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
                                    <button 
                                        class="btn btn-sm btn-primary edit-btn"
                                        data-id="<?= $r['id'] ?>"
                                        data-item='<?= htmlspecialchars(json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                        Edit
                                    </button>
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
              <label class="form-label">Article</label>
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

        <!-- PROPERTY NUMBER & BARCODE SECTION -->
        <div class="col-md-6 mb-2">
            <label class="form-label">Property No <small class="text-muted">(Auto-generate barcode based on this)</small></label>
            <div class="input-group mb-2">
                <input type="text" name="property_no" id="invPropertyNo" class="form-control" required>
                <button type="button" class="btn btn-outline-secondary" onclick="autoGeneratePropertyNo()">
                    <i class="fas fa-sync-alt"></i> Auto
                </button>
                <button type="button" class="btn btn-outline-warning" onclick="openMultipleBarcodeModal()">
                    <i class="fas fa-layer-group"></i> Multiple
                </button>
            </div>
            
            <div id="propertyNoSyncIndicator" class="sync-indicator" style="display: none;">
                <span class="sync-badge" onclick="syncPrefixToModal()" title="Click to use this prefix in Multiple Barcode modal">
                    <i class="fas fa-sync-alt"></i> Prefix: <span id="currentPrefix"></span>
                </span>
                <small class="text-muted">Prefix detected. Click to sync to Multiple Barcode modal.</small>
            </div>
            
            <div class="generate-btn-group">
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
          <button type="button" class="btn btn-success" onclick="printCurrentBarcode()">
              <i class="fas fa-print"></i> Print Barcode
          </button>
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================================
MULTIPLE BARCODE GENERATION MODAL - COMPLETE FIXED VERSION WITH EMPLOYEE FIELDS
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
                        <label class="form-label">Propery No.<small class="text-muted">(Sync from Property No)</small></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="multiPrefix" placeholder="e.g., INV-" value="INV-">
                            
                        </div>
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
                BATCH SETTINGS SECTION - BASIC FIELDS
                ============================================================================ -->
                <div id="batchSettings">
                    <hr>
                    <h6><i class="fas fa-cog"></i> Batch Item Settings</h6>
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
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Article</label>
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

                <!-- ============================================================================
                EMPLOYEE ASSIGNMENT SECTION
                ============================================================================ -->
                <div class="employee-section mt-3">
                    <h6><i class="fas fa-user-check"></i> Employee Assignment <small class="text-muted">(Will be applied to ALL generated items)</small></h6>
                    <div class="row">
                        <!-- Accountable By -->
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Accountable By</label>
                            <div class="input-group">
                                <select class="form-select" id="batchAllocate">
                                    <option value="">-- Select Accountable Person --</option>
                                    <?php 
                                    $employees_for_batch->data_seek(0);
                                    while($e = $employees_for_batch->fetch_assoc()): ?>
                                    <option value="<?= $e['id'] ?>">
                                        <?= e($e['firstname'] . ' ' . $e['lastname']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                               
                            </div>
                        </div>
                        
                        <!-- Approved By -->
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Approved By</label>
                            <div class="input-group">
                                <select class="form-select" id="batchApproved">
                                    <option value="">-- Select Approver --</option>
                                    <?php 
                                    $employees_for_batch->data_seek(0);
                                    while($e = $employees_for_batch->fetch_assoc()): ?>
                                    <option value="<?= $e['id'] ?>">
                                        <?= e($e['firstname'] . ' ' . $e['lastname']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                               

                            </div>
                        </div>
                        
                        <!-- Verified By -->
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Verified By</label>
                            <div class="input-group">
                                <select class="form-select" id="batchVerified">
                                    <option value="">-- Select Verifier --</option>
                                    <?php 
                                    $employees_for_batch->data_seek(0);
                                    while($e = $employees_for_batch->fetch_assoc()): ?>
                                    <option value="<?= $e['id'] ?>">
                                        <?= e($e['firstname'] . ' ' . $e['lastname']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                
                            </div>
                        </div>
                        
                        <!-- Certified Correct (Multi-select) -->
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Certified Correct</label>
                            <div class="input-group">
                                <select class="form-select" id="batchCertified" multiple size="3">
                                    <?php 
                                    $employees_for_batch->data_seek(0);
                                    while($e = $employees_for_batch->fetch_assoc()): ?>
                                    <option value="<?= $e['id'] ?>">
                                        <?= e($e['firstname'] . ' ' . $e['lastname']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                               
                            </div>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                        </div>
                        
                        <!-- Section -->
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Section</label>
                            <div class="input-group">
                                <select class="form-select" id="batchSection">
                                    <option value="">-- Select Section --</option>
                                    <?php
                                    $sections_res->data_seek(0);
                                    while($s = $sections_res->fetch_assoc()):
                                        $label = trim(
                                            ($s['bname'] ? $s['bname'].' / ' : '') .
                                            ($s['dname'] ? $s['dname'].' / ' : '') .
                                            $s['sname']
                                        );
                                    ?>
                                    <option value="<?= $s['id'] ?>"><?= e($label) ?></option>
                                    <?php endwhile; ?>
                                </select>
                                
                            </div>
                        </div>
                        
                        <!-- Equipment -->
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Equipment</label>
                            <div class="input-group">
                                <select class="form-select" id="batchEquipment">
                                    <option value="">-- Select Equipment --</option>
                                    <?php
                                    $equipment_res->data_seek(0);
                                    while($e = $equipment_res->fetch_assoc()):
                                    ?>
                                    <option value="<?= $e['id'] ?>"><?= e($e['name']) ?> (<?= e($e['category']) ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                                
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Generated Barcodes Preview -->
                <div class="mt-3">
                    <label class="form-label">Generated Barcodes Preview</label>
                    <div id="multiBarcodePreview" class="border p-3 rounded" style="max-height: 300px; overflow-y: auto; background: #f8f9fa;">
                        <p class="text-muted mb-0 text-center">Click "Generate & Preview" to see barcodes</p>
                    </div>
                    <div class="mt-2">
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

<script>
const defaultEquipmentType = '<?= $default_equipment_type ?>';
let generatedBarcodes = []; // Array to store generated barcodes

function setVal(selectorOrEl, value) {
    if (!selectorOrEl) return;
    const el = (typeof selectorOrEl === 'string') ? document.querySelector(selectorOrEl) : selectorOrEl;
    if (!el) return;
    if ('value' in el) el.value = (value !== undefined && value !== null) ? value : '';
}

function safeParse(jsonStr){
    try {
        return jsonStr ? JSON.parse(jsonStr) : {};
    } catch (e) {
        console.error('Failed to parse JSON', e, jsonStr);
        return {};
    }
}

// ============================================================================
// IMPROVED: EXTRACT PREFIX - WORKS WITH ANY PREFIX
// ============================================================================

function extractPrefix(propertyNo) {
    if (!propertyNo) return '';
    
    // Match anything before the last dash including the dash
    // This works for: MED-001, INV-0001, EQP-001, DEPT-001, LAB-001, etc.
    const lastDashIndex = propertyNo.lastIndexOf('-');
    if (lastDashIndex > 0) {
        return propertyNo.substring(0, lastDashIndex + 1);
    }
    
    // If no dash, match letters at the beginning and add dash
    const match = propertyNo.match(/^([A-Za-z]+)/);
    if (match) {
        return match[1] + '-';
    }
    
    return '';
}

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

function showAlert(message, type = 'info') {
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
        setTimeout(() => { if (alertDiv.parentElement) alertDiv.remove(); }, 3000);
    }
}

// ============================================================================
// QUANTITY HINT FUNCTIONS
// ============================================================================

function showQuantityHint() {
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    const hintDiv = document.getElementById('qtyHint');
    if (hintDiv) hintDiv.remove();
    const qtyField = document.getElementById('invQtyPhy');
    if (qtyField) {
        qtyField.style.borderColor = '';
        qtyField.style.backgroundColor = '';
    }
}

function syncQuantityToCount() {
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    const modalCountField = document.getElementById('multiCount');
    const modalElement = document.getElementById('multipleBarcodeModal');
    if (modalElement && modalElement.classList.contains('show')) {
        if (modalCountField) modalCountField.value = quantity;
    }
}

// ============================================================================
// SYNC FUNCTIONS BETWEEN MAIN FORM AND MULTIPLE BARCODE MODAL
// ============================================================================

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
    syncAllocateFromMainForm();
    syncApprovedFromMainForm();
    syncVerifiedFromMainForm();
    syncCertifiedFromMainForm();
    syncSectionFromMainForm();
    syncEquipmentFromMainForm();
    
    showAlert('All fields synced from main form!', 'success');
}

function syncDescriptionFromMainForm() {
    const mainDesc = document.getElementById('invDescription').value;
    const batchDesc = document.getElementById('batchDescription');
    if (batchDesc) batchDesc.value = mainDesc;
}

function syncLocationFromMainForm() {
    const mainLocation = document.getElementById('invLocation').value;
    const batchLocation = document.getElementById('batchLocation');
    if (batchLocation) batchLocation.value = mainLocation;
}

function syncUOMFromMainForm() {
    const mainUOM = document.getElementById('invUom').value;
    const batchUOM = document.getElementById('batchUOM');
    if (batchUOM) batchUOM.value = mainUOM;
}

function syncConditionFromMainForm() {
    const mainCondition = document.getElementById('invCondition').value;
    const batchCondition = document.getElementById('batchCondition');
    if (batchCondition) batchCondition.value = mainCondition;
}

function syncFundFromMainForm() {
    const mainFund = document.getElementById('invFund').value;
    const batchFund = document.getElementById('batchFund');
    if (batchFund) batchFund.value = mainFund;
}

function syncEquipmentTypeFromMainForm() {
    const mainEquipmentTypeSelect = document.getElementById('invType');
    const batchEquipmentType = document.getElementById('batchEquipmentType');
    if (batchEquipmentType) {
        if (mainEquipmentTypeSelect && !mainEquipmentTypeSelect.disabled) {
            const mainEquipmentType = mainEquipmentTypeSelect.value;
            if (mainEquipmentType) batchEquipmentType.value = mainEquipmentType;
        } else if (defaultEquipmentType) {
            batchEquipmentType.value = defaultEquipmentType;
        }
    }
}

function syncRemarksFromMainForm() {
    const mainRemarks = document.getElementById('invRemarks').value;
    const batchRemarks = document.getElementById('batchRemarks');
    if (batchRemarks) batchRemarks.value = mainRemarks;
}

function syncQuantityFromMainForm() {
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    const modalCountField = document.getElementById('multiCount');
    if (modalCountField) modalCountField.value = quantity;
}

function syncFromPropertyNo() {
    const propertyNo = document.getElementById('invPropertyNo').value;
    let prefix = extractPrefix(propertyNo);
    
    if (prefix) {
        // Ensure prefix ends with dash
        if (!prefix.endsWith('-')) {
            prefix = prefix + '-';
        }
        document.getElementById('multiPrefix').value = prefix;
        
        // Extract the numeric part
        const numericPart = propertyNo.replace(prefix, '');
        const numMatch = numericPart.match(/\d+/);
        if (numMatch) {
            const currentNum = parseInt(numMatch[0]);
            // Set to next number
            document.getElementById('multiStartNum').value = currentNum + 1;
        }
    }
}
// ============================================================================
// EMPLOYEE SYNC FUNCTIONS
// ============================================================================

function syncAllocateFromMainForm() {
    const mainAllocate = document.getElementById('invAllocate').value;
    const batchAllocate = document.getElementById('batchAllocate');
    if (batchAllocate) {
        batchAllocate.value = mainAllocate;
        showAlert('Accountable person synced from main form', 'success');
    }
}

function syncApprovedFromMainForm() {
    const mainApproved = document.getElementById('invApproved').value;
    const batchApproved = document.getElementById('batchApproved');
    if (batchApproved) {
        batchApproved.value = mainApproved;
        showAlert('Approver synced from main form', 'success');
    }
}

function syncVerifiedFromMainForm() {
    const mainVerified = document.getElementById('invVerified').value;
    const batchVerified = document.getElementById('batchVerified');
    if (batchVerified) {
        batchVerified.value = mainVerified;
        showAlert('Verifier synced from main form', 'success');
    }
}

function syncCertifiedFromMainForm() {
    const mainCertSelect = document.getElementById('invCertified');
    const batchCertSelect = document.getElementById('batchCertified');
    if (!mainCertSelect || !batchCertSelect) return;
    
    const selectedValues = [];
    Array.from(mainCertSelect.options).forEach(option => {
        if (option.selected) selectedValues.push(option.value);
    });
    
    Array.from(batchCertSelect.options).forEach(option => {
        option.selected = selectedValues.includes(option.value);
    });
    
    showAlert('Certified correct selections synced from main form', 'success');
}

function syncSectionFromMainForm() {
    const mainSection = document.getElementById('invSection').value;
    const batchSection = document.getElementById('batchSection');
    if (batchSection) {
        batchSection.value = mainSection;
        showAlert('Section synced from main form', 'success');
    }
}

function syncEquipmentFromMainForm() {
    const mainEquipment = document.getElementById('invEquipment').value;
    const batchEquipment = document.getElementById('batchEquipment');
    if (batchEquipment) {
        batchEquipment.value = mainEquipment;
        showAlert('Equipment synced from main form', 'success');
    }
}

// ============================================================================
// SINGLE BARCODE FUNCTIONS
// ============================================================================

function updateBarcodePreview(propertyNo) {
    const previewDiv = document.getElementById('barcodePreview');
    if (!propertyNo || propertyNo.trim() === '') {
        previewDiv.innerHTML = '<p class="text-muted mb-0">Enter Property No to see barcode preview</p>';
        updatePrefixSyncIndicator();
        return;
    }
    
    updatePrefixSyncIndicator();
    previewDiv.innerHTML = '<p class="text-info"><i class="fas fa-spinner fa-spin"></i> Generating preview...</p>';
    
    fetch('generate_barcode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'property_no=' + encodeURIComponent(propertyNo)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.barcode_image) {
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

function autoGeneratePropertyNo() {
    const date = new Date();
    const year = date.getFullYear().toString().substr(-2);
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    
    if (quantity > 1) {
        if (confirm(`Quantity is ${quantity}. Generate ${quantity} sequential barcodes instead?`)) {
            openMultipleBarcodeModal();
            return;
        }
    }
    
    const random = Math.floor(1000 + Math.random() * 9000);
    const propertyNo = `${year}${month}-${random}`;
    setVal('#invPropertyNo', propertyNo);
    updateBarcodePreview(propertyNo);
    updatePrefixSyncIndicator();
}

function generateFromEquipment() {
    const equipmentSelect = document.getElementById('invEquipment');
    const locationSelect = document.getElementById('invLocation');
    
    if (!equipmentSelect || !locationSelect || !equipmentSelect.value || !locationSelect.value) {
        alert('Please select equipment and location first');
        return;
    }
    
    // Get equipment code from data attribute
    const equipmentCode = equipmentSelect.options[equipmentSelect.selectedIndex].getAttribute('data-code') || 'EQP';
    // Get location code
    const locationCode = locationSelect.options[locationSelect.selectedIndex].getAttribute('data-code') || 'LOC';
    
    // Create prefix from equipment code
    let prefix = equipmentCode + '-';
    
    // Show loading
    const propertyNoField = document.getElementById('invPropertyNo');
    propertyNoField.value = 'Generating...';
    propertyNoField.disabled = true;
    
    // Get next number for this equipment prefix
    fetch('get_next_property_no.php?prefix=' + encodeURIComponent(prefix))
    .then(response => response.json())
    .then(data => {
        propertyNoField.disabled = false;
        
        let nextNum = 1;
        if (data.success && data.next_number) {
            nextNum = data.next_number;
        }
        
        const paddedNum = nextNum.toString().padStart(3, '0');
        const propertyNo = `${prefix}${paddedNum}`;
        
        setVal('#invPropertyNo', propertyNo);
        updateBarcodePreview(propertyNo);
        updatePrefixSyncIndicator();
        showAlert(`Generated: ${propertyNo}`, 'success');
    })
    .catch(error => {
        propertyNoField.disabled = false;
        const seq = Math.floor(100 + Math.random() * 900);
        const propertyNo = `${prefix}${seq}`;
        setVal('#invPropertyNo', propertyNo);
        updateBarcodePreview(propertyNo);
        updatePrefixSyncIndicator();
        console.error('Error:', error);
    });
}

function generateFromDepartment() {
    const locationSelect = document.getElementById('invLocation');
    
    if (!locationSelect || !locationSelect.value) {
        alert('Please select location first');
        return;
    }
    
    // Get location code
    const locationCode = locationSelect.options[locationSelect.selectedIndex].getAttribute('data-code') || 'DEPT';
    
    // Create prefix from location code
    let prefix = locationCode + '-';
    
    // Show loading
    const propertyNoField = document.getElementById('invPropertyNo');
    propertyNoField.value = 'Generating...';
    propertyNoField.disabled = true;
    
    // Get next number for this location prefix
    fetch('get_next_property_no.php?prefix=' + encodeURIComponent(prefix))
    .then(response => response.json())
    .then(data => {
        propertyNoField.disabled = false;
        
        let nextNum = 1;
        if (data.success && data.next_number) {
            nextNum = data.next_number;
        }
        
        const paddedNum = nextNum.toString().padStart(3, '0');
        const propertyNo = `${prefix}${paddedNum}`;
        
        setVal('#invPropertyNo', propertyNo);
        updateBarcodePreview(propertyNo);
        updatePrefixSyncIndicator();
        showAlert(`Generated: ${propertyNo}`, 'success');
    })
    .catch(error => {
        propertyNoField.disabled = false;
        const timestamp = Date.now().toString().slice(-6);
        const propertyNo = `${prefix}${timestamp}`;
        setVal('#invPropertyNo', propertyNo);
        updateBarcodePreview(propertyNo);
        updatePrefixSyncIndicator();
        console.error('Error:', error);
    });
}
// ============================================================================
// FIXED: SEQUENTIAL BARCODE GENERATION - ALWAYS GETS NEXT NUMBER FOR PREFIX
// ============================================================================

function generateSequential() {
    // Get current property number to determine prefix
    const propertyNo = document.getElementById('invPropertyNo').value;
    let prefix = extractPrefix(propertyNo);
    
    // Default prefix if none found
    if (!prefix) {
        prefix = 'INV-';
    }
    
    // Ensure prefix ends with dash
    if (!prefix.endsWith('-')) {
        prefix = prefix + '-';
    }
    
    // Show loading state
    const propertyNoField = document.getElementById('invPropertyNo');
    propertyNoField.value = 'Generating...';
    propertyNoField.disabled = true;
    
    // ALWAYS fetch the next available number from the database for this prefix
    fetch('get_next_property_no.php?prefix=' + encodeURIComponent(prefix))
    .then(response => response.json())
    .then(data => {
        propertyNoField.disabled = false;
        
        if (data.success) {
            // Use the next_number from server - this is ALWAYS max_number + 1
            let nextNum = data.next_number;
            
            // If no existing numbers for this prefix, start at 1
            if (nextNum === 0 || nextNum === null) {
                nextNum = 1;
            }
            
            // Determine padding based on the expected size
            let padDigits = 4; // Default
            
            // If we have existing numbers, match their padding
            if (data.last_property_no) {
                const lastNum = data.max_number;
                if (lastNum < 10) padDigits = 2;
                else if (lastNum < 100) padDigits = 3;
                else if (lastNum < 1000) padDigits = 4;
                else if (lastNum < 10000) padDigits = 5;
                else padDigits = 6;
            } else {
                // No existing numbers, use default padding based on prefix
                if (prefix.includes('MED')) padDigits = 3;
                else if (prefix.includes('INV')) padDigits = 4;
                else if (prefix.includes('EQP')) padDigits = 3;
                else if (prefix.includes('DEPT')) padDigits = 3;
                else padDigits = 4;
            }
            
            const paddedNum = nextNum.toString().padStart(padDigits, '0');
            const newPropertyNo = prefix + paddedNum;
            
            setVal('#invPropertyNo', newPropertyNo);
            updateBarcodePreview(newPropertyNo);
            updatePrefixSyncIndicator();
            
            showAlert(`Generated: ${newPropertyNo} (Next in ${prefix} sequence after #${data.max_number})`, 'success');
        } else {
            // Fallback
            fallbackGenerateSequential(prefix);
        }
    })
    .catch(error => {
        propertyNoField.disabled = false;
        console.error('Error:', error);
        fallbackGenerateSequential(prefix);
    });
}

function fallbackGenerateSequential(prefix) {
    // Fallback method that checks the database directly for this prefix
    fetch('get_last_property_no.php?prefix=' + encodeURIComponent(prefix))
    .then(response => response.json())
    .then(data => {
        let nextNum = 1;
        if (data.success && data.last_property_no) {
            // Extract the last number from the property number
            const matches = data.last_property_no.match(/\d+$/);
            if (matches && matches.length > 0) {
                nextNum = parseInt(matches[0]) + 1;
            }
        }
        
        // Determine padding
        let padDigits = 4;
        if (nextNum < 10) padDigits = 3;
        else if (nextNum < 100) padDigits = 3;
        else if (nextNum < 1000) padDigits = 4;
        else padDigits = 5;
        
        const paddedNum = nextNum.toString().padStart(padDigits, '0');
        const newPropertyNo = prefix + paddedNum;
        
        setVal('#invPropertyNo', newPropertyNo);
        updateBarcodePreview(newPropertyNo);
        updatePrefixSyncIndicator();
        showAlert(`Generated: ${newPropertyNo} (${prefix} sequence)`, 'info');
    })
    .catch(error => {
        console.error('Fallback error:', error);
        // Ultimate fallback - use timestamp
        const timestamp = Date.now().toString().slice(-6);
        const newPropertyNo = prefix + timestamp;
        setVal('#invPropertyNo', newPropertyNo);
        updateBarcodePreview(newPropertyNo);
        updatePrefixSyncIndicator();
        showAlert(`Generated: ${newPropertyNo} (timestamp)`, 'warning');
    });
}

// ============================================================================
// PRINT FUNCTIONS
// ============================================================================

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

function openMultipleBarcodeModal() {
    const quantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    const modal = new bootstrap.Modal(document.getElementById('multipleBarcodeModal'));
    modal.show();
    
    // Set count
    document.getElementById('multiCount').value = quantity;
    
    // Get prefix from property number
    const propertyNo = document.getElementById('invPropertyNo').value;
    let prefix = extractPrefix(propertyNo);
    
    if (!prefix) {
        prefix = 'INV-';
    }
    
    // Ensure prefix ends with dash
    if (!prefix.endsWith('-')) {
        prefix = prefix + '-';
    }
    
    document.getElementById('multiPrefix').value = prefix;
    
    // Show loading state
    const startNumField = document.getElementById('multiStartNum');
    startNumField.value = 'Loading...';
    startNumField.disabled = true;
    
    // ALWAYS fetch the NEXT available starting number for this prefix
    fetch('get_next_property_no.php?prefix=' + encodeURIComponent(prefix))
    .then(response => response.json())
    .then(data => {
        startNumField.disabled = false;
        
        if (data.success) {
            let nextNum = data.next_number;
            
            // If no existing numbers for this prefix, start at 1
            if (nextNum === 0 || nextNum === null) {
                nextNum = 1;
            }
            
            startNumField.value = nextNum;
            
            // Determine padding
            let padDigits = 4;
            if (data.last_property_no) {
                const lastNum = data.max_number;
                if (lastNum < 10) padDigits = 2;
                else if (lastNum < 100) padDigits = 3;
                else if (lastNum < 1000) padDigits = 4;
                else padDigits = 5;
            }
            
            showAlert(`Starting ${prefix} sequence from #${nextNum} (${prefix}${nextNum.toString().padStart(padDigits, '0')})`, 'info');
        } else {
            startNumField.value = 1;
        }
    })
    .catch(error => {
        startNumField.disabled = false;
        startNumField.value = 1;
        console.error('Error fetching start number:', error);
    });
    
    // Sync other fields
    setTimeout(() => { syncAllFromMainForm(); }, 300);
    
    // Reset generated barcodes
    generatedBarcodes = [];
    document.getElementById('multiGeneratedList').value = '';
    document.getElementById('multiBarcodePreview').innerHTML = 
        '<p class="text-muted mb-0 text-center">Click "Generate & Preview" to see barcodes</p>';
    
    // Auto-generate if quantity is reasonable
    if (quantity > 1 && quantity <= 50) {
        setTimeout(() => { generateAndPreviewMultiple(); }, 500);
    }
}
async function generateAndPreviewMultiple() {
    let count = parseInt(document.getElementById('multiCount').value);
    const formQuantity = parseInt(document.getElementById('invQtyPhy').value) || 1;
    
    if (!count || count <= 0) {
        count = formQuantity;
        document.getElementById('multiCount').value = count;
    }
    
    let prefix = document.getElementById('multiPrefix').value || '';
    
    // Ensure prefix ends with dash
    if (prefix && !prefix.endsWith('-')) {
        prefix = prefix + '-';
        document.getElementById('multiPrefix').value = prefix;
    }
    
    let startNum = parseInt(document.getElementById('multiStartNum').value) || 1;
    const padDigits = parseInt(document.getElementById('multiPadDigits').value) || 4;
    const previewFormat = document.getElementById('multiPreviewFormat').value;
    
    if (count > 100) {
        alert('Maximum 100 barcodes at a time');
        return;
    }
    
    const previewDiv = document.getElementById('multiBarcodePreview');
    previewDiv.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Checking available numbers...</div>';
    
    // ALWAYS verify with server the correct starting number
    try {
        const checkResponse = await fetch('get_next_property_no.php?prefix=' + encodeURIComponent(prefix));
        const checkData = await checkResponse.json();
        
        if (checkData.success) {
            // Use the server-provided next_number
            startNum = checkData.next_number;
            document.getElementById('multiStartNum').value = startNum;
        }
    } catch (error) {
        console.error('Error checking next number:', error);
    }
    
    // Generate property numbers
    previewDiv.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Generating ' + count + ' barcodes...</div>';
    
    generatedBarcodes = [];
    const propertyNos = [];
    
    for (let i = 0; i < count; i++) {
        const num = startNum + i;
        const paddedNum = num.toString().padStart(padDigits, '0');
        const propertyNo = prefix + paddedNum;
        propertyNos.push(propertyNo);
    }
    
    document.getElementById('multiGeneratedList').value = propertyNos.join('\n');
    
    try {
        const batchSize = 10;
        for (let i = 0; i < propertyNos.length; i += batchSize) {
            const batch = propertyNos.slice(i, i + batchSize);
            const batchPromises = batch.map(propertyNo => generateSingleBarcodeForBatch(propertyNo));
            const batchResults = await Promise.all(batchPromises);
            generatedBarcodes.push(...batchResults);
            const progress = Math.round((i + batch.length) / propertyNos.length * 100);
            previewDiv.innerHTML = `<div class="text-center py-3">
                <div class="spinner-border spinner-border-sm"></div>
                <div>Generating barcodes... ${progress}%</div>
            </div>`;
        }
        displayBarcodePreview(previewFormat);
    } catch (error) {
        console.error('Error generating barcodes:', error);
        previewDiv.innerHTML = '<div class="alert alert-danger">Error generating barcodes. Please try again.</div>';
    }
}

async function generateSingleBarcodeForBatch(propertyNo) {
    try {
        const response = await fetch('generate_barcode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'property_no=' + encodeURIComponent(propertyNo)
        });
        const data = await response.json();
        if (data.success && data.barcode_image) {
            return { property_no: propertyNo, barcode_image: data.barcode_image, success: true };
        } else {
            return { property_no: propertyNo, barcode_image: null, success: false, error: data.message || 'Unknown error' };
        }
    } catch (error) {
        return { property_no: propertyNo, barcode_image: null, success: false, error: 'Network error' };
    }
}

function displayBarcodePreview(format) {
    const previewDiv = document.getElementById('multiBarcodePreview');
    if (generatedBarcodes.length === 0) {
        previewDiv.innerHTML = '<p class="text-muted mb-0 text-center">No barcodes generated</p>';
        return;
    }
    let previewHTML = '';
    const successCount = generatedBarcodes.filter(b => b.success).length;
    const failCount = generatedBarcodes.length - successCount;
    
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
    
    previewHTML += `
        <div class="mt-2 p-2 bg-light border rounded">
            <div class="row">
                <div class="col"><small>Total: <strong>${generatedBarcodes.length}</strong></small></div>
                <div class="col"><small>Success: <span class="text-success"><strong>${successCount}</strong></span></small></div>
                <div class="col"><small>Failed: <span class="text-danger"><strong>${failCount}</strong></span></small></div>
            </div>
        </div>
    `;
    previewDiv.innerHTML = previewHTML;
}

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
        const modal = bootstrap.Modal.getInstance(document.getElementById('multipleBarcodeModal'));
        if (modal) modal.hide();
        showAlert(`Using barcode: ${firstBarcode.property_no}`, 'success');
    } else {
        alert('First barcode generation failed');
    }
}

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

function getBatchCertifiedCorrect() {
    const certSelect = document.getElementById('batchCertified');
    if (!certSelect) return null;
    const selectedValues = [];
    Array.from(certSelect.options).forEach(option => {
        if (option.selected) selectedValues.push(parseInt(option.value));
    });
    return selectedValues.length > 0 ? selectedValues : null;
}

async function saveMultipleBarcodes() {
    if (generatedBarcodes.length === 0) {
        alert('No barcodes to save');
        return;
    }
    
    const description = document.getElementById('batchDescription')?.value.trim();
    const location_id = document.getElementById('batchLocation')?.value;
    const uom = document.getElementById('batchUOM')?.value;
    const condition = document.getElementById('batchCondition')?.value;
    const fund = document.getElementById('batchFund')?.value;
    const equipment_type = document.getElementById('batchEquipmentType')?.value || defaultEquipmentType || 'Semi-expendable Equipment';
    const remarks = document.getElementById('batchRemarks')?.value.trim() || 'Batch generated';
    
    // Convert employee fields to integers or null for database
    const allocate_to = document.getElementById('batchAllocate')?.value ? parseInt(document.getElementById('batchAllocate').value) : null;
    const approved_by = document.getElementById('batchApproved')?.value ? parseInt(document.getElementById('batchApproved').value) : null;
    const verified_by = document.getElementById('batchVerified')?.value ? parseInt(document.getElementById('batchVerified').value) : null;
    const section_id = document.getElementById('batchSection')?.value ? parseInt(document.getElementById('batchSection').value) : null;
    const equipment_id = document.getElementById('batchEquipment')?.value ? parseInt(document.getElementById('batchEquipment').value) : null;
    
    // Handle certified_correct - convert to JSON string
    const certified_correct = getBatchCertifiedCorrect();
    const certified_correct_json = certified_correct ? JSON.stringify(certified_correct) : null;
    
    // Required fields validation
    const missingFields = [];
    if (!description) missingFields.push('Description');
    if (!location_id) missingFields.push('Location');
    if (!uom) missingFields.push('Unit of Measure');
    if (!condition) missingFields.push('Condition');
    if (!fund) missingFields.push('Fund Cluster');
    
    if (missingFields.length > 0) {
        alert(`Cannot save: Missing required fields:\n- ${missingFields.join('\n- ')}`);
        return;
    }
    
    if (!equipment_id) {
        alert('Please select Equipment in Batch Settings');
        return;
    }
    
    const successBarcodes = generatedBarcodes.filter(b => b.success);
    if (successBarcodes.length === 0) {
        alert('No successful barcodes to save');
        return;
    }
    
    const batchData = {
        article_name: description,
        description: description,
        location_id: parseInt(location_id),
        uom: uom,
        condition_text: condition,
        fund_cluster: fund,
        type_equipment: equipment_type,
        remarks: remarks,
        category: 'Batch Generated',
        
        // Employee fields - use null instead of 0
        allocate_to: allocate_to,
        approved_by: approved_by,
        verified_by: verified_by,
        certified_correct: certified_correct_json,
        section_id: section_id,
        equipment_id: equipment_id,
        
        // Default values
        unit_value: parseFloat(document.getElementById('invUnitValue')?.value) || 0,
        qty_property_card: 1,
        qty_physical_count: 1
    };
    
    if (!confirm(`Save ${successBarcodes.length} inventory items with these settings?`)) {
        return;
    }
    
    const previewDiv = document.getElementById('multiBarcodePreview');
    previewDiv.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Saving to database...</div>';
    
    try {
        const itemsToSave = successBarcodes.map((barcode) => ({
            ...batchData,
            property_no: barcode.property_no,
            barcode_image: barcode.barcode_image
        }));
        
        const response = await fetch('save_multiple_inventory.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                items: itemsToSave,
                user_id: <?= $u['id'] ?? 0 ?>
            })
        });
        
        const result = await response.json();
        
        if (result.success && result.saved > 0) {
            previewDiv.innerHTML = `
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> Success!</h5>
                    <p>✅ Saved <strong>${result.saved}</strong> items to inventory.</p>
                    ${result.failed > 0 ? `<p>❌ Failed: ${result.failed}</p>` : ''}
                    <button class="btn btn-sm btn-primary mt-2" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Refresh Page
                    </button>
                </div>
            `;
            
            generatedBarcodes = [];
            document.getElementById('multiGeneratedList').value = '';
            
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('multipleBarcodeModal'));
                if (modal) modal.hide();
                if (result.failed === 0) location.reload();
            }, 2000);
        } else {
            let errorMsg = result.message || 'Unknown error';
            if (result.errors && result.errors.length > 0) {
                errorMsg += '<br><br>' + result.errors.slice(0, 3).join('<br>');
            }
            previewDiv.innerHTML = `<div class="alert alert-danger">❌ Error: ${errorMsg}</div>`;
            console.error('Save errors:', result.errors);
        }
    } catch (error) {
        console.error('Save error:', error);
        previewDiv.innerHTML = '<div class="alert alert-danger">❌ Network error saving items</div>';
    }
}

// ============================================================================
// BATCH BARCODE GENERATION (For existing items)
// ============================================================================

document.getElementById('selectAllItems')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

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
    
    progressContainer.style.display = 'block';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    resultDiv.innerHTML = '';
    
    let successCount = 0;
    let failCount = 0;
    
    for (let i = 0; i < checkboxes.length; i++) {
        const checkbox = checkboxes[i];
        const id = checkbox.value;
        const propertyNo = checkbox.getAttribute('data-property');
        
        try {
            const response = await fetch('generate_barcode.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'item_id=' + id + '&property_no=' + encodeURIComponent(propertyNo)
            });
            const data = await response.json();
            if (data.success) successCount++;
            else failCount++;
        } catch (error) {
            failCount++;
        }
        
        const progress = Math.round(((i + 1) / checkboxes.length) * 100);
        progressBar.style.width = progress + '%';
        progressBar.textContent = progress + '%';
    }
    
    resultDiv.innerHTML = `
        <div class="alert ${failCount > 0 ? 'alert-warning' : 'alert-success'}">
            <strong>Batch generation complete!</strong><br>
            Successful: ${successCount}<br>
            Failed: ${failCount}
        </div>
    `;
    
    if (failCount === 0) {
        setTimeout(() => { location.reload(); }, 2000);
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

window.openInventoryModal = function (id = '', data = {}, defaultType = '') {
    const isEdit = id ? true : false;
    setVal('#inventoryModalLabel', isEdit ? 'Edit Item' : 'Add Item');
    setVal('#invId', id || '');
    setVal('#invArticleName', data.article_name || '');
    
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
    
    if (data.property_no) updateBarcodePreview(data.property_no);
    
    const equipmentSelect = document.getElementById('invEquipment');
    if (equipmentSelect && data.equipment_id) {
        equipmentSelect.dispatchEvent(new Event('change'));
    }
    
    setTimeout(() => { showQuantityHint(); }, 100);
};

// ============================================================================
// EVENT LISTENERS
// ============================================================================

document.addEventListener('DOMContentLoaded', function () {
    const propertyNoInput = document.getElementById('invPropertyNo');
    if (propertyNoInput) {
        propertyNoInput.addEventListener('input', function() {
            updateBarcodePreview(this.value);
        });
    }
    
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
    
    if (defaultEquipmentType) {
        console.log('Default equipment type from URL:', defaultEquipmentType);
    }
});

function printBarcodeLabels() {
    alert('Select items and use the Print button next to each barcode, or implement bulk printing as needed.');
}
</script>

<?php 
$mysqli->close();
ob_end_flush(); 
?>