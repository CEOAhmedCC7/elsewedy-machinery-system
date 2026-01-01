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
            $rawNames = $_POST['sub_batch_names'] ?? [];
            $names = [];

            foreach ($rawNames as $name) {
                $trimmed = trim((string) $name);
                if ($trimmed !== '') {
                    if (strlen($trimmed) > 255) {
                        throw new RuntimeException('Sub-batch name must be 255 characters or fewer.');
                    }
                    $names[] = $trimmed;
                }
            }

            if ($batchId <= 0 || $projectId <= 0) {
                throw new RuntimeException('Choose a batch before adding sub-batches.');
            }
            if (!$names && $baseName === '') {
                throw new RuntimeException('Provide at least one sub-batch name.');
            }
            if ($names) {
                $count = count($names);
            }
            if ($count > 50) {
                throw new RuntimeException('Please create 50 or fewer sub-batches at once.');
            }
            if (!$names && strlen($baseName) > 255) {
                throw new RuntimeException('Sub-batch name must be 255 characters or fewer.');
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
                $stmt = $pdo->prepare('INSERT INTO sub_batch_details (batch_id, sub_batch_name, description) VALUES (:batch_id, :name, NULLIF(:description, \'\'))');

                if ($names) {
                    foreach ($names as $name) {
                        $stmt->execute([
                            ':batch_id' => $batchId,
                            ':name' => $name,
                            ':description' => $description,
                        ]);
                    }
                } else {
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
                }

                $pdo->commit();
                $success = $count > 1 ? 'Sub-batches created successfully.' : 'Sub-batch created successfully.';
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
                $subStmt = $pdo->prepare('SELECT sub_batch_detail_id, sub_batch_name, COALESCE(description, \'\') AS description FROM sub_batch_details WHERE batch_id = :batch ORDER BY sub_batch_detail_id');
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

      .create-sub-modal .message-dialog {
        max-width: 720px;
      }

      .name-inputs {
        display: grid;
        gap: 8px;
        max-height: 320px;
        overflow-y: auto;
        padding-right: 4px;
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
              <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <h2 style="margin:0; color:var(--secondary);">Batch: <?php echo safe($selectedBatch['batch_name'] ?? 'Batch'); ?></h2>
              </div>
              <?php if ($selectedBatch): ?>
                <div style="color:var(--muted); font-size:14px;">Project: <?php echo safe($selectedBatch['project_name']); ?> | PO: <?php echo safe($selectedBatch['po_number'] ?: '—'); ?> | Cost center: <?php echo safe($selectedBatch['cost_center_no'] ?: '—'); ?></div>
              <?php endif; ?>
            </div>
            <?php if ($selectedBatch): ?>
              <div style="display:flex; align-items:center; gap:8px;">
                <button class="btn btn-save" type="button" data-open-sub-batch-modal style="white-space:nowrap;">Create sub-batches</button>
              </div>
            <?php endif; ?>
          </div>

          <?php if (!$selectedBatch): ?>
            <div class="empty-state">Batch not found.</div>
          <?php else: ?>
            <div class="module-card module-card--no-image" style="padding:16px; display:grid; gap:12px;">
              <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                <h3 style="margin:0;">Sub-batches</h3>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                  <span class="module-card__status <?php echo $subBatches ? 'module-card__status--allowed' : 'module-card__status--blocked'; ?>"><?php echo $subBatches ? sprintf('%d sub-batches', count($subBatches)) : 'No sub-batches'; ?></span>
                  <button class="btn btn-save" type="button" data-open-sub-batch-modal style="white-space:nowrap;">Create sub-batches</button>
                </div>
              </div>

              <?php if (!$subBatches): ?>
                <div class="module-card module-card--no-image module-card--disabled" style="padding:12px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                  <div class="module-card__body" style="margin:0;">
                    <h4 style="margin:0;">No sub-batches</h4>
                    <p style="margin:4px 0 0; color:var(--muted);"><small>Create the first sub-batches for this batch.</small></p>
                  </div>
                  <button class="btn btn-save" type="button" data-open-sub-batch-modal>Create sub-batches</button>
                </div>
              <?php else: ?>
                <div class="batch-grid">
                  <?php foreach ($subBatches as $sub): ?>
                    <div class="module-card module-card--no-image batches-card" style="display:flex; flex-direction:column; gap:8px; position:relative; padding:12px;">
                      <div class="module-card__body" style="margin:0; display:grid; gap:6px; align-content:start;">
                        <h4 style="margin:0; color:#fff;"><?php echo safe($sub['sub_batch_name']); ?></h4>
                        <p style="margin:0; color:#e6e6e6;">
                          <?php echo safe($sub['description'] ?: 'No description provided'); ?>
                        </p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </main>

    <?php if ($selectedBatch): ?>
      <div class="message-modal create-sub-modal" id="create-sub-batches-modal" role="dialog" aria-modal="true" aria-label="Create sub-batches">
        <div class="message-dialog">
          <div class="message-dialog__header">
            <span class="message-title">Create sub-batches for <?php echo safe($selectedBatch['batch_name']); ?></span>
            <button class="message-close" type="button" aria-label="Close create sub-batches" data-close-sub-batch-modal>&times;</button>
          </div>
          <form method="POST" action="sub-batches.php?batch_id=<?php echo safe($selectedBatch['batch_id']); ?>" style="display:grid; gap:12px;">
            <input type="hidden" name="action" value="create_sub_batches" />
            <input type="hidden" name="batch_id" value="<?php echo safe($selectedBatch['batch_id']); ?>" />
            <input type="hidden" name="project_id" value="<?php echo safe($selectedBatch['project_id']); ?>" />

            <p style="margin:0; color:var(--muted);">Choose how many sub-batches to create, then name each sub-batch individually. Add an optional description that applies to all new sub-batches.</p>

            <div class="form-row" style="gap:10px; align-items:flex-end;">
              <div style="flex:1; min-width:180px;">
                <label class="label" for="sub-batch-count-input">How many sub-batches?</label>
                <input id="sub-batch-count-input" name="sub_batch_count" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Enter a number" value="1" />
              </div>
              <div style="flex:1; min-width:200px;">
                <label class="label" for="sub-batch-base-input">Base name (optional)</label>
                <input id="sub-batch-base-input" name="sub_batch_base" type="text" placeholder="Sub-batch" />
              </div>
              <div style="flex:1; min-width:200px;">
                <label class="label" for="sub-batch-description-input">Description (optional)</label>
                <input id="sub-batch-description-input" name="sub_batch_description" type="text" placeholder="Notes about these sub-batches" />
              </div>
            </div>

            <div class="name-inputs" data-sub-batch-names aria-label="Sub-batch names"></div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
              <button class="btn" type="button" data-close-sub-batch-modal>Cancel</button>
              <button class="btn btn-save" type="submit">Confirm and create</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const messageModal = document.querySelector('.message-modal:not(.create-sub-modal)');
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

        const createModal = document.getElementById('create-sub-batches-modal');
        const openModalButtons = document.querySelectorAll('[data-open-sub-batch-modal]');
        const closeModalButtons = createModal ? createModal.querySelectorAll('[data-close-sub-batch-modal]') : [];
        const countInput = document.getElementById('sub-batch-count-input');
        const baseInput = document.getElementById('sub-batch-base-input');
        const namesContainer = createModal ? createModal.querySelector('[data-sub-batch-names]') : null;

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
            label.textContent = `Sub-batch ${i} name`;
            label.setAttribute('for', `sub-batch-name-${i}`);

            const input = document.createElement('input');
            input.id = `sub-batch-name-${i}`;
            input.name = 'sub_batch_names[]';
            input.type = 'text';
            input.placeholder = `Sub-batch ${i}`;
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
      });
    </script>
  </body>
</html>
