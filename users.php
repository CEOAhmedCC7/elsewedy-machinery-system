<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$error = '';
$success = '';

$submitted = [
    'user_id' => trim($_POST['user_id'] ?? ''),
    'username' => trim($_POST['username'] ?? ''),
    'password' => (string) ($_POST['password'] ?? ''),
    'role' => $_POST['role'] ?? 'viewer',
    'status' => $_POST['status'] ?? 'active',
];

$allowedRoles = ['admin', 'project_manager', 'finance', 'viewer'];
$allowedStatuses = ['active', 'inactive'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo = get_pdo();

    try {
        if ($action === 'create') {
            if ($submitted['username'] === '' || $submitted['password'] === '') {
                $error = 'Username and password are required to create a user.';
            } elseif (!in_array($submitted['role'], $allowedRoles, true) || !in_array($submitted['status'], $allowedStatuses, true)) {
                $error = 'Please select a valid role and status.';
            } elseif (strlen($submitted['username']) < 3) {
                $error = 'Username must be at least 3 characters long.';
            } elseif (strlen($submitted['password']) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } else {
                $userId = $submitted['user_id'] !== '' ? $submitted['user_id'] : 'usr_' . bin2hex(random_bytes(4));

                $exists = $pdo->prepare('SELECT 1 FROM users WHERE user_id = :id OR username = :username');
                $exists->execute([
                    ':id' => $userId,
                    ':username' => $submitted['username'],
                ]);

                if ($exists->fetchColumn()) {
                    $error = 'A user with this ID or username already exists.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users (user_id, username, password_hash, role, status) VALUES (:id, :username, :hash, :role, :status)');
                    $stmt->execute([
                        ':id' => $userId,
                        ':username' => $submitted['username'],
                        ':hash' => password_hash($submitted['password'], PASSWORD_DEFAULT),
                        ':role' => $submitted['role'],
                        ':status' => $submitted['status'],
                    ]);

                    $success = 'User created successfully.';
                    $submitted = [
                        'user_id' => '',
                        'username' => '',
                        'password' => '',
                        'role' => 'viewer',
                        'status' => 'active',
                    ];
                }
            }
        } elseif ($action === 'update') {
            if ($submitted['user_id'] === '') {
                $error = 'Please provide the User ID to update.';
            } elseif ($submitted['username'] === '') {
                $error = 'Username cannot be empty when updating a user.';
            } elseif (!in_array($submitted['role'], $allowedRoles, true) || !in_array($submitted['status'], $allowedStatuses, true)) {
                $error = 'Please select a valid role and status.';
            } else {
                $check = $pdo->prepare('SELECT 1 FROM users WHERE user_id = :id');
                $check->execute([':id' => $submitted['user_id']]);

                if (!$check->fetchColumn()) {
                    $error = 'User not found. Please check the User ID.';
                } else {
                    $params = [
                        ':id' => $submitted['user_id'],
                        ':username' => $submitted['username'],
                        ':role' => $submitted['role'],
                        ':status' => $submitted['status'],
                    ];

                    $passwordSql = '';
                    if ($submitted['password'] !== '') {
                        if (strlen($submitted['password']) < 8) {
                            throw new RuntimeException('Password must be at least 8 characters long.');
                        }
                        $passwordSql = ', password_hash = :hash';
                        $params[':hash'] = password_hash($submitted['password'], PASSWORD_DEFAULT);
                    }

                    $stmt = $pdo->prepare("UPDATE users SET username = :username, role = :role, status = :status{$passwordSql} WHERE user_id = :id");
                    $stmt->execute($params);

                    $success = 'User updated successfully.';
                }
            }
        } elseif ($action === 'view') {
            if ($submitted['user_id'] === '') {
                $error = 'Please enter a User ID to view details.';
            } else {
                $stmt = $pdo->prepare('SELECT user_id, username, role, status FROM users WHERE user_id = :id');
                $stmt->execute([':id' => $submitted['user_id']]);
                $found = $stmt->fetch();

                if ($found) {
                    $submitted['username'] = $found['username'];
                    $submitted['role'] = $found['role'];
                    $submitted['status'] = $found['status'];
                    $submitted['password'] = '';
                    $success = 'User loaded. You can update or delete this account.';
                } else {
                    $error = 'No user found with that ID.';
                }
            }
        } elseif ($action === 'delete') {
            if ($submitted['user_id'] === '') {
                $error = 'Please provide the User ID to delete.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :id');
                $stmt->execute([':id' => $submitted['user_id']]);

                if ($stmt->rowCount() === 0) {
                    $error = 'User not found or already deleted.';
                } else {
                    $success = 'User deleted successfully.';
                    $submitted = [
                        'user_id' => '',
                        'username' => '',
                        'password' => '',
                        'role' => 'viewer',
                        'status' => 'active',
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        $error = format_db_error($e, 'users table');
    }
}

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
      <a href="./users-list.php">Copy Users</a>
      <a href="./home.php">Home</a>
      <a href="./logout.php">Logout</a>
    </div>
  </header>
  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">User Management</h3>
      <?php if ($error): ?>
        <div class="alert" style="color: var(--secondary); margin-bottom:12px;">
          <?php echo safe($error); ?>
        </div>
      <?php elseif ($success): ?>
        <div class="alert" style="color: var(--primary); margin-bottom:12px;">
          <?php echo safe($success); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="users.php">
        <div class="form-row">
          <div>
            <label class="label" for="user-id">User ID</label>
            <input id="user-id" name="user_id" type="text" placeholder="USR-001" value="<?php echo safe($submitted['user_id']); ?>" />
            <p class="helper-text">Leave blank to auto-generate when saving.</p>
          </div>
          <div>
            <label class="label" for="username">Username</label>
            <input id="username" name="username" type="text" placeholder="username" value="<?php echo safe($submitted['username']); ?>" />
          </div>
          <div>
            <label class="label" for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="********" />
            <p class="helper-text">Enter a new password to reset it.</p>
          </div>
        </div>
        <div class="form-row">
          <div>
            <label class="label" for="role">Role</label>
            <select id="role" name="role">
              <?php foreach ($allowedRoles as $role): ?>
                <option value="<?php echo safe($role); ?>" <?php echo $submitted['role'] === $role ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $role)); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="status">Status</label>
            <select id="status" name="status">
              <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?php echo safe($status); ?>" <?php echo $submitted['status'] === $status ? 'selected' : ''; ?>><?php echo ucwords($status); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="actions">
          <button class="btn btn-save" type="submit" name="action" value="create">Save User</button>
          <button class="btn btn-neutral" type="submit" name="action" value="view">View</button>
          <button class="btn btn-neutral" type="submit" name="action" value="update">Update</button>
          <button class="btn btn-delete" type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
        </div>
      </form>
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