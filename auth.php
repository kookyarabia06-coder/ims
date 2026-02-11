<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
function require_login(){
    if(!isset($_SESSION['user_id'])){
        header('Location: index.php');
        exit;
    }
}

// Only admin
function require_admin(){
    if(!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin'){
        header('HTTP/1.1 403 Forbidden');
        echo "Forbidden - admin only.";
        exit;
    }
}

// Get currently logged-in user
if (!function_exists('current_user')) {
    function current_user() {
        global $mysqli;
        if (!isset($_SESSION['user_id'])) return null;

        $id = $_SESSION['user_id'];
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}

// Check if user is logged in
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}
// Escape HTML
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

?>