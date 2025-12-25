<?php
require_once __DIR__ . '/helpers.php';

$user = require_login();

$modules = fetch_table('modules', 'module_name');
$modulePermissions = [];

if (!empty($user['user_id'])) {
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'SELECT rp.module_id, rp.can_read
             FROM role_module_permissions rp
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id'
        );
        $stmt->execute([':user_id' => $user['user_id']]);

        foreach ($stmt->fetchAll() as $row) {
            $moduleId = (int) $row['module_id'];
            $modulePermissions[$moduleId] = [
                'read' => filter_var($row['can_read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ];
        }
    } catch (Throwable $e) {
        error_log('Failed to load role permissions: ' . $e->getMessage());
    }
}

if (!$modules) {
    $modules = [
        [
            'module_code' => 'ROLE',
            'module_name' => 'Role Management',
            'href' => './role-access.php',
            'description' => 'Manage roles and permissions for module access.',
        ],
        [
            'module_code' => 'USER',
            'module_name' => 'User Management',
            'href' => './users.php',
            'description' => 'CRUD users, assign roles, and control account status.',
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Home | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
    </div>
    <div class="title">Home</div>
    <div class="links">
      <div class="user-chip">
        <span class="name"><?php echo safe($user['username']); ?></span>
        <span class="role"><?php echo strtoupper(safe($user['role'] ?? '')); ?></span>
      </div>
      <a class="logout-icon" href="./logout.php" aria-label="Logout">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M15 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M10 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M16 8l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
    </div>
  </header>
  <main class="content">
    <section class="form-container">
      <div class="section-header">
        <div>
          <h3 style="margin:0; color:var(--secondary);">Modules</h3>
          <p class="muted">Select a module to get started. Modules you cannot access are disabled.</p>
        </div>
      </div>

      <div class="module-grid">
        <?php if ($modules): ?>
          <?php foreach ($modules as $module): ?>
            <?php
              $moduleId = isset($module['module_id']) ? (int) $module['module_id'] : null;
              $hasPermissionMap = !empty($modulePermissions);
              $canAccess = !$hasPermissionMap || ($moduleId !== null && !empty($modulePermissions[$moduleId]['read']) && $modulePermissions[$moduleId]['read']);
              $moduleLink = trim($module['href'] ?? '');
              $imageSrc = $module['img'] ?? './assets/Wallpaper.png';
              $description = $module['description'] ?? (($module['module_name'] ?? 'Module') . ' module');
              $cardClasses = 'module-card' . ($canAccess && $moduleLink !== '' ? ' module-card--link' : '') . (!$canAccess ? ' module-card--disabled' : '');
            ?>

            <?php if ($canAccess && $moduleLink !== ''): ?>
              <a class="<?php echo safe($cardClasses); ?>" href="<?php echo safe($moduleLink); ?>">
                <div class="module-card__image">
                  <img src="<?php echo safe($imageSrc); ?>" alt="<?php echo safe($module['module_name'] ?? 'Module'); ?>" />
                </div>
                <div class="module-card__body">
                  <h4><?php echo safe($module['module_name']); ?></h4>
                  <p><small><em><?php echo safe($description); ?></em></small></p>
                </div>
              </a>
            <?php else: ?>
              <div class="<?php echo safe($cardClasses); ?>" aria-disabled="true">
                <div class="module-card__image">
                  <img src="<?php echo safe($imageSrc); ?>" alt="<?php echo safe($module['module_name'] ?? 'Module'); ?>" />
                  <div class="module-card__status">No access</div>
                </div>
                <div class="module-card__body">
                  <h4><?php echo safe($module['module_name']); ?></h4>
                  <p><small><em><?php echo safe($description); ?></em></small></p>
                  <?php if ($moduleLink === ''): ?>
                    <span class="muted">No link configured</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No modules configured.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>
  </body>
</html>