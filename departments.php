<?php
require_once 'auth.php';
require_admin();


// Ordinal function
function ordinal($number) {
    $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) {
        return $number.'th';
    } else {
        return $number.$ends[$number % 10];
    }
}

// Handle Delete
if(isset($_GET['action']) && $_GET['action']==='delete'){
    $id = intval($_GET['id']);
    $mysqli->query("DELETE FROM departments WHERE id = $id");
    header('Location: departments.php');
    exit;
}

// Handle Add/Edit submission
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name']);
    $building_id = $_POST['building_id'] ?: null;

    if($id){
        $stmt = $mysqli->prepare("UPDATE departments SET name=?, building_id=? WHERE id=?");
        $stmt->bind_param('sii', $name, $building_id, $id);
        $stmt->execute();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO departments (name, building_id) VALUES (?,?)");
        $stmt->bind_param('si', $name, $building_id);
        $stmt->execute();
    }
    header('Location: departments.php');
    exit;
}
include 'header.php';
// Fetch departments with building names and floor
$res = $mysqli->query("
    SELECT d.*, b.name AS building_name, b.floor AS building_floor 
    FROM departments d 
    LEFT JOIN buildings b ON d.building_id=b.id 
    ORDER BY d.id DESC
");

// Fetch buildings for dropdown
$buildings = $mysqli->query("SELECT * FROM buildings ORDER BY name");
?>

<div class="container mt-4">
    <h2>Location / Area</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#departmentModal" onclick="openDeptModal()">Add Area</button>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Area</th>
                <th>Building</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($d = $res->fetch_assoc()): ?>
            <tr>
                <td><?= $d['id'] ?></td>
                <td><?= e($d['name']) ?></td>
                <td>
                    <?= e($d['building_name']) ?>
                    <?php if(!empty($d['building_floor'])): ?>
                        – <?= ordinal($d['building_floor']) ?> Floor
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#departmentModal" 
                        onclick="openDeptModal(<?= $d['id'] ?>,'<?= e($d['name']) ?>',<?= $d['building_id'] ?? 'null' ?>)">Edit</button>
                    <a href="departments.php?action=delete&id=<?= $d['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this department?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Department Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deptModalLabel">Add/Edit Area</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" name="id" id="deptId">
          <div class="mb-3">
              <label for="deptName" class="form-label">Area Name</label>
              <input type="text" name="name" id="deptName" class="form-control" required>
          </div>
          <div class="mb-3">
              <label for="deptBuilding" class="form-label">Building</label>
                <select name="building_id" id="deptBuilding" class="form-select">
                    <option value=''>-- none --</option>
                    <?php
                    $buildings->data_seek(0);
                    while($b = $buildings->fetch_assoc()){
                        $floor_text = !empty($b['floor']) ? ' – '.ordinal($b['floor']).' Floor' : '';
                        echo "<option value='{$b['id']}'>".e($b['name']).$floor_text."</option>";
                    }
                    ?>
                </select>

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
function openDeptModal(id='', name='', building_id=''){
    document.getElementById('deptId').value = id;
    document.getElementById('deptName').value = name;
    document.getElementById('deptBuilding').value = building_id;
    document.getElementById('deptModalLabel').innerText = id ? 'Edit Area' : 'Add Area';
}
</script>

