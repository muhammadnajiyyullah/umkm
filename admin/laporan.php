<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['admin']);
$user = current_user();
$pdo = db();
$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];
$report = data_report($pdo, $from, $to);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-penjualan-' . $from . '-sd-' . $to . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Kode pesanan', 'Waktu', 'Meja', 'Pelanggan', 'Pelayan', 'Metode', 'Nomor struk', 'Total']);
    foreach ($report['orders'] as $order) {
        fputcsv($output, [$order['kode_pesanan'] ?? '-', $order['created_at'] ?? '-', $order['meja_nomor'] ?? ($order['meja'] ?? 'Takeaway'), pelanggan_name($order['nama_pelanggan'] ?? ''), $order['pelayan'] ?? '-', $order['metode'] ?? '-', $order['nomor_struk'] ?? '-', (float)($order['total'] ?? 0)]);
    }
    fclose($output);
    exit;
}

$topbarStats = data_dashboard($pdo, 'admin', (int)$user['id']);
$activeMenu = 'reports';
$pageTitle = 'Laporan Penjualan';
$pageSubtitle = 'Rekap transaksi, omzet, dan komisi UMKM dari data yang sudah lunas.';
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">Numbers that matter</span><h1 class="page-title">Laporan Penjualan</h1><p class="page-subtitle">Gunakan filter tanggal untuk melihat performa cafe dan rekap bagi hasil mitra.</p></div><div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('admin/laporan.php?export=csv&from=' . $from . '&to=' . $to)) ?>"><?= icon('download') ?> Ekspor CSV</a><button class="btn btn-primary" type="button" data-print><?= icon('printer') ?> Cetak laporan</button></div></div>
<section class="card mb-18"><form class="filter-bar" method="get"><div class="form-group" style="min-width:155px"><label class="form-label" for="from">Dari tanggal</label><input class="form-control" id="from" type="date" name="from" value="<?= e($from) ?>"></div><div class="form-group" style="min-width:155px"><label class="form-label" for="to">Sampai tanggal</label><input class="form-control" id="to" type="date" name="to" value="<?= e($to) ?>"></div><button class="btn btn-primary" type="submit" style="align-self:end"><?= icon('filter') ?> Terapkan</button><div class="filter-spacer"></div><span class="muted" style="align-self:end;font-size:10px"><?= e(format_date_id($from, false)) ?> — <?= e(format_date_id($to, false)) ?></span></form></section>
<div class="stats-grid"><article class="card stat-card"><div class="stat-top"><span class="stat-label">Omzet periode ini</span><span class="stat-icon sage"><?= icon('wallet') ?></span></div><div class="stat-value"><?= e(money($report['omzet'])) ?></div><div class="stat-foot"><span class="stat-trend"><?= icon('trend-up') ?> lunas</span><span>dari <?= count($report['orders']) ?> transaksi</span></div></article><article class="card stat-card"><div class="stat-top"><span class="stat-label">Jumlah transaksi</span><span class="stat-icon clay"><?= icon('receipt') ?></span></div><div class="stat-value"><?= number_format((int)$report['transaksi'], 0, ',', '.') ?></div><div class="stat-foot"><span class="stat-trend"><?= icon('clipboard') ?> tercatat</span></div></article><article class="card stat-card"><div class="stat-top"><span class="stat-label">Item terjual</span><span class="stat-icon gold"><?= icon('package') ?></span></div><div class="stat-value"><?= number_format((int)$report['items'], 0, ',', '.') ?></div><div class="stat-foot"><span class="stat-trend"><?= icon('trend-up') ?> semua channel</span></div></article><article class="card stat-card"><div class="stat-top"><span class="stat-label">Rata-rata transaksi</span><span class="stat-icon blue"><?= icon('trend') ?></span></div><div class="stat-value"><?= e(money($report['transaksi'] ? $report['omzet'] / $report['transaksi'] : 0)) ?></div><div class="stat-foot"><span class="stat-trend"><?= icon('spark') ?> overview</span></div></article></div>
<div class="content-grid two-thirds"><section class="card"><div class="card-header"><div><h3>Transaksi periode ini</h3><p>Detail pembayaran yang sudah berstatus lunas</p></div><span class="badge badge-ready"><?= count($report['orders']) ?> baris</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Pesanan</th><th>Waktu</th><th>Meja</th><th>Pelanggan</th><th>Metode</th><th>Struk</th><th class="text-right">Total</th></tr></thead><tbody><?php foreach ($report['orders'] as $order): ?><tr><td><div class="primary-cell"><?= e($order['kode_pesanan'] ?? '-') ?></div><div class="secondary-cell"><?= e($order['pelayan'] ?? '-') ?></div></td><td><?= e(format_date_id($order['created_at'] ?? ($order['waktu_bayar'] ?? null))) ?></td><td><?= e($order['meja_nomor'] ?? ($order['meja'] ?? 'Takeaway')) ?></td><td><?= e(order_pelanggan($order)) ?></td><td><span class="role-pill <?= e($order['metode'] ?? 'tunai') ?>"><?= e(ucfirst((string)($order['metode'] ?? 'tunai'))) ?></span></td><td class="order-code"><?= e($order['nomor_struk'] ?? '-') ?></td><td class="text-right primary-cell"><?= e(money($order['total'] ?? 0)) ?></td></tr><?php endforeach; ?><?php if (!$report['orders']): ?><tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><?= icon('receipt') ?></div><strong>Belum ada transaksi</strong><p>Coba ubah rentang tanggal atau selesaikan pembayaran.</p></div></td></tr><?php endif; ?></tbody></table></div></section>
<section class="card"><div class="card-header"><div><h3>Rekap UMKM</h3><p>Penjualan dan komisi mitra</p></div><span class="badge badge-umkm">UMKM</span></div><div class="card-body"><div class="activity-list"><?php foreach ($report['partners'] as $partner): ?><div class="activity-item"><span class="activity-icon clay"><?= icon('store') ?></span><div class="activity-copy"><strong><?= e($partner['nama_usaha']) ?></strong><span><?= (int)($partner['terjual'] ?? 0) ?> item · <?= e($partner['komisi_persen'] ?? 0) ?>% komisi</span></div><div class="text-right"><strong style="font-size:10px"><?= e(money($partner['penjualan'] ?? 0)) ?></strong><small><?= e(money($partner['komisi'] ?? 0)) ?> komisi</small></div></div><?php endforeach; ?><?php if (!$report['partners']): ?><div class="empty-state"><strong>Belum ada rekap UMKM</strong><p>Data akan muncul setelah produk titipan terjual.</p></div><?php endif; ?></div></div></section></div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
