<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['admin']);
$user = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'create');
    $id = (int)($_POST['user_id'] ?? 0);
    if ($action === 'delete') {
        if ($id === (int)$user['id']) {
            set_flash('danger', 'Akun yang sedang digunakan tidak dapat dihapus.');
        } elseif (database_ready($pdo)) {
            $used = db_one('SELECT COUNT(*) AS total FROM pesanan WHERE pelayan_id=?', [$id]);
            if ((int)($used['total'] ?? 0) > 0 || (int)$id === 2) {
                set_flash('danger', 'Akun sudah memiliki transaksi atau tidak boleh dihapus.');
            } else {
                set_flash(db_exec('DELETE FROM users WHERE id=?', [$id]) ? 'success' : 'danger', 'Akun berhasil dihapus.');
            }
        } else {
            $deleted = demo_delete_user($id);
            set_flash($deleted ? 'success' : 'danger', $deleted ? 'Akun demo berhasil dihapus.' : 'Akun demo tidak dapat dihapus.');
        }
        redirect('admin/user.php');
    }
    $payload = [
        'nama' => trim((string)($_POST['nama'] ?? '')),
        'username' => trim((string)($_POST['username'] ?? '')),
        'role' => in_array($_POST['role'] ?? '', ['admin', 'kasir', 'pelayan', 'dapur', 'umkm'], true) ? (string)$_POST['role'] : 'pelayan',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    $password = trim((string)($_POST['password'] ?? ''));
    if ($payload['nama'] === '' || $payload['username'] === '' || ($id === 0 && strlen($password) < 5)) {
        set_flash('danger', 'Nama, username, dan password minimal 5 karakter wajib diisi.');
        redirect('admin/user.php');
    }
    if (database_ready($pdo)) {
        if ($id > 0) {
            $sql = 'UPDATE users SET nama=?, username=?, role=?, is_active=?';
            $params = [$payload['nama'], $payload['username'], $payload['role'], $payload['is_active']];
            if ($password !== '') { $sql .= ', password=?'; $params[] = password_hash($password, PASSWORD_DEFAULT); }
            $sql .= ' WHERE id=?'; $params[] = $id;
            $ok = db_exec($sql, $params);
            if ($ok) {
                // Nama akun mitra ikut diperbarui supaya halaman admin dan
                // dashboard mitra selalu menampilkan nama yang sama.
                db_exec('UPDATE mitra_umkm SET pemilik=? WHERE user_id=?', [$payload['nama'], $id]);
            }
        } else {
            $ok = db_exec('INSERT INTO users (nama, username, password, role, is_active) VALUES (?, ?, ?, ?, ?)', [$payload['nama'], $payload['username'], password_hash($password, PASSWORD_DEFAULT), $payload['role'], $payload['is_active']]);
        }
    } else {
        $ok = $id > 0 ? demo_update_user($id, $payload) : (bool)demo_add_user($payload);
    }
    set_flash($ok ? 'success' : 'danger', $ok ? 'Akun pengguna berhasil disimpan.' : 'Username mungkin sudah digunakan.');
    redirect('admin/user.php');
}

$users = data_users($pdo);
$topbarStats = data_dashboard($pdo, 'admin', (int)$user['id']);
$activeMenu = 'users';
$pageTitle = 'Kelola User';
$pageSubtitle = 'Atur siapa saja yang dapat mengakses setiap bagian operasional cafe.';
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">People &amp; access</span><h1 class="page-title">Kelola User</h1><p class="page-subtitle">Role menjaga setiap tindakan tetap sesuai tanggung jawabnya.</p></div><div class="page-actions"><button class="btn btn-primary" type="button" data-modal-target="userModal"><?= icon('plus') ?> Tambah user</button></div></div>
<section class="card"><div class="filter-bar"><div class="search-box"><?= icon('search') ?><input class="form-control" type="search" placeholder="Cari nama atau username" data-table-filter="userTable"></div><div class="filter-spacer"></div><span class="muted" style="font-size:10px"><?= count($users) ?> akun terdaftar</span></div><div class="table-wrap"><table class="data-table" id="userTable"><thead><tr><th>Pengguna</th><th>Username</th><th>Role</th><th>Status</th><th>Bergabung</th><th class="text-right">Aksi</th></tr></thead><tbody>
<?php foreach ($users as $row): ?><tr><td><div class="table-product"><span class="avatar avatar-<?= e(avatar_color((string)$row['nama'])) ?> avatar-sm"><?= e(initials((string)$row['nama'])) ?></span><div class="table-product-copy"><strong><?= e($row['nama']) ?></strong><small><?= e(relative_time($row['last_login'] ?? $row['created_at'] ?? null)) ?></small></div></div></td><td class="primary-cell">@<?= e($row['username']) ?></td><td><span class="role-pill <?= e($row['role']) ?>"><?= e(role_label((string)$row['role'])) ?></span></td><td><span class="badge badge-<?= (int)($row['is_active'] ?? 0) ? 'ready' : 'cancelled' ?>"><?= (int)($row['is_active'] ?? 0) ? 'Aktif' : 'Nonaktif' ?></span></td><td class="muted"><?= e(format_date_id($row['created_at'] ?? null, false)) ?></td><td><div class="flex gap-6 justify-between"><button class="btn btn-ghost btn-sm" type="button" data-modal-target="userModal" data-edit-user="<?= json_attr($row) ?>"><?= icon('edit') ?> Ubah</button><?php if ((int)$row['id'] !== (int)$user['id']): ?><form method="post" data-confirm="Hapus akun pengguna ini?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-danger btn-sm btn-icon" type="submit" title="Hapus"><?= icon('trash') ?></button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="6"><div class="empty-state"><strong>Belum ada pengguna</strong></div></td></tr><?php endif; ?>
</tbody></table></div></section>

<div class="modal-backdrop-custom" id="userModal"><div class="modal-card"><div class="modal-head"><div><h3 id="userModalTitle">Tambah pengguna</h3><p>Password disimpan dengan hash yang aman.</p></div><button class="modal-close" type="button" data-modal-close="userModal"><?= icon('x') ?></button></div><form method="post" id="userForm"><?= csrf_input() ?><input type="hidden" name="action" value="create"><input type="hidden" name="user_id" id="userId"><div class="modal-body"><div class="form-grid"><div class="form-group full"><label class="form-label" for="userName">Nama lengkap</label><input class="form-control" id="userName" name="nama" required placeholder="Nama pengguna"></div><div class="form-group"><label class="form-label" for="userUsername">Username</label><input class="form-control" id="userUsername" name="username" required placeholder="username"></div><div class="form-group"><label class="form-label" for="userRole">Role</label><select class="form-select" id="userRole" name="role"><option value="admin">Administrator</option><option value="kasir">Kasir</option><option value="pelayan">Pelayan</option><option value="dapur">Dapur</option><option value="umkm">Mitra UMKM</option></select></div><div class="form-group full"><label class="form-label" for="userPassword">Password <span class="form-hint" id="passwordHint">wajib diisi</span></label><input class="form-control" id="userPassword" name="password" type="password" minlength="5" placeholder="Minimal 5 karakter"></div><div class="form-group full"><label class="checkbox-line"><input type="checkbox" name="is_active" checked> Akun aktif dan dapat login</label></div></div></div><div class="modal-foot"><button class="btn btn-ghost" type="button" data-modal-close="userModal">Batal</button><button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan user</button></div></form></div></div>
<script>
(function(){
 const form=document.getElementById('userForm'), title=document.getElementById('userModalTitle');
 document.querySelectorAll('[data-edit-user]').forEach((b)=>b.addEventListener('click',()=>{const d=JSON.parse(b.dataset.editUser||'{}');document.getElementById('userId').value=d.id||'';document.getElementById('userName').value=d.nama||'';document.getElementById('userUsername').value=d.username||'';document.getElementById('userRole').value=d.role||'pelayan';document.getElementById('userPassword').value='';document.getElementById('passwordHint').textContent='kosongkan jika tidak diubah';form.querySelector('[name="action"]').value='update';title.textContent='Ubah pengguna';}));
 const add=document.querySelector('[data-modal-target="userModal"]'); if(add) add.addEventListener('click',()=>{form.reset();document.getElementById('userId').value='';form.querySelector('[name="action"]').value='create';title.textContent='Tambah pengguna';document.getElementById('passwordHint').textContent='wajib diisi';});
}());
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
