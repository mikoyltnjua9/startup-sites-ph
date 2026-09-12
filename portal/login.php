<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['is_admin'])) {
    redirect('index.php');
}

$stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = ?');
$stmt->execute(['admin_password_hash']);
$hash = $stmt->fetchColumn();

if ($hash === false) {
    redirect('setup.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Basic throttle: after 5 failed attempts, force a short pause.
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastAttempt = $_SESSION['login_last_attempt'] ?? 0;
    if ($attempts >= 5 && (time() - $lastAttempt) < 30) {
        $error = 'Too many attempts — wait a moment and try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        if (password_verify($password, $hash)) {
            $_SESSION['is_admin'] = true;
            $_SESSION['login_attempts'] = 0;
            session_regenerate_id(true);
            redirect('index.php');
        }
        $_SESSION['login_attempts'] = $attempts + 1;
        $_SESSION['login_last_attempt'] = time();
        $error = 'Incorrect password.';
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="auth-page" style="padding-top: 4rem;">
  <div class="card auth-card">
    <h1>Client Portal</h1>
    <p class="muted">Startup Sites PH — internal use only.</p>
    <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label>Password
        <input type="password" name="password" required autofocus>
      </label>
      <div class="form-actions">
        <button type="submit" class="btn btn--primary">Log in</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
