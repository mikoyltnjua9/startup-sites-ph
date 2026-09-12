<?php
// Expects $pageTitle (string) and optionally $activeNav ('dashboard'|'clients')
// to be set before this is included.
$pageTitle = $pageTitle ?? 'Client Portal';
$activeNav = $activeNav ?? '';
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
  <a class="topbar-brand" href="index.php">
    <img src="../icons/favicon.png" alt="" onerror="this.style.display='none'">
    Client Portal
  </a>
  <?php if (!empty($_SESSION['is_admin'])): ?>
  <nav class="topbar-nav">
    <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
    <a href="clients.php" class="<?= $activeNav === 'clients' ? 'is-active' : '' ?>">Clients</a>
    <a href="logout.php" class="btn btn--small">Log out</a>
  </nav>
  <?php endif; ?>
</div>
<div class="wrap">
