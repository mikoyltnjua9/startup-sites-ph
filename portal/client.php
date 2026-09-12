<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/includes/upload.php';
require_once __DIR__ . '/includes/mailer.php';

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

            $upd = $pdo->prepare(
                'UPDATE clients SET company_name=?, contact_name=?, email=?, phone=?, source=?,
                 service_type=?, stage=?, start_date=?, target_launch_date=?, actual_launch_date=?,
                 next_follow_up_date=?, total_amount=?, live_url=?, updated_at=?
                 WHERE id=?'
            );
            $upd->execute([
                $company, $contact, $email, $phone, $source,
                $serviceType, $stage, $startDate, $targetLaunch, $actualLaunch,
                $nextFollowUp, $totalAmount, $liveUrl, date('Y-m-d H:i:s'),
                $id,
            ]);
            redirect('client.php?id=' . $id);
        }
    } elseif ($action === 'add_payment') {
        $amount = (float) ($_POST['amount'] ?? 0);
        $paidDate = (string) ($_POST['paid_date'] ?? '') ?: date('Y-m-d');
        $note = trim((string) ($_POST['note'] ?? '')) ?: null;
        if ($amount > 0) {
            $ins = $pdo->prepare('INSERT INTO payments (client_id, amount, paid_date, note, created_at) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$id, $amount, $paidDate, $note, date('Y-m-d H:i:s')]);
        }
        redirect('client.php?id=' . $id . '#payments');
    } elseif ($action === 'delete_payment') {
        $del = $pdo->prepare('DELETE FROM payments WHERE id = ? AND client_id = ?');
        $del->execute([(int) ($_POST['payment_id'] ?? 0), $id]);
        redirect('client.php?id=' . $id . '#payments');
    } elseif ($action === 'add_maintenance_item') {
        $label = trim((string) ($_POST['label'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($label !== '') {
            $ins = $pdo->prepare('INSERT INTO maintenance_items (client_id, label, amount, created_at) VALUES (?, ?, ?, ?)');
            $ins->execute([$id, $label, $amount, date('Y-m-d H:i:s')]);
        }
        redirect('client.php?id=' . $id . '#maintenance');
    } elseif ($action === 'delete_maintenance_item') {
        $del = $pdo->prepare('DELETE FROM maintenance_items WHERE id = ? AND client_id = ?');
        $del->execute([(int) ($_POST['item_id'] ?? 0), $id]);
        redirect('client.php?id=' . $id . '#maintenance');
    } elseif ($action === 'send_maintenance_email') {
        if (empty($client['email'])) {
            redirect('client.php?id=' . $id . '&mail_status=error&mail_msg=' . urlencode('No email on file for this client.'));
        }
        $items = $pdo->prepare('SELECT * FROM maintenance_items WHERE client_id = ? ORDER BY created_at ASC');
        $items->execute([$id]);
        $items = $items->fetchAll();
        $total = array_sum(array_column($items, 'amount'));

        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr><td style="padding:6px 10px;border-bottom:1px solid #eee;">' . h($item['label']) . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;">' . money((float) $item['amount']) . '</td></tr>';
        }
        $html = '<div style="font-family:sans-serif;color:#1d1d1f;max-width:480px;">'
            . '<p>Hi ' . h($client['contact_name']) . ',</p>'
            . '<p>Here is your monthly maintenance breakdown for <strong>' . h($client['company_name']) . '</strong>:</p>'
            . '<table style="width:100%;border-collapse:collapse;">' . $rows
            . '<tr><td style="padding:8px 10px;font-weight:bold;">Total</td>'
            . '<td style="padding:8px 10px;font-weight:bold;text-align:right;">' . money($total) . '</td></tr>'
            . '</table>'
            . '<p>If you have any questions about this, just reply to this email.</p>'
            . '<p>— Startup Sites PH</p>'
            . '</div>';

        $mailer = new SmtpMailer();
        $sent = $mailer->send(
            $client['email'],
            $client['contact_name'],
            'Your monthly maintenance breakdown — ' . $client['company_name'],
            $html
        );
        if ($sent) {
            redirect('client.php?id=' . $id . '&mail_status=sent');
        }
        redirect('client.php?id=' . $id . '&mail_status=error&mail_msg=' . urlencode($mailer->getLastError()));
    } elseif ($action === 'add_note') {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body !== '') {
            $ins = $pdo->prepare('INSERT INTO notes (client_id, body, created_at) VALUES (?, ?, ?)');
            $ins->execute([$id, $body, date('Y-m-d H:i:s')]);
        }
        redirect('client.php?id=' . $id . '#notes');
    } elseif ($action === 'add_task') {
        $label = trim((string) ($_POST['label'] ?? ''));
        $assignedTo = (int) ($_POST['assigned_to'] ?? 0) ?: null;
        if ($label !== '') {
            [$stored, $uploadError] = handle_upload($_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], ALLOWED_IMAGE_UPLOADS);
            if ($uploadError) {
                $error = $uploadError;
            } else {
                $ins = $pdo->prepare('INSERT INTO tasks (client_id, label, is_done, assigned_to, photo_path, created_at) VALUES (?, ?, 0, ?, ?, ?)');
                $ins->execute([$id, $label, $assignedTo, $stored, date('Y-m-d H:i:s')]);
            }
        }
        if (!$error) {
            redirect('client.php?id=' . $id . '#tasks');
        }
    } elseif ($action === 'toggle_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $upd = $pdo->prepare('UPDATE tasks SET is_done = 1 - is_done WHERE id = ? AND client_id = ?');
        $upd->execute([$taskId, $id]);
        redirect('client.php?id=' . $id . '#tasks');
    } elseif ($action === 'delete_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $find = $pdo->prepare('SELECT photo_path FROM tasks WHERE id = ? AND client_id = ?');
        $find->execute([$taskId, $id]);
        delete_upload($find->fetchColumn() ?: null);
        $del = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND client_id = ?');
        $del->execute([$taskId, $id]);
        redirect('client.php?id=' . $id . '#tasks');
    } elseif ($action === 'add_file') {
        $label = trim((string) ($_POST['label'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $hasUpload = !empty($_FILES['upload']['name']);

        if ($hasUpload) {
            [$stored, $uploadError] = handle_upload($_FILES['upload']);
            if ($uploadError) {
                $error = $uploadError;
            } elseif ($stored) {
                $finalLabel = $label !== '' ? $label : $_FILES['upload']['name'];
                $ins = $pdo->prepare('INSERT INTO files (client_id, label, stored_path, created_at) VALUES (?, ?, ?, ?)');
                $ins->execute([$id, $finalLabel, $stored, date('Y-m-d H:i:s')]);
            }
        } elseif ($label !== '' && $url !== '') {
            $ins = $pdo->prepare('INSERT INTO files (client_id, label, url, created_at) VALUES (?, ?, ?, ?)');
            $ins->execute([$id, $label, $url, date('Y-m-d H:i:s')]);
        }
        if (!$error) {
            redirect('client.php?id=' . $id . '#files');
        }
    } elseif ($action === 'delete_file') {
        $fileId = (int) ($_POST['file_id'] ?? 0);
        $find = $pdo->prepare('SELECT stored_path FROM files WHERE id = ? AND client_id = ?');
        $find->execute([$fileId, $id]);
        delete_upload($find->fetchColumn() ?: null);
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

$tasksStmt = $pdo->prepare(
    'SELECT t.*, u.name AS assignee_name
     FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
     WHERE t.client_id = ? ORDER BY t.is_done ASC, t.created_at ASC'
);
$tasksStmt->execute([$id]);
$tasks = $tasksStmt->fetchAll();

$filesStmt = $pdo->prepare('SELECT * FROM files WHERE client_id = ? ORDER BY created_at DESC');
$filesStmt->execute([$id]);
$files = $filesStmt->fetchAll();

$paymentsStmt = $pdo->prepare('SELECT * FROM payments WHERE client_id = ? ORDER BY paid_date DESC, created_at DESC');
$paymentsStmt->execute([$id]);
$payments = $paymentsStmt->fetchAll();
$amountPaid = (float) array_sum(array_column($payments, 'amount'));

$maintenanceStmt = $pdo->prepare('SELECT * FROM maintenance_items WHERE client_id = ? ORDER BY created_at ASC');
$maintenanceStmt->execute([$id]);
$maintenanceItems = $maintenanceStmt->fetchAll();
$maintenanceTotal = (float) array_sum(array_column($maintenanceItems, 'amount'));

$developers = $pdo->query("SELECT id, name FROM users ORDER BY name ASC")->fetchAll();

[$payLabel, $payClass] = payment_status((float) $client['total_amount'], $amountPaid);
$balance = (float) $client['total_amount'] - $amountPaid;

$mailStatus = (string) ($_GET['mail_status'] ?? '');
$mailMsg = (string) ($_GET['mail_msg'] ?? '');

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

<?php if ($mailStatus === 'sent'): ?>
  <p class="card" style="border-left: 4px solid var(--mint, #34c79a); margin-top: 1rem;">Maintenance email sent to <?= h($client['email']) ?>.</p>
<?php elseif ($mailStatus === 'error'): ?>
  <p class="card error-text" style="margin-top: 1rem;">Couldn't send: <?= h($mailMsg) ?></p>
<?php endif; ?>

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
        </div>
        <p class="muted" style="margin-top: 0.9rem; font-size: 0.85rem;">Paid so far: <strong><?= money($amountPaid) ?></strong> · Balance due: <strong><?= money($balance) ?></strong> (record actual payments below)</p>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Save changes</button>
        </div>
      </form>
    </div>

    <div class="section-title" id="tasks"><h2>Tasks</h2></div>
    <div class="card card--tasks">
      <?php if (empty($tasks)): ?>
        <p class="empty-state">No tasks yet.</p>
      <?php else: ?>
        <?php foreach ($tasks as $t): ?>
          <div class="list-item task-item">
            <form method="post" class="task-row <?= $t['is_done'] ? 'is-done' : '' ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_task">
              <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
              <input type="checkbox" onchange="this.form.submit()" <?= $t['is_done'] ? 'checked' : '' ?>>
              <span>
                <?= h($t['label']) ?>
                <?php if ($t['assignee_name']): ?>
                  <span class="badge badge--deposit_paid" style="margin-left:0.5rem;"><?= h($t['assignee_name']) ?></span>
                <?php endif; ?>
              </span>
            </form>
            <div style="display:flex; align-items:center; gap:0.6rem;">
              <?php if ($t['photo_path']): ?>
                <a href="uploads/<?= h($t['photo_path']) ?>" target="_blank" rel="noopener noreferrer">
                  <img src="uploads/<?= h($t['photo_path']) ?>" alt="" style="width:52px;height:52px;object-fit:cover;border-radius:8px;">
                </a>
              <?php endif; ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_task">
                <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                <button type="submit" class="btn btn--small" aria-label="Delete task">✕</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="inline-form" style="flex-wrap: wrap; margin-top: 1.1rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_task">
        <input type="text" name="label" placeholder="New task…" required style="flex: 2 1 200px;">
        <select name="assigned_to" style="flex: 1 1 140px;">
          <option value="">Unassigned</option>
          <?php foreach ($developers as $dev): ?>
            <option value="<?= (int) $dev['id'] ?>"><?= h($dev['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="file" name="photo" accept="image/*" style="flex: 1 1 160px; font-size: 0.8rem;">
        <button type="submit" class="btn btn--small">Add task</button>
      </form>
    </div>
  </div>

  <div>
    <div class="section-title" id="payments"><h2>Payments</h2></div>
    <div class="card">
      <?php if (empty($payments)): ?>
        <p class="empty-state">No payments recorded yet.</p>
      <?php else: ?>
        <?php foreach ($payments as $p): ?>
          <div class="list-item">
            <div>
              <strong><?= money((float) $p['amount']) ?></strong>
              <span class="muted"> — <?= date_fmt($p['paid_date']) ?><?= $p['note'] ? ' · ' . h($p['note']) : '' ?></span>
            </div>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_payment">
              <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn btn--small" aria-label="Delete payment">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <form method="post" class="inline-form" style="flex-wrap: wrap; margin-top: 0.9rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_payment">
        <input type="number" step="0.01" min="0" name="amount" placeholder="Amount (₱)" required style="flex: 1 1 100px;">
        <input type="date" name="paid_date" value="<?= h(date('Y-m-d')) ?>" style="flex: 1 1 130px;">
        <input type="text" name="note" placeholder="Note (optional)" style="flex: 1 1 120px;">
        <button type="submit" class="btn btn--small">Add</button>
      </form>
    </div>

    <div class="section-title" id="maintenance"><h2>Monthly maintenance</h2></div>
    <div class="card">
      <?php if (empty($maintenanceItems)): ?>
        <p class="empty-state">No breakdown items yet.</p>
      <?php else: ?>
        <?php foreach ($maintenanceItems as $item): ?>
          <div class="list-item">
            <div><?= h($item['label']) ?></div>
            <div style="display:flex; align-items:center; gap:0.75rem;">
              <span><?= money((float) $item['amount']) ?></span>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_maintenance_item">
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                <button type="submit" class="btn btn--small" aria-label="Remove item">✕</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="list-item" style="font-weight: 700;">
          <div>Monthly total</div>
          <div><?= money($maintenanceTotal) ?></div>
        </div>
      <?php endif; ?>
      <form method="post" class="inline-form" style="margin-top: 0.9rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_maintenance_item">
        <input type="text" name="label" placeholder="e.g. Hosting, plugin license…" required style="flex: 2;">
        <input type="number" step="0.01" min="0" name="amount" placeholder="₱" required style="flex: 1;">
        <button type="submit" class="btn btn--small">Add</button>
      </form>

      <?php if ($client['email']): ?>
        <form method="post" style="margin-top: 1rem;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="send_maintenance_email">
          <button type="submit" class="btn btn--primary" <?= empty($maintenanceItems) ? 'disabled' : '' ?>>Send maintenance email to <?= h($client['email']) ?></button>
        </form>
      <?php else: ?>
        <p class="muted" style="font-size: 0.82rem; margin-top: 1rem;">Add an email above to enable sending this breakdown.</p>
      <?php endif; ?>
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

    <div class="section-title" id="files"><h2>Files &amp; links</h2></div>
    <div class="card">
      <?php if (empty($files)): ?>
        <p class="empty-state">No files yet.</p>
      <?php else: ?>
        <?php foreach ($files as $f): ?>
          <div class="list-item">
            <a href="<?= $f['stored_path'] ? 'uploads/' . h($f['stored_path']) : h($f['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($f['label']) ?> ↗</a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_file">
              <input type="hidden" name="file_id" value="<?= (int) $f['id'] ?>">
              <button type="submit" class="btn btn--small" aria-label="Remove link">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="inline-form" style="flex-wrap: wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_file">
        <input type="text" name="label" placeholder="Label (e.g. Contract)" style="flex: 1 1 140px;">
        <input type="url" name="url" placeholder="Paste a link…" style="flex: 2 1 180px;">
        <span class="muted" style="font-size: 0.78rem; flex-basis: 100%;">— or —</span>
        <input type="file" name="upload" style="flex: 2 1 200px;">
        <button type="submit" class="btn btn--small">Add</button>
      </form>
    </div>

    <div class="section-title"><h2>Danger zone</h2></div>
    <div class="card">
      <p class="muted" style="font-size: 0.85rem;">Deleting a client also removes its notes, tasks, payments, maintenance items, and file links. This cannot be undone.</p>
      <form method="post" onsubmit="return confirm('Delete this client and everything attached to it? This cannot be undone.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_client">
        <button type="submit" class="btn btn--danger">Delete client</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
