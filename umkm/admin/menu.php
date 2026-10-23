<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['admin']);
$user = current_user();
$pdo = db();

function menu_upload_path(?string $fallback = null): ?string
{
    if (empty($_FILES['foto_upload']['tmp_name']) || !is_uploaded_file($_FILES['foto_upload']['tmp_name'])) {
        $value = trim((string)($_POST['foto'] ?? ''));
        return $value !== '' ? $value : $fallback;
    }
    $file = $_FILES['foto_upload'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
        return $fallback;
    }
    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    if (!in_array($extension, $allowed, true)) {
        return $fallback;
    }
    $folder = __DIR__ . '/../assets/img/menu';
    if (!is_dir($folder)) mkdir($folder, 0775, true);
    $filename = 'menu-' . bin2hex(random_bytes(5)) . '.' . $extension;
    if (move_uploaded_file((string)$file['tmp_name'], $folder . '/' . $filename)) {
        return 'assets/img/menu/' . $filename;
    }
    return $fallback;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'create');
    $id = (int)($_POST['menu_id'] ?? 0);
    if ($action === 'delete') {
        if (database_ready($pdo)) {
            $used = db_one('SELECT COUNT(*) AS total FROM detail_pesanan WHERE menu_id=?', [$id]);
            if ((int)($used['total'] ?? 0) > 0) {
                set_flash('danger', 'Menu sudah pernah masuk transaksi dan tidak dapat dihapus. Nonaktifkan sebagai gantinya.');
            } elseif (db_exec('DELETE FROM menu WHERE id=?', [$id])) {
                set_flash('success', 'Menu berhasil dihapus dari katalog.');
            } else {
                set_flash('danger', 'Menu gagal dihapus.');
            }
        } else {
            $deleted = demo_delete_menu($id);
            set_flash($deleted ? 'success' : 'danger', $deleted ? 'Menu demo berhasil dihapus.' : 'Menu tidak dapat dihapus karena sudah ada transaksi.');
        }
        redirect('admin/menu.php');
    }
    $payload = [
        'kode' => trim((string)($_POST['kode'] ?? '')),
        'nama' => trim((string)($_POST['nama'] ?? '')),
        'kategori_id' => (int)($_POST['kategori_id'] ?? 1),
        'harga' => (int)($_POST['harga'] ?? 0),
        'stok' => (int)($_POST['stok'] ?? 0),
        'tipe' => ($_POST['tipe'] ?? 'cafe') === 'umkm' ? 'umkm' : 'cafe',
        'mitra_id' => (int)($_POST['mitra_id'] ?? 0) ?: null,
        'deskripsi' => trim((string)($_POST['deskripsi'] ?? '')),
        'foto' => menu_upload_path(),
        'is_tersedia' => isset($_POST['is_tersedia']) ? 1 : 0,
    ];
    if ($payload['nama'] === '' || $payload['harga'] < 0) {
        set_flash('danger', 'Nama menu dan harga wajib diisi dengan benar.');
        redirect('admin/menu.php');
    }
    if ($payload['tipe'] === 'umkm' && !$payload['mitra_id']) {
        set_flash('danger', 'Pilih mitra UMKM untuk produk titipan.');
        redirect('admin/menu.php');
    }
    if (database_ready($pdo)) {
        if ($id > 0) {
            $sql = 'UPDATE menu SET kode=?, nama=?, kategori_id=?, harga=?, stok=?, foto=?, tipe=?, mitra_id=?, deskripsi=?, is_tersedia=? WHERE id=?';
            $ok = db_exec($sql, [$payload['kode'] ?: null, $payload['nama'], $payload['kategori_id'], $payload['harga'], $payload['stok'], $payload['foto'], $payload['tipe'], $payload['mitra_id'], $payload['deskripsi'] ?: null, $payload['is_tersedia'], $id]);
        } else {
            $sql = 'INSERT INTO menu (kode, nama, kategori_id, harga, stok, foto, tipe, mitra_id, deskripsi, is_tersedia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $ok = db_exec($sql, [$payload['kode'] ?: null, $payload['nama'], $payload['kategori_id'], $payload['harga'], $payload['stok'], $payload['foto'], $payload['tipe'], $payload['mitra_id'], $payload['deskripsi'] ?: null, $payload['is_tersedia']]);
        }
    } else {
        $ok = $id > 0 ? demo_update_menu($id, $payload) : (bool)demo_add_menu($payload);
    }
    set_flash($ok ? 'success' : 'danger', $ok ? ($id > 0 ? 'Perubahan menu berhasil disimpan.' : 'Menu baru berhasil ditambahkan.') : 'Perubahan menu gagal disimpan.');
    redirect('admin/menu.php');
}

$menus = database_ready($pdo) ? db_all('SELECT m.*, k.nama AS kategori, p.nama_usaha AS mitra FROM menu m LEFT JOIN kategori k ON k.id=m.kategori_id LEFT JOIN mitra_umkm p ON p.id=m.mitra_id ORDER BY m.tipe, m.nama') : demo_menus();
$categories = data_categories($pdo);
$partners = data_partners($pdo);
$topbarStats = data_dashboard($pdo, 'admin', (int)$user['id']);
$activeMenu = 'menu';
$pageTitle = 'Kelola Menu';
$pageSubtitle = 'Atur katalog cafe dan produk titipan UMKM dari satu tempat.';
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">Catalog control</span><h1 class="page-title">Kelola Menu</h1><p class="page-subtitle">Harga, stok, foto, dan asal produk selalu mengikuti katalog yang dipakai pelayan.</p></div><div class="page-actions"><button class="btn btn-ghost" type="button" data-print><?= icon('printer') ?> Cetak</button><button class="btn btn-primary" type="button" data-modal-target="menuModal"><?= icon('plus') ?> Tambah menu</button></div></div>
<section class="card">
  <div class="filter-bar"><div class="search-box"><?= icon('search') ?><input class="form-control" type="search" placeholder="Cari nama atau kode menu" data-table-filter="menuTable"></div><div class="filter-spacer"></div><span class="muted" style="font-size:10px"><?= count($menus) ?> item · <span class="badge badge-umkm"><?= count(array_filter($menus, static fn(array $m): bool => $m['tipe'] === 'umkm')) ?> UMKM</span></span></div>
  <div class="table-wrap"><table class="data-table" id="menuTable"><thead><tr><th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th><th class="text-right">Aksi</th></tr></thead><tbody>
    <?php foreach ($menus as $menu): ?><tr><td><div class="table-product"><img class="table-product-thumb" src="<?= e(image_url($menu['foto'] ?? null)) ?>" alt=""><div class="table-product-copy"><strong><?= e($menu['nama']) ?></strong><small><?= e($menu['kode'] ?? 'Tanpa kode') ?><?= ($menu['mitra'] ?? null) ? ' · ' . e($menu['mitra']) : '' ?></small></div></div></td><td><?= e($menu['kategori'] ?? '-') ?><?= ($menu['tipe'] ?? 'cafe') === 'umkm' ? '<div class="secondary-cell">Produk titipan</div>' : '' ?></td><td class="primary-cell"><?= e(money($menu['harga'])) ?></td><td><?= (int)($menu['stok'] ?? 0) ?> pcs<div class="progress-track"><i style="width:<?= min(100, (int)($menu['stok'] ?? 0) * 3) ?>%"></i></div></td><td><span class="badge badge-<?= (int)($menu['is_tersedia'] ?? 0) ? 'ready' : 'cancelled' ?>"><?= (int)($menu['is_tersedia'] ?? 0) ? 'Tersedia' : 'Nonaktif' ?></span></td><td><div class="flex gap-6 justify-between"><button class="btn btn-ghost btn-sm" type="button" data-modal-target="menuModal" data-edit-menu="<?= json_attr($menu) ?>"><?= icon('edit') ?> Ubah</button><form method="post" data-confirm="Hapus menu ini dari katalog? Riwayat transaksi lama tetap aman."><?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="menu_id" value="<?= (int)$menu['id'] ?>"><button class="btn btn-danger btn-sm btn-icon" type="submit" title="Hapus"><?= icon('trash') ?></button></form></div></td></tr><?php endforeach; ?>
    <?php if (!$menus): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon"><?= icon('coffee') ?></div><strong>Belum ada menu</strong><p>Tambahkan produk pertama untuk memulai katalog.</p></div></td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<div class="modal-backdrop-custom" id="menuModal"><div class="modal-card wide"><div class="modal-head"><div><h3 id="menuModalTitle">Tambah menu baru</h3><p>Data akan langsung terlihat oleh pelayan.</p></div><button class="modal-close" type="button" data-modal-close="menuModal"><?= icon('x') ?></button></div><form method="post" enctype="multipart/form-data" id="menuForm"><?= csrf_input() ?><input type="hidden" name="action" value="create"><input type="hidden" name="menu_id" id="menuId"><div class="modal-body"><div class="form-grid"><div class="form-group"><label class="form-label" for="menuName">Nama menu</label><input class="form-control" id="menuName" name="nama" required placeholder="Contoh: Cappuccino"></div><div class="form-group"><label class="form-label" for="menuCode">Kode menu</label><input class="form-control" id="menuCode" name="kode" placeholder="MKN-009"></div><div class="form-group"><label class="form-label" for="menuCategory">Kategori</label><select class="form-select" id="menuCategory" name="kategori_id"><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= e($category['nama']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label" for="menuPrice">Harga jual (Rp)</label><input class="form-control" id="menuPrice" name="harga" type="number" min="0" required placeholder="28000"></div><div class="form-group"><label class="form-label" for="menuStock">Stok tersedia</label><input class="form-control" id="menuStock" name="stok" type="number" min="0" required value="0"></div><div class="form-group"><label class="form-label" for="menuType">Asal produk</label><select class="form-select" id="menuType" name="tipe"><option value="cafe">Menu cafe</option><option value="umkm">Produk UMKM</option></select></div><div class="form-group full"><label class="form-label" for="menuPartner">Mitra UMKM <span class="form-hint">(wajib untuk produk titipan)</span></label><select class="form-select" id="menuPartner" name="mitra_id"><option value="">— Tanpa mitra —</option><?php foreach ($partners as $partner): ?><option value="<?= (int)$partner['id'] ?>"><?= e($partner['nama_usaha']) ?></option><?php endforeach; ?></select></div><div class="form-group full"><label class="form-label" for="menuDescription">Deskripsi singkat</label><textarea class="form-control" id="menuDescription" name="deskripsi" placeholder="Ceritakan-keunikan produk..."></textarea></div><div class="form-group"><label class="form-label" for="menuPhoto">Foto produk</label><input class="form-control" id="menuPhoto" name="foto" placeholder="assets/img/menu/default.svg"></div><div class="form-group"><label class="form-label" for="menuPhotoUpload">Upload foto <span class="form-hint">(maks. 2 MB)</span></label><input class="form-control" id="menuPhotoUpload" type="file" name="foto_upload" accept="image/*"></div><div class="form-group full"><label class="checkbox-line"><input type="checkbox" name="is_tersedia" checked> Tampilkan dan izinkan dipesan</label></div></div></div><div class="modal-foot"><button class="btn btn-ghost" type="button" data-modal-close="menuModal">Batal</button><button class="btn btn-primary" type="submit" id="menuSubmit"><?= icon('check') ?> Simpan menu</button></div></form></div></div>
<script>
(function(){
  const form=document.getElementById('menuForm');
  const title=document.getElementById('menuModalTitle');
  document.querySelectorAll('[data-edit-menu]').forEach((button)=>button.addEventListener('click',()=>{
    const data=JSON.parse(button.dataset.editMenu||'{}');
    document.getElementById('menuId').value=data.id||'';
    document.getElementById('menuName').value=data.nama||'';
    document.getElementById('menuCode').value=data.kode||'';
    document.getElementById('menuCategory').value=data.kategori_id||1;
    document.getElementById('menuPrice').value=data.harga||0;
    document.getElementById('menuStock').value=data.stok||0;
    document.getElementById('menuType').value=data.tipe||'cafe';
    document.getElementById('menuPartner').value=data.mitra_id||'';
    document.getElementById('menuDescription').value=data.deskripsi||'';
    document.getElementById('menuPhoto').value=data.foto||'';
    const check=form.querySelector('[name="is_tersedia"]'); if(check) check.checked=Number(data.is_tersedia||0)===1;
    form.querySelector('[name="action"]').value='update';
    title.textContent='Ubah menu';
    document.getElementById('menuSubmit').innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m5 12 4 4L19 6"/></svg> Simpan perubahan';
  }));
  document.querySelector('[data-modal-target="menuModal"]').addEventListener('click',()=>{
    if(document.querySelector('[data-edit-menu]:focus')) return;
    form.reset(); document.getElementById('menuId').value=''; form.querySelector('[name="action"]').value='create'; title.textContent='Tambah menu baru'; document.getElementById('menuSubmit').innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m12 5v14M5 12h14"/></svg> Simpan menu';
  });
}());
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
