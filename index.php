<?php
require_once 'auth.php'; // contains session, helper functions, and $mysqli

// Redirect if already logged in
if(is_logged_in()){
    header('Location: dashboard.php');
    exit;
}

$err = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $mysqli->prepare("SELECT id, firstname, lastname, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param('s',$username);
    $stmt->execute();
    $res = $stmt->get_result();

    if($res && $res->num_rows){
        $u = $res->fetch_assoc();
        if(password_verify($password, $u['password'])){
            unset($u['password']);
            $_SESSION['user_id'] = $u['id']; // store id
            $_SESSION['user'] = $u;           // store user info
            header('Location: dashboard.php');
            exit;
        }
    }
    $err = "Invalid username / password.";
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>IMS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center" style="height:100vh;">
    <div class="card shadow p-4" style="width:400px;">
        <h4 class="text-center mb-3">Material Management IMS</h4>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?= $_GET['error'] ?></div>
        <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">Login</button>
            </form>
            <?php if($err): ?>
                <div class="alert alert-danger mt-3"><?= e($err) ?></div>
            <?php endif; ?>                        
    </div>
</div>

</body>
</html>
