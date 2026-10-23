<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['kasir']);
$user = current_user();
$pdo = db();
if (database_ready($pdo)) {
    $payments = db_all('SELECT pay.*, p.kode_pesanan, p.total, p.nama_pelanggan, t.nomor AS meja_nomor, u.nama AS pelayan FROM pembayaran pay JOIN pesanan p ON p.id=pay.pesanan_id LEFT JOIN meja t ON t.id=p.meja_id JOIN users u ON u.id=p.pelayan_id WHERE DATE(pay.waktu_bayar)=CURDATE() ORDER BY pay.id DESC');
} else {
    $payments = [];
    foreach (demo_orders() as $order) {
        if (!empty($order['payment'])) $payments[] = array_merge($order['payment'], ['kode_pesanan' => $order['kode_pesanan'], 'total' => $order['total'], 'nama_pelanggan' => $order['nama_pelanggan'] ?? '', 'meja_nomor' => $order['meja'], 'pelayan' => $order['pelayan']]);
    }
}
$topbarStats = data_dashboard($pdo, 'kasir', (int)$user['id']);
$activeMenu = 'transactions';
$pageTitle = 'Riwayat Transaksi';
$pageSubtitle = 'Semua pembayaran yang selesai pada shift berjalan.';
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">Shift ledger</span><h1 class="page-title">Riwayat Transaksi</h1><p class="page-subtitle">Nomor struk, metode, dan kembalian tercatat otomatis.</p></div><div class="page-actions"><button class="btn btn-ghost" type="button" data-print><?= icon('printer') ?> Cetak</button><a class="btn btn-primary" href="<?= e(url('kasir/daftar_tagihan.php')) ?>"><?= icon('receipt') ?> Tagihan menunggu</a></div></div>
<section class="card"><div class="card-header"><div><h3>Transaksi hari ini</h3><p><?= count($payments) ?> pembayaran berhasil diproses</p></div><span class="badge badge-ready"><?= e(format_date_id(date('Y-m-d'), false)) ?></span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Nomor struk</th><th>Waktu</th><th>Pesanan</th><th>Pelanggan</th><th>Meja</th><th>Metode</th><th class="text-right">Bayar</th><th class="text-right">Kembalian</th><th></th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td class="order-code"><?= e($payment['nomor_struk'] ?? '-') ?></td><td><?= e(format_date_id($payment['waktu_bayar'] ?? null)) ?></td><td><div class="primary-cell"><?= e($payment['kode_pesanan'] ?? '-') ?></div><div class="secondary-cell"><?= e($payment['pelayan'] ?? '-') ?></div></td><td><?= e(pelanggan_name($payment['nama_pelanggan'] ?? '')) ?></td><td><?= e($payment['meja_nomor'] ?? 'Takeaway') ?></td><td><span class="role-pill <?= e($payment['metode'] ?? 'tunai') ?>"><?= e(strtoupper((string)($payment['metode'] ?? 'tunai'))) ?></span></td><td class="text-right primary-cell"><?= e(money($payment['jumlah_bayar'] ?? 0)) ?></td><td class="text-right"><?= e(money($payment['kembalian'] ?? 0)) ?></td><td class="text-right"><a class="btn btn-ghost btn-sm" href="<?= e(url('kasir/struk.php?id=' . (int)($payment['pesanan_id'] ?? 0))) ?>"><?= icon('eye') ?> Struk</a></td></tr><?php endforeach; ?><?php if (!$payments): ?><tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon"><?= icon('receipt') ?></div><strong>Belum ada transaksi</strong><p>Transaksi lunas akan muncul di sini.</p></div></td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../layout/footer.php'; ?>
