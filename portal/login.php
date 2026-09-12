<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect(is_admin() ? 'index.php' : 'my_tasks.php');
}

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount === 0) {
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
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_attempts'] = 0;
            session_regenerate_id(true);
            redirect($user['role'] === 'admin' ? 'index.php' : 'my_tasks.php');
        }
        $_SESSION['login_attempts'] = $attempts + 1;
        $_SESSION['login_last_attempt'] = time();
        $error = 'Incorrect email or password.';
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
      <label>Email
        <input type="email" name="email" required autofocus>
      </label>
      <label>Password
        <input type="password" name="password" required>
      </label>
      <div class="form-actions">
        <button type="submit" class="btn btn--primary">Log in</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
