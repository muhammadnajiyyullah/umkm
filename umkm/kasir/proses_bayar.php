<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['kasir']);
$user = current_user();
$pdo = db();
$orderId = (int)($_GET['id'] ?? $_POST['order_id'] ?? 0);
$order = $orderId > 0 ? data_order($pdo, $orderId) : null;
if (!$order) {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    redirect('kasir/daftar_tagihan.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    // Pembayaran hanya setelah pelayan mengantar pesanan ke meja.
    if (($order['status'] ?? '') !== 'Menunggu Bayar') {
        set_flash('danger', ($order['status'] ?? '') === 'Lunas'
            ? 'Pesanan sudah dibayar.'
            : 'Pesanan belum diantar ke meja, tidak bisa ditagih.');
        redirect('kasir/daftar_tagihan.php');
    }
    $method = in_array($_POST['metode'] ?? '', ['tunai', 'qris', 'transfer'], true) ? (string)$_POST['metode'] : 'tunai';
    $paid = (float)($_POST['jumlah_bayar'] ?? 0);
    if ($paid < (float)$order['total']) {
        set_flash('danger', 'Jumlah diterima tidak boleh kurang dari total tagihan.');
        redirect('kasir/proses_bayar.php?id=' . $orderId);
    }
    if (database_ready($pdo)) {
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('SELECT * FROM pesanan WHERE id=? FOR UPDATE');
            $statement->execute([$orderId]);
            $locked = $statement->fetch();
            if (!$locked) throw new RuntimeException('Pesanan tidak ditemukan.');
            if ($locked['status'] === 'Lunas') throw new RuntimeException('Pesanan sudah dibayar.');
            if ($locked['status'] === 'Dibatalkan') throw new RuntimeException('Pesanan sudah dibatalkan.');
            // Pembayaran hanya setelah pesanan diantar ke meja oleh pelayan.
            if ($locked['status'] !== 'Menunggu Bayar') throw new RuntimeException('Pesanan belum diantar ke meja. Tunggu pelayan mengantar pesanan.');
            $struk = next_nomor_struk($pdo);
            $payment = $pdo->prepare('INSERT INTO pembayaran (pesanan_id, kasir_id, nomor_struk, metode, jumlah_bayar, kembalian) VALUES (?, ?, ?, ?, ?, ?)');
            $payment->execute([$orderId, (int)$user['id'], $struk, $method, $paid, max(0, $paid - (float)$locked['total'])]);
            $pdo->prepare('UPDATE pesanan SET status="Lunas" WHERE id=?')->execute([$orderId]);
            $pdo->prepare('UPDATE detail_pesanan SET status_item="Siap Saji" WHERE pesanan_id=?')->execute([$orderId]);
            // Pelanggan sudah bayar: meja siap dipakai pelanggan berikutnya.
            if ($locked['meja_id']) $pdo->prepare('UPDATE meja SET status="tersedia" WHERE id=?')->execute([(int)$locked['meja_id']]);
            catat_aktivitas($pdo, $orderId, (int)$user['id'], 'Bayar pesanan', $locked['status'], 'Lunas', $method);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_flash('danger', pesan_exception($exception, 'Pembayaran gagal diproses.'));
            redirect('kasir/proses_bayar.php?id=' . $orderId);
        }
    } else {
        $paidOrder = demo_process_payment($orderId, $method, $paid, (string)$user['name']);
        if (!$paidOrder) {
            set_flash('danger', 'Pesanan sudah dibayar atau tidak ditemukan.');
            redirect('kasir/proses_bayar.php?id=' . $orderId);
        }
    }
    set_flash('success', 'Pembayaran berhasil dikonfirmasi. Struk siap dicetak.');
    redirect('kasir/struk.php?id=' . $orderId);
}

$topbarStats = data_dashboard($pdo, 'kasir', (int)$user['id']);
$activeMenu = 'bills';
$pageTitle = 'Proses Bayar';
$pageSubtitle = 'Periksa rincian, konfirmasi metode, lalu terbitkan struk untuk pelanggan.';
$isPaid = ($order['status'] ?? '') === 'Lunas';
$paymentMethod = (string)($_POST['metode'] ?? 'tunai');
$paidAmount = (float)($_POST['jumlah_bayar'] ?? 0);
$pageScript = <<<'JS'
<script>
(function(){
 const total=__TOTAL__, input=document.getElementById('paidAmount'), change=document.getElementById('changeAmount'), warning=document.getElementById('changeWarning');
 function update(){const value=Number(input?.value||0);if(change)change.textContent=Cafe.money(Math.max(0,value-total));if(warning)warning.textContent=value>0&&value<total?'Uang diterima masih kurang dari total.':'';}
 input?.addEventListener('input',update); document.querySelectorAll('input[name="metode"]').forEach((radio)=>radio.addEventListener('change',()=>{if(input&&!input.value)input.value=total;update();}));
 document.querySelectorAll('[data-quick-add]').forEach((btn)=>btn.addEventListener('click',()=>{if(input){input.value=Number(input.value||0)+Number(btn.dataset.quickAdd);update();}}));
 document.querySelectorAll('[data-quick-set]').forEach((btn)=>btn.addEventListener('click',()=>{if(input){input.value=btn.dataset.quickSet;update();}}));
 document.querySelectorAll('[data-quick-clear]').forEach((btn)=>btn.addEventListener('click',()=>{if(input){input.value='';update();input.focus();}})); update();
}());
</script>
JS;
$pageScript = str_replace('__TOTAL__', (string)(float)($order['total'] ?? 0), $pageScript);
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">Service flow · 03</span><h1 class="page-title">Proses Bayar</h1><p class="page-subtitle">Pesanan <span class="order-code"><?= e($order['kode_pesanan'] ?? '-') ?></span> · <?= $order['meja'] ? 'Meja ' . e($order['meja']) : 'Takeaway' ?> · Pelanggan <strong><?= e(order_pelanggan($order)) ?></strong></p></div><div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('kasir/daftar_tagihan.php')) ?>"><?= icon('arrow-right') ?> Kembali ke tagihan</a></div></div>
<?php if ($isPaid): ?><section class="card"><div class="empty-state"><div class="empty-state-icon"><?= icon('check') ?></div><strong>Pesanan sudah lunas</strong><p>Pembayaran <?= e($order['payment']['nomor_struk'] ?? 'sudah tercatat') ?> telah dikonfirmasi.</p><a class="btn btn-primary mt-14" href="<?= e(url('kasir/struk.php?id=' . $orderId)) ?>"><?= icon('receipt') ?> Buka struk</a></div></section>
<?php else: ?><div class="payment-layout"><section class="card bill-card"><div class="bill-head"><div><span class="eyebrow" style="color:var(--gold)">Payment request</span><h2>Rincian tagihan</h2><p><span class="customer-chip"><?= icon('user') ?><strong><?= e(order_pelanggan($order)) ?></strong></span></p><p><?= e(relative_time($order['created_at'] ?? null)) ?> · <?= e($order['pelayan'] ?? 'Pelayan') ?></p></div><div class="bill-total-badge"><small>Total</small><strong><?= e(money($order['total'])) ?></strong></div></div><div class="bill-items"><?php foreach ($order['items'] ?? [] as $item): ?><div class="bill-item"><span class="bill-item-qty"><?= (int)$item['qty'] ?>×</span><div class="bill-item-copy"><strong><?= e($item['nama']) ?></strong><small><?= e(money($item['harga_satuan'])) ?> / item<?= ($item['catatan_item'] ?? '') ? ' · ' . e($item['catatan_item']) : '' ?></small></div><strong class="bill-item-price"><?= e(money($item['subtotal'])) ?></strong></div><?php endforeach; ?></div><div class="bill-summary"><div class="bill-summary-row"><span>Subtotal</span><strong><?= e(money($order['total'])) ?></strong></div><div class="bill-summary-row"><span>Diskon</span><strong>Rp0</strong></div><div class="bill-summary-row grand"><span>Total tagihan</span><strong><?= e(money($order['total'])) ?></strong></div></div></section><section class="card"><div class="card-header"><div><h3>Konfirmasi pembayaran</h3><p>Pilih metode yang digunakan pelanggan</p></div><span class="badge badge-ready"><?= icon('lock') ?> Aman</span></div><div class="card-body"><form method="post" id="paymentForm"><?= csrf_input() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><div class="method-list"><div class="method-option"><input id="methodCash" type="radio" name="metode" value="tunai" <?= $paymentMethod === 'tunai' ? 'checked' : '' ?>><label for="methodCash"><span class="method-icon"><?= icon('wallet') ?></span><span class="method-copy"><strong>Tunai</strong><small>Bayar diounter</small></span><?= icon('check', 'method-check') ?></label></div><div class="method-option"><input id="methodQris" type="radio" name="metode" value="qris" <?= $paymentMethod === 'qris' ? 'checked' : '' ?>><label for="methodQris"><span class="method-icon"><?= icon('phone') ?></span><span class="method-copy"><strong>QRIS</strong><small>QRIS statis cafe</small></span><?= icon('check', 'method-check') ?></label></div><div class="method-option"><input id="methodTransfer" type="radio" name="metode" value="transfer" <?= $paymentMethod === 'transfer' ? 'checked' : '' ?>><label for="methodTransfer"><span class="method-icon"><?= icon('receipt') ?></span><span class="method-copy"><strong>Transfer</strong><small>Rekening / e-wallet</small></span><?= icon('check', 'method-check') ?></label></div></div><div class="form-group mt-18"><label class="form-label" for="paidAmount">Uang diterima</label><input class="form-control" id="paidAmount" name="jumlah_bayar" type="number" min="<?= (int)$order['total'] ?>" step="100" value="<?= e($paidAmount > 0 ? (string)(int)$paidAmount : '') ?>" required placeholder="0"><span class="form-hint">Minimal <?= e(money($order['total'])) ?></span></div><div class="quick-cash"><button type="button" class="quick-cash-btn quick-cash-exact" data-quick-set="<?= (int)$order['total'] ?>">Uang Pas</button><button type="button" class="quick-cash-btn" data-quick-add="10000">+Rp10.000</button><button type="button" class="quick-cash-btn" data-quick-add="20000">+Rp20.000</button><button type="button" class="quick-cash-btn" data-quick-add="50000">+Rp50.000</button><button type="button" class="quick-cash-btn" data-quick-add="100000">+Rp100.000</button><button type="button" class="quick-cash-btn quick-cash-clear" data-quick-clear="1">Reset</button></div><div class="change-box"><div class="change-box-row"><span>Kembalian</span><strong id="changeAmount">Rp0</strong></div><div class="change-warning" id="changeWarning"></div></div><button class="btn btn-primary payment-submit" type="submit"><?= icon('check') ?> Konfirmasi &amp; terbitkan struk</button></form></div></section></div><?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
