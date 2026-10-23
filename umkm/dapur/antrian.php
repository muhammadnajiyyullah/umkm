<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['dapur']);
$user = current_user();
$pdo = db();
$orders = data_orders($pdo, ['statuses' => ['Baru', 'Diterima', 'Diproses'], 'limit' => 100]);
$orders = array_values(array_filter($orders, static function (array $order): bool {
    foreach ($order['items'] ?? [] as $item) if (($item['tipe'] ?? 'cafe') === 'cafe') return true;
    return false;
}));
// Pesanan terlama lebih dulu.
usort($orders, static fn(array $a, array $b): int => strcmp((string)$a['created_at'], (string)$b['created_at']));
$topbarStats = data_dashboard($pdo, 'dapur', (int)$user['id']);
$activeMenu = 'queue';
$pageTitle = 'Antrian Dapur';
$pageSubtitle = 'Terima, proses, dan tandai pesanan siap saji dengan satu klik.';
$pageScript = <<<'JS'
<script>
(function(){
 let lastSignature='';
 const signature=(orders)=>orders.map((o)=>o.id+':'+o.status).join('|');
 async function refresh(){
  try{const result=await Cafe.request(Cafe.api('api/get_antrian.php'));const next=signature(result.orders||[]);const stamp=document.getElementById('lastSync');if(stamp)stamp.textContent='Sinkron '+new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});if(lastSignature&&next!==lastSignature)location.reload();lastSignature=next;}catch(error){const stamp=document.getElementById('lastSync');if(stamp)stamp.textContent='Sinkron tertunda';}
 }
 document.querySelectorAll('[data-queue-action]').forEach((button)=>button.addEventListener('click',async()=>{button.disabled=true;const params=new URLSearchParams();params.append('csrf_token',Cafe.csrf);params.append('order_id',button.dataset.queueAction);params.append('status',button.dataset.queueStatus);try{const result=await Cafe.request(Cafe.api('api/ubah_status.php'),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()});Cafe.notify(result.message,'success');setTimeout(()=>location.reload(),350);}catch(error){Cafe.notify(error.message,'danger');button.disabled=false;}}));
 refresh();setInterval(refresh,10000);
}());
</script>
JS;
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">Kitchen command center</span><h1 class="page-title">Antrian Dapur</h1><p class="page-subtitle">Layar besar, status besar, keputusan yang lebih cepat.</p></div><div class="page-actions"><span class="demo-tag"><?= icon('refresh') ?> Auto refresh 10 detik</span></div></div>
<div class="queue-toolbar"><div class="queue-live"><i></i><strong><?= count($orders) ?> pesanan perlu diproses</strong><span class="muted">·</span><span id="lastSync">Sinkron baru saja</span></div><button class="btn btn-ghost btn-sm" type="button" onclick="location.reload()"><?= icon('refresh') ?> Muat ulang</button></div>
<div class="queue-grid">
<?php foreach ($orders as $order): $cafeItems=array_values(array_filter($order['items']??[],static fn(array $item):bool=>($item['tipe']??'cafe')==='cafe')); $age=max(0,time()-(int)strtotime((string)($order['created_at']??'now'))); $ageMinutes=(int)floor($age/60); $next=['Baru'=>'Diterima','Diterima'=>'Diproses','Diproses'=>'Siap Saji'][$order['status']]??null; $label=['Baru'=>'Terima pesanan','Diterima'=>'Mulai proses','Diproses'=>'Tandai siap saji'][$order['status']]??''; ?>
  <article class="queue-card <?= e(status_class((string)$order['status'])) ?>" data-queue-card="<?= (int)$order['id'] ?>"><div class="queue-card-head"><div class="queue-order-meta"><strong><?= $order['meja'] ? 'Meja ' . e($order['meja']) : 'Takeaway' ?></strong><span class="queue-customer"><?= icon('user') ?><?= e(order_pelanggan($order, 'Pelanggan')) ?></span><span><?= e($order['kode_pesanan'] ?? '-') ?> · <?= count($cafeItems) ?> item dapur</span></div><div class="queue-age"><?= $ageMinutes > 0 ? $ageMinutes . ' menit' : 'Baru' ?><small><?= e(relative_time($order['created_at']??null)) ?></small></div></div><div class="queue-items"><?php foreach ($cafeItems as $item): ?><div class="queue-item"><div class="queue-item-name"><span class="queue-item-qty"><?= (int)$item['qty'] ?>×</span><span><strong><?= e($item['nama']) ?></strong><?= ($item['catatan_item']??'')?'<small>Catatan: '.e($item['catatan_item']).'</small>':'' ?></span></div><span class="queue-item-price"><?= e(status_label((string)($item['status_item']??'Baru'))) ?></span></div><?php endforeach; ?></div><div class="queue-card-foot"><div class="queue-card-total">Total tagihan<strong><?= e(money($order['total']??0)) ?></strong></div><div class="queue-actions"><?php if ($next): ?><button class="btn btn-primary btn-sm" type="button" data-queue-action="<?= (int)$order['id'] ?>" data-queue-status="<?= e($next) ?>"><?= icon($order['status']==='Diproses'?'check':'arrow-right') ?> <?= e($label) ?></button><?php else: ?><span class="badge badge-ready">Siap saji</span><?php endif; ?></div></div></article>
<?php endforeach; ?>
</div>
<?php if (!$orders): ?><section class="card mt-18"><div class="empty-state"><div class="empty-state-icon"><?= icon('check') ?></div><strong>Antrian bersih</strong><p>Tidak ada pesanan baru yang perlu dimasak saat ini.</p><span class="demo-tag mt-14"><?= icon('spark') ?> Bagus, dapur tetap tenang</span></div></section><?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
