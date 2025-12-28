<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$pdo = get_pdo();
$error = '';
$success = '';

// Handle role and permission actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_role') {
            $roleName = trim($_POST['role_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isActive = ($_POST['is_active'] ?? 'active') === 'active';

            if ($roleName === '') {
                $error = 'Role name is required to create a role.';
            } else {
                $duplicate = $pdo->prepare('SELECT 1 FROM roles WHERE LOWER(role_name) = LOWER(:name)');
                $duplicate->execute([':name' => $roleName]);

                if ($duplicate->fetchColumn()) {
                    $error = 'A role with this name already exists.';
                } else {
                    $insert = $pdo->prepare('INSERT INTO roles (role_name, description, is_active) VALUES (:name, :description, :active)');
                    $insert->execute([
                        ':name' => $roleName,
                        ':description' => $description,
                        ':active' => $isActive ? 'true' : 'false',
                    ]);
                    $success = 'Role created successfully.';
                }
            }
        } elseif ($action === 'update_role') {
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $isActive = ($_POST['is_active'] ?? 'inactive') === 'active';

            if ($roleId === 0) {
                $error = 'Choose a role to update.';
            } else {
                $update = $pdo->prepare('UPDATE roles SET description = :description, is_active = :active WHERE role_id = :id');
                $update->execute([
                    ':description' => $description,
                    ':active' => $isActive ? 'true' : 'false',
                    ':id' => $roleId,
                ]);
                $success = 'Role updated successfully.';
            }
        } elseif ($action === 'delete_role') {
            $roleId = (int) ($_POST['role_id'] ?? 0);

            if ($roleId === 0) {
                $error = 'Choose a role to delete.';
            } else {
                $pdo->beginTransaction();

                $pdo->prepare('DELETE FROM role_module_permissions WHERE role_id = :role_id')->execute([':role_id' => $roleId]);
                $pdo->prepare('DELETE FROM user_roles WHERE role_id = :role_id')->execute([':role_id' => $roleId]);
                $pdo->prepare('DELETE FROM roles WHERE role_id = :role_id')->execute([':role_id' => $roleId]);

                $pdo->commit();
                $success = 'Role deleted successfully.';
            }
        } elseif ($action === 'save_permissions') {
            $activeRoleId = (int) ($_POST['active_role_id'] ?? 0);
            $permissions = (array) ($_POST['permissions'] ?? []);

            if ($activeRoleId === 0) {
                $error = 'Select a role to update its permissions.';
            } else {
                $pdo->beginTransaction();

                $pdo->prepare('DELETE FROM role_module_permissions WHERE role_id = :role_id')->execute([':role_id' => $activeRoleId]);

                $insert = $pdo->prepare('INSERT INTO role_module_permissions (role_id, module_id, can_create, can_read, can_update, can_delete) VALUES (:role_id, :module_id, :create, :read, :update, :delete)');

                foreach ($permissions as $roleId => $modulesPermissions) {
                    // Only persist the active role to avoid accidental overwrites.
                    if ((int) $roleId !== $activeRoleId) {
                        continue;
                    }

                    foreach ($modulesPermissions as $moduleId => $crud) {
                        $hasPermission = isset($crud['create'], $crud['read'], $crud['update'], $crud['delete'])
                            || isset($crud['create']) || isset($crud['read']) || isset($crud['update']) || isset($crud['delete']);

                        if (!$hasPermission) {
                            continue;
                        }

                        $insert->execute([
                            ':role_id' => $activeRoleId,
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
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = format_db_error($e, 'roles and permissions');
    }
}

$roles = fetch_table('roles', 'role_name');
$modules = fetch_table('modules', 'module_name');

$roleOptions = [];
foreach ($roles as $role) {
    $roleOptions[(int) $role['role_id']] = $role;
}

$activeRoleId = isset($_POST['active_role_id']) ? (int) $_POST['active_role_id'] : null;
if ($activeRoleId === null && isset($_GET['role'])) {
    $activeRoleId = (int) $_GET['role'];
}
if ($activeRoleId === null && $roles) {
    $activeRoleId = (int) $roles[0]['role_id'];
}
if ($activeRoleId !== null && !isset($roleOptions[$activeRoleId]) && $roles) {
    $activeRoleId = (int) $roles[0]['role_id'];
}

$permissionLookup = [];
if ($roles && $modules) {
    try {
        $stmt = $pdo->query('SELECT role_id, module_id, can_create, can_read, can_update, can_delete FROM role_module_permissions');
        foreach ($stmt->fetchAll() as $row) {
            $rid = (int) $row['role_id'];
            $mid = (int) $row['module_id'];
            $permissionLookup[$rid][$mid] = [
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

$activeRoleName = $activeRoleId !== null && isset($roleOptions[$activeRoleId])
    ? (string) $roleOptions[$activeRoleId]['role_name']
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Role Management | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <?php if ($error || $success): ?>
    <div class="message-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Role notification">
      <div class="message-dialog <?php echo $error ? 'is-error' : 'is-success'; ?>">
        <div class="message-dialog__header">
          <span class="message-title"><?php echo $error ? 'Action needed' : 'Success'; ?></span>
          <button class="message-close" type="button" aria-label="Close message">&times;</button>
        </div>
        <p class="message-body"><?php echo safe($error ?: $success); ?></p>
      </div>
    </div>
  <?php endif; ?>
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
    <section class="form-container" aria-labelledby="role-actions-heading">
      <div class="section-header">
        <div>
          <h3 id="role-actions-heading" style="margin:0; color:var(--secondary);">Role Actions</h3>
          <br>
          <!-- <p class="muted">Create, edit, or delete roles. Current roles are listed below.</p> -->
        </div>
        <button class="btn btn-save" type="submit" form="create-role-form">Create Role</button>
      </div>

      <form id="create-role-form" method="POST" action="role-access.php" class="form-row">
        <input type="hidden" name="action" value="create_role" />
        <div style="flex:1;">
          <label class="label" for="role-name">Role Name</label>
          <input id="role-name" name="role_name" type="text" placeholder="e.g. Procurement" required />
        </div>
        <div style="flex:2;">
          <label class="label" for="role-description">Description</label>
          <input id="role-description" name="description" type="text" placeholder="What is this role responsible for?" />
        </div>
        <div class="radio-group">
          <span class="label" style="margin-bottom:4px;">Status</span>
          <label class="radio-option">
            <input type="radio" name="is_active" value="active" checked />
            Active
          </label>
          <label class="radio-option">
            <input type="radio" name="is_active" value="inactive" />
            Inactive
          </label>
        </div>
      </form>

      <div class="table-wrapper" aria-live="polite">
        <table>
          <thead>
            <tr><th>Role</th><th>Description</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if ($roles): ?>
              <?php foreach ($roles as $role): ?>
                <form id="role-form-<?php echo safe($role['role_id']); ?>" method="POST" action="role-access.php"></form>
                <tr>
                  <td><?php echo safe($role['role_name']); ?></td>
                  <td>
                    <input form="role-form-<?php echo safe($role['role_id']); ?>" type="text" name="description" value="<?php echo safe($role['description']); ?>" />
                  </td>
                   <td style="text-align:center;">
                    <div class="radio-group" style="justify-content:center;">
                      <label class="radio-option">
                        <input form="role-form-<?php echo safe($role['role_id']); ?>" type="radio" name="is_active" value="active" <?php echo $role['is_active'] ? 'checked' : ''; ?> />
                        Active
                      </label>
                      <label class="radio-option">
                        <input form="role-form-<?php echo safe($role['role_id']); ?>" type="radio" name="is_active" value="inactive" <?php echo !$role['is_active'] ? 'checked' : ''; ?> />
                        Inactive
                      </label>
                    </div>
                  </td>
                  <td class="role-row-actions">
                    <input form="role-form-<?php echo safe($role['role_id']); ?>" type="hidden" name="role_id" value="<?php echo safe($role['role_id']); ?>" />
                    <button form="role-form-<?php echo safe($role['role_id']); ?>" class="btn btn-save" name="action" value="update_role" type="submit">Save</button>
                    <button form="role-form-<?php echo safe($role['role_id']); ?>" class="btn btn-delete" name="action" value="delete_role" type="submit">Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="4">No roles found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="form-container" aria-labelledby="permissions-heading">
      <div class="section-header">
        <div>
          <h3 id="permissions-heading" style="margin:0; color:var(--secondary);">Permissions</h3>
          <br>
          <!-- <p class="muted">Choose for a role to load its current permissions, then toggle CRUD per module.</p> -->
        </div>
        <?php if ($activeRoleName): ?>
          <div class="badge" id="active-role-badge">Currently viewing: <?php echo safe($activeRoleName); ?></div>
        <?php endif; ?>
      </div>

      <form method="POST" action="role-access.php" class="stacked" id="permissions-form">
        <input type="hidden" name="action" value="save_permissions" />
        <input type="hidden" name="active_role_id" id="active-role-id" value="<?php echo safe((string) $activeRoleId); ?>" />

       <div class="form-row">
          <div>
            <label class="label" for="role-select">Select Role</label>
            <select id="role-select">
              <?php foreach ($roles as $role): ?>
                <option value="<?php echo safe($role['role_id']); ?>" <?php echo $role['role_id'] === $activeRoleId ? 'selected' : ''; ?>><?php echo safe($role['role_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="table-wrapper">
          <table id="access-table">
            <thead>
              <tr><th>Module Name</th><th>Module Description</th><th>Create</th><th>Read</th><th>Update</th><th>Delete</th></tr>
            </thead>
            <tbody>
              <?php if ($modules && $activeRoleId !== null): ?>
                <?php foreach ($modules as $module): ?>
                  <?php $perms = $permissionLookup[$activeRoleId][$module['module_id']] ?? ['create' => false, 'read' => false, 'update' => false, 'delete' => false]; ?>
                  <tr class="perm-row">
                    <td><?php echo safe($module['module_code'] ?? ''); ?></td>
                    <td><?php echo safe($module['module_name']); ?></td>
                    <td style="text-align:center;"><input class="permission-input" data-module-id="<?php echo safe($module['module_id']); ?>" data-action="create" type="checkbox" name="permissions[<?php echo safe((string) $activeRoleId); ?>][<?php echo safe($module['module_id']); ?>][create]" <?php echo $perms['create'] ? 'checked' : ''; ?> /></td>
                    <td style="text-align:center;"><input class="permission-input" data-module-id="<?php echo safe($module['module_id']); ?>" data-action="read" type="checkbox" name="permissions[<?php echo safe((string) $activeRoleId); ?>][<?php echo safe($module['module_id']); ?>][read]" <?php echo $perms['read'] ? 'checked' : ''; ?> /></td>
                    <td style="text-align:center;"><input class="permission-input" data-module-id="<?php echo safe($module['module_id']); ?>" data-action="update" type="checkbox" name="permissions[<?php echo safe((string) $activeRoleId); ?>][<?php echo safe($module['module_id']); ?>][update]" <?php echo $perms['update'] ? 'checked' : ''; ?> /></td>
                    <td style="text-align:center;"><input class="permission-input" data-module-id="<?php echo safe($module['module_id']); ?>" data-action="delete" type="checkbox" name="permissions[<?php echo safe((string) $activeRoleId); ?>][<?php echo safe($module['module_id']); ?>][delete]" <?php echo $perms['delete'] ? 'checked' : ''; ?> /></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6">Add roles and modules to configure permissions.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="actions"><button class="btn btn-save" type="submit">Save Permissions</button></div>
      </form>
    </section>
  </main>

 <script>
    const permissionLookup = <?php echo json_encode($permissionLookup); ?>;
    const roleSelect = document.querySelector('#role-select');
    const badge = document.querySelector('#active-role-badge');
    const activeRoleInput = document.querySelector('#active-role-id');
    const permissionInputs = Array.from(document.querySelectorAll('.permission-input'));
    const messageModal = document.querySelector('.message-modal');
    const messageClose = document.querySelector('.message-close');

    function updateBadge(roleId) {
      if (!badge || !roleSelect) return;
      const option = roleSelect.querySelector(`option[value="${roleId}"]`);
      if (option) {
        badge.textContent = `Currently viewing: ${option.textContent}`;
      }
    }

    function applyPermissions(roleId) {
      if (!roleId) return;

      permissionInputs.forEach((input) => {
        const moduleId = input.dataset.moduleId;
        const action = input.dataset.action;
        const perms = (permissionLookup[roleId] && permissionLookup[roleId][moduleId]) || {};
        input.checked = Boolean(perms[action]);
        input.name = `permissions[${roleId}][${moduleId}][${action}]`;
      });

      if (activeRoleInput) {
        activeRoleInput.value = roleId;
      }
      updateBadge(roleId);
    }

  if (roleSelect) {
      roleSelect.addEventListener('change', (event) => {
        const roleId = event.target.value;
        applyPermissions(roleId);
      });
    }

    if (roleSelect) {
      applyPermissions(roleSelect.value);
    }

    if (messageModal && messageClose) {
      messageClose.addEventListener('click', () => messageModal.classList.remove('is-visible'));
    }
  </script>
</body>
</html>