<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$error = '';
$success = '';

$userIdPosted = trim($_POST['user_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $isActive = filter_var($submitted['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($submitted['full_name'] === '') {
                $error = 'Please enter a full name for the new account.';
            } elseif ($submitted['email'] === '') {
                $error = 'Please enter an email address.';
            } elseif (!filter_var($submitted['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid email address.';
            } elseif ($submitted['password'] === '') {
                $error = 'Password is required to create a user.';
            } elseif (!in_array((string) $submitted['role_id'], array_map('strval', $allowedRoleIds), true)) {
                $error = 'Please select a valid role.';
            } elseif ($isActive === null) {
                $error = 'Please choose whether the user is active or inactive.';
            } elseif (strlen($submitted['password']) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } else {
                $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :email');
                $exists->execute([
                    ':email' => $submitted['email'],
                ]);

                if ($exists->fetchColumn()) {
                    $error = 'A user with this email already exists.';
                } else {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, is_active) VALUES (:name, :email, :hash, :active)');
                    $stmt->execute([
                        ':name' => $submitted['full_name'],
                        ':email' => $submitted['email'],
                        ':hash' => $submitted['password'],
                        ':active' => $isActive,
                    ]);

                    $newUserId = (int) $pdo->lastInsertId('users_user_id_seq');

                    $roleStmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
                    $roleStmt->execute([
                        ':user_id' => $newUserId,
                        ':role_id' => $submitted['role_id'],
                    ]);

                    $pdo->commit();

                    $success = 'User created successfully.';
                    $submitted = [
                        'user_id' => '',
                        'full_name' => '',
                        'email' => '',
                        'password' => '',
                        'role_id' => '',
                        'is_active' => 'true',
                    ];
                }
            }
        } elseif ($action === 'update') {
            $isActive = filter_var($submitted['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($submitted['user_id'] === '') {
                $error = 'Please provide the User ID to update.';
            } elseif ($submitted['full_name'] === '') {
                $error = 'Full name cannot be empty when updating a user.';
            } elseif ($submitted['email'] === '') {
                $error = 'Email cannot be empty when updating a user.';
            } elseif (!filter_var($submitted['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid email address.';
            } elseif (!in_array((string) $submitted['role_id'], array_map('strval', $allowedRoleIds), true)) {
                $error = 'Please select a valid role.';
            } elseif ($isActive === null) {
                $error = 'Please choose whether the user is active or inactive.';
            } else {
                $check = $pdo->prepare('SELECT 1 FROM users WHERE user_id = :id');
                $check->execute([':id' => $submitted['user_id']]);

                if (!$check->fetchColumn()) {
                    $error = 'User not found. Please check the User ID.';
                } else {
                    $pdo->beginTransaction();

                    $params = [
                        ':id' => $submitted['user_id'],
                        ':full_name' => $submitted['full_name'],
                        ':email' => $submitted['email'],
                        ':is_active' => $isActive,
                    ];

                    $passwordSql = '';
                    if ($submitted['password'] !== '') {
                        if (strlen($submitted['password']) < 8) {
                            throw new RuntimeException('Password must be at least 8 characters long.');
                        }
                        $passwordSql = ', password_hash = :hash';
                        $params[':hash'] = $submitted['password'];
                    }

                    $stmt = $pdo->prepare("UPDATE users SET full_name = :full_name, email = :email, is_active = :is_active{$passwordSql} WHERE user_id = :id");
                    $stmt->execute($params);

                    $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute([':user_id' => $submitted['user_id']]);
                    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute([
                        ':user_id' => $submitted['user_id'],
                        ':role_id' => $submitted['role_id'],
                    ]);

                    $pdo->commit();

                    $success = 'User updated successfully.';
                }
            }
        } elseif ($action === 'view') {
            if ($submitted['user_id'] === '') {
                $error = 'Please enter a User ID to view details.';
            } else {
                $stmt = $pdo->prepare('SELECT u.user_id, u.full_name, u.email, u.is_active, r.role_id FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.user_id LEFT JOIN roles r ON r.role_id = ur.role_id WHERE u.user_id = :id');
                $stmt->execute([':id' => $submitted['user_id']]);
                $found = $stmt->fetch();

                if ($found) {
                    $submitted['full_name'] = $found['full_name'];
                    $submitted['email'] = $found['email'];
                    $submitted['role_id'] = (string) $found['role_id'];
                    $submitted['is_active'] = filter_var($found['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false ? 'false' : 'true';
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
                        'full_name' => '',
                        'email' => '',
                        'password' => '',
                        'role_id' => '',
                        'is_active' => 'true',
                    ];
                }
            }
        } elseif ($action === 'bulk_delete') {
          if (!$selectedIds) {
                $error = 'Select at least one user to delete.';
            } else {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id IN ({$placeholders})");
                $stmt->execute($selectedIds);
                $deleted = $stmt->rowCount();
                $success = $deleted . ' user(s) removed.';
            }
             }
} catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = format_db_error($e, 'users table');
    }
}

$users = [];
try {
    $stmt = $pdo->query('SELECT u.user_id, u.full_name, u.email, u.is_active, u.created_at, r.role_name, r.role_id FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.user_id LEFT JOIN roles r ON r.role_id = ur.role_id ORDER BY u.user_id');
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    $error = $error ?: format_db_error($e, 'users and roles tables');
}

$userIdOptions = array_column($users, 'user_id');
$userIdSelectValue = in_array($submitted['user_id'], $userIdOptions, true) ? $submitted['user_id'] : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
  <script src="./assets/app.js" defer></script>
</head>
<body class="page">
<header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
    </div>
    <div class="title">Users</div>
    <div class="links">
      <div class="user-chip">
        <span class="name"><?php echo safe($currentUser['username']); ?></span>
        <span class="role"><?php echo strtoupper(safe($currentUser['role'])); ?></span>
      </div>
      <a href="./home.php">Home</a>
      <a class="logout-icon" href="./logout.php" aria-label="Logout">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M15 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M10 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M16 8l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
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
        <div class="alert" style="color: var(--secondary); margin-bottom:12px;">
          <?php echo safe($success); ?> 
        </div> 
      <?php endif; ?> 

  <form method="POST" action="users.php">
       <div class="title">Users</div>
    <div class="links">
      <div class="user-chip">
        <span class="name"><?php echo safe($currentUser['username']); ?></span>
        <span class="role"><?php echo strtoupper(safe($currentUser['role'])); ?></span>
      </div>
      <a href="./home.php">Home</a>
      <a class="logout-icon" href="./logout.php" aria-label="Logout">
        <div class="actions">
          <button class="btn btn-save" type="submit" name="action" value="create">Create New User</button>
          <button class="btn btn-neutral" type="submit" name="action" value="view">View</button>
          <button class="btn btn-neutral" type="submit" name="action" value="update">Update</button>
          <button class="btn btn-delete" type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
        </div>
       </form>
    </div>

    <form method="POST" action="users.php">
      <div class="table-actions">
        <div class="actions">
          <button class="btn btn-delete" type="submit" name="action" value="bulk_delete" onclick="return confirm('Delete selected users?');">Delete Selected</button>
        </div>
      </div>
      <div class="table-wrapper">
         <table id="users-table">
          <thead>
            <tr><th><input type="checkbox" onclick="toggleAll(this, 'users-table')" aria-label="Select all users" /></th><th>ID</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>
          </thead>
          <tbody>
             <?php if ($users): ?>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td><input type="checkbox" name="selected_ids[]" value="<?php echo safe($user['user_id']); ?>" /></td>
                  <td><?php echo safe($user['user_id']); ?></td>
                  <td><?php echo safe($user['full_name']); ?></td>
                  <td><?php echo safe($user['email']); ?></td>
                  <td><?php echo safe($user['role_name'] ?? 'Unassigned'); ?></td>
                  <td><?php echo filter_var($user['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false ? 'Inactive' : 'Active'; ?></td>
                  <td><?php echo safe($user['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7">No users recorded yet.</td></tr>
            <?php endif; ?>
        </tbody>
        </table>
      </div>
      </form>
  </main>
</body>
</html>


