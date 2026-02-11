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

                <li class="nav-item">
                    <a class="nav-link" href="inventory.php">Inventory</a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        Locations
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="buildings.php">Buildings</a></li>
                        <li><a class="dropdown-item" href="departments.php">Departments</a></li>
                        <li><a class="dropdown-item" href="sections.php">Sections</a></li>
                    </ul>
                </li>
                    <!-- Equipment menu -->
                    <li class="nav-item">
                        <a class="nav-link" href="equipment.php">Equipment</a>
                    </li>

                <?php if($u['role']==='admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">Users</a>
                </li>
                <?php endif; ?>

            </ul>

            <!-- RIGHT SIDE USER -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <?= e($u['firstname'].' '.$u['lastname']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Logged in as:</h6></li>
                        <li><p class="dropdown-item-text">
                            <strong><?= e($u['firstname'].' '.$u['lastname']) ?></strong><br>
                            <small class="text-muted"><?= strtoupper($u['role']) ?></small>
                        </p></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>

        </div>
    </div>
</nav>
