<?php
require_once 'config.php';

// Get section_id and date
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$as_of_date = $_GET['as_of_date'] ?? date('F d, Y');

// Safe accessor
function safe($arr, $key) {
    return $arr[$key] ?? '';
}

$items = [];

// Fetch inventory
if ($section_id) {
    $res = $mysqli->query("
        SELECT inv.*, 
               eq.name AS equip_name, 
               eq.category AS equip_category,
               s.name AS section_name, 
               d.name AS department_name,
               b.floor AS building_floor, 
               b.name AS building_name,
               e1.firstname AS approved_first, e1.lastname AS approved_last,
               e2.firstname AS verified_first, e2.lastname AS verified_last,
               e3.firstname AS allocate_first, e3.lastname AS allocate_last,
               inv.fund_cluster, inv.condition_text, inv.certified_correct 
        FROM inventory inv
        LEFT JOIN equipment eq ON inv.equipment_id = eq.id
        LEFT JOIN sections s ON inv.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN buildings b ON d.building_id = b.id
        LEFT JOIN employees e1 ON inv.approved_by = e1.id
        LEFT JOIN employees e2 ON inv.verified_by = e2.id
        LEFT JOIN employees e3 ON inv.allocate_to = e3.id
        WHERE inv.section_id = $section_id
        ORDER BY inv.id ASC
    ");
    while($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
}

// Certified Correct names
$cert_names = [];
if (!empty($items[0]['certified_correct'])) {
    $ids = json_decode($items[0]['certified_correct'], true);
    if (is_array($ids)) {
        foreach($ids as $cid){
            $ce = $mysqli->query("SELECT firstname, middlename, lastname FROM employees WHERE id=" . intval($cid))->fetch_assoc();
            if($ce) {
                $mid = $ce['middlename'] ? ' ' . substr($ce['middlename'],0,1) . '. ' : ' ';
                $cert_names[] = $ce['firstname'] . $mid . $ce['lastname'];
            }
        }
    }
}

// Generate Excel content as string
ob_start();
?>
<table>
<thead>
<tr>
    <th>ARTICLE/ITEM</th>
    <th>DESCRIPTION</th>
    <th>PROPERTY NUMBER</th>
    <th>UNIT OF MEASURE</th>
    <th>UNIT VALUE</th>
    <th>QUANTITY PROPERTY CARD</th>
    <th>QUANTITY PHYSICAL COUNT</th>
    <th>LOCATION / WHEREABOUTS</th>
    <th>CONDITION</th>
    <th>REMARKS</th>
</tr>
</thead>
<tbody>
<?php foreach($items as $item): ?>
<tr>
    <td><?= htmlspecialchars(safe($item, 'equip_name')) ?></td>
    <td><?= htmlspecialchars(safe($item, 'description')) ?></td>
    <td><?= htmlspecialchars(safe($item, 'property_no')) ?></td>
    <td><?= htmlspecialchars(safe($item, 'uom')) ?></td>
    <td><?= number_format((float)safe($item, 'unit_value'), 2) ?></td>
    <td><?= safe($item, 'qty_property_card') ?></td>
    <td><?= safe($item, 'qty_physical') ?: safe($item, 'qty_property_card') ?></td>
    <td><?= htmlspecialchars(safe($item, 'section_name')) ?></td>
    <td><?= htmlspecialchars(safe($item, 'condition_text')) ?></td>
    <td><?= htmlspecialchars(safe($item, 'remarks')) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php
$excelContent = ob_get_clean(); // Save Excel content
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Count Form - Section</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid black; }
        th, td { padding: 5px; font-size: 11px; }
        th { text-align: center; }
        .signature { margin-top: 50px; display: flex; justify-content: space-between; }
        .sig-line { display: inline-block; width: 200px; border-bottom: 1px solid #000; text-align: center; }
        .sig-label { text-align: center; margin-top: 5px; }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <h2>AMANG RODRIGUEZ MEMORIAL MEDICAL CENTER</h2>
    <h3>Inventory Count Form</h3>
    <h4><?= htmlspecialchars(safe($items[0], 'department_name')) ?> - <?= htmlspecialchars(safe($items[0], 'section_name')) ?></h4>
    <p><strong>As of:</strong> <?= htmlspecialchars($as_of_date) ?></p>
</div>

<!-- Inventory Table -->
<?= $excelContent ?>

<!-- Signatures -->
<div class="signature">
    <div>
        <strong>Certified Correct</strong><br>
        <div class="sig-line"><?= htmlspecialchars($cert_names[0] ?? '___________________') ?></div>
    </div>
    <div>
        <strong>Approved by</strong><br>
        <div class="sig-line"><?= htmlspecialchars(trim(safe($items[0], 'approved_first') . ' ' . safe($items[0], 'approved_last'))) ?></div>
    </div>
    <div>
        <strong>Verified by</strong><br>
        <div class="sig-line"><?= htmlspecialchars(trim(safe($items[0], 'verified_first') . ' ' . safe($items[0], 'verified_last'))) ?></div>
    </div>
</div>

<script>
// Auto print preview
window.print();

// Automatically download Excel
function downloadExcel(filename, content) {
    const blob = new Blob([content], { type: 'application/vnd.ms-excel' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Trigger Excel download after slight delay
setTimeout(() => {
    downloadExcel("Inventory_Section_<?= $section_id ?>.xls", <?= json_encode($excelContent) ?>);
}, 500);
</script>

</body>
</html>
