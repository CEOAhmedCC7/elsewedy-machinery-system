<?php
// Common helper utilities for PHP views

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const ALLOWED_TABLES = [
    'batches',
    'budget_update_requests',
    'budgets',
    'business_lines',
    'cash_in',
    'cash_out',
    'collection_targets',
    'customers',
    'hot_deals',
    'items',
    'invoices',
    'leads_tracking',
    'lost_deals',
    'modules',
    'opportunity_owners',
    'products',
    'projects',
    'revenue_targets',
    'role_module_permissions',
    'roles',
    'sales',
    'sales_targets',
    'sleeping_opportunities',
    'sub_batch_details',
    'suppliers',
    'user_roles',
    'users',
];


function fetch_table(string $table, string $orderBy = ''): array
{
    if (!in_array($table, ALLOWED_TABLES, true)) {
        return [];
    }

     $pdo = get_pdo();
    $orderSql = $orderBy !== '' ? " ORDER BY {$orderBy}" : '';

    try {
        $stmt = $pdo->query("SELECT * FROM {$table}{$orderSql}");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log(sprintf('Failed to fetch table %s: %s', $table, $e->getMessage()));
        return [];
    }
}

function to_options(array $rows, string $valueKey, string $labelKey): array
{
    $options = [];
    foreach ($rows as $row) {
        if (!isset($row[$valueKey], $row[$labelKey])) {
            continue;
        }
        $options[] = [
            'value' => (string) $row[$valueKey],
            'label' => (string) $row[$labelKey],
        ];
    }
    return $options;
}

function safe(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        header('Location: login.php');
        exit;
    }

    $userId = isset($user['user_id']) ? (int) $user['user_id'] : 0;

    if ($userId > 0) {
        try {
            $pdo = get_pdo();
            $stmt = $pdo->prepare(
                'SELECT u.user_id, u.full_name, u.email, u.is_active, r.role_name
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.user_id = u.user_id
                 LEFT JOIN roles r ON r.role_id = ur.role_id
                 WHERE u.user_id = :user_id
                 LIMIT 1'
            );
            $stmt->execute([':user_id' => $userId]);
            $freshUser = $stmt->fetch();

            if ($freshUser) {
                $displayName = $freshUser['full_name'] ?: ($freshUser['email'] ?? ($user['username'] ?? ''));
                $updatedUser = [
                    'user_id' => $freshUser['user_id'],
                    'username' => $displayName,
                    'email' => $freshUser['email'] ?? $user['email'] ?? '',
                    'role' => $freshUser['role_name'] ?? 'user',
                ];

                $_SESSION['user'] = $updatedUser;
                $user = $updatedUser;
            }
        } catch (Throwable $e) {
            error_log('Failed to refresh user session: ' . $e->getMessage());
        }
    }

    return $user;
}


/**
 * Load CRUD permissions for the given user keyed by module_code.
 */
function get_user_crud_permissions(int $userId): array
{
    static $cache = [];

    if (isset($cache[$userId])) {
        return $cache[$userId];
    }

    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'SELECT m.module_code, rp.can_create, rp.can_read, rp.can_update, rp.can_delete
             FROM role_module_permissions rp
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             INNER JOIN modules m ON m.module_id = rp.module_id
             WHERE ur.user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);

        $permissions = [];
        foreach ($stmt->fetchAll() as $row) {
            $code = strtoupper((string) $row['module_code']);
            $permissions[$code] = [
                'create' => filter_var($row['can_create'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'read' => filter_var($row['can_read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'update' => filter_var($row['can_update'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'delete' => filter_var($row['can_delete'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ];
        }

        $cache[$userId] = $permissions;
        return $permissions;
    } catch (Throwable $e) {
        error_log('Failed to load user permissions: ' . $e->getMessage());
        return [];
    }
}

/**
 * Check if the supplied user can perform the CRUD action on the module code.
 */
function has_crud_permission(array $user, string $moduleCode, string $action): bool
{
    if (empty($user['user_id'])) {
        return false;
    }

    $normalizedAction = strtolower($action);
    if (!in_array($normalizedAction, ['create', 'read', 'update', 'delete'], true)) {
        return false;
    }

    $permissions = get_user_crud_permissions((int) $user['user_id']);
    $code = strtoupper($moduleCode);

    if (!isset($permissions[$code])) {
        return false;
    }

    return !empty($permissions[$code][$normalizedAction]);
}

/**
 * Try to resolve the module code using the provided fallback or the current script name.
 */
function resolve_module_code(?string $fallbackCode = null): ?string
{
    $normalizedFallback = $fallbackCode !== null ? strtoupper(trim($fallbackCode)) : null;
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    try {
        if ($script !== '') {
            $pdo = get_pdo();
            $stmt = $pdo->prepare('SELECT module_code FROM modules WHERE link = :link LIMIT 1');
            $stmt->execute([':link' => $script]);
            $row = $stmt->fetch();

            if ($row && isset($row['module_code'])) {
                return strtoupper((string) $row['module_code']);
            }
        }
    } catch (Throwable $e) {
        error_log('Failed to resolve module code: ' . $e->getMessage());
    }

    return $normalizedFallback;
}

/**
 * Map an incoming action value to a CRUD permission and validate access.
 */
function enforce_action_permission(array $user, string $moduleCode, string $action, array $actionCrudMap): ?string
{
    if ($action === '') {
        return null;
    }

    $normalizedAction = strtolower($action);
    $crudAction = $actionCrudMap[$normalizedAction] ?? null;

    if ($crudAction === null) {
        return null;
    }

    $resolvedModule = resolve_module_code($moduleCode);
    if ($resolvedModule === null) {
        return 'Module permissions are not configured.';
    }

    if (!has_crud_permission($user, $resolvedModule, $crudAction)) {
        return "You don't have permission to {$crudAction} this module.";
    }

    return null;
}

function permission_denied_modal(): array
{
    return [
        'title' => 'Request the administrator for the access',
        'subtitle' => "You don't have the access for this action.",
    ];
}