<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$pdo = get_pdo();
$error = '';
$success = '';

// Handle form submissions for role creation, updates, and permission assignments.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_role') {
            $roleName = trim($_POST['role_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isActive = isset($_POST['is_active']);

            if ($roleName === '') {
                $error = 'Role name is required to create a new role.';
            } else {
                $check = $pdo->prepare('SELECT 1 FROM roles WHERE LOWER(role_name) = LOWER(:name)');
                $check->execute([':name' => $roleName]);

                if ($check->fetchColumn()) {
                    $error = 'A role with this name already exists.';
                } else {
                    $insert = $pdo->prepare('INSERT INTO roles (role_name, description, is_active) VALUES (:name, :description, :active)');
                    $insert->execute([
                        ':name' => $roleName,
                        ':description' => $description,
                        ':active' => $isActive,
                    ]);
                    $success = 'Role created successfully.';
                }
            }
        } elseif ($action === 'update_roles') {
            $roleUpdates = (array) ($_POST['roles'] ?? []);

            $update = $pdo->prepare('UPDATE roles SET description = :description, is_active = :active WHERE role_id = :id');

            foreach ($roleUpdates as $roleId => $data) {
                $update->execute([
                    ':description' => trim($data['description'] ?? ''),
                    ':active' => isset($data['is_active']) ? 'true' : 'false',
                    ':id' => (int) $roleId,
                ]);
            }

            $success = 'Roles updated successfully.';
        } elseif ($action === 'save_permissions') {
            $permissionsInput = (array) ($_POST['permissions'] ?? []);

            $pdo->beginTransaction();

            foreach ($permissionsInput as $roleId => $modulesPermissions) {
                $roleId = (int) $roleId;
                $delete = $pdo->prepare('DELETE FROM role_module_permissions WHERE role_id = :role_id');
                $delete->execute([':role_id' => $roleId]);

                $insert = $pdo->prepare('INSERT INTO role_module_permissions (role_id, module_id, can_create, can_read, can_update, can_delete) VALUES (:role_id, :module_id, :create, :read, :update, :delete)');

                foreach ($modulesPermissions as $moduleId => $crud) {
                    $hasAnyPermission = isset($crud['create']) || isset($crud['read']) || isset($crud['update']) || isset($crud['delete']);

                    if (!$hasAnyPermission) {
                        continue;
                    }

                    $insert->execute([
                        ':role_id' => $roleId,
                        ':module_id' => (int) $moduleId,
                        ':create' => isset($crud['create']) ? 'true' : 'false',
                        ':read' => isset($crud['read']) ? 'true' : 'false',
                        ':update' => isset($crud['update']) ? 'true' : 'false',
                        ':delete' => isset($crud['delete']) ? 'true' : 'false',
                    ]);
                }
            }

            $pdo->commit();
            $success = 'Permissions saved successfully.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = format_db_error($e, 'roles and permissions');
    }
}

// Fetch roles and modules from the database to build the management tables.
$roles = fetch_table('roles', 'role_name');
$modules = fetch_table('modules', 'module_name');

// Build a permission lookup for quick rendering.
$permissionLookup = [];

if ($roles && $modules) {
    try {
        $stmt = $pdo->query('SELECT role_id, module_id, can_create, can_read, can_update, can_delete FROM role_module_permissions');
        foreach ($stmt->fetchAll() as $row) {
            $roleId = (int) $row['role_id'];
            $moduleId = (int) $row['module_id'];

            $permissionLookup[$roleId][$moduleId] = [
                'create' => (bool) $row['can_create'],
                'read' => (bool) $row['can_read'],
                'update' => (bool) $row['can_update'],
                'delete' => (bool) $row['can_delete'],
            ];
        }
    } catch (Throwable $e) {
        $error = format_db_error($e, 'role_module_permissions table');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Role Management | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
  <script src="./assets/app.js" defer></script>
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
    </div>
    <div class="title">Role Management</div>
    <div class="links">
      <div class="user-chip">
        <span class="name"><?php echo safe($currentUser['username']); ?></span>
        <span class="role"><?php echo strtoupper(safe($currentUser['role'])); ?></span>
      </div>
      <a href="./home.php">Home</a>
      <a class="logout-icon" href="./logout.php" aria-label="Logout">⎋</a>
    </div>
  </header>
   <main style="padding:24px; display:grid; gap:24px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Create Role</h3>
      <?php if ($error): ?>
        <div class="alert" style="color: var(--secondary); margin-bottom:12px;"><?php echo safe($error); ?></div>
      <?php elseif ($success): ?>
        <div class="alert" style="color: var(--secondary); margin-bottom:12px;"><?php echo safe($success); ?></div>
      <?php endif; ?>
      <form method="POST" action="role-access.php" class="form-row">
        <input type="hidden" name="action" value="create_role" />
        <div style="flex:1;">
          <label class="label" for="role-name">Role Name</label>
          <input id="role-name" name="role_name" type="text" placeholder="e.g. Procurement" required />
        </div>
        <div style="flex:2;">
          <label class="label" for="description">Description</label>
          <input id="description" name="description" type="text" placeholder="What is this role responsible for?" />
        </div>
        <label class="label" style="display:flex; align-items:center; gap:8px; margin-top:26px;">
          <input type="checkbox" name="is_active" checked /> Active
        </label>
        <button class="btn btn-save" type="submit" style="align-self:flex-end;">Add Role</button>
      </form>
    </div>

    <form method="POST" action="role-access.php" class="form-container">
      <input type="hidden" name="action" value="update_roles" />
      <h3 style="margin-top:0; color:var(--secondary);">Existing Roles</h3>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>Role</th><th>Description</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if ($roles): ?>
              <?php foreach ($roles as $role): ?>
                <tr>
                  <td><?php echo safe($role['role_name']); ?></td>
                  <td><input type="text" name="roles[<?php echo safe($role['role_id']); ?>][description]" value="<?php echo safe($role['description']); ?>" /></td>
                  <td style="text-align:center;"><input type="checkbox" name="roles[<?php echo safe($role['role_id']); ?>][is_active]" <?php echo $role['is_active'] ? 'checked' : ''; ?> /></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3">No roles found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="actions"><button class="btn btn-save" type="submit">Save Role Updates</button></div>
    </form>

    <form method="POST" action="role-access.php" class="form-container">
      <input type="hidden" name="action" value="save_permissions" />
      <h3 style="margin-top:0; color:var(--secondary);">Module Access by Role</h3>
      <p style="margin-top:0; color:#444;">Assign CRUD permissions per module for each role. Only checked capabilities will be saved.</p>
      <div class="table-wrapper">
        <table id="access-table">
          <thead>
            <tr>
              <th>Module</th>
              <?php foreach ($roles as $role): ?>
                <th colspan="4" style="text-align:center;"><?php echo safe($role['role_name']); ?></th>
              <?php endforeach; ?>
            </tr>
            <tr>
              <th></th>
              <?php foreach ($roles as $_): ?>
                <th style="text-align:center;">C</th>
                <th style="text-align:center;">R</th>
                <th style="text-align:center;">U</th>
                <th style="text-align:center;">D</th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($modules && $roles): ?>
              <?php foreach ($modules as $module): ?>
                <tr>
                  <td><?php echo safe($module['module_name']); ?></td>
                  <?php foreach ($roles as $role): ?>
                    <?php $perms = $permissionLookup[$role['role_id']][$module['module_id']] ?? ['create' => false, 'read' => false, 'update' => false, 'delete' => false]; ?>
                    <td style="text-align:center;"><input type="checkbox" name="permissions[<?php echo safe($role['role_id']); ?>][<?php echo safe($module['module_id']); ?>][create]" <?php echo $perms['create'] ? 'checked' : ''; ?> /></td>
                    <td style="text-align:center;"><input type="checkbox" name="permissions[<?php echo safe($role['role_id']); ?>][<?php echo safe($module['module_id']); ?>][read]" <?php echo $perms['read'] ? 'checked' : ''; ?> /></td>
                    <td style="text-align:center;"><input type="checkbox" name="permissions[<?php echo safe($role['role_id']); ?>][<?php echo safe($module['module_id']); ?>][update]" <?php echo $perms['update'] ? 'checked' : ''; ?> /></td>
                    <td style="text-align:center;"><input type="checkbox" name="permissions[<?php echo safe($role['role_id']); ?>][<?php echo safe($module['module_id']); ?>][delete]" <?php echo $perms['delete'] ? 'checked' : ''; ?> /></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="<?php echo 1 + (count($roles) * 4); ?>">Add roles and modules to configure permissions.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="actions"><button class="btn btn-save" type="submit">Save Permissions</button></div>
    </form>
  </main>
</body>
</html>