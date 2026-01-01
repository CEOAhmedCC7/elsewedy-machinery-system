<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$moduleCode = resolve_module_code('BATCHES');

$error = '';
$success = '';

try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    $pdo = null;
    $error = format_db_error($e, 'database connection');
}

$batchId = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : null;

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $permissionError = enforce_action_permission(
        $currentUser,
        $moduleCode ?? 'BATCHES',
        $action,
        [
            'create_sub_batches' => 'create',
        ]
    );

    try {
        if ($permissionError) {
            $error = $permissionError;
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
        }
    } catch (Throwable $e) {
        $error = $error ?: format_db_error($e, 'sub-batches');
    }
}

$selectedBatch = null;
$subBatches = [];
$batches = [];

if ($pdo) {
    try {
        if ($batchId) {
            $batchStmt = $pdo->prepare('SELECT b.batch_id, b.batch_name, p.project_id, p.project_name, p.po_number, p.cost_center_no
                FROM batches b
                JOIN projects p ON p.project_id = b.project_id
                WHERE b.batch_id = :id');
            $batchStmt->execute([':id' => $batchId]);
            $selectedBatch = $batchStmt->fetch();

            if ($selectedBatch) {
                $subStmt = $pdo->prepare('SELECT sub_batch_detail_id, sub_batch_name, COALESCE(description, "") AS description FROM sub_batch_details WHERE batch_id = :batch ORDER BY sub_batch_detail_id');
                $subStmt->execute([':batch' => $batchId]);
                $subBatches = $subStmt->fetchAll();
            }
        } else {
            $batchList = $pdo->query('SELECT b.batch_id, b.batch_name, p.project_name, p.project_id, COALESCE(sb.sub_count, 0) AS sub_count
                FROM batches b
                JOIN projects p ON p.project_id = b.project_id
                LEFT JOIN (
                    SELECT batch_id, COUNT(*) AS sub_count
                    FROM sub_batch_details
                    GROUP BY batch_id
                ) sb ON sb.batch_id = b.batch_id
                ORDER BY b.batch_id DESC
                LIMIT 50');
            $batches = $batchList->fetchAll();
        }
    } catch (Throwable $e) {
        $error = $error ?: format_db_error($e, 'sub-batches');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sub-batches | Elsewedy Machinery</title>
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

      .batches-card h4 {
        padding-top: 20px;
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
        border-top-left-radius: 8px;
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        border-bottom-left-radius: 0;
      }

      .batch-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      }
    </style>
  </head>
  <body class="page">
    <header class="navbar">
      <div class="header">
        <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
      </div>
      <div class="title">Sub-batches</div>
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
      <div class="message-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Sub-batches notification">
        <div class="message-dialog <?php echo $error ? 'is-error' : 'is-success'; ?>">
          <div class="message-dialog__header">
            <span class="message-title"><?php echo $error ? 'Action needed' : 'Success'; ?></span>
            <button class="message-close" type="button" aria-label="Close message">&times;</button>
          </div>
          <p class="message-body"><?php echo safe($error !== '' ? $error : $success); ?></p>
        </div>
      </div>
    <?php endif; ?>

    <main style="padding:24px; display:grid; gap:20px;">
      <div class="form-container" style="display:grid; gap:16px;">
        <?php if (!$batchId): ?>
          <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div>
              <h3 style="margin:0; color:var(--secondary);">Select a batch to manage sub-batches</h3>
              <p style="margin:6px 0 0; color:var(--muted);">Create, update, or review sub-batches per batch.</p>
            </div>
          </div>

          <?php if (!$batches): ?>
            <div class="empty-state">No batches found.</div>
          <?php endif; ?>

          <div class="module-grid">
            <?php foreach ($batches as $batch): ?>
              <?php
                $hasSubBatches = ((int) ($batch['sub_count'] ?? 0)) > 0;
                $statusClass = $hasSubBatches ? 'module-card__status--allowed' : 'module-card__status--blocked';
                $statusLabel = $hasSubBatches ? sprintf('%d sub-batches', (int) $batch['sub_count']) : 'No sub-batches';
              ?>
              <a class="module-card module-card--link module-card--no-image batches-card" style="display:flex; flex-direction:column; justify-content:space-between;" href="sub-batches.php?batch_id=<?php echo safe($batch['batch_id']); ?>">
                <div class="module-card__body" style="flex:1;">
                  <h4 style="margin-bottom:6px;">Batch: <?php echo safe($batch['batch_name']); ?></h4>
                  <p style="margin:0;"><small>Project: <?php echo safe($batch['project_name']); ?></small></p>
                </div>
                <div class="batches-card__footer">
                  <span class="module-card__status <?php echo safe($statusClass); ?>" aria-label="Sub-batch availability"><?php echo safe($statusLabel); ?></span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div style="display:grid; gap:6px;">
              <a class="btn" style="width:fit-content; text-decoration:none;" href="sub-batches.php">← All batches</a>
              <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <h2 style="margin:0; color:var(--secondary);">Batch: <?php echo safe($selectedBatch['batch_name'] ?? 'Batch'); ?></h2>
                <?php if ($selectedBatch): ?>
                  <span style="color:var(--muted);">Project: <?php echo safe($selectedBatch['project_name']); ?> | PO: <?php echo safe($selectedBatch['po_number'] ?: '—'); ?> | Cost center: <?php echo safe($selectedBatch['cost_center_no'] ?: '—'); ?></span>
                <?php endif; ?>
              </div>
            </div>
            <a class="btn" style="text-decoration:none; white-space:nowrap;" href="batches.php?project_id=<?php echo safe($selectedBatch['project_id'] ?? 0); ?>">Back to project</a>
          </div>

          <?php if (!$selectedBatch): ?>
            <div class="empty-state">Batch not found.</div>
          <?php else: ?>
            <div class="module-card module-card--no-image" style="padding:16px; display:grid; gap:12px;">
              <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                <h3 style="margin:0;">Sub-batches</h3>
                <span class="module-card__status <?php echo $subBatches ? 'module-card__status--allowed' : 'module-card__status--blocked'; ?>"><?php echo $subBatches ? sprintf('%d sub-batches', count($subBatches)) : 'No sub-batches'; ?></span>
              </div>

              <?php if (!$subBatches): ?>
                <div class="module-card module-card--no-image module-card--disabled" style="padding:12px;">
                  <div class="module-card__body" style="margin:0;">
                    <h4 style="margin:0;">No sub-batches</h4>
                    <p style="margin:4px 0 0; color:var(--muted);"><small>Create the first sub-batches for this batch.</small></p>
                  </div>
                  <div class="module-card__status module-card__status--blocked">No sub-batches</div>
                </div>
              <?php else: ?>
                <div class="batch-grid">
                  <?php foreach ($subBatches as $sub): ?>
                    <div class="module-card module-card--no-image" style="padding:10px; display:grid; gap:6px;">
                      <div class="module-card__body" style="margin:0;">
                        <h4 style="margin:0;">Sub-batch: <?php echo safe($sub['sub_batch_name']); ?></h4>
                        <p style="margin:4px 0 0; color:var(--muted);"><small><?php echo safe($sub['description'] ?: 'No description'); ?></small></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <form method="POST" action="sub-batches.php?batch_id=<?php echo safe($selectedBatch['batch_id']); ?>" class="form-row" style="gap:10px; align-items:flex-end; border-top:1px solid var(--border); padding-top:10px;">
                <input type="hidden" name="action" value="create_sub_batches" />
                <input type="hidden" name="batch_id" value="<?php echo safe($selectedBatch['batch_id']); ?>" />
                <input type="hidden" name="project_id" value="<?php echo safe($selectedBatch['project_id']); ?>" />
                <div style="flex:1; min-width:200px;">
                  <label class="label" for="sub-base">Sub-batch name</label>
                  <input id="sub-base" name="sub_batch_base" type="text" placeholder="Sub-batch" required />
                </div>
                <div style="width:140px;">
                  <label class="label" for="sub-count">How many?</label>
                  <input id="sub-count" name="sub_batch_count" type="number" min="1" max="50" value="1" />
                </div>
                <div style="flex:1; min-width:200px;">
                  <label class="label" for="sub-desc">Description (optional)</label>
                  <input id="sub-desc" name="sub_batch_description" type="text" placeholder="Notes about this sub-batch" />
                </div>
                <button class="btn btn-save" type="submit" style="white-space:nowrap;">Create sub-batches</button>
              </form>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </main>

    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const messageModal = document.querySelector('.message-modal');
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
      });
    </script>
  </body>
</html>