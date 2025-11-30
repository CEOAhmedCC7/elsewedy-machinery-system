<?php
require_once __DIR__ . '/helpers.php';
$customers = fetch_table('customers', 'customer_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customers | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
      <div class="title">Customers</div>
    </div>
    <div class="links">
      <a href="./home.php">Home</a>
      <a href="./login.php">Logout</a>
    </div>
  </header>

  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Create or Update Customer</h3>
      <div class="form-row">
        <div>
          <label class="label" for="customer-id">Customer ID</label>
          <input id="customer-id" type="text" placeholder="CUST-001" />
        </div>
        <div>
          <label class="label" for="customer-name">Customer Name</label>
          <input id="customer-name" type="text" placeholder="Nile Energy" />
        </div>
      </div>
      <div class="actions">
        <button class="btn btn-save" type="button">Save Customer</button>
        <button class="btn btn-neutral" type="button">View</button>
        <button class="btn btn-delete" type="button">Delete</button>
      </div>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>ID</th><th>Name</th></tr>
        </thead>
        <tbody>
          <?php if ($customers): ?>
            <?php foreach ($customers as $customer): ?>
              <tr>
                <td><?php echo safe($customer['customer_id']); ?></td>
                <td><?php echo safe($customer['customer_name']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No customers recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>