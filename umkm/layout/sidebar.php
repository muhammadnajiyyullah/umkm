<?php
$sidebarItems = nav_items((string)($user['role'] ?? 'admin'));
$roleName = role_label((string)($user['role'] ?? ''));
?>
<aside class="sidebar" id="appSidebar">
  <div class="sidebar-topline"></div>
  <div class="brand-lockup">
    <a class="brand" href="<?= e(url('dashboard.php')) ?>" aria-label="KOPI NUSA dashboard">
      <span class="brand-mark"><span class="brand-mark-dot"></span><?= icon('coffee') ?></span>
      <span class="brand-copy"><strong>KOPI<span>NUSA</span></strong><small>CAFE · COFFEE · COMMUNITY</small></span>
    </a>
    <button class="sidebar-close d-lg-none" type="button" data-sidebar-close aria-label="Tutup navigasi"><?= icon('x') ?></button>
  </div>

  <div class="sidebar-context">
    <span class="context-dot"></span>
    <span>Operasional hari ini</span>
    <span class="context-live">LIVE</span>
  </div>

  <nav class="sidebar-nav" aria-label="Navigasi utama">
    <p class="nav-label">WORKSPACE</p>
    <?php foreach ($sidebarItems as $item): ?>
      <?php $isActive = ($activeMenu ?? '') === $item['key']; ?>
      <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= e(url($item['href'])) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
        <span class="nav-icon"><?= icon($item['icon']) ?></span>
        <span><?= e($item['label']) ?></span>
        <?php if ($item['key'] === 'bills' && (int)($topbarStats['waiting_orders'] ?? 0) > 0): ?>
          <em class="nav-count"><?= (int)$topbarStats['waiting_orders'] ?></em>
        <?php elseif ($item['key'] === 'queue' && (int)($topbarStats['queue_orders'] ?? 0) > 0): ?>
          <em class="nav-count"><?= (int)$topbarStats['queue_orders'] ?></em>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-bottom">
    <div class="sidebar-help-card">
      <div class="help-icon"><?= icon('spark') ?></div>
      <div><strong>Butuh bantuan?</strong><span>Lihat panduan operasional</span></div>
      <button type="button" class="help-arrow" data-help-toggle><?= icon('arrow-right') ?></button>
      <div class="help-popover" data-help-popover>
        <strong>Alur cepat</strong>
        <span>Pelayan → Dapur → Kasir → Struk</span>
        <span>Status berubah otomatis dan tercatat.</span>
      </div>
    </div>
    <div class="sidebar-user">
      <span class="avatar avatar-<?= e(avatar_color((string)($user['name'] ?? 'KOPI NUSA'))) ?>"><?= e(initials((string)($user['name'] ?? 'KOPI NUSA'))) ?></span>
      <span class="sidebar-user-copy"><strong><?= e($user['name'] ?? 'Tamu') ?></strong><small><?= e($roleName) ?></small></span>
      <a class="sidebar-logout" href="<?= e(url('index.php?logout=1')) ?>" title="Keluar"><?= icon('logout') ?></a>
    </div>
  </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-backdrop></div>
