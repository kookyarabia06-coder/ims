<?php
require_once 'config.php';
$u = current_user();
if (!$u) {
    header('Location: index.php');
    exit;
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $firstname = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $department_id = $_POST['department_id'] ?: null;

    if ($id) {
        // UPDATE
        $stmt = $mysqli->prepare("
            UPDATE employees SET 
                firstname=?, middlename=?, lastname=?, position=?, department_id=?, date_updated=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("ssssii", $firstname, $middlename, $lastname, $position, $department_id, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        // INSERT
        $stmt = $mysqli->prepare("
            INSERT INTO employees (firstname, middlename, lastname, position, department_id, date_created)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssssi", $firstname, $middlename, $lastname, $position, $department_id);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: employees.php');
    exit;
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = intval($_GET['id']);
    $mysqli->query("DELETE FROM employees WHERE id=$id");
    header('Location: employees.php');
    exit;
}

// Fetch employees list
$employees_res = $mysqli->query("
    SELECT e.*, d.name AS department_name 
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY e.id DESC
");

include 'header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between mb-3">
        <h3>Employees</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeModal" onclick="openEmployeeModal()">Add Employee</button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>First Name</th>
                    <th>Middle Name</th>
                    <th>Last Name</th>
                    <th>Position</th>
                    <th>Area</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($e = $employees_res->fetch_assoc()): ?>
                <tr>
                    <td><?= $e['id'] ?></td>
                    <td><?= e($e['firstname']) ?></td>
                    <td><?= e($e['middlename']) ?></td>
                    <td><?= e($e['lastname']) ?></td>
                    <td><?= e($e['position']) ?></td>
                    <td><?= e($e['department_name']) ?></td>
                    <td><?= $e['date_created'] ?></td>
                    <td><?= $e['date_updated'] ?? '' ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary" 
                            onclick='openEmployeeModal(<?= $e["id"] ?>, <?= json_encode($e, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                            Edit
                        </button>

                        <a href="employees.php?action=delete&id=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this employee?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Employee Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="employeeModalLabel">Add/Edit Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" name="id" id="empId">
          <div class="mb-2">
              <label class="form-label">First Name</label>
              <input type="text" name="firstname" id="empFirst" class="form-control" required>
          </div>
          <div class="mb-2">
              <label class="form-label">Middle Name</label>
              <input type="text" name="middlename" id="empMiddle" class="form-control">
          </div>
          <div class="mb-2">
              <label class="form-label">Last Name</label>
              <input type="text" name="lastname" id="empLast" class="form-control" required>
          </div>
          <div class="mb-2">
              <label class="form-label">Position</label>
              <input type="text" name="position" id="empPosition" class="form-control">
          </div>
          <div class="mb-2">
              <label class="form-label">Area</label>
              <select name="department_id" id="empDept" class="form-select">
                  <option value="">-- Select Area --</option>
                  <?php
                  $departments_res = $mysqli->query("SELECT id, name FROM departments ORDER BY name");
                  while ($d = $departments_res->fetch_assoc()) {
                      echo "<option value='{$d['id']}'>".e($d['name'])."</option>";
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
function openEmployeeModal(id='', data={}) {
    document.getElementById('employeeModalLabel').innerText = id ? 'Edit Employee' : 'Add Employee';
    document.getElementById('empId').value = id || '';
    document.getElementById('empFirst').value = data.firstname || '';
    document.getElementById('empMiddle').value = data.middlename || '';
    document.getElementById('empLast').value = data.lastname || '';
    document.getElementById('empPosition').value = data.position || '';
    document.getElementById('empDept').value = data.department_id || '';
    // Show the modal
    var myModal = new bootstrap.Modal(document.getElementById('employeeModal'));
    myModal.show();
}

</script>


