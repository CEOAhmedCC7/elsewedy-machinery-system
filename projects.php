<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$moduleCode = resolve_module_code('PROJECTS');

$error = '';
$success = '';
$modalOverride = null;

$pdo = null;
try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    $error = format_db_error($e, 'database connection');
}

$customers = fetch_table('customers', 'customer_name');
$customerOptions = to_options($customers, 'customer_id', 'customer_name');

$businessLines = fetch_table('business_lines', 'business_line_name');
$businessLineOptions = to_options($businessLines, 'business_line_id', 'business_line_name');

$submitted = [
    'project_id' => trim($_POST['project_id'] ?? ''),
    'project_name' => trim($_POST['project_name'] ?? ''),
    'cost_center_no' => trim($_POST['cost_center_no'] ?? ''),
    'po_number' => trim($_POST['po_number'] ?? ''),
    'customer_id' => trim($_POST['customer_id'] ?? ''),
    'contract_date' => trim($_POST['contract_date'] ?? ''),
    'expected_end_date' => trim($_POST['expected_end_date'] ?? ''),
    'actual_end_date' => trim($_POST['actual_end_date'] ?? ''),
    'business_line_id' => trim($_POST['business_line_id'] ?? ''),
];

$selectedIds = array_map('intval', (array) ($_POST['selected_ids'] ?? []));

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $permissionError = enforce_action_permission(
        $currentUser,
        $moduleCode ?? 'PROJECTS',
        $action,
        [
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            'bulk_delete' => 'delete',
        ]
    );

    try {
        if ($permissionError) {
            $modalOverride = permission_denied_modal();
            $error = $permissionError;
        } elseif ($action === 'create') {
            if ($submitted['project_name'] === '') {
                throw new RuntimeException('Project name is required.');
            }
            if ($submitted['customer_id'] === '') {
                throw new RuntimeException('Please choose a customer.');
            }
            if ($submitted['business_line_id'] === '') {
                throw new RuntimeException('Please choose a business line.');
            }

$stmt = $pdo->prepare('INSERT INTO projects (project_name, cost_center_no, po_number, customer_id, contract_date, expected_end_date, actual_end_date, business_line_id) VALUES (:name, NULLIF(:cost, \'\'), NULLIF(:po, \'\'), :customer, NULLIF(:contract, \'\')::date, NULLIF(:expected, \'\')::date, NULLIF(:actual, \'\')::date, :business)');            $stmt->execute([
                ':name' => $submitted['project_name'],
                ':cost' => $submitted['cost_center_no'],
                ':po' => $submitted['po_number'],
                ':customer' => $submitted['customer_id'],
                ':contract' => $submitted['contract_date'],
                ':expected' => $submitted['expected_end_date'],
                ':actual' => $submitted['actual_end_date'],
                ':business' => $submitted['business_line_id'],
            ]);

            $newId = (int) $pdo->lastInsertId('projects_project_id_seq');
            $success = 'Project created successfully (ID #' . $newId . ').';
            $submitted = array_map(static fn () => '', $submitted);
        } elseif ($action === 'update') {
            if ($submitted['project_id'] === '') {
                throw new RuntimeException('Load a project before updating.');
            }
            if ($submitted['project_name'] === '') {
                throw new RuntimeException('Project name is required.');
            }
            if ($submitted['customer_id'] === '') {
                throw new RuntimeException('Please choose a customer.');
            }
            if ($submitted['business_line_id'] === '') {
                throw new RuntimeException('Please choose a business line.');
            }

 $stmt = $pdo->prepare('UPDATE projects SET project_name = :name, cost_center_no = NULLIF(:cost, \'\'), po_number = NULLIF(:po, \'\'), customer_id = :customer, contract_date = NULLIF(:contract, \'\')::date, expected_end_date = NULLIF(:expected, \'\')::date, actual_end_date = NULLIF(:actual, \'\')::date, business_line_id = :business WHERE project_id = :id');            $stmt->execute([
                ':id' => $submitted['project_id'],
                ':name' => $submitted['project_name'],
                ':cost' => $submitted['cost_center_no'],
                ':po' => $submitted['po_number'],
                ':customer' => $submitted['customer_id'],
                ':contract' => $submitted['contract_date'],
                ':expected' => $submitted['expected_end_date'],
                ':actual' => $submitted['actual_end_date'],
                ':business' => $submitted['business_line_id'],
            ]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Project not found.');
            }

            $success = 'Project updated successfully.';
        } elseif ($action === 'delete') {
            if ($submitted['project_id'] === '') {
                throw new RuntimeException('Load a project before deleting.');
            }

            $stmt = $pdo->prepare('DELETE FROM projects WHERE project_id = :id');
            $stmt->execute([':id' => $submitted['project_id']]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Project not found or already deleted.');
            }

            $success = 'Project deleted successfully.';
            $submitted = array_map(static fn () => '', $submitted);
        } elseif ($action === 'bulk_delete') {
            if (!$selectedIds) {
                throw new RuntimeException('Select at least one project to delete.');
            }

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM projects WHERE project_id IN ({$placeholders})");
            $stmt->execute($selectedIds);
            $success = $stmt->rowCount() . ' project(s) removed.';
        }
    } catch (Throwable $e) {
        $error = format_db_error($e, 'projects table');
    }
}

$loadProjectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;

if ($pdo && $loadProjectId) {
    $stmt = $pdo->prepare('SELECT project_id, project_name, cost_center_no, po_number, customer_id, contract_date, expected_end_date, actual_end_date, business_line_id FROM projects WHERE project_id = :id');
    $stmt->execute([':id' => $loadProjectId]);
    $found = $stmt->fetch();

    if ($found) {
        $submitted = array_map('strval', $found);
    } else {
        $error = $error ?: 'Project not found.';
    }
}

$filters = [
    'project_name' => trim($_GET['filter_project_name'] ?? ''),
    'cost_center_no' => trim($_GET['filter_cost_center_no'] ?? ''),
    'po_number' => trim($_GET['filter_po_number'] ?? ''),
    'business_line_id' => trim($_GET['filter_business_line_id'] ?? ''),
];

$projects = [];

if ($pdo) {
    try {
        $conditions = [];
        $params = [];

        if ($filters['project_name'] !== '') {
            $conditions[] = 'LOWER(p.project_name) LIKE :filter_name';
            $params[':filter_name'] = '%' . strtolower($filters['project_name']) . '%';
        }
        if ($filters['cost_center_no'] !== '') {
            $conditions[] = 'LOWER(p.cost_center_no) LIKE :filter_cost';
            $params[':filter_cost'] = '%' . strtolower($filters['cost_center_no']) . '%';
        }
        if ($filters['po_number'] !== '') {
            $conditions[] = 'LOWER(p.po_number) LIKE :filter_po';
            $params[':filter_po'] = '%' . strtolower($filters['po_number']) . '%';
        }
        if ($filters['business_line_id'] !== '') {
            $conditions[] = 'p.business_line_id = :filter_business';
            $params[':filter_business'] = $filters['business_line_id'];
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT p.project_id, p.project_name, p.cost_center_no, p.po_number, p.customer_id, p.contract_date, p.expected_end_date, p.actual_end_date, p.business_line_id, COALESCE(c.customer_name, '') AS customer_name, COALESCE(bl.business_line_name, '') AS business_line_name FROM projects p LEFT JOIN customers c ON c.customer_id = p.customer_id LEFT JOIN business_lines bl ON bl.business_line_id = p.business_line_id {$whereSql} ORDER BY p.project_id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error = $error ?: format_db_error($e, 'projects table');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Projects | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
  <script src="./assets/app.js" defer></script>
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
    </div>
    <div class="title">Projects</div>
    <div class="links">
      <div class="user-chip">
        <span class="name"><?php echo safe($currentUser['username']); ?></span>
        <span class="role"><?php echo strtoupper(safe($currentUser['role'])); ?></span>
      </div>
      <a href="./home.php">Home</a>
      <a class="logout-icon" href="./logout.php" aria-label="Logout">⎋</a>
    </div>
  </header>
  <?php if ($error !== '' || $success !== ''): ?>
    <div class="message-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Projects notification">
      <div class="message-dialog <?php echo $error ? 'is-error' : 'is-success'; ?>">
        <div class="message-dialog__header">
          <span class="message-title"><?php echo $modalOverride['title'] ?? ($error ? 'Action needed' : 'Success'); ?></span>
          <button class="message-close" type="button" aria-label="Close message">&times;</button>
        </div>
        <p class="message-body"><?php echo safe($modalOverride['subtitle'] ?? ($error !== '' ? $error : $success)); ?></p>
      </div>
    </div>
  <?php endif; ?>
  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Create, View, Update or Delete Projects</h3>

      <form method="POST" action="projects.php" id="project-form">
        <input id="project-id" name="project_id" type="hidden" value="<?php echo safe($submitted['project_id']); ?>" />
 <table class="form-table" style="width:100%; border-collapse:separate; border-spacing:12px 8px;">
          <tbody>
            <tr>
              <td style="vertical-align:top; width:50%;">
                <label class="label" for="project-name">Project Name</label>
                <input id="project-name" name="project_name" type="text" placeholder="Wind Farm Expansion" value="<?php echo safe($submitted['project_name']); ?>" />
              </td>
              <td style="vertical-align:top; width:50%;">
                <label class="label" for="cost-center">Cost Center No</label>
                <input id="cost-center" name="cost_center_no" type="text" placeholder="CC-1001" value="<?php echo safe($submitted['cost_center_no']); ?>" />
              </td>
            </tr>
            <tr>
              <td style="vertical-align:top;">
                <label class="label" for="po-number">PO Number</label>
                <input id="po-number" name="po_number" type="text" placeholder="PO-2025-01" value="<?php echo safe($submitted['po_number']); ?>" />
              </td>
              <td style="vertical-align:top;">
                <label class="label" for="project-start">Contract Date</label>
                <input id="project-start" name="contract_date" type="date" value="<?php echo safe($submitted['contract_date']); ?>" />
              </td>
            </tr>
            <tr>
              <td style="vertical-align:top;">
                <label class="label" for="project-customer">Customer</label>
                <select id="project-customer" name="customer_id">
                  <option value="">-- Select Customer --</option>
                  <?php foreach ($customerOptions as $option): ?>
                    <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['customer_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="vertical-align:top;">
                <label class="label" for="project-end">Expected End</label>
                <input id="project-end" name="expected_end_date" type="date" value="<?php echo safe($submitted['expected_end_date']); ?>" />
              </td>
            </tr>
            <tr>
              <td style="vertical-align:top;">
                <label class="label" for="business-line">Business Line</label>
                <select id="business-line" name="business_line_id">
                  <option value="">-- Select Business Line --</option>
                  <?php foreach ($businessLineOptions as $option): ?>
                    <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['business_line_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="vertical-align:top;">
                <label class="label" for="project-actual-end">Actual End</label>
                <input id="project-actual-end" name="actual_end_date" type="date" value="<?php echo safe($submitted['actual_end_date']); ?>" />
              </td>
            </tr>
          </tbody>
        </table>


        <div class="actions" style="margin:12px 0 28px; gap:10px; flex-wrap:wrap;">
          <button class="btn btn-save" type="submit" name="action" value="create">Create New Project</button>
          <button class="btn btn-update" type="submit" name="action" value="update">Update</button>
          <button class="btn btn-delete" type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this project?');">Delete</button>
          <button class="btn btn-delete" type="button" id="clear-project-fields">Clear Fields</button>
        </div>
      </form>

        <form method="GET" action="projects.php" class="filter-form">
          <table class="filter-table">
            <tbody>
              <tr>
                <td><label class="label" for="filter_project_name">Filter by Name</label></td>
                <td><label class="label" for="filter_cost_center_no">Filter by Cost Center</label></td>
                <td><label class="label" for="filter_po_number">Filter by PO</label></td>
                <td><label class="label" for="filter_business_line_id">Filter by Business Line</label></td>
                <td><label class="label" for="filter_actions">Actions</label></td>
              </tr>
              <tr>
                <td class="filter-cell">
                  <input type="text" id="filter_project_name" name="filter_project_name" value="<?php echo safe($filters['project_name']); ?>" placeholder="Project name" />
                </td>
                <td class="filter-cell">
                  <input type="text" id="filter_cost_center_no" name="filter_cost_center_no" value="<?php echo safe($filters['cost_center_no']); ?>" placeholder="Cost center" />
                </td>
                <td class="filter-cell">
                  <input type="text" id="filter_po_number" name="filter_po_number" value="<?php echo safe($filters['po_number']); ?>" placeholder="PO number" />
                </td>
                <td class="filter-cell">
                  <select id="filter_business_line_id" name="filter_business_line_id">
                    <option value="">All business lines</option>
                    <?php foreach ($businessLineOptions as $option): ?>
                      <option value="<?php echo safe($option['value']); ?>" <?php echo $filters['business_line_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="filter-actions-cell" rowspan="1">
                  <div class="actions filter-actions">
                    <button class="btn btn-update" type="submit">Apply Filters</button>
                    <a class="btn btn-delete" href="projects.php" style="text-decoration:none;">Reset</a>
                    <button class="btn btn-delete" type="submit" form="projects-table-form" name="action" value="bulk_delete" onclick="return confirm('Delete selected projects?');">Delete Selected</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </form>

      <form method="POST" action="projects.php" id="projects-table-form">
        <input type="hidden" name="project_id" id="table-project-id" value="" />
        <div class="table-wrapper">
          <table id="projects-table">
            <thead>
              <tr>
                <th><input type="checkbox" onclick="toggleAll(this, 'projects-table')" aria-label="Select all projects" /></th>
                <th>Name</th>
                <th>Cost Center</th>
                <th>Customer</th>
                <th>PO Number</th>
                <th>Contract</th>
                <th>Expected End</th>
                <th>Actual End</th>
                <th>Business Line</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($projects): ?>
                <?php foreach ($projects as $project): ?>
                  <tr>
                    <td><input type="checkbox" name="selected_ids[]" value="<?php echo safe($project['project_id']); ?>" /></td>
                    <td><?php echo safe($project['project_name']); ?></td>
                    <td><?php echo safe($project['cost_center_no']); ?></td>
                    <td><?php echo safe($project['customer_name'] ?: $project['customer_id']); ?></td>
                    <td><?php echo safe($project['po_number']); ?></td>
                    <td><?php echo safe($project['contract_date']); ?></td>
                    <td><?php echo safe($project['expected_end_date']); ?></td>
                    <td><?php echo safe($project['actual_end_date']); ?></td>
                    <td><?php echo safe($project['business_line_name'] ?: $project['business_line_id']); ?></td>
                    <td>
                      <div class="actions" style="gap:6px; flex-wrap:wrap;">
                        <button class="btn btn-update load-project" type="button" data-project='<?php echo json_encode([
                            'project_id' => $project['project_id'],
                            'project_name' => $project['project_name'],
                            'cost_center_no' => $project['cost_center_no'],
                            'po_number' => $project['po_number'],
                            'customer_id' => $project['customer_id'],
                            'contract_date' => $project['contract_date'],
                            'expected_end_date' => $project['expected_end_date'],
                            'actual_end_date' => $project['actual_end_date'],
                            'business_line_id' => $project['business_line_id'],
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>Load</button>
                        <button class="btn btn-neutral project-details" type="button" data-details='<?php echo json_encode([
                            'name' => $project['project_name'],
                            'customer' => $project['customer_name'] ?: $project['customer_id'],
                            'business_line' => $project['business_line_name'] ?: $project['business_line_id'],
                            'contract_date' => $project['contract_date'],
                            'expected_end_date' => $project['expected_end_date'],
                            'actual_end_date' => $project['actual_end_date'],
                            'po_number' => $project['po_number'],
                            'cost_center_no' => $project['cost_center_no'],
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>Details</button>
                        <button class="btn btn-delete row-delete" type="submit" name="action" value="delete" data-project-id="<?php echo safe($project['project_id']); ?>" onclick="return confirm('Delete this project?');" formnovalidate>
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="10">No projects recorded yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </form>
    </div>
  </main>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const projectForm = document.getElementById('project-form');
      const clearButton = document.getElementById('clear-project-fields');
      const messageModal = document.querySelector('.message-modal');
      const messageClose = document.querySelector('.message-close');
      const projectIdInput = document.getElementById('project-id');
      const tableProjectId = document.getElementById('table-project-id');
      const nameInput = document.getElementById('project-name');
      const costCenterInput = document.getElementById('cost-center');
      const poInput = document.getElementById('po-number');
      const customerSelect = document.getElementById('project-customer');
      const businessSelect = document.getElementById('business-line');
      const contractInput = document.getElementById('project-start');
      const expectedInput = document.getElementById('project-end');
      const actualInput = document.getElementById('project-actual-end');

      const resetFields = () => {
        projectIdInput.value = '';
        nameInput.value = '';
        costCenterInput.value = '';
        poInput.value = '';
        customerSelect.value = '';
        businessSelect.value = '';
        contractInput.value = '';
        expectedInput.value = '';
        actualInput.value = '';
      };

      clearButton.addEventListener('click', resetFields);

      if (messageModal && messageClose) {
        const hideModal = () => messageModal.classList.remove('is-visible');
        messageClose.addEventListener('click', hideModal);
        messageModal.addEventListener('click', (event) => {
          if (event.target === messageModal) {
            hideModal();
          }
        });
        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape') {
            hideModal();
          }
        });
      }

      document.querySelectorAll('.load-project').forEach((button) => {
        button.addEventListener('click', () => {
          const data = button.getAttribute('data-project');
          if (!data) return;
          const project = JSON.parse(data);
          projectIdInput.value = project.project_id || '';
          nameInput.value = project.project_name || '';
          costCenterInput.value = project.cost_center_no || '';
          poInput.value = project.po_number || '';
          customerSelect.value = project.customer_id || '';
          businessSelect.value = project.business_line_id || '';
          contractInput.value = project.contract_date || '';
          expectedInput.value = project.expected_end_date || '';
          actualInput.value = project.actual_end_date || '';
          window.scrollTo({ top: 0, behavior: 'smooth' });
        });
      });

      document.querySelectorAll('.project-details').forEach((button) => {
        button.addEventListener('click', () => {
          const payload = button.getAttribute('data-details');
          if (!payload) return;
          const details = JSON.parse(payload);
          const lines = [
            `Project: ${details.name || ''}`,
            `Customer: ${details.customer || ''}`,
            `Business Line: ${details.business_line || ''}`,
            `PO Number: ${details.po_number || ''}`,
            `Cost Center: ${details.cost_center_no || ''}`,
            `Contract Date: ${details.contract_date || ''}`,
            `Expected End: ${details.expected_end_date || ''}`,
            `Actual End: ${details.actual_end_date || ''}`,
          ];
          alert(lines.join('\n'));
        });
      });

      projectForm.addEventListener('submit', () => {
        nameInput.value = nameInput.value.trim();
        costCenterInput.value = costCenterInput.value.trim();
        poInput.value = poInput.value.trim();
      });

      document.querySelectorAll('.row-delete').forEach((button) => {
        button.addEventListener('click', () => {
          if (tableProjectId) {
            tableProjectId.value = button.dataset.projectId || '';
          }
        });
      });
    });
  </script>
</body>
</html>
