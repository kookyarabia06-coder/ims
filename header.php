<?php
require_once 'config.php';

$u = current_user();

if (!$u) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="icon" type="image/png" href="assets/img/mms.png">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="assets/img/favicon.ico" type="image/x-icon">

    <style>
        body { background: #f5f6fa; }
        .sidebar {
            height: 100vh;
            width: 240px;
            background: #0d6efd;
            color: white;
            position: fixed;
            padding-top: 20px;
        }
        .sidebar a { color: white; text-decoration: none; padding: 10px 20px; display: block; }
        .sidebar a:hover { background: rgba(255,255,255,0.2); }
        .content { margin-left: 250px; padding: 20px; }
        
        /* Dropdown submenu styles */
        .dropdown-submenu {
            position: relative;
        }

        .dropdown-submenu > .dropdown-menu {
            top: 0;
            left: 100%;
            margin-top: -6px;
            margin-left: -1px;
            border-radius: 0.25rem;
            display: none;
        }

        .dropdown-submenu:hover > .dropdown-menu {
            display: block;
        }

        .dropdown-submenu > a::after {
            display: block;
            content: " ";
            float: right;
            width: 0;
            height: 0;
            border-color: transparent;
            border-style: solid;
            border-width: 5px 0 5px 5px;
            border-left-color: #ccc;
            margin-top: 5px;
            margin-right: -10px;
        }

        .dropdown-submenu:hover > a::after {
            border-left-color: #fff;
        }

        @media (max-width: 991.98px) {
            .dropdown-submenu > .dropdown-menu {
                position: static;
                left: auto;
                margin-left: 20px;
                border: none;
                box-shadow: none;
                border-radius: 0;
                padding-left: 20px;
            }
            
            .dropdown-submenu > a::after {
                display: none;
            }
        }
    </style>
</head>
<body>

<?php
$u = current_user();
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
    <div class="container-fluid">

        <a class="navbar-brand fw-bold" href="dashboard.php">IMS</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">

            <!-- LEFT LINKS -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                </li>

                <!-- INVENTORY DROPDOWN WITH SUBMENU -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        Inventory
                    </a>
                    <ul class="dropdown-menu">
                        <!-- Inventory List with sub-dropdown -->
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">Inventory List</a>
                            <ul class="dropdown-menu">
                                
                                <li><a class="dropdown-item" href="inventory.php?type=semi-expendable">Semi-expendable Equipment</a></li>
                                <li><a class="dropdown-item" href="inventory.php?type=ppe">Property Plant Equipment (50K Above)</a></li>
                            </ul>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="report.php">Report</a></li>
                    </ul>
                </li>
                
                <!-- Equipment menu -->
                <li class="nav-item">
                    <a class="nav-link" href="equipment.php">Equipment</a>
                </li>
                
                <?php if($u['role'] === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        Locations
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="departments.php">Area</a></li>
                        <li><a class="dropdown-item" href="buildings.php">Buildings</a></li>
                        <li><a class="dropdown-item" href="sections.php">Sections</a></li>
                    </ul>
                </li>

                <!-- Employees CRUD -->
                <li class="nav-item">
                    <a class="nav-link" href="employees.php">
                        <i class="fas fa-users"></i> Employees
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">Users</a>
                </li>
                <?php endif; ?>

            </ul>

            <!-- RIGHT SIDE USER -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <img src="<?= !empty($u['avatar']) ? 'uploads/avatars/'.$u['avatar'] : 'assets/img/default-avatar.png' ?>" 
                             alt="Avatar" 
                             class="rounded-circle me-2" 
                             style="width: 32px; height: 32px; object-fit: cover;">
                        <span><?= e($u['firstname'].' '.$u['lastname']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header text-center">
                            <img src="<?= !empty($u['avatar']) ? 'uploads/avatars/'.$u['avatar'] : 'assets/img/default-avatar.png' ?>" 
                                alt="Avatar" 
                                class="rounded-circle mb-2" 
                                style="width: 60px; height: 60px; object-fit: cover;">
                            <br>
                            <strong><?= e($u['firstname'].' '.$u['lastname']) ?></strong><br>
                            <small class="text-muted"><?= strtoupper($u['role']) ?></small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person-circle me-2"></i> Edit Profile
                            </a>
                        </li>
                        
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

        </div>
    </div>
</nav>

<!-- JavaScript for submenu -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Close submenus when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-submenu')) {
            document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(function(submenu) {
                submenu.style.display = '';
            });
        }
    });
    
    // Mobile touch support
    document.querySelectorAll('.dropdown-submenu > a').forEach(function(element) {
        element.addEventListener('click', function(e) {
            if (window.innerWidth < 992) {
                e.preventDefault();
                e.stopPropagation();
                let submenu = this.nextElementSibling;
                if (submenu.style.display === 'block') {
                    submenu.style.display = 'none';
                } else {
                    submenu.style.display = 'block';
                }
            }
        });
    });
});
</script>

</body>
</html>