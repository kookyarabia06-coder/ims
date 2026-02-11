<?php
require_once 'auth.php';
require_admin();
include 'header.php';

// Add the ordinal function here
function ordinal($number) {
    $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) {
        return $number.'th';
    } else {
        return $number.$ends[$number % 10];
    }
}
$action = $_GET['action'] ?? 'list';

// Handle Delete
if($action==='delete'){
    $id = intval($_GET['id']);
    $mysqli->query("DELETE FROM buildings WHERE id = $id");
    header('Location: buildings.php');
    exit;
}

// Handle Add/Edit submission
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name = trim($_POST['name']);
    $floor = intval($_POST['floor']);
    $id = $_POST['id'] ?? null;

    if($id){
        $stmt = $mysqli->prepare("UPDATE buildings SET name=?, floor=? WHERE id=?");
        $stmt->bind_param('sii', $name, $floor, $id);
        $stmt->execute();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO buildings (name, floor) VALUES (?, ?)");
        $stmt->bind_param('si', $name, $floor);
        $stmt->execute();
    }

    header('Location: buildings.php');
    exit;
}

// Fetch buildings
$res = $mysqli->query("SELECT * FROM buildings ORDER BY id DESC");
?>

<div class="container mt-4">
    <h2>Buildings</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#buildingModal" onclick="openModal()">Add Building</button>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Floor</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($r = $res->fetch_assoc()): ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= e($r['name']) ?></td>
                <td><?= ordinal($r['floor']) ?></td>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#buildingModal" 
                            onclick="openModal(<?= $r['id'] ?>,'<?= e($r['name']) ?>', <?= $r['floor'] ?>)">Edit</button>
                    <a href="buildings.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this building?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>

<!-- Bootstrap Modal -->
<div class="modal fade" id="buildingModal" tabindex="-1" aria-labelledby="buildingModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="buildingModalLabel">Add/Edit Building</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" name="id" id="buildingId">
          <div class="mb-3">
              <label for="buildingName" class="form-label">Name</label>
              <input type="text" name="name" id="buildingName" class="form-control" required>
          </div>
            <div class="mb-3">
                <label for="buildingFloor" class="form-label">Floor</label>
                <input type="number" name="floor" id="buildingFloor" class="form-control" min="1" value="1" required>
            </div>

      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id='', name='', floor=1){
    document.getElementById('buildingId').value = id;
    document.getElementById('buildingName').value = name;
    document.getElementById('buildingFloor').value = floor;
    document.getElementById('buildingModalLabel').innerText = id ? 'Edit Building' : 'Add Building';
}

</script>

