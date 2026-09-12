<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_user') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'developer');
        if (!in_array($role, ['admin', 'developer'], true)) {
            $role = 'developer';
        }

        if ($name === '' || $email === '') {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'That email is already in use.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
                $ins->execute([$name, $email, $hash, $role, date('Y-m-d H:i:s')]);
                redirect('users.php');
            }
        }
    } elseif ($action === 'delete_user') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId === (int) $_SESSION['user_id']) {
            $error = "You can't delete your own account while logged in as it.";
        } else {
            $target = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $target->execute([$targetId]);
            $targetRole = $target->fetchColumn();

            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($targetRole === 'admin' && $adminCount <= 1) {
                $error = "Can't delete the last remaining admin.";
            } else {
                $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $del->execute([$targetId]);
                redirect('users.php');
            }
        }
    }
}

$users = $pdo->query('SELECT * FROM users ORDER BY role ASC, name ASC')->fetchAll();

$pageTitle = 'Team';
$activeNav = 'team';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="detail-header">
  <h1>Team</h1>
</div>

<div class="card">
  <?php if (empty($users)): ?>
    <p class="empty-state">No accounts yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= h($u['name']) ?></td>
              <td><?= h($u['email']) ?></td>
              <td><span class="badge <?= $u['role'] === 'admin' ? 'badge--launched' : 'badge--lead' ?>"><?= h(ucfirst($u['role'])) ?></span></td>
              <td>
                <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                  <form method="post" onsubmit="return confirm('Remove <?= h(addslashes($u['name'])) ?> from the portal?');" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" class="btn btn--small">Remove</button>
                  </form>
                <?php else: ?>
                  <span class="muted">(you)</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="section-title"><h2>Add a team member</h2></div>
<div class="card">
  <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_user">
    <div class="form-grid">
      <label>Name
        <input type="text" name="name" required>
      </label>
      <label>Email
        <input type="email" name="email" required>
      </label>
      <label>Temporary password
        <input type="password" name="password" minlength="8" required>
      </label>
      <label>Role
        <select name="role">
          <option value="developer">Developer</option>
          <option value="admin">Admin</option>
        </select>
      </label>
    </div>
    <p class="muted" style="margin-top: 0.9rem; font-size: 0.82rem;">Share this password with them directly. There's no self-service "change password" yet, so pick something they're okay using for now — you can always remove and re-add them with a new one later.</p>
    <div class="form-actions">
      <button type="submit" class="btn btn--primary">Add team member</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
