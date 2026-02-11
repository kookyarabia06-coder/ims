<?php
require 'auth.php';
require_admin();

// ================= POST HANDLING ================= //
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $fn = trim($_POST['firstname']);
    $ln = trim($_POST['lastname']);
    $un = trim($_POST['username']);
    $rl = $_POST['role'];
    $pw = $_POST['password'] ?? '';

    // Check for duplicate username
    if($id){
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? AND id<>?");
        $stmt->bind_param('si', $un, $id);
    } else {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=?");
        $stmt->bind_param('s', $un);
    }
    $stmt->execute();
    $stmt->store_result();
    if($stmt->num_rows > 0){
        $error = "Username already exists. Please choose another.";
    } else {
        // Insert or update user
        if($id){
            if($pw){
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("UPDATE users SET firstname=?, lastname=?, username=?, role=?, password=? WHERE id=?");
                $stmt->bind_param('sssssi', $fn, $ln, $un, $rl, $hash, $id);
            } else {
                $stmt = $mysqli->prepare("UPDATE users SET firstname=?, lastname=?, username=?, role=? WHERE id=?");
                $stmt->bind_param('ssssi', $fn, $ln, $un, $rl, $id);
            }
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO users (firstname, lastname, username, password, role) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $fn, $ln, $un, $hash, $rl);
        }
        $stmt->execute();
        header('Location: users.php');
        exit;
    }
}


// ================= DELETE HANDLING ================= //
if($action === 'delete' && $id){
    $mysqli->query("DELETE FROM users WHERE id=".intval($id));
    header('Location: users.php');
    exit;
}

// ================= INCLUDE HEADER ================= //
include 'header.php';

// ================= LIST USERS ================= //
if($action === 'list'):
    $res = $mysqli->query("SELECT id, firstname, lastname, username, role FROM users ORDER BY id DESC");
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Users</h2>
        <a href="users.php?action=create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add User</a>
    </div>

    <div class="table-responsive shadow-sm rounded">
        <table class="table table-striped table-bordered align-middle">
            <thead class="bg-primary text-white">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($r = $res->fetch_assoc()): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= e($r['firstname'].' '.$r['lastname']) ?></td>
                        <td><?= e($r['username']) ?></td>
                        <td><?= strtoupper($r['role']) ?></td>
                        <td>
                            <a href="users.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning">
                                <i class="bi bi-pencil-square"></i> Edit
                            </a>
                            <a href="users.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
exit;
endif;

// ================= CREATE / EDIT ================= //
$row = ['firstname'=>'','lastname'=>'','username'=>'','role'=>'staff'];
if($action === 'create' || $action === 'edit'){
    if($id){
        $stmt = $mysqli->prepare("SELECT id, firstname, lastname, username, role FROM users WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
    }
?>
<div class="container mt-4">
    <div class="card shadow-sm mx-auto" style="max-width: 600px;">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><?= $id ? 'Edit' : 'Create' ?> User</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">First Name</label>
                    <input type="text" name="firstname" class="form-control" value="<?= e($row['firstname']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="lastname" class="form-control" value="<?= e($row['lastname']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?= e($row['username']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password <?= $id ? '(leave blank to keep current)' : '' ?></label>
                    <input type="password" name="password" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="staff" <?= $row['role']=='staff'?'selected':'' ?>>Staff</option>
                        <option value="admin" <?= $row['role']=='admin'?'selected':'' ?>>Admin</option>
                    </select>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary"><?= $id ? 'Update' : 'Save' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
exit;
}
?>
