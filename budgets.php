<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();

$pdo = get_pdo();

$projects = fetch_table('projects', 'project_id');
$batches = fetch_table('batches', 'batch_id');
$subBatchDetails = fetch_table('sub_batch_details', 'sub_batch_detail_id');
$businessLines = fetch_table('business_lines', 'business_line_id');

$projectLookup = [];
foreach ($projects as $project) {
    $projectLookup[$project['project_id']] = $project;
}

$batchLookup = [];
foreach ($batches as $batch) {
    $batchLookup[$batch['batch_id']] = $batch;
}

$subBatchLookup = [];
foreach ($subBatchDetails as $detail) {
    $subBatchLookup[$detail['sub_batch_detail_id']] = $detail;
}


$projectOptions = to_options($projects, 'project_id', 'project_name');

$batchOptions = array_map(
    static function (array $batch) use ($projectLookup): array {
        $projectName = $projectLookup[$batch['project_id']]['project_name'] ?? '';
        $labelParts = array_filter([$projectName, $batch['batch_name'] ?? ''], static fn ($value) => $value !== '');
        $label = $labelParts ? implode(' • ', $labelParts) : ($batch['batch_name'] ?? (string) $batch['batch_id']);

        return [
            'value' => $batch['batch_id'],
            'label' => $label,
        ];
    },
    $batches
);

$subBatchOptions = array_map(
    static function (array $detail) use ($batchLookup, $projectLookup): array {
        $batch = $batchLookup[$detail['batch_id']] ?? [];
        $projectName = $projectLookup[$batch['project_id'] ?? '']['project_name'] ?? '';
        $batchName = $batch['batch_name'] ?? '';
        $labelParts = array_filter([$projectName, $batchName, $detail['sub_batch_name'] ?? ''], static fn ($value) => $value !== '');
        $label = $labelParts ? implode(' • ', $labelParts) : ($detail['sub_batch_name'] ?? (string) $detail['sub_batch_detail_id']);

        return [
            'value' => $detail['sub_batch_detail_id'],
            'label' => $label,
        ];
    },
    $subBatchDetails
);

function option_label(array $options, string $value): string
{
    foreach ($options as $option) {
        if ((string) ($option['value'] ?? '') === $value) {
            return (string) ($option['label'] ?? $value);
        }
    }

    return $value;
}

function normalize_items_from_request(array $source): array
{
    $ids = (array) ($source['item_id'] ?? []);
    $names = (array) ($source['item_name'] ?? []);
    $descriptions = (array) ($source['item_description'] ?? []);
    $quantities = (array) ($source['item_qty'] ?? []);
    $sellingPrices = (array) ($source['item_selling_price'] ?? []);
    $costTypes = (array) ($source['item_cost_type'] ?? []);
    $revenueAmounts = (array) ($source['item_revenue_amount'] ?? []);
    $revenueCurrencies = (array) ($source['item_revenue_currency'] ?? []);
    $revenueRates = (array) ($source['item_revenue_exchange_rate'] ?? []);
    $freightAmounts = (array) ($source['item_freight_amount'] ?? []);
    $freightCurrencies = (array) ($source['item_freight_currency'] ?? []);
    $freightRates = (array) ($source['item_freight_exchange_rate'] ?? []);
    $supplierAmounts = (array) ($source['item_supplier_cost_amount'] ?? []);
    $supplierCurrencies = (array) ($source['item_supplier_cost_currency'] ?? []);
     $supplierRates = (array) ($source['item_supplier_cost_exchange_rate'] ?? []);


    $items = [];
    foreach ($descriptions as $index => $description) {
        $cleanDescription = trim((string) $description);
        $itemName = trim((string) ($names[$index] ?? ''));
        $quantity = trim((string) ($quantities[$index] ?? ''));
        $sellingPrice = trim((string) ($sellingPrices[$index] ?? ''));
        $itemId = trim((string) ($ids[$index] ?? ''));
        $costType = trim((string) ($costTypes[$index] ?? ''));
        $revenueAmount = trim((string) ($revenueAmounts[$index] ?? ''));
        $revenueCurrency = trim((string) ($revenueCurrencies[$index] ?? ''));
        $revenueRate = trim((string) ($revenueRates[$index] ?? ''));
        $freightAmount = trim((string) ($freightAmounts[$index] ?? ''));
        $freightCurrency = trim((string) ($freightCurrencies[$index] ?? ''));
        $freightRate = trim((string) ($freightRates[$index] ?? ''));
       $supplierAmount = trim((string) ($supplierAmounts[$index] ?? ''));
        $supplierCurrency = trim((string) ($supplierCurrencies[$index] ?? ''));
        $supplierRate = trim((string) ($supplierRates[$index] ?? ''));

       $hasValues = $cleanDescription !== ''
            || $itemName !== ''
            || $quantity !== ''
            || $sellingPrice !== ''
            || $costType !== ''
            || $revenueAmount !== ''
            || $freightAmount !== ''
            || $supplierAmount !== '';

        if (!$hasValues && $itemId === '') {
            continue;
        }

        $items[] = [
           'item_id' => $itemId,
            'item_name' => $itemName,
            'description' => $cleanDescription,
            'qty' => $quantity,
            'selling_price' => $sellingPrice,
            'costtype' => $costType,
            'revenue_amount' => $revenueAmount,
            'revenue_currency' => $revenueCurrency ?: 'EGP',
            'revenue_exchange_rate' => $revenueRate,
            'freight_amount' => $freightAmount,
            'freight_currency' => $freightCurrency ?: 'EGP',
            'freight_exchange_rate' => $freightRate,
            'supplier_cost_amount' => $supplierAmount,
            'supplier_cost_currency' => $supplierCurrency ?: 'EGP',
            'supplier_cost_exchange_rate' => $supplierRate,
        ];
    }

    return $items;
}

$error = '';
$success = '';

$costTypes = ['Materials', 'Freight', 'Customs', 'Services', 'Other'];
$currencyOptions = ['EGP', 'USD', 'EUR'];

$submitted = [
    'budget_id' => trim($_POST['budget_id'] ?? ''),
    'scope' => $_POST['budget_scope'] ?? 'project',
    'project_id' => trim($_POST['project_id'] ?? ''),
    'batch_id' => trim($_POST['batch_id'] ?? ''),
    'sub_batch_detail_id' => trim($_POST['sub_batch_detail_id'] ?? ''),
];

$selectedIds = array_filter(array_map('trim', (array) ($_POST['selected_ids'] ?? [])));
$submittedItems = normalize_items_from_request($_POST);
$deleteItems = array_filter(array_map('trim', (array) ($_POST['delete_items'] ?? [])));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $linkProject = $submitted['scope'] === 'project' ? $submitted['project_id'] : '';
    $linkBatch = $submitted['scope'] === 'batch' ? $submitted['batch_id'] : '';
    $linkSubBatch = $submitted['scope'] === 'sub-batch' ? $submitted['sub_batch_detail_id'] : '';

    if ($linkSubBatch !== '' && $linkBatch === '') {
        $linkBatch = (string) ($subBatchLookup[$linkSubBatch]['batch_id'] ?? '');
    }

    try {
        if ($action === 'create') {
            if ($linkProject === '' && $linkBatch === '' && $linkSubBatch === '') {
                $error = 'Select a project, batch, or sub-batch for this budget.';
            } else {
                $budgetId = null;

                if ($submitted['budget_id'] !== '') {
                    if (!ctype_digit($submitted['budget_id'])) {
                        $error = 'Budget ID must be a number.';
                    } else {
                        $budgetId = $submitted['budget_id'];
                    }
                }

                if ($error === '' && $budgetId !== null) {
                    $exists = $pdo->prepare('SELECT 1 FROM budgets WHERE budget_id = :id');
                    $exists->execute([':id' => $budgetId]);

                    if ($exists->fetchColumn()) {
                        $error = 'A budget with this ID already exists.';
                    }
                }

                if ($error === '') {
                    $columns = [
                        'project_id',
                        'batch_id',
                        'sub_batch_detail_id',
                    ];

                    $placeholders = [
                        ':project',
                        ':batch',
                        ':sub_batch',
                    ];

                    $params = [
                        ':project' => $linkProject !== '' ? $linkProject : null,
                        ':batch' => $linkBatch !== '' ? $linkBatch : null,
                        ':sub_batch' => $linkSubBatch !== '' ? $linkSubBatch : null,
                    ];

                    if ($budgetId !== null) {
                        array_unshift($columns, 'budget_id');
                        array_unshift($placeholders, ':id');
                        $params[':id'] = $budgetId;
                    }

                    $columnsSql = implode(', ', $columns);
                    $placeholdersSql = implode(', ', $placeholders);

                    $stmt = $pdo->prepare("INSERT INTO budgets ({$columnsSql}) VALUES ({$placeholdersSql})");
                    $stmt->execute($params);

                    $budgetId = $budgetId ?? $pdo->lastInsertId('budgets_budget_id_seq');

                    if ($submittedItems) {
                        $itemInsert = $pdo->prepare('INSERT INTO items (budget_id, item_name, description, qty, selling_price,costtype, revenue_amount, revenue_currency, revenue_exchange_rate, freight_amount, freight_currency, freight_exchange_rate, supplier_cost_amount, supplier_cost_currency, supplier_cost_exchange_rate) VALUES (:budget_id, :item_name, :description, :qty, :selling_price, :costtype, :rev_amount, :rev_currency, :rev_rate, :freight_amount, :freight_currency, :freight_rate, :supplier_amount, :supplier_currency, :supplier_rate)');

                        foreach ($submittedItems as $item) {
                            $itemInsert->execute([
                                ':budget_id' => $budgetId,
                                ':item_name' => $item['item_name'] ?: null,
                                ':description' => $item['description'] ?: null,
                                ':qty' => $item['qty'] !== '' ? $item['qty'] : null,
                                ':selling_price' => $item['selling_price'] !== '' ? $item['selling_price'] : null,
                                ':costtype' => $item['costtype'] ?: null,
                                ':rev_amount' => $item['revenue_amount'] !== '' ? $item['revenue_amount'] : null,
                                ':rev_currency' => $item['revenue_currency'] ?: null,
                                ':rev_rate' => $item['revenue_exchange_rate'] !== '' ? $item['revenue_exchange_rate'] : null,
                                ':freight_amount' => $item['freight_amount'] !== '' ? $item['freight_amount'] : null,
                                ':freight_currency' => $item['freight_currency'] ?: null,
                                ':freight_rate' => $item['freight_exchange_rate'] !== '' ? $item['freight_exchange_rate'] : null,
                                ':supplier_amount' => $item['supplier_cost_amount'] !== '' ? $item['supplier_cost_amount'] : null,
                                ':supplier_currency' => $item['supplier_cost_currency'] ?: null,
                                ':supplier_rate' => $item['supplier_cost_exchange_rate'] !== '' ? $item['supplier_cost_exchange_rate'] : null,
                            ]);
                        }
                    }

                    $success = 'Budget saved successfully.';
                    $submitted = array_merge($submitted, [
                        'budget_id' => '',
                        'project_id' => '',
                        'batch_id' => '',
                        'sub_batch_detail_id' => '',
                    ]);
                    $submittedItems = [];
                }
            }
        } elseif ($action === 'update') {
            if ($submitted['budget_id'] === '') {
                $error = 'Enter the Budget ID to update.';
            } elseif ($linkProject === '' && $linkBatch === '' && $linkSubBatch === '') {
                $error = 'Select a project, batch, or sub-batch for this budget.';
            } else {
                $stmt = $pdo->prepare('UPDATE budgets SET project_id = :project, batch_id = :batch, sub_batch_detail_id = :sub_batch WHERE budget_id = :id');
                $stmt->execute([
                    ':id' => $submitted['budget_id'],
                    ':project' => $linkProject !== '' ? $linkProject : null,
                    ':batch' => $linkBatch !== '' ? $linkBatch : null,
                    ':sub_batch' => $linkSubBatch !== '' ? $linkSubBatch : null,
                ]);


                if ($stmt->rowCount() === 0) {
                    $error = 'Budget not found.';
                } else {
                    if ($deleteItems) {
                        $placeholders = implode(',', array_fill(0, count($deleteItems), '?'));
                        $deleteStmt = $pdo->prepare("DELETE FROM items WHERE budget_id = ? AND item_id IN ({$placeholders})");
                        $deleteStmt->execute(array_merge([$submitted['budget_id']], $deleteItems));
                    }

                    if ($submittedItems) {
                        $updateStmt = $pdo->prepare('UPDATE items SET item_name = :item_name, description = :description, qty = :qty, selling_price = :selling_price, costtype = :costtype, revenue_amount = :rev_amount, revenue_currency = :rev_currency, revenue_exchange_rate = :rev_rate, freight_amount = :freight_amount, freight_currency = :freight_currency, freight_exchange_rate = :freight_rate, supplier_cost_amount = :supplier_amount, supplier_cost_currency = :supplier_currency, supplier_cost_exchange_rate = :supplier_rate WHERE item_id = :id AND budget_id = :budget_id');
                        $insertStmt = $pdo->prepare('INSERT INTO items (budget_id, item_name, description, qty, selling_price, costtype, revenue_amount, revenue_currency, revenue_exchange_rate, freight_amount, freight_currency, freight_exchange_rate, supplier_cost_amount, supplier_cost_currency, supplier_cost_exchange_rate) VALUES (:budget_id, :item_name, :description, :qty, :selling_price, :costtype, :rev_amount, :rev_currency, :rev_rate, :freight_amount, :freight_currency, :freight_rate, :supplier_amount, :supplier_currency, :supplier_rate)');
                        foreach ($submittedItems as $item) {
                            if ($item['item_id'] !== '') {
                                if (in_array($item['item_id'], $deleteItems, true)) {
                                    continue;
                                }


                                $updateStmt->execute([
                                    ':id' => $item['item_id'],
                                     ':budget_id' => $submitted['budget_id'],
                                    ':item_name' => $item['item_name'] ?: null,
                                    ':description' => $item['description'] ?: null,
                                    ':qty' => $item['qty'] !== '' ? $item['qty'] : null,
                                    ':selling_price' => $item['selling_price'] !== '' ? $item['selling_price'] : null,
                                    ':costtype' => $item['costtype'] ?: null,
                                    ':rev_amount' => $item['revenue_amount'] !== '' ? $item['revenue_amount'] : null,
                                    ':rev_currency' => $item['revenue_currency'] ?: null,
                                    ':rev_rate' => $item['revenue_exchange_rate'] !== '' ? $item['revenue_exchange_rate'] : null,
                                    ':freight_amount' => $item['freight_amount'] !== '' ? $item['freight_amount'] : null,
                                    ':freight_currency' => $item['freight_currency'] ?: null,
                                    ':freight_rate' => $item['freight_exchange_rate'] !== '' ? $item['freight_exchange_rate'] : null,
                                    ':supplier_amount' => $item['supplier_cost_amount'] !== '' ? $item['supplier_cost_amount'] : null,
                                    ':supplier_currency' => $item['supplier_cost_currency'] ?: null,
                                    ':supplier_rate' => $item['supplier_cost_exchange_rate'] !== '' ? $item['supplier_cost_exchange_rate'] : null,
                                ]);

                            } else {
                                $insertStmt->execute([
                                    ':budget_id' => $submitted['budget_id'],
                                    ':item_name' => $item['item_name'] ?: null,
                                    ':description' => $item['description'] ?: null,
                                    ':qty' => $item['qty'] !== '' ? $item['qty'] : null,
                                    ':selling_price' => $item['selling_price'] !== '' ? $item['selling_price'] : null,
                                    ':costtype' => $item['costtype'] ?: null,
                                    ':rev_amount' => $item['revenue_amount'] !== '' ? $item['revenue_amount'] : null,
                                    ':rev_currency' => $item['revenue_currency'] ?: null,
                                    ':rev_rate' => $item['revenue_exchange_rate'] !== '' ? $item['revenue_exchange_rate'] : null,
                                    ':freight_amount' => $item['freight_amount'] !== '' ? $item['freight_amount'] : null,
                                    ':freight_currency' => $item['freight_currency'] ?: null,
                                    ':freight_rate' => $item['freight_exchange_rate'] !== '' ? $item['freight_exchange_rate'] : null,
                                    ':supplier_amount' => $item['supplier_cost_amount'] !== '' ? $item['supplier_cost_amount'] : null,
                                    ':supplier_currency' => $item['supplier_cost_currency'] ?: null,
                                    ':supplier_rate' => $item['supplier_cost_exchange_rate'] !== '' ? $item['supplier_cost_exchange_rate'] : null,
                                ]);
                            }
                        }
                    }

                    $success = 'Budget updated successfully.';
                }
            }
        } elseif ($action === 'delete') {
            if ($submitted['budget_id'] === '') {
                $error = 'Enter a Budget ID to delete.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM budgets WHERE budget_id = :id');
                $stmt->execute([':id' => $submitted['budget_id']]);

                if ($stmt->rowCount() === 0) {
                    $error = 'Budget not found or already deleted.';
                } else {
                   $success = 'Budget deleted successfully.';
                    $submitted = array_merge($submitted, [
                        'budget_id' => '',
                        'project_id' => '',
                        'batch_id' => '',
                        'sub_batch_detail_id' => '',
                    ]);
                }
            }
        } elseif ($action === 'bulk_delete') {
            if (!$selectedIds) {
                $error = 'Select at least one budget to delete.';
            } else {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM budgets WHERE budget_id IN ({$placeholders})");
                $stmt->execute($selectedIds);
                $deleted = $stmt->rowCount();
                $success = $deleted . ' budget(s) removed.';
            }
        }
    } catch (Throwable $e) {
        $error = format_db_error($e, 'budgets table');
    }
}

$filters = [
    'project_id' => trim($_GET['filter_project_id'] ?? ''),
    'batch_id' => trim($_GET['filter_batch_id'] ?? ''),
    'sub_batch_detail_id' => trim($_GET['filter_sub_batch_detail_id'] ?? ''),
    'business_line_id' => trim($_GET['filter_business_line_id'] ?? ''),
];

$budgets = [];

if ($pdo) {
    try {
        $conditions = [];
        $params = [];

        if ($filters['project_id'] !== '') {
            $conditions[] = '(p.project_id = :filter_project OR bp.project_id = :filter_project)';
            $params[':filter_project'] = $filters['project_id'];
        }

        if ($filters['batch_id'] !== '') {
            $conditions[] = 'bat.batch_id = :filter_batch';
            $params[':filter_batch'] = $filters['batch_id'];
        }

        if ($filters['sub_batch_detail_id'] !== '') {
            $conditions[] = 'sb.sub_batch_detail_id = :filter_sub_batch';
            $params[':filter_sub_batch'] = $filters['sub_batch_detail_id'];
        }

        if ($filters['business_line_id'] !== '') {
            $conditions[] = 'COALESCE(p.business_line_id, bp.business_line_id) = :filter_business';
            $params[':filter_business'] = $filters['business_line_id'];
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

       $sql = "SELECT b.*, p.project_name, p.business_line_id, bl.business_line_name, sb.sub_batch_name, bat.batch_id, bat.batch_name, bp.project_name AS batch_project_name, bp.business_line_id AS batch_business_line_id, COALESCE(items.item_count, 0) AS item_count FROM budgets b LEFT JOIN projects p ON p.project_id = b.project_id LEFT JOIN sub_batch_details sb ON sb.sub_batch_detail_id = b.sub_batch_detail_id LEFT JOIN batches bat ON bat.batch_id = COALESCE(b.batch_id, sb.batch_id) LEFT JOIN projects bp ON bp.project_id = bat.project_id LEFT JOIN business_lines bl ON bl.business_line_id = COALESCE(p.business_line_id, bp.business_line_id) LEFT JOIN (SELECT budget_id, COUNT(*) AS item_count FROM items GROUP BY budget_id) items ON items.budget_id = b.budget_id {$whereSql} ORDER BY b.created_at DESC, b.budget_id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $budgets = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error = $error ?: format_db_error($e, 'budgets table');
    }
}

$itemsByBudget = [];

if ($pdo) {
    try {
        $stmt = $pdo->query('SELECT * FROM items ORDER BY item_id');
        foreach ($stmt->fetchAll() as $item) {
            $budgetId = $item['budget_id'] ?? null;
            if ($budgetId === null) {
                continue;
            }
            $itemsByBudget[$budgetId][] = $item;
        }
    } catch (Throwable $e) {
        $error = $error ?: format_db_error($e, 'items table');
    }
}

$budgetIdOptions = array_column($budgets, 'budget_id');
$businessLineOptions = to_options($businessLines, 'business_line_id', 'business_line_name');

$batchDataForJs = array_map(
    static function (array $batch) use ($projectLookup): array {
        return [
            'batch_id' => (string) ($batch['batch_id'] ?? ''),
            'batch_name' => $batch['batch_name'] ?? '',
            'project_id' => (string) ($batch['project_id'] ?? ''),
            'project_name' => $projectLookup[$batch['project_id']]['project_name'] ?? '',
        ];
    },
    $batches
);

$subBatchDataForJs = array_map(
    static function (array $detail) use ($batchLookup, $projectLookup): array {
        $batch = $batchLookup[$detail['batch_id']] ?? [];

        return [
            'sub_batch_detail_id' => (string) ($detail['sub_batch_detail_id'] ?? ''),
            'sub_batch_name' => $detail['sub_batch_name'] ?? '',
            'batch_id' => (string) ($detail['batch_id'] ?? ''),
            'batch_name' => $batch['batch_name'] ?? '',
            'project_id' => (string) ($batch['project_id'] ?? ''),
            'project_name' => $projectLookup[$batch['project_id']]['project_name'] ?? '',
        ];
    },
    $subBatchDetails
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Budgets | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
  <style>
    .budget-grid {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }

    .budget-card {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      background: linear-gradient(135deg, #0b8dc0, #0f4b8c);
      color: #fff;
      border-radius: 10px;
      padding: 14px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      min-height: 160px;
      position: relative;
      text-decoration: none;
    }

    .budget-card:hover,
    .budget-card:focus-visible {
      transform: translateY(-2px);
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
      outline: none;
    }

    .budget-card h4,
    .budget-card p,
    .budget-card small {
      color: #fff;
      margin: 0;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .budget-card__footer {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }

    .budget-card__footer .btn,
    .budget-card__footer a.btn {
      flex: 1;
      text-align: center;
    }

    .budget-card__select {
      position: absolute;
      top: 10px;
      right: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px;
      border-radius: 10px;
    }

    .message-dialog.is-wide {
      width: min(95vw, 1200px);
      max-width: 1900px;
    }

    .budget-status {
      position: absolute;
      top: 0;
      left: 0;
      padding: 8px 12px;
      border-radius: 0 0 8px 0;
      background: rgba(255, 255, 255, 0.14);
      color: #fff;
      font-weight: 700;
      letter-spacing: 0.3px;
      text-transform: uppercase;
      font-size: 12px;
    }

    .budget-card__select input[type="checkbox"] {
      appearance: none;
      width: 18px;
      height: 18px;
      border: 2px solid #fff;
      border-radius: 6px;
      background: transparent;
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: background-color 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
    }

    .budget-card__select input[type="checkbox"]:checked {
      background: var(--secondary);
      border-color: var(--secondary);
      box-shadow: inset 0 0 0 2px #ffffffff;
    }

    .budget-card__select input[type="checkbox"]:focus-visible {
      outline: 2px solid #fff;
      outline-offset: 2px;
    }

     .budget-modal .message-dialog {
      width: 90vw;
      max-width: 90vw;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
    }

    .budget-modal form {
      overflow-y: auto;
      max-height: calc(90vh - 80px);
      padding-right: 4px;
    }


    .budget-form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }

    .message-table__wrapper {
      margin-top: 8px;
      overflow-x: auto;
    }

    .message-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 6px;
      overflow: hidden;
    }

    .message-table th,
    .message-table td {
      padding: 8px 10px;
      border-bottom: 1px solid var(--border);
    }

    .message-table th {
      width: 40%;
      text-align: left;
      background: rgba(0, 0, 0, 0.03);
      font-weight: 600;
      color: var(--secondary);
    }

    .message-table tr:last-child th,
    .message-table tr:last-child td {
      border-bottom: none;
    }

    .item-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid var(--border);
      background: var(--surface);
      border-radius: 6px;
      overflow: hidden;
    }

    .item-table th,
    .item-table td {
      padding: 8px 10px;
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--border);
    }

    .item-table th:last-child,
    .item-table td:last-child {
      border-right: none;
    }

    .item-table thead th {
      background: rgba(0, 0, 0, 0.03);
      color: var(--secondary);
      text-align: left;
    }

    .item-table__actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
      margin-top: 6px;
    }

    .item-table__actions input[type="number"] {
      width: 100px;
    }

    .item-table input[type="text"],
    .item-table input[type="number"],
    .item-table select {
      width: 100%;
      box-sizing: border-box;
    }
  </style>
  <script src="./assets/app.js" defer></script>
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
    </div>
    <div class="title">Budgets</div>
    <div class="links">
      <div class="user-chip">
        <span class="name"><?php echo safe($currentUser['username']); ?></span>
        <span class="role"><?php echo strtoupper(safe($currentUser['role'])); ?></span>
      </div>
      <a href="./home.php">Home</a>
      <a class="logout-icon" href="./logout.php" aria-label="Logout">⎋</a>
    </div>
  </header>

  <?php if ($error !== '' || $success !== ''): ?>
    <div class="message-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Budgets notification">
      <div class="message-dialog <?php echo $error ? 'is-error' : 'is-success'; ?>">
        <div class="message-dialog__header">
          <span class="message-title"><?php echo $error ? 'Action needed' : 'Success'; ?></span>
          <button class="message-close" type="button" aria-label="Close message" data-close-modal>&times;</button>
        </div>
        <div class="message-body"><?php echo safe($error ?: $success); ?></div>
      </div>
    </div>
  <?php endif; ?>

  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container" style="display:grid; gap:16px;">
       <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
          <h3 style="margin:0; color:var(--secondary);">Create, view, update or delete budgets</h3>
          <p style="margin:6px 0 0; color:var(--muted);">Use the create button to add budgets, then manage or review them from the cards below.</p>
        </div>
        <button class="btn btn-save" type="button" data-open-create style="white-space:nowrap;">Create budget</button>
      </div>

      <form method="GET" action="budgets.php" class="filter-form" style="display:grid; gap:10px;">
        <div class="grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:10px;">
          <div>
            <label class="label" for="filter_project_id">Project</label>
            <select id="filter_project_id" name="filter_project_id">
              <option value="">-- Any project --</option>
              <?php foreach ($projectOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $filters['project_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="filter_batch_id">Batch</label>
            <select id="filter_batch_id" name="filter_batch_id">
              <option value="">-- Any batch --</option>
              <?php foreach ($batchOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $filters['batch_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="filter_sub_batch_detail_id">Sub-batch</label>
            <select id="filter_sub_batch_detail_id" name="filter_sub_batch_detail_id">
              <option value="">-- Any sub-batch --</option>
              <?php foreach ($subBatchOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $filters['sub_batch_detail_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="filter_business_line_id">Business line</label>
            <select id="filter_business_line_id" name="filter_business_line_id">
              <option value="">-- Any business line --</option>
              <?php foreach ($businessLineOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $filters['business_line_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="actions" style="justify-content:flex-start; gap:10px; flex-wrap:wrap;">
          <button class="btn btn-update" type="submit">Apply filters</button>
          <a class="btn" href="budgets.php">Clear filters</a>
        </div>
      </form>

      <?php if (!$budgets): ?>
        <div class="alert" style="margin:0;">No budgets recorded yet. Click "Create budget" to add one.</div>
      <?php else: ?>
        <form method="POST" action="budgets.php" style="display:grid; gap:10px;">
          <input type="hidden" name="action" value="bulk_delete" />
          <div class="actions" style="justify-content:flex-start; gap:10px; flex-wrap:wrap;">
            <button class="btn btn-delete" type="submit" onclick="return confirm('Delete selected budgets?');">Delete selected</button>
          </div>
          <div class="budget-grid">
            <?php foreach ($budgets as $budget): ?>

             <?php
               $scope = $budget['sub_batch_detail_id'] ? 'Sub-batch' : ($budget['batch_id'] ? 'Batch' : 'Project');
                $projectName = $budget['project_name'] ?: ($budget['batch_project_name'] ?? '');
                $batchName = $budget['batch_name'] ?? '';
                $subBatchName = $budget['sub_batch_name'] ?? '';
                $budgetTitle = $subBatchName ?: ($batchName ?: ($projectName ?: ($budget['budget_id'] ?? 'Budget')));
                $createdAt = $budget['created_at'] ?? '';
              ?>
              <div class="module-card module-card--no-image budget-card" tabindex="0">
                <span class="budget-status" aria-label="Budget scope"><?php echo safe($scope); ?></span>
                <label class="budget-card__select" title="Select budget" aria-label="Select budget">
                  <input type="checkbox" name="selected_ids[]" value="<?php echo safe($budget['budget_id']); ?>" />
                </label>
                <div class="module-card__body" style="display:grid; gap:6px; align-content:start;">
                  <h4 style="margin:0; font-weight:700;"><?php echo safe($budgetTitle); ?></h4>
                  <?php if ($projectName !== ''): ?>
                    <p class="budget-meta"><small>Project: <?php echo safe($projectName); ?></small></p>
                  <?php endif; ?>
                  <?php if ($batchName !== ''): ?>
                    <p class="budget-meta"><small>Batch: <?php echo safe($batchName); ?></small></p>
                  <?php endif; ?>
                  <?php if ($subBatchName !== ''): ?>
                    <p class="budget-meta"><small>Sub-batch: <?php echo safe($subBatchName); ?></small></p>
                  <?php endif; ?>
                  <p class="budget-meta"><small>Items: <?php echo (int) ($budget['item_count'] ?? 0); ?></small></p>
                  <p class="budget-meta"><small>Created at: <?php echo safe($createdAt ?: '—'); ?></small></p>
                </div>
                <div class="budget-card__footer">
                  <button class="btn btn-update" type="button" data-open-manage="<?php echo safe($budget['budget_id']); ?>">Manage</button>
                  <button class="btn btn-neutral" type="button" data-open-details="<?php echo safe($budget['budget_id']); ?>">View details</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </form>
      <?php endif; ?>

      <?php foreach ($budgets as $budget): ?>
        <div class="message-modal budget-modal" data-manage-modal="<?php echo safe($budget['budget_id']); ?>" role="dialog" aria-modal="true" aria-label="Manage budget <?php echo safe($budget['budget_id']); ?>">
          <div class="message-dialog is-wide">
            <div class="message-dialog__header">
              <span class="message-title">Manage budget <?php echo safe($budget['budget_id']); ?></span>
              <button class="message-close" type="button" aria-label="Close manage budget" data-close-modal>&times;</button>
            </div>
            <form method="POST" action="budgets.php" style="display:grid; gap:12px;">
              <input type="hidden" name="action" value="update" />
              <input type="hidden" name="budget_id" value="<?php echo safe($budget['budget_id']); ?>" />
              <div class="budget-form-grid">
                 <div>
                  <label class="label">Scope</label>
                  <div style="display:flex; gap:10px; align-items:center;">
                    <label><input type="radio" name="budget_scope" value="project" <?php echo $budget['project_id'] ? 'checked' : ''; ?> /> Project</label>
                    <label><input type="radio" name="budget_scope" value="batch" <?php echo $budget['batch_id'] && !$budget['sub_batch_detail_id'] ? 'checked' : ''; ?> /> Batch</label>
                    <label><input type="radio" name="budget_scope" value="sub-batch" <?php echo $budget['sub_batch_detail_id'] ? 'checked' : ''; ?> /> Sub-Batch Detail</label>
                  </div>
                </div>
                <div>
                  <label class="label" for="project-<?php echo safe($budget['budget_id']); ?>">Project</label>
                  <select id="project-<?php echo safe($budget['budget_id']); ?>" name="project_id" data-project-select data-selected-value="<?php echo safe($budget['project_id']); ?>">
                    <option value="">-- Select Project --</option>
                    <?php foreach ($projectOptions as $option): ?>
                      <option value="<?php echo safe($option['value']); ?>" <?php echo $budget['project_id'] == $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="label" for="batch-<?php echo safe($budget['budget_id']); ?>">Batch</label>
                  <select id="batch-<?php echo safe($budget['budget_id']); ?>" name="batch_id" data-batch-select data-selected-value="<?php echo safe($budget['batch_id']); ?>">
                    <option value="">-- Select Batch --</option>
                    <?php foreach ($batchOptions as $option): ?>
                      <option value="<?php echo safe($option['value']); ?>" <?php echo $budget['batch_id'] == $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="label" for="sub-batch-<?php echo safe($budget['budget_id']); ?>">Sub-Batch Detail</label>
                  <select id="sub-batch-<?php echo safe($budget['budget_id']); ?>" name="sub_batch_detail_id" data-sub-batch-select data-selected-value="<?php echo safe($budget['sub_batch_detail_id']); ?>">
                    <option value="">-- Select Sub-Batch --</option>
                    <?php foreach ($subBatchOptions as $option): ?>
                      <option value="<?php echo safe($option['value']); ?>" <?php echo $budget['sub_batch_detail_id'] == $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <?php $budgetItems = $itemsByBudget[$budget['budget_id']] ?? []; ?>
             <div data-item-section>
                <h4 style="margin:0 0 6px;">Budget items</h4>
                <div class="item-table__actions" style="margin-bottom:6px;">
                  <label for="item-count-<?php echo safe($budget['budget_id']); ?>">How many items?</label>
                  <input id="item-count-<?php echo safe($budget['budget_id']); ?>" type="number" min="0" value="<?php echo max(1, count($budgetItems)); ?>" data-item-count />
                  <button class="btn btn-neutral" type="button" data-add-item-row>Add new item</button>
                  <button class="btn btn-delete" type="button" data-delete-selected>Delete selected</button>
                </div>
                <div class="message-table__wrapper">
                 <table class="item-table" data-item-table data-item-context="manage-<?php echo safe($budget['budget_id']); ?>">
                    <thead>
                      <tr>
                        <th style="width:36px;">Select</th>
                        <th>Qty</th>
                        <th>Item</th>
                        <th>Selling price</th>
                        <th>Cost type</th>
                        <th>Revenue</th>
                        <th>Freight</th>
                        <th>Supplier</th>
                      </tr>
                    </thead>
                    <tbody data-item-body>
                     <?php if (!$budgetItems): ?>
                        <tr data-item-row>
                          <td><input type="checkbox" class="item-select" /></td>
                          <td>
                            <input type="number" step="1" min="0" name="item_qty[]" placeholder="Qty" />
                          </td>
                          <td>
                            <input type="hidden" name="item_id[]" value="" />
                            <input type="text" name="item_name[]" placeholder="Item name" />
                            <input type="text" name="item_description[]" placeholder="Item description" />
                          </td>
                          <td>
                            <input type="number" step="0.01" name="item_selling_price[]" placeholder="Selling price" />
                          </td>
                          <td>
                            <select name="item_cost_type[]">
                              <option value="">-- Cost type --</option>
                              <?php foreach ($costTypes as $type): ?>
                                <option value="<?php echo safe($type); ?>"><?php echo safe($type); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </td>
                          <td>
                            <input type="number" step="0.01" name="item_revenue_amount[]" placeholder="Amount" />
                            <select name="item_revenue_currency[]">
                              <?php foreach ($currencyOptions as $currency): ?>
                                <option value="<?php echo $currency; ?>"><?php echo $currency; ?></option>
                              <?php endforeach; ?>
                            </select>
                            <input type="number" step="0.0001" name="item_revenue_exchange_rate[]" placeholder="Rate" />
                          </td>
                          <td>
                            <input type="number" step="0.01" name="item_freight_amount[]" placeholder="Amount" />
                            <select name="item_freight_currency[]">
                              <?php foreach ($currencyOptions as $currency): ?>
                                <option value="<?php echo $currency; ?>"><?php echo $currency; ?></option>
                              <?php endforeach; ?>
                            </select>
                            <input type="number" step="0.0001" name="item_freight_exchange_rate[]" placeholder="Rate" />
                          </td>
                          <td>
                            <input type="number" step="0.01" name="item_supplier_cost_amount[]" placeholder="Amount" />
                            <select name="item_supplier_cost_currency[]">
                              <?php foreach ($currencyOptions as $currency): ?>
                                <option value="<?php echo $currency; ?>"><?php echo $currency; ?></option>
                              <?php endforeach; ?>
                            </select>
                            <input type="number" step="0.0001" name="item_supplier_cost_exchange_rate[]" placeholder="Rate" />
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($budgetItems as $item): ?>
                         <tr data-item-row>
                            <td>
                              <input type="checkbox" class="item-select" value="<?php echo safe($item['item_id']); ?>" />
                              <input type="hidden" name="item_id[]" value="<?php echo safe($item['item_id']); ?>" />
                            </td>
                            <td>
                              <input type="number" step="1" min="0" name="item_qty[]" value="<?php echo safe($item['qty'] ?? ''); ?>" placeholder="Qty" />
                            </td>
                           <td>
                            <input type="text" name="item_name[]" value="<?php echo safe($item['item_name'] ?? ''); ?>" placeholder="Item name" />
                            <input type="text" name="item_description[]" value="<?php echo safe($item['description'] ?? ''); ?>" placeholder="Item description" />
                          </td>
                          <td>
                            <input type="number" step="0.01" name="item_selling_price[]" value="<?php echo safe($item['selling_price'] ?? ''); ?>" placeholder="Selling price" />
                          </td>
                          <td>
                            <select name="item_cost_type[]">
                              <option value="">-- Cost type --</option>
                              <?php foreach ($costTypes as $type): ?>
                                  <option value="<?php echo safe($type); ?>" <?php echo ($item['costtype'] ?? '') === $type ? 'selected' : ''; ?>><?php echo safe($type); ?></option>
                                <?php endforeach; ?>
                              </select>
                            </td>
                            <td>
                              <input type="number" step="0.01" name="item_revenue_amount[]" value="<?php echo safe($item['revenue_amount'] ?? ''); ?>" placeholder="Amount" />
                              <select name="item_revenue_currency[]">
                                <?php foreach ($currencyOptions as $currency): ?>
                                  <option value="<?php echo $currency; ?>" <?php echo ($item['revenue_currency'] ?? 'EGP') === $currency ? 'selected' : ''; ?>><?php echo $currency; ?></option>
                                <?php endforeach; ?>
                              </select>
                              <input type="number" step="0.0001" name="item_revenue_exchange_rate[]" value="<?php echo safe($item['revenue_exchange_rate'] ?? ''); ?>" placeholder="Rate" />
                            </td>
                            <td>
                              <input type="number" step="0.01" name="item_freight_amount[]" value="<?php echo safe($item['freight_amount'] ?? ''); ?>" placeholder="Amount" />
                              <select name="item_freight_currency[]">
                                <?php foreach ($currencyOptions as $currency): ?>
                                  <option value="<?php echo $currency; ?>" <?php echo ($item['freight_currency'] ?? 'EGP') === $currency ? 'selected' : ''; ?>><?php echo $currency; ?></option>
                                <?php endforeach; ?>
                              </select>
                              <input type="number" step="0.0001" name="item_freight_exchange_rate[]" value="<?php echo safe($item['freight_exchange_rate'] ?? ''); ?>" placeholder="Rate" />
                            </td>
                            <td>
                              <input type="number" step="0.01" name="item_supplier_cost_amount[]" value="<?php echo safe($item['supplier_cost_amount'] ?? ''); ?>" placeholder="Amount" />
                              <select name="item_supplier_cost_currency[]">
                                <?php foreach ($currencyOptions as $currency): ?>
                                  <option value="<?php echo $currency; ?>" <?php echo ($item['supplier_cost_currency'] ?? 'EGP') === $currency ? 'selected' : ''; ?>><?php echo $currency; ?></option>
                                <?php endforeach; ?>
                              </select>
                              <input type="number" step="0.0001" name="item_supplier_cost_exchange_rate[]" value="<?php echo safe($item['supplier_cost_exchange_rate'] ?? ''); ?>" placeholder="Rate" />
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                </table>
                </div>
              </div>
              <div class="actions" style="justify-content:flex-end; gap:10px;">
                <button class="btn btn-update" type="submit">Update budget</button>
              </div>
            </form>
            <form method="POST" action="budgets.php" onsubmit="return confirm('Delete this budget?');" style="display:flex; justify-content:flex-end;">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="budget_id" value="<?php echo safe($budget['budget_id']); ?>" />
              <button class="btn btn-delete" type="submit">Delete budget</button>
             </form>
          </div>
        </div>

        <div class="message-modal budget-modal" data-details-modal="<?php echo safe($budget['budget_id']); ?>" role="dialog" aria-modal="true" aria-label="Budget details for <?php echo safe($budget['budget_id']); ?>">
          <div class="message-dialog is-wide">
            <div class="message-dialog__header">
              <span class="message-title">Budget details</span>
              <button class="message-close" type="button" aria-label="Close budget details" data-close-modal>&times;</button>
            </div>
            <?php
              $scope = $budget['sub_batch_detail_id'] ? 'Sub-batch' : ($budget['batch_id'] ? 'Batch' : 'Project');
              $projectName = $budget['project_name'] ?: ($budget['batch_project_name'] ?? '');
              $batchName = $budget['batch_name'] ?? '';
              $subBatchName = $budget['sub_batch_name'] ?? '';
            ?>
            <div class="message-table__wrapper">
              <table class="message-table details-table">
                <tbody>
                  <tr>
                    <th scope="row">Budget ID</th>
                    <td><?php echo safe($budget['budget_id']); ?></td>
                  </tr>
                  <tr>
                    <th scope="row">Project</th>
                    <td><?php echo safe($projectName ?: '—'); ?></td>
                  </tr>
                  <tr>
                    <th scope="row">Batch</th>
                    <td><?php echo safe($batchName ?: '—'); ?></td>
                  </tr>
                  <tr>
                    <th scope="row">Sub-batch</th>
                    <td><?php echo safe($subBatchName ?: '—'); ?></td>
                  </tr>
                  <tr>
                    <th scope="row">Scope</th>
                    <td><?php echo safe($scope); ?></td>
                  </tr>
                  <tr>
                    <th scope="row">Business line</th>
                    <td><?php echo safe($budget['business_line_name'] ?? '—'); ?></td>
                  </tr>
                  <tr>
                    <th scope="row">Created at</th>
                    <td><?php echo safe($budget['created_at'] ?? '—'); ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <?php $detailsItems = $itemsByBudget[$budget['budget_id']] ?? []; ?>
            <?php if ($detailsItems): ?>
              <h4 style="margin:12px 0 6px;">Items (<?php echo count($detailsItems); ?>)</h4>
              <div class="message-table__wrapper">
                 <table class="item-table">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Qty</th>
                      <th>Selling price</th>
                      <th>Cost type</th>
                      <th>Revenue</th>
                      <th>Freight</th>
                      <th>Supplier</th>
                    </tr>
                  </thead>
                  <tbody>
                     <?php foreach ($detailsItems as $item): ?>
                      <tr>
                        <td>
                          <div><?php echo safe($item['item_name'] ?? '—'); ?></div>
                          <small><?php echo safe($item['description'] ?? '—'); ?></small>
                        </td>
                        <td><?php echo safe($item['qty'] ?? '—'); ?></td>
                        <td><?php echo safe($item['selling_price'] ?? '—'); ?></td>
                        <td><?php echo safe($item['costtype'] ?? '—'); ?></td>
                        <td><?php echo safe(($item['revenue_currency'] ?? '') . ' ' . ($item['revenue_amount'] ?? '')); ?></td>
                        <td><?php echo safe(($item['freight_currency'] ?? '') . ' ' . ($item['freight_amount'] ?? '')); ?></td>
                        <td><?php echo safe(($item['supplier_cost_currency'] ?? '') . ' ' . ($item['supplier_cost_amount'] ?? '')); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <div class="message-modal budget-modal" id="create-budget-modal" role="dialog" aria-modal="true" aria-label="Create budget">
    <div class="message-dialog is-wide">
      <div class="message-dialog__header">
        <span class="message-title">Create a new budget</span>
        <button class="message-close" type="button" aria-label="Close create budget" data-close-modal>&times;</button>
      </div>
      <form method="POST" action="budgets.php" style="display:grid; gap:12px;">
        <input type="hidden" name="action" value="create" />
        <div class="budget-form-grid">
          <div>
            <label class="label">Scope</label>
            <select id="budget-scope" name="budget_scope">
              <option value="project" <?php echo $submitted['scope'] === 'project' ? 'selected' : ''; ?>>Project</option>
              <option value="batch" <?php echo $submitted['scope'] === 'batch' ? 'selected' : ''; ?>>Batch</option>
              <option value="sub-batch" <?php echo $submitted['scope'] === 'sub-batch' ? 'selected' : ''; ?>>Sub-Batch Detail</option>
            </select>
          </div>
          <div>
            <label class="label" for="budget-project">Project</label>
            <select id="budget-project" name="project_id" data-project-select data-selected-value="<?php echo safe($submitted['project_id']); ?>">
              <option value="">-- Select Project --</option>
              <?php foreach ($projectOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['project_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="budget-batch">Batch</label>
            <select id="budget-batch" name="batch_id" data-batch-select data-selected-value="<?php echo safe($submitted['batch_id']); ?>">
              <option value="">-- Select Batch --</option>
              <?php foreach ($batchOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['batch_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="budget-sub-batch">Sub-Batch Detail</label>
            <select id="budget-sub-batch" name="sub_batch_detail_id" data-sub-batch-select data-selected-value="<?php echo safe($submitted['sub_batch_detail_id']); ?>">
              <option value="">-- Select Sub-Batch --</option>
              <?php foreach ($subBatchOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['sub_batch_detail_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php $createItems = $submittedItems ?: [[]]; ?>
       <div data-item-section>
          <h4 style="margin:0 0 6px;">Budget items</h4>
          <div class="item-table__actions" style="margin-bottom:6px;">
            <label for="create-item-count">How many items?</label>
            <input id="create-item-count" type="number" min="0" value="<?php echo max(1, count($createItems)); ?>" data-item-count />
            <button class="btn btn-neutral" type="button" data-add-item-row>Add new item</button>
            <button class="btn btn-delete" type="button" data-delete-selected>Delete selected</button>
          </div>
          <div class="message-table__wrapper">
            <table class="item-table" data-item-table data-item-context="create">
               <thead>
                <tr>
                  <th style="width:36px;">Select</th>
                  <th>Qty</th>
                  <th>Item</th>
                  <th>Selling price</th>
                  <th>Cost type</th>
                  <th>Revenue</th>
                  <th>Freight</th>
                  <th>Supplier</th>
                </tr>
              </thead>
              <tbody data-item-body>
                <?php foreach ($createItems as $item): ?>
                 <tr data-item-row>
                   <td><input type="checkbox" class="item-select" /></td>
                    <td>
                      <input type="number" step="1" min="0" name="item_qty[]" value="<?php echo safe($item['qty'] ?? ''); ?>" placeholder="Qty" />
                    </td>
                    <td>
                      <input type="hidden" name="item_id[]" value="<?php echo safe($item['item_id'] ?? ''); ?>" />
                      <input type="text" name="item_name[]" value="<?php echo safe($item['item_name'] ?? ''); ?>" placeholder="Item name" />
                      <input type="text" name="item_description[]" value="<?php echo safe($item['description'] ?? ''); ?>" placeholder="Item description" />
                    </td>
                    <td>
                      <input type="number" step="0.01" name="item_selling_price[]" value="<?php echo safe($item['selling_price'] ?? ''); ?>" placeholder="Selling price" />
                    </td>
                    <td>
                      <select name="item_cost_type[]">
                        <option value="">-- Cost type --</option>
                        <?php foreach ($costTypes as $type): ?>
                          <option value="<?php echo safe($type); ?>" <?php echo ($item['costtype'] ?? '') === $type ? 'selected' : ''; ?>><?php echo safe($type); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td>
                      <input type="number" step="0.01" name="item_revenue_amount[]" value="<?php echo safe($item['revenue_amount'] ?? ''); ?>" placeholder="Amount" />
                      <select name="item_revenue_currency[]">
                        <?php foreach ($currencyOptions as $currency): ?>
                          <option value="<?php echo $currency; ?>" <?php echo ($item['revenue_currency'] ?? 'EGP') === $currency ? 'selected' : ''; ?>><?php echo $currency; ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input type="number" step="0.0001" name="item_revenue_exchange_rate[]" value="<?php echo safe($item['revenue_exchange_rate'] ?? ''); ?>" placeholder="Rate" />
                    </td>
                    <td>
                      <input type="number" step="0.01" name="item_freight_amount[]" value="<?php echo safe($item['freight_amount'] ?? ''); ?>" placeholder="Amount" />
                      <select name="item_freight_currency[]">
                        <?php foreach ($currencyOptions as $currency): ?>
                          <option value="<?php echo $currency; ?>" <?php echo ($item['freight_currency'] ?? 'EGP') === $currency ? 'selected' : ''; ?>><?php echo $currency; ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input type="number" step="0.0001" name="item_freight_exchange_rate[]" value="<?php echo safe($item['freight_exchange_rate'] ?? ''); ?>" placeholder="Rate" />
                    </td>
                    <td>
                      <input type="number" step="0.01" name="item_supplier_cost_amount[]" value="<?php echo safe($item['supplier_cost_amount'] ?? ''); ?>" placeholder="Amount" />
                      <select name="item_supplier_cost_currency[]">
                        <?php foreach ($currencyOptions as $currency): ?>
                          <option value="<?php echo $currency; ?>" <?php echo ($item['supplier_cost_currency'] ?? 'EGP') === $currency ? 'selected' : ''; ?>><?php echo $currency; ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input type="number" step="0.0001" name="item_supplier_cost_exchange_rate[]" value="<?php echo safe($item['supplier_cost_exchange_rate'] ?? ''); ?>" placeholder="Rate" />
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="actions" style="justify-content:flex-end; gap:10px;">
          <button class="btn" type="button" data-close-modal>Cancel</button>
          <button class="btn btn-save" type="submit">Create budget</button>
        </div>
      </form>
    </div>
   </div>

  <table id="budgets-table" style="display:none;">
    <thead>
      <tr><th>ID</th><th>Project</th><th>Scope</th><th>Link</th><th>Business Line</th><th>Created at</th></tr>
    </thead>
    <tbody>
      <?php foreach ($budgets as $budget): ?>
        <?php
           $scope = $budget['sub_batch_detail_id'] ? 'Sub-batch' : ($budget['batch_id'] ? 'Batch' : 'Project');
          $linkId = $budget['sub_batch_detail_id'] ?: ($budget['batch_id'] ?: $budget['project_id']);
          $linkLabel = $budget['project_id']
            ? option_label($projectOptions, (string) $budget['project_id'])
              : ($budget['sub_batch_detail_id'] ? option_label($subBatchOptions, (string) $budget['sub_batch_detail_id']) : option_label($batchOptions, (string) $budget['batch_id']));
          $budgetTitle = $budget['project_name'] ?: ($budget['batch_name'] ?? '') ?: ($budget['sub_batch_name'] ?? $linkId);
        ?>
        <tr>
          <td><?php echo safe($budget['budget_id']); ?></td>
          <td><?php echo safe($budgetTitle ?: '—'); ?></td>
          <td><?php echo safe($scope); ?></td>
          <td><?php echo safe($linkLabel ?: $linkId ?: '—'); ?></td>
          <td><?php echo safe($budget['business_line_name'] ?? '—'); ?></td>
          <td><?php echo safe($budget['created_at'] ?? ''); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

 <script>
    const currencyOptions = <?php echo json_encode($currencyOptions); ?>;
    const costTypeOptions = <?php echo json_encode($costTypes); ?>;
    const batchOptionsData = <?php echo json_encode($batchDataForJs); ?>;
    const subBatchOptionsData = <?php echo json_encode($subBatchDataForJs); ?>;

    const escapeHtml = (value = '') => String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    document.addEventListener('DOMContentLoaded', () => {
      const closeButtons = document.querySelectorAll('[data-close-modal]');
      const openCreateButtons = document.querySelectorAll('[data-open-create]');
      const createModal = document.getElementById('create-budget-modal');
      const hideModal = (modal) => {
        if (modal) {
          modal.classList.remove('is-visible');
        }
      };

      const showModal = (modal) => {
        if (modal) {
          modal.classList.add('is-visible');
        }
      };

      closeButtons.forEach((button) => {
        button.addEventListener('click', () => {
          const modal = button.closest('.message-modal');
          hideModal(modal);
        });
      });

      document.querySelectorAll('.message-modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
          if (event.target === modal && !modal.hasAttribute('data-dismissable')) {
            hideModal(modal);
          }
        });
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          document.querySelectorAll('.message-modal.is-visible').forEach((modal) => hideModal(modal));
        }
      });

      openCreateButtons.forEach((button) => button.addEventListener('click', () => showModal(createModal)));

      document.querySelectorAll('[data-open-manage]').forEach((button) => {
        const target = button.getAttribute('data-open-manage');
        const modal = document.querySelector(`[data-manage-modal="${target}"]`);
        if (!modal) return;

        button.addEventListener('click', () => showModal(modal));
      });

      document.querySelectorAll('[data-open-details]').forEach((button) => {
        const target = button.getAttribute('data-open-details');
        const modal = document.querySelector(`[data-details-modal="${target}"]`);
        if (!modal) return;

        button.addEventListener('click', () => showModal(modal));
      });

      const buildCurrencyOptions = (selected) => currencyOptions
        .map((currency) => `<option value="${currency}" ${currency === selected ? 'selected' : ''}>${currency}</option>`)
        .join('');

      const buildCostOptions = (selected) => ['<option value="">-- Cost type --</option>']
        .concat(costTypeOptions.map((type) => `<option value="${escapeHtml(type)}" ${type === selected ? 'selected' : ''}>${escapeHtml(type)}</option>`))
        .join('');

      const buildOptionList = (items, labelBuilder, selected, placeholderLabel) => {
        const options = items.map((item) => {
          const value = escapeHtml(item.value);
          const label = escapeHtml(labelBuilder(item));
          const isSelected = value === selected ? 'selected' : '';
          return `<option value="${value}" ${isSelected}>${label}</option>`;
        });

        return [`<option value="">${placeholderLabel}</option>`].concat(options).join('');
      };

      const attachLinkedSelects = (form) => {
        const projectSelect = form.querySelector('[data-project-select]');
        const batchSelect = form.querySelector('[data-batch-select]');
        const subBatchSelect = form.querySelector('[data-sub-batch-select]');
        const scopeRadios = form.querySelectorAll('input[name="budget_scope"]');
        const scopeSelect = form.querySelector('select[name="budget_scope"]');

        if (!projectSelect && !batchSelect && !subBatchSelect) {
          return;
        }

        const buildBatchLabel = (batch) => {
          const parts = [batch.project_name, batch.batch_name].filter(Boolean);
          return parts.join(' • ') || `Batch ${batch.batch_id}`;
        };

        const buildSubBatchLabel = (detail) => {
          const parts = [detail.project_name, detail.batch_name, detail.sub_batch_name].filter(Boolean);
          return parts.join(' • ') || `Sub batch ${detail.sub_batch_detail_id}`;
        };

        const refreshBatchOptions = () => {
          if (!batchSelect) return;
          const selected = batchSelect.value || batchSelect.dataset.selectedValue || '';
          const projectId = projectSelect?.value || '';
          const options = batchOptionsData
            .filter((batch) => !projectId || batch.project_id === projectId)
            .map((batch) => ({ value: batch.batch_id, label: buildBatchLabel(batch) }));

          batchSelect.innerHTML = buildOptionList(options, (item) => item.label, selected, '-- Select Batch --');
          batchSelect.value = selected;
          if (!batchSelect.value && selected) {
            batchSelect.value = '';
          }
          batchSelect.dataset.selectedValue = batchSelect.value;
        };

        const refreshSubBatchOptions = () => {
          if (!subBatchSelect) return;
          const selected = subBatchSelect.value || subBatchSelect.dataset.selectedValue || '';
          const batchId = batchSelect?.value || '';
          const projectId = projectSelect?.value || '';
          const options = subBatchOptionsData
            .filter((detail) => (batchId ? detail.batch_id === batchId : (!projectId || detail.project_id === projectId)))
            .map((detail) => ({ value: detail.sub_batch_detail_id, label: buildSubBatchLabel(detail) }));

          subBatchSelect.innerHTML = buildOptionList(options, (item) => item.label, selected, '-- Select Sub-Batch --');
          subBatchSelect.value = selected;
          if (!subBatchSelect.value && selected) {
            subBatchSelect.value = '';
          }
          subBatchSelect.dataset.selectedValue = subBatchSelect.value;
        };

        const syncScopeRequirements = () => {
          const radioScope = form.querySelector('input[name="budget_scope"]:checked');
          const activeScope = radioScope?.value || scopeSelect?.value || 'project';
          if (projectSelect) projectSelect.required = activeScope === 'project';
          if (batchSelect) batchSelect.required = activeScope === 'batch';
          if (subBatchSelect) subBatchSelect.required = activeScope === 'sub-batch';
        };

        projectSelect?.addEventListener('change', () => {
          refreshBatchOptions();
          refreshSubBatchOptions();
        });

        batchSelect?.addEventListener('change', () => {
          refreshSubBatchOptions();
        });

        scopeRadios.forEach((radio) => radio.addEventListener('change', syncScopeRequirements));
        scopeSelect?.addEventListener('change', syncScopeRequirements);

        refreshBatchOptions();
        refreshSubBatchOptions();
        syncScopeRequirements();
      };

      const createItemRow = (data = {}) => {
        const row = document.createElement('tr');
        row.setAttribute('data-item-row', '');

        const itemId = data.item_id || '';
        const itemName = escapeHtml(data.item_name || '');
        const description = escapeHtml(data.description || '');
        const sellingPrice = escapeHtml(data.selling_price || '');
        const costType = data.costtype || '';
        const revenueCurrency = data.revenue_currency || 'EGP';
        const freightCurrency = data.freight_currency || 'EGP';
        const supplierCurrency = data.supplier_cost_currency || 'EGP';

        row.innerHTML = `
           <td>
            <input type="checkbox" class="item-select" value="${escapeHtml(itemId)}" />
            <input type="hidden" name="item_id[]" value="${escapeHtml(itemId)}" />
          </td>
          <td>
            <input type="number" step="1" min="0" name="item_qty[]" value="${escapeHtml(data.qty || '')}" placeholder="Qty" />
          </td>
          <td>
            <input type="text" name="item_name[]" value="${itemName}" placeholder="Item name" />
            <input type="text" name="item_description[]" value="${description}" placeholder="Item description" />
          </td>
          <td>
            <input type="number" step="0.01" name="item_selling_price[]" value="${sellingPrice}" placeholder="Selling price" />
          </td>
          <td>
            <select name="item_cost_type[]">${buildCostOptions(costType)}</select>
          </td>
          <td>
            <input type="number" step="0.01" name="item_revenue_amount[]" value="${escapeHtml(data.revenue_amount || '')}" placeholder="Amount" />
            <select name="item_revenue_currency[]">${buildCurrencyOptions(revenueCurrency)}</select>
            <input type="number" step="0.0001" name="item_revenue_exchange_rate[]" value="${escapeHtml(data.revenue_exchange_rate || '')}" placeholder="Rate" />
          </td>
          <td>
            <input type="number" step="0.01" name="item_freight_amount[]" value="${escapeHtml(data.freight_amount || '')}" placeholder="Amount" />
            <select name="item_freight_currency[]">${buildCurrencyOptions(freightCurrency)}</select>
            <input type="number" step="0.0001" name="item_freight_exchange_rate[]" value="${escapeHtml(data.freight_exchange_rate || '')}" placeholder="Rate" />
          </td>
          <td>
            <input type="number" step="0.01" name="item_supplier_cost_amount[]" value="${escapeHtml(data.supplier_cost_amount || '')}" placeholder="Amount" />
            <select name="item_supplier_cost_currency[]">${buildCurrencyOptions(supplierCurrency)}</select>
            <input type="number" step="0.0001" name="item_supplier_cost_exchange_rate[]" value="${escapeHtml(data.supplier_cost_exchange_rate || '')}" placeholder="Rate" />
          </td>
          `;

        return row;
      };

      document.querySelectorAll('form').forEach((form) => {
        if (form.querySelector('[data-project-select],[data-batch-select],[data-sub-batch-select]')) {
          attachLinkedSelects(form);
        }
      });

      const syncRowCount = (tbody, desired) => {
        const parsed = Number.parseInt(desired, 10);
        if (Number.isNaN(parsed) || parsed < 0) {
          return;
        }

        const current = tbody.querySelectorAll('[data-item-row]').length;
        if (parsed > current) {
          for (let i = current; i < parsed; i += 1) {
            tbody.appendChild(createItemRow());
          }
        } else if (parsed < current) {
          for (let i = current; i > parsed; i -= 1) {
            const last = tbody.querySelector('[data-item-row]:last-child');
            if (last) {
              last.remove();
            }
          }
        }
      };

      document.querySelectorAll('[data-item-table]').forEach((table) => {
        const section = table.closest('[data-item-section]') || table.closest('div');
        if (!section) return;

        const tbody = table.querySelector('[data-item-body]');
        const countInput = section.querySelector('[data-item-count]');
        const addButton = section.querySelector('[data-add-item-row]');
        const deleteButton = section.querySelector('[data-delete-selected]');
        const form = section.closest('form');

        if (countInput) {
          countInput.addEventListener('change', () => syncRowCount(tbody, countInput.value));
          syncRowCount(tbody, countInput.value);
        }

        if (addButton) {
          addButton.addEventListener('click', () => {
            tbody.appendChild(createItemRow());
            if (countInput) {
              const current = tbody.querySelectorAll('[data-item-row]').length;
              countInput.value = current;
            }
          });
        }

        if (deleteButton) {
          deleteButton.addEventListener('click', () => {
            const rows = Array.from(tbody.querySelectorAll('[data-item-row]'));
            rows.forEach((row) => {
              const checkbox = row.querySelector('.item-select');
              if (checkbox && checkbox.checked) {
                const itemId = checkbox.value || row.querySelector('input[name="item_id[]"]')?.value || '';
                if (itemId && form) {
                  const deleteInput = document.createElement('input');
                  deleteInput.type = 'hidden';
                  deleteInput.name = 'delete_items[]';
                  deleteInput.value = itemId;
                  form.appendChild(deleteInput);
                }

                row.remove();
              }
            });

            if (countInput) {
              const current = tbody.querySelectorAll('[data-item-row]').length;
              countInput.value = current;
            }
          });
        }
      });
    });
  </script>
</body>
</html>