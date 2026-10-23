<?php
require_once __DIR__ . '/../config/app.php';
$user = current_user() ?: ['name' => 'Pengguna', 'role' => ''];
$activeMenu = $activeMenu ?? '';
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$topbarStats = $topbarStats ?? [];
$flashes = pull_flash();
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#202a25">
  <title><?= e($pageTitle) ?> · KOPI NUSA</title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body data-base="<?= e(app_base_url()) ?>" data-csrf="<?= e(csrf_token()) ?>">
<div class="app-shell">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <div class="main-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="icon-btn menu-toggle d-lg-none" type="button" data-sidebar-open aria-label="Buka navigasi"><?= icon('menu') ?></button>
        <div class="topbar-breadcrumb"><span>Workspace</span><?= icon('arrow-right') ?><strong><?= e($pageTitle) ?></strong></div>
      </div>
      <div class="topbar-actions">
        <div class="topbar-date d-none d-md-flex"><span class="date-pulse"></span><?= e(format_date_id(date('Y-m-d'), false)) ?></div>
        <button class="icon-btn notification-btn" type="button" data-notification-toggle aria-label="Notifikasi"><?= icon('bell') ?><span class="notification-dot"></span></button>
        <div class="notification-popover" data-notification-popover>
          <div class="popover-heading"><strong>Notifikasi</strong><span>Hari ini</span></div>
          <div class="notification-item"><span class="notification-symbol clay"><?= icon('receipt') ?></span><span><strong><?= (int)($topbarStats['waiting_orders'] ?? 0) ?> tagihan menunggu</strong><small>Periksa dari halaman kasir</small></span></div>
          <div class="notification-item"><span class="notification-symbol sage"><?= icon('package') ?></span><span><strong><?= (int)($topbarStats['ready_orders'] ?? 0) ?> pesanan siap saji</strong><small>Segera antar ke meja</small></span></div>
          <a class="popover-link" href="<?= e(url('dashboard.php')) ?>">Lihat ringkasan <?= icon('arrow-right') ?></a>
        </div>
        <div class="topbar-user"><span class="avatar avatar-<?= e(avatar_color((string)($user['name'] ?? 'KOPI NUSA'))) ?>"><?= e(initials((string)($user['name'] ?? 'KOPI NUSA'))) ?></span><span class="topbar-user-copy d-none d-sm-flex"><strong><?= e($user['name'] ?? 'Pengguna') ?></strong><small><?= e(role_label((string)($user['role'] ?? ''))) ?></small></span><?= icon('arrow-down', 'topbar-chevron') ?></div>
      </div>
    </header>
    <main class="content">
      <?php if ($flashes): ?>
        <div class="flash-stack">
          <?php foreach ($flashes as $flash): ?>
            <div class="flash flash-<?= e($flash['type'] ?? 'info') ?>" data-flash><?= icon($flash['type'] === 'success' ? 'check' : ($flash['type'] === 'danger' ? 'alert' : 'spark')) ?><span><?= e($flash['message'] ?? '') ?></span><button type="button" data-dismiss-flash aria-label="Tutup"><?= icon('x') ?></button></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
