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
    echo json_encode(['success' => false, 'message' => 'Sesi formulir kedaluwarsa. Muat ulang halaman.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
if (!in_array($user['role'] ?? '', ['pelayan', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Hanya pelayan yang dapat membuat pesanan.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawItems = json_decode((string)($_POST['items'] ?? '[]'), true);
$items = is_array($rawItems) ? $rawItems : [];
$tipePesanan = ($_POST['tipe_pesanan'] ?? 'dine_in') === 'takeaway' ? 'takeaway' : 'dine_in';
$mejaId = (int)($_POST['meja_id'] ?? 0);
$catatan = trim((string)($_POST['catatan'] ?? ''));
$namaPelanggan = sanitize_pelanggan($_POST['nama_pelanggan'] ?? '');
$logNote = $catatan !== '' ? $catatan : '';
if ($namaPelanggan !== '') {
    $logNote = $logNote !== '' ? 'Pelanggan: ' . $namaPelanggan . ' · ' . $logNote : 'Pelanggan: ' . $namaPelanggan;
}
if (!$items) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Keranjang masih kosong.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($namaPelanggan === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Isi nama pelanggan terlebih dahulu.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($tipePesanan === 'dine_in' && $mejaId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Pilih meja sebelum menyimpan pesanan.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$editId = (int)($_POST['edit_id'] ?? 0);
$pdo = db();
if ($editId > 0) {
    if (!database_ready($pdo)) {
        $updated = demo_update_order($editId, ['items' => $items, 'tipe_pesanan' => $tipePesanan, 'meja_id' => $mejaId ?: null, 'catatan' => $catatan, 'nama_pelanggan' => $namaPelanggan]);
        if (!$updated) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Pesanan tidak dapat diubah pada status saat ini.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Perubahan pesanan demo berhasil disimpan.', 'redirect' => url('pelayan/pesanan_aktif.php')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $editTransaction = false;
    try {
        $pdo->beginTransaction();
        $editTransaction = true;
        $orderStatement = $pdo->prepare('SELECT * FROM pesanan WHERE id=? FOR UPDATE');
        $orderStatement->execute([$editId]);
        $existingOrder = $orderStatement->fetch();
        if (!$existingOrder || (int)$existingOrder['pelayan_id'] !== (int)$user['id'] || !in_array($existingOrder['status'], ['Baru', 'Diterima'], true)) {
            throw new RuntimeException('Pesanan tidak dapat diubah pada status saat ini.');
        }
        $oldDetails = $pdo->prepare('SELECT menu_id, qty FROM detail_pesanan WHERE pesanan_id=?');
        $oldDetails->execute([$editId]);
        foreach ($oldDetails->fetchAll() as $oldItem) {
            $pdo->prepare('UPDATE menu SET stok=stok+? WHERE id=?')->execute([(int)$oldItem['qty'], (int)$oldItem['menu_id']]);
        }
        if ($tipePesanan === 'dine_in' && (int)($existingOrder['meja_id'] ?? 0) !== $mejaId) {
            if ($existingOrder['meja_id']) $pdo->prepare('UPDATE meja SET status="tersedia" WHERE id=?')->execute([(int)$existingOrder['meja_id']]);
            $checkTable = $pdo->prepare('SELECT id FROM meja WHERE id=? AND status="tersedia" FOR UPDATE');
            $checkTable->execute([$mejaId]);
            if (!$checkTable->fetch()) throw new RuntimeException('Meja baru sudah terisi.');
        }
        $menuStatement = $pdo->prepare('SELECT id, kode, nama, harga, stok, tipe FROM menu WHERE id=? AND is_tersedia=1 FOR UPDATE');
        $updateStock = $pdo->prepare('UPDATE menu SET stok=stok-? WHERE id=? AND stok>=?');
        $validItems = [];
        $total = 0;
        $hasCafeItem = false;
        foreach ($items as $raw) {
            $menuId = (int)($raw['menu_id'] ?? 0);
            $qty = max(1, min(99, (int)($raw['qty'] ?? 1)));
            $menuStatement->execute([$menuId]);
            $menu = $menuStatement->fetch();
            if (!$menu || (int)$menu['stok'] < $qty) throw new RuntimeException('Menu tidak tersedia atau stok tidak mencukupi.');
            $subtotal = (float)$menu['harga'] * $qty;
            $total += $subtotal;
            $hasCafeItem = $hasCafeItem || $menu['tipe'] === 'cafe';
            $validItems[] = ['menu' => $menu, 'qty' => $qty, 'subtotal' => $subtotal, 'note' => trim((string)($raw['catatan_item'] ?? ''))];
        }
        $pdo->prepare('DELETE FROM detail_pesanan WHERE pesanan_id=?')->execute([$editId]);
        $detail = $pdo->prepare('INSERT INTO detail_pesanan (pesanan_id, menu_id, qty, harga_satuan, subtotal, catatan_item, status_item) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($validItems as $item) {
            $detail->execute([$editId, $item['menu']['id'], $item['qty'], $item['menu']['harga'], $item['subtotal'], $item['note'] ?: null, $item['menu']['tipe'] === 'umkm' ? 'Siap Saji' : $existingOrder['status']]);
            $updateStock->execute([$item['qty'], $item['menu']['id'], $item['qty']]);
        }
        $newStatus = $hasCafeItem ? $existingOrder['status'] : 'Siap Saji';
        $pdo->prepare('UPDATE pesanan SET meja_id=?, tipe_pesanan=?, nama_pelanggan=?, status=?, total=?, catatan=? WHERE id=?')->execute([$tipePesanan === 'dine_in' ? $mejaId : null, $tipePesanan, $namaPelanggan ?: null, $newStatus, $total, $catatan ?: null, $editId]);
        if ($tipePesanan === 'dine_in') $pdo->prepare('UPDATE meja SET status="terisi" WHERE id=?')->execute([$mejaId]);
        catat_aktivitas($pdo, $editId, (int)$user['id'], 'Ubah pesanan', $existingOrder['status'], $newStatus, $logNote ?: null);
        $pdo->commit();
        $editTransaction = false;
        echo json_encode(['success' => true, 'message' => 'Perubahan pesanan berhasil disimpan.', 'redirect' => url('pelayan/pesanan_aktif.php'), 'order_id' => $editId], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $exception) {
        if ($editTransaction && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => pesan_exception($exception, 'Perubahan pesanan gagal disimpan.')], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$pdo = db();
if (!database_ready($pdo)) {
    $order = demo_add_order(['items' => $items, 'tipe_pesanan' => $tipePesanan, 'meja_id' => $mejaId ?: null, 'catatan' => $catatan, 'nama_pelanggan' => $namaPelanggan]);
    if (!$order) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Item tidak valid atau sudah habis.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Pesanan demo berhasil dibuat.', 'order' => $order, 'redirect' => url('pelayan/pesanan_aktif.php')], JSON_UNESCAPED_UNICODE);
    exit;
}

$transaction = false;
try {
    $pdo->beginTransaction();
    $transaction = true;
    if ($tipePesanan === 'dine_in') {
        $tableStatement = $pdo->prepare('SELECT id FROM meja WHERE id = ? AND status = "tersedia" FOR UPDATE');
        $tableStatement->execute([$mejaId]);
        if (!$tableStatement->fetch()) {
            throw new RuntimeException('Meja sudah terisi. Pilih meja lain.');
        }
    }

    $menuStatement = $pdo->prepare('SELECT id, kode, nama, harga, stok, tipe FROM menu WHERE id = ? AND is_tersedia = 1 FOR UPDATE');
    $updateStock = $pdo->prepare('UPDATE menu SET stok = stok - ? WHERE id = ? AND stok >= ?');
    $total = 0;
    $validItems = [];
    $hasCafeItem = false;
    foreach ($items as $raw) {
        $menuId = (int)($raw['menu_id'] ?? 0);
        $qty = max(1, min(99, (int)($raw['qty'] ?? 1)));
        $menuStatement->execute([$menuId]);
        $menu = $menuStatement->fetch();
        if (!$menu) {
            throw new RuntimeException('Salah satu menu sudah tidak tersedia.');
        }
        if ((int)$menu['stok'] < $qty) {
            throw new RuntimeException('Stok ' . $menu['nama'] . ' tidak mencukupi.');
        }
        $subtotal = (float)$menu['harga'] * $qty;
        $total += $subtotal;
        $hasCafeItem = $hasCafeItem || $menu['tipe'] === 'cafe';
        $validItems[] = ['menu' => $menu, 'qty' => $qty, 'subtotal' => $subtotal, 'note' => trim((string)($raw['catatan_item'] ?? ''))];
    }
    $code = next_kode_pesanan($pdo);
    // Pesanan masuk antrian dapur. Item UMKM siap saji sehingga pesanannya
    // langsung siap diantar tanpa dimasak.
    $status = $hasCafeItem ? 'Baru' : 'Siap Saji';
    $statement = $pdo->prepare('INSERT INTO pesanan (kode_pesanan, meja_id, tipe_pesanan, pelayan_id, nama_pelanggan, status, total, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $statement->execute([$code, $tipePesanan === 'dine_in' ? $mejaId : null, $tipePesanan, (int)$user['id'], $namaPelanggan ?: null, $status, $total, $catatan ?: null]);
    $orderId = (int)$pdo->lastInsertId();
    $detail = $pdo->prepare('INSERT INTO detail_pesanan (pesanan_id, menu_id, qty, harga_satuan, subtotal, catatan_item, status_item) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($validItems as $item) {
        $detail->execute([$orderId, $item['menu']['id'], $item['qty'], $item['menu']['harga'], $item['subtotal'], $item['note'] ?: null, $item['menu']['tipe'] === 'umkm' ? 'Siap Saji' : 'Baru']);
        $updateStock->execute([$item['qty'], $item['menu']['id'], $item['qty']]);
    }
    if ($tipePesanan === 'dine_in') {
        $tableUpdate = $pdo->prepare('UPDATE meja SET status = "terisi" WHERE id = ?');
        $tableUpdate->execute([$mejaId]);
    }
    catat_aktivitas($pdo, $orderId, (int)$user['id'], 'Buat pesanan', '-', $status, $logNote ?: null);
    $pdo->commit();
    $transaction = false;
    echo json_encode([
        'success' => true,
        'message' => 'Pesanan berhasil dikirim ke dapur.',
        'redirect' => url('pelayan/pesanan_aktif.php'),
        'order_id' => $orderId,
        'kode' => $code,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($transaction && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => pesan_exception($exception, 'Pesanan gagal disimpan. Silakan coba lagi.')], JSON_UNESCAPED_UNICODE);
}
