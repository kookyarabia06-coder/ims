<?php
// get_next_property_no.php
require_once 'auth.php';
require_login();

header('Content-Type: application/json');

$mysqli = new mysqli('localhost', 'root', '', 'inventory_db');
if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$prefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';

// If no prefix provided, try to extract from request or use default
if (empty($prefix)) {
    $prefix = 'INV-';
}

// Ensure prefix ends with dash
if (!str_ends_with($prefix, '-')) {
    $prefix .= '-';
}

try {
    // CRITICAL: Get the absolute highest number for this prefix
    // This query extracts the numeric part after the last dash and finds the MAX value
    $like_prefix = $prefix . '%';
    
    $stmt = $mysqli->prepare("
        SELECT 
            MAX(CAST(SUBSTRING_INDEX(property_no, '-', -1) AS UNSIGNED)) as max_number,
            COUNT(*) as total_count
        FROM inventory 
        WHERE property_no LIKE ? 
        AND property_no REGEXP '^[A-Za-z]+-[0-9]+$'
    ");
    
    $stmt->bind_param("s", $like_prefix);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $max_number = intval($row['max_number'] ?? 0);
    $total_count = intval($row['total_count'] ?? 0);
    
    // Next number is ALWAYS max_number + 1
    $next_number = $max_number + 1;
    
    // Get the actual last property number for reference
    $last_stmt = $mysqli->prepare("
        SELECT property_no 
        FROM inventory 
        WHERE property_no LIKE ? 
        AND property_no REGEXP '^[A-Za-z]+-[0-9]+$'
        ORDER BY CAST(SUBSTRING_INDEX(property_no, '-', -1) AS UNSIGNED) DESC 
        LIMIT 1
    ");
    $last_stmt->bind_param("s", $like_prefix);
    $last_stmt->execute();
    $last_result = $last_stmt->get_result();
    $last_row = $last_result->fetch_assoc();
    $last_property_no = $last_row['property_no'] ?? null;
    $last_stmt->close();
    
    echo json_encode([
        'success' => true,
        'last_property_no' => $last_property_no,
        'max_number' => $max_number,
        'next_number' => $next_number,
        'prefix' => $prefix,
        'total_with_prefix' => $total_count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$mysqli->close();
?>