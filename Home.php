<?php
require_once __DIR__ . '/helpers.php';

$user = require_login();

$modules = fetch_table('modules', 'module_name');
$modulePermissions = [];

if (!empty($user['user_id'])) {
    try {
        $pdo = get_pdo();
$stmt = $pdo->prepare(
            'SELECT rp.module_id, rp.can_create, rp.can_read, rp.can_update, rp.can_delete
             FROM role_module_permissions rp
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id'
        );
        $stmt->execute([':user_id' => $user['user_id']]);

        foreach ($stmt->fetchAll() as $row) {
            $moduleId = (int) $row['module_id'];
            $modulePermissions[$moduleId] = [
                'create' => filter_var($row['can_create'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'read' => filter_var($row['can_read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'update' => filter_var($row['can_update'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'delete' => filter_var($row['can_delete'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
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
        </div>
      </div>

        <div class="module-grid">
        <?php if ($modules): ?>
          <?php foreach ($modules as $module): ?>
            <?php
              $moduleId = isset($module['module_id']) ? (int) $module['module_id'] : null;
              $hasPermissionMap = !empty($modulePermissions);
              $permissionSet = $moduleId !== null && isset($modulePermissions[$moduleId]) ? $modulePermissions[$moduleId] : [];
              $permissionLetters = [];

              if (!empty($permissionSet['create'])) {
                  $permissionLetters[] = 'C';
              }
              if (!empty($permissionSet['read'])) {
                  $permissionLetters[] = 'R';
              }
              if (!empty($permissionSet['update'])) {
                  $permissionLetters[] = 'U';
              }
              if (!empty($permissionSet['delete'])) {
                  $permissionLetters[] = 'D';
              }

              $hasAnyPermission = !empty(array_filter($permissionSet));
              $canAccess = !$hasPermissionMap || $hasAnyPermission;

              $rawImage = $module['img'] ?? '';
              if (is_string($rawImage)) {
                  $trimmed = trim($rawImage, "{} \" ");
                  $moduleImage = $trimmed !== '' ? explode(',', $trimmed)[0] : '';
              } elseif (is_array($rawImage)) {
                  $moduleImage = reset($rawImage) ?: '';
              } else {
                  $moduleImage = '';
              }

              $sanitizedImage = trim($moduleImage);
              if ($sanitizedImage !== '') {
                  $hasProtocol = preg_match('/^https?:\/\//i', $sanitizedImage) === 1;
                  $hasLeadingSlash = strncmp($sanitizedImage, '/', 1) === 0 || strncmp($sanitizedImage, './', 2) === 0;
                  $imageSrc = $hasProtocol || $hasLeadingSlash
                      ? $sanitizedImage
                      : './assets/' . ltrim($sanitizedImage, '/');
              } else {
                  $imageSrc = './assets/Wallpaper.png';
              }
              $moduleLink = trim($module['href'] ?? $module['link'] ?? '');
              $description = $module['description'] ?? (($module['module_name'] ?? 'Module'));
              $cardClasses = 'module-card' . ($canAccess && $moduleLink !== '' ? ' module-card--link' : '') . (!$canAccess ? ' module-card--disabled' : '');

              $permissionSummary = $hasPermissionMap
                  ? (!empty($permissionLetters) ? implode(', ', $permissionLetters) : 'No access')
                  : 'C, R, U, D';

              $accessLevel = $canAccess ? $permissionSummary : 'No access';
              $statusClass = $canAccess ? 'module-card__status--allowed' : 'module-card__status--blocked';
            ?>

            <?php if ($canAccess && $moduleLink !== ''): ?>
              <a class="<?php echo safe($cardClasses); ?>" href="<?php echo safe($moduleLink); ?>">
                <div class="module-card__image">
                  <img src="<?php echo safe($imageSrc); ?>" alt="<?php echo safe($module['module_name'] ?? 'Module'); ?>" />
                  <div class="module-card__status <?php echo safe($statusClass); ?>"><?php echo safe($accessLevel); ?></div>
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
                  <div class="module-card__status <?php echo safe($statusClass); ?>"><?php echo safe($accessLevel); ?></div>
                </div>
                <div class="module-card__body">
                  <h4><?php echo safe($module['module_code']); ?></h4>
                  <p><small><em><?php echo safe($description); ?></em></small></p>
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