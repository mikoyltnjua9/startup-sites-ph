<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('clients.php');
}

$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) {
    redirect('clients.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_info') {
        $company = trim((string) ($_POST['company_name'] ?? ''));
        $contact = trim((string) ($_POST['contact_name'] ?? ''));

        if ($company === '' || $contact === '') {
            $error = 'Company name and contact name are required.';
        } else {
            $serviceType = (string) ($_POST['service_type'] ?? 'web_design');
            if (!isset(SERVICE_TYPES[$serviceType])) {
                $serviceType = 'web_design';
            }
            $stage = (string) ($_POST['stage'] ?? 'lead');
            if (!isset(STAGES[$stage])) {
                $stage = 'lead';
            }
            $email = trim((string) ($_POST['email'] ?? '')) ?: null;
            $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
            $source = trim((string) ($_POST['source'] ?? '')) ?: null;
            $liveUrl = trim((string) ($_POST['live_url'] ?? '')) ?: null;
            $startDate = (string) ($_POST['start_date'] ?? '') ?: null;
            $targetLaunch = (string) ($_POST['target_launch_date'] ?? '') ?: null;
            $actualLaunch = (string) ($_POST['actual_launch_date'] ?? '') ?: null;
            $nextFollowUp = (string) ($_POST['next_follow_up_date'] ?? '') ?: null;
            $totalAmount = (float) ($_POST['total_amount'] ?? 0);
            $amountPaid = (float) ($_POST['amount_paid'] ?? 0);

            $upd = $pdo->prepare(
                'UPDATE clients SET company_name=?, contact_name=?, email=?, phone=?, source=?,
                 service_type=?, stage=?, start_date=?, target_launch_date=?, actual_launch_date=?,
                 next_follow_up_date=?, total_amount=?, amount_paid=?, live_url=?, updated_at=?
                 WHERE id=?'
            );
            $upd->execute([
                $company, $contact, $email, $phone, $source,
                $serviceType, $stage, $startDate, $targetLaunch, $actualLaunch,
                $nextFollowUp, $totalAmount, $amountPaid, $liveUrl, date('Y-m-d H:i:s'),
                $id,
            ]);
            redirect('client.php?id=' . $id);
        }
    } elseif ($action === 'add_note') {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body !== '') {
            $ins = $pdo->prepare('INSERT INTO notes (client_id, body, created_at) VALUES (?, ?, ?)');
            $ins->execute([$id, $body, date('Y-m-d H:i:s')]);
        }
        redirect('client.php?id=' . $id . '#notes');
    } elseif ($action === 'add_task') {
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label !== '') {
            $ins = $pdo->prepare('INSERT INTO tasks (client_id, label, is_done, created_at) VALUES (?, ?, 0, ?)');
            $ins->execute([$id, $label, date('Y-m-d H:i:s')]);
        }
        redirect('client.php?id=' . $id . '#tasks');
    } elseif ($action === 'toggle_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $upd = $pdo->prepare('UPDATE tasks SET is_done = 1 - is_done WHERE id = ? AND client_id = ?');
        $upd->execute([$taskId, $id]);
        redirect('client.php?id=' . $id . '#tasks');
    } elseif ($action === 'delete_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND client_id = ?');
        $del->execute([$taskId, $id]);
        redirect('client.php?id=' . $id . '#tasks');
    } elseif ($action === 'add_file') {
        $label = trim((string) ($_POST['label'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        if ($label !== '' && $url !== '') {
            $ins = $pdo->prepare('INSERT INTO files (client_id, label, url, created_at) VALUES (?, ?, ?, ?)');
            $ins->execute([$id, $label, $url, date('Y-m-d H:i:s')]);
        }
        redirect('client.php?id=' . $id . '#files');
    } elseif ($action === 'delete_file') {
        $fileId = (int) ($_POST['file_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM files WHERE id = ? AND client_id = ?');
        $del->execute([$fileId, $id]);
        redirect('client.php?id=' . $id . '#files');
    } elseif ($action === 'delete_client') {
        $del = $pdo->prepare('DELETE FROM clients WHERE id = ?');
        $del->execute([$id]);
        redirect('clients.php');
    }
}

$notesStmt = $pdo->prepare('SELECT * FROM notes WHERE client_id = ? ORDER BY created_at DESC');
$notesStmt->execute([$id]);
$notes = $notesStmt->fetchAll();

$tasksStmt = $pdo->prepare('SELECT * FROM tasks WHERE client_id = ? ORDER BY is_done ASC, created_at ASC');
$tasksStmt->execute([$id]);
$tasks = $tasksStmt->fetchAll();

$filesStmt = $pdo->prepare('SELECT * FROM files WHERE client_id = ? ORDER BY created_at DESC');
$filesStmt->execute([$id]);
$files = $filesStmt->fetchAll();

[$payLabel, $payClass] = payment_status((float) $client['total_amount'], (float) $client['amount_paid']);
$balance = (float) $client['total_amount'] - (float) $client['amount_paid'];

$pageTitle = $client['company_name'];
$activeNav = 'clients';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="detail-header">
  <div>
    <a class="muted" href="clients.php">&larr; All clients</a>
    <h1 style="margin-top: 0.4rem;"><?= h($client['company_name']) ?></h1>
    <span class="badge badge--<?= h($client['stage']) ?>"><?= h(stage_label($client['stage'])) ?></span>
    <span class="badge badge--<?= $payClass ?>"><?= h($payLabel) ?></span>
    <?php if ($client['live_url']): ?>
      <a class="btn btn--small" href="<?= h($client['live_url']) ?>" target="_blank" rel="noopener noreferrer">Visit live site ↗</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid--2" style="margin-top: 1.5rem;">
  <div>
    <div class="section-title"><h2>Project info</h2></div>
    <div class="card">
      <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_info">
        <div class="form-grid">
          <label>Company name
            <input type="text" name="company_name" value="<?= h($client['company_name']) ?>" required>
          </label>
          <label>Contact name
            <input type="text" name="contact_name" value="<?= h($client['contact_name']) ?>" required>
          </label>
          <label>Email
            <input type="email" name="email" value="<?= h($client['email']) ?>">
          </label>
          <label>Phone
            <input type="text" name="phone" value="<?= h($client['phone']) ?>">
          </label>
          <label>How they found us
            <input type="text" name="source" value="<?= h($client['source']) ?>" placeholder="Referral, Facebook, Google…">
          </label>
          <label>Service
            <select name="service_type">
              <?php foreach (SERVICE_TYPES as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $client['service_type'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Stage
            <select name="stage">
              <?php foreach (STAGES as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $client['stage'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Live site URL
            <input type="url" name="live_url" value="<?= h($client['live_url']) ?>" placeholder="https://…">
          </label>
          <label>Start date
            <input type="date" name="start_date" value="<?= h($client['start_date']) ?>">
          </label>
          <label>Target launch date
            <input type="date" name="target_launch_date" value="<?= h($client['target_launch_date']) ?>">
          </label>
          <label>Actual launch date
            <input type="date" name="actual_launch_date" value="<?= h($client['actual_launch_date']) ?>">
          </label>
          <label>Next follow-up
            <input type="date" name="next_follow_up_date" value="<?= h($client['next_follow_up_date']) ?>">
          </label>
          <label>Total project value (₱)
            <input type="number" step="0.01" min="0" name="total_amount" value="<?= h((string) $client['total_amount']) ?>">
          </label>
          <label>Amount paid so far (₱)
            <input type="number" step="0.01" min="0" name="amount_paid" value="<?= h((string) $client['amount_paid']) ?>">
          </label>
        </div>
        <p class="muted" style="margin-top: 0.9rem; font-size: 0.85rem;">Balance due: <strong><?= money($balance) ?></strong></p>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Save changes</button>
        </div>
      </form>
    </div>

    <div class="section-title" id="notes"><h2>Notes</h2></div>
    <div class="card">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_note">
        <label>Add a note
          <textarea name="body" placeholder="What happened? Calls, decisions, feedback…" required></textarea>
        </label>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Add note</button>
        </div>
      </form>
      <?php if (empty($notes)): ?>
        <p class="empty-state">No notes yet.</p>
      <?php else: ?>
        <?php foreach ($notes as $n): ?>
          <div class="note-item">
            <time><?= h(date('M j, Y g:ia', strtotime($n['created_at']))) ?></time>
            <p style="margin: 0; white-space: pre-wrap;"><?= h($n['body']) ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="section-title" id="tasks"><h2>Tasks</h2></div>
    <div class="card">
      <?php if (empty($tasks)): ?>
        <p class="empty-state">No tasks yet.</p>
      <?php else: ?>
        <?php foreach ($tasks as $t): ?>
          <div class="list-item">
            <form method="post" class="task-row <?= $t['is_done'] ? 'is-done' : '' ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_task">
              <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
              <input type="checkbox" onchange="this.form.submit()" <?= $t['is_done'] ? 'checked' : '' ?>>
              <span><?= h($t['label']) ?></span>
            </form>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_task">
              <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
              <button type="submit" class="btn btn--small" aria-label="Delete task">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <form method="post" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_task">
        <input type="text" name="label" placeholder="New task…" required>
        <button type="submit" class="btn btn--small">Add</button>
      </form>
    </div>

    <div class="section-title" id="files"><h2>Files &amp; links</h2></div>
    <div class="card">
      <?php if (empty($files)): ?>
        <p class="empty-state">No files linked yet.</p>
      <?php else: ?>
        <?php foreach ($files as $f): ?>
          <div class="list-item">
            <a href="<?= h($f['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($f['label']) ?> ↗</a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_file">
              <input type="hidden" name="file_id" value="<?= (int) $f['id'] ?>">
              <button type="submit" class="btn btn--small" aria-label="Remove link">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <form method="post" class="inline-form" style="flex-wrap: wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_file">
        <input type="text" name="label" placeholder="Label (e.g. Contract)" required style="flex: 1 1 140px;">
        <input type="url" name="url" placeholder="https://drive.google.com/…" required style="flex: 2 1 200px;">
        <button type="submit" class="btn btn--small">Add</button>
      </form>
    </div>

    <div class="section-title"><h2>Danger zone</h2></div>
    <div class="card">
      <p class="muted" style="font-size: 0.85rem;">Deleting a client also removes its notes, tasks, and file links. This cannot be undone.</p>
      <form method="post" onsubmit="return confirm('Delete this client and everything attached to it? This cannot be undone.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_client">
        <button type="submit" class="btn btn--danger">Delete client</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
