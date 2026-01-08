<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$moduleCode = resolve_module_code('BUSINESS_DEVELOPMENT');

$error = '';
$success = '';
$successHtml = '';
$modalOverride = null;

$pdo = null;
try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    $error = format_db_error($e, 'business_development table');
}

$businessLines = fetch_table('business_lines', 'business_line_name');
$businessLineOptions = to_options($businessLines, 'business_line_id', 'business_line_name');

$opportunityOwners = fetch_table('opportunity_owners', 'opportunity_owner_name');
$opportunityOwnerOptions = to_options($opportunityOwners, 'opportunity_owner_id', 'opportunity_owner_name');

function option_label(array $options, string $value): string
{
    foreach ($options as $option) {
        if ((string) ($option['value'] ?? '') === $value) {
            return (string) ($option['label'] ?? $value);
        }
    }

    return $value;
}

$submitted = [
    'business_dev_id' => trim($_POST['business_dev_id'] ?? ''),
    'project_name' => trim($_POST['project_name'] ?? ''),
    'location' => trim($_POST['location'] ?? ''),
    'client' => trim($_POST['client'] ?? ''),
    'consultant' => trim($_POST['consultant'] ?? ''),
    'status' => trim($_POST['status'] ?? ''),
    'date_of_invitation' => trim($_POST['date_of_invitation'] ?? ''),
    'submission_date' => trim($_POST['submission_date'] ?? ''),
    'contact_person_name' => trim($_POST['contact_person_name'] ?? ''),
    'contact_person_title' => trim($_POST['contact_person_title'] ?? ''),
    'contact_person_phone' => trim($_POST['contact_person_phone'] ?? ''),
    'remarks' => trim($_POST['remarks'] ?? ''),
    'business_line_id' => trim($_POST['business_line_id'] ?? ''),
    'opportunity_owner_id' => trim($_POST['opportunity_owner_id'] ?? ''),
];

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $permissionError = enforce_action_permission(
        $currentUser,
        $moduleCode ?? 'BUSINESS_DEVELOPMENT',
        $action,
        [
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
        ]
    );

    try {
        if ($permissionError) {
            $modalOverride = permission_denied_modal();
            $error = $permissionError;
        } elseif ($action === 'create') {
            if ($submitted['project_name'] === '') {
                throw new RuntimeException('Project name is required.');
            }
            if ($submitted['business_line_id'] === '') {
                throw new RuntimeException('Please choose a business line.');
            }
            if ($submitted['opportunity_owner_id'] === '') {
                throw new RuntimeException('Please choose an opportunity owner.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO business_development (project_name, location, client, consultant, status, date_of_invitation, submission_date, contact_person_name, contact_person_title, contact_person_phone, remarks, business_line_id, opportunity_owner_id)
                 VALUES (:project_name, NULLIF(:location, ''), NULLIF(:client, ''), NULLIF(:consultant, ''), NULLIF(:status, ''), NULLIF(:date_of_invitation, '')::date, NULLIF(:submission_date, '')::date, NULLIF(:contact_person_name, ''), NULLIF(:contact_person_title, ''), NULLIF(:contact_person_phone, ''), NULLIF(:remarks, ''), :business_line_id, :opportunity_owner_id)"
            );
            $stmt->execute([
                ':project_name' => $submitted['project_name'],
                ':location' => $submitted['location'],
                ':client' => $submitted['client'],
                ':consultant' => $submitted['consultant'],
                ':status' => $submitted['status'],
                ':date_of_invitation' => $submitted['date_of_invitation'],
                ':submission_date' => $submitted['submission_date'],
                ':contact_person_name' => $submitted['contact_person_name'],
                ':contact_person_title' => $submitted['contact_person_title'],
                ':contact_person_phone' => $submitted['contact_person_phone'],
                ':remarks' => $submitted['remarks'],
                ':business_line_id' => $submitted['business_line_id'],
                ':opportunity_owner_id' => $submitted['opportunity_owner_id'],
            ]);

            $newId = (int) $pdo->lastInsertId('business_development_business_dev_id_seq');
            $successDetails = [
                'Project name' => $submitted['project_name'],
                'Location' => $submitted['location'] ?: '—',
                'Client' => $submitted['client'] ?: '—',
                'Consultant' => $submitted['consultant'] ?: '—',
                'Status' => $submitted['status'] ?: '—',
                'Invitation date' => $submitted['date_of_invitation'] ?: '—',
                'Submission date' => $submitted['submission_date'] ?: '—',
                'Contact' => $submitted['contact_person_name'] ?: '—',
                'Business line' => option_label($businessLineOptions, $submitted['business_line_id']),
                'Opportunity owner' => option_label($opportunityOwnerOptions, $submitted['opportunity_owner_id']),
            ];

            $successRows = '';
            foreach ($successDetails as $label => $value) {
                $successRows .= '<tr><th>' . safe($label) . '</th><td>' . safe($value) . '</td></tr>';
            }

            $success = 'Opportunity created successfully (ID #' . $newId . ').';
            $successHtml = $success . '<div class="message-table__wrapper"><table class="message-table">' . $successRows . '</table></div>';
            $submitted = array_map(static fn () => '', $submitted);
        } elseif ($action === 'update') {
            if ($submitted['business_dev_id'] === '') {
                throw new RuntimeException('Load an opportunity before updating.');
            }
            if ($submitted['project_name'] === '') {
                throw new RuntimeException('Project name is required.');
            }
            if ($submitted['business_line_id'] === '') {
                throw new RuntimeException('Please choose a business line.');
            }
            if ($submitted['opportunity_owner_id'] === '') {
                throw new RuntimeException('Please choose an opportunity owner.');
            }

            $stmt = $pdo->prepare(
                "UPDATE business_development
                 SET project_name = :project_name,
                     location = NULLIF(:location, ''),
                     client = NULLIF(:client, ''),
                     consultant = NULLIF(:consultant, ''),
                     status = NULLIF(:status, ''),
                     date_of_invitation = NULLIF(:date_of_invitation, '')::date,
                     submission_date = NULLIF(:submission_date, '')::date,
                     contact_person_name = NULLIF(:contact_person_name, ''),
                     contact_person_title = NULLIF(:contact_person_title, ''),
                     contact_person_phone = NULLIF(:contact_person_phone, ''),
                     remarks = NULLIF(:remarks, ''),
                     business_line_id = :business_line_id,
                     opportunity_owner_id = :opportunity_owner_id
                 WHERE business_dev_id = :business_dev_id"
            );
            $stmt->execute([
                ':business_dev_id' => $submitted['business_dev_id'],
                ':project_name' => $submitted['project_name'],
                ':location' => $submitted['location'],
                ':client' => $submitted['client'],
                ':consultant' => $submitted['consultant'],
                ':status' => $submitted['status'],
                ':date_of_invitation' => $submitted['date_of_invitation'],
                ':submission_date' => $submitted['submission_date'],
                ':contact_person_name' => $submitted['contact_person_name'],
                ':contact_person_title' => $submitted['contact_person_title'],
                ':contact_person_phone' => $submitted['contact_person_phone'],
                ':remarks' => $submitted['remarks'],
                ':business_line_id' => $submitted['business_line_id'],
                ':opportunity_owner_id' => $submitted['opportunity_owner_id'],
            ]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Opportunity not found.');
            }

            $success = 'Opportunity updated successfully.';
        } elseif ($action === 'delete') {
            if ($submitted['business_dev_id'] === '') {
                throw new RuntimeException('Load an opportunity before deleting.');
            }

            $stmt = $pdo->prepare('DELETE FROM business_development WHERE business_dev_id = :id');
            $stmt->execute([':id' => $submitted['business_dev_id']]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Opportunity not found or already deleted.');
            }

            $success = 'Opportunity deleted successfully.';
            $submitted = array_map(static fn () => '', $submitted);
        }
    } catch (Throwable $e) {
        $error = format_db_error($e, 'business_development table');
    }
}

$filters = [
    'business_line_id' => trim($_GET['filter_business_line_id'] ?? ''),
    'opportunity_owner_id' => trim($_GET['filter_opportunity_owner_id'] ?? ''),
];

$opportunities = [];

if ($pdo) {
    try {
        $conditions = [];
        $params = [];

        if ($filters['business_line_id'] !== '') {
            $conditions[] = 'bd.business_line_id = :filter_business_line_id';
            $params[':filter_business_line_id'] = $filters['business_line_id'];
        }
        if ($filters['opportunity_owner_id'] !== '') {
            $conditions[] = 'bd.opportunity_owner_id = :filter_opportunity_owner_id';
            $params[':filter_opportunity_owner_id'] = $filters['opportunity_owner_id'];
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT bd.business_dev_id, bd.project_name, bd.location, bd.client, bd.consultant, bd.status, bd.date_of_invitation, bd.submission_date, bd.contact_person_name, bd.contact_person_title, bd.contact_person_phone, bd.remarks, bd.business_line_id, bd.opportunity_owner_id,
                       COALESCE(bl.business_line_name, '') AS business_line_name,
                       COALESCE(oo.opportunity_owner_name, '') AS opportunity_owner_name
                FROM business_development bd
                LEFT JOIN business_lines bl ON bl.business_line_id = bd.business_line_id
                LEFT JOIN opportunity_owners oo ON oo.opportunity_owner_id = bd.opportunity_owner_id
                {$whereSql}
                ORDER BY bd.business_dev_id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $opportunities = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error = $error ?: format_db_error($e, 'business_development table');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Business Development | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
  <style>
    .opportunity-grid {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }

    .opportunity-card {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      background: linear-gradient(135deg, #0b8dc0, #0f4b8c);
      color: #fff;
      border-radius: 10px;
      padding: 14px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      min-height: 180px;
      position: relative;
    }

    .opportunity-card:hover,
    .opportunity-card:focus-visible {
      transform: translateY(-2px);
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
      outline: none;
    }

    .opportunity-card h4,
    .opportunity-card p,
    .opportunity-card small {
      color: #fff;
      margin: 0;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .opportunity-card__footer {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }

    .opportunity-card__footer .btn,
    .opportunity-card__footer a.btn {
      flex: 1;
      text-align: center;
    }

    .opportunity-status {
      position: absolute;
      height: 35px;
      top: 10px;
      left: 10px;
      right: auto;
      border-radius: 6px;
      background: var(--secondary);
      padding: 6px 10px;
      min-width: 80px;
      text-align: center;
    }

    .opportunity-flag {
      position: absolute;
      top: 10px;
      right: 10px;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }

    .opportunity-flag.is-missing {
      background: rgba(255, 76, 76, 0.9);
    }

    .opportunity-flag.is-submitted {
      background: rgba(78, 205, 107, 0.9);
    }

    .opportunity-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(220px, 1fr));
      gap: 12px;
    }

    .opportunity-form-grid .span-full {
      grid-column: 1 / -1;
    }

    .details-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 10px;
    }

    .details-grid__item {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px;
    }

    .details-grid__item h5 {
      margin: 0 0 4px;
      color: var(--secondary);
    }

    .details-grid__item p {
      margin: 0;
      color: var(--text);
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
      padding: 6px 10px;
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
  </style>
</head>
<body class="page">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
    </div>
    <div class="title">Business Development</div>
    <div class="links">
      <div class="user-chip">
        <span class="name"><?php echo safe($currentUser['username']); ?></span>
        <span class="role"><?php echo strtoupper(safe($currentUser['role'])); ?></span>
      </div>
      <a href="./home.php">Home</a>
      <a class="logout-icon" href="./logout.php" aria-label="Logout">⎋</a>
    </div>
  </header>

  <?php
    $messageBody = $modalOverride['subtitle'] ?? ($error !== '' ? safe($error) : ($successHtml !== '' ? $successHtml : safe($success)));
  ?>

  <?php if ($error !== '' || $success !== ''): ?>
    <div class="message-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Business development notification" data-dismissable>
      <div class="message-dialog <?php echo $error ? 'is-error' : 'is-success'; ?>">
        <div class="message-dialog__header">
          <span class="message-title"><?php echo $modalOverride['title'] ?? ($error ? 'Action needed' : 'Success'); ?></span>
          <button class="message-close" type="button" aria-label="Close message" data-close-modal>&times;</button>
        </div>
        <div class="message-body"><?php echo $messageBody; ?></div>
      </div>
    </div>
  <?php endif; ?>

  <main style="padding:24px; display:grid; gap:20px;">
    <div class="form-container" style="display:grid; gap:16px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
          <h3 style="margin:0; color:var(--secondary);">Create, view, update or delete opportunities</h3>
          <p style="margin:6px 0 0; color:var(--muted);">Use the create button to add opportunities, then manage or review them from the cards below.</p>
        </div>
        <button class="btn btn-save" type="button" data-open-create style="white-space:nowrap;">Create opportunity</button>
      </div>

      <form method="GET" action="business-development.php" class="filter-form" style="display:grid; gap:10px;">
        <div class="form-row" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
          <div style="flex:1; min-width:200px;">
            <label class="label" for="filter_business_line_id">Business Line</label>
            <select id="filter_business_line_id" name="filter_business_line_id">
              <option value="">All business lines</option>
              <?php foreach ($businessLineOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $filters['business_line_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="flex:1; min-width:220px;">
            <label class="label" for="filter_opportunity_owner_id">Opportunity Owner</label>
            <select id="filter_opportunity_owner_id" name="filter_opportunity_owner_id">
              <option value="">All opportunity owners</option>
              <?php foreach ($opportunityOwnerOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $filters['opportunity_owner_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="actions" style="justify-content:flex-start; gap:10px;">
          <button class="btn btn-update" type="submit">Apply Filters</button>
          <a class="btn btn-delete" href="business-development.php" style="text-decoration:none;">Reset</a>
        </div>
      </form>

      <?php if (!$opportunities): ?>
        <div class="empty-state">No opportunities recorded yet. Use the Create opportunity button to add one.</div>
      <?php endif; ?>

      <div class="opportunity-grid">
        <?php foreach ($opportunities as $opportunity): ?>
          <?php
            $businessLineName = $opportunity['business_line_name'] ?: 'Business line not set';
            $flagClass = $opportunity['submission_date'] ? 'is-submitted' : 'is-missing';
            $flagLabel = $opportunity['submission_date'] ? 'Submitted' : 'No submission';
          ?>
          <div class="module-card module-card--no-image opportunity-card" tabindex="0">
            <span class="module-card__status opportunity-status" aria-label="Business line">
              <?php echo safe($businessLineName); ?>
            </span>
            <span class="opportunity-flag <?php echo safe($flagClass); ?>" aria-label="Submission status">
              <?php echo safe($flagLabel); ?>
            </span>
            <div class="module-card__body" style="display:grid; gap:6px; align-content:start;">
              <h4><?php echo safe($opportunity['project_name']); ?></h4>
              <p><small>Client: <?php echo safe($opportunity['client'] ?: '—'); ?> | Location: <?php echo safe($opportunity['location'] ?: '—'); ?></small></p>
              <p><small>Owner: <?php echo safe($opportunity['opportunity_owner_name'] ?: $opportunity['opportunity_owner_id'] ?: '—'); ?></small></p>
              <p><small>Status: <?php echo safe($opportunity['status'] ?: '—'); ?></small></p>
            </div>
            <div class="opportunity-card__footer">
              <button class="btn btn-update" type="button" data-open-manage="<?php echo safe($opportunity['business_dev_id']); ?>">Manage</button>
              <button class="btn btn-neutral" type="button" data-open-details="<?php echo safe($opportunity['business_dev_id']); ?>">View details</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php foreach ($opportunities as $opportunity): ?>
        <div class="message-modal project-modal" data-manage-modal="<?php echo safe($opportunity['business_dev_id']); ?>" role="dialog" aria-modal="true" aria-label="Manage opportunity <?php echo safe($opportunity['project_name']); ?>">
          <div class="message-dialog">
            <div class="message-dialog__header">
              <span class="message-title">Manage <?php echo safe($opportunity['project_name']); ?></span>
              <button class="message-close" type="button" aria-label="Close manage opportunity" data-close-modal>&times;</button>
            </div>
            <form method="POST" action="business-development.php" style="display:grid; gap:12px;">
              <input type="hidden" name="action" value="update" />
              <input type="hidden" name="business_dev_id" value="<?php echo safe($opportunity['business_dev_id']); ?>" />
              <div class="opportunity-form-grid">
                <div>
                  <label class="label" for="project-name-<?php echo safe($opportunity['business_dev_id']); ?>">Project Name</label>
                  <input id="project-name-<?php echo safe($opportunity['business_dev_id']); ?>" name="project_name" type="text" value="<?php echo safe($opportunity['project_name']); ?>" required />
                </div>
                <div>
                  <label class="label" for="location-<?php echo safe($opportunity['business_dev_id']); ?>">Location</label>
                  <input id="location-<?php echo safe($opportunity['business_dev_id']); ?>" name="location" type="text" value="<?php echo safe($opportunity['location']); ?>" />
                </div>
                <div>
                  <label class="label" for="client-<?php echo safe($opportunity['business_dev_id']); ?>">Client</label>
                  <input id="client-<?php echo safe($opportunity['business_dev_id']); ?>" name="client" type="text" value="<?php echo safe($opportunity['client']); ?>" />
                </div>
                <div>
                  <label class="label" for="consultant-<?php echo safe($opportunity['business_dev_id']); ?>">Consultant</label>
                  <input id="consultant-<?php echo safe($opportunity['business_dev_id']); ?>" name="consultant" type="text" value="<?php echo safe($opportunity['consultant']); ?>" />
                </div>
                <div>
                  <label class="label" for="status-<?php echo safe($opportunity['business_dev_id']); ?>">Status</label>
                  <input id="status-<?php echo safe($opportunity['business_dev_id']); ?>" name="status" type="text" value="<?php echo safe($opportunity['status']); ?>" />
                </div>
                <div>
                  <label class="label" for="invitation-<?php echo safe($opportunity['business_dev_id']); ?>">Date of Invitation</label>
                  <input id="invitation-<?php echo safe($opportunity['business_dev_id']); ?>" name="date_of_invitation" type="date" value="<?php echo safe($opportunity['date_of_invitation']); ?>" />
                </div>
                <div>
                  <label class="label" for="submission-<?php echo safe($opportunity['business_dev_id']); ?>">Submission Date</label>
                  <input id="submission-<?php echo safe($opportunity['business_dev_id']); ?>" name="submission_date" type="date" value="<?php echo safe($opportunity['submission_date']); ?>" />
                </div>
                <div>
                  <label class="label" for="contact-name-<?php echo safe($opportunity['business_dev_id']); ?>">Contact Person Name</label>
                  <input id="contact-name-<?php echo safe($opportunity['business_dev_id']); ?>" name="contact_person_name" type="text" value="<?php echo safe($opportunity['contact_person_name']); ?>" />
                </div>
                <div>
                  <label class="label" for="contact-title-<?php echo safe($opportunity['business_dev_id']); ?>">Contact Person Title</label>
                  <input id="contact-title-<?php echo safe($opportunity['business_dev_id']); ?>" name="contact_person_title" type="text" value="<?php echo safe($opportunity['contact_person_title']); ?>" />
                </div>
                <div>
                  <label class="label" for="contact-phone-<?php echo safe($opportunity['business_dev_id']); ?>">Contact Person Phone</label>
                  <input id="contact-phone-<?php echo safe($opportunity['business_dev_id']); ?>" name="contact_person_phone" type="text" value="<?php echo safe($opportunity['contact_person_phone']); ?>" />
                </div>
                <div class="span-full">
                  <label class="label" for="remarks-<?php echo safe($opportunity['business_dev_id']); ?>">Remarks</label>
                  <input id="remarks-<?php echo safe($opportunity['business_dev_id']); ?>" name="remarks" type="text" value="<?php echo safe($opportunity['remarks']); ?>" />
                </div>
                <div>
                  <label class="label" for="business-line-<?php echo safe($opportunity['business_dev_id']); ?>">Business Line</label>
                  <select id="business-line-<?php echo safe($opportunity['business_dev_id']); ?>" name="business_line_id" required>
                    <option value="">-- Select Business Line --</option>
                    <?php foreach ($businessLineOptions as $option): ?>
                      <option value="<?php echo safe($option['value']); ?>" <?php echo $opportunity['business_line_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="label" for="owner-<?php echo safe($opportunity['business_dev_id']); ?>">Opportunity Owner</label>
                  <select id="owner-<?php echo safe($opportunity['business_dev_id']); ?>" name="opportunity_owner_id" required>
                    <option value="">-- Select Opportunity Owner --</option>
                    <?php foreach ($opportunityOwnerOptions as $option): ?>
                      <option value="<?php echo safe($option['value']); ?>" <?php echo $opportunity['opportunity_owner_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="actions" style="justify-content:flex-end; gap:10px;">
                <button class="btn btn-update" type="submit">Update opportunity</button>
              </div>
            </form>
            <form method="POST" action="business-development.php" onsubmit="return confirm('Delete this opportunity?');" style="display:flex; justify-content:flex-end;">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="business_dev_id" value="<?php echo safe($opportunity['business_dev_id']); ?>" />
              <button class="btn btn-delete" type="submit">Delete opportunity</button>
            </form>
          </div>
        </div>

        <div class="message-modal project-modal" data-details-modal="<?php echo safe($opportunity['business_dev_id']); ?>" role="dialog" aria-modal="true" aria-label="Opportunity details for <?php echo safe($opportunity['project_name']); ?>">
          <div class="message-dialog">
            <div class="message-dialog__header">
              <span class="message-title">Opportunity details</span>
              <button class="message-close" type="button" aria-label="Close opportunity details" data-close-modal>&times;</button>
            </div>
            <div class="details-grid">
              <div class="details-grid__item">
                <h5>Project name</h5>
                <p><?php echo safe($opportunity['project_name']); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Business line</h5>
                <p><?php echo safe($opportunity['business_line_name'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Opportunity owner</h5>
                <p><?php echo safe($opportunity['opportunity_owner_name'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Client</h5>
                <p><?php echo safe($opportunity['client'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Location</h5>
                <p><?php echo safe($opportunity['location'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Consultant</h5>
                <p><?php echo safe($opportunity['consultant'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Status</h5>
                <p><?php echo safe($opportunity['status'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Date of invitation</h5>
                <p><?php echo safe($opportunity['date_of_invitation'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Submission date</h5>
                <p><?php echo safe($opportunity['submission_date'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Contact name</h5>
                <p><?php echo safe($opportunity['contact_person_name'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Contact title</h5>
                <p><?php echo safe($opportunity['contact_person_title'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item">
                <h5>Contact phone</h5>
                <p><?php echo safe($opportunity['contact_person_phone'] ?: '—'); ?></p>
              </div>
              <div class="details-grid__item" style="grid-column: 1 / -1;">
                <h5>Remarks</h5>
                <p><?php echo safe($opportunity['remarks'] ?: '—'); ?></p>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <div class="message-modal project-modal" id="create-opportunity-modal" role="dialog" aria-modal="true" aria-label="Create opportunity">
    <div class="message-dialog">
      <div class="message-dialog__header">
        <span class="message-title">Create a new opportunity</span>
        <button class="message-close" type="button" aria-label="Close create opportunity" data-close-modal>&times;</button>
      </div>
      <form method="POST" action="business-development.php" style="display:grid; gap:12px;">
        <input type="hidden" name="action" value="create" />
        <div class="opportunity-form-grid">
          <div>
            <label class="label" for="project-name">Project Name</label>
            <input id="project-name" name="project_name" type="text" placeholder="New Opportunity" value="<?php echo safe($submitted['project_name']); ?>" required />
          </div>
          <div>
            <label class="label" for="location">Location</label>
            <input id="location" name="location" type="text" placeholder="Cairo" value="<?php echo safe($submitted['location']); ?>" />
          </div>
          <div>
            <label class="label" for="client">Client</label>
            <input id="client" name="client" type="text" placeholder="Client name" value="<?php echo safe($submitted['client']); ?>" />
          </div>
          <div>
            <label class="label" for="consultant">Consultant</label>
            <input id="consultant" name="consultant" type="text" placeholder="Consultant name" value="<?php echo safe($submitted['consultant']); ?>" />
          </div>
          <div>
            <label class="label" for="status">Status</label>
            <input id="status" name="status" type="text" placeholder="Pending" value="<?php echo safe($submitted['status']); ?>" />
          </div>
          <div>
            <label class="label" for="date-of-invitation">Date of Invitation</label>
            <input id="date-of-invitation" name="date_of_invitation" type="date" value="<?php echo safe($submitted['date_of_invitation']); ?>" />
          </div>
          <div>
            <label class="label" for="submission-date">Submission Date</label>
            <input id="submission-date" name="submission_date" type="date" value="<?php echo safe($submitted['submission_date']); ?>" />
          </div>
          <div>
            <label class="label" for="contact-person-name">Contact Person Name</label>
            <input id="contact-person-name" name="contact_person_name" type="text" placeholder="Contact name" value="<?php echo safe($submitted['contact_person_name']); ?>" />
          </div>
          <div>
            <label class="label" for="contact-person-title">Contact Person Title</label>
            <input id="contact-person-title" name="contact_person_title" type="text" placeholder="Title" value="<?php echo safe($submitted['contact_person_title']); ?>" />
          </div>
          <div>
            <label class="label" for="contact-person-phone">Contact Person Phone</label>
            <input id="contact-person-phone" name="contact_person_phone" type="text" placeholder="Phone" value="<?php echo safe($submitted['contact_person_phone']); ?>" />
          </div>
          <div class="span-full">
            <label class="label" for="remarks">Remarks</label>
            <input id="remarks" name="remarks" type="text" placeholder="Notes" value="<?php echo safe($submitted['remarks']); ?>" />
          </div>
          <div>
            <label class="label" for="business-line">Business Line</label>
            <select id="business-line" name="business_line_id" required>
              <option value="">-- Select Business Line --</option>
              <?php foreach ($businessLineOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['business_line_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="opportunity-owner">Opportunity Owner</label>
            <select id="opportunity-owner" name="opportunity_owner_id" required>
              <option value="">-- Select Opportunity Owner --</option>
              <?php foreach ($opportunityOwnerOptions as $option): ?>
                <option value="<?php echo safe($option['value']); ?>" <?php echo $submitted['opportunity_owner_id'] === $option['value'] ? 'selected' : ''; ?>><?php echo safe($option['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="actions" style="justify-content:flex-end; gap:10px;">
          <button class="btn" type="button" data-close-modal>Cancel</button>
          <button class="btn btn-save" type="submit">Create opportunity</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const closeButtons = document.querySelectorAll('[data-close-modal]');
      const openCreateButtons = document.querySelectorAll('[data-open-create]');
      const createModal = document.getElementById('create-opportunity-modal');

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
    });
  </script>
</body>
</html>