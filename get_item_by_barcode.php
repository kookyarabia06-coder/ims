<?php
require_once 'auth.php';
require_login();

$mysqli = new mysqli('localhost', 'root', '', 'inventory_db');
if ($mysqli->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$barcode = $_GET['barcode'] ?? '';

if (empty($barcode)) {
    echo json_encode(['success' => false, 'message' => 'No barcode provided']);
    exit;
}

// Search for item by property_no
$stmt = $mysqli->prepare("
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
    WHERE inv.property_no = ?
    LIMIT 1
");

$stmt->bind_param('s', $barcode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $item = $result->fetch_assoc();
    echo json_encode(['success' => true, 'item' => $item]);
} else {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
}

$stmt->close();
$mysqli->close();
?>