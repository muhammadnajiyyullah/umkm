<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['admin']);
$user = current_user();
$pdo = db();
$logs = data_logs($pdo, 150);
$topbarStats = data_dashboard($pdo, 'admin', (int)$user['id']);
$activeMenu = 'logs';
$pageTitle = 'Log Aktivitas';
$pageSubtitle = 'Jejak audit perubahan status, pesanan, dan pembayaran oleh setiap pengguna.';
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">Audit trail</span><h1 class="page-title">Log Aktivitas</h1><p class="page-subtitle">Setiap tindakan penting dicatat agar operasional dapat ditelusuri kembali.</p></div><div class="page-actions"><button class="btn btn-ghost" type="button" data-print><?= icon('printer') ?> Cetak</button></div></div>
<section class="card"><div class="filter-bar"><div class="search-box"><?= icon('search') ?><input class="form-control" type="search" placeholder="Cari kode, nama, atau aksi" data-table-filter="logTable"></div><div class="filter-spacer"></div><span class="muted" style="font-size:10px"><?= count($logs) ?> aktivitas terbaru</span></div><div class="table-wrap"><table class="data-table" id="logTable"><thead><tr><th>Waktu</th><th>Pesanan</th><th>Pengguna</th><th>Aksi</th><th>Perubahan status</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><div class="primary-cell"><?= e(format_date_id($log['created_at'] ?? null)) ?></div><div class="secondary-cell"><?= e(relative_time($log['created_at'] ?? null)) ?></div></td><td class="order-code"><?= e($log['pesanan_kode'] ?? ($log['kode_pesanan'] ?? '-')) ?></td><td><div class="table-product"><span class="avatar avatar-<?= e(avatar_color((string)($log['user_nama'] ?? 'System'))) ?> avatar-sm"><?= e(initials((string)($log['user_nama'] ?? 'System'))) ?></span><div class="table-product-copy"><strong><?= e($log['user_nama'] ?? 'System') ?></strong><small><?= e($log['username'] ?? 'automated') ?></small></div></div></td><td><span class="role-pill <?= str_contains(strtolower((string)($log['aksi'] ?? '')), 'bayar') ? 'kasir' : (str_contains(strtolower((string)($log['aksi'] ?? '')), 'status') ? 'dapur' : 'admin') ?>"><?= e($log['aksi'] ?? '-') ?></span></td><td><div class="flex gap-6 items-center"><span class="muted"><?= e($log['status_lama'] ?? '-') ?></span><?= icon('arrow-right', 'icon') ?><span class="badge badge-<?= e(status_class((string)($log['status_baru'] ?? 'Baru'))) ?>"><?= e(status_label((string)($log['status_baru'] ?? '-'))) ?></span></div></td></tr><?php endforeach; ?><?php if (!$logs): ?><tr><td colspan="5"><div class="empty-state"><strong>Belum ada aktivitas</strong><p>Log akan terisi setelah ada transaksi.</p></div></td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../layout/footer.php'; ?>
