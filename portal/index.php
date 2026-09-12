<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

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

// Revenue overview.
$row = $pdo->query('SELECT COALESCE(SUM(total_amount), 0) AS total, COALESCE(SUM(amount_paid), 0) AS paid FROM clients')->fetch();
$totalRevenue = (float) $row['total'];
$paidRevenue = (float) $row['paid'];
$outstanding = $totalRevenue - $paidRevenue;

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

<div class="section-title"><h2>Revenue overview</h2></div>
<div class="grid grid--stats">
  <div class="card stat">
    <strong><?= money($totalRevenue) ?></strong>
    <span>Total value</span>
  </div>
  <div class="card stat">
    <strong><?= money($paidRevenue) ?></strong>
    <span>Collected</span>
  </div>
  <div class="card stat">
    <strong><?= money($outstanding) ?></strong>
    <span>Outstanding</span>
  </div>
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

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
