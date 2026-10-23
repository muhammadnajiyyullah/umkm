<?php
declare(strict_types=1);

require_once __DIR__ . '/demo_data.php';
require_once __DIR__ . '/schema.php';

function database_ready(?PDO $pdo): bool
{
    if (!$pdo) {
        return false;
    }
    try {
        $statement = $pdo->query("SHOW TABLES LIKE 'users'");
        if (!$statement->fetchColumn()) {
            return false;
        }
    } catch (Throwable $exception) {
        return false;
    }
    ensure_schema($pdo);
    return true;
}

function data_categories(?PDO $pdo): array
{
    if (database_ready($pdo)) {
        $rows = db_all('SELECT id, nama FROM kategori ORDER BY nama', []);
        if ($rows) {
            return $rows;
        }
    }
    return demo_categories();
}

/**
 * Membuat nomor urut harian yang bebas tabrakan, misalnya ORD-20260926-0004.
 *
 * Angka diambil dari sufiks terbesar yang sudah dipakai, bukan dari COUNT(*),
 * sehingga penghapusan pesanan lama tidak menyebabkan nomor terpakai ulang.
 */
function next_daily_code(PDO $pdo, string $table, string $column, string $prefix): string
{
    $statement = $pdo->prepare("SELECT `{$column}` FROM `{$table}` WHERE `{$column}` LIKE ?");
    $statement->execute([$prefix . '%']);
    $max = 0;
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $code) {
        $max = max($max, (int)substr((string)$code, strlen($prefix)));
    }
    return $prefix . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
}

function next_kode_pesanan(PDO $pdo): string
{
    return next_daily_code($pdo, 'pesanan', 'kode_pesanan', 'ORD-' . date('Ymd') . '-');
}

function next_nomor_struk(PDO $pdo): string
{
    return next_daily_code($pdo, 'pembayaran', 'nomor_struk', 'STR-' . date('Ymd') . '-');
}

function data_menus(?PDO $pdo, array $filters = []): array
{
    if (database_ready($pdo)) {
        $where = ['m.is_tersedia = 1'];
        $params = [];
        if (!empty($filters['category_id'])) {
            $where[] = 'm.kategori_id = :category_id';
            $params['category_id'] = (int)$filters['category_id'];
        }
        if (!empty($filters['search'])) {
            // Placeholder bernama tidak boleh diulang: PDO dengan emulate_prepares
            // mati hanya mengikat satu kemunculan per nama.
            $where[] = '(m.nama LIKE :search_nama OR m.kode LIKE :search_kode)';
            $params['search_nama'] = '%' . $filters['search'] . '%';
            $params['search_kode'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['tipe'])) {
            $where[] = 'm.tipe = :tipe';
            $params['tipe'] = $filters['tipe'];
        }
        $sql = 'SELECT m.*, k.nama AS kategori, p.nama_usaha AS mitra
                FROM menu m
                LEFT JOIN kategori k ON k.id = m.kategori_id
                LEFT JOIN mitra_umkm p ON p.id = m.mitra_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY m.tipe ASC, m.nama ASC';
        $rows = db_all($sql, $params);
        return $rows ?: [];
    }
    $menus = demo_menus();
    if (!empty($filters['category_id'])) {
        $menus = array_values(array_filter($menus, static fn(array $menu): bool => (int)$menu['kategori_id'] === (int)$filters['category_id']));
    }
    if (!empty($filters['search'])) {
        $search = mb_strtolower((string)$filters['search']);
        $menus = array_values(array_filter($menus, static fn(array $menu): bool => str_contains(mb_strtolower($menu['nama']), $search) || str_contains(mb_strtolower($menu['kode']), $search)));
    }
    if (!empty($filters['tipe'])) {
        $menus = array_values(array_filter($menus, static fn(array $menu): bool => $menu['tipe'] === $filters['tipe']));
    }
    return $menus;
}

function data_tables(?PDO $pdo): array
{
    if (database_ready($pdo)) {
        return db_all('SELECT * FROM meja ORDER BY nomor', []);
    }
    return demo_tables();
}

function data_order_items(?PDO $pdo, int $orderId): array
{
    if (database_ready($pdo)) {
        return db_all('SELECT d.*, m.kode, m.nama, m.tipe, m.foto, p.nama_usaha AS mitra
                       FROM detail_pesanan d
                       JOIN menu m ON m.id = d.menu_id
                       LEFT JOIN mitra_umkm p ON p.id = m.mitra_id
                       WHERE d.pesanan_id = ?
                       ORDER BY d.id', [$orderId]);
    }
    $order = demo_order_by_id($orderId);
    return $order['items'] ?? [];
}

function data_orders(?PDO $pdo, array $filters = []): array
{
    if (database_ready($pdo)) {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $statuses = array_values(array_filter(array_map('strval', $filters['statuses'])));
            if ($statuses) {
                $where[] = 'p.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
                $params = array_merge($params, $statuses);
            }
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'p.pelayan_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['exclude_paid'])) {
            $where[] = "p.status <> 'Lunas'";
        }
        if (!empty($filters['unpaid'])) {
            $where[] = "p.status = 'Menunggu Bayar' AND p.status <> 'Dibatalkan'";
        }
        if (!empty($filters['search'])) {
            // Pakai placeholder posisional berulang: PDO dengan emulate_prepares
            // mati tidak mencampur parameter bernama dan posisional.
            $where[] = '(p.kode_pesanan LIKE ? OR p.nama_pelanggan LIKE ? OR t.nomor LIKE ? OR u.nama LIKE ?)';
            $needle = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$needle, $needle, $needle, $needle]);
        }
        if (!empty($filters['pelanggan'])) {
            $where[] = 'p.nama_pelanggan = ?';
            $params[] = (string)$filters['pelanggan'];
        }
        $limit = min(200, max(1, (int)($filters['limit'] ?? 100)));
        $sql = 'SELECT p.*, u.nama AS pelayan, t.nomor AS meja_nomor
                FROM pesanan p
                JOIN users u ON u.id = p.pelayan_id
                LEFT JOIN meja t ON t.id = p.meja_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY FIELD(p.status, "Baru", "Diterima", "Diproses", "Siap Saji", "Menunggu Bayar", "Dibatalkan", "Lunas"), p.created_at DESC
                LIMIT ' . $limit;
        $orders = db_all($sql, $params);
        foreach ($orders as $index => $order) {
            $orders[$index]['meja'] = $order['meja_nomor'];
            $orders[$index]['items'] = data_order_items($pdo, (int)$order['id']);
        }
        return $orders;
    }
    $orders = demo_orders();
    if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
        $orders = array_values(array_filter($orders, static fn(array $order): bool => in_array($order['status'], $filters['statuses'], true)));
    }
    if (!empty($filters['user_id'])) {
        $orders = array_values(array_filter($orders, static fn(array $order): bool => (int)($order['pelayan_id'] ?? 0) === (int)$filters['user_id']));
    }
    if (!empty($filters['exclude_paid'])) {
        $orders = array_values(array_filter($orders, static fn(array $order): bool => $order['status'] !== 'Lunas'));
    }
    if (!empty($filters['unpaid'])) {
        $orders = array_values(array_filter($orders, static fn(array $order): bool => $order['status'] === 'Menunggu Bayar'));
    }
    if (!empty($filters['search'])) {
        $needle = mb_strtolower((string)$filters['search']);
        $orders = array_values(array_filter($orders, static function (array $order) use ($needle): bool {
            $haystack = mb_strtolower(($order['kode_pesanan'] ?? '') . ' ' . ($order['meja'] ?? '') . ' ' . ($order['pelayan'] ?? '') . ' ' . ($order['nama_pelanggan'] ?? ''));
            return str_contains($haystack, $needle);
        }));
    }
    if (!empty($filters['pelanggan'])) {
        $orders = array_values(array_filter($orders, static fn(array $order): bool => (string)($order['nama_pelanggan'] ?? '') === (string)$filters['pelanggan']));
    }
    return array_slice($orders, 0, (int)($filters['limit'] ?? 100));
}

function data_order(?PDO $pdo, int $id): ?array
{
    if (database_ready($pdo)) {
        $order = db_one('SELECT p.*, u.nama AS pelayan, t.nomor AS meja_nomor
                         FROM pesanan p
                         JOIN users u ON u.id = p.pelayan_id
                         LEFT JOIN meja t ON t.id = p.meja_id
                         WHERE p.id = ?', [$id]);
        if ($order) {
            $order['meja'] = $order['meja_nomor'];
            $order['items'] = data_order_items($pdo, $id);
            $payment = db_one('SELECT pay.*, u.nama AS kasir_nama FROM pembayaran pay JOIN users u ON u.id = pay.kasir_id WHERE pay.pesanan_id = ? ORDER BY pay.id DESC LIMIT 1', [$id]);
            $order['payment'] = $payment;
        }
        return $order;
    }
    return demo_order_by_id($id);
}

function data_partners(?PDO $pdo): array
{
    if (database_ready($pdo)) {
        // Kolom ditulis eksplisit, bukan p.*. Demo memakai SELECT p.*,u.nama AS
        // pemilik sehingga kolom mitra_umkm.pemilik tertimpa dan nilainya
        // berbeda antara halaman admin dan halaman mitra.
        //
        // owners: pemilik yang tampil = nama akun mitra bila tertaut ke
        // users, jika tidak pakai kolom pemilik bebas. Dengan begitu rename
        // user langsung tercermin di halaman mana pun.
        $rows = db_all("SELECT p.id, p.user_id, p.nama_usaha, p.kontak, p.komisi_persen, p.status, p.created_at,
                        COALESCE(u.nama, p.pemilik) AS pemilik,
                        p.pemilik AS pemilik_usaha,
                        u.nama AS nama_akun, u.username,
                        COUNT(DISTINCT m.id) AS produk,
                        COALESCE(SUM(CASE WHEN pes.status = 'Lunas' THEN d.qty ELSE 0 END), 0) AS terjual,
                        COALESCE(SUM(CASE WHEN pes.status = 'Lunas' THEN d.subtotal ELSE 0 END), 0) AS penjualan,
                        COALESCE(SUM(CASE WHEN pes.status = 'Lunas' THEN d.subtotal * p.komisi_persen / 100 ELSE 0 END), 0) AS komisi
                        FROM mitra_umkm p
                        LEFT JOIN users u ON u.id = p.user_id
                        LEFT JOIN menu m ON m.mitra_id = p.id AND m.tipe = 'umkm'
                        LEFT JOIN detail_pesanan d ON d.menu_id = m.id
                        LEFT JOIN pesanan pes ON pes.id = d.pesanan_id
                        GROUP BY p.id, p.user_id, p.nama_usaha, p.kontak, p.komisi_persen, p.status, p.created_at, p.pemilik, u.nama, u.username
                        ORDER BY p.status = 'aktif' DESC, p.nama_usaha");
        return $rows;
    }
    return demo_partners();
}

function data_users(?PDO $pdo): array
{
    if (database_ready($pdo)) {
        return db_all('SELECT id, nama, username, role, is_active, last_login, created_at FROM users ORDER BY nama', []);
    }
    return demo_users();
}

function data_logs(?PDO $pdo, int $limit = 100): array
{
    if (database_ready($pdo)) {
        return db_all('SELECT l.*, p.kode_pesanan, u.nama AS user_nama
                       FROM log_pesanan l
                       LEFT JOIN pesanan p ON p.id = l.pesanan_id
                       LEFT JOIN users u ON u.id = l.user_id
                       ORDER BY l.created_at DESC LIMIT ' . min(200, max(1, $limit)), []);
    }
    return array_slice(demo_logs(), 0, $limit);
}

function data_dashboard(?PDO $pdo, string $role, int $userId): array
{
    if (!database_ready($pdo)) {
        return demo_dashboard_data();
    }

    $today = db_one("SELECT COALESCE(SUM(total), 0) AS omzet, COUNT(*) AS transaksi
                     FROM pesanan WHERE status = 'Lunas' AND DATE(created_at) = CURDATE()") ?: ['omzet' => 0, 'transaksi' => 0];
    $active = (int)(db_one("SELECT COUNT(*) AS total FROM pesanan WHERE status NOT IN ('Lunas','Dibatalkan')")['total'] ?? 0);
    $ready = (int)(db_one("SELECT COUNT(*) AS total FROM pesanan WHERE status = 'Siap Saji'")['total'] ?? 0);
    $waiting = (int)(db_one("SELECT COUNT(*) AS total FROM pesanan WHERE status = 'Menunggu Bayar'")['total'] ?? 0);
    $queue = (int)(db_one("SELECT COUNT(*) AS total FROM pesanan WHERE status IN ('Baru','Diterima','Diproses')")['total'] ?? 0);
    $umkm = db_one("SELECT COALESCE(SUM(d.subtotal),0) AS sales, COALESCE(SUM(d.qty),0) AS items
                    FROM detail_pesanan d JOIN menu m ON m.id=d.menu_id JOIN pesanan p ON p.id=d.pesanan_id
                    WHERE m.tipe='umkm' AND p.status='Lunas' AND DATE(p.created_at)=CURDATE()") ?: ['sales' => 0, 'items' => 0];
    $chart = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime('-' . $i . ' days'));
        $value = (float)(db_one('SELECT COALESCE(SUM(total),0) AS total FROM pesanan WHERE status = \'Lunas\' AND DATE(created_at) = ?', [$date])['total'] ?? 0);
        $chart[] = ['label' => date('d M', strtotime($date)), 'value' => $value];
    }
    $recent = data_orders($pdo, ['limit' => 6]);
    $top = db_all("SELECT m.id, m.nama, m.kode, m.foto, m.harga, m.stok,
                          COALESCE(SUM(CASE WHEN p.status = 'Lunas' THEN d.qty ELSE 0 END),0) AS terjual
                   FROM menu m
                   LEFT JOIN detail_pesanan d ON d.menu_id=m.id
                   LEFT JOIN pesanan p ON p.id=d.pesanan_id AND p.status='Lunas'
                   GROUP BY m.id ORDER BY terjual DESC, m.nama LIMIT 5");
    $low = db_all('SELECT id, nama, kode, stok, harga, foto, tipe FROM menu WHERE is_tersedia=1 AND stok <= 8 ORDER BY stok LIMIT 6');
    $partners = data_partners($pdo);
    $paid = (int)$today['transaksi'];
    return [
        'omzet' => (float)$today['omzet'],
        'transaksi' => $paid,
        'active_orders' => $active,
        'ready_orders' => $ready,
        'waiting_orders' => $waiting,
        'queue_orders' => $queue,
        'umkm_sales' => (float)$umkm['sales'],
        'umkm_items' => (int)$umkm['items'],
        'avg_transaction' => $paid ? (int)round((float)$today['omzet'] / $paid) : 0,
        'chart' => $chart,
        'recent_orders' => $recent,
        'top_menus' => $top,
        'low_stock' => $low,
        'partners' => $partners,
    ];
}

function data_report(?PDO $pdo, string $from, string $to): array
{
    if (!database_ready($pdo)) {
        $orders = array_values(array_filter(demo_orders(), static function (array $order) use ($from, $to): bool {
            $date = date('Y-m-d', strtotime($order['created_at']));
            return $date >= $from && $date <= $to;
        }));
        $paid = array_values(array_filter($orders, static fn(array $order): bool => $order['status'] === 'Lunas'));
        $omzet = array_sum(array_map(static fn(array $order): int => (int)$order['total'], $paid));
        $partners = [];
        foreach (demo_partners() as $partner) {
            $partners[] = $partner;
        }
        return ['from' => $from, 'to' => $to, 'omzet' => $omzet, 'transaksi' => count($paid), 'items' => array_sum(array_map(static fn(array $order): int => array_sum(array_column($order['items'], 'qty')), $paid)), 'orders' => $paid, 'partners' => $partners];
    }
    $summary = db_one("SELECT COALESCE(SUM(p.total),0) AS omzet, COUNT(*) AS transaksi,
                       COALESCE(SUM((SELECT COALESCE(SUM(d.qty),0) FROM detail_pesanan d WHERE d.pesanan_id=p.id)),0) AS items
                       FROM pesanan p WHERE p.status='Lunas' AND DATE(p.created_at) BETWEEN ? AND ?", [$from, $to]) ?: [];
    $orders = db_all("SELECT p.*, u.nama AS pelayan, t.nomor AS meja_nomor, pay.nomor_struk, pay.metode
                      FROM pesanan p JOIN users u ON u.id=p.pelayan_id LEFT JOIN meja t ON t.id=p.meja_id
                      LEFT JOIN pembayaran pay ON pay.pesanan_id=p.id
                      WHERE p.status='Lunas' AND DATE(p.created_at) BETWEEN ? AND ?
                      ORDER BY p.created_at DESC", [$from, $to]);
    $partners = db_all("SELECT p.nama_usaha, p.komisi_persen,
                        COALESCE(SUM(d.subtotal),0) AS penjualan,
                        COALESCE(SUM(d.subtotal * p.komisi_persen / 100),0) AS komisi,
                        COALESCE(SUM(d.qty),0) AS terjual
                        FROM mitra_umkm p JOIN menu m ON m.mitra_id=p.id AND m.tipe='umkm'
                        JOIN detail_pesanan d ON d.menu_id=m.id JOIN pesanan pes ON pes.id=d.pesanan_id AND pes.status='Lunas'
                        WHERE DATE(pes.created_at) BETWEEN ? AND ? GROUP BY p.id ORDER BY penjualan DESC", [$from, $to]);
    return ['from' => $from, 'to' => $to, 'omzet' => (float)($summary['omzet'] ?? 0), 'transaksi' => (int)($summary['transaksi'] ?? 0), 'items' => (int)($summary['items'] ?? 0), 'orders' => $orders, 'partners' => $partners];
}

function data_dashboard_orders(?PDO $pdo, string $role, int $userId): array
{
    if ($role === 'dapur') {
        return data_orders($pdo, ['statuses' => ['Baru', 'Diterima', 'Diproses'], 'limit' => 100]);
    }
    if ($role === 'kasir') {
        return data_orders($pdo, ['statuses' => ['Menunggu Bayar'], 'limit' => 100]);
    }
    if ($role === 'pelayan') {
        return data_orders($pdo, ['user_id' => $userId, 'exclude_paid' => true, 'limit' => 100]);
    }
    if ($role === 'umkm') {
        return data_orders($pdo, ['statuses' => ['Lunas'], 'limit' => 30]);
    }
    return data_orders($pdo, ['limit' => 8]);
}
