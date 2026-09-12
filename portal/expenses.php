<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_expense') {
        $label = trim((string) ($_POST['label'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? 0);
        $date = (string) ($_POST['expense_date'] ?? '') ?: date('Y-m-d');
        $clientId = (int) ($_POST['client_id'] ?? 0) ?: null;

        if ($label === '' || $amount <= 0) {
            $error = 'Label and a positive amount are required.';
        } else {
            $ins = $pdo->prepare('INSERT INTO expenses (client_id, label, amount, expense_date, created_at) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$clientId, $label, $amount, $date, date('Y-m-d H:i:s')]);
            redirect('expenses.php');
        }
    } elseif ($action === 'delete_expense') {
        $del = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
        $del->execute([(int) ($_POST['expense_id'] ?? 0)]);
        redirect('expenses.php');
    }
}

$expenses = $pdo->query(
    "SELECT e.*, c.company_name
     FROM expenses e LEFT JOIN clients c ON c.id = e.client_id
     ORDER BY e.expense_date DESC, e.created_at DESC"
)->fetchAll();

$totalExpenses = (float) array_sum(array_column($expenses, 'amount'));

$clients = $pdo->query('SELECT id, company_name FROM clients ORDER BY company_name ASC')->fetchAll();

$pageTitle = 'Expenses';
$activeNav = 'expenses';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="detail-header">
  <h1>Expenses</h1>
  <span class="badge badge--unpaid">All-time: <?= money($totalExpenses) ?></span>
</div>

<div class="section-title"><h2>Add an expense</h2></div>
<div class="card">
  <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_expense">
    <div class="form-grid">
      <label>Label
        <input type="text" name="label" placeholder="e.g. Hosting renewal, software license…" required>
      </label>
      <label>Amount (₱)
        <input type="number" step="0.01" min="0.01" name="amount" required>
      </label>
      <label>Date
        <input type="date" name="expense_date" value="<?= h(date('Y-m-d')) ?>">
      </label>
      <label>Linked client (optional)
        <select name="client_id">
          <option value="">— General / overhead —</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= h($c['company_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn--primary">Add expense</button>
    </div>
  </form>
</div>

<div class="section-title"><h2>All expenses</h2></div>
<div class="card">
  <?php if (empty($expenses)): ?>
    <p class="empty-state">No expenses logged yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Date</th><th>Label</th><th>Client</th><th>Amount</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($expenses as $e): ?>
            <tr>
              <td><?= date_fmt($e['expense_date']) ?></td>
              <td class="wrap-cell"><?= h($e['label']) ?></td>
              <td><?= $e['company_name'] ? h($e['company_name']) : '<span class="muted">General</span>' ?></td>
              <td><?= money((float) $e['amount']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Delete this expense?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_expense">
                  <input type="hidden" name="expense_id" value="<?= (int) $e['id'] ?>">
                  <button type="submit" class="btn btn--small">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
