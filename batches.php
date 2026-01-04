<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$moduleCode = resolve_module_code('BATCHES');

$error = '';
$success = '';
$modalOverride = null;

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    $pdo = null;
    $error = format_db_error($e, 'database connection');
}

$projectId = isset($_GET['project_id'])
    ? (int) $_GET['project_id']
    : (isset($_POST['project_id']) ? (int) $_POST['project_id'] : null);

$filters = [
    'project_name' => trim($_GET['filter_project_name'] ?? $_POST['filter_project_name'] ?? ''),
    'po_number' => trim($_GET['filter_po_number'] ?? $_POST['filter_po_number'] ?? ''),
    'cost_center_no' => trim($_GET['filter_cost_center_no'] ?? $_POST['filter_cost_center_no'] ?? ''),
];

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $permissionError = enforce_action_permission(
        $currentUser,
        $moduleCode ?? 'BATCHES',
        $action,
        [
             'create_batch' => 'create',
            'create_sub_batches' => 'create',
            'delete_batch' => 'delete',
            'update_batch' => 'update',
            'bulk_delete_batches' => 'delete',
        ]
    );


    try {
        if ($permissionError) {
            $modalOverride = permission_denied_modal();
            $error = $permissionError;
        } elseif ($action === 'create_batch') {
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $baseName = trim((string) ($_POST['batch_base'] ?? ''));
            $count = max(1, (int) ($_POST['batch_count'] ?? 1));
            $rawNames = $_POST['batch_names'] ?? [];
            $names = [];

            foreach ($rawNames as $name) {
                $trimmed = trim((string) $name);
                if ($trimmed !== '') {
                    if (strlen($trimmed) > 255) {
                        throw new RuntimeException('Batch name must be 255 characters or fewer.');
                    }
                    $names[] = $trimmed;
                }
            }

            if ($projectId <= 0) {
                throw new RuntimeException('Select a project before creating a batch.');
            }
            if (!$names && $baseName === '') {
                throw new RuntimeException('Provide at least one batch name.');
            }
            if ($names) {
                $count = count($names);
            }
            if ($count > 50) {
                throw new RuntimeException('Please create 50 or fewer batches at once.');
            }
            if (!$names && strlen($baseName) > 255) {
                throw new RuntimeException('Batch name must be 255 characters or fewer.');
            }

            $projectCheck = $pdo->prepare('SELECT 1 FROM projects WHERE project_id = :id');
            $projectCheck->execute([':id' => $projectId]);
            if (!$projectCheck->fetchColumn()) {
                throw new RuntimeException('Project not found.');
            }

            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare('INSERT INTO batches (project_id, batch_name) VALUES (:project_id, :batch_name)');

                if ($names) {
                    foreach ($names as $name) {
                        $insert->execute([
                            ':project_id' => $projectId,
                            ':batch_name' => $name,
                        ]);
                    }
                } else {
                    for ($i = 1; $i <= $count; $i++) {
                        $name = $baseName;
                        if ($count > 1) {
                            $name = sprintf('%s %d', $baseName, $i);
                        }
                        $insert->execute([
                            ':project_id' => $projectId,
                            ':batch_name' => $name,
                        ]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $inner) {
                $pdo->rollBack();
                throw $inner;
            }

            $success = $count > 1 ? 'Batches created successfully.' : 'Batch created successfully.';
        } elseif ($action === 'create_sub_batches') {
            $batchId = (int) ($_POST['batch_id'] ?? 0);
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $baseName = trim((string) ($_POST['sub_batch_base'] ?? ''));
            $count = max(1, (int) ($_POST['sub_batch_count'] ?? 1));
            $description = trim((string) ($_POST['sub_batch_description'] ?? ''));

            if ($batchId <= 0 || $projectId <= 0) {
                throw new RuntimeException('Choose a batch before adding sub-batches.');
            }
            if ($baseName === '') {
                throw new RuntimeException('A base name for the sub-batches is required.');
            }
            if ($count > 50) {
                throw new RuntimeException('Please create 50 or fewer sub-batches at once.');
            }

            $batchCheck = $pdo->prepare('SELECT project_id FROM batches WHERE batch_id = :id');
            $batchCheck->execute([':id' => $batchId]);
            $batchRow = $batchCheck->fetch();

            if (!$batchRow) {
                throw new RuntimeException('Batch not found.');
            }
            if ((int) $batchRow['project_id'] !== $projectId) {
                throw new RuntimeException('This batch does not belong to the selected project.');
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO sub_batch_details (batch_id, sub_batch_name, description) VALUES (:batch_id, :name, NULLIF(:description, ""))');

                for ($i = 1; $i <= $count; $i++) {
                    $name = $baseName;
                    if ($count > 1) {
                        $name = sprintf('%s %d', $baseName, $i);
                    }
                    $stmt->execute([
                        ':batch_id' => $batchId,
                        ':name' => $name,
                        ':description' => $description,
                    ]);
                }

                $pdo->commit();
                $success = 'Sub-batches created successfully.';
            } catch (Throwable $inner) {
                $pdo->rollBack();
                throw $inner;
            }
        } elseif ($action === 'delete_batch') {
            $batchId = (int) ($_POST['batch_id'] ?? 0);
            if ($batchId <= 0) {
                throw new RuntimeException('Choose a batch before deleting.');
            }
            $delete = $pdo->prepare('DELETE FROM batches WHERE batch_id = :id');
            $delete->execute([':id' => $batchId]);
            if ($delete->rowCount() === 0) {
                throw new RuntimeException('Batch not found or already removed.');
            }
            $success = 'Batch deleted successfully.';
        } elseif ($action === 'update_batch') {
            $batchId = (int) ($_POST['batch_id'] ?? 0);
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $batchName = trim((string) ($_POST['batch_name'] ?? ''));

            if ($batchId <= 0 || $projectId <= 0) {
                throw new RuntimeException('Select a batch to update.');
            }
            if ($batchName === '') {
                throw new RuntimeException('Batch name cannot be empty.');
            }
            if (strlen($batchName) > 255) {
                throw new RuntimeException('Batch name must be 255 characters or fewer.');
            }

            $check = $pdo->prepare('SELECT project_id FROM batches WHERE batch_id = :id');
            $check->execute([':id' => $batchId]);
            $row = $check->fetch();

            if (!$row) {
                throw new RuntimeException('Batch not found.');
            }
            if ((int) $row['project_id'] !== $projectId) {
                throw new RuntimeException('This batch does not belong to the selected project.');
            }

            $update = $pdo->prepare('UPDATE batches SET batch_name = :name WHERE batch_id = :id');
            $update->execute([
                ':name' => $batchName,
                ':id' => $batchId,
            ]);

            $success = 'Batch updated successfully.';
        } elseif ($action === 'bulk_delete_batches') {
            $selectedIds = array_filter(array_map('intval', (array) ($_POST['selected_ids'] ?? [])));
            $projectId = (int) ($_POST['project_id'] ?? 0);

            if (!$selectedIds) {
                throw new RuntimeException('Select at least one batch to delete.');
            }
            if ($projectId <= 0) {
                throw new RuntimeException('Choose a project before deleting batches.');
            }

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $delete = $pdo->prepare("DELETE FROM batches WHERE project_id = ? AND batch_id IN ({$placeholders})");
            $delete->execute(array_merge([$projectId], $selectedIds));

            $success = $delete->rowCount() . ' batch(es) removed.';
        }
    } catch (Throwable $e) {
        $error = $error ?: format_db_error($e, 'batches module');
    }
}
$projects = [];
$projectDetail = null;
$batchesByProject = [];
$subBatchesByBatch = [];

if ($pdo) {
    try {
        if ($projectId) {
            $stmt = $pdo->prepare('SELECT project_id, project_name, cost_center_no, po_number, customer_id, contract_date, expected_end_date, actual_end_date, business_line_id FROM projects WHERE project_id = :id');
            $stmt->execute([':id' => $projectId]);
            $projectDetail = $stmt->fetch();

            if ($projectDetail) {
                $batchStmt = $pdo->prepare('SELECT batch_id, project_id, batch_name FROM batches WHERE project_id = :project ORDER BY batch_id DESC');
                $batchStmt->execute([':project' => $projectId]);
                $batches = $batchStmt->fetchAll();

                foreach ($batches as $batch) {
                    $batchesByProject[$projectId][] = $batch;
                }

                $batchIds = array_column($batches, 'batch_id');
                if ($batchIds) {
                    $batchPlaceholders = implode(',', array_fill(0, count($batchIds), '?'));
                    $subStmt = $pdo->prepare("SELECT sub_batch_detail_id, batch_id, sub_batch_name, COALESCE(description, '') AS description FROM sub_batch_details WHERE batch_id IN ({$batchPlaceholders}) ORDER BY sub_batch_detail_id");
                    $subStmt->execute($batchIds);
                    $subBatches = $subStmt->fetchAll();

                    foreach ($subBatches as $subBatch) {
                        $batchId = (int) $subBatch['batch_id'];
                        $subBatchesByBatch[$batchId][] = $subBatch;
                    }
                }
            }
        } else {
            $conditions = [];
            $params = [];

            if ($filters['project_name'] !== '') {
                $conditions[] = 'LOWER(project_name) LIKE :name';
                $params[':name'] = '%' . strtolower($filters['project_name']) . '%';
            }
            if ($filters['po_number'] !== '') {
                $conditions[] = 'LOWER(po_number) LIKE :po';
                $params[':po'] = '%' . strtolower($filters['po_number']) . '%';
            }
            if ($filters['cost_center_no'] !== '') {
                $conditions[] = 'LOWER(cost_center_no) LIKE :cost';
                $params[':cost'] = '%' . strtolower($filters['cost_center_no']) . '%';
            }

            $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $sql = "SELECT p.project_id, p.project_name, p.cost_center_no, p.po_number, COALESCE(bc.batch_count, 0) AS batch_count
                    FROM projects p
                    LEFT JOIN (
                        SELECT project_id, COUNT(*) AS batch_count
                        FROM batches
                        GROUP BY project_id
                    ) bc ON bc.project_id = p.project_id
                    {$whereSql}
                    ORDER BY p.project_id DESC
                    LIMIT 50";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $projects = $stmt->fetchAll();
        }
    } catch (Throwable $e) {
        $error = $error ?: format_db_error($e, 'projects and batches');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Batches | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
  <style>
   .batches-card {
      background: #282828ff;
      color: #fff;
      border: none;
      text-decoration: none;
      transition: transform 120ms ease, box-shadow 120ms ease;
      position: relative;
      min-height: 140px;
      /* align-items:center; */
       }

    .batches-card:hover,
    .batches-card:focus-visible {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      outline: none;
    }

     .batches-card h4,
    .batches-card p,
    .batches-card small {
      color: #fff;
    }

    .batches-card h4{
        padding-top:20px;
    }
   .batches-card__footer {
      display: flex;
      justify-content: space-between;
      align-items: stretch;
      margin-top: 12px;
      gap: 10px;
    }

/* .batches-card .module-card__status {
  position: static;
  margin-left: auto;
  border-top-left-radius: 8px;
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
  border-bottom-left-radius: 0;
} */

 .batch-panel {
  background: #fff;
  color: var(--text);
  border-radius: 10px;
  padding: 12px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border);
  display: grid;
  gap: 10px;
  margin-top: 6px;
 }

 .batch-panel h4 {
  margin: 0;
 }

 .batch-panel__actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
 }

 .batch-listing {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
 }

 .create-batch-modal .message-dialog {
  max-width: 720px;
 }

 .name-inputs {
  display: grid;
  gap: 8px;
  max-height: 320px;
  overflow-y: auto;
  padding-right: 4px;
 }

.batch-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .batch-card__select {
    position: absolute;
    top: 10px;
    right: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    /* background: rgba(0, 0, 0, 0.35); */
    padding: 8px;
    border-radius: 10px;
  }

  .batch-card__select input[type="checkbox"] {
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

  .batch-card__select input[type="checkbox"]:checked {
    background: var(--secondary);
    border-color: var(--secondary);
     box-shadow: inset 0 0 0 2px #ffffffff;
  }

  .batch-card__select input[type="checkbox"]:focus-visible {
    outline: 2px solid #fff;
    outline-offset: 2px;
  }

 .batch-grid .batches-card__footer .btn,
 .batch-grid .batches-card__footer a.btn {
  flex: 1;
  text-align: center;
 }

 .batch-grid .batches-card__footer {
  align-items: stretch;
 }

 .batch-grid .module-card__body {
  text-align: center;
  justify-items: center;
 }

 @media (max-width: 1200px) {
  .batch-grid {
   grid-template-columns: repeat(3, minmax(0, 1fr));
  }
 }

 @media (max-width: 900px) {
  .batch-grid {
   grid-template-columns: repeat(2, minmax(0, 1fr));
  }
 }

 @media (max-width: 600px) {
  .batch-grid {
   grid-template-columns: 1fr;
  }
  }
  </style>
  <script src="./assets/app.js" defer></script>
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
    </div>
    <div class="title">Batches</div>
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
    <div class="message-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Batches notification">
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
    <div class="form-container" style="display:grid; gap:16px;">
      <?php if (!$projectId): ?>
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-end; flex-wrap:wrap;">
          <div>
            <h3 style="margin:0; color:var(--secondary);">Select a project to manage batches</h3>
            <p style="margin:6px 0 0; color:var(--muted);">Create, update, or delete batches per project.</p>
          </div>
          <!-- <div style="color:var(--muted); font-size:14px;">Create, update, or delete batches per project.</div> -->
        </div>

        <form class="filter-form" method="GET" action="batches.php" style="margin-bottom:4px; display:grid; gap:10px;">
          <div class="form-row" style="gap:12px;">
            <div style="flex:1; min-width:180px;">
              <label class="label" for="filter_project_name">Project name</label>
              <input type="text" id="filter_project_name" name="filter_project_name" value="<?php echo safe($filters['project_name']); ?>" placeholder="Wind Farm" />
            </div>
            <div style="flex:1; min-width:150px;">
              <label class="label" for="filter_po_number">PO number</label>
              <input type="text" id="filter_po_number" name="filter_po_number" value="<?php echo safe($filters['po_number']); ?>" placeholder="PO-2025-01" />
            </div>
            <div style="flex:1; min-width:150px;">
              <label class="label" for="filter_cost_center_no">Cost center</label>
              <input type="text" id="filter_cost_center_no" name="filter_cost_center_no" value="<?php echo safe($filters['cost_center_no']); ?>" placeholder="CC-1001" />
            </div>
          </div>
          <div class="actions" style="justify-content:flex-start; gap:10px;">
            <button class="btn btn-update" type="submit">Apply filters</button>
            <a class="btn btn-delete" style="text-decoration:none;" href="batches.php">Reset</a>
          </div>
        </form>

        <?php if (!$projects): ?>
          <div class="empty-state">No projects found. Adjust the filters above to load matching projects.</div>
        <?php endif; ?>

        <div class="module-grid">
          <?php foreach ($projects as $project): ?>
           <?php
              $batchCount = (int) ($project['batch_count'] ?? 0);
              $hasBatches = $batchCount > 0;
              $statusClass = $hasBatches ? 'module-card__status--allowed' : 'module-card__status--blocked';
              $statusLabel = $hasBatches ? sprintf('%d - batches', $batchCount) : 'No batches';
            ?>
         <a class="module-card module-card--link module-card--no-image batches-card" style="display:flex; flex-direction:column; justify-content:space-between;" data-project-id="<?php echo safe((string) $project['project_id']); ?>" href="batches.php?project_id=<?php echo safe($project['project_id']); ?>">
              <div class="module-card__body" style="flex:1;"> 
                <h4 style="margin-bottom:6px;"><?php echo safe($project['project_name']); ?></h4>
                <p style="margin:0;"><small>PO: <?php echo safe($project['po_number'] ?: '—'); ?> | Cost center: <?php echo safe($project['cost_center_no'] ?: '—'); ?></small></p>
              </div> 
              <div class="batches-card__footer">
                <span class="module-card__status <?php echo safe($statusClass); ?>" aria-label="Batch availability"><?php echo safe($statusLabel); ?></span> 
              </div> 
            </a>
          <?php endforeach; ?> 
        </div> 
      <?php else: ?>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
          <div style="display:grid; gap:6px;">
            <a class="btn" style="width:fit-content; text-decoration:none;" href="batches.php">← All projects</a>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
              <h2 style="margin:0; color:var(--secondary);"><?php echo safe($projectDetail['project_name'] ?? 'Project'); ?></h2>
              <?php if ($projectDetail): ?>
                <span style="color:var(--muted);">PO: <?php echo safe($projectDetail['po_number'] ?: '—'); ?> | Cost center: <?php echo safe($projectDetail['cost_center_no'] ?: '—'); ?></span>
              <?php endif; ?>
            </div>
            <?php if ($projectDetail): ?>
              <div style="color:var(--muted); font-size:14px;">Contract: <?php echo safe($projectDetail['contract_date'] ?: '—'); ?> | Expected end: <?php echo safe($projectDetail['expected_end_date'] ?: '—'); ?> | Actual end: <?php echo safe($projectDetail['actual_end_date'] ?: '—'); ?></div>
<?php else: ?>
              <div class="empty-state">Project not found.</div>
            <?php endif; ?>
          </div>
          <div style="display:flex; align-items:center; gap:8px;">
            <button class="btn btn-save" type="button" data-open-batch-modal style="white-space:nowrap;">Create batches</button>
          </div>
        </div>

<?php if ($projectDetail): ?>
          <?php $projectBatches = $batchesByProject[(int) $projectDetail['project_id']] ?? []; ?>
          <?php if (!$projectBatches): ?>
            <div class="module-card module-card--no-image module-card--disabled" style="padding:16px; display:flex; justify-content:space-between; align-items:center; gap:12px;">
              <div class="module-card__body">
                <h4 style="margin:0;">No batches</h4>
                <p style="margin:4px 0 0; color:var(--muted);"><small>Create the first batches for this project.</small></p>
              </div>
              <button class="btn btn-save" type="button" data-open-batch-modal>Create batches</button>
            </div>
          <?php else: ?>
            <div style="display:grid; gap:12px;">
              <form id="bulk-delete-form" method="POST" action="batches.php?project_id=<?php echo safe($projectDetail['project_id']); ?>" style="display:flex; justify-content:flex-end; gap:10px;">
                <input type="hidden" name="action" value="bulk_delete_batches" />
                <input type="hidden" name="project_id" value="<?php echo safe($projectDetail['project_id']); ?>" />
                <button class="btn btn-delete" type="submit">Delete selected</button>
              </form>
              <div class="batch-grid">
                <?php foreach ($projectBatches as $batch): ?>
                  <?php $subBatches = $subBatchesByBatch[(int) $batch['batch_id']] ?? []; ?>
                  <?php $hasSubBatches = count($subBatches) > 0; ?>
                  <?php
                    $statusClass = $hasSubBatches ? 'module-card__status--allowed' : 'module-card__status--blocked';
                    $statusLabel = $hasSubBatches ? sprintf('%d sub-batches', count($subBatches)) : 'No sub-batches';
                  ?>
                  <div class="module-card module-card--no-image batches-card" style="display:flex; flex-direction:column; gap:10px; position:relative;">
                    <label class="batch-card__select" title="Select batch" aria-label="Select batch">
                      <input type="checkbox" name="selected_ids[]" value="<?php echo safe($batch['batch_id']); ?>" form="bulk-delete-form" />
                    </label>
                    <span class="module-card__status batches-card__status <?php echo safe($statusClass); ?>" aria-label="Sub-batch availability"><?php echo safe($statusLabel); ?></span>
                    <div class="module-card__body" style="flex:1; display:grid; gap:6px; align-content:start;">
                      <h4 style="margin:0; color:#fff;">Batch: <?php echo safe($batch['batch_name']); ?></h4>
                      <p style="margin:0; color:#fff;"><small>Project: <?php echo safe($projectDetail['project_name']); ?></small></p>
                    </div>
                    <div class="batches-card__footer">
                      <a class="btn btn-save" style="text-decoration:none;" href="sub-batches.php?batch_id=<?php echo safe($batch['batch_id']); ?>">sub-batches</a>
                      <button class="btn btn-update" type="button" data-open-manage="<?php echo safe($batch['batch_id']); ?>">Manage</button>
                    </div>
                  </div>

                  <div class="message-modal manage-batch-modal" data-batch-modal="<?php echo safe($batch['batch_id']); ?>" role="dialog" aria-modal="true" aria-label="Manage batch <?php echo safe($batch['batch_name']); ?>">
                    <div class="message-dialog">
                      <div class="message-dialog__header">
                        <span class="message-title">Manage batch <?php echo safe($batch['batch_name']); ?></span>
                        <button class="message-close" type="button" data-close-manage>&times;</button>
                      </div>
                      <div style="display:grid; gap:12px;">
                        <form method="POST" action="batches.php" class="form-row" style="gap:6px; align-items:center;">
                          <input type="hidden" name="action" value="update_batch" />
                          <input type="hidden" name="batch_id" value="<?php echo safe($batch['batch_id']); ?>" />
                          <input type="hidden" name="project_id" value="<?php echo safe($projectDetail['project_id']); ?>" />
                          <input type="text" name="batch_name" value="<?php echo safe($batch['batch_name']); ?>" style="min-width:180px;" />
                          <button class="btn btn-update" type="submit">Update name</button>
                        </form>
                        <form method="POST" action="batches.php" onsubmit="return confirm('Delete this batch and its sub-batches?');" style="display:flex; justify-content:flex-end;">
                          <input type="hidden" name="action" value="delete_batch" />
                          <input type="hidden" name="batch_id" value="<?php echo safe($batch['batch_id']); ?>" />
                          <input type="hidden" name="project_id" value="<?php echo safe($projectDetail['project_id']); ?>" />
                          <button class="btn btn-delete" type="submit">Delete batch</button>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
  <?php if ($projectDetail): ?>
    <div class="message-modal create-batch-modal" id="create-batches-modal" role="dialog" aria-modal="true" aria-label="Create batches">
      <div class="message-dialog">
        <div class="message-dialog__header">
          <span class="message-title">Create batches for <?php echo safe($projectDetail['project_name']); ?></span>
          <button class="message-close" type="button" aria-label="Close create batches" data-close-batch-modal>&times;</button>
        </div>
        <form method="POST" action="batches.php" style="display:grid; gap:12px;">
          <input type="hidden" name="action" value="create_batch" />
          <input type="hidden" name="project_id" value="<?php echo safe($projectDetail['project_id']); ?>" />
          <p style="margin:0; color:var(--muted);">Choose how many batches to create, then name each batch individually.</p>

          <div class="form-row" style="gap:10px; align-items:flex-end;">
            <div style="flex:1; min-width:180px;">
              <label class="label" for="batch-count-input">How many batches?</label>
              <input id="batch-count-input" name="batch_count" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Enter a number" value="1" />
            </div>
            <div style="flex:1; min-width:200px;">
              <label class="label" for="batch-base-input">Base name (optional)</label>
              <input id="batch-base-input" name="batch_base" type="text" placeholder="Batch" />
            </div>
          </div>

          <div class="name-inputs" data-batch-names aria-label="Batch names"></div>

          <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button class="btn" type="button" data-close-batch-modal>Cancel</button>
            <button class="btn btn-save" type="submit">Confirm and create</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const messageModal = document.querySelector('.message-modal:not(.create-batch-modal)');
      const messageClose = messageModal ? messageModal.querySelector('.message-close') : null;

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
 const manageButtons = document.querySelectorAll('[data-open-manage]');
      const manageModals = document.querySelectorAll('.manage-batch-modal');

      const hideManageModal = (modal) => {
        modal.classList.remove('is-visible');
      };

      manageButtons.forEach((button) => {
        const targetId = button.getAttribute('data-open-manage');
        const modal = document.querySelector(`[data-batch-modal="${targetId}"]`);
        if (!modal) return;

        button.addEventListener('click', () => {
          modal.classList.add('is-visible');
        });
      });

      manageModals.forEach((modal) => {
        const closeButtons = modal.querySelectorAll('[data-close-manage]');

        closeButtons.forEach((button) => {
          button.addEventListener('click', () => hideManageModal(modal));
        });

        modal.addEventListener('click', (event) => {
          if (event.target === modal) {
            hideManageModal(modal);
          }
        });
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          manageModals.forEach((modal) => hideManageModal(modal));
        }
      });


      const createModal = document.getElementById('create-batches-modal');
       const openModalButtons = document.querySelectorAll('[data-open-batch-modal]');
      const closeModalButtons = createModal ? createModal.querySelectorAll('[data-close-batch-modal]') : [];
      const countInput = document.getElementById('batch-count-input');
      const baseInput = document.getElementById('batch-base-input');
      const namesContainer = createModal ? createModal.querySelector('[data-batch-names]') : null;

      const sanitizeCount = (value) => {
        const parsed = parseInt(value, 10);
        if (Number.isNaN(parsed)) return 1;
        return Math.min(50, Math.max(1, parsed));
      };


      const prefillNamesFromBase = () => {
        if (!baseInput || !namesContainer) return;
        if (baseInput.value === '') return;

        namesContainer.querySelectorAll('input').forEach((input, index) => {
          if (input.value === '') {
            input.value = `${baseInput.value} ${index + 1}`;
          }
        });
      };

      const buildNameInputs = (count) => {
        if (!namesContainer) return;
        namesContainer.innerHTML = '';
        for (let i = 1; i <= count; i++) {
          const wrapper = document.createElement('div');
          wrapper.style.display = 'grid';
          wrapper.style.gap = '4px';

          const label = document.createElement('label');
          label.className = 'label';
          label.textContent = `Batch ${i} name`;
          label.setAttribute('for', `batch-name-${i}`);

          const input = document.createElement('input');
          input.id = `batch-name-${i}`;
          input.name = 'batch_names[]';
          input.type = 'text';
          input.placeholder = `Batch ${i}`;
          input.maxLength = 255;

          wrapper.append(label, input);
          namesContainer.append(wrapper);
        }

        prefillNamesFromBase();
      };

      if (createModal && countInput && namesContainer) {
        const showCreateModal = () => {
          createModal.classList.add('is-visible');
         const initialCount = sanitizeCount(countInput.value || '1');
          countInput.value = initialCount;
          buildNameInputs(initialCount);
        };

        const hideCreateModal = () => {
          createModal.classList.remove('is-visible');
        };

        openModalButtons.forEach((button) => button.addEventListener('click', showCreateModal));
        closeModalButtons.forEach((button) => button.addEventListener('click', hideCreateModal));

        createModal.addEventListener('click', (event) => {
          if (event.target === createModal) {
            hideCreateModal();
          }
        });

        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && createModal.classList.contains('is-visible')) {
            hideCreateModal();
          }
        });

        countInput.addEventListener('input', (event) => {
          const value = sanitizeCount(event.target.value);
          event.target.value = value;
          buildNameInputs(value);
        });

        baseInput?.addEventListener('input', prefillNamesFromBase);
      }

      const bulkDeleteForm = document.getElementById('bulk-delete-form');
      if (bulkDeleteForm) {
        const batchCheckboxes = document.querySelectorAll('input[name="selected_ids[]"]');

        bulkDeleteForm.addEventListener('submit', (event) => {
          const hasSelection = Array.from(batchCheckboxes).some((checkbox) => checkbox.checked);
          if (!hasSelection) {
            event.preventDefault();
            alert('Select at least one batch to delete.');
            return;
          }

          if (!confirm('Delete selected batches?')) {
            event.preventDefault();
          }
        });
      }
    });
  </script>
</body>
</html>