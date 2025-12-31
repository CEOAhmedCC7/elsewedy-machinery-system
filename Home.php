<?php
require_once __DIR__ . '/helpers.php';

$user = require_login();

$modules = fetch_table('modules', 'module_name');
$isAdmin = strtolower((string) ($user['role'] ?? '')) === 'admin';
$userId = isset($user['user_id']) ? (int) $user['user_id'] : 0;
$modulePermissions = [];

if (!empty($user['user_id'])) {
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'SELECT rp.module_id, rp.can_create, rp.can_read, rp.can_update, rp.can_delete
             FROM role_module_permissions rp
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id'
        );
        $stmt->execute([':user_id' => $user['user_id']]);

        foreach ($stmt->fetchAll() as $row) {
            $moduleId = (int) $row['module_id'];
            $modulePermissions[$moduleId] = [
                'create' => filter_var($row['can_create'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'read' => filter_var($row['can_read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'update' => filter_var($row['can_update'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'delete' => filter_var($row['can_delete'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ];
        }
    } catch (Throwable $e) {
        error_log('Failed to load role permissions: ' . $e->getMessage());
    }
}

if (!$modules) {
    $modules = [
        [
            'module_code' => 'ROLE',
            'module_name' => 'Role Management',
            'href' => './role-access.php',
            'description' => 'Manage roles and permissions for module access.',
        ],
        [
            'module_code' => 'USER',
            'module_name' => 'User Management',
            'href' => './users.php',
            'description' => 'CRUD users, assign roles, and control account status.',
        ],
    ];
}

function normalize_module_card_image(string $rawImage): string
{
    $sanitizedImage = trim($rawImage, "{} \" ");

    if ($sanitizedImage === '') {
        return './assets/Wallpaper.png';
    }

    $hasProtocol = preg_match('/^https?:\\/\\//i', $sanitizedImage) === 1;
    $hasLeadingSlash = strncmp($sanitizedImage, '/', 1) === 0 || strncmp($sanitizedImage, './', 2) === 0;
    $startsWithAssetsDir = strncmp($sanitizedImage, 'assets/', 7) === 0;

    if ($hasProtocol || $hasLeadingSlash) {
        return $sanitizedImage;
    }

    if ($startsWithAssetsDir) {
        return './' . $sanitizedImage;
    }

    return './assets/' . ltrim($sanitizedImage, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Home | Elsewedy Machinery</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page" data-user-role="<?php echo safe(strtolower((string) ($user['role'] ?? ''))); ?>" data-user-id="<?php echo safe((string) $userId); ?>">
  <header class="navbar">
    <div class="header">
      <img src="../EM%20Logo.jpg" alt="Elsewedy Machinery" class="logo" />
    </div>
    <div class="title">Home</div>
    <div class="links">
      <?php if ($isAdmin): ?>
        <button type="button" class="nav-button" id="reorder-toggle">Edit order</button>
      <?php endif; ?>
      <div class="user-chip">
        <span class="name"><?php echo safe($user['username']); ?></span>
        <span class="role"><?php echo strtoupper(safe($user['role'] ?? '')); ?></span>
      </div>
      <a class="logout-icon" href="./logout.php" aria-label="Logout">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M15 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M10 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M16 8l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
    </div>
  </header>
  <main class="content">
    <section class="form-container">
      <div class="section-header">
        <div>
          <h3 style="margin:0; color:var(--secondary);">Modules</h3>
        </div>
      </div>

      <div class="module-grid">
        <?php if ($modules): ?>
          <?php foreach ($modules as $module): ?>
            <?php
              $moduleId = isset($module['module_id']) ? (int) $module['module_id'] : null;
              $moduleCode = strtoupper((string) ($module['module_code'] ?? 'MODULE'));
              $hasPermissionMap = !empty($modulePermissions);
              $permissionSet = $moduleId !== null && isset($modulePermissions[$moduleId]) ? $modulePermissions[$moduleId] : [];
              $permissionLetters = [];

              if (!empty($permissionSet['create'])) {
                  $permissionLetters[] = 'C';
              }
              if (!empty($permissionSet['read'])) {
                  $permissionLetters[] = 'R';
              }
              if (!empty($permissionSet['update'])) {
                  $permissionLetters[] = 'U';
              }
              if (!empty($permissionSet['delete'])) {
                  $permissionLetters[] = 'D';
              }

              $hasAnyPermission = !empty(array_filter($permissionSet));
              $canAccess = !$hasPermissionMap || $hasAnyPermission;

              $rawImage = $module['img'] ?? '';
              if (is_array($rawImage)) {
                  $rawImage = (string) (reset($rawImage) ?: '');
              }

              $imageSrc = normalize_module_card_image((string) $rawImage);
              $moduleLink = trim($module['link'] ?? $module['href'] ?? '');
              $description = $module['module_name'] ?? 'Module';
              $cardClasses = 'module-card' . ($canAccess && $moduleLink !== '' ? ' module-card--link' : '') . (!$canAccess ? ' module-card--disabled' : '');

              $permissionSummary = $hasPermissionMap
                  ? (!empty($permissionLetters) ? implode(', ', $permissionLetters) : 'No access')
                  : 'C, R, U, D';

              $accessLevel = $canAccess ? $permissionSummary : 'No access';
              $statusClass = $canAccess ? 'module-card__status--allowed' : 'module-card__status--blocked';
            ?>

            <?php if ($canAccess && $moduleLink !== ''): ?>
              <a class="<?php echo safe($cardClasses); ?>" href="<?php echo safe($moduleLink); ?>" data-module-code="<?php echo safe($moduleCode); ?>" data-module-id="<?php echo safe((string) ($moduleId ?? '')); ?>">
                <div class="module-card__image">
                  <img src="<?php echo safe($imageSrc); ?>" alt="<?php echo safe($module['module_name'] ?? 'Module'); ?>" />
                  <div class="module-card__status <?php echo safe($statusClass); ?>"><?php echo safe($accessLevel); ?></div>
                </div>
                <div class="module-card__body">
                  <h4><?php echo safe($module['module_code']); ?></h4>
                  <p><small><em><?php echo safe($description); ?></em></small></p>
                </div>
              </a>
            <?php else: ?>
              <div class="<?php echo safe($cardClasses); ?>" aria-disabled="true" data-module-code="<?php echo safe($moduleCode); ?>" data-module-id="<?php echo safe((string) ($moduleId ?? '')); ?>">
                <div class="module-card__image">
                  <?php if ($moduleLink !== ''): ?>
                    <a href="<?php echo safe($moduleLink); ?>">
                      <img src="<?php echo safe($imageSrc); ?>" alt="<?php echo safe($module['module_name'] ?? 'Module'); ?>" />
                    </a>
                  <?php else: ?>
                    <img src="<?php echo safe($imageSrc); ?>" alt="<?php echo safe($module['module_name'] ?? 'Module'); ?>" />
                  <?php endif; ?>
                  <div class="module-card__status <?php echo safe($statusClass); ?>"><?php echo safe($accessLevel); ?></div>
                </div>
                <div class="module-card__body">
                  <h4><?php echo safe($module['module_code']); ?></h4>
                  <p><small><em><?php echo safe($description); ?></em></small></p>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No modules configured.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const grid = document.querySelector('.module-grid');
      const toggleButton = document.getElementById('reorder-toggle');
      const userRole = (document.body.dataset.userRole || '').toLowerCase();
      const isAdmin = userRole === 'admin';
      const userId = document.body.dataset.userId || '0';
      const storageKey = `moduleOrder:${userId}`;

      if (!grid) {
        return;
      }

      const applySavedOrder = () => {
        const savedOrderRaw = localStorage.getItem(storageKey);
        if (!savedOrderRaw) {
          return;
        }

        let savedOrder;
        try {
          savedOrder = JSON.parse(savedOrderRaw);
        } catch (error) {
          return;
        }

        if (!Array.isArray(savedOrder) || savedOrder.length === 0) {
          return;
        }

        const cardMap = new Map();
        Array.from(grid.children).forEach((card) => {
          const code = card.dataset.moduleCode;
          if (code) {
            cardMap.set(code, card);
          }
        });

        savedOrder.forEach((code) => {
          const card = cardMap.get(code);
          if (card) {
            grid.appendChild(card);
          }
        });

        cardMap.forEach((card, code) => {
          if (!savedOrder.includes(code)) {
            grid.appendChild(card);
          }
        });
      };

      applySavedOrder();

      if (!isAdmin || !toggleButton) {
        return;
      }

      let dragMode = false;
      let draggingCard = null;

      const cards = () => Array.from(grid.querySelectorAll('[data-module-code]'));

      const saveOrder = () => {
        const order = cards()
          .map((card) => card.dataset.moduleCode)
          .filter(Boolean);
        localStorage.setItem(storageKey, JSON.stringify(order));
      };

      const setDragMode = (enabled) => {
        dragMode = enabled;
        grid.classList.toggle('module-grid--sortable', enabled);
        toggleButton.textContent = enabled ? 'Done' : 'Edit order';

        cards().forEach((card) => {
          card.draggable = enabled;
          card.classList.toggle('module-card--sortable', enabled);
        });
      };

      const moveCard = (target) => {
        if (!draggingCard || !target || draggingCard === target) {
          return;
        }

        const allCards = cards();
        const targetIndex = allCards.indexOf(target);
        const draggingIndex = allCards.indexOf(draggingCard);

        if (targetIndex < draggingIndex) {
          grid.insertBefore(draggingCard, target);
        } else {
          grid.insertBefore(draggingCard, target.nextElementSibling);
        }
      };

      toggleButton.addEventListener('click', () => {
        setDragMode(!dragMode);
        if (!dragMode) {
          saveOrder();
        }
      });

      grid.addEventListener('click', (event) => {
        if (dragMode && event.target.closest('[data-module-code]')) {
          event.preventDefault();
        }
      });

      grid.addEventListener('dragstart', (event) => {
        if (!dragMode) {
          return;
        }
        const card = event.target.closest('[data-module-code]');
        if (!card) {
          return;
        }

        draggingCard = card;
        card.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
      });

      grid.addEventListener('dragover', (event) => {
        if (!dragMode || !draggingCard) {
          return;
        }

        event.preventDefault();
        const target = event.target.closest('[data-module-code]');
        if (target) {
          moveCard(target);
        }
      });

      grid.addEventListener('drop', (event) => {
        if (dragMode) {
          event.preventDefault();
        }
      });

      grid.addEventListener('dragend', () => {
        if (!dragMode || !draggingCard) {
          return;
        }

        draggingCard.classList.remove('is-dragging');
        draggingCard = null;
        saveOrder();
      });
    });
  </script>
</body>
</html>
