<?php
require_once __DIR__ . '/helpers.php';
$projects = fetch_table('projects', 'project_id');
$invoices = fetch_table('invoices', 'invoice_id');
$projectOptions = to_options($projects, 'project_id', 'project_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Invoices | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
      <div class="title">Invoices</div>
    </div>
    <div class="links">
      <a href="./home.php">Home</a>
      <a href="./login.php">Logout</a>
    </div>
  </header>
  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Invoice Creation</h3>
      <div class="form-row">
        <div>
          <label class="label" for="invoice-id">Invoice ID</label>
          <input id="invoice-id" type="text" placeholder="INV-001" />
        </div>
        <div>
          <label class="label" for="invoice-number">Invoice Number</label>
          <input id="invoice-number" type="text" placeholder="Official invoice number" />
        </div>
        <div>
          <label class="label" for="invoice-project">Project</label>
          <select id="invoice-project">
            <option value="">-- Select Project --</option>
            <?php foreach ($projectOptions as $option): ?>
              <option value="<?php echo safe($option['value']); ?>"><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="invoice-date">Invoice Date</label>
          <input id="invoice-date" type="date" />
        </div>
        <div>
          <label class="label" for="invoice-subtotal">Total Amount (before VAT)</label>
          <input id="invoice-subtotal" type="number" step="0.01" placeholder="100000" />
        </div>
        <div>
          <label class="label" for="invoice-vat">VAT %</label>
          <input id="invoice-vat" type="number" step="0.01" value="14" />
        </div>
        <div>
          <label class="label">Total with VAT</label>
          <div id="invoice-total" class="pill">0.00</div>
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="invoice-description">Description</label>
          <input id="invoice-description" type="text" placeholder="Scope notes" />
        </div>
        <div>
          <label class="label" for="invoice-status">Status</label>
          <select id="invoice-status">
            <option value="draft">Draft</option>
            <option value="issued">Issued</option>
            <option value="collected">Collected</option>
          </select>
        </div>
        <div>
          <label class="label" for="collected-date">Collected Date</label>
          <input id="collected-date" type="date" />
        </div>
      </div>
      <div class="actions">
        <button class="btn btn-save" type="button">Save Invoice</button>
        <button class="btn btn-neutral" type="button">View</button>
        <button class="btn btn-delete" type="button">Delete</button>
      </div>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>ID</th><th>Project</th><th>Invoice #</th><th>Date</th><th>Total</th><th>VAT</th><th>With VAT</th><th>Status</th><th>Collected</th></tr>
        </thead>
        <tbody>
          <?php if ($invoices): ?>
            <?php foreach ($invoices as $invoice): ?>
              <tr>
                <td><?php echo safe($invoice['invoice_id']); ?></td>
                <td><?php echo safe($invoice['project_id']); ?></td>
                <td><?php echo safe($invoice['invoice_number']); ?></td>
                <td><?php echo safe($invoice['invoice_date']); ?></td>
                <td><?php echo safe($invoice['total_amount']); ?></td>
                <td><?php echo safe($invoice['vat_amount']); ?></td>
                <td><?php echo safe($invoice['amount_with_vat']); ?></td>
                <td><?php echo safe($invoice['status']); ?></td>
                <td><?php echo safe($invoice['collected_date']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="9">No invoices recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
  <script src="./assets/scripts.js"></script>
</body>
</html>