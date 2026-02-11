<?php
require_once 'auth.php';
require_admin();
include 'header.php';

// Handle Delete
if(isset($_GET['action']) && $_GET['action']==='delete'){
    $id = intval($_GET['id']);
    $mysqli->query("DELETE FROM sections WHERE id = $id");
    header('Location: sections.php');
    exit;
}

// Handle Add/Edit submission
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name']);
    $department_id = $_POST['department_id'] ?: null;

    if($id){
        $stmt = $mysqli->prepare("UPDATE sections SET name=?, department_id=? WHERE id=?");
        $stmt->bind_param('sii', $name, $department_id, $id);
        $stmt->execute();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO sections (name, department_id) VALUES (?,?)");
        $stmt->bind_param('si', $name, $department_id);
        $stmt->execute();
    }
    header('Location: sections.php');
    exit;
}

// Fetch sections with department names
$res = $mysqli->query("SELECT s.*, d.name AS department_name, b.name AS building_name 
                       FROM sections s
                       LEFT JOIN departments d ON s.department_id=d.id
                       LEFT JOIN buildings b ON d.building_id=b.id
                       ORDER BY s.id DESC");

// Fetch departments for dropdown
$departments = $mysqli->query("SELECT s.id, s.name, b.name AS building_name 
                               FROM departments s 
                               LEFT JOIN buildings b ON s.building_id=b.id ORDER BY s.name");
?>

<div class="container mt-4">
    <h2>Sections</h2>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#sectionModal" onclick="openSectionModal()">Add Section</button>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Section</th>
                <th>Department / Building</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($s = $res->fetch_assoc()): ?>
            <tr>
                <td><?= $s['id'] ?></td>
                <td><?= e($s['name']) ?></td>
                <td><?= e($s['department_name'].' / '.$s['building_name']) ?></td>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#sectionModal" 
                        onclick="openSectionModal(<?= $s['id'] ?>,'<?= e($s['name']) ?>',<?= $s['department_id'] ?? 'null' ?>)">Edit</button>
                    <a href="sections.php?action=delete&id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this section?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Section Modal -->
<div class="modal fade" id="sectionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sectionModalLabel">Add/Edit Section</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" name="id" id="sectionId">
          <div class="mb-3">
              <label for="sectionName" class="form-label">Section Name</label>
              <input type="text" name="name" id="sectionName" class="form-control" required>
          </div>
          <div class="mb-3">
              <label for="sectionDept" class="form-label">Department</label>
              <select name="department_id" id="sectionDept" class="form-select">
                  <option value=''>-- none --</option>
                  <?php
                  $departments->data_seek(0);
                  while($d = $departments->fetch_assoc()){
                      echo "<option value='{$d['id']}'>".e($d['name'].' / '.$d['building_name'])."</option>";
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
function openSectionModal(id='', name='', dept_id=''){
    document.getElementById('sectionId').value = id;
    document.getElementById('sectionName').value = name;
    document.getElementById('sectionDept').value = dept_id;
    document.getElementById('sectionModalLabel').innerText = id ? 'Edit Section' : 'Add Section';
}
</script>

