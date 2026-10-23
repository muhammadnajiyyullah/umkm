<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
wajib_login();
if ((current_user()['role'] ?? '') !== 'dapur') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses khusus dapur.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$orders = data_orders(db(), ['statuses' => ['Baru', 'Diterima', 'Diproses'], 'limit' => 100]);
$orders = array_values(array_filter($orders, static function (array $order): bool {
    foreach ($order['items'] ?? [] as $item) {
        if (($item['tipe'] ?? 'cafe') === 'cafe') {
            return true;
        }
    }
    return false;
}));
// Pesanan terlama lebih dulu.
usort($orders, static fn(array $a, array $b): int => strcmp((string)$a['created_at'], (string)$b['created_at']));
$payload = [];
foreach ($orders as $order) {
    $age = max(0, time() - (int)strtotime((string)($order['created_at'] ?? 'now')));
    $payload[] = [
        'id' => (int)$order['id'],
        'kode' => (string)($order['kode_pesanan'] ?? ''),
        'meja' => $order['meja'] ?? null,
        'nama_pelanggan' => order_pelanggan($order, 'Pelanggan'),
        'status' => (string)$order['status'],
        'total' => (int)$order['total'],
        'created_at' => $order['created_at'] ?? null,
        'age_minutes' => (int)floor($age / 60),
        'items' => array_map(static fn(array $item): array => [
            'nama' => (string)($item['nama'] ?? 'Menu'),
            'qty' => (int)($item['qty'] ?? 1),
            'catatan_item' => (string)($item['catatan_item'] ?? ''),
            'status_item' => (string)($item['status_item'] ?? 'Baru'),
        ], $order['items'] ?? []),
    ];
}
echo json_encode(['success' => true, 'orders' => $payload, 'server_time' => date('c')], JSON_UNESCAPED_UNICODE);
