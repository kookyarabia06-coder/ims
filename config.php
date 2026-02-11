<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Database connection
$mysqli = new mysqli('localhost', 'root', '', 'inventory_db');

if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Escape output
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Get current logged-in user
function current_user() {
    global $mysqli;
    if (!isset($_SESSION['user_id'])) return null;
    $uid = intval($_SESSION['user_id']);
    $res = $mysqli->query("SELECT * FROM users WHERE id = $uid LIMIT 1");
    return $res ? $res->fetch_assoc() : null;
}

?>
