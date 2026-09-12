<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_client') {
    csrf_check();
    $company = trim((string) ($_POST['company_name'] ?? ''));
    $contact = trim((string) ($_POST['contact_name'] ?? ''));
    $serviceType = (string) ($_POST['service_type'] ?? 'web_design');

    if ($company === '' || $contact === '') {
        $error = 'Company name and contact name are required.';
    } else {
        if (!isset(SERVICE_TYPES[$serviceType])) {
            $serviceType = 'web_design';
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO clients (company_name, contact_name, service_type, stage, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$company, $contact, $serviceType, 'lead', $now, $now]);
        redirect('client.php?id=' . $pdo->lastInsertId());
    }
}

// Filters.
$stageFilter = (string) ($_GET['stage'] ?? '');
$serviceFilter = (string) ($_GET['service_type'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($stageFilter !== '' && isset(STAGES[$stageFilter])) {
    $where[] = 'stage = ?';
    $params[] = $stageFilter;
}
if ($serviceFilter !== '' && isset(SERVICE_TYPES[$serviceFilter])) {
    $where[] = 'service_type = ?';
    $params[] = $serviceFilter;
}
if ($search !== '') {
    $where[] = '(company_name LIKE ? OR contact_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT * FROM clients $whereSql ORDER BY updated_at DESC");
$stmt->execute($params);
$clients = $stmt->fetchAll();

$pageTitle = 'Clients';
$activeNav = 'clients';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="detail-header">
  <h1>Clients</h1>
</div>

<form class="filters" method="get">
  <input type="search" name="q" placeholder="Search company or contact…" value="<?= h($search) ?>">
  <select name="stage">
    <option value="">All stages</option>
    <?php foreach (STAGES as $key => $label): ?>
      <option value="<?= h($key) ?>" <?= $stageFilter === $key ? 'selected' : '' ?>><?= h($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="service_type">
    <option value="">All services</option>
    <?php foreach (SERVICE_TYPES as $key => $label): ?>
      <option value="<?= h($key) ?>" <?= $serviceFilter === $key ? 'selected' : '' ?>><?= h($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn">Filter</button>
  <?php if ($stageFilter || $serviceFilter || $search): ?>
    <a class="btn" href="clients.php">Clear</a>
  <?php endif; ?>
</form>

<div class="card">
  <?php if (empty($clients)): ?>
    <p class="empty-state">No clients match yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Company</th>
            <th>Contact</th>
            <th>Service</th>
            <th>Stage</th>
            <th>Next follow-up</th>
            <th>Payment</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $c): [$payLabel, $payClass] = payment_status((float) $c['total_amount'], (float) $c['amount_paid']); ?>
            <tr>
              <td class="wrap-cell"><a class="company-link" href="client.php?id=<?= (int) $c['id'] ?>"><?= h($c['company_name']) ?></a></td>
              <td class="wrap-cell"><?= h($c['contact_name']) ?></td>
              <td><?= h(service_label($c['service_type'])) ?></td>
              <td><span class="badge badge--<?= h($c['stage']) ?>"><?= h(stage_label($c['stage'])) ?></span></td>
              <td><?= date_fmt($c['next_follow_up_date']) ?></td>
              <td><span class="badge badge--<?= $payClass ?>"><?= h($payLabel) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="section-title" id="add"><h2>Add a client</h2></div>
<div class="card">
  <?php if ($error): ?><p class="error-text"><?= h($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_client">
    <div class="form-grid">
      <label>Company name
        <input type="text" name="company_name" required>
      </label>
      <label>Contact name
        <input type="text" name="contact_name" required>
      </label>
      <label>Service
        <select name="service_type">
          <?php foreach (SERVICE_TYPES as $key => $label): ?>
            <option value="<?= h($key) ?>"><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <p class="muted" style="margin-top: 0.9rem; font-size: 0.82rem;">You can fill in email, dates, pricing, and everything else on the client's own page after adding them.</p>
    <div class="form-actions">
      <button type="submit" class="btn btn--primary">Add client</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
