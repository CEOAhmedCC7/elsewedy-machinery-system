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

    return $user;
}