<?php
require_once __DIR__ . '/helpers.php';

$currentUser = require_login();
$moduleCode = resolve_module_code('BUSINESS_DEVELOPMENT');
$resolvedModuleCode = $moduleCode ?? 'BUSINESS_DEVELOPMENT';
$canCreate = has_crud_permission($currentUser, $resolvedModuleCode, 'create');
$canRead = has_crud_permission($currentUser, $resolvedModuleCode, 'read');
$canUpdate = has_crud_permission($currentUser, $resolvedModuleCode, 'update');
$canDelete = has_crud_permission($currentUser, $resolvedModuleCode, 'delete');

$error = '';
$success = '';
$successHtml = '';
$modalOverride = null;
$metabaseUrl = getenv('METABASE_URL') ?: 'http://localhost:3000/dashboard/37-business-development-dashboard?business_line=&date_of_invitation=&opportunity_owner=&status=&submission_date=';

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

const OPPORTUNITY_UPLOAD_DIR = __DIR__ . '/assets/uploads/opportunities';
const OPPORTUNITY_UPLOAD_PUBLIC_DIR = 'assets/uploads/opportunities';

function option_label(array $options, string $value): string
{
    foreach ($options as $option) {
        if ((string) ($option['value'] ?? '') === $value) {
            return (string) ($option['label'] ?? $value);
        }
    }

    return $value;
}

function normalize_opportunity_files(?string $stored): array
{
    if ($stored === null) {
        return [];
    }

    $trimmed = trim($stored);
    if ($trimmed === '') {
        return [];
    }

    $decoded = json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return array_values(array_filter($decoded, static function ($value) {
            return is_string($value) && trim($value) !== '';
        }));
    }

    return [$trimmed];
}

function serialize_opportunity_files(array $files): string
{
    $filtered = array_values(array_filter($files, static function ($value) {
        return is_string($value) && trim($value) !== '';
    }));

    if ($filtered === []) {
        return '';
    }

    return json_encode($filtered, JSON_UNESCAPED_SLASHES);
}

function normalize_upload_batch(array $upload): array
{
    if (!is_array($upload['name'])) {
        return [$upload];
    }

    $files = [];
    foreach ($upload['name'] as $index => $name) {
        $files[] = [
            'name' => $name,
            'type' => $upload['type'][$index] ?? '',
            'tmp_name' => $upload['tmp_name'][$index] ?? '',
            'error' => $upload['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $upload['size'][$index] ?? 0,
        ];
    }

    return $files;
}

function store_opportunity_uploads(array $upload): array
{
    $storedPaths = [];
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
    $maxSize = 10 * 1024 * 1024;

    foreach (normalize_upload_batch($upload) as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error uploading file.');
        }

        $originalName = basename((string) ($file['name'] ?? ''));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, $allowed, true)) {
            throw new RuntimeException('Please upload a valid file (pdf, doc, docx, xls, xlsx, png, jpg, jpeg).');
        }

        if (!empty($file['size']) && $file['size'] > $maxSize) {
            throw new RuntimeException('Please upload a file smaller than 10MB.');
        }

        if (!is_dir(OPPORTUNITY_UPLOAD_DIR)) {
            mkdir(OPPORTUNITY_UPLOAD_DIR, 0755, true);
        }

        $fileName = bin2hex(random_bytes(12)) . '.' . $extension;
        $targetPath = OPPORTUNITY_UPLOAD_DIR . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Unable to save the uploaded file.');
        }

        $storedPaths[] = OPPORTUNITY_UPLOAD_PUBLIC_DIR . '/' . $fileName;
    }

    return $storedPaths;
}

$submitted = [
    'business_dev_id' => trim($_POST['business_dev_id'] ?? ''),
    'project_name' => trim($_POST['project_name'] ?? ''),
    'location' => trim($_POST['location'] ?? ''),
    'client' => trim($_POST['client'] ?? ''),
    'date_of_invitation' => trim($_POST['date_of_invitation'] ?? ''),
    'submission_date' => trim($_POST['submission_date'] ?? ''),
    'approvalstatus' => trim($_POST['approvalstatus'] ?? ''),
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
        $resolvedModuleCode,
        $action,
        [
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            'bulk_delete' => 'delete',
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

           $uploadedPaths = [];
            $upload = $_FILES['opportunity_file'] ?? null;
            if ($upload) {
                $uploadedPaths = store_opportunity_uploads($upload);
            }
            $storedFiles = serialize_opportunity_files($uploadedPaths);

            $duplicateCheck = $pdo->prepare(
                "SELECT 1
                 FROM business_development
                 WHERE project_name = :project_name
                   AND location IS NOT DISTINCT FROM NULLIF(:location, '')
                   AND client IS NOT DISTINCT FROM NULLIF(:client, '')
                   AND date_of_invitation IS NOT DISTINCT FROM NULLIF(:date_of_invitation, '')::date
                   AND submission_date IS NOT DISTINCT FROM NULLIF(:submission_date, '')::date
                   AND approvalstatus IS NOT DISTINCT FROM NULLIF(:approvalstatus, '')
                   AND contact_person_name IS NOT DISTINCT FROM NULLIF(:contact_person_name, '')
                   AND contact_person_title IS NOT DISTINCT FROM NULLIF(:contact_person_title, '')
                   AND contact_person_phone IS NOT DISTINCT FROM NULLIF(:contact_person_phone, '')
                   AND remarks IS NOT DISTINCT FROM NULLIF(:remarks, '')
                   AND business_line_id = :business_line_id
                   AND opportunity_owner_id = :opportunity_owner_id
                 LIMIT 1"
            );
            $duplicateCheck->execute([
                ':project_name' => $submitted['project_name'],
                ':location' => $submitted['location'],
                ':client' => $submitted['client'],
                ':date_of_invitation' => $submitted['date_of_invitation'],
                ':submission_date' => $submitted['submission_date'],
                ':approvalstatus' => $submitted['approvalstatus'],
                ':contact_person_name' => $submitted['contact_person_name'],
                ':contact_person_title' => $submitted['contact_person_title'],
                ':contact_person_phone' => $submitted['contact_person_phone'],
                ':remarks' => $submitted['remarks'],
                ':business_line_id' => $submitted['business_line_id'],
                ':opportunity_owner_id' => $submitted['opportunity_owner_id'],
            ]);

            if ($duplicateCheck->fetchColumn()) {
                throw new RuntimeException('This opportunity already exists with the same data.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO business_development (project_name, location, client, date_of_invitation, submission_date, \"approvalstatus\", contact_person_name, contact_person_title, contact_person_phone, remarks, business_line_id, opportunity_owner_id, opportunity_file)
                 VALUES (:project_name, NULLIF(:location, ''), NULLIF(:client, ''), NULLIF(:date_of_invitation, '')::date, NULLIF(:submission_date, '')::date, NULLIF(:approvalstatus, ''), NULLIF(:contact_person_name, ''), NULLIF(:contact_person_title, ''), NULLIF(:contact_person_phone, ''), NULLIF(:remarks, ''), :business_line_id, :opportunity_owner_id, NULLIF(:opportunity_file, ''))"
            );
            $stmt->execute([
                ':project_name' => $submitted['project_name'],
                ':location' => $submitted['location'],
                ':client' => $submitted['client'],
                ':date_of_invitation' => $submitted['date_of_invitation'],
                ':submission_date' => $submitted['submission_date'],
                ':approvalstatus' => $submitted['approvalstatus'],
                ':contact_person_name' => $submitted['contact_person_name'],
                ':contact_person_title' => $submitted['contact_person_title'],
                ':contact_person_phone' => $submitted['contact_person_phone'],
                ':remarks' => $submitted['remarks'],
                ':business_line_id' => $submitted['business_line_id'],
                ':opportunity_owner_id' => $submitted['opportunity_owner_id'],
                ':opportunity_file' => $storedFiles,
            ]);

            $newId = (int) $pdo->lastInsertId('business_development_business_dev_id_seq');
             $successDetails = [
                'Project name' => $submitted['project_name'],
                'Location' => $submitted['location'] ?: '—',
                'Client' => $submitted['client'] ?: '—',
                'Invitation date' => $submitted['date_of_invitation'] ?: '—',
                'Submission date' => $submitted['submission_date'] ?: '—',
                'Approval status' => $submitted['approvalstatus'] ?: '—',
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

           $currentFile = trim($_POST['current_opportunity_file'] ?? '');
            $upload = $_FILES['opportunity_file'] ?? null;
            $currentFiles = normalize_opportunity_files($currentFile);
            $uploadedPaths = [];
            if ($upload) {
                $uploadedPaths = store_opportunity_uploads($upload);
            }

            if ($uploadedPaths !== []) {
                $mergedFiles = array_values(array_unique(array_merge($currentFiles, $uploadedPaths)));
                $uploadedPath = serialize_opportunity_files($mergedFiles);
            } else {
                $uploadedPath = serialize_opportunity_files($currentFiles);
            }


            $stmt = $pdo->prepare(
                "UPDATE business_development
                 SET project_name = :project_name,
                     location = NULLIF(:location, ''),
                     client = NULLIF(:client, ''),
                     date_of_invitation = NULLIF(:date_of_invitation, '')::date,
                     submission_date = NULLIF(:submission_date, '')::date,
                     approvalstatus = NULLIF(:approvalstatus, ''),
                     contact_person_name = NULLIF(:contact_person_name, ''),
                     contact_person_title = NULLIF(:contact_person_title, ''),
                     contact_person_phone = NULLIF(:contact_person_phone, ''),
                     remarks = NULLIF(:remarks, ''),
                     business_line_id = :business_line_id,
                     opportunity_owner_id = :opportunity_owner_id,
                     opportunity_file = NULLIF(:opportunity_file, '')
                 WHERE business_dev_id = :business_dev_id"
            );
            $stmt->execute([
                ':business_dev_id' => $submitted['business_dev_id'],
                ':project_name' => $submitted['project_name'],
                ':location' => $submitted['location'],
                ':client' => $submitted['client'],
                ':date_of_invitation' => $submitted['date_of_invitation'],
                ':submission_date' => $submitted['submission_date'],
                ':approvalstatus' => $submitted['approvalstatus'],
                ':contact_person_name' => $submitted['contact_person_name'],
                ':contact_person_title' => $submitted['contact_person_title'],
                ':contact_person_phone' => $submitted['contact_person_phone'],
                ':remarks' => $submitted['remarks'],
                ':business_line_id' => $submitted['business_line_id'],
                ':opportunity_owner_id' => $submitted['opportunity_owner_id'],
                ':opportunity_file' => $uploadedPath,
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
        } elseif ($action === 'bulk_delete') {
            $selectedIds = array_map('intval', (array) ($_POST['selected_ids'] ?? []));
            if (!$selectedIds) {
                throw new RuntimeException('Select at least one opportunity to delete.');
            }

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM business_development WHERE business_dev_id IN ({$placeholders})");
            $stmt->execute($selectedIds);
            $success = $stmt->rowCount() . ' opportunity(s) removed.';
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
$totalOpportunities = 0;
$visibleCount = (int) ($_GET['visible_count'] ?? 4);
$visibleCount = max(4, $visibleCount);
if ($pdo && $canRead) {
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
        $countSql = "SELECT COUNT(*)
                     FROM business_development bd
                     {$whereSql}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalOpportunities = (int) $countStmt->fetchColumn();

        if ($visibleCount > $totalOpportunities && $totalOpportunities > 0) {
            $visibleCount = $totalOpportunities;
        }

        $sql = "SELECT bd.business_dev_id, bd.project_name, bd.location, bd.client, bd.date_of_invitation, bd.submission_date, bd.\"approvalstatus\" AS approvalstatus, bd.contact_person_name, bd.contact_person_title, bd.contact_person_phone, bd.remarks, bd.business_line_id, bd.opportunity_owner_id, bd.opportunity_file,
                       COALESCE(bl.business_line_name, '') AS business_line_name,
                       COALESCE(oo.opportunity_owner_name, '') AS opportunity_owner_name
                FROM business_development bd
                LEFT JOIN business_lines bl ON bl.business_line_id = bd.business_line_id
                LEFT JOIN opportunity_owners oo ON oo.opportunity_owner_id = bd.opportunity_owner_id
                {$whereSql}
                ORDER BY bd.business_dev_id DESC
                LIMIT :visible_count";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':visible_count', $visibleCount, PDO::PARAM_INT);
        $stmt->execute();
        $opportunities = $stmt->fetchAll();
     } catch (Throwable $e) {
        $error = $error ?: format_db_error($e, 'business_development table');
    }
}

if ($error === '' && !$canRead) {
    $modalOverride = permission_denied_modal();
    $error = "You don't have permission to read this module.";
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

    .opportunity-card__select {
      position: absolute;
      top: 10px;
      right: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px;
      border-radius: 10px;
    }

    .opportunity-card__select input[type="checkbox"] {
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

    .opportunity-card__select input[type="checkbox"]:checked {
      background: var(--secondary);
      border-color: var(--secondary);
      box-shadow: inset 0 0 0 2px #ffffffff;
    }

    .opportunity-card__select input[type="checkbox"]:focus-visible {
      outline: 2px solid #fff;
      outline-offset: 2px;
    }

    .opportunity-flag {
      position: absolute;
      top: 0px;
      left: 0px;
      border-radius: 0 0 8px 0;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }

    .opportunity-flag.is-missing {
      background: var(--secondary);
    }

    .opportunity-flag.is-submitted {
      background: rgba(5, 168, 43, 0.9);
    }

    .opportunity-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(220px, 1fr));
      gap: 12px;
    }

     .opportunity-form-grid .span-full {
      grid-column: 1 / -1;
    }

    .opportunity-manage-table {
      width: 100%;
      border-collapse: collapse;
    }

    .opportunity-manage-table td {
      padding: 6px 8px;
      vertical-align: top;
    }

    .opportunity-manage-table .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .manage-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 12px;
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

    .project-modal .message-dialog {
      max-width: 760px;
      max-height: 70vh;
      overflow-y: auto;
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
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <?php if ($canCreate): ?>
            <button class="btn btn-save" type="button" data-open-create style="white-space:nowrap;">Create opportunity</button>
          <?php endif; ?>
          <a class="btn btn-update" href="<?php echo safe($metabaseUrl); ?>" target="_blank" rel="noopener" style="white-space:nowrap; text-decoration:none;">Dashboard</a>
        </div>
      </div>
      <?php if ($canRead): ?>
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
      <?php endif; ?>

      <?php if ($canRead && !$opportunities): ?>
        <div class="empty-state">No opportunities recorded yet. Use the Create opportunity button to add one.</div>
      <?php endif; ?>

      <?php if ($canRead): ?>
        <?php if ($canDelete): ?>
          <form method="POST" action="business-development.php" onsubmit="return confirm('Delete selected opportunities?');" style="display:grid; gap:12px;">
            <input type="hidden" name="action" value="bulk_delete" />
            <div class="actions" style="justify-content:flex-end; gap:10px;">
              <button class="btn btn-delete" type="submit">Delete selected</button>
            </div>
            <div class="opportunity-grid">
        <?php else: ?>
          <div class="opportunity-grid">
        <?php endif; ?>
          <?php foreach ($opportunities as $opportunity): ?>
            <?php
              $businessLineName = $opportunity['business_line_name'] ?: 'Business line not set';
              $flagClass = $opportunity['submission_date'] ? 'is-submitted' : 'is-missing';
              $flagLabel = $opportunity['submission_date'] ? 'Submitted' : 'Pending';
            ?>
            <div class="module-card module-card--no-image opportunity-card"
                 tabindex="0"
                 data-opportunity-id="<?php echo safe($opportunity['business_dev_id']); ?>"
                 data-project-name="<?php echo safe($opportunity['project_name']); ?>"
                 data-location="<?php echo safe($opportunity['location']); ?>"
                 data-client="<?php echo safe($opportunity['client']); ?>"
                 data-date-of-invitation="<?php echo safe($opportunity['date_of_invitation']); ?>"
                 data-submission-date="<?php echo safe($opportunity['submission_date']); ?>"
                 data-approval-status="<?php echo safe($opportunity['approvalstatus']); ?>"
                 data-contact-person-name="<?php echo safe($opportunity['contact_person_name']); ?>"
                 data-contact-person-title="<?php echo safe($opportunity['contact_person_title']); ?>"
                 data-contact-person-phone="<?php echo safe($opportunity['contact_person_phone']); ?>"
                 data-remarks="<?php echo safe($opportunity['remarks']); ?>"
                 data-business-line-id="<?php echo safe($opportunity['business_line_id']); ?>"
                 data-opportunity-owner-id="<?php echo safe($opportunity['opportunity_owner_id']); ?>"
                 data-business-line-name="<?php echo safe($opportunity['business_line_name']); ?>"
                 data-opportunity-owner-name="<?php echo safe($opportunity['opportunity_owner_name']); ?>"
                 data-opportunity-file="<?php echo safe($opportunity['opportunity_file']); ?>">
              <span class="opportunity-flag <?php echo safe($flagClass); ?>" aria-label="Submission status">
                <?php echo safe($flagLabel); ?>
              </span>
              <?php if ($canDelete): ?>
                <label class="opportunity-card__select" title="Select opportunity" aria-label="Select opportunity">
                  <input type="checkbox" name="selected_ids[]" value="<?php echo safe($opportunity['business_dev_id']); ?>" />
                </label>
              <?php endif; ?>
              <div class="module-card__body" style="display:grid; gap:6px; align-content:start;">
                <h4><?php echo safe($opportunity['project_name']); ?></h4>
                <p><small>Client: <?php echo safe($opportunity['client'] ?: '—'); ?> | Business line: <?php echo safe($businessLineName); ?></small></p>
                <p><small>Owner: <?php echo safe($opportunity['opportunity_owner_name'] ?: $opportunity['opportunity_owner_id'] ?: '—'); ?></small></p>
              </div>
              <div class="opportunity-card__footer">
                <?php if ($canUpdate): ?>
                  <button class="btn btn-update" type="button" data-open-manage="<?php echo safe($opportunity['business_dev_id']); ?>">Manage</button>
                <?php endif; ?>
                <button class="btn btn-neutral" type="button" data-open-details="<?php echo safe($opportunity['business_dev_id']); ?>">View details</button>
              </div>
             </div>
          <?php endforeach; ?>
       <?php if ($canDelete): ?>
            </div>
          </form>
        <?php else: ?>
          </div>
        <?php endif; ?>
        <?php if ($totalOpportunities > 0): ?>
          <div class="actions" style="justify-content:space-between; margin-top:12px;">
            <span style="color:var(--muted);">
              Showing <?php echo safe((string) count($opportunities)); ?> of <?php echo safe((string) $totalOpportunities); ?> opportunities
            </span>
            <?php if ($visibleCount < $totalOpportunities): ?>
              <form method="GET" action="business-development.php">
                <input type="hidden" name="filter_business_line_id" value="<?php echo safe($filters['business_line_id']); ?>" />
                <input type="hidden" name="filter_opportunity_owner_id" value="<?php echo safe($filters['opportunity_owner_id']); ?>" />
                <input type="hidden" name="visible_count" value="<?php echo safe((string) ($visibleCount + 4)); ?>" />
                <button class="btn btn-update" type="submit">View more</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="message-modal project-modal" id="manage-opportunity-modal" role="dialog" aria-modal="true" aria-label="Manage opportunity">
        <div class="message-dialog">
          <div class="message-dialog__header">
            <span class="message-title" id="manage-opportunity-title">Manage opportunity</span>
            <button class="message-close" type="button" aria-label="Close manage opportunity" data-close-modal>&times;</button>
          </div>
          <form id="update-form" method="POST" action="business-development.php" enctype="multipart/form-data" style="display:grid; gap:12px;">
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="business_dev_id" id="manage-business-dev-id" value="" />
            <input type="hidden" name="current_opportunity_file" id="manage-current-file" value="" />
            <table class="opportunity-manage-table">
              <tr>
                <td>
                  <div class="field">
                    <label class="label" for="manage-project-name">Project Name</label>
                    <input id="manage-project-name" name="project_name" type="text" required />
                  </div>
                </td>
                <td>
                  <div class="field">
                    <label class="label" for="manage-location">Location</label>
                    <input id="manage-location" name="location" type="text" />
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="field">
                    <label class="label" for="manage-client">Client</label>
                    <input id="manage-client" name="client" type="text" />
                  </div>
                </td>
                <td>
                  <div class="field">
                    <label class="label" for="manage-invitation">Date of Invitation</label>
                    <input id="manage-invitation" name="date_of_invitation" type="date" />
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="field">
                    <label class="label" for="manage-submission">Submission Date</label>
                    <input id="manage-submission" name="submission_date" type="date" />
                  </div>
                </td>
                <td>
                  <div class="field">
                    <label class="label" for="manage-approval-status">Approval Status</label>
                    <input id="manage-approval-status" name="approvalstatus" type="text" />
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="field">
                    <label class="label" for="manage-contact-name">Contact Person Name</label>
                    <input id="manage-contact-name" name="contact_person_name" type="text" />
                  </div>
                </td>
                <td>
                  <div class="field">
                    <label class="label" for="manage-contact-title">Contact Person Title</label>
                    <input id="manage-contact-title" name="contact_person_title" type="text" />
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="field">
                    <label class="label" for="manage-contact-phone">Contact Person Phone</label>
                    <input id="manage-contact-phone" name="contact_person_phone" type="text" />
                  </div>
                </td>
                <td>
                  <div class="field">
                    <label class="label" for="manage-remarks">Remarks</label>
                    <input id="manage-remarks" name="remarks" type="text" />
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <div class="field">
                    <label class="label" for="manage-business-line">Business Line</label>
                    <select id="manage-business-line" name="business_line_id" required>
                      <option value="">-- Select Business Line --</option>
                      <?php foreach ($businessLineOptions as $option): ?>
                        <option value="<?php echo safe($option['value']); ?>"><?php echo safe($option['label']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </td>
                <td>
                  <div class="field">
                    <label class="label" for="manage-owner">Opportunity Owner</label>
                    <select id="manage-owner" name="opportunity_owner_id" required>
                      <option value="">-- Select Opportunity Owner --</option>
                      <?php foreach ($opportunityOwnerOptions as $option): ?>
                        <option value="<?php echo safe($option['value']); ?>"><?php echo safe($option['label']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </td>
              </tr>
              <tr>
                <td colspan="2">
                  <div class="field">
                     <label class="label" for="manage-opportunity-file">Upload/replace files</label>
                    <input id="manage-opportunity-file" name="opportunity_file[]" type="file" multiple />
                    <small id="manage-current-file-link" style="color:var(--muted);"></small>
                  </div>
                </td>
              </tr>
            </table>
          </form>
          <?php if ($canDelete): ?>
            <form id="delete-form" method="POST" action="business-development.php" onsubmit="return confirm('Delete this opportunity?');">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="business_dev_id" id="delete-business-dev-id" value="" />
            </form>
          <?php endif; ?>
          <div class="manage-actions">
            <?php if ($canUpdate): ?>
              <button class="btn btn-update" type="submit" form="update-form">Update opportunity</button>
            <?php endif; ?>
            <?php if ($canDelete): ?>
              <button class="btn btn-delete" type="submit" form="delete-form">Delete opportunity</button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="message-modal project-modal" id="details-opportunity-modal" role="dialog" aria-modal="true" aria-label="Opportunity details">
        <div class="message-dialog">
          <div class="message-dialog__header">
            <span class="message-title">Opportunity details</span>
            <button class="message-close" type="button" aria-label="Close opportunity details" data-close-modal>&times;</button>
          </div>
          <div class="details-grid">
            <div class="details-grid__item">
              <h5>Project name</h5>
              <p data-detail="project_name">—</p>
            </div>
            <div class="details-grid__item">
              <h5>Business line</h5>
              <p data-detail="business_line_name">—</p>
            </div>
            <div class="details-grid__item">
              <h5>Opportunity owner</h5>
              <p data-detail="opportunity_owner_name">—</p>
            </div>
            <div class="details-grid__item">
              <h5>Client</h5>
              <p data-detail="client">—</p>
            </div>
             <div class="details-grid__item">
              <h5>Location</h5>
              <p data-detail="location">—</p>
            </div>
            <div class="details-grid__item">
              <h5>Date of invitation</h5>
              <p data-detail="date_of_invitation">—</p>
            </div>
            <div class="details-grid__item">
              <h5>Submission date</h5>
              <p data-detail="submission_date">—</p>
            </div>
            <div class="details-grid__item">
              <h5>Approval status</h5>
              <p data-detail="approvalstatus">—</p>
            </div>
            <div class="details-grid__item">
              <h5>Contact name</h5>
              <p data-detail="contact_person_name">—</p>
            </div>
            <div class="details-grid__item">
              <h5>Contact title</h5>
              <p data-detail="contact_person_title">—</p>
            </div>
            <div class="details-grid__item">
              <h5>Contact phone</h5>
              <p data-detail="contact_person_phone">—</p>
            </div>
            <div class="details-grid__item" style="grid-column: 1 / -1;">
              <h5>Remarks</h5>
              <p data-detail="remarks">—</p>
            </div>
            <div class="details-grid__item" style="grid-column: 1 / -1;">
              <h5>Uploaded file</h5>
              <p data-detail="opportunity_file">—</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

   <?php if ($canCreate): ?>
    <div class="message-modal project-modal" id="create-opportunity-modal" role="dialog" aria-modal="true" aria-label="Create opportunity">
      <div class="message-dialog">
        <div class="message-dialog__header">
          <span class="message-title">Create a new opportunity</span>
          <button class="message-close" type="button" aria-label="Close create opportunity" data-close-modal>&times;</button>
        </div>
        <form method="POST" action="business-development.php" enctype="multipart/form-data" style="display:grid; gap:12px;">
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
              <label class="label" for="date-of-invitation">Date of Invitation</label>
              <input id="date-of-invitation" name="date_of_invitation" type="date" value="<?php echo safe($submitted['date_of_invitation']); ?>" />
            </div>
            <div>
              <label class="label" for="submission-date">Submission Date</label>
              <input id="submission-date" name="submission_date" type="date" value="<?php echo safe($submitted['submission_date']); ?>" />
            </div>
            <div>
              <label class="label" for="approval-status">Approval Status</label>
              <input id="approval-status" name="approvalstatus" type="text" placeholder="Pending" value="<?php echo safe($submitted['approvalstatus']); ?>" />
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
            <div class="span-full">
              <label class="label" for="opportunity-file">Attach files</label>
              <input id="opportunity-file" name="opportunity_file[]" type="file" multiple />
            </div>
          </div>
          <div class="actions" style="justify-content:flex-end; gap:10px;">
            <button class="btn" type="button" data-close-modal>Cancel</button>
            <button class="btn btn-save" type="submit">Create opportunity</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const closeButtons = document.querySelectorAll('[data-close-modal]');
      const openCreateButtons = document.querySelectorAll('[data-open-create]');
      const createModal = document.getElementById('create-opportunity-modal');
      const manageModal = document.getElementById('manage-opportunity-modal');
      const detailsModal = document.getElementById('details-opportunity-modal');
      const manageTitle = document.getElementById('manage-opportunity-title');
      const manageForm = document.getElementById('update-form');
      const manageCurrentFile = document.getElementById('manage-current-file');
      const manageCurrentFileLink = document.getElementById('manage-current-file-link');
      const deleteBusinessDevId = document.getElementById('delete-business-dev-id');

      const parseFiles = (value) => {
        if (!value) return [];
        try {
          const parsed = JSON.parse(value);
          if (Array.isArray(parsed)) {
            return parsed.filter((entry) => entry);
          }
        } catch (error) {
          // Not JSON, fall back to single entry.
        }
        return value ? [value] : [];
      };

      const buildFileLinks = (files) => {
        return files
          .map((file, index) => `<a href="${file}" target="_blank" rel="noopener">File ${index + 1}</a>`)
          .join('<br>');
      };      const hideModal = (modal) => {
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
        button.addEventListener('click', () => {
          const card = button.closest('.opportunity-card');
          if (!card) return;
          const data = card.dataset;

          if (manageTitle) {
            manageTitle.textContent = `Manage ${data.projectName || 'opportunity'}`;
          }

          const fieldMap = {
            'manage-business-dev-id': data.opportunityId,
            'manage-project-name': data.projectName,
            'manage-location': data.location,
            'manage-client': data.client,
            'manage-invitation': data.dateOfInvitation,
            'manage-submission': data.submissionDate,
            'manage-approval-status': data.approvalStatus,
            'manage-contact-name': data.contactPersonName,
            'manage-contact-title': data.contactPersonTitle,
            'manage-contact-phone': data.contactPersonPhone,
            'manage-remarks': data.remarks
          };

          const businessLineSelect = document.getElementById('manage-business-line');
          const ownerSelect = document.getElementById('manage-owner');

          if (manageForm) {
            manageForm.reset();
            Object.entries(fieldMap).forEach(([id, value]) => {
              const input = document.getElementById(id);
              if (input) {
                input.value = value || '';
              }
            });
            if (businessLineSelect) {
              businessLineSelect.value = data.businessLineId || '';
            }
            if (ownerSelect) {
              ownerSelect.value = data.opportunityOwnerId || '';
            }
          }

           if (manageCurrentFile) {
            manageCurrentFile.value = data.opportunityFile || '';
          }

          if (manageCurrentFileLink) {
            const files = parseFiles(data.opportunityFile);
            if (files.length) {
              manageCurrentFileLink.innerHTML = buildFileLinks(files);
            } else {
              manageCurrentFileLink.textContent = 'No files uploaded yet.';
            }
          }
          if (deleteBusinessDevId) {
            deleteBusinessDevId.value = data.opportunityId || '';
          }

          showModal(manageModal);
        });
      });

      document.querySelectorAll('[data-open-details]').forEach((button) => {
        button.addEventListener('click', () => {
          const card = button.closest('.opportunity-card');
          if (!card) return;
          const data = card.dataset;
          const detailMap = {
            project_name: data.projectName,
            business_line_name: data.businessLineName,
            opportunity_owner_name: data.opportunityOwnerName,
            client: data.client,
            location: data.location,
            date_of_invitation: data.dateOfInvitation,
            submission_date: data.submissionDate,
            approvalstatus: data.approvalStatus,
            contact_person_name: data.contactPersonName,
            contact_person_title: data.contactPersonTitle,
            contact_person_phone: data.contactPersonPhone,
            remarks: data.remarks,
            opportunity_file: data.opportunityFile
          };

          Object.entries(detailMap).forEach(([key, value]) => {
            const target = detailsModal?.querySelector(`[data-detail="${key}"]`);
            if (!target) return;
            if (key === 'opportunity_file') {
              const files = parseFiles(value);
              if (files.length) {
                target.innerHTML = buildFileLinks(files);
              } else {
                target.textContent = '—';
              }
              return;
            }
            target.textContent = value || '—';
          });

          showModal(detailsModal);
        });
      });
    });
  </script>
</body>
</html>