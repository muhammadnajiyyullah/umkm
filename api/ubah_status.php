<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
header('Content-Type: application/json; charset=utf-8');
wajib_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Sesi formulir kedaluwarsa.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$role = (string)($user['role'] ?? '');
$orderId = (int)($_POST['order_id'] ?? 0);
$newStatus = trim((string)($_POST['status'] ?? ''));
$keterangan = trim((string)($_POST['keterangan'] ?? '')) ?: null;

if (!in_array($role, ['dapur', 'pelayan', 'kasir', 'admin'], true) || $orderId < 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda tidak berwenang mengubah status ini.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Alur linear sesuai urutan layanan:
 *   Dapur   : Baru -> Diterima -> Diproses -> Siap Saji
 *   Pelayan : Siap Saji -> Menunggu Bayar (antar ke meja)
 *   Kasir   : Menunggu Bayar -> Lunas (lewat halaman proses bayar)
 *   Semua   : status mana pun -> Dibatalkan
 */
$transisi = [
    'Baru' => ['Diterima', 'Dibatalkan'],
    'Diterima' => ['Diproses', 'Dibatalkan'],
    'Diproses' => ['Siap Saji', 'Dibatalkan'],
    'Siap Saji' => ['Menunggu Bayar', 'Dibatalkan'],
    'Menunggu Bayar' => ['Dibatalkan'],
    'Lunas' => [],
    'Dibatalkan' => [],
];
$boleh = [
    'Diterima' => ['dapur', 'admin'],
    'Diproses' => ['dapur', 'admin'],
    'Siap Saji' => ['dapur', 'admin'],
    'Menunggu Bayar' => ['pelayan', 'admin'],
    'Dibatalkan' => ['pelayan', 'kasir', 'admin'],
];

if (!isset($transisi) || !array_key_exists($newStatus, $boleh)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Status baru tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($role, $boleh[$newStatus], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Tahap ini hanya untuk: ' . implode('/', $boleh[$newStatus]) . '.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();
if (!database_ready($pdo)) {
    $demoOrder = demo_order_by_id($orderId);
    if (!$demoOrder) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($role === 'pelayan' && (int)($demoOrder['pelayan_id'] ?? 0) !== (int)$user['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Pesanan ini bukan milik akun Anda.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!in_array($newStatus, $transisi[$demoOrder['status']] ?? [], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Status ' . status_label((string)$demoOrder['status']) . ' tidak dapat diubah menjadi ' . status_label($newStatus) . '.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($newStatus === 'Menunggu Bayar' && $demoOrder['status'] !== 'Siap Saji') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Pesanan belum siap diantar. Minta dapur menandai siap saji dulu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ok = $newStatus === 'Dibatalkan'
        ? demo_cancel_order($orderId, (string)$user['name'])
        : demo_advance_order($orderId, $newStatus, (string)$user['name']);
    if (!$ok) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Status gagal diperbarui.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'message' => demo_status_message($newStatus)], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('SELECT p.*, u.nama AS pelayan FROM pesanan p JOIN users u ON u.id = p.pelayan_id WHERE p.id = ? FOR UPDATE');
    $statement->execute([$orderId]);
    $order = $statement->fetch();
    if (!$order) {
        throw new RuntimeException('Pesanan tidak ditemukan.');
    }
    $oldStatus = (string)$order['status'];
    if ($role === 'pelayan' && (int)$order['pelayan_id'] !== (int)$user['id']) {
        throw new RuntimeException('Pesanan ini bukan milik akun Anda.');
    }
    if (!in_array($newStatus, $transisi[$oldStatus] ?? [], true)) {
        throw new RuntimeException('Status ' . status_label($oldStatus) . ' tidak dapat diubah menjadi ' . status_label($newStatus) . '.');
    }
    if ($oldStatus === 'Lunas') {
        throw new RuntimeException('Pesanan sudah lunas dan tidak dapat diubah.');
    }

    if ($newStatus === 'Dibatalkan') {
        // Kembalikan stok, lepas meja, batalkan seluruh item.
        $items = $pdo->prepare('SELECT menu_id, qty FROM detail_pesanan WHERE pesanan_id = ?');
        $items->execute([$orderId]);
        $restore = $pdo->prepare('UPDATE menu SET stok = stok + ? WHERE id = ?');
        foreach ($items->fetchAll() as $item) {
            $restore->execute([(int)$item['qty'], (int)$item['menu_id']]);
        }
        $pdo->prepare('UPDATE pesanan SET status = "Dibatalkan" WHERE id = ?')->execute([$orderId]);
        $pdo->prepare('UPDATE detail_pesanan SET status_item = "Dibatalkan" WHERE pesanan_id = ?')->execute([$orderId]);
        if ($order['meja_id']) {
            $pdo->prepare('UPDATE meja SET status = "tersedia" WHERE id = ?')->execute([(int)$order['meja_id']]);
        }
        catat_aktivitas($pdo, $orderId, (int)$user['id'], 'Batalkan pesanan', $oldStatus, 'Dibatalkan', $keterangan);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pesanan dibatalkan dan stok dikembalikan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($newStatus === 'Menunggu Bayar') {
        // Pelayan antar ke meja: pastikan semua item cafe sudah siap saji.
        $notReady = $pdo->prepare("SELECT COUNT(*) FROM detail_pesanan d JOIN menu m ON m.id = d.menu_id
                                    WHERE d.pesanan_id = ? AND m.tipe = 'cafe' AND d.status_item <> 'Siap Saji'");
        $notReady->execute([$orderId]);
        if ((int)$notReady->fetchColumn() > 0) {
            throw new RuntimeException('Pesanan belum siap saji seluruhnya. Tunggu dapur menandai siap.');
        }
    }

    $pdo->prepare('UPDATE pesanan SET status = ? WHERE id = ?')->execute([$newStatus, $orderId]);
    // Item UMKM selalu siap saji; item cafe mengikuti tahap yang sedang berjalan.
    $pdo->prepare("UPDATE detail_pesanan d JOIN menu m ON m.id = d.menu_id
                    SET d.status_item = ? WHERE d.pesanan_id = ? AND m.tipe <> 'umkm'")->execute([$newStatus, $orderId]);

    catat_aktivitas($pdo, $orderId, (int)$user['id'], 'Ubah status', $oldStatus, $newStatus, $keterangan ?? demo_status_message($newStatus));
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => demo_status_message($newStatus)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => pesan_exception($exception, 'Status gagal diperbarui.')], JSON_UNESCAPED_UNICODE);
}
