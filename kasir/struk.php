<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['kasir', 'admin', 'pelayan']);
$user = current_user();
$pdo = db();
$orderId = (int)($_GET['id'] ?? 0);
$order = $orderId > 0 ? data_order($pdo, $orderId) : null;
if (!$order) {
    set_flash('danger', 'Data struk tidak ditemukan.');
    redirect('dashboard.php');
}
$payment = $order['payment'] ?? null;
$topbarStats = data_dashboard($pdo, (string)$user['role'], (int)$user['id']);
$activeMenu = $user['role'] === 'kasir' ? 'bills' : 'dashboard';
$pageTitle = 'Struk Pembayaran';
$pageSubtitle = 'Bukti transaksi berformat thermal, siap dicetak atau disimpan.';
require __DIR__ . '/../layout/header.php';
?>
<div class="receipt-page"><div class="receipt-toolbar"><div><span class="eyebrow">Payment proof · <?= e($payment['nomor_struk'] ?? 'BELUM DIBAYAR') ?></span><h1 class="page-title" style="font-size:2rem">Struk siap dicetak</h1><p class="page-subtitle">Simpan nomor struk sebagai bukti pembayaran pelanggan.</p></div><div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('kasir/daftar_tagihan.php')) ?>"><?= icon('arrow-right') ?> Kembali</a><button class="btn btn-primary" type="button" data-print><?= icon('printer') ?> Cetak struk</button></div></div>
<div class="receipt-paper"><div class="receipt-brand"><strong>KOPI<span style="color:#b56d4f">NUSA</span></strong><span>CAFE · COFFEE · COMMUNITY</span><span>Jl. Rasa Nusantara No. 08 · Local server</span></div><div class="receipt-meta"><div><span>No. struk</span><strong><?= e($payment['nomor_struk'] ?? '-') ?></strong></div><div><span>Waktu</span><strong><?= e(format_date_id($payment['waktu_bayar'] ?? ($order['created_at'] ?? null))) ?></strong></div><div><span>Pesanan</span><strong><?= e($order['kode_pesanan'] ?? '-') ?></strong></div><div><span>Pelanggan</span><strong><?= e(order_pelanggan($order)) ?></strong></div><div><span>Kasir</span><strong><?= e($payment['kasir_nama'] ?? $user['name']) ?></strong></div></div><div class="receipt-items"><?php foreach ($order['items'] ?? [] as $item): ?><div class="receipt-line"><span><?= (int)$item['qty'] ?>× <?= e($item['nama']) ?><small><?= e(money($item['harga_satuan'])) ?> / item<?= ($item['catatan_item'] ?? '') ? ' · ' . e($item['catatan_item']) : '' ?></small></span><strong><?= e(money($item['subtotal'])) ?></strong></div><?php endforeach; ?></div><div class="receipt-total"><span>TOTAL</span><strong><?= e(money($order['total'] ?? 0)) ?></strong></div><div class="receipt-payment"><div><span>Metode</span><strong><?= e(strtoupper((string)($payment['metode'] ?? '-'))) ?></strong></div><div><span>Bayar</span><strong><?= e(money($payment['jumlah_bayar'] ?? 0)) ?></strong></div><div><span>Kembalian</span><strong><?= e(money($payment['kembalian'] ?? 0)) ?></strong></div></div><div class="receipt-foot">Terima kasih sudah singgah.<br>Setiap tegukan punya cerita.</div></div></div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
