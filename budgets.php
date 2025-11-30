<?php
require_once __DIR__ . '/helpers.php';
$projects = fetch_table('projects', 'project_id');
$subBatchDetails = fetch_table('sub_batch_details', 'sub_batch_detail_id');
$budgets = fetch_table('budgets', 'budget_id');
$projectOptions = to_options($projects, 'project_id', 'project_name');
$subBatchOptions = to_options($subBatchDetails, 'sub_batch_detail_id', 'sub_batch_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Budgets | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
      <div class="title">Budgets</div>
    </div>
    <div class="links">
      <a href="./home.php">Home</a>
      <a href="./login.php">Logout</a>
    </div>
  </header>
  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Budget Entry (Project or Sub-Batch)</h3>
      <div class="form-row">
        <div>
          <label class="label" for="budget-id">Budget ID</label>
          <input id="budget-id" type="text" placeholder="BUD-001" />
        </div>
        <div>
          <label class="label">Scope</label>
          <div style="display:flex; gap:10px; align-items:center;">
            <label><input type="radio" name="budget-scope" value="project" checked /> Project</label>
            <label><input type="radio" name="budget-scope" value="sub-batch" /> Sub-Batch Detail</label>
          </div>
        </div>
        <div>
          <label class="label" for="budget-project">Project</label>
          <select id="budget-project">
            <option value="">-- Select Project --</option>
            <?php foreach ($projectOptions as $option): ?>
              <option value="<?php echo safe($option['value']); ?>"><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label" for="budget-sub-batch">Sub-Batch Detail</label>
          <select id="budget-sub-batch">
            <option value="">-- Select Sub-Batch --</option>
            <?php foreach ($subBatchOptions as $option): ?>
              <option value="<?php echo safe($option['value']); ?>"><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="budget-type">Cost Type</label>
          <input id="budget-type" type="text" placeholder="Materials / Freight / Customs" />
        </div>
        <div>
          <label class="label" for="revenue-amount">Revenue Amount</label>
          <input id="revenue-amount" type="number" step="0.01" placeholder="100000" />
        </div>
        <div>
          <label class="label" for="revenue-currency">Revenue Currency</label>
          <select id="revenue-currency">
            <option>EGP</option><option>USD</option><option>EUR</option>
          </select>
        </div>
        <div>
          <label class="label" for="revenue-rate">Revenue Exchange Rate</label>
          <input id="revenue-rate" type="number" step="0.0001" placeholder="48.50" />
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="freight-amount">Freight Amount</label>
          <input id="freight-amount" type="number" step="0.01" placeholder="5000" />
        </div>
        <div>
          <label class="label" for="freight-currency">Freight Currency</label>
          <select id="freight-currency">
            <option>EGP</option><option>USD</option><option>EUR</option>
          </select>
        </div>
        <div>
          <label class="label" for="freight-rate">Freight Exchange Rate</label>
          <input id="freight-rate" type="number" step="0.0001" placeholder="48.50" />
        </div>
        <div>
          <label class="label" for="supplier-cost">Supplier Cost Amount</label>
          <input id="supplier-cost" type="number" step="0.01" placeholder="7500" />
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="supplier-currency">Supplier Currency</label>
          <select id="supplier-currency">
            <option>EGP</option><option>USD</option><option>EUR</option>
          </select>
        </div>
        <div>
          <label class="label" for="supplier-rate">Supplier Exchange Rate</label>
          <input id="supplier-rate" type="number" step="0.0001" placeholder="48.50" />
        </div>
      </div>
      <div class="actions">
        <button class="btn btn-save" type="button">Save Budget</button>
        <button class="btn btn-neutral" type="button">View</button>
        <button class="btn btn-delete" type="button">Delete</button>
      </div>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>ID</th><th>Scope</th><th>Link</th><th>Cost Type</th><th>Revenue</th><th>Freight</th><th>Supplier Cost</th></tr>
        </thead>
        <tbody>
          <?php if ($budgets): ?>
            <?php foreach ($budgets as $budget): ?>
              <?php
                $scope = $budget['project_id'] ? 'Project' : 'Sub-Batch';
                $link = $budget['project_id'] ?: $budget['sub_batch_detail_id'];
              ?>
              <tr>
                <td><?php echo safe($budget['budget_id']); ?></td>
                <td><?php echo safe($scope); ?></td>
                <td><?php echo safe($link); ?></td>
                <td><?php echo safe($budget['cost_type']); ?></td>
                <td><?php echo safe(($budget['revenue_currency'] ?? '') . ' ' . ($budget['revenue_amount'] ?? '')); ?></td>
                <td><?php echo safe(($budget['freight_currency'] ?? '') . ' ' . ($budget['freight_amount'] ?? '')); ?></td>
                <td><?php echo safe(($budget['supplier_cost_currency'] ?? '') . ' ' . ($budget['supplier_cost_amount'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7">No budgets recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
  <script src="./assets/scripts.js"></script>
</body>
</html>