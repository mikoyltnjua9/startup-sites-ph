<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

// Stage pipeline counts.
$stageCounts = array_fill_keys(array_keys(STAGES), 0);
$stmt = $pdo->query('SELECT stage, COUNT(*) AS cnt FROM clients GROUP BY stage');
foreach ($stmt->fetchAll() as $row) {
    if (isset($stageCounts[$row['stage']])) {
        $stageCounts[$row['stage']] = (int) $row['cnt'];
    }
}
$totalClients = array_sum($stageCounts);

$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+7 days'));

// Follow-ups due soon or overdue (only for clients still being actively worked).
$stmt = $pdo->prepare(
    "SELECT id, company_name, contact_name, stage, next_follow_up_date
     FROM clients
     WHERE next_follow_up_date IS NOT NULL
       AND stage NOT IN ('launched', 'maintenance')
       AND next_follow_up_date <= ?
     ORDER BY next_follow_up_date ASC"
);
$stmt->execute([$soon]);
$followUps = $stmt->fetchAll();

// Launch dates approaching or overdue (project not yet actually launched).
$stmt = $pdo->prepare(
    "SELECT id, company_name, contact_name, stage, target_launch_date
     FROM clients
     WHERE target_launch_date IS NOT NULL
       AND actual_launch_date IS NULL
       AND target_launch_date <= ?
     ORDER BY target_launch_date ASC"
);
$stmt->execute([$soon]);
$launchDates = $stmt->fetchAll();

// Revenue (all payments ever received) and expenses.
$totalRevenue = (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments')->fetchColumn();
$totalExpenses = (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM expenses')->fetchColumn();
$netProfit = $totalRevenue - $totalExpenses;

// Outstanding balance across every client: total project value minus what
// each has actually paid, summed (never negative per-client).
$balanceRows = $pdo->query(
    "SELECT c.id, c.total_amount, COALESCE(SUM(p.amount), 0) AS paid
     FROM clients c LEFT JOIN payments p ON p.client_id = c.id
     GROUP BY c.id, c.total_amount"
)->fetchAll();
$outstanding = 0.0;
foreach ($balanceRows as $row) {
    $outstanding += max(0, (float) $row['total_amount'] - (float) $row['paid']);
}

// Monthly revenue vs expenses for the last 12 months. Bucketed in PHP
// (rather than a DB-specific date-grouping function) so this works
// identically against MySQL in production.
$monthsBack = 11;
$startMonth = date('Y-m-01', strtotime("-$monthsBack months"));
$monthLabels = [];
$monthKeys = [];
for ($i = $monthsBack; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $monthKeys[] = $key;
    $monthLabels[] = date('M \'y', strtotime($key . '-01'));
}
$revenueByMonth = array_fill_keys($monthKeys, 0.0);
$expensesByMonth = array_fill_keys($monthKeys, 0.0);

$stmt = $pdo->prepare('SELECT amount, paid_date FROM payments WHERE paid_date >= ?');
$stmt->execute([$startMonth]);
foreach ($stmt->fetchAll() as $row) {
    $key = substr((string) $row['paid_date'], 0, 7);
    if (isset($revenueByMonth[$key])) {
        $revenueByMonth[$key] += (float) $row['amount'];
    }
}
$stmt = $pdo->prepare('SELECT amount, expense_date FROM expenses WHERE expense_date >= ?');
$stmt->execute([$startMonth]);
foreach ($stmt->fetchAll() as $row) {
    $key = substr((string) $row['expense_date'], 0, 7);
    if (isset($expensesByMonth[$key])) {
        $expensesByMonth[$key] += (float) $row['amount'];
    }
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="detail-header">
  <h1>Dashboard</h1>
  <a class="btn btn--primary" href="clients.php#add">+ Add client</a>
</div>

<div class="section-title"><h2>Pipeline (<?= $totalClients ?> total)</h2></div>
<div class="grid grid--stats">
  <?php foreach (STAGES as $key => $label): ?>
    <div class="card stat">
      <strong><?= $stageCounts[$key] ?></strong>
      <span><?= h($label) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="section-title"><h2>Revenue &amp; expenses</h2></div>
<div class="grid grid--stats">
  <div class="card stat">
    <strong><?= money($totalRevenue) ?></strong>
    <span>Total revenue</span>
  </div>
  <div class="card stat">
    <strong><?= money($totalExpenses) ?></strong>
    <span>Total expenses</span>
  </div>
  <div class="card stat">
    <strong style="color: <?= $netProfit >= 0 ? '#1f8f6a' : '#c94636' ?>;"><?= money($netProfit) ?></strong>
    <span>Net profit</span>
  </div>
  <div class="card stat">
    <strong><?= money($outstanding) ?></strong>
    <span>Outstanding balance</span>
  </div>
</div>

<div class="card" style="margin-top: 1rem;">
  <canvas id="revenueChart" height="90"></canvas>
</div>

<div class="section-title"><h2>Pipeline by stage</h2></div>
<div class="card">
  <canvas id="pipelineChart" height="90"></canvas>
</div>

<div class="section-title"><h2>Follow-ups due</h2></div>
<div class="card">
  <?php if (empty($followUps)): ?>
    <p class="empty-state">Nothing due in the next 7 days.</p>
  <?php else: ?>
    <?php foreach ($followUps as $c): $overdue = $c['next_follow_up_date'] < $today; ?>
      <div class="list-item">
        <div>
          <a class="company-link" href="client.php?id=<?= (int) $c['id'] ?>"><?= h($c['company_name']) ?></a>
          <span class="muted"> — <?= h($c['contact_name']) ?></span>
        </div>
        <span class="badge <?= $overdue ? 'badge--unpaid' : 'badge--deposit_paid' ?> pill-nowrap">
          <?= $overdue ? 'Overdue' : 'Due' ?> <?= date_fmt($c['next_follow_up_date']) ?>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="section-title"><h2>Launch dates approaching</h2></div>
<div class="card">
  <?php if (empty($launchDates)): ?>
    <p class="empty-state">Nothing on the horizon.</p>
  <?php else: ?>
    <?php foreach ($launchDates as $c): $overdue = $c['target_launch_date'] < $today; ?>
      <div class="list-item">
        <div>
          <a class="company-link" href="client.php?id=<?= (int) $c['id'] ?>"><?= h($c['company_name']) ?></a>
          <span class="badge badge--<?= h($c['stage']) ?>"><?= h(stage_label($c['stage'])) ?></span>
        </div>
        <span class="badge <?= $overdue ? 'badge--unpaid' : 'badge--deposit_paid' ?> pill-nowrap">
          Target <?= date_fmt($c['target_launch_date']) ?>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  const monthLabels = <?= json_encode($monthLabels) ?>;
  const revenueData = <?= json_encode(array_values($revenueByMonth)) ?>;
  const expensesData = <?= json_encode(array_values($expensesByMonth)) ?>;

  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
      labels: monthLabels,
      datasets: [
        { label: 'Revenue', data: revenueData, backgroundColor: '#34c79a' },
        { label: 'Expenses', data: expensesData, backgroundColor: '#ff6b5b' },
      ],
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Revenue vs. expenses, last 12 months' } },
      scales: { y: { beginAtZero: true } },
    },
  });

  const stageLabels = <?= json_encode(array_values(STAGES)) ?>;
  const stageData = <?= json_encode(array_values($stageCounts)) ?>;

  new Chart(document.getElementById('pipelineChart'), {
    type: 'bar',
    data: {
      labels: stageLabels,
      datasets: [{ label: 'Clients', data: stageData, backgroundColor: '#4da3ff' }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
    },
  });
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
