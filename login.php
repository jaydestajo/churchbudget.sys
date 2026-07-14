<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

if (current_user()) {
    redirect(BASE_URL . 'dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row) {
        $error = 'Invalid username or password.';
    } elseif ($row['status'] !== 'Active') {
        $error = 'Your account is inactive. Please contact the administrator.';
    } elseif (!password_verify($password, $row['password_hash'])) {
        $error = 'Invalid username or password.';
    } else {
        $_SESSION['user'] = [
            'id'   => $row['id'],
            'name' => $row['name'],
            'username' => $row['username'],
            'role' => $row['role_name'],
        ];
        log_action($pdo, $row['id'], 'Login', 'User logged in');
        redirect(BASE_URL . 'dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>public/css/style.css">
</head>
<body>
<div class="login-wrapper">
  <div class="card login-card shadow p-4">
    <div class="card-body">
      <div class="text-center mb-3">
        <i class="bi bi-wallet2" style="font-size:2.5rem;color:#2f5d50;"></i>
        <h4 class="mt-2 mb-0"><?= h(APP_NAME) ?></h4>
        <small class="text-muted">Sign in to continue</small>
      </div>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-brand w-100">Login</button>
      </form>
      <hr>
      <small class="text-muted d-block text-center">
        Created by: <b>JBDestajo</b>
      </small>
    </div>
  </div>
</div>
</body>
</html>
