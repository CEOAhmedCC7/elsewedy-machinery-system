<?php
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['user'])) {
    header('Location: Home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email !== '' && $password !== '') {
        try {
            $pdo = get_pdo();
            $stmt = $pdo->prepare(
                'SELECT u.user_id, u.full_name, u.email, u.password_hash, u.is_active, r.role_name
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.user_id = u.user_id
                 LEFT JOIN roles r ON r.role_id = ur.role_id
                 WHERE u.email = :email
                 LIMIT 1'
            );
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

if (!$user) {
                $error = 'Account not found. Please contact an administrator to create an account.';
            } else {
                // PostgreSQL booleans come back as 't'/'f' strings by default, so
                // normalize to a real boolean before checking the account status.
                $isActive = filter_var($user['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($isActive !== true) {
                    $error = 'This account is inactive. Please contact an administrator.';
                } else {
                     $storedPassword = (string) $user['password_hash'];

                    if (hash_equals($storedPassword, $password)) {
                        $roleName = $user['role_name'] ?? 'user';
                        $displayName = $user['full_name'] ?: $user['email'];

                        $_SESSION['user'] = [
                            'user_id' => $user['user_id'],
                            'username' => $displayName,
                            'email' => $user['email'],
                            'role' => $roleName,
                        ];
                        header('Location: Home.php');
                        exit;
                    }

                    $error = 'Invalid password. Double-check your email and password and try again.';
                }
            }
        } catch (Throwable $e) {
            $error = format_db_error($e, 'users, user_roles, and roles tables');
        }
    } else {
        $error = 'Please provide both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Elsewedy Machinery | Login</title>
  <link rel="stylesheet" href="./assets/styles.css" />
</head>
<body class="page login-page">
  <?php if ($error): ?>
    <div class="error-modal is-visible" role="alertdialog" aria-live="assertive" aria-label="Login error">
      <div class="error-dialog">
        <div class="error-dialog__header">
          <span class="error-title">Login issue</span>
          <button class="error-close" type="button" aria-label="Close error">&times;</button>
        </div>
        <p class="error-message"><?php echo safe($error); ?></p>
      </div>
    </div>
  <?php endif; ?>

  <div class="login-card">
    <form class="form-container" method="POST" action="login.php">
      <div class="form-row">
        <div>
          <label class="label" for="email">Email</label>
          <input id="email" name="email" type="email" placeholder="Enter your email" required />
        </div>
        <div>
          <label class="label" for="password">Password</label>
          <input id="password" name="password" type="password" placeholder="Enter your password" required />
        </div>
      </div>
      <div class="actions" style="justify-content:space-between; align-items:center;">
        <button class="btn btn-save" type="submit">Login</button>
      </div>
    </form>
  </div>
  <p class="secured-footnote">Secured by <b> PMO Team </b> </p>

  <script>
    const closeButton = document.querySelector('.error-close');
    const modal = document.querySelector('.error-modal');

    if (closeButton && modal) {
      closeButton.addEventListener('click', () => modal.classList.remove('is-visible'));
    }
  </script>
</body>
</html>