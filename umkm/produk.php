<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['umkm']);
$user = current_user();
$pdo = db();
$partners = data_partners($pdo);
$partner = null;
foreach ($partners as $row) {
    // Pencocokan memakai user_id (foreign key) supaya tetap benar setelah
    // admin mengganti nama akun mitra.
    if ((int)($row['user_id'] ?? 0) === (int)$user['id']) {
        $partner = $row;
        break;
    }
}
if (!$partner) {
    foreach ($partners as $row) {
        if (($row['pemilik'] ?? '') === $user['name'] || ($row['nama_akun'] ?? '') === $user['name']) {
            $partner = $row;
            break;
        }
    }
}
$partnerId = (int)($partner['id'] ?? 0);
$products = $partnerId > 0 ? data_menus($pdo, ['tipe' => 'umkm']) : [];
$products = array_values(array_filter($products, static fn(array $menu): bool => (int)($menu['mitra_id'] ?? 0) === $partnerId));
$topbarStats = data_dashboard($pdo, 'umkm', (int)$user['id']);
$activeMenu = 'products';
$pageTitle = 'Produk Saya';
$pageSubtitle = 'Pantau stok, penjualan, dan komisi titipan produk UMKM.';
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">Partner workspace</span><h1 class="page-title">Produk Saya</h1><p class="page-subtitle"><?= e($partner['nama_usaha'] ?? 'Mitra UMKM') ?> · data penjualan diperbarui setiap transaksi lunas.</p></div><div class="page-actions"><span class="demo-tag"><?= icon('package') ?> <?= e($partner['status'] ?? 'aktif') ?></span></div></div>
<div class="stats-grid"><article class="card stat-card"><div class="stat-top"><span class="stat-label">Penjualan</span><span class="stat-icon sage"><?= icon('wallet') ?></span></div><div class="stat-value"><?= e(money($partner['penjualan'] ?? 0)) ?></div><div class="stat-foot"><span class="stat-trend"><?= icon('trend-up') ?> periode berjalan</span></div></article><article class="card stat-card"><div class="stat-top"><span class="stat-label">Item terjual</span><span class="stat-icon clay"><?= icon('package') ?></span></div><div class="stat-value"><?= (int)($partner['terjual'] ?? 0) ?></div><div class="stat-foot"><span class="stat-trend"><?= icon('spark') ?> semua produk</span></div></article><article class="card stat-card"><div class="stat-top"><span class="stat-label">Komisi cafe</span><span class="stat-icon gold"><?= icon('trend') ?></span></div><div class="stat-value"><?= e(money($partner['komisi'] ?? 0)) ?></div><div class="stat-foot"><span class="stat-trend"><?= e($partner['komisi_persen'] ?? 10) ?>% · transparan</span></div></article><article class="card stat-card"><div class="stat-top"><span class="stat-label">Produk aktif</span><span class="stat-icon blue"><?= icon('store') ?></span></div><div class="stat-value"><?= count($products) ?></div><div class="stat-foot"><span class="stat-trend"><?= icon('check') ?> di katalog</span></div></article></div>
<div class="content-grid two-thirds"><section class="card"><div class="card-header"><div><h3>Produk titipan</h3><p>Barang jadi langsung siap saji setelah dibayar</p></div><span class="badge badge-umkm">UMKM</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Produk</th><th>Harga</th><th>Stok</th><th>Terjual</th><th>Status</th></tr></thead><tbody><?php foreach ($products as $product): ?><tr><td><div class="table-product"><img class="table-product-thumb" src="<?= e(image_url($product['foto'])) ?>" alt=""><div class="table-product-copy"><strong><?= e($product['nama']) ?></strong><small><?= e($product['kode'] ?? '') ?> · <?= e($product['deskripsi'] ?? '') ?></small></div></div></td><td class="primary-cell"><?= e(money($product['harga'])) ?></td><td><?= (int)$product['stok'] ?> pcs</td><td><?= (int)($product['terlaris'] ?? $product['terjual'] ?? 0) ?> item</td><td><span class="badge badge-<?= (int)$product['is_tersedia'] ? 'ready' : 'cancelled' ?>"><?= (int)$product['is_tersedia'] ? 'Tersedia' : 'Nonaktif' ?></span></td></tr><?php endforeach; ?><?php if (!$products): ?><tr><td colspan="5"><div class="empty-state"><strong>Belum ada produk</strong><p>Hubungi admin untuk menambahkan produk titipan.</p></div></td></tr><?php endif; ?></tbody></table></div></section><section class="card"><div class="card-header"><div><h3>Cara bagi hasil</h3><p>Perhitungan transparan</p></div><?= icon('spark') ?></div><div class="card-body"><div class="activity-list"><div class="activity-item"><span class="activity-icon sage"><?= icon('receipt') ?></span><div class="activity-copy"><strong>Harga jual pelanggan</strong><span>Nilai yang dibayar pelanggan</span></div><strong style="font-size:12px"><?= e(money($partner['penjualan'] ?? 0)) ?></strong></div><div class="activity-item"><span class="activity-icon clay"><?= icon('trend') ?></span><div class="activity-copy"><strong>Komisi cafe</strong><span><?= e($partner['komisi_persen'] ?? 10) ?>% dari penjualan</span></div><strong style="font-size:12px;color:var(--clay-deep)">− <?= e(money($partner['komisi'] ?? 0)) ?></strong></div><div class="activity-item"><span class="activity-icon gold"><?= icon('wallet') ?></span><div class="activity-copy"><strong>Estimasi hak mitra</strong><span>Penjualan − komisi cafe</span></div><strong style="font-size:12px;color:var(--sage-deep)"><?= e(money(max(0, (float)($partner['penjualan'] ?? 0) - (float)($partner['komisi'] ?? 0)))) ?></strong></div></div></div></section></div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
