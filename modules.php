<?php
require_once __DIR__ . '/helpers.php';

function normalize_module_image_path(string $path): string
{
    $path = trim($path);

    if ($path === '') {
        return '';
    }

    if (strpos($path, '://') !== false || strpos($path, 'assets/') === 0 || strpos($path, '/') === 0) {
        return $path;
    }

    return 'assets/uploads/modules/' . $path;
}

$user = require_login();
$moduleCode = resolve_module_code('MODULES');

$pdo = null;
$error = '';
$success = '';

$uploadDir = __DIR__ . '/assets/uploads/modules';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$formData = [
    'module_id' => '',
    'module_code' => '',
    'module_name' => '',
    'img' => '',
    'current_img' => '',
    'link' => '',
];
try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    $error = format_db_error($e);
}

$requestedModuleId = isset($_GET['module_id']) ? (int) $_GET['module_id'] : null;

if ($pdo && $requestedModuleId) {
    $stmt = $pdo->prepare("SELECT module_id, module_code, module_name, COALESCE(img, '') AS img, COALESCE(link, '') AS link FROM modules WHERE module_id = :id");
    $stmt->execute([':id' => $requestedModuleId]);
    $found = $stmt->fetch();

    if ($found) {
       $formData = [
            'module_id' => (string) $found['module_id'],
            'module_code' => (string) $found['module_code'],
            'module_name' => (string) $found['module_name'],
            'img' => normalize_module_image_path((string) $found['img']),
            'current_img' => normalize_module_image_path((string) $found['img']),
            'link' => (string) $found['link'],
        ];
    }
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $permissionError = enforce_action_permission(
        $user,
        $moduleCode,
        $action,
        [
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
        ]
    );

    $formData = [
        'module_id' => trim((string) ($_POST['module_id'] ?? '')),
        'module_code' => trim((string) ($_POST['module_code'] ?? '')),
        'module_name' => trim((string) ($_POST['module_name'] ?? '')),
        'img' => '',
        'current_img' => normalize_module_image_path(trim((string) ($_POST['current_img'] ?? ''))),
        'link' => trim((string) ($_POST['link'] ?? '')),
    ];

    $moduleId = $formData['module_id'] !== '' ? (int) $formData['module_id'] : null;

    try {
        if ($permissionError) {
            $error = $permissionError;
        } elseif (in_array($action, ['create', 'update'], true)) {
            $upload = $_FILES['img_file'] ?? null;
            $formData['img'] = $formData['current_img'];

            if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($upload['error'] === UPLOAD_ERR_OK) {
                    $originalName = basename((string) $upload['name']);
                    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

                    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
                        throw new RuntimeException('Please upload a valid image file (jpg, jpeg, png, gif, webp, bmp).');
                    }

                    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) pathinfo($originalName, PATHINFO_FILENAME));
                    $baseName = $baseName !== '' ? $baseName : 'module_image';
                    $fileName = $baseName . '.' . $extension;
                    $targetPath = $uploadDir . '/' . $fileName;

                    $counter = 1;
                    while (file_exists($targetPath)) {
                        $fileName = $baseName . '_' . $counter . '.' . $extension;
                        $targetPath = $uploadDir . '/' . $fileName;
                        $counter++;
                    }

                    if (!move_uploaded_file($upload['tmp_name'], $targetPath)) {
                        throw new RuntimeException('Unable to save the uploaded image.');
                    }

                    $formData['img'] = 'assets/uploads/modules/' . $fileName;
                } else {
                    throw new RuntimeException('Error uploading image.');
                }
            } elseif ($action === 'create') {
                $formData['img'] = '';
            }
        }

         if ($error) {
            // Skip action processing when permission check fails.
        } elseif ($action === 'create') {
            if ($formData['module_code'] === '' || $formData['module_name'] === '') {
                throw new RuntimeException('Module code and module name are required.');
            }

            if (strlen($formData['module_code']) > 100) {
                throw new RuntimeException('Module code must be 100 characters or fewer.');
            }

            if (strlen($formData['module_name']) > 255) {
                throw new RuntimeException('Module name must be 255 characters or fewer.');
            }

            $dupeCheck = $pdo->prepare('SELECT 1 FROM modules WHERE module_code = :code');
            $dupeCheck->execute([':code' => $formData['module_code']]);

            if ($dupeCheck->fetchColumn()) {
                throw new RuntimeException('A module with this code already exists.');
            }

            $insert = $pdo->prepare("INSERT INTO modules (module_code, module_name, img, link) VALUES (:code, :name, NULLIF(:img, ''), NULLIF(:link, ''))");
            $insert->execute([
                ':code' => $formData['module_code'],
                ':name' => $formData['module_name'],
                ':img' => $formData['img'],
                ':link' => $formData['link'],
            ]);

            $success = 'Module created successfully.';
            $formData = [
               'module_id' => '',
                'module_code' => '',
                'module_name' => '',
                'img' => '',
                'current_img' => '',
                'link' => '',
            ];
        } elseif ($action === 'update') {
            if (!$moduleId) {
                throw new RuntimeException('Select a module to update.');
            }

            if ($formData['module_code'] === '' || $formData['module_name'] === '') {
                throw new RuntimeException('Module code and module name are required.');
            }

            $exists = $pdo->prepare('SELECT 1 FROM modules WHERE module_id = :id');
            $exists->execute([':id' => $moduleId]);

            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Module not found.');
            }

            $dupeCheck = $pdo->prepare('SELECT 1 FROM modules WHERE module_code = :code AND module_id <> :id');
            $dupeCheck->execute([
                ':code' => $formData['module_code'],
                ':id' => $moduleId,
            ]);

            if ($dupeCheck->fetchColumn()) {
                throw new RuntimeException('Another module already uses this code.');
            }

            $update = $pdo->prepare("UPDATE modules SET module_code = :code, module_name = :name, img = NULLIF(:img, ''), link = NULLIF(:link, '') WHERE module_id = :id");
            if ($formData['img'] === '') {
                $formData['img'] = $formData['current_img'];
            }

            $update = $pdo->prepare("UPDATE modules SET module_code = :code, module_name = :name, img = NULLIF(:img, ''), link = NULLIF(:link, '') WHERE module_id = :id");
            $update->execute([
                ':code' => $formData['module_code'],
                ':name' => $formData['module_name'],
                ':img' => $formData['img'],
                ':link' => $formData['link'],
                ':id' => $moduleId,
            ]);

            $success = 'Module updated successfully.';
        } elseif ($action === 'delete') {
            if (!$moduleId) {
                throw new RuntimeException('Select a module to delete.');
            }

           $pdo->prepare('DELETE FROM modules WHERE module_id = :id')->execute([':id' => $moduleId]);
            $success = 'Module deleted successfully.';

            if ((int) ($formData['module_id'] ?? 0) === $moduleId) {
                $formData = [
                    'module_id' => '',
                    'module_code' => '',
                    'module_name' => '',
                    'img' => '',
                    'current_img' => '',
                    'link' => '',
                ];
            }
        }
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? format_db_error($e, 'modules table') : $e->getMessage();
    }
}

$modules = $pdo ? fetch_table('modules', 'module_name') : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Modules | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <?php if ($error !== '' || $success !== ''): ?>
    <div class="message-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Modules notification">
      <div class="message-dialog <?php echo $error ? 'is-error' : 'is-success'; ?>">
        <div class="message-dialog__header">
          <span class="message-title"><?php echo $error ? 'Action needed' : 'Success'; ?></span>
          <button class="message-close" type="button" aria-label="Close message">&times;</button>
        </div>
        <p class="message-body"><?php echo safe($error !== '' ? $error : $success); ?></p>
      </div>
    </div>
  <?php endif; ?>
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
    </div>
     <div class="title">Module Management</div>
    <div class="links">
      <div class="user-chip">
        <span class="name"><?php echo safe($user['username']); ?></span>
        <span class="role"><?php echo strtoupper(safe($user['role'] ?? '')); ?></span>
      </div>
      <a href="./home.php">Home</a>
      <a class="logout-icon" href="./logout.php" aria-label="Logout">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M15 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M10 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
    </div>
  </header>
  <main class="content">
    <section class="form-container">
      <div class="section-header">
        <div>
          <h3 style="margin:0; color:var(--secondary);">Create or Update Modules</h3>
          <br>
          <!-- <p class="muted" style="margin-top:4px;">Manage the modules list that powers the home dashboard and permissions grid.</p> -->
        </div>
        <div class="status-pill neutral">CRUD Ready</div>
      </div>

      <form method="post" class="stacked" autocomplete="off" enctype="multipart/form-data">
        <input type="hidden" name="module_id" value="<?php echo safe($formData['module_id']); ?>" />
        <input type="hidden" name="current_img" value="<?php echo safe($formData['current_img']); ?>" />
        <div class="form-row">
          <div>
            <label class="label" for="module_code">Module Name</label>
            <input type="text" id="module_code" name="module_code" maxlength="100" value="<?php echo safe($formData['module_code']); ?>" placeholder="e.g., SALES" required />
          </div>
          <div>
            <label class="label" for="module_name">Module Description</label>
            <input type="text" id="module_name" name="module_name" maxlength="255" value="<?php echo safe($formData['module_name']); ?>" placeholder="Human-friendly module name" required />
          </div>
          </div>
        <div class="form-row">
          <div>
            <label class="label" for="img_file">Module Image</label>
            <input type="file" id="img_file" name="img_file" accept="image/*" />
            <?php if ($formData['current_img']): ?>
              <p class="muted" style="margin:6px 0 0;">Current image: <?php echo safe(basename($formData['current_img'])); ?></p>
            <?php endif; ?>
          </div>
          <div>
            <label class="label" for="link">Page Link</label>
            <input type="text" id="link" name="link" maxlength="255" value="<?php echo safe($formData['link']); ?>" placeholder="page.php" />
          </div>
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <button type="submit" name="action" value="create" class="btn btn-save">Create Module</button>
          <button type="submit" name="action" value="update" class="btn btn-update">Update Module</button>
          <?php if ($formData['module_id'] !== ''): ?>
            <button type="submit" name="action" value="delete" class="btn btn-delete" onclick="return confirm('Delete this module?');">Delete Module</button>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <section class="form-container" style="margin-top:20px;">
      <div class="section-header">
        <div>
          <h3 style="margin:0; color:var(--secondary);">All Modules</h3>
          <br>
          <!-- <p class="muted" style="margin-top:4px;">Click "Load" to fill the form for quick updates.</p> -->
        </div>
      </div>

      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Module Name</th>
              <th>Description</th>
              <th>Image</th>
              <th>Link</th>
              <th style="width:200px;">Actions</th>
            </tr>
          </thead>
           <tbody>
            <?php if ($modules): ?>
              <?php foreach ($modules as $module): ?>
                <?php $imagePath = normalize_module_image_path($module['img'] ?? ''); ?>
                <tr>
                  <td><?php echo safe((string) $module['module_id']); ?></td>
                  <td><?php echo safe($module['module_code']); ?></td>
                  <td><?php echo safe($module['module_name']); ?></td>
                  <td>
                    <?php if ($imagePath !== ''): ?>
                      <div class="module-thumb">
                        <img src="<?php echo safe($imagePath); ?>" alt="<?php echo safe($module['module_code']); ?> image" />
                      </div>
                    <?php else: ?>
                      <div class="module-thumb" aria-hidden="true">--</div>
                    <?php endif; ?>
                  </td>
                  <td><?php echo safe($module['link'] ?? ''); ?></td>
                  <td>
                    <div class="table-actions">
                      <a class="btn btn-update" href="modules.php?module_id=<?php echo urlencode((string) $module['module_id']); ?>">Load</a>
                      <form method="post" onsubmit="return confirm('Delete this module?');">
                        <input type="hidden" name="module_id" value="<?php echo safe((string) $module['module_id']); ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <button type="submit" class="btn btn-delete">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6">No modules found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
       </div>
    </section>
  </main>
   <script>
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
  </script>
</body>
</html>