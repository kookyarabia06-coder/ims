 <?php
require_once 'auth.php';
header('Content-Type: application/json');

// Database connection
$mysqli = new mysqli('localhost', 'root', '', 'inventory_db');
if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get barcode from request
$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';

if (empty($barcode)) {
    echo json_encode(['success' => false, 'message' => 'No barcode provided']);
    exit;
}

// Query to get item details with all related information
$query = "
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
    WHERE inv.property_no = ? OR inv.barcode_data = ?
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("ss", $barcode, $barcode);
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