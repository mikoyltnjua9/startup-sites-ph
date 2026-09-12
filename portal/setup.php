<?php
// One-time setup: sets the shared admin password on first visit. After that,
// it refuses to run again — to reset the password later, delete the
// 'admin_password_hash' row from the `settings` table via phpMyAdmin and
// revisit this page.

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = ?');
$stmt->execute(['admin_password_hash']);
$existingHash = $stmt->fetchColumn();

$error = null;
$done = false;

if ($existingHash === false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)');
        $ins->execute(['admin_password_hash', $hash]);
        $done = true;
    }
}

$pageTitle = 'Setup';
require __DIR__ . '/includes/layout_top.php';
?>
<div class="auth-page" style="padding-top: 4rem;">
  <div class="card auth-card">
    <?php if ($existingHash !== false && !$done): ?>
      <h1>Already set up</h1>
      <p>An admin password is already configured for this portal.</p>
      <a class="btn btn--primary" href="login.php">Go to login</a>
    <?php elseif ($done): ?>
      <h1>Password set</h1>
      <p>You're all set — you can log in now.</p>
      <a class="btn btn--primary" href="login.php">Go to login</a>
    <?php else: ?>
      <h1>Set your admin password</h1>
      <p class="muted">This runs once. Everyone on the team will use this same password to access the portal.</p>
      <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <label>Password
          <input type="password" name="password" minlength="8" required autofocus>
        </label>
        <label>Confirm password
          <input type="password" name="confirm" minlength="8" required>
        </label>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Set password</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
