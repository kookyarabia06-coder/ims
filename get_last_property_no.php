<?php
// get_last_property_no.php
require_once 'auth.php';
require_login();

header('Content-Type: application/json');

$mysqli = new mysqli('localhost', 'root', '', 'inventory_db');
if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$prefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';

try {
    if ($prefix) {
        // Ensure prefix ends with dash
        if (!str_ends_with($prefix, '-')) {
            $prefix .= '-';
        }
        
        // Get last property number with specific prefix by numeric value
        $like_prefix = $prefix . '%';
        $stmt = $mysqli->prepare("
            SELECT property_no 
            FROM inventory 
            WHERE property_no LIKE ? 
            ORDER BY CAST(SUBSTRING_INDEX(property_no, '-', -1) AS UNSIGNED) DESC 
            LIMIT 1
        ");
        $stmt->bind_param("s", $like_prefix);
    } else {
        // Get last property number overall
        $stmt = $mysqli->prepare("SELECT property_no FROM inventory ORDER BY id DESC LIMIT 1");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'last_property_no' => $row['property_no']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'last_property_no' => null,
            'message' => 'No existing property numbers found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$mysqli->close();
?>