<?php
require_once __DIR__ . '/helpers.php';
$users = fetch_table('users', 'user_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
      <div class="title">Users</div>
    </div>
    <div class="links">
      <a href="./home.php">Home</a>
      <a href="./login.php">Logout</a>
    </div>
  </header>
  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">User Management</h3>
      <div class="form-row">
        <div>
          <label class="label" for="user-id">User ID</label>
          <input id="user-id" type="text" placeholder="USR-001" />
        </div>
        <div>
          <label class="label" for="username">Username</label>
          <input id="username" type="text" placeholder="username" />
        </div>
        <div>
          <label class="label" for="password">Password</label>
          <input id="password" type="password" placeholder="********" />
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="role">Role</label>
          <select id="role">
            <option value="admin">Admin</option>
            <option value="project_manager">Project Manager</option>
            <option value="finance">Finance</option>
            <option value="viewer">Viewer</option>
          </select>
        </div>
        <div>
          <label class="label" for="status">Status</label>
          <select id="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="actions">
        <button class="btn btn-save" type="button">Save User</button>
        <button class="btn btn-neutral" type="button">View</button>
        <button class="btn btn-delete" type="button">Delete</button>
      </div>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>ID</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th></tr>
        </thead>
        <tbody>
          <?php if ($users): ?>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?php echo safe($user['user_id']); ?></td>
                <td><?php echo safe($user['username']); ?></td>
                <td><?php echo safe($user['role']); ?></td>
                <td><?php echo safe($user['status']); ?></td>
                <td><?php echo safe($user['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5">No users recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>