<?php
declare(strict_types=1);

require_once __DIR__ . '/config/cek_akses.php';
require_once __DIR__ . '/config/data.php';
wajib_login();

$user = current_user();
$role = (string)$user['role'];
$pdo = db();
$data = data_dashboard($pdo, $role, (int)$user['id']);
$topbarStats = $data;
$activeMenu = 'dashboard';
$hour = (int)date('G');
$greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 19 ? 'Selamat sore' : 'Selamat malam'));
$pageTitle = $role === 'admin' ? 'Ringkasan operasional' : 'Ringkasan hari ini';
$pageSubtitle = 'Pantau layanan cafe, pesanan, dan kemitraan UMKM dari satu tempat.';
$orderHref = 'pelayan/pesanan_baru.php';
$queueHref = 'dapur/antrian.php';
$billHref = 'kasir/daftar_tagihan.php';
$roleCopy = [
    'admin' => ['title' => 'Kendalikan hari ini, tanpa drama.', 'text' => 'Semua angka penting café ada di depan mata. Dari omzet, menu, sampai rekap mitra UMKM.', 'cta' => 'Kelola menu', 'href' => 'admin/menu.php'],
    'kasir' => ['title' => 'Antrian pembayaran, rapi.', 'text' => 'Siap tagih pesanan yang sudah diantar, konfirmasi pembayaran, dan cetak struk.', 'cta' => 'Buka daftar tagihan', 'href' => $billHref],
    'pelayan' => ['title' => 'Meja baru, cerita baru.', 'text' => 'Catat pesanan dengan cepat dan kirim pembaruan langsung ke dapur.', 'cta' => 'Buat pesanan baru', 'href' => $orderHref],
    'dapur' => ['title' => 'Dapur dalam ritme yang tepat.', 'text' => 'Antrian tersusun, status jelas, dan tim tahu pesanan mana yang harus bergerak berikutnya.', 'cta' => 'Buka antrian dapur', 'href' => $queueHref],
    'umkm' => ['title' => 'Hasil titipan, terlihat jelas.', 'text' => 'Pantau produk yang terjual dan komisi cafe dari rekap yang selalu siap.', 'cta' => 'Lihat produk saya', 'href' => 'umkm/produk.php'],
];
$hero = $roleCopy[$role] ?? $roleCopy['admin'];
$menuManageHref = $role === 'admin' ? 'admin/menu.php' : 'dashboard.php';

$statCards = [
    ['label' => 'Omzet hari ini', 'value' => money($data['omzet']), 'icon' => 'wallet', 'tone' => 'sage', 'trend' => '+12,4%', 'caption' => 'dari kemarin'],
    ['label' => 'Transaksi selesai', 'value' => number_format((int)$data['transaksi'], 0, ',', '.') . ' pesanan', 'icon' => 'receipt', 'tone' => 'clay', 'trend' => '+8,2%', 'caption' => 'minggu ini'],
    ['label' => 'Pesanan aktif', 'value' => number_format((int)$data['active_orders'], 0, ',', '.') . ' pesanan', 'icon' => 'clipboard', 'tone' => 'gold', 'trend' => 'Live', 'caption' => 'sedang berjalan'],
    ['label' => 'Penjualan UMKM', 'value' => money($data['umkm_sales']), 'icon' => 'package', 'tone' => 'blue', 'trend' => '10–12%', 'caption' => 'komisi mitra'],
];
if ($role === 'dapur') {
    $statCards = [
        ['label' => 'Menunggu diproses', 'value' => number_format((int)$data['queue_orders'], 0, ',', '.') . ' pesanan', 'icon' => 'chef', 'tone' => 'gold', 'trend' => 'Live', 'caption' => 'perlu ditinjau'],
        ['label' => 'Sedang dimasak', 'value' => number_format(count(array_filter($data['recent_orders'] ?? [], static fn(array $order): bool => $order['status'] === 'Diproses')), 0, ',', '.') . ' pesanan', 'icon' => 'refresh', 'tone' => 'clay', 'trend' => 'Dapur', 'caption' => 'status diproses'],
        ['label' => 'Siap diantar', 'value' => number_format((int)$data['ready_orders'], 0, ',', '.') . ' pesanan', 'icon' => 'check', 'tone' => 'sage', 'trend' => 'Siap', 'caption' => 'menunggu pelayan'],
        ['label' => 'Rata-rata waktu', 'value' => '08:24', 'icon' => 'clock', 'tone' => 'blue', 'trend' => '-02:10', 'caption' => 'dalam 7 hari'],
    ];
} elseif ($role === 'kasir') {
    $statCards = [
        ['label' => 'Menunggu pembayaran', 'value' => number_format((int)$data['waiting_orders'], 0, ',', '.') . ' tagihan', 'icon' => 'receipt', 'tone' => 'gold', 'trend' => 'Prioritas', 'caption' => 'perlu ditagih'],
        ['label' => 'Siap dibayar', 'value' => number_format((int)$data['ready_orders'], 0, ',', '.') . ' pesanan', 'icon' => 'wallet', 'tone' => 'sage', 'trend' => 'Live', 'caption' => 'sudah siap saji'],
        ['label' => 'Transaksi hari ini', 'value' => number_format((int)$data['transaksi'], 0, ',', '.') . ' transaksi', 'icon' => 'check', 'tone' => 'clay', 'trend' => '+8,2%', 'caption' => 'dibanding kemarin'],
        ['label' => 'Rata-rata transaksi', 'value' => money($data['avg_transaction']), 'icon' => 'trend', 'tone' => 'blue', 'trend' => 'stabil', 'caption' => 'per transaksi'],
    ];
} elseif ($role === 'pelayan') {
    $myOrders = data_orders($pdo, ['user_id' => (int)$user['id'], 'exclude_paid' => true, 'limit' => 100]);
    $statCards = [
        ['label' => 'Pesanan aktif', 'value' => number_format(count($myOrders), 0, ',', '.') . ' pesanan', 'icon' => 'receipt', 'tone' => 'clay', 'trend' => 'Live', 'caption' => 'milik Anda'],
        ['label' => 'Siap diantar', 'value' => number_format(count(array_filter($myOrders, static fn(array $order): bool => $order['status'] === 'Siap Saji')), 0, ',', '.') . ' pesanan', 'icon' => 'check', 'tone' => 'sage', 'trend' => 'Siap', 'caption' => 'tunggu antar'],
        ['label' => 'Meja terisi', 'value' => number_format(count(array_filter(data_tables($pdo), static fn(array $table): bool => $table['status'] === 'terisi')), 0, ',', '.') . ' meja', 'icon' => 'table', 'tone' => 'gold', 'trend' => 'okupansi', 'caption' => 'hari ini'],
        ['label' => 'Menu tersedia', 'value' => number_format(count($pdo && database_ready($pdo) ? data_menus($pdo) : demo_menus()), 0, ',', '.') . ' item', 'icon' => 'coffee', 'tone' => 'blue', 'trend' => 'Katalog', 'caption' => 'siap dipesan'],
    ];
} elseif ($role === 'umkm') {
    $myPartner = null;
    // Cocokkan lewat user_id supaya tetap benar setelah admin mengganti
    // nama akun mitra.
    foreach ($data['partners'] as $partner) {
        if ((int)($partner['user_id'] ?? 0) === (int)$user['id']) {
            $myPartner = $partner;
            break;
        }
    }
    if (!$myPartner) {
        foreach ($data['partners'] as $partner) {
            if (($partner['pemilik'] ?? '') === $user['name'] || ($partner['nama_akun'] ?? '') === $user['name']) {
                $myPartner = $partner;
                break;
            }
        }
    }
    $statCards = [
        ['label' => 'Penjualan bulan ini', 'value' => money($myPartner['penjualan'] ?? $data['umkm_sales']), 'icon' => 'wallet', 'tone' => 'sage', 'trend' => '+14%', 'caption' => 'dari bulan lalu'],
        ['label' => 'Produk terjual', 'value' => number_format((int)($myPartner['terjual'] ?? $data['umkm_items']), 0, ',', '.') . ' item', 'icon' => 'package', 'tone' => 'clay', 'trend' => 'Terjual', 'caption' => 'akumulasi'],
        ['label' => 'Komisi cafe', 'value' => money($myPartner['komisi'] ?? 0), 'icon' => 'trend', 'tone' => 'gold', 'trend' => ' transparan', 'caption' => 'diteruskan ke cafe'],
        ['label' => 'Produk aktif', 'value' => number_format(count(array_filter(data_menus($pdo, ['tipe' => 'umkm']), static fn(array $menu): bool => (int)$menu['is_tersedia'] === 1)), 0, ',', '.') . ' item', 'icon' => 'store', 'tone' => 'blue', 'trend' => 'Aktif', 'caption' => 'di katalog'],
    ];
}
$maxChart = max(1, ...array_map(static fn(array $item): int => (int)$item['value'], $data['chart']));
require __DIR__ . '/layout/header.php';
?>
<section class="welcome-panel">
  <div class="welcome-copy">
    <span class="eyebrow"><?= e(strtoupper(role_label($role))) ?> · <?= e(date('d M Y')) ?></span>
    <h1><?= e($greeting) ?>, <?= e(explode(' ', (string)$user['name'])[0]) ?>.</h1>
    <p><?= e($hero['title']) ?> <?= e($hero['text']) ?></p>
    <div class="welcome-actions"><a class="btn btn-primary" style="background:#fff;color:var(--ink)" href="<?= e(url($hero['href'])) ?>"><?= e($hero['cta']) ?> <?= icon('arrow-right') ?></a><span class="demo-tag" style="background:rgba(255,255,255,.1);color:var(--gold)"><?= icon('clock') ?> Diperbarui <?= e(date('H:i')) ?></span></div>
  </div>
  <div class="welcome-decoration" aria-hidden="true"><span class="deco-circle"></span><span class="deco-line"></span><span class="deco-leaf"></span><span class="deco-dot"></span></div>
</section>

<div class="stats-grid">
  <?php foreach ($statCards as $card): ?>
    <article class="card stat-card">
      <div class="stat-top"><span class="stat-label"><?= e($card['label']) ?></span><span class="stat-icon <?= e($card['tone']) ?>"><?= icon($card['icon']) ?></span></div>
      <div class="stat-value"><?= e($card['value']) ?></div>
      <div class="stat-foot"><span class="stat-trend"><?= icon($card['trend'] === 'Live' || str_contains(strtolower($card['trend']), 'transparan') ? 'spark' : 'trend-up') ?> <?= e($card['trend']) ?></span><span><?= e($card['caption']) ?></span></div>
    </article>
  <?php endforeach; ?>
</div>

<div class="content-grid two-thirds">
  <section class="card chart-card">
    <div class="chart-head"><div><h3>Performa penjualan</h3><p>Omzet transaksi lunas · 7 hari terakhir</p></div><div class="chart-legend"><i></i> Penjualan <span class="chart-total"><?= e(money(array_sum(array_column($data['chart'], 'value')))) ?></span></div></div>
    <?php if (array_sum(array_column($data['chart'], 'value')) > 0): ?>
      <div class="bar-chart"><div class="chart-y"><span><?= e(compact_money($maxChart)) ?></span><span><?= e(compact_money($maxChart * .66)) ?></span><span><?= e(compact_money($maxChart * .33)) ?></span><span>Rp0</span></div><div class="chart-bars">
        <?php foreach ($data['chart'] as $index => $point): ?><div class="bar-column <?= $index === count($data['chart']) - 1 ? 'current' : '' ?>" title="<?= e(money($point['value'])) ?>"><div class="bar" style="height:<?= max(5, (int)round(($point['value'] / $maxChart) * 100)) ?>%"></div><span><?= e($point['label']) ?></span></div><?php endforeach; ?>
      </div></div>
    <?php else: ?><div class="empty-state"><div class="empty-state-icon"><?= icon('trend') ?></div><strong>Belum ada omzet</strong><p>Data penjualan akan muncul setelah transaksi pertama dibayar.</p></div><?php endif; ?>
  </section>
  <section class="card">
    <div class="card-header"><div><h3>Menu paling dicari</h3><p>Paling sering dipesan</p></div><a class="text-link" href="<?= e(url($menuManageHref)) ?>">Lihat menu <?= icon('arrow-right') ?></a></div>
    <div class="card-body"><div class="top-menu-list">
      <?php foreach (array_slice($data['top_menus'] ?: demo_menus(), 0, 5) as $index => $menu): $sold = (int)($menu['terlaris'] ?? $menu['terjual'] ?? 0); ?>
        <div class="top-menu-item"><span class="top-menu-rank">0<?= $index + 1 ?></span><img src="<?= e(image_url($menu['foto'] ?? null)) ?>" alt="<?= e($menu['nama']) ?>"><div class="top-menu-copy"><strong><?= e($menu['nama']) ?></strong><small><?= number_format($sold, 0, ',', '.') ?> terjual · <?= e(money($menu['harga'] ?? 0)) ?></small><div class="progress-track"><i style="width:<?= min(100, max(8, $sold * 2)) ?>%"></i></div></div></div>
      <?php endforeach; ?>
      <?php if (!$data['top_menus']): ?><div class="empty-state"><p>Belum ada data menu.</p></div><?php endif; ?>
    </div></div>
  </section>
</div>

<div class="content-grid two-thirds mt-18">
  <section class="card">
    <div class="card-header"><div><h3><?= $role === 'kasir' ? 'Tagihan perlu dibayar' : 'Aktivitas terbaru' ?></h3><p>Pesanan yang sedang bergerak di workspace</p></div><a class="text-link" href="<?= e(url($role === 'kasir' ? $billHref : 'dashboard.php')) ?>">Lihat semua <?= icon('arrow-right') ?></a></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Pesanan</th><th>Meja / pelayan</th><th>Pelanggan</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
      <?php foreach (array_slice($data['recent_orders'] ?: demo_orders(), 0, 5) as $order): ?>
        <tr><td><div class="primary-cell"><?= e($order['kode_pesanan'] ?? $order['kode'] ?? '-') ?></div><div class="secondary-cell"><?= e(relative_time($order['created_at'] ?? null)) ?></div></td><td><div class="primary-cell"><?= $order['meja'] ? 'Meja ' . e($order['meja']) : 'Takeaway' ?></div><div class="secondary-cell"><?= e($order['pelayan'] ?? 'Pelayan') ?></div></td><td><?= e(order_pelanggan($order)) ?></td><td><span class="badge badge-<?= e(status_class((string)($order['status'] ?? 'Baru'))) ?>"><?= e(status_label((string)($order['status'] ?? 'Baru'))) ?></span></td><td class="primary-cell"><?= e(money($order['total'] ?? 0)) ?></td><td class="text-right"><a class="btn btn-ghost btn-sm" href="<?= e(url($role === 'kasir' ? 'kasir/proses_bayar.php?id=' . (int)($order['id'] ?? 0) : 'pelayan/pesanan_aktif.php')) ?>"><?= icon('eye') ?></a></td></tr>
      <?php endforeach; ?>
      <?php if (!$data['recent_orders']): ?><tr><td colspan="6"><div class="empty-state"><strong>Belum ada aktivitas</strong><p>Pesanan berikutnya akan tampil di sini.</p></div></td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
  <div class="stack">
    <?php if ($role === 'pelayan'): ?>
      <section class="card"><div class="card-header"><div><h3>Pilih meja</h3><p>Status meja saat ini</p></div><a class="text-link" href="<?= e(url($orderHref)) ?>">Buka <?= icon('arrow-right') ?></a></div><div class="card-body"><div class="table-grid"><?php foreach (data_tables($pdo) as $table): ?><button type="button" class="table-card <?= $table['status'] === 'terisi' ? 'occupied' : '' ?>" <?= $table['status'] === 'terisi' ? 'disabled' : '' ?>><span><?= e($table['nomor']) ?></span><small><?= $table['status'] === 'terisi' ? 'Terisi' : 'Tersedia' ?></small></button><?php endforeach; ?></div></div></section>
    <?php elseif ($role === 'dapur'): ?>
      <section class="card"><div class="card-header"><div><h3>Antrian singkat</h3><p>Prioritas berikutnya</p></div><a class="text-link" href="<?= e(url($queueHref)) ?>">Kelola <?= icon('arrow-right') ?></a></div><div class="card-body"><div class="activity-list"><?php foreach (array_slice($data['recent_orders'] ?: demo_orders(), 0, 4) as $order): ?><div class="activity-item"><span class="activity-icon <?= $order['status'] === 'Diproses' ? 'clay' : 'gold' ?>"><?= icon('chef') ?></span><div class="activity-copy"><strong><?= $order['meja'] ? 'Meja ' . e($order['meja']) : 'Takeaway' ?> · <?= e($order['kode_pesanan'] ?? '-') ?></strong><span><?= e(order_pelanggan($order, 'Pelanggan')) ?> · <?= count($order['items'] ?? []) ?> item · <?= e(status_label((string)$order['status'])) ?></span></div><span class="activity-time"><?= e(relative_time($order['created_at'] ?? null)) ?></span></div><?php endforeach; ?></div></div></section>
    <?php elseif ($role === 'umkm'): ?>
      <section class="card"><div class="card-header"><div><h3>Rekap mitra</h3><p>Perkiraan komisi periode ini</p></div><a class="text-link" href="<?= e(url('umkm/produk.php')) ?>">Detail <?= icon('arrow-right') ?></a></div><div class="card-body"><div class="activity-list"><?php foreach (array_slice($data['partners'], 0, 3) as $partner): ?><div class="activity-item"><span class="activity-icon clay"><?= icon('store') ?></span><div class="activity-copy"><strong><?= e($partner['nama_usaha']) ?></strong><span><?= e($partner['komisi_persen'] ?? 10) ?>% komisi · <?= e($partner['status'] ?? 'aktif') ?></span></div><span class="activity-time"><?= e(money($partner['komisi'] ?? 0)) ?></span></div><?php endforeach; ?></div></div></section>
    <?php else: ?>
      <section class="card"><div class="card-header"><div><h3>Stok perlu diperhatikan</h3><p>Produk dengan stok menipis</p></div><a class="text-link" href="<?= e(url($menuManageHref)) ?>">Kelola <?= icon('arrow-right') ?></a></div><div class="card-body"><div class="low-stock-list"><?php foreach (array_slice($data['low_stock'] ?: [], 0, 5) as $menu): ?><div class="low-stock-item"><img src="<?= e(image_url($menu['foto'] ?? null)) ?>" alt=""><div class="low-stock-copy"><strong><?= e($menu['nama']) ?></strong><small><?= e($menu['kode'] ?? '') ?></small></div><span class="low-stock-qty"><?= (int)($menu['stok'] ?? 0) ?> pcs</span></div><?php endforeach; ?><?php if (!$data['low_stock']): ?><div class="empty-state"><strong>Stok aman</strong><p>Belum ada stok di bawah batas.</p></div><?php endif; ?></div></div></section>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
