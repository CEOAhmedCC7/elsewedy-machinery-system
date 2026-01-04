<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$moduleCode = resolve_module_code('PROJECTS');

$error = '';
$success = '';
$successHtml = '';
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

function option_label(array $options, string $value): string
{
    foreach ($options as $option) {
        if ((string) ($option['value'] ?? '') === $value) {
            return (string) ($option['label'] ?? $value);
        }
    }

    return $value;
}

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

$contractTimestamp = $submitted['contract_date'] !== '' ? strtotime($submitted['contract_date']) : time();
$defaultExpectedEndDate = date('Y-m-d', strtotime('+3 days', $contractTimestamp ?: time()));

if ($submitted['expected_end_date'] === '' && ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') === 'create')) {
    $submitted['expected_end_date'] = $defaultExpectedEndDate;
}

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

           $stmt = $pdo->prepare("INSERT INTO projects (project_name, cost_center_no, po_number, customer_id, contract_date, expected_end_date, actual_end_date, business_line_id) VALUES (:name, NULLIF(:cost, ''), NULLIF(:po, ''), :customer, NULLIF(:contract, '')::date, NULLIF(:expected, '')::date, NULLIF(:actual, '')::date, :business)");
            $stmt->execute([
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
            $successDetails = [
                'Name' => $submitted['project_name'],
                'Cost Center' => $submitted['cost_center_no'] ?: '—',
                'PO' => $submitted['po_number'] ?: '—',
                'Customer' => option_label($customerOptions, $submitted['customer_id']),
                'Contract' => $submitted['contract_date'] ?: '—',
                'Expected End' => $submitted['expected_end_date'] ?: '—',
                'Actual End' => $submitted['actual_end_date'] ?: '—',
                'Business Line' => option_label($businessLineOptions, $submitted['business_line_id']),
            ];

            $successRows = '';
            foreach ($successDetails as $label => $value) {
                $successRows .= '<tr><th>' . safe($label) . '</th><td>' . safe($value) . '</td></tr>';
            }

            $success = 'Project created successfully (ID #' . $newId . ').';
            $successHtml = $success . '<div class="message-table__wrapper"><table class="message-table">' . $successRows . '</table></div>';
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

             $stmt = $pdo->prepare("UPDATE projects SET project_name = :name, cost_center_no = NULLIF(:cost, ''), po_number = NULLIF(:po, ''), customer_id = :customer, contract_date = NULLIF(:contract, '')::date, expected_end_date = NULLIF(:expected, '')::date, actual_end_date = NULLIF(:actual, '')::date, business_line_id = :business WHERE project_id = :id");
            $stmt->execute([
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
            $selectedIds = array_map('intval', (array) ($_POST['selected_ids'] ?? []));
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

$filters = [
    'project_name' => trim($_GET['filter_project_name'] ?? ''),
    'cost_center_no' => trim($_GET['filter_cost_center_no'] ?? ''),
    'po_number' => trim($_GET['filter_po_number'] ?? ''),
    'business_line_id' => trim($_GET['filter_business_line_id'] ?? ''),
    'customer_id' => trim($_GET['filter_customer_id'] ?? ''),
    'contract_date' => trim($_GET['filter_contract_date'] ?? ''),
    'expected_end_date' => trim($_GET['filter_expected_end_date'] ?? ''),
    'actual_end_date' => trim($_GET['filter_actual_end_date'] ?? ''),
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
        if ($filters['customer_id'] !== '') {
            $conditions[] = 'p.customer_id = :filter_customer';
            $params[':filter_customer'] = $filters['customer_id'];
        }
        if ($filters['contract_date'] !== '') {
            $conditions[] = 'p.contract_date = :filter_contract';
            $params[':filter_contract'] = $filters['contract_date'];
        }
        if ($filters['expected_end_date'] !== '') {
            $conditions[] = 'p.expected_end_date = :filter_expected';
            $params[':filter_expected'] = $filters['expected_end_date'];
        }
        if ($filters['actual_end_date'] !== '') {
            $conditions[] = 'p.actual_end_date = :filter_actual';
            $params[':filter_actual'] = $filters['actual_end_date'];
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
  <style>
    .project-grid {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }

    .project-card {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      background: linear-gradient(135deg, #0b8dc0, #0f4b8c);
      color: #fff;
      border-radius: 10px;
      padding: 14px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      min-height: 160px;
      position: relative;
      text-decoration: none;
    }

    .project-card:hover,
    .project-card:focus-visible {
      transform: translateY(-2px);
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
      outline: none;
    }

.project-card h4,
    .project-card p,
    .project-card small {
      color: #fff;
      margin: 0;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .project-card__footer {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }

    .project-card__footer .btn,
    .project-card__footer a.btn {
      flex: 1;
      text-align: center;
    }

     .project-status {
      position: absolute;
      height:35px;
      top: 10px;
      left: 10px;
      right: auto;
      border-radius: 6px;
      background: var(--secondary);
      padding: 6px 10px;
      min-width: 80px;
      text-align: center;
    }

    .project-status.module-card__status--blocked {
      background: var(--secondary);
    }

     .project-card__select {
      position: absolute;
      top: 10px;
      right: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px;
      border-radius: 10px;
    }

    .project-card__select input[type="checkbox"] {
      appearance: none;
      width: 18px;
      height: 18px;
      border: 2px solid #fff;
      border-radius: 6px;
      background: transparent;
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: background-color 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
    }

    .project-card__select input[type="checkbox"]:checked {
      background: var(--secondary);
      border-color: var(--secondary);
      box-shadow: inset 0 0 0 2px #ffffffff;
    }

    .project-card__select input[type="checkbox"]:focus-visible {
      outline: 2px solid #fff;
      outline-offset: 2px;
    }

    .project-modal .message-dialog {
      max-width: 860px;
    }

    .project-form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }

    .details-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 10px;
    }

    .details-grid__item {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px;
    }

    .details-grid__item h5 {
      margin: 0 0 4px;
      color: var(--secondary);
    }

    .details-grid__item p {
      margin: 0;
      color: var(--text);
    }

    .message-table__wrapper {
      margin-top: 8px;
      overflow-x: auto;
    }

    .message-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 6px;
      overflow: hidden;
    }

    .message-table th,
    .message-table td {
      padding: 6px 10px;
      border-bottom: 1px solid var(--border);
    }

    .message-table th {
      width: 40%;
      text-align: left;
      background: rgba(0, 0, 0, 0.03);
      font-weight: 600;
      color: var(--secondary);
    }

    .message-table tr:last-child th,
    .message-table tr:last-child td {
      border-bottom: none;
    }
  </style>
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

   <?php
    $messageBody = $modalOverride['subtitle'] ?? ($error !== '' ? safe($error) : ($successHtml !== '' ? $successHtml : safe($success)));
  ?>

  <?php if ($error !== '' || $success !== ''): ?>
    <div class="message-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Projects notification" data-dismissable>
      <div class="message-dialog <?php echo $error ? 'is-error' : 'is-success'; ?>">
        <div class="message-dialog__header">
          <span class="message-title"><?php echo $modalOverride['title'] ?? ($error ? 'Action needed' : 'Success'); ?></span>
          <button class="message-close" type="button" aria-label="Close message" data-close-modal>&times;</button>
        </div>
        <div class="message-body"><?php echo $messageBody; ?></div>
      </div>
    </div>
  <?php endif; ?>

  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container" style="display:grid; gap:16px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
          <h3 style="margin:0; color:var(--secondary);">Create, view, update or delete projects</h3>
          <p style="margin:6px 0 0; color:var(--muted);">Use the create button to add projects, then manage or review them from the cards below.</p>
        </div>
        <button class="btn btn-save" type="button" data-open-create style="white-space:nowrap;">Create project</button>
      </div>

      <form method="GET" action="projects.php" class="filter-form" style="display:grid; gap:10px;">
        <div class="form-row" style="display:flex; flex-wrap:nowrap; gap:12px; align-items:flex-end; overflow-x:auto; padding-bottom:6px;">
          <div style="flex:1; min-width:170px;">
            <label class="label" for="filter_project_name">Filter by Name</label>
            <input type="text" id="filter_project_name" name="filter_project_name" value="<?php echo safe($filters['project_name']); ?>" placeholder="Project name" />
          </div>
          <div style="flex:1; min-width:150px;">
            <label class="label" for="filter_cost_center_no">Cost Center</label>
            <input type="text" id="filter_cost_center_no" name="filter_cost_center_no" value="<?php echo safe($filters['cost_center_no']); ?>" placeholder="Cost center" />
          </div>
          <div style="flex:1; min-width:150px;">
            <label class="label" for="filter_po_number">PO</label>
            <input type="text" id="filter_po_number" name="filter_po_number" value="<?php echo safe($filters['po_number']); ?>" placeholder="PO number" />
          </div>
          <div style="flex:1; min-width:180px;">
            <label class="label" for="filter_customer_id">Customer</label>
            <select id="filter_customer_id" name="filter_customer_id">
              <option value="">All customers</option>
              <?php foreach ($customerOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $filters['customer_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="flex:1; min-width:180px;">
            <label class="label" for="filter_business_line_id">Business Line</label>
            <select id="filter_business_line_id" name="filter_business_line_id">
              <option value="">All business lines</option>
              <?php foreach ($businessLineOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $filters['business_line_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="flex:1; min-width:170px;">
            <label class="label" for="filter_contract_date">Contract date</label>
            <input type="date" id="filter_contract_date" name="filter_contract_date" value="<?php echo safe($filters['contract_date']); ?>" />
          </div>
          <div style="flex:1; min-width:170px;">
            <label class="label" for="filter_expected_end_date">Expected end</label>
            <input type="date" id="filter_expected_end_date" name="filter_expected_end_date" value="<?php echo safe($filters['expected_end_date']); ?>" />
          </div>
          <div style="flex:1; min-width:170px;">
            <label class="label" for="filter_actual_end_date">Actual end</label>
            <input type="date" id="filter_actual_end_date" name="filter_actual_end_date" value="<?php echo safe($filters['actual_end_date']); ?>" />
          </div>
        </div>
        <div class="actions" style="justify-content:flex-start; gap:10px;">
          <button class="btn btn-update" type="submit">Apply Filters</button>
          <a class="btn btn-delete" href="projects.php" style="text-decoration:none;">Reset</a>
        </div>
      </form>

      <?php if (!$projects): ?>
        <div class="empty-state">No projects recorded yet. Use the Create project button to add one.</div>
      <?php endif; ?>

      <form method="POST" action="projects.php" onsubmit="return confirm('Delete selected projects?');" style="display:grid; gap:12px;">
        <input type="hidden" name="action" value="bulk_delete" />
        <div class="actions" style="justify-content:flex-end; gap:10px;">
          <button class="btn btn-delete" type="submit">Delete selected</button>
        </div>
        <div class="project-grid">
          <?php foreach ($projects as $project): ?>
            <?php
              $businessLineName = $project['business_line_name'] ?: 'Business line not set';
              $statusClass = $project['business_line_name'] ? 'module-card__status--allowed' : 'module-card__status--blocked';
            ?>
            <div class="module-card module-card--no-image project-card" tabindex="0">
              <span class="module-card__status project-status <?php echo safe($statusClass); ?>" aria-label="Business line">
                <?php echo safe($businessLineName); ?>
              </span>
              <label class="project-card__select" title="Select project" aria-label="Select project">
                <input type="checkbox" name="selected_ids[]" value="<?php echo safe($project['project_id']); ?>" />
              </label>
              <div class="module-card__body" style="display:grid; gap:6px; align-content:start;">
                <h4><?php echo safe($project['project_name']); ?></h4>
                <p><small>PO: <?php echo safe($project['po_number'] ?: '—'); ?> | Cost center: <?php echo safe($project['cost_center_no'] ?: '—'); ?></small></p>
                <p><small>Customer: <?php echo safe($project['customer_name'] ?: $project['customer_id'] ?: '—'); ?></small></p>
              </div>
              <div class="project-card__footer">
                <button class="btn btn-update" type="button" data-open-manage="<?php echo safe($project['project_id']); ?>">Manage</button>
                <button class="btn btn-neutral" type="button" data-open-details="<?php echo safe($project['project_id']); ?>">View details</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </form>

      <?php foreach ($projects as $project): ?>
        <div class="message-modal project-modal" data-manage-modal="<?php echo safe($project['project_id']); ?>" role="dialog" aria-modal="true" aria-label="Manage project <?php echo safe($project['project_name']); ?>">
          <div class="message-dialog">
            <div class="message-dialog__header">
              <span class="message-title">Manage <?php echo safe($project['project_name']); ?></span>
              <button class="message-close" type="button" aria-label="Close manage project" data-close-modal>&times;</button>
            </div>
            <form method="POST" action="projects.php" style="display:grid; gap:12px;">
              <input type="hidden" name="action" value="update" />
              <input type="hidden" name="project_id" value="<?php echo safe($project['project_id']); ?>" />
              <div class="project-form-grid">
                <div>
                  <label class="label" for="project-name-<?php echo safe($project['project_id']); ?>">Project Name</label>
                  <input id="project-name-<?php echo safe($project['project_id']); ?>" name="project_name" type="text" value="<?php echo safe($project['project_name']); ?>" required />
                </div>
                <div>
                  <label class="label" for="cost-center-<?php echo safe($project['project_id']); ?>">Cost Center No</label>
                  <input id="cost-center-<?php echo safe($project['project_id']); ?>" name="cost_center_no" type="text" value="<?php echo safe($project['cost_center_no']); ?>" />
                </div>
                <div>
                  <label class="label" for="po-number-<?php echo safe($project['project_id']); ?>">PO Number</label>
                  <input id="po-number-<?php echo safe($project['project_id']); ?>" name="po_number" type="text" value="<?php echo safe($project['po_number']); ?>" />
                </div>
                <div>
                  <label class="label" for="project-customer-<?php echo safe($project['project_id']); ?>">Customer</label>
                  <select id="project-customer-<?php echo safe($project['project_id']); ?>" name="customer_id" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customerOptions as $option): ?>
                      <option value="<?php echo safe($option['value']); ?>" <?php echo $project['customer_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="label" for="contract-date-<?php echo safe($project['project_id']); ?>">Contract Date</label>
                  <input id="contract-date-<?php echo safe($project['project_id']); ?>" name="contract_date" type="date" value="<?php echo safe($project['contract_date']); ?>" />
                </div>
                <div>
                  <label class="label" for="expected-date-<?php echo safe($project['project_id']); ?>">Expected End Date</label>
                  <input id="expected-date-<?php echo safe($project['project_id']); ?>" name="expected_end_date" type="date" value="<?php echo safe($project['expected_end_date']); ?>" />
                </div>
                <div>
                  <label class="label" for="actual-date-<?php echo safe($project['project_id']); ?>">Actual End Date</label>
                  <input id="actual-date-<?php echo safe($project['project_id']); ?>" name="actual_end_date" type="date" value="<?php echo safe($project['actual_end_date']); ?>" />
                </div>
                <div>
                  <label class="label" for="business-line-<?php echo safe($project['project_id']); ?>">Business Line</label>
                  <select id="business-line-<?php echo safe($project['project_id']); ?>" name="business_line_id" required>
                    <option value="">-- Select Business Line --</option>
                    <?php foreach ($businessLineOptions as $option): ?>
                      <option value="<?php echo safe($option['value']); ?>" <?php echo $project['business_line_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="actions" style="justify-content:flex-end; gap:10px;">
                <button class="btn btn-update" type="submit">Update project</button>
              </div>
            </form>
            <form method="POST" action="projects.php" onsubmit="return confirm('Delete this project?');" style="display:flex; justify-content:flex-end;">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="project_id" value="<?php echo safe($project['project_id']); ?>" />
              <button class="btn btn-delete" type="submit">Delete project</button>
            </form>
          </div>
        </div>

        <div class="message-modal project-modal" data-details-modal="<?php echo safe($project['project_id']); ?>" role="dialog" aria-modal="true" aria-label="Project details for <?php echo safe($project['project_name']); ?>">
          <div class="message-dialog">
            <div class="message-dialog__header">
              <span class="message-title">Project details</span>
              <button class="message-close" type="button" aria-label="Close project details" data-close-modal>&times;</button>
            </div>
            <div class="details-grid">
              <div class="details-grid__item">
                <h5>Name</h5>
                <p><?php echo safe($project['project_name']); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Customer</h5>
                <p><?php echo safe($project['customer_name'] ?: $project['customer_id'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Business line</h5>
                <p><?php echo safe($project['business_line_name'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>PO number</h5>
                <p><?php echo safe($project['po_number'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Cost center</h5>
                <p><?php echo safe($project['cost_center_no'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Contract date</h5>
                <p><?php echo safe($project['contract_date'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Expected end</h5>
                <p><?php echo safe($project['expected_end_date'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Actual end</h5>
                <p><?php echo safe($project['actual_end_date'] ?: '—'); ?></p>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <div class="message-modal project-modal" id="create-project-modal" role="dialog" aria-modal="true" aria-label="Create project">
    <div class="message-dialog">
      <div class="message-dialog__header">
        <span class="message-title">Create a new project</span>
        <button class="message-close" type="button" aria-label="Close create project" data-close-modal>&times;</button>
      </div>
      <form method="POST" action="projects.php" style="display:grid; gap:12px;">
        <input type="hidden" name="action" value="create" />
        <div class="project-form-grid">
          <div>
            <label class="label" for="project-name">Project Name</label>
            <input id="project-name" name="project_name" type="text" placeholder="Wind Farm Expansion" value="<?php echo safe($submitted['project_name']); ?>" required />
          </div>
          <div>
            <label class="label" for="cost-center">Cost Center No</label>
            <input id="cost-center" name="cost_center_no" type="text" placeholder="CC-1001" value="<?php echo safe($submitted['cost_center_no']); ?>" />
          </div>
          <div>
            <label class="label" for="po-number">PO Number</label>
            <input id="po-number" name="po_number" type="text" placeholder="PO-2025-01" value="<?php echo safe($submitted['po_number']); ?>" />
          </div>
          <div>
            <label class="label" for="project-customer">Customer</label>
            <select id="project-customer" name="customer_id" required>
              <option value="">-- Select Customer --</option>
              <?php foreach ($customerOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['customer_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
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
          <div>
            <label class="label" for="business-line">Business Line</label>
            <select id="business-line" name="business_line_id" required>
              <option value="">-- Select Business Line --</option>
              <?php foreach ($businessLineOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['business_line_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="actions" style="justify-content:flex-end; gap:10px;">
          <button class="btn" type="button" data-close-modal>Cancel</button>
          <button class="btn btn-save" type="submit">Create project</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const closeButtons = document.querySelectorAll('[data-close-modal]');
      const openCreateButtons = document.querySelectorAll('[data-open-create]');
      const createModal = document.getElementById('create-project-modal');

      const setDefaultExpectedDate = (contractInput, expectedInput) => {
        if (!contractInput || !expectedInput) return;

        const updateExpected = () => {
          const contractValue = contractInput.value;
          const expectedWasAuto = expectedInput.dataset.autofilled === 'true';

          if (!contractValue) return;

          if (expectedInput.value === '' || expectedWasAuto) {
            const contractDate = new Date(contractValue);
            if (Number.isNaN(contractDate.getTime())) return;

            contractDate.setDate(contractDate.getDate() + 3);
            const adjusted = contractDate.toISOString().split('T')[0];
            expectedInput.value = adjusted;
            expectedInput.dataset.autofilled = 'true';
          }
        };

        contractInput.addEventListener('change', updateExpected);
        contractInput.addEventListener('input', updateExpected);
        expectedInput.addEventListener('input', () => {
          expectedInput.dataset.autofilled = 'false';
        });

        updateExpected();
      };

      const hideModal = (modal) => {
        if (modal) {
          modal.classList.remove('is-visible');
        }
      };

      const showModal = (modal) => {
        if (modal) {
          modal.classList.add('is-visible');
        }
      };

      closeButtons.forEach((button) => {
        button.addEventListener('click', () => {
          const modal = button.closest('.message-modal');
          hideModal(modal);
        });
      });

      document.querySelectorAll('.message-modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
          if (event.target === modal && !modal.hasAttribute('data-dismissable')) {
            hideModal(modal);
          }
        });
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          document.querySelectorAll('.message-modal.is-visible').forEach((modal) => hideModal(modal));
        }
       });

      openCreateButtons.forEach((button) => button.addEventListener('click', () => showModal(createModal)));

      setDefaultExpectedDate(
        document.getElementById('project-start'),
        document.getElementById('project-end')
      );

      document.querySelectorAll('[data-open-manage]').forEach((button) => {
        const target = button.getAttribute('data-open-manage');
        const modal = document.querySelector(`[data-manage-modal="${target}"]`);
        if (!modal) return;

        button.addEventListener('click', () => showModal(modal));
      });

      document.querySelectorAll('[data-open-details]').forEach((button) => {
        const target = button.getAttribute('data-open-details');
        const modal = document.querySelector(`[data-details-modal="${target}"]`);
        if (!modal) return;

        button.addEventListener('click', () => showModal(modal));
      });

      document.querySelectorAll('input[id^="contract-date-"]').forEach((contractInput) => {
        const projectId = contractInput.id.replace('contract-date-', '');
        const expectedInput = document.getElementById(`expected-date-${projectId}`);
        setDefaultExpectedDate(contractInput, expectedInput);
      });
    });
  </script>
</body>
</html>