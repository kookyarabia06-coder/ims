<?php
require_once 'config.php';
$u = current_user();
if (!$u) {
    header('Location: index.php');
    exit;
}
include 'header.php';

// Helper for ordinal numbers
function ordinal($number) {
    $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) return $number.'th';
    return $number.$ends[$number % 10];
}
?>

<div class="row mt-5">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-alt"></i> Inventory Report</h5>
            </div>

            <div class="card-body">
                <!-- Filters -->
                <form method="GET" class="row g-2 align-items-end">

                    <!-- Type of Equipment -->
                    <div class="col-md-2">
                        <label class="form-label">Type of Equipment</label>
                        <select name="type_equipment" class="form-select">
                            <option value="">All Types</option>
                            <option value="Semi-expendable Equipment" <?= (($_GET['type_equipment'] ?? '') == 'Semi-expendable Equipment') ? 'selected' : '' ?>>Semi-expendable Equipment</option>
                            <option value="Property Plant Equipment (50K Above)" <?= (($_GET['type_equipment'] ?? '') == 'Property Plant Equipment (50K Above)') ? 'selected' : '' ?>>Property Plant Equipment (50K Above)</option>
                        </select>
                    </div>

                    <!-- Category -->
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php
                            $categories_res = $mysqli->query("SELECT DISTINCT category FROM equipment ORDER BY category");
                            while($c = $categories_res->fetch_assoc()):
                                $sel = ($_GET['category'] ?? '') == $c['category'] ? 'selected' : '';
                            ?>
                                <option value="<?= e($c['category']) ?>" <?= $sel ?>><?= e($c['category']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Department -->
                    <!-- <div class="col-md-2">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <?php
                            $depts = $mysqli->query("SELECT id, name FROM departments ORDER BY name");
                            while($d = $depts->fetch_assoc()):
                                $sel = ($_GET['department'] ?? '') == $d['id'] ? 'selected' : '';
                            ?>
                                <option value="<?= $d['id'] ?>" <?= $sel ?>><?= e($d['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div> -->

                    <!-- Section -->
                    <div class="col-md-2">
                        <label class="form-label">Section</label>
                        <select name="section" class="form-select">
                            <option value="">All Sections</option>
                            <?php
                            $sections = $mysqli->query("SELECT id, name FROM sections ORDER BY name");
                            while($s = $sections->fetch_assoc()):
                                $sel = ($_GET['section'] ?? '') == $s['id'] ? 'selected' : '';
                            ?>
                                <option value="<?= $s['id'] ?>" <?= $sel ?>><?= e($s['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Allocate To -->
                    <div class="col-md-2">
                        <label class="form-label">Allocate To</label>
                        <select name="allocate_to" class="form-select">
                            <option value="">All Employees</option>
                            <?php
                            $emp_res = $mysqli->query("SELECT DISTINCT e.id, e.firstname, e.lastname
                                FROM inventory inv
                                JOIN employees e ON inv.allocate_to = e.id
                                ORDER BY e.firstname, e.lastname");
                            while($emp = $emp_res->fetch_assoc()):
                                $selected = (isset($_GET['allocate_to']) && $_GET['allocate_to'] == $emp['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $emp['id'] ?>" <?= $selected ?>><?= htmlspecialchars($emp['firstname'].' '.$emp['lastname']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Item Name -->
                    <div class="col-md-2">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="item_name" placeholder="Search Item Name..." value="<?= e($_GET['item_name'] ?? '') ?>" class="form-control">
                    </div>

                    <!-- Filter Button -->
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </form>

                <?php
                // Build WHERE clause
                $where = [];
                if(!empty($_GET['type_equipment'])) $where[] = "inv.type_equipment = '".$mysqli->real_escape_string($_GET['type_equipment'])."'";
                if(!empty($_GET['category'])) $where[] = "eq.category = '".$mysqli->real_escape_string($_GET['category'])."'";
                if(!empty($_GET['section'])) $where[] = "s.id = ".intval($_GET['section']);
                if(!empty($_GET['department'])) $where[] = "d.id = ".intval($_GET['department']);
                if(!empty($_GET['allocate_to'])) $where[] = "inv.allocate_to = ".intval($_GET['allocate_to']);
                if(!empty($_GET['item_name'])) $where[] = "inv.article_name LIKE '%".$mysqli->real_escape_string($_GET['item_name'])."%'";
                $where_sql = $where ? "WHERE ".implode(" AND ", $where) : "";

                // Fetch filtered inventory
                $sql = "
                SELECT inv.*, eq.name AS equip_name, eq.category AS equip_category,
                       s.name AS section_name, d.name AS department_name,
                       b.floor AS building_floor,
                       e1.firstname AS approved_first, e1.lastname AS approved_last,
                       e2.firstname AS verified_first, e2.lastname AS verified_last,
                       e3.firstname AS allocate_first, e3.lastname AS allocate_last
                FROM inventory inv
                LEFT JOIN equipment eq ON inv.equipment_id = eq.id
                LEFT JOIN sections s ON inv.section_id = s.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN buildings b ON d.building_id = b.id
                LEFT JOIN employees e1 ON inv.approved_by = e1.id
                LEFT JOIN employees e2 ON inv.verified_by = e2.id
                LEFT JOIN employees e3 ON inv.allocate_to = e3.id
                $where_sql
                ORDER BY inv.id DESC
                ";
                $res = $mysqli->query($sql);
                $filtered_rows = [];

                if($res){
                    while($r = $res->fetch_assoc()){
                        // Certified Correct
                        $cert_names = [];
                        if (!empty($r['certified_correct'])){
                            $cert_ids = json_decode($r['certified_correct'], true);
                            foreach($cert_ids as $cid){
                                $ce = $mysqli->query("SELECT firstname, middlename, lastname FROM employees WHERE id = ".intval($cid))->fetch_assoc();
                                if($ce){
                                    $cert_names[] = $ce['firstname'] . ($ce['middlename'] ? ' ' . substr($ce['middlename'],0,1) . '. ' : ' ') . $ce['lastname'];
                                }
                            }
                        }
                        $r['cert_names_str'] = implode(", ", $cert_names);
                        $r['allocated_name'] = trim($r['allocate_first'] . ' ' . $r['allocate_last']);
                        $r['floor_ordinal'] = ordinal($r['building_floor']);
                        $filtered_rows[] = $r;
                    }
                }

                $total_filtered = count($filtered_rows);
                ?>

                <!-- Inventory Table -->
                <div class="table-responsive mt-3">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Article</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Property No</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Floor</th>
                                <th>Section</th>
                                <th>Area</th>
                                <th>Allocate To</th>
                                <th>Certified Correct</th>
                                <th>Approved</th>
                                <th>Verified</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($filtered_rows)): ?>
                                <?php foreach($filtered_rows as $r): ?>
                                    <tr>
                                        <td><?= $r['id'] ?></td>
                                        <td><?= e($r['type_equipment']) ?></td>
                                        <td><?= e($r['equip_name']) ?></td>
                                        <td><?= e($r['equip_category']) ?></td>
                                        <td><?= e($r['description']) ?></td>
                                        <td><?= e($r['property_no']) ?></td>
                                        <td><?= e($r['qty_property_card']) ?></td>
                                        <td><?= e($r['uom']) ?></td>
                                        <td><?= e($r['floor_ordinal']) ?></td>
                                        <td><?= e($r['section_name']) ?></td>
                                        <td><?= e($r['department_name']) ?></td>
                                        <td><?= e($r['allocated_name']) ?></td>
                                        <td><?= e($r['cert_names_str']) ?></td>
                                        <td><?= e(trim($r['approved_first'].' '.$r['approved_last'])) ?></td>
                                        <td><?= e(trim($r['verified_first'].' '.$r['verified_last'])) ?></td>
                                        <td><?= e($r['remarks']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="16" class="text-center text-muted">No records found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Total -->
                <div class="mt-2 text-center text-muted" style="font-size:0.8rem;">
                    <strong>TOTAL: <?= $total_filtered ?></strong>
                </div>

                <!-- Print Controls -->
                <div class="d-flex gap-2 flex-wrap mt-3 align-items-center">
                    <select id="print_item" class="form-select" style="width:auto;">
                        <option value="">Print All Filtered</option>
                        <?php foreach($filtered_rows as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['equip_name'].' ('.$r['property_no'].')') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button class="btn btn-success" onclick="printItem()">
                        <i class="fas fa-print"></i> Print
                    </button>

                    <select id="print_section" class="form-select" style="width:auto;">
                        <option value="">Select Section to Print</option>
                        <?php
                        $sections_res = $mysqli->query("
                            SELECT s.id, s.name AS section_name, d.name AS department_name 
                            FROM sections s 
                            LEFT JOIN departments d ON s.department_id = d.id 
                            ORDER BY d.name, s.name
                        ");
                        while($s = $sections_res->fetch_assoc()):
                        ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['department_name'].' - '.$s['section_name']) ?></option>
                        <?php endwhile; ?>
                    </select>

                    <button class="btn btn-success" onclick="printSection()">
                        <i class="fas fa-print"></i> Print Section
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printItem() {
    let id = document.getElementById('print_item').value;
    if(id){
        window.open('print.php?item_id=' + id, '_blank');
    } else {
        let ids = <?= json_encode(array_column($filtered_rows,'id')) ?>;
        window.open('print.php?ids=' + ids.join(','), '_blank');
    }
}

function printSection() {
    let sectionId = document.getElementById('print_section').value;
    if(!sectionId){
        alert('Please select a section to print.');
        return;
    }

    let asOfDate = prompt('Enter "As of" date (e.g., December 31, 2024):', '');
    if(asOfDate === null) return;

    window.open('print_section.php?section_id=' + sectionId + '&as_of_date=' + encodeURIComponent(asOfDate), '_blank');
}
</script>
