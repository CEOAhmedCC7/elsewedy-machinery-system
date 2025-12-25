<?php
require_once __DIR__ . '/helpers.php';
$user = require_login();

$role = $user['role'] ?? '';

$modules = fetch_table('modules', 'module_name');

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
        <span class="role"><?php echo strtoupper(safe($user['role'])); ?></span>
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
  <main style="padding:24px;">
    <section class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Modules</h3>
      <p class="muted">The table lists the modules available on the system home page.</p>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>Code</th><th>Module</th><th>Description</th><th>Link</th></tr>
          </thead>
          <tbody>
            <?php if ($modules): ?>
              <?php foreach ($modules as $module): ?>
                <tr>
                  <td><?php echo safe($module['module_code'] ?? ''); ?></td>
                  <td><?php echo safe($module['module_name']); ?></td>
                  <td><?php echo safe($module['description'] ?? ($module['module_name'] . ' module')); ?></td>
                  <td>
                    <?php if (!empty($module['href'])): ?>
                      <a href="<?php echo safe($module['href']); ?>" class="btn btn-neutral">Open</a>
                    <?php else: ?>
                      <span class="muted">No link</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="4">No modules configured.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
  </body>
</html>