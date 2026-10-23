<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
header('Content-Type: application/json; charset=utf-8');
wajib_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (current_user()['role'] ?? '') !== 'kasir') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses khusus kasir.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Sesi formulir kedaluwarsa.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$orderId = (int)($_POST['order_id'] ?? 0);
$method = in_array($_POST['metode'] ?? '', ['tunai', 'qris', 'transfer'], true) ? (string)$_POST['metode'] : 'tunai';
$paid = (float)($_POST['jumlah_bayar'] ?? 0);
if ($orderId < 1 || $paid < 0) {
    echo json_encode(['success' => false, 'message' => 'Data pembayaran tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$pdo = db();
if (!database_ready($pdo)) {
    $order = demo_process_payment($orderId, $method, $paid, (string)current_user()['name']);
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dikonfirmasi.', 'redirect' => url('kasir/struk.php?id=' . $orderId)], JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('SELECT * FROM pesanan WHERE id=? FOR UPDATE');
    $statement->execute([$orderId]);
    $order = $statement->fetch();
    if (!$order || $order['status'] === 'Lunas') {
        throw new RuntimeException('Pesanan sudah dibayar atau tidak ditemukan.');
    }
    $notReady = $pdo->prepare("SELECT COUNT(*) FROM detail_pesanan d JOIN menu m ON m.id=d.menu_id WHERE d.pesanan_id=? AND m.tipe='cafe' AND d.status_item <> 'Siap Saji'");
    $notReady->execute([$orderId]);
    if ((int)$notReady->fetchColumn() > 0) {
        throw new RuntimeException('Pesanan belum siap saji seluruhnya.');
    }
    $total = (float)$order['total'];
    $kembalian = max(0, $paid - $total);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran WHERE DATE(waktu_bayar)=CURDATE()")->fetchColumn();
    $struk = 'STR-' . date('Ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
    $payment = $pdo->prepare('INSERT INTO pembayaran (pesanan_id, kasir_id, nomor_struk, metode, jumlah_bayar, kembalian) VALUES (?, ?, ?, ?, ?, ?)');
    $payment->execute([$orderId, (int)current_user()['id'], $struk, $method, $paid, $kembalian]);
    $pdo->prepare('UPDATE pesanan SET status="Lunas" WHERE id=?')->execute([$orderId]);
    $pdo->prepare('UPDATE detail_pesanan SET status_item="Siap Saji" WHERE pesanan_id=?')->execute([$orderId]);
    if ($order['meja_id']) {
        $pdo->prepare('UPDATE meja SET status="tersedia" WHERE id=?')->execute([(int)$order['meja_id']]);
    }
    catat_aktivitas($pdo, $orderId, (int)current_user()['id'], 'Bayar pesanan', $order['status'], 'Lunas', $method);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dikonfirmasi.', 'redirect' => url('kasir/struk.php?id=' . $orderId)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => pesan_exception($exception, 'Pembayaran gagal diproses.')], JSON_UNESCAPED_UNICODE);
}
