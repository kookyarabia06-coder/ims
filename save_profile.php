<?php
session_start();
include 'connection.php';

$id = $_POST['id'];
$firstname = $_POST['firstname'];
$lastname = $_POST['lastname'];

$avatar_filename = null;

// Handle avatar upload
if (!empty($_FILES['avatar']['name'])) {
    $avatar_filename = time() . '_' . basename($_FILES['avatar']['name']);
    $target = "uploads/avatars/" . $avatar_filename;
    move_uploaded_file($_FILES['avatar']['tmp_name'], $target);

    $conn->query("UPDATE users SET avatar='$avatar_filename' WHERE id='$id'");
}

// Update basic info
$conn->query("UPDATE users SET firstname='$firstname', lastname='$lastname' WHERE id='$id'");

// Optional password change
if (!empty($_POST['password'])) {
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password='$password' WHERE id='$id'");
}

header("Location: profile.php?success=1");
exit;
