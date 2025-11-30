<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$customers = fetch_table('customers', 'customer_id');
$customerOptions = to_options($customers, 'customer_id', 'customer_name');

$error = '';
$success = '';

$submitted = [
    'project_id' => trim($_POST['project_id'] ?? ''),
    'project_name' => trim($_POST['project_name'] ?? ''),
    'cost_center_no' => trim($_POST['cost_center_no'] ?? ''),
    'po_number' => trim($_POST['po_number'] ?? ''),
    'customer_id' => trim($_POST['customer_id'] ?? ''),
    'contract_date' => trim($_POST['contract_date'] ?? ''),
    'expected_end_date' => trim($_POST['expected_end_date'] ?? ''),
    'actual_end_date' => trim($_POST['actual_end_date'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo = get_pdo();

    try {
        if ($action === 'create') {
            if ($submitted['project_name'] === '' || $submitted['cost_center_no'] === '') {
                $error = 'Project name and cost center are required.';
            } else {
                $projectId = $submitted['project_id'] !== '' ? $submitted['project_id'] : 'prj_' . bin2hex(random_bytes(4));

                $exists = $pdo->prepare('SELECT 1 FROM projects WHERE project_id = :id');
                $exists->execute([':id' => $projectId]);

                if ($exists->fetchColumn()) {
                    $error = 'A project with this ID already exists.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO projects (project_id, project_name, cost_center_no, po_number, customer_id, contract_date, expected_end_date, actual_end_date) VALUES (:id, :name, :cost, :po, :customer, :contract, :expected, :actual)');
                    $stmt->execute([
                        ':id' => $projectId,
                        ':name' => $submitted['project_name'],
                        ':cost' => $submitted['cost_center_no'],
                        ':po' => $submitted['po_number'],
                        ':customer' => $submitted['customer_id'] ?: null,
                        ':contract' => $submitted['contract_date'] ?: null,
                        ':expected' => $submitted['expected_end_date'] ?: null,
                        ':actual' => $submitted['actual_end_date'] ?: null,
                    ]);

                    $success = 'Project saved successfully.';
                    $submitted = array_fill_keys(array_keys($submitted), '');
                }
            }
        } elseif ($action === 'update') {
            if ($submitted['project_id'] === '') {
                $error = 'Provide the Project ID to update.';
            } elseif ($submitted['project_name'] === '' || $submitted['cost_center_no'] === '') {
                $error = 'Project name and cost center are required.';
            } else {
                $stmt = $pdo->prepare('UPDATE projects SET project_name = :name, cost_center_no = :cost, po_number = :po, customer_id = :customer, contract_date = :contract, expected_end_date = :expected, actual_end_date = :actual WHERE project_id = :id');
                $stmt->execute([
                    ':id' => $submitted['project_id'],
                    ':name' => $submitted['project_name'],
                    ':cost' => $submitted['cost_center_no'],
                    ':po' => $submitted['po_number'],
                    ':customer' => $submitted['customer_id'] ?: null,
                    ':contract' => $submitted['contract_date'] ?: null,
                    ':expected' => $submitted['expected_end_date'] ?: null,
                    ':actual' => $submitted['actual_end_date'] ?: null,
                ]);

                if ($stmt->rowCount() === 0) {
                    $error = 'Project not found.';
                } else {
                    $success = 'Project updated successfully.';
                }
            }
        } elseif ($action === 'view') {
            if ($submitted['project_id'] === '') {
                $error = 'Enter a Project ID to view details.';
            } else {
                $stmt = $pdo->prepare('SELECT * FROM projects WHERE project_id = :id');
                $stmt->execute([':id' => $submitted['project_id']]);
                $found = $stmt->fetch();

                if ($found) {
                    foreach ($submitted as $key => $_) {
                        $submitted[$key] = (string) ($found[$key] ?? '');
                    }
                    $success = 'Project loaded. You can update or delete it.';
                } else {
                    $error = 'No project found with that ID.';
                }
            }
        } elseif ($action === 'delete') {
            if ($submitted['project_id'] === '') {
                $error = 'Enter a Project ID to delete.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM projects WHERE project_id = :id');
                $stmt->execute([':id' => $submitted['project_id']]);

                if ($stmt->rowCount() === 0) {
                    $error = 'Project not found or already deleted.';
                } else {
                    $success = 'Project deleted successfully.';
                    $submitted = array_fill_keys(array_keys($submitted), '');
                }
            }
        }
    } catch (Throwable $e) {
        $error = format_db_error($e, 'projects table');
    }
}

$projects = fetch_table('projects', 'project_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Projects | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
      <div class="title">Projects</div>
    </div>
    <div class="links">
            <a href="./home.php">Home</a>␊
      <a href="./logout.php">Logout</a>
    </div>
  </header>

  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Create or Update Project</h3>
      <?php
require_once __DIR__ . '/helpers.php';
$customers = fetch_table('customers', 'customer_id');
$customerOptions = to_options($customers, 'customer_id', 'customer_name');
$projects = fetch_table('projects', 'project_id');
?>
<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$customers = fetch_table('customers', 'customer_id');
$customerOptions = to_options($customers, 'customer_id', 'customer_name');

$error = '';
$success = '';

$submitted = [
    'project_id' => trim($_POST['project_id'] ?? ''),
    'project_name' => trim($_POST['project_name'] ?? ''),
    'cost_center_no' => trim($_POST['cost_center_no'] ?? ''),
    'po_number' => trim($_POST['po_number'] ?? ''),
    'customer_id' => trim($_POST['customer_id'] ?? ''),
    'contract_date' => trim($_POST['contract_date'] ?? ''),
    'expected_end_date' => trim($_POST['expected_end_date'] ?? ''),
    'actual_end_date' => trim($_POST['actual_end_date'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo = get_pdo();

    try {
        if ($action === 'create') {
            if ($submitted['project_name'] === '' || $submitted['cost_center_no'] === '') {
                $error = 'Project name and cost center are required.';
            } else {
                $projectId = $submitted['project_id'] !== '' ? $submitted['project_id'] : 'prj_' . bin2hex(random_bytes(4));

                $exists = $pdo->prepare('SELECT 1 FROM projects WHERE project_id = :id');
                $exists->execute([':id' => $projectId]);

                if ($exists->fetchColumn()) {
                    $error = 'A project with this ID already exists.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO projects (project_id, project_name, cost_center_no, po_number, customer_id, contract_date, expected_end_date, actual_end_date) VALUES (:id, :name, :cost, :po, :customer, :contract, :expected, :actual)');
                    $stmt->execute([
                        ':id' => $projectId,
                        ':name' => $submitted['project_name'],
                        ':cost' => $submitted['cost_center_no'],
                        ':po' => $submitted['po_number'],
                        ':customer' => $submitted['customer_id'] ?: null,
                        ':contract' => $submitted['contract_date'] ?: null,
                        ':expected' => $submitted['expected_end_date'] ?: null,
                        ':actual' => $submitted['actual_end_date'] ?: null,
                    ]);

                    $success = 'Project saved successfully.';
                    $submitted = array_fill_keys(array_keys($submitted), '');
                }
            }
        } elseif ($action === 'update') {
            if ($submitted['project_id'] === '') {
                $error = 'Provide the Project ID to update.';
            } elseif ($submitted['project_name'] === '' || $submitted['cost_center_no'] === '') {
                $error = 'Project name and cost center are required.';
            } else {
                $stmt = $pdo->prepare('UPDATE projects SET project_name = :name, cost_center_no = :cost, po_number = :po, customer_id = :customer, contract_date = :contract, expected_end_date = :expected, actual_end_date = :actual WHERE project_id = :id');
                $stmt->execute([
                    ':id' => $submitted['project_id'],
                    ':name' => $submitted['project_name'],
                    ':cost' => $submitted['cost_center_no'],
                    ':po' => $submitted['po_number'],
                    ':customer' => $submitted['customer_id'] ?: null,
                    ':contract' => $submitted['contract_date'] ?: null,
                    ':expected' => $submitted['expected_end_date'] ?: null,
                    ':actual' => $submitted['actual_end_date'] ?: null,
                ]);

                if ($stmt->rowCount() === 0) {
                    $error = 'Project not found.';
                } else {
                    $success = 'Project updated successfully.';
                }
            }
        } elseif ($action === 'view') {
            if ($submitted['project_id'] === '') {
                $error = 'Enter a Project ID to view details.';
            } else {
                $stmt = $pdo->prepare('SELECT * FROM projects WHERE project_id = :id');
                $stmt->execute([':id' => $submitted['project_id']]);
                $found = $stmt->fetch();

                if ($found) {
                    foreach ($submitted as $key => $_) {
                        $submitted[$key] = (string) ($found[$key] ?? '');
                    }
                    $success = 'Project loaded. You can update or delete it.';
                } else {
                    $error = 'No project found with that ID.';
                }
            }
        } elseif ($action === 'delete') {
            if ($submitted['project_id'] === '') {
                $error = 'Enter a Project ID to delete.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM projects WHERE project_id = :id');
                $stmt->execute([':id' => $submitted['project_id']]);

                if ($stmt->rowCount() === 0) {
                    $error = 'Project not found or already deleted.';
                } else {
                    $success = 'Project deleted successfully.';
                    $submitted = array_fill_keys(array_keys($submitted), '');
                }
            }
        }
    } catch (Throwable $e) {
        $error = format_db_error($e, 'projects table');
    }
}

$projects = fetch_table('projects', 'project_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Projects | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
      <div class="title">Projects</div>
    </div>
    <div class="links">
      <a href="./home.php">Home</a>␍␊
      <a href="./login.php">Logout</a>
      <a href="./home.php">Home</a>␊
      <a href="./logout.php">Logout</a>
    </div>
  </header>

  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Create or Update Project</h3>
      <div class="form-row">
        <div>
          <label class="label" for="project-id">Project ID</label>
          <input id="project-id" type="text" placeholder="PRJ-001" />
        </div>
        <div>
          <label class="label" for="project-name">Project Name</label>
          <input id="project-name" type="text" placeholder="Wind Farm Expansion" />
        </div>
        <div>
          <label class="label" for="cost-center">Cost Center No</label>
          <input id="cost-center" type="text" placeholder="CC-1001" />
        </div>
        <div>
          <label class="label" for="po-number">PO Number</label>
          <input id="po-number" type="text" placeholder="PO-2025-01" />
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="project-customer">Customer</label>
          <select id="project-customer">
            <option value="">-- Select Customer --</option>
            <?php foreach ($customerOptions as $option): ?>
              <option value="<?php echo safe($option['value']); ?>"><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label" for="project-start">Contract Date</label>
          <input id="project-start" type="date" />
        </div>
        <div>
          <label class="label" for="project-end">Expected End</label>
          <input id="project-end" type="date" />
        </div>
        <div>
          <label class="label" for="project-actual-end">Actual End</label>
          <input id="project-actual-end" type="date" />
        </div>
      </div>
      <div class="actions">
        <button class="btn btn-save" type="button">Save Project</button>
        <button class="btn btn-neutral" type="button">View</button>
        <button class="btn btn-delete" type="button">Delete</button>
      </div>
    </div>
      <?php if ($error): ?>
        <div class="alert" style="color: var(--secondary); margin-bottom:12px;">
          <?php echo safe($error); ?>
        </div>
      <?php elseif ($success): ?>
        <div class="alert" style="color: var(--primary); margin-bottom:12px;">
          <?php echo safe($success); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="projects.php">
        <div class="form-row">
          <div>
            <label class="label" for="project-id">Project ID</label>
            <input id="project-id" name="project_id" type="text" placeholder="PRJ-001" value="<?php echo safe($submitted['project_id']); ?>" />
            <p class="helper-text">Leave blank to auto-generate when saving.</p>
          </div>
          <div>
            <label class="label" for="project-name">Project Name</label>
            <input id="project-name" name="project_name" type="text" placeholder="Wind Farm Expansion" value="<?php echo safe($submitted['project_name']); ?>" />
          </div>
          <div>
            <label class="label" for="cost-center">Cost Center No</label>
            <input id="cost-center" name="cost_center_no" type="text" placeholder="CC-1001" value="<?php echo safe($submitted['cost_center_no']); ?>" />
          </div>
          <div>
            <label class="label" for="po-number">PO Number</label>
            <input id="po-number" name="po_number" type="text" placeholder="PO-2025-01" value="<?php echo safe($submitted['po_number']); ?>" />
          </div>
        </div>
        <div class="form-row">
          <div>
            <label class="label" for="project-customer">Customer</label>
            <select id="project-customer" name="customer_id">
              <option value="">-- Select Customer --</option>
              <?php foreach ($customerOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['customer_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="project-start">Contract Date</label>
            <input id="project-start" name="contract_date" type="date" value="<?php echo safe($submitted['contract_date']); ?>" />
          </div>
          <div>
            <label class="label" for="project-end">Expected End</label>
            <input id="project-end" name="expected_end_date" type="date" value="<?php echo safe($submitted['expected_end_date']); ?>" />
          </div>
          <div>
            <label class="label" for="project-actual-end">Actual End</label>
            <input id="project-actual-end" name="actual_end_date" type="date" value="<?php echo safe($submitted['actual_end_date']); ?>" />
          </div>
        </div>
        <div class="actions">
          <button class="btn btn-save" type="submit" name="action" value="create">Save Project</button>
          <button class="btn btn-neutral" type="submit" name="action" value="view">View</button>
          <button class="btn btn-neutral" type="submit" name="action" value="update">Update</button>
          <button class="btn btn-delete" type="submit" name="action" value="delete" onclick="return confirm('Delete this project?');">Delete</button>
        </div>
      </form>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Name</th><th>Customer</th><th>Cost Center</th><th>PO Number</th><th>Contract</th><th>Expected End</th><th>Actual End</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($projects): ?>
            <?php foreach ($projects as $project): ?>
              <tr>
                <td><?php echo safe($project['project_id']); ?></td>
                <td><?php echo safe($project['project_name']); ?></td>
                <td><?php echo safe($project['customer_id'] ?? ''); ?></td>
                <td><?php echo safe($project['cost_center_no']); ?></td>
                <td><?php echo safe($project['po_number']); ?></td>
                <td><?php echo safe($project['contract_date']); ?></td>
                <td><?php echo safe($project['expected_end_date']); ?></td>
                <td><?php echo safe($project['actual_end_date']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="8">No projects recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>