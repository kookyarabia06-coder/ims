<?php
require_once 'config.php';

// Get item(s) to print
$items = [];
if(isset($_GET['item_id'])) {
    $id = intval($_GET['item_id']);
    $res = $mysqli->query("
        SELECT inv.*, eq.name AS equip_name, eq.category AS equip_category, inv.type_equipment, 
               s.name AS section_name, d.name AS department_name,
               b.floor AS building_floor,
               e1.firstname AS approved_first, e1.lastname AS approved_last,
               e2.firstname AS verified_first, e2.lastname AS verified_last,
               e3.firstname AS allocate_first, e3.lastname AS allocate_last,
               inv.certified_correct, inv.fund_cluster
        FROM inventory inv
        LEFT JOIN equipment eq ON inv.equipment_id = eq.id
        LEFT JOIN sections s ON inv.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN buildings b ON d.building_id = b.id
        LEFT JOIN employees e1 ON inv.approved_by = e1.id
        LEFT JOIN employees e2 ON inv.verified_by = e2.id
        LEFT JOIN employees e3 ON inv.allocate_to = e3.id
        WHERE inv.id = $id
    ");

    while($row = $res->fetch_assoc()) $items[] = $row;
} elseif(isset($_GET['ids'])) {
    $ids = implode(',', array_map('intval', explode(',', $_GET['ids'])));
    $res = $mysqli->query("
        SELECT inv.*, eq.name AS equip_name, eq.category AS equip_category, inv.type_equipment, 
               s.name AS section_name, d.name AS department_name,
               b.floor AS building_floor,
               e1.firstname AS approved_first, e1.lastname AS approved_last,
               e2.firstname AS verified_first, e2.lastname AS verified_last,
               e3.firstname AS allocate_first, e3.lastname AS allocate_last,
               inv.fund_cluster
        FROM inventory inv
        LEFT JOIN equipment eq ON inv.equipment_id = eq.id
        LEFT JOIN sections s ON inv.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN buildings b ON d.building_id = b.id
        LEFT JOIN employees e1 ON inv.approved_by = e1.id
        LEFT JOIN employees e2 ON inv.verified_by = e2.id
        LEFT JOIN employees e3 ON inv.allocate_to = e3.id
        WHERE inv.id IN ($ids)
        ORDER BY inv.id ASC
    ");
    while($row = $res->fetch_assoc()) $items[] = $row;
}

// Date
$as_of_date = $_GET['as_of_date'] ?? 'December 31, 2024';

// Generate Excel content
ob_start();
?>
<table>
<thead>
<tr>
    <th>TYPE</th>
    <th>ARTICLE</th>
    <th>DESCRIPTION</th>
    <th>PROPERTY NUMBER</th>
    <th>UNIT OF MEASURE</th>
    <th>UNIT VALUE</th>
    <th>QUANTITY PROPERTY CARD</th>
    <th>QUANTITY PHYSICAL COUNT</th>
    <th>REMARKS</th>
</tr>
</thead>
<tbody>
<?php foreach($items as $item):
    $qty_physical = $item['qty_physical'] ?? $item['qty_property_card'];
?>
<tr>
    <td><?= htmlspecialchars($item['type_equipment']) ?></td>
    <td><?= htmlspecialchars($item['equip_name']) ?></td>
    <td><?= htmlspecialchars($item['description']) ?></td>
    <td><?= htmlspecialchars($item['property_no']) ?></td>
    <td><?= htmlspecialchars($item['uom']) ?></td>
    <td><?= number_format($item['unit_value'],2) ?></td>
    <td><?= $item['qty_property_card'] ?></td>
    <td><?= $qty_physical ?></td>
    <td><?= htmlspecialchars($item['remarks']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php
$excelContent = ob_get_clean();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 10px; }
        .header img.left-logo { float: left; height: 70px; }
        .header img.right-logo { float: right; height: 70px; }
        .clear { clear: both; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid black; }
        th, td { padding: 5px; text-align: left; font-size: 11px; }
        th { text-align: center; }
        .signature { margin-top: 50px; display: flex; justify-content: space-between; }
        .sig-line { display: inline-block; width: 200px; border-bottom: 1px solid #000; text-align: center; }
        .bold-name { font-weight: bold; }
    </style>
</head>
<body>

<div class="header">
    <img src="assets/img/left-logo.png" class="left-logo" alt="Left Logo">
    <img src="assets/img/right-logo.png" class="right-logo" alt="Right Logo">
    <h2>REPORT ON THE PHYSICAL COUNT OF PROPERTY PLANT AND EQUIPMENT</h2>
    <h3><?= htmlspecialchars($items[0]['type_equipment'] ?? $items[0]['equip_category'] ?? 'COMMUNICATION EQUIPMENT') ?></h3>
    <h4>(Type of Property, Plant and Equipment)</h4>
    <h4>As at <?= htmlspecialchars($as_of_date) ?></h4>
    <div class="clear"></div>
</div>

<p><strong>Fund Cluster:</strong> <?= htmlspecialchars($items[0]['fund_cluster'] ?? '') ?></p>
<p>For which <?= htmlspecialchars(trim($items[0]['allocate_first'] . ' ' . $items[0]['allocate_last'])) ?> is accountable</p>

<!-- Inventory Table -->
<?= $excelContent ?>

<!-- Signatures -->
<?php
$cert_names = [];
if(!empty($items[0]['certified_correct'])){
    $ids = json_decode($items[0]['certified_correct'], true);
    foreach($ids as $cid){
        $ce = $mysqli->query("SELECT firstname, middlename, lastname FROM employees WHERE id=".intval($cid))->fetch_assoc();
        if($ce) $cert_names[] = $ce['firstname'] . ($ce['middlename'] ? ' '.substr($ce['middlename'],0,1).'. ' : ' ') . $ce['lastname'];
    }
}
?>
<div class="signature">
    <div>
        <strong>Certified Correct</strong><br>
        <div class="sig-line"><?= htmlspecialchars($cert_names[0] ?? '___________________') ?></div>
    </div>
    <div>
        <strong>Approved by</strong><br>
        <div class="sig-line"><?= htmlspecialchars(trim($items[0]['approved_first'].' '.$items[0]['approved_last'])) ?></div>
    </div>
    <div>
        <strong>Verified by</strong><br>
        <div class="sig-line"><?= htmlspecialchars(trim($items[0]['verified_first'].' '.$items[0]['verified_last'])) ?></div>
    </div>
</div>

<script>
// Auto print preview
window.print();

// Auto Excel download
function downloadExcel(filename, content) {
    const blob = new Blob([content], { type: 'application/vnd.ms-excel' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

setTimeout(() => {
    downloadExcel("Inventory_Report_<?= date('Ymd_His') ?>.xls", <?= json_encode($excelContent) ?>);
}, 500);
</script>

</body>
</html>
