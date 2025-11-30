<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$error = '';
$success = '';

$submitted = [
    'customer_id' => trim($_POST['customer_id'] ?? ''),
    'customer_name' => trim($_POST['customer_name'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo = get_pdo();

    try {
        if ($action === 'create') {
            if ($submitted['customer_name'] === '') {
                $error = 'Customer name is required.';
            } else {
                $customerId = $submitted['customer_id'] !== '' ? $submitted['customer_id'] : 'cust_' . bin2hex(random_bytes(4));

                $exists = $pdo->prepare('SELECT 1 FROM customers WHERE customer_id = :id');
                $exists->execute([':id' => $customerId]);

                if ($exists->fetchColumn()) {
                    $error = 'A customer with this ID already exists.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO customers (customer_id, customer_name) VALUES (:id, :name)');
                    $stmt->execute([
                        ':id' => $customerId,
                        ':name' => $submitted['customer_name'],
                    ]);

                    $success = 'Customer saved successfully.';
                    $submitted = ['customer_id' => '', 'customer_name' => ''];
                }
            }
        } elseif ($action === 'update') {
            if ($submitted['customer_id'] === '' || $submitted['customer_name'] === '') {
                $error = 'Provide both Customer ID and Name to update.';
            } else {
                $stmt = $pdo->prepare('UPDATE customers SET customer_name = :name WHERE customer_id = :id');
                $stmt->execute([
                    ':id' => $submitted['customer_id'],
                    ':name' => $submitted['customer_name'],
                ]);

                if ($stmt->rowCount() === 0) {
                    $error = 'Customer not found.';
                } else {
                    $success = 'Customer updated successfully.';
                }
            }
        } elseif ($action === 'view') {
            if ($submitted['customer_id'] === '') {
                $error = 'Enter a Customer ID to load details.';
            } else {
                $stmt = $pdo->prepare('SELECT customer_id, customer_name FROM customers WHERE customer_id = :id');
                $stmt->execute([':id' => $submitted['customer_id']]);
                $found = $stmt->fetch();

                if ($found) {
                    $submitted['customer_name'] = $found['customer_name'];
                    $success = 'Customer loaded. You can update or delete it.';
                } else {
                    $error = 'No customer found with that ID.';
                }
            }
        } elseif ($action === 'delete') {
            if ($submitted['customer_id'] === '') {
                $error = 'Enter a Customer ID to delete.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM customers WHERE customer_id = :id');
                $stmt->execute([':id' => $submitted['customer_id']]);

                if ($stmt->rowCount() === 0) {
                    $error = 'Customer not found or already deleted.';
                } else {
                    $success = 'Customer deleted successfully.';
                    $submitted = ['customer_id' => '', 'customer_name' => ''];
                }
            }
        }
    } catch (Throwable $e) {
        $error = format_db_error($e, 'customers table');
    }
}

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
      <a href="./home.php">Home</a>␊
      <a href="./logout.php">Logout</a>
    </div>
  </header>

  <main style="padding:24px; display:grid; gap:20px;">
<div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Create or Update Customer</h3>
      <?php if ($error): ?>
        <div class="alert" style="color: var(--secondary); margin-bottom:12px;">
          <?php echo safe($error); ?>
        </div>
      <?php elseif ($success): ?>
        <div class="alert" style="color: var(--primary); margin-bottom:12px;">
          <?php echo safe($success); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="customers.php">
        <div class="form-row">
          <div>
            <label class="label" for="customer-id">Customer ID</label>
            <input id="customer-id" name="customer_id" type="text" placeholder="CUST-001" value="<?php echo safe($submitted['customer_id']); ?>" />
            <p class="helper-text">Leave blank to auto-generate when saving.</p>
          </div>
          <div>
            <label class="label" for="customer-name">Customer Name</label>
            <input id="customer-name" name="customer_name" type="text" placeholder="Nile Energy" value="<?php echo safe($submitted['customer_name']); ?>" />
          </div>
        </div>
        <div class="actions">
          <button class="btn btn-save" type="submit" name="action" value="create">Save Customer</button>
          <button class="btn btn-neutral" type="submit" name="action" value="view">View</button>
          <button class="btn btn-neutral" type="submit" name="action" value="update">Update</button>
          <button class="btn btn-delete" type="submit" name="action" value="delete" onclick="return confirm('Delete this customer?');">Delete</button>
        </div>
      </form>
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