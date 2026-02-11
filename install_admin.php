<?php
// install_admin.php
require 'config.php';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $fn = trim($_POST['firstname']);
    $ln = trim($_POST['lastname']);
    $username = trim($_POST['username']);
    $pass = $_POST['password'];
    if($fn===''||$ln===''||$username===''||$pass===''){
        $err = "All fields required.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO users (firstname, lastname, username, password, role) VALUES (?,?,?,?, 'admin')");
        $stmt->bind_param('ssss',$fn,$ln,$username,$hash);
        if($stmt->execute()){
            echo "Admin created. <a href='index.php'>Go login</a>";
            exit;
        } else {
            $err = "Error: ".$mysqli->error;
        }
    }
}
?>
<!doctype html>
<title>Install Admin</title>
<h3>Create Admin</h3>
<?php if(!empty($err)) echo "<div style='color:red'>".e($err)."</div>"; ?>
<form method="post">
Firstname: <input name="firstname"><br>
Lastname: <input name="lastname"><br>
Username: <input name="username"><br>
Password: <input name="password" type="password"><br>
<button>Create</button>
</form>
