<?php
require_once 'config.php';
$u = current_user();
if (!$u) {
    header('Location: index.php');
    exit;
}
include 'header.php';
?>
<div class="content">
    <h3>Dashboard</h3>
    <div class="row mt-4">

        <?php
        // Use $mysqli instead of $conn
        $count_users = $mysqli->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
        $count_inventory = $mysqli->query("SELECT COUNT(*) AS total FROM inventory")->fetch_assoc()['total'];
        $count_buildings = $mysqli->query("SELECT COUNT(*) AS total FROM buildings")->fetch_assoc()['total'];
        ?>

        <div class="col-md-4">
            <div class="card shadow p-3">
                <h5>Total Inventory</h5>
                <h2><?= $count_inventory ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow p-3">
                <h5>Total Buildings/Dept/Section</h5>
                <h2><?= $count_buildings ?></h2>
            </div>
        </div>

        <?php if (current_user()['role'] == 'admin'): ?>
        <div class="col-md-4">
            <div class="card shadow p-3">
                <h5>Total Users</h5>
                <h2><?= $count_users ?></h2>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>
