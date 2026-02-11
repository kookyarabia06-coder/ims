<?php
require_once 'config.php';
$u = current_user();
if (!$u) {
    header('Location: index.php');
    exit;
}
include 'header.php';

// Helper function for ordinal numbers
function ordinal($number) {
    $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) return $number.'th';
    return $number.$ends[$number % 10];
}

// Fetch totals
$total_buildings = $mysqli->query("SELECT COUNT(*) AS total FROM buildings")->fetch_assoc()['total'];
$total_departments = $mysqli->query("SELECT COUNT(*) AS total FROM departments")->fetch_assoc()['total'];
$total_sections = $mysqli->query("SELECT COUNT(*) AS total FROM sections")->fetch_assoc()['total'];
$total_employees = $mysqli->query("SELECT COUNT(*) AS total FROM employees")->fetch_assoc()['total'];
$total_inventory = $mysqli->query("SELECT COUNT(*) AS total FROM inventory")->fetch_assoc()['total'];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<div class="container-fluid mt-4 text-center">
    <h3>Amang Rodriguez Memorial Medical Center</h3>
    <h3>Material Management Inventory Monitoring System</h3>
<div class="row mt-4 g-3 justify-content-center">

<style>
    /* Card 3D hover effect */
    .dashboard-card {
        background: #f8f9fa;
        border: 2px solid #0d6efd; /* border color */
        border-radius: 1rem; /* rounded corners */
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
        text-decoration: none; /* remove underline for links */
        color: inherit; /* keep text color */
        display: block;
    }

    .dashboard-card:hover {
        transform: translateY(-10px) scale(1.05);
        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    }

    .dashboard-card h6 {
        color: #6c757d;
        margin-top: 0.5rem;
    }

    .dashboard-card h3 {
        margin-top: 0.5rem;
    }

    .dashboard-card i {
        font-size: 2rem;
        color: #0d6efd;
    }

</style>

    <div class="col-md-2">
        <a href="inventory.php" class="dashboard-card text-center p-3">
            <i class="fas fa-boxes"></i> <!-- Inventory icon -->
            <h6>Total Inventory Records</h6>
            <h3><?= $total_inventory ?></h3>
        </a>
    </div>
    
    <div class="col-md-2">
        <a href="sections.php" class="dashboard-card text-center p-3">
            <i class="fas fa-th-large"></i> <!-- Section icon -->
            <h6>Total Sections</h6>
            <h3><?= $total_sections ?></h3>
        </a>
    </div>

    <div class="row mt-4 g-3 justify-content-center">
        <div class="col-md-2">
            <a href="buildings.php" class="dashboard-card text-center p-3">
                <i class="fas fa-building"></i> <!-- Building icon -->
                <h6>Total Buildings</h6>
                <h3><?= $total_buildings ?></h3>
            </a>
        </div>

        <div class="col-md-2">
            <a href="departments.php" class="dashboard-card text-center p-3">
                <i class="fas fa-sitemap"></i> <!-- Department icon -->
                <h6>Total Departments</h6>
                <h3><?= $total_departments ?></h3>
            </a>
        </div>
        <div class="col-md-2">
            <a href="employees.php" class="dashboard-card text-center p-3">
                <i class="fas fa-user-friends"></i> <!-- Employees icon -->
                <h6>Total Employees</h6>
                <h3><?= $total_employees ?></h3>
            </a>
        </div>
    </div>
</div>
<?php
include 'footer.php';
?>

