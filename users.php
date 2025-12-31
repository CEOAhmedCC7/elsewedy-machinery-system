<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$moduleCode = resolve_module_code('USERS');
$error = '';
$success = '';
$passwordPlaceholder = '********';

$pdo = null;
try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    $error = format_db_error($e, 'database connection');
}

$roles = fetch_table('roles', 'role_name');
$roleOptions = to_options($roles, 'role_id', 'role_name');
$allowedRoleIds = array_map(static fn ($role) => (string) $role['value'], $roleOptions);

$submitted = [
    'user_id' => trim($_POST['user_id'] ?? ''),
    'full_name' => trim($_POST['full_name'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'password' => trim($_POST['password'] ?? ''),
    'role_id' => trim($_POST['role_id'] ?? ''),
    'is_active' => ($_POST['is_active'] ?? 'true') === 'false' ? 'false' : 'true',
];

$selectedIds = array_map('intval', (array) ($_POST['selected_ids'] ?? []));

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $permissionError = enforce_action_permission(
        $currentUser,
        $moduleCode ?? 'USERS',
        $action,
        [
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            'bulk_delete' => 'delete',
        ]
    );

    try {
        if ($permissionError) {
            $error = $permissionError;
        } elseif ($action === 'create') {
            $isActive = filter_var($submitted['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($submitted['full_name'] === '') {
                $error = 'Please enter a full name for the new account.';
            } elseif ($submitted['email'] === '') {
                $error = 'Please enter an email address.';
            } elseif (!filter_var($submitted['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid email address.';
            } elseif ($submitted['password'] === '') {
                $error = 'Password is required to create a user.';
            } elseif (!in_array((string) $submitted['role_id'], $allowedRoleIds, true)) {
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

            $passwordForUpdate = $submitted['password'] === $passwordPlaceholder ? '' : $submitted['password'];

            if ($submitted['user_id'] === '') {
                $error = 'Please provide the User ID to update.';
            } elseif ($submitted['full_name'] === '') {
                $error = 'Full name cannot be empty when updating a user.';
            } elseif ($submitted['email'] === '') {
                $error = 'Email cannot be empty when updating a user.';
            } elseif (!filter_var($submitted['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid email address.';
            } elseif (!in_array((string) $submitted['role_id'], $allowedRoleIds, true)) {
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
                    if ($passwordForUpdate !== '') {
                        if (strlen($passwordForUpdate) < 8) {
                            throw new RuntimeException('Password must be at least 8 characters long.');
                        }
                        $passwordSql = ', password_hash = :hash';
                        $params[':hash'] = $passwordForUpdate;
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
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = format_db_error($e, 'users table');
    }
}

$filters = [
    'status' => $_GET['filter_status'] ?? '',
    'role_id' => trim($_GET['filter_role'] ?? ''),
    'user_id' => trim($_GET['filter_user_id'] ?? ''),
    'name' => trim($_GET['filter_name'] ?? ''),
];

$users = [];
try {
    if ($pdo) {
        $conditions = [];
        $params = [];

        if ($filters['user_id'] !== '') {
            $conditions[] = 'u.user_id = :filter_user_id';
            $params[':filter_user_id'] = $filters['user_id'];
        }

        if ($filters['status'] === 'active') {
            $conditions[] = 'u.is_active = true';
        } elseif ($filters['status'] === 'inactive') {
            $conditions[] = 'u.is_active = false';
        }

        if ($filters['role_id'] !== '') {
            $conditions[] = 'r.role_id = :filter_role_id';
            $params[':filter_role_id'] = $filters['role_id'];
        }

        if ($filters['name'] !== '') {
            $conditions[] = 'LOWER(u.full_name) LIKE :filter_name';
            $params[':filter_name'] = '%' . strtolower($filters['name']) . '%';
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT u.user_id, u.full_name, u.email, u.is_active, u.created_at, r.role_name, r.role_id FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.user_id LEFT JOIN roles r ON r.role_id = ur.role_id {$whereSql} ORDER BY u.user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
    }
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
  <?php if ($error !== '' || $success !== ''): ?>
    <div class="message-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Users notification">
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
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">User Management</h3>

      <form method="POST" action="users.php" id="user-form">
        <div class="form-row">
          <div>
            <label class="label" for="user_id">User</label>
            <select id="user_id" name="user_id">
              <option value="">Select a user</option>
              <?php foreach ($users as $user): ?>
                <option value="<?php echo safe($user['user_id']); ?>" <?php echo $userIdSelectValue == $user['user_id'] ? 'selected' : ''; ?>>
                  <?php echo safe($user['full_name'] ?: $user['email']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="full_name">Full Name</label>
            <input id="full_name" name="full_name" type="text" value="<?php echo safe($submitted['full_name']); ?>" placeholder="Enter the user's full name" />
          </div>
        </div>

        <div class="form-row">
          <div>
            <label class="label" for="email">Email</label>
            <input id="email" name="email" type="email" value="<?php echo safe($submitted['email']); ?>" placeholder="Enter the user's email" />
          </div>
          <div>
            <label class="label" for="password">Password</label>
            <input id="password" name="password" type="password" value="<?php echo safe($submitted['password']); ?>" placeholder="Set or update password" />
          </div>
        </div>

        <div class="form-row">
          <div>
            <label class="label" for="role_id">Role</label>
            <select id="role_id" name="role_id">
              <option value="">Select role</option>
              <?php foreach ($roleOptions as $role): ?>
                <option value="<?php echo safe($role['value']); ?>" <?php echo $submitted['role_id'] === $role['value'] ? 'selected' : ''; ?>>
                  <?php echo safe($role['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="is_active">Status</label>
            <select id="is_active" name="is_active">
              <option value="true" <?php echo $submitted['is_active'] === 'true' ? 'selected' : ''; ?>>Active</option>
              <option value="false" <?php echo $submitted['is_active'] === 'false' ? 'selected' : ''; ?>>Inactive</option>
            </select>
          </div>
        </div>

        <div class="actions" style="margin:12px 0 28px; gap:10px; flex-wrap:wrap;">
          <button class="btn btn-save" type="submit" name="action" value="create">Create New User</button>
          <button class="btn btn-update" type="submit" name="action" value="update">Update</button>
          <button class="btn btn-delete" type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
          <button class="btn btn-clear" type="button" id="clear-fields">Clear Fields</button>
        </div>
      </form>

     <form method="GET" action="users.php" class="filter-form">
        <table class="filter-table">
          <tbody>
            <tr>
              <td class="filter-cell">
                <label class="label" for="filter_user_id">Filter by ID</label>
                <input type="number" id="filter_user_id" name="filter_user_id" value="<?php echo safe($filters['user_id']); ?>" placeholder="User ID" />
              </td>
              <td class="filter-cell">
                <label class="label" for="filter_name">Filter by Name</label>
                <input type="text" id="filter_name" name="filter_name" value="<?php echo safe($filters['name']); ?>" placeholder="Full name" />
              </td>

               
              <td class="filter-cell">
                <label class="label" for="filter_role">Filter by Role</label>
                <select id="filter_role" name="filter_role">
                  <option value="">All roles</option>
                  <?php foreach ($roleOptions as $role): ?>
                    <option value="<?php echo safe($role['value']); ?>" <?php echo $filters['role_id'] === $role['value'] ? 'selected' : ''; ?>>
                      <?php echo safe($role['label']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="filter-cell">
                <label class="label" for="filter_status">Filter by Status</label>
                <select id="filter_status" name="filter_status">
                  <option value="">All statuses</option>
                  <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                  <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
              </td>
              <td class="filter-actions-cell" rowspan="2">
                <div class="actions filter-actions">
                  <button class="btn btn-filter" type="submit">Apply Filters</button>
                  <a class="btn btn-reset" href="users.php" style="text-decoration:none;">Reset</a>
                  <button class="btn btn-delete" type="submit" form="bulk-delete-form" name="action" value="bulk_delete" onclick="return confirm('Delete selected users?');">Delete Selected</button>
                </div>
              </td>
            
            </tr>
          </tbody>
        </table>
      </form>
      <div class="table-wrapper">
        <table id="users-table">
          <thead>
            <tr>
              <th><input type="checkbox" onclick="toggleAll(this, 'users-table')" aria-label="Select all users" /></th>
              <th>ID</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
            </tr>
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
                  <td><?php echo safe($user['created_at'] ? date('Y-m-d', strtotime($user['created_at'])) : ''); ?></td>
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
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const userData = <?php echo json_encode(array_map(static fn ($user) => [
          'user_id' => $user['user_id'],
          'full_name' => $user['full_name'],
          'email' => $user['email'],
          'role_id' => $user['role_id'],
          'is_active' => filter_var($user['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false ? 'false' : 'true',
      ], $users), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

      const userMap = new Map(userData.map((user) => [String(user.user_id), user]));

      const userSelect = document.getElementById('user_id');
      const fullNameInput = document.getElementById('full_name');
      const emailInput = document.getElementById('email');
      const passwordInput = document.getElementById('password');
      const roleSelect = document.getElementById('role_id');
      const statusSelect = document.getElementById('is_active');
      const clearButton = document.getElementById('clear-fields');
      const form = document.getElementById('user-form');
      const messageModal = document.querySelector('.message-modal');
      const messageClose = document.querySelector('.message-close');
      const passwordPlaceholder = '<?php echo $passwordPlaceholder; ?>';

      const resetFields = () => {
        userSelect.value = '';
        fullNameInput.value = '';
        emailInput.value = '';
        passwordInput.value = '';
        roleSelect.value = '';
        statusSelect.value = 'true';
      };

      const populateFields = (userId) => {
        const user = userMap.get(String(userId));
        if (!user) {
          return;
        }

        fullNameInput.value = user.full_name || '';
        emailInput.value = user.email || '';
        passwordInput.value = passwordPlaceholder;
        roleSelect.value = user.role_id ? String(user.role_id) : '';
        statusSelect.value = user.is_active === 'false' ? 'false' : 'true';
      };

      userSelect.addEventListener('change', (event) => {
        const { value } = event.target;
        if (!value) {
          resetFields();
          return;
        }
        populateFields(value);
      });

      if (userSelect.value) {
        populateFields(userSelect.value);
      }

      clearButton.addEventListener('click', () => {
        resetFields();
      });

      form.addEventListener('submit', () => {
        passwordInput.value = passwordInput.value.trim();
        if (passwordInput.value === passwordPlaceholder) {
          passwordInput.value = '';
        }
      });

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
    });
  </script>
</body>
</html>
