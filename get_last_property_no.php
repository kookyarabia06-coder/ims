<?php
// get_last_property_no.php
require_once 'auth.php';
require_login();

header('Content-Type: application/json');

$mysqli = new mysqli('localhost', 'root', '', 'inventory_db');

if ($mysqli->connect_error) {
    echo json_encode(['last_property_no' => '']);
    exit;
}

// Get the last property number
$result = $mysqli->query("SELECT property_no FROM inventory ORDER BY id DESC LIMIT 1");

if ($row = $result->fetch_assoc()) {
    echo json_encode(['last_property_no' => $row['property_no']]);
} else {
    echo json_encode(['last_property_no' => '']);
}

$mysqli->close();
?>