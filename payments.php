<?php
require_once __DIR__ . '/helpers.php';
$projects = fetch_table('projects', 'project_id');
$subBatchDetails = fetch_table('sub_batch_details', 'sub_batch_detail_id');
$payments = fetch_table('payments', 'payment_id');
$projectOptions = to_options($projects, 'project_id', 'project_name');
$subBatchOptions = to_options($subBatchDetails, 'sub_batch_detail_id', 'sub_batch_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payments | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
      <div class="title">Payments</div>
    </div>
    <div class="links">
      <a href="./home.php">Home</a>
      <a href="./login.php">Logout</a>
    </div>
  </header>
  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Outgoing Payment</h3>
      <div class="form-row">
        <div>
          <label class="label" for="payment-id">Payment ID</label>
          <input id="payment-id" type="text" placeholder="PAY-001" />
        </div>
        <div>
          <label class="label">Scope</label>
          <div style="display:flex; gap:10px; align-items:center;">
            <label><input type="radio" name="budget-scope" value="project" checked /> Project</label>
            <label><input type="radio" name="budget-scope" value="sub-batch" /> Sub-Batch Detail</label>
          </div>
        </div>
        <div>
          <label class="label" for="payment-project">Project</label>
          <select id="payment-project">
            <option value="">-- Select Project --</option>
            <?php foreach ($projectOptions as $option): ?>
              <option value="<?php echo safe($option['value']); ?>"><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label" for="payment-sub-batch">Sub-Batch Detail</label>
          <select id="payment-sub-batch">
            <option value="">-- Select Sub-Batch --</option>
            <?php foreach ($subBatchOptions as $option): ?>
              <option value="<?php echo safe($option['value']); ?>"><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="payment-code">Payment Code</label>
          <input id="payment-code" type="text" placeholder="PMT-REF-01" />
        </div>
        <div>
          <label class="label" for="payment-type">Payment Type</label>
          <input id="payment-type" type="text" placeholder="Freight / Customs / Supplier" />
        </div>
        <div>
          <label class="label" for="payment-requested-by">Requested By</label>
          <input id="payment-requested-by" type="text" placeholder="Requester name" />
        </div>
        <div>
          <label class="label" for="payment-description">Description</label>
          <input id="payment-description" type="text" placeholder="Short description" />
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="payment-amount">Amount</label>
          <input id="payment-amount" type="number" step="0.01" placeholder="50000" />
        </div>
        <div>
          <label class="label" for="payment-currency">Currency</label>
          <select id="payment-currency">
            <option>EGP</option>
            <option>USD</option>
            <option>EUR</option>
          </select>
        </div>
        <div>
          <label class="label" for="payment-rate">Exchange Rate</label>
          <input id="payment-rate" type="number" step="0.0001" placeholder="48.50" />
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="requested-date">Requested Date</label>
          <input id="requested-date" type="date" />
        </div>
        <div>
          <label class="label" for="due-date">Payment Due (auto)</label>
          <input id="due-date" type="date" disabled />
        </div>
        <div>
          <label class="label" for="paid-date">Paid Date</label>
          <input id="paid-date" type="date" />
        </div>
        <div>
          <label class="label">Paid?</label>
          <label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" id="payment-paid" /> Mark as paid</label>
        </div>
        <div>
          <label class="label">Status</label>
          <span id="payment-status" class="status-pill warning">Pending</span>
        </div>
      </div>
      <div class="actions">
        <button class="btn btn-save" type="button">Save Payment</button>
        <button class="btn btn-neutral" type="button">View</button>
        <button class="btn btn-delete" type="button">Delete</button>
      </div>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>ID</th><th>Scope</th><th>Link</th><th>Type</th><th>Amount</th><th>Requested</th><th>Due</th><th>Paid</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if ($payments): ?>
            <?php foreach ($payments as $payment): ?>
              <?php
                $scope = $payment['project_id'] ? 'Project' : 'Sub-Batch';
                $link = $payment['project_id'] ?: $payment['sub_batch_detail_id'];
                $statusClass = $payment['status'] === 'paid' ? 'success' : 'warning';
              ?>
              <tr>
                <td><?php echo safe($payment['payment_id']); ?></td>
                <td><?php echo safe($scope); ?></td>
                <td><?php echo safe($link); ?></td>
                <td><?php echo safe($payment['payment_type']); ?></td>
                <td><?php echo safe(($payment['currency'] ?? '') . ' ' . ($payment['amount'] ?? '')); ?></td>
                <td><?php echo safe($payment['requested_date']); ?></td>
                <td><?php echo safe($payment['due_date']); ?></td>
                <td><?php echo safe($payment['paid_date']); ?></td>
                <td><span class="status-pill <?php echo safe($statusClass); ?>"><?php echo safe(ucfirst($payment['status'])); ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="9">No payments recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
  <script src="./assets/scripts.js"></script>
</body>
</html>