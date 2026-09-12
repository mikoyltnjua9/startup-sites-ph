<?php
// Expects $pageTitle (string) and optionally $activeNav
// ('dashboard'|'clients'|'expenses'|'team'|'tasks') to be set before this
// is included.
$pageTitle = $pageTitle ?? 'Client Portal';
$activeNav = $activeNav ?? '';
$loggedIn = !empty($_SESSION['user_id']);
$brandHref = $loggedIn ? (is_admin() ? 'index.php' : 'my_tasks.php') : 'login.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($pageTitle) ?> · Startup Sites PH Portal</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar">
  <a class="topbar-brand" href="<?= h($brandHref) ?>">
    <img src="../icons/favicon.png" alt="" onerror="this.style.display='none'">
    Client Portal
  </a>
  <?php if ($loggedIn): ?>
  <nav class="topbar-nav">
    <?php if (is_admin()): ?>
      <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
      <a href="clients.php" class="<?= $activeNav === 'clients' ? 'is-active' : '' ?>">Clients</a>
      <a href="expenses.php" class="<?= $activeNav === 'expenses' ? 'is-active' : '' ?>">Expenses</a>
      <a href="my_tasks.php" class="<?= $activeNav === 'tasks' ? 'is-active' : '' ?>">My Tasks</a>
      <a href="users.php" class="<?= $activeNav === 'team' ? 'is-active' : '' ?>">Team</a>
    <?php else: ?>
      <a href="my_tasks.php" class="<?= $activeNav === 'tasks' ? 'is-active' : '' ?>">My Tasks</a>
    <?php endif; ?>
    <span class="muted" style="font-size: 0.82rem;"><?= h($_SESSION['user_name'] ?? '') ?></span>
    <a href="logout.php" class="btn btn--small">Log out</a>
  </nav>
  <?php endif; ?>
</div>
<div class="wrap">
