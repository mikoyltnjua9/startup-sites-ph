<?php
// One-time setup: creates the first admin account. After that, it refuses
// to run again — additional accounts (admin or developer) are created from
// the Team page by an existing admin. To start over, delete every row from
// the `users` table via phpMyAdmin and revisit this page.

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

$error = null;
$done = false;

if ($userCount === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($name === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$name, $email, $hash, 'admin', date('Y-m-d H:i:s')]);
        $done = true;
    }
}

$pageTitle = 'Setup';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="auth-page" style="padding-top: 4rem;">
  <div class="card auth-card">
    <?php if ($userCount > 0 && !$done): ?>
      <h1>Already set up</h1>
      <p>This portal already has an admin account.</p>
      <a class="btn btn--primary" href="login.php">Go to login</a>
    <?php elseif ($done): ?>
      <h1>You're set up</h1>
      <p>Your admin account is ready — you can log in now, and add developer accounts from the Team page afterward.</p>
      <a class="btn btn--primary" href="login.php">Go to login</a>
    <?php else: ?>
      <h1>Create your admin account</h1>
      <p class="muted">This runs once, for the first admin. You'll add other team accounts later from inside the portal.</p>
      <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label>Your name
          <input type="text" name="name" required autofocus>
        </label>
        <label>Email
          <input type="email" name="email" required>
        </label>
        <label>Password
          <input type="password" name="password" minlength="8" required>
        </label>
        <label>Confirm password
          <input type="password" name="confirm" minlength="8" required>
        </label>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Create account</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
