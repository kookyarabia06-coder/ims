<?php
require_once 'auth.php';
require_login();
include 'header.php';


// Handle Add/Edit
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);

    if($id){ // Edit
        $stmt = $mysqli->prepare("UPDATE equipment SET name=?, category=?, description=? WHERE id=?");
        $stmt->bind_param('sssi', $name, $category, $description, $id);
        $stmt->execute();
    } else { // Add
        $stmt = $mysqli->prepare("INSERT INTO equipment (name, category, description) VALUES (?,?,?)");
        $stmt->bind_param('sss', $name, $category, $description);
        $stmt->execute();
    }
    header('Location: equipment.php');
    exit;
}

// Handle Delete
if(isset($_GET['action']) && $_GET['action'] === 'delete'){
    $id = intval($_GET['id']);
    $mysqli->query("DELETE FROM equipment WHERE id = $id");
    header('Location: equipment.php');
    exit;
}

// Fetch equipment list
$equipment_res = $mysqli->query("SELECT * FROM equipment ORDER BY category, name");
?>

<div class="container mt-4">
    <h2>Equipment List</h2>
    <?php if($u['role'] === 'admin'): ?>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#equipmentModal" onclick="openEquipmentModal()">Add Equipment</button>
    <?php endif; ?>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Description</th>
                <?php if($u['role'] === 'admin'): ?>
                <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while($e = $equipment_res->fetch_assoc()): ?>
            <tr>
                <td><?= $e['id'] ?></td>
                <td><?= e($e['name']) ?></td>
                <td><?= e($e['category']) ?></td>
                <td><?= e($e['description']) ?></td>
                <?php if($u['role'] === 'admin'): ?>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#equipmentModal" 
                        onclick="openEquipmentModal(<?= $e['id'] ?>, <?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)">Edit</button>
                    <a href="equipment.php?action=delete&id=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this equipment?')">Delete</a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Equipment Modal -->
<div class="modal fade" id="equipmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content border-primary shadow-lg rounded-3 p-3">
            <input type="hidden" name="id" id="equipId">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="equipmentModalLabel">Add Equipment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Equipment Name</label>
                    <input type="text" name="name" id="equipName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category" id="equipCategory" class="form-select" required>
                        <option value="">--Select Category--</option>
                        <option value="ICT">ICT</option>
                        <option value="OFFICE">OFFICE</option>
                        <option value="MEDICAL">MEDICAL</option>
                        <option value="DRRM">DRRM</option>
                        <option value="Communication">Communication</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="equipDesc" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEquipmentModal(id='', data={}){
    document.getElementById('equipmentModalLabel').innerText = id ? 'Edit Equipment' : 'Add Equipment';
    document.getElementById('equipId').value = id || '';
    document.getElementById('equipName').value = data.name || '';
    document.getElementById('equipCategory').value = data.category || '';
    document.getElementById('equipDesc').value = data.description || '';
}
</script>


