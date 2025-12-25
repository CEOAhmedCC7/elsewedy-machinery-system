<?php
require_once __DIR__ . '/helpers.php';
$user = require_login();

$role = $user['role'] ?? '';

$modules = [
    [
        'roles' => ['admin'],
        'href' => './role-access.php',
        'title' => 'Role Management',
        'description' => 'Manage roles and permissions for module access.',
    ],
    [
        'roles' => ['admin'],
        'href' => './users.php',
        'title' => 'User Management',
        'description' => 'CRUD users, assign roles, and control account status.',
    ],
];


$visibleModules = array_filter($modules, function ($module) use ($role) {
    return in_array($role, $module['roles'], true);
});
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
  <main class="home-grid">
    <section id="modules">
      <?php foreach ($visibleModules as $module): ?>
        <a class="card" href="<?php echo safe($module['href']); ?>">
          <div class="card-title"><?php echo safe($module['title']); ?></div>
          <p><?php echo safe($module['description']); ?></p>
        </a>
      <?php endforeach; ?>
    </section>
  </main>
  </body>
</html>