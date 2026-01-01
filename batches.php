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

$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;

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

            if ($projectId <= 0) {
                throw new RuntimeException('Select a project before creating a batch.');
            }
            if ($baseName === '') {
                throw new RuntimeException('Batch name is required.');
            }
            if (strlen($baseName) > 255) {
                throw new RuntimeException('Batch name must be 255 characters or fewer.');
            }
            if ($count > 50) {
                throw new RuntimeException('Please create 50 or fewer batches at once.');
            }

            $projectCheck = $pdo->prepare('SELECT 1 FROM projects WHERE project_id = :id');
            $projectCheck->execute([':id' => $projectId]);
            if (!$projectCheck->fetchColumn()) {
                throw new RuntimeException('Project not found.');
            }

            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare('INSERT INTO batches (project_id, batch_name) VALUES (:project_id, :batch_name)');
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
      align-items: center;
      margin-top: 12px;
      gap: 10px;
    }

 .batches-card .module-card__status {
  position: static;
  margin-left: auto;
  border-top-left-radius: 8px; /* adjust value as needed */
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
  border-bottom-left-radius: 0;
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
          <div style="color:var(--muted); font-size:14px;">Manage batches and sub-batches for this project.</div>
        </div>

        <?php if ($projectDetail): ?>
          <section class="card" style="padding:14px; border:1px solid var(--border); border-radius:8px; box-shadow:var(--shadow-sm); display:grid; gap:12px;">
            <header style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
              <div style="font-weight:600;">Create batches</div>
              <span style="color:var(--muted); font-size:13px;">Up to 50 batches at once</span>
            </header>
            <form method="POST" action="batches.php" class="form-row" style="gap:12px; align-items:flex-end;">
              <input type="hidden" name="action" value="create_batch" />
              <input type="hidden" name="project_id" value="<?php echo safe($projectDetail['project_id']); ?>" />
              <div style="flex:1; min-width:200px;">
                <label class="label" for="batch-base-<?php echo safe($projectDetail['project_id']); ?>">Batch name</label>
                <input id="batch-base-<?php echo safe($projectDetail['project_id']); ?>" name="batch_base" type="text" placeholder="Batch" required />
              </div>
              <div style="width:140px;">
                <label class="label" for="batch-count-<?php echo safe($projectDetail['project_id']); ?>">How many?</label>
                <input id="batch-count-<?php echo safe($projectDetail['project_id']); ?>" name="batch_count" type="number" min="1" max="50" value="1" />
              </div>
              <button class="btn btn-save" type="submit" style="white-space:nowrap;">Create batch</button>
            </form>
          </section>

          <?php $projectBatches = $batchesByProject[(int) $projectDetail['project_id']] ?? []; ?>
          <?php if (!$projectBatches): ?>
            <div class="module-card module-card--no-image module-card--disabled" style="padding:16px;">
              <div class="module-card__body">
                <h4 style="margin:0;">No batches</h4>
                <p style="margin:4px 0 0; color:var(--muted);"><small>Create the first batch for this project.</small></p>
              </div>
              <div class="module-card__status module-card__status--blocked">No batches</div>
            </div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($projectBatches as $batch): ?>
                <?php $subBatches = $subBatchesByBatch[(int) $batch['batch_id']] ?? []; ?>
                <?php $hasSubBatches = count($subBatches) > 0; ?>
                <div class="module-card module-card--no-image" style="display:flex; flex-direction:column; gap:10px;">
                  <div class="module-card__body" style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start; flex-wrap:wrap;">
                    <div>
                      <h4 style="margin:0; color:var(--secondary);">Batch: <?php echo safe($batch['batch_name']); ?></h4>
                      <p style="margin:4px 0 0; color:var(--muted);"><small>Sub-batches: <?php echo count($subBatches); ?></small></p>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                      <form method="POST" action="batches.php" class="form-row" style="gap:6px; align-items:center;">
                        <input type="hidden" name="action" value="update_batch" />
                        <input type="hidden" name="batch_id" value="<?php echo safe($batch['batch_id']); ?>" />
                        <input type="hidden" name="project_id" value="<?php echo safe($projectDetail['project_id']); ?>" />
                        <input type="text" name="batch_name" value="<?php echo safe($batch['batch_name']); ?>" style="min-width:140px;" />
                        <button class="btn btn-update" type="submit">Update</button>
                      </form>
                      <form method="POST" action="batches.php" onsubmit="return confirm('Delete this batch and its sub-batches?');">
                        <input type="hidden" name="action" value="delete_batch" />
                        <input type="hidden" name="batch_id" value="<?php echo safe($batch['batch_id']); ?>" />
                        <input type="hidden" name="project_id" value="<?php echo safe($projectDetail['project_id']); ?>" />
                        <button class="btn btn-delete" type="submit">Delete</button>
                      </form>
                    </div>
                  </div>

                  <details class="card" style="border:1px dashed var(--border); padding:10px; border-radius:8px;">
                    <summary style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                      <span style="color:var(--primary); font-weight:600;">View sub-batches</span>
                      <span class="module-card__status <?php echo $hasSubBatches ? 'module-card__status--allowed' : 'module-card__status--blocked'; ?>"><?php echo $hasSubBatches ? 'Sub-batches available' : 'No sub-batches'; ?></span>
                    </summary>
                    <div style="margin-top:10px; display:grid; gap:10px;">
                      <?php if (!$subBatches): ?>
                        <div class="module-card module-card--no-image module-card--disabled" style="padding:12px;">
                          <div class="module-card__body" style="margin:0;">
                            <h4 style="margin:0;">No sub-batches</h4>
                            <p style="margin:4px 0 0; color:var(--muted);"><small>Create sub-batches for this batch.</small></p>
                          </div>
                          <div class="module-card__status module-card__status--blocked">No sub-batches</div>
                        </div>
                      <?php else: ?>
                        <div style="display:grid; gap:8px; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));">
                          <?php foreach ($subBatches as $sub): ?>
                            <div class="module-card module-card--no-image" style="padding:10px; display:grid; gap:4px;">
                              <div class="module-card__body" style="margin:0;">
                                <h4 style="margin:0;">Sub-batch: <?php echo safe($sub['sub_batch_name']); ?></h4>
                                <p style="margin:4px 0 0; color:var(--muted);"><small><?php echo safe($sub['description'] ?: 'No description'); ?></small></p>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>

                      <form method="POST" action="batches.php" class="form-row" style="gap:10px; align-items:flex-end; border-top:1px solid var(--border); padding-top:10px;">
                        <input type="hidden" name="action" value="create_sub_batches" />
                        <input type="hidden" name="batch_id" value="<?php echo safe($batch['batch_id']); ?>" />
                        <input type="hidden" name="project_id" value="<?php echo safe($projectDetail['project_id']); ?>" />
                        <div style="flex:1; min-width:200px;">
                          <label class="label" for="sub-base-<?php echo safe($batch['batch_id']); ?>">Sub-batch name</label>
                          <input id="sub-base-<?php echo safe($batch['batch_id']); ?>" name="sub_batch_base" type="text" placeholder="Sub-batch" required />
                        </div>
                        <div style="width:140px;">
                          <label class="label" for="sub-count-<?php echo safe($batch['batch_id']); ?>">How many?</label>
                          <input id="sub-count-<?php echo safe($batch['batch_id']); ?>" name="sub_batch_count" type="number" min="1" max="50" value="1" />
                        </div>
                        <div style="flex:1; min-width:200px;">
                          <label class="label" for="sub-desc-<?php echo safe($batch['batch_id']); ?>">Description (optional)</label>
                          <input id="sub-desc-<?php echo safe($batch['batch_id']); ?>" name="sub_batch_description" type="text" placeholder="Notes about this batch" />
                        </div>
                        <button class="btn btn-save" type="submit" style="white-space:nowrap;">Create sub-batches</button>
                      </form>
                    </div>
                  </details>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const messageModal = document.querySelector('.message-modal');
      const messageClose = document.querySelector('.message-close');

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
    });
  </script>
</body>
</html>