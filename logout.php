<?php
require 'config.php';

session_start();        // REQUIRED before destroying
session_unset();        // clears all session variables
session_destroy();      // destroys the session
setcookie(session_name(), '', time() - 3600, '/');  // remove PHP session cookie

header('Location: index.php');
exit;
?>
