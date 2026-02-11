<?php
require_once 'config.php';

// Make sure user is logged in
$u = current_user();
if (!$u) {
    header('Location: index.php'); // redirect to login if not logged in
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $password = $_POST['password'] ?? '';

    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_'.$u['id'].'.'.$ext;
        $target = 'uploads/avatars/'.$filename;
        move_uploaded_file($_FILES['avatar']['tmp_name'], $target);
    } else {
        $filename = $u['avatar']; // keep existing
    }

    // Update DB with avatar included
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE users SET firstname=?, lastname=?, password=?, avatar=? WHERE id=?");
            $stmt->bind_param('ssssi', $firstname, $lastname, $hashed, $filename, $u['id']);
        } else {
            $stmt = $mysqli->prepare("UPDATE users SET firstname=?, lastname=?, avatar=? WHERE id=?");
            $stmt->bind_param('sssi', $firstname, $lastname, $filename, $u['id']);
        }

    $stmt->execute();
    $stmt->close();

    // Refresh user info after update
    $u = current_user(); // reload updated info
    $_SESSION['user_id'] = $u['id']; // keep session

    header('Location: profile.php?success=1');
    exit;
}

include 'header.php';
?>

<div class="container mt-4">
    <div class="card mx-auto shadow-sm" style="max-width: 600px;">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-person-circle"></i> Edit Profile</h5>
        </div>
        <div class="card-body">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Profile updated successfully!</div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="text-center mb-3">
                    <img src="<?= !empty($u['avatar']) ? 'uploads/avatars/'.$u['avatar'] : 'assets/img/default-avatar.png' ?>" 
                         alt="Avatar" class="rounded-circle mb-2" style="width:100px; height:100px; object-fit:cover;">
                </div>

                <div class="mb-3">
                    <label class="form-label">Change Avatar</label>
                    <input type="file" name="avatar" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">First Name</label>
                    <input type="text" name="firstname" class="form-control" value="<?= e($u['firstname']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="lastname" class="form-control" value="<?= e($u['lastname']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Change Password (optional)</label>
                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
