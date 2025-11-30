<?php
require_once __DIR__ . '/helpers.php';
$customers = fetch_table('customers', 'customer_id');
$customerOptions = to_options($customers, 'customer_id', 'customer_name');
$projects = fetch_table('projects', 'project_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Projects | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
      <div class="title">Projects</div>
    </div>
    <div class="links">
      <a href="./home.php">Home</a>
      <a href="./login.php">Logout</a>
    </div>
  </header>

  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container">
      <h3 style="margin-top:0; color:var(--secondary);">Create or Update Project</h3>
      <div class="form-row">
        <div>
          <label class="label" for="project-id">Project ID</label>
          <input id="project-id" type="text" placeholder="PRJ-001" />
        </div>
        <div>
          <label class="label" for="project-name">Project Name</label>
          <input id="project-name" type="text" placeholder="Wind Farm Expansion" />
        </div>
        <div>
          <label class="label" for="cost-center">Cost Center No</label>
          <input id="cost-center" type="text" placeholder="CC-1001" />
        </div>
        <div>
          <label class="label" for="po-number">PO Number</label>
          <input id="po-number" type="text" placeholder="PO-2025-01" />
        </div>
      </div>
      <div class="form-row">
        <div>
          <label class="label" for="project-customer">Customer</label>
          <select id="project-customer">
            <option value="">-- Select Customer --</option>
            <?php foreach ($customerOptions as $option): ?>
              <option value="<?php echo safe($option['value']); ?>"><?php echo safe($option['value'] . ' | ' . $option['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label" for="project-start">Contract Date</label>
          <input id="project-start" type="date" />
        </div>
        <div>
          <label class="label" for="project-end">Expected End</label>
          <input id="project-end" type="date" />
        </div>
        <div>
          <label class="label" for="project-actual-end">Actual End</label>
          <input id="project-actual-end" type="date" />
        </div>
      </div>
      <div class="actions">
        <button class="btn btn-save" type="button">Save Project</button>
        <button class="btn btn-neutral" type="button">View</button>
        <button class="btn btn-delete" type="button">Delete</button>
      </div>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Name</th><th>Customer</th><th>Cost Center</th><th>PO Number</th><th>Contract</th><th>Expected End</th><th>Actual End</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($projects): ?>
            <?php foreach ($projects as $project): ?>
              <tr>
                <td><?php echo safe($project['project_id']); ?></td>
                <td><?php echo safe($project['project_name']); ?></td>
                <td><?php echo safe($project['customer_id'] ?? ''); ?></td>
                <td><?php echo safe($project['cost_center_no']); ?></td>
                <td><?php echo safe($project['po_number']); ?></td>
                <td><?php echo safe($project['contract_date']); ?></td>
                <td><?php echo safe($project['expected_end_date']); ?></td>
                <td><?php echo safe($project['actual_end_date']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="8">No projects recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>