<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['pelayan', 'admin']);
$user = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $order = data_order($pdo, $orderId);
        if (!$order || ($user['role'] !== 'admin' && (int)($order['pelayan_id'] ?? 0) !== (int)$user['id'])) {
            set_flash('danger', 'Pesanan tidak ditemukan atau bukan milik Anda.');
        } elseif (!in_array($order['status'], ['Baru', 'Diterima'], true)) {
            set_flash('danger', 'Pesanan hanya dapat dibatalkan sebelum masuk proses dapur.');
        } elseif (database_ready($pdo)) {
            try {
                $pdo->beginTransaction();
                $items = $pdo->prepare('SELECT menu_id, qty FROM detail_pesanan WHERE pesanan_id=?');
                $items->execute([$orderId]);
                $restore = $pdo->prepare('UPDATE menu SET stok = stok + ? WHERE id = ?');
                foreach ($items->fetchAll() as $item) {
                    $restore->execute([(int)$item['qty'], (int)$item['menu_id']]);
                }
                $pdo->prepare('UPDATE pesanan SET status="Dibatalkan" WHERE id=? AND status IN ("Baru","Diterima")')->execute([$orderId]);
                $pdo->prepare('UPDATE detail_pesanan SET status_item="Dibatalkan" WHERE pesanan_id=?')->execute([$orderId]);
                if ($order['meja_id']) $pdo->prepare('UPDATE meja SET status="tersedia" WHERE id=?')->execute([(int)$order['meja_id']]);
                catat_aktivitas($pdo, $orderId, (int)$user['id'], 'Batalkan pesanan', $order['status'], 'Dibatalkan', 'Dibatalkan pelayan sebelum diproses dapur');
                $pdo->commit();
                set_flash('success', 'Pesanan dibatalkan dan stok dikembalikan.');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('danger', 'Pesanan gagal dibatalkan.');
            }
        } else {
            $deleted = demo_delete_order($orderId);
            set_flash($deleted ? 'success' : 'danger', $deleted ? 'Pesanan dibatalkan dan stok dikembalikan.' : 'Pesanan tidak dapat dibatalkan.');
        }
        redirect('pelayan/pesanan_aktif.php');
    }
}
$filters = ['exclude_paid' => true, 'limit' => 100];
if ($user['role'] === 'pelayan') $filters['user_id'] = (int)$user['id'];
$orders = data_orders($pdo, $filters);
$topbarStats = data_dashboard($pdo, 'pelayan', (int)$user['id']);
$activeMenu = 'orders';
$pageTitle = 'Pesanan Aktif';
$pageSubtitle = 'Pantau pesanan, antar ke meja saat siap saji, lalu tagih di kasir.';
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">Service flow · 02</span><h1 class="page-title">Pesanan Aktif</h1><p class="page-subtitle">Status bergerak otomatis setelah dapur dan kasir bekerja.</p></div><div class="page-actions"><a class="btn btn-primary" href="<?= e(url('pelayan/pesanan_baru.php')) ?>"><?= icon('plus') ?> Pesanan baru</a></div></div>
<div class="queue-toolbar"><div class="queue-live"><i></i><span><?= count($orders) ?> pesanan aktif · sinkronisasi otomatis</span></div><div class="flex gap-9"><a class="btn btn-ghost btn-sm" href="<?= e(url('pelayan/pesanan_aktif.php')) ?>"><?= icon('refresh') ?> Muat ulang</a></div></div>
<div class="order-list">
<?php foreach ($orders as $order): ?>
  <article class="order-row-card"><div class="table-number <?= !$order['meja'] ? 'takeaway' : '' ?>"><?= $order['meja'] ? e($order['meja']) : '↗' ?></div><div class="order-row-main"><div class="order-row-top"><strong><?= e($order['kode_pesanan'] ?? '-') ?></strong><span class="badge badge-<?= e(status_class((string)$order['status'])) ?>"><?= e(status_label((string)$order['status'])) ?></span><?php if (($order['tipe_pesanan'] ?? '') === 'takeaway'): ?><span class="badge badge-neutral">Takeaway</span><?php endif; ?></div><small><span class="customer-chip"><?= icon('user') ?><strong><?= e(order_pelanggan($order)) ?></strong></span></small><small><?= e(relative_time($order['created_at'] ?? null)) ?> · <?= count($order['items'] ?? []) ?> item · <?= e($order['catatan'] ?: 'Tanpa catatan umum') ?></small><div class="flex gap-6 mt-6 flex-wrap"><?php foreach (array_slice($order['items'] ?? [], 0, 3) as $item): ?><span class="badge badge-neutral"><?= (int)$item['qty'] ?>× <?= e($item['nama']) ?></span><?php endforeach; ?><?php if (count($order['items'] ?? []) > 3): ?><span class="muted" style="font-size:9px">+<?= count($order['items']) - 3 ?> item</span><?php endif; ?></div></div><div class="order-row-total"><strong><?= e(money($order['total'] ?? 0)) ?></strong><small><?= e($order['pelayan'] ?? 'Pelayan') ?></small></div><div class="order-row-actions"><?php if (($order['status'] ?? '') === 'Siap Saji'): ?><button class="btn btn-sage btn-sm" type="button" data-order-status="<?= (int)$order['id'] ?>" data-next="Menunggu Bayar"><?= icon('check') ?> Antar ke meja</button><?php endif; ?><?php if (in_array($order['status'] ?? '', ['Baru', 'Diterima'], true)): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('pelayan/pesanan_baru.php?id=' . (int)$order['id'])) ?>"><?= icon('edit') ?> Ubah</a><form method="post" data-confirm="Batalkan pesanan ini? Stok akan dikembalikan."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><button class="btn btn-danger btn-sm btn-icon" type="submit" title="Hapus"><?= icon('trash') ?></button></form><?php endif; ?></div></article>
<?php endforeach; ?>
<?php if (!$orders): ?><section class="card"><div class="empty-state"><div class="empty-state-icon"><?= icon('receipt') ?></div><strong>Tidak ada pesanan aktif</strong><p>Buat pesanan baru dan kirim ke dapur dari tombol di atas.</p><a class="btn btn-primary mt-14" href="<?= e(url('pelayan/pesanan_baru.php')) ?>"><?= icon('plus') ?> Buat pesanan</a></div></section><?php endif; ?>
</div>
<script>
document.querySelectorAll('[data-order-status]').forEach((button)=>button.addEventListener('click',async()=>{button.disabled=true;const params=new URLSearchParams();params.append('csrf_token',Cafe.csrf);params.append('order_id',button.dataset.orderStatus);params.append('status',button.dataset.next);try{const result=await Cafe.request(Cafe.api('api/ubah_status.php'),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()});Cafe.notify(result.message,'success');setTimeout(()=>location.reload(),500);}catch(error){Cafe.notify(error.message,'danger');button.disabled=false;}}));
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
