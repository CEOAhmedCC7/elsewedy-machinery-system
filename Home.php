<?php
require_once __DIR__ . '/helpers.php';
$user = require_login();

$role = $user['role'] ?? '';

$modules = [
    [
        'roles' => ['admin'],
        'href' => './users.php',
        'icon' => '👥',
        'title' => 'User Management',
        'description' => 'Admin-only user provisioning',
    ],
    [
        'roles' => ['admin', 'finance', 'project_manager'],
        'href' => './projects.php',
        'icon' => '📁',
        'title' => 'Projects',
        'description' => 'Create and track projects',
    ],
    [
        'roles' => ['admin', 'finance', 'project_manager', 'logistics', 'procurement'],
        'href' => './budgets.php',
        'icon' => '💰',
        'title' => 'Budgets',
        'description' => 'Project / sub-batch budgets',
    ],
    [
        'roles' => ['admin', 'finance'],
        'href' => './payments.php',
        'icon' => '💳',
        'title' => 'Payments',
        'description' => 'Outgoing payment tracking',
    ],
    [
        'roles' => ['admin', 'finance', 'project_manager', 'customer_support'],
        'href' => './invoices.php',
        'icon' => '🧾',
        'title' => 'Invoices',
        'description' => 'Project-linked invoices',
    ],
    [
        'roles' => ['admin', 'project_manager', 'customer_support'],
        'href' => './customers.php',
        'icon' => '🤝',
        'title' => 'Customers',
        'description' => 'Customer records',
    ],
    [
        'roles' => ['admin', 'procurement'],
        'href' => './suppliers.php',
        'icon' => '🏭',
        'title' => 'Suppliers',
        'description' => 'Procurement partners',
    ],
    [
        'roles' => ['admin', 'finance', 'project_manager'],
        'href' => './budget-requests.php',
        'icon' => '📈',
        'title' => 'Budget Update Requests',
        'description' => 'Track scope change approvals',
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
      <div class="title">Home</div>
    </div>
    <div class="links">
      <span style="color:var(--secondary);">Signed in as <?php echo safe($user['username']); ?> (<?php echo safe($user['role']); ?>)</span>
      <a href="./logout.php">Logout</a>
    </div>
  </header>
  <main class="home-grid">
    <section id="modules">
      <?php foreach ($visibleModules as $module): ?>
        <a class="card" href="<?php echo safe($module['href']); ?>">
          <div class="card-icon"><?php echo safe($module['icon']); ?></div>
          <div class="card-title"><?php echo safe($module['title']); ?></div>
          <p><?php echo safe($module['description']); ?></p>
        </a>
      <?php endforeach; ?>
    </section>
  </main>
  </body>
</html>