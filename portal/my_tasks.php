<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/upload.php';

$userId = (int) $_SESSION['user_id'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $taskId = (int) ($_POST['task_id'] ?? 0);

    // Every action below only touches a task that's actually assigned to
    // the logged-in user — a developer can't toggle or attach to someone
    // else's task just by guessing an id.
    $check = $pdo->prepare('SELECT id, photo_path FROM tasks WHERE id = ? AND assigned_to = ?');
    $check->execute([$taskId, $userId]);
    $task = $check->fetch();

    if ($task) {
        if ($action === 'toggle_task') {
            $pdo->prepare('UPDATE tasks SET is_done = 1 - is_done WHERE id = ?')->execute([$taskId]);
        } elseif ($action === 'add_photo' && !empty($_FILES['photo']['name'])) {
            [$stored, $uploadError] = handle_upload($_FILES['photo'], ALLOWED_IMAGE_UPLOADS);
            if ($uploadError) {
                $error = $uploadError;
            } elseif ($stored) {
                delete_upload($task['photo_path']);
                $pdo->prepare('UPDATE tasks SET photo_path = ? WHERE id = ?')->execute([$stored, $taskId]);
            }
        }
    }

    if (!$error) {
        redirect('my_tasks.php');
    }
}

$stmt = $pdo->prepare(
    "SELECT t.*, c.company_name
     FROM tasks t
     JOIN clients c ON c.id = t.client_id
     WHERE t.assigned_to = ?
     ORDER BY t.is_done ASC, t.created_at ASC"
);
$stmt->execute([$userId]);
$tasks = $stmt->fetchAll();

$pageTitle = 'My Tasks';
$activeNav = 'tasks';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="detail-header">
  <h1>My Tasks</h1>
</div>

<?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>

<div class="card">
  <?php if (empty($tasks)): ?>
    <p class="empty-state">Nothing assigned to you yet.</p>
  <?php else: ?>
    <?php foreach ($tasks as $t): ?>
      <div class="list-item" style="align-items: center;">
        <form method="post" class="task-row <?= $t['is_done'] ? 'is-done' : '' ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle_task">
          <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
          <input type="checkbox" onchange="this.form.submit()" <?= $t['is_done'] ? 'checked' : '' ?>>
          <span>
            <?= h($t['label']) ?>
            <span class="muted" style="display:block; font-size: 0.78rem;"><?= h($t['company_name']) ?></span>
          </span>
        </form>
        <div style="display:flex; align-items:center; gap:0.6rem;">
          <?php if ($t['photo_path']): ?>
            <a href="uploads/<?= h($t['photo_path']) ?>" target="_blank" rel="noopener noreferrer">
              <img src="uploads/<?= h($t['photo_path']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;">
            </a>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" style="display:flex; gap:0.4rem; align-items:center;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_photo">
            <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
            <input type="file" name="photo" accept="image/*" style="width:130px; font-size:0.75rem; padding:0.3rem;" onchange="this.form.submit()">
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
