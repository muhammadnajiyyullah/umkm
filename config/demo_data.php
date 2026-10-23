<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function demo_initial_menus(): array
{
    return [
        ['id' => 1, 'kode' => 'MKN-001', 'nama' => 'Cappuccino', 'kategori_id' => 1, 'kategori' => 'Minuman', 'harga' => 28000, 'stok' => 24, 'tipe' => 'cafe', 'mitra_id' => null, 'mitra' => null, 'is_tersedia' => 1, 'terlaris' => 42, 'deskripsi' => 'Espresso, steamed milk, dan busa lembut.', 'foto' => 'assets/img/menu/cappuccino.svg'],
        ['id' => 2, 'kode' => 'MKN-002', 'nama' => 'Matcha Latte', 'kategori_id' => 1, 'kategori' => 'Minuman', 'harga' => 32000, 'stok' => 18, 'tipe' => 'cafe', 'mitra_id' => null, 'mitra' => null, 'is_tersedia' => 1, 'terlaris' => 31, 'deskripsi' => 'Matcha premium dengan susu oat yang gurih.', 'foto' => 'assets/img/menu/matcha-latte.svg'],
        ['id' => 3, 'kode' => 'MKN-003', 'nama' => 'Cold Brew Tonic', 'kategori_id' => 1, 'kategori' => 'Minuman', 'harga' => 30000, 'stok' => 15, 'tipe' => 'cafe', 'mitra_id' => null, 'mitra' => null, 'is_tersedia' => 1, 'terlaris' => 27, 'deskripsi' => 'Cold brew-blackcurrant dengan rasa segar.', 'foto' => 'assets/img/menu/cold-brew.svg'],
        ['id' => 4, 'kode' => 'MKN-004', 'nama' => 'Butter Croissant', 'kategori_id' => 2, 'kategori' => 'Makanan', 'harga' => 22000, 'stok' => 12, 'tipe' => 'cafe', 'mitra_id' => null, 'mitra' => null, 'is_tersedia' => 1, 'terlaris' => 19, 'deskripsi' => 'Croissant buttery, renyah di luar dan lembut di dalam.', 'foto' => 'assets/img/menu/croissant.svg'],
        ['id' => 5, 'kode' => 'MKN-005', 'nama' => 'Nasi Goreng Kampung', 'kategori_id' => 2, 'kategori' => 'Makanan', 'harga' => 32000, 'stok' => 9, 'tipe' => 'cafe', 'mitra_id' => null, 'mitra' => null, 'is_tersedia' => 1, 'terlaris' => 16, 'deskripsi' => 'Nasi goreng smoky dengan telur dan garnish.', 'foto' => 'assets/img/menu/nasi-goreng.svg'],
        ['id' => 6, 'kode' => 'UMKM-001', 'nama' => 'Cookies Cokelat', 'kategori_id' => 3, 'kategori' => 'Produk UMKM', 'harga' => 18000, 'stok' => 20, 'tipe' => 'umkm', 'mitra_id' => 1, 'mitra' => 'Rasa Rumahan', 'is_tersedia' => 1, 'terlaris' => 14, 'deskripsi' => 'Homemade cookies dengan filling cokelat Belgia.', 'foto' => 'assets/img/menu/cookies.svg'],
        ['id' => 7, 'kode' => 'UMKM-002', 'nama' => 'Keripik Pisang', 'kategori_id' => 3, 'kategori' => 'Produk UMKM', 'harga' => 15000, 'stok' => 7, 'tipe' => 'umkm', 'mitra_id' => 2, 'mitra' => 'Bumi Snack', 'is_tersedia' => 1, 'terlaris' => 11, 'deskripsi' => 'Keripik pisang renyah dengan garam yang pas.', 'foto' => 'assets/img/menu/keripik-pisang.svg'],
        ['id' => 8, 'kode' => 'UMKM-003', 'nama' => 'Es Citrus Lumut', 'kategori_id' => 3, 'kategori' => 'Produk UMKM', 'harga' => 16000, 'stok' => 16, 'tipe' => 'umkm', 'mitra_id' => 3, 'mitra' => 'Sari Buah', 'is_tersedia' => 1, 'terlaris' => 9, 'deskripsi' => 'Es markis lumpur-orange yang menyegarkan.', 'foto' => 'assets/img/menu/es-citrus.svg'],
    ];
}

function demo_initial_tables(): array
{
    // Awalnya semua meja kosong; pesanan demo akan menempati mejanya sendiri.
    $tables = [];
    foreach (range(1, 10) as $number) {
        $tables[] = [
            'id' => $number,
            'nomor' => str_pad((string)$number, 2, '0', STR_PAD_LEFT),
            'kapasitas' => $number % 3 === 0 ? 6 : 4,
            'status' => 'tersedia',
            'order_id' => null,
        ];
    }
    return $tables;
}

/**
 * Menandai meja terisi sesuai pesanan demo yang belum dibatalkan.
 */
function demo_sync_tables(array $state): array
{
    foreach ($state['tables'] as $tableIndex => $table) {
        $orderId = null;
        foreach ($state['orders'] as $order) {
            if ((int)($order['meja_id'] ?? 0) !== (int)$table['id']) {
                continue;
            }
            if (in_array($order['status'], ['Lunas', 'Dibatalkan'], true)) {
                continue;
            }
            $orderId = (int)$order['id'];
            break;
        }
        $state['tables'][$tableIndex]['status'] = $orderId !== null ? 'terisi' : 'tersedia';
        $state['tables'][$tableIndex]['order_id'] = $orderId;
    }
    return $state;
}

function demo_initial_users(): array
{
    return [
        ['id' => 1, 'nama' => 'Naji', 'username' => 'admin', 'role' => 'admin', 'is_active' => 1, 'last_login' => date('Y-m-d H:i:s', strtotime('-8 minutes'))],
        ['id' => 2, 'nama' => 'Iyyul', 'username' => 'kasir', 'role' => 'kasir', 'is_active' => 1, 'last_login' => date('Y-m-d H:i:s', strtotime('-12 minutes'))],
        ['id' => 3, 'nama' => 'Raka Pratama', 'username' => 'pelayan', 'role' => 'pelayan', 'is_active' => 1, 'last_login' => date('Y-m-d H:i:s', strtotime('-3 minutes'))],
        ['id' => 4, 'nama' => 'Dimas Kitchen', 'username' => 'dapur', 'role' => 'dapur', 'is_active' => 1, 'last_login' => date('Y-m-d H:i:s', strtotime('-2 minutes'))],
        ['id' => 5, 'nama' => 'Rasa Rumahan', 'username' => 'umkm', 'role' => 'umkm', 'is_active' => 1, 'last_login' => date('Y-m-d H:i:s', strtotime('-1 day'))],
    ];
}

function demo_initial_partners(): array
{
    return [
        ['id' => 1, 'user_id' => 5, 'nama_usaha' => 'Rasa Rumahan', 'pemilik' => 'Nadia Putri', 'kontak' => '0812 3456 7890', 'komisi_persen' => 10, 'status' => 'aktif', 'produk' => 1, 'terjual' => 14, 'penjualan' => 252000, 'komisi' => 25200],
        ['id' => 2, 'user_id' => null, 'nama_usaha' => 'Bumi Snack', 'pemilik' => 'Fajar Nugroho', 'kontak' => '0813 9087 6543', 'komisi_persen' => 12, 'status' => 'aktif', 'produk' => 1, 'terjual' => 11, 'penjualan' => 165000, 'komisi' => 19800],
        ['id' => 3, 'user_id' => null, 'nama_usaha' => 'Sari Buah', 'pemilik' => 'Lina Wulandari', 'kontak' => '0857 4432 1109', 'komisi_persen' => 10, 'status' => 'pending', 'produk' => 1, 'terjual' => 0, 'penjualan' => 0, 'komisi' => 0],
    ];
}

function demo_initial_logs(): array
{
    return [
        ['id' => 1, 'pesanan_kode' => 'ORD-20260925-0107', 'user_nama' => 'Iyyul', 'aksi' => 'Bayar pesanan', 'status_lama' => 'Menunggu Bayar', 'status_baru' => 'Lunas', 'created_at' => date('Y-m-d H:i:s', strtotime('-16 minutes'))],
        ['id' => 2, 'pesanan_kode' => 'ORD-20260925-0101', 'user_nama' => 'Raka Pratama', 'aksi' => 'Ubah status', 'status_lama' => 'Siap Saji', 'status_baru' => 'Menunggu Bayar', 'created_at' => date('Y-m-d H:i:s', strtotime('-24 minutes'))],
        ['id' => 3, 'pesanan_kode' => 'ORD-20260925-0104', 'user_nama' => 'Raka Pratama', 'aksi' => 'Buat pesanan', 'status_lama' => '-', 'status_baru' => 'Baru', 'created_at' => date('Y-m-d H:i:s', strtotime('-31 minutes'))],
        ['id' => 4, 'pesanan_kode' => 'ORD-20260925-0106', 'user_nama' => 'Dimas Kitchen', 'aksi' => 'Ubah status', 'status_lama' => 'Baru', 'status_baru' => 'Diterima', 'created_at' => date('Y-m-d H:i:s', strtotime('-42 minutes'))],
    ];
}

function demo_initial_orders(): array
{
    $menus = demo_initial_menus();
    $item = static function (int $menuId, int $qty = 1, string $note = '') use ($menus): array {
        $menu = $menus[$menuId - 1] ?? $menus[0];
        return [
            'id' => 900 + $menuId,
            'menu_id' => $menu['id'],
            'kode' => $menu['kode'],
            'nama' => $menu['nama'],
            'qty' => $qty,
            'harga_satuan' => $menu['harga'],
            'subtotal' => $menu['harga'] * $qty,
            'catatan_item' => $note,
            'status_item' => $menu['tipe'] === 'umkm' ? 'Siap Saji' : 'Baru',
            'tipe' => $menu['tipe'],
            'mitra' => $menu['mitra'],
            'foto' => $menu['foto'],
        ];
    };
    $orders = [];
    // Alur linear: Baru -> Diterima -> Diproses -> Siap Saji -> Menunggu Bayar -> Lunas
    // [id, kode, meja, tipe, pelanggan, status, total, catatan, menit, item, pembayaran]
    $rows = [
        [101, 'ORD-20260925-0101', 7, 'dine_in', 'Bu Sari Wijaya', 'Menunggu Bayar', 50000, 'Tolong es sedikit lebih banyak.', 24, [[1, 1, ''], [4, 1, '']], null],
        [102, 'ORD-20260925-0102', 3, 'dine_in', 'Kak Reza', 'Menunggu Bayar', 96000, 'Satu tanpa gula.', 18, [[1, 1, ''], [2, 1, ''], [6, 2, '']], null],
        [103, 'ORD-20260925-0103', 5, 'dine_in', 'Ibu Nur', 'Lunas', 60000, '', 52, [[3, 1, ''], [7, 2, '']], ['id' => 501, 'nomor_struk' => 'STR-20260925-0001', 'metode' => 'qris', 'jumlah_bayar' => 60000, 'kembalian' => 6000, 'waktu_bayar' => date('Y-m-d H:i:s', strtotime('-48 minutes'))]],
        [104, 'ORD-20260925-0104', 2, 'dine_in', 'Andi Saputra', 'Baru', 60000, '', 31, [[1, 1, ''], [5, 1, '']], null],
        [105, 'ORD-20260925-0105', 9, 'dine_in', 'Bu Tuti Aminah', 'Diproses', 112000, 'Pedas level 2.', 40, [[2, 1, ''], [5, 2, 'Pedas level 2'], [8, 1, '']], null],
        [106, 'ORD-20260925-0106', null, 'takeaway', 'Pak Hendra', 'Diterima', 30000, 'Bungkus rapat.', 42, [[3, 1, '']], null],
        [107, 'ORD-20260925-0107', 1, 'dine_in', 'Dewi Lestari', 'Lunas', 78000, '', 70, [[1, 2, ''], [4, 1, '']], ['id' => 502, 'nomor_struk' => 'STR-20260925-0002', 'metode' => 'tunai', 'jumlah_bayar' => 100000, 'kembalian' => 32000, 'waktu_bayar' => date('Y-m-d H:i:s', strtotime('-66 minutes'))]],
    ];

    // Tahap masak tiap pesanan. Item UMKM selalu siap saji sehingga pesanan
    // di status 'Menunggu Bayar'/'Lunas' tidak muncul di antrian dapur.
    $tahapMasak = [
        101 => 'Siap Saji', 102 => 'Siap Saji', 103 => 'Siap Saji',
        104 => 'Baru', 105 => 'Diproses', 106 => 'Diterima', 107 => 'Siap Saji',
    ];

    foreach ($rows as [$id, $kode, $meja, $tipe, $pelanggan, $status, $total, $catatan, $menit, $items, $payment]) {
        $detail = [];
        foreach ($items as [$menuId, $qty, $note]) {
            $built = $item($menuId, $qty, $note);
            if ($built['tipe'] !== 'umkm') {
                $built['status_item'] = $tahapMasak[$id];
            }
            $detail[] = $built;
        }
        $orders[] = [
            'id' => $id,
            'kode_pesanan' => $kode,
            'meja_id' => $meja,
            'meja_nomor' => $meja ? str_pad((string)$meja, 2, '0', STR_PAD_LEFT) : null,
            'meja' => $meja ? str_pad((string)$meja, 2, '0', STR_PAD_LEFT) : null,
            'tipe_pesanan' => $tipe,
            'pelayan' => 'Raka Pratama',
            'pelayan_id' => 3,
            'nama_pelanggan' => $pelanggan,
            'status' => $status,
            'total' => $total,
            'catatan' => $catatan,
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . $menit . ' minutes')),
            'items' => $detail,
            'payment' => $payment,
        ];
    }
    return $orders;
}

function demo_initial_state(): array
{
    $state = [
        'menus' => demo_initial_menus(),
        'tables' => demo_initial_tables(),
        'users' => demo_initial_users(),
        'partners' => demo_initial_partners(),
        'orders' => demo_initial_orders(),
        'logs' => demo_initial_logs(),
    ];
    return demo_sync_tables($state);
}

function demo_state_file(): string
{
    return __DIR__ . '/../storage/demo_state.json';
}

function demo_state(): array
{
    $file = demo_state_file();
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['menus'], $decoded['orders'])) {
            $decoded = demo_backfill_pelanggan($decoded);
            $_SESSION['demo_data'] = $decoded;
            return $decoded;
        }
    }
    if (!isset($_SESSION['demo_data']) || !is_array($_SESSION['demo_data'])) {
        $_SESSION['demo_data'] = demo_initial_state();
    }
    save_demo_state($_SESSION['demo_data']);
    return $_SESSION['demo_data'];
}

/**
 * State demo lama belum punya kolom nama_pelanggan. Isi otomatis supaya
 * tampilan dapur/kasir tidak kosong tanpa harus mereset data demo.
 */
function demo_backfill_pelanggan(array $state): array
{
    $fallbacks = ['Bu Sari Wijaya', 'Kak Reza', 'Ibu Nur', 'Andi Saputra', 'Bu Tuti Aminah', 'Pak Hendra', 'Dewi Lestari', 'Rina Kusuma', 'Bang Fajar', 'Mba Lina'];
    $changed = false;
    foreach ($state['orders'] as $index => $order) {
        if (trim((string)($order['nama_pelanggan'] ?? '')) !== '') {
            continue;
        }
        $state['orders'][$index]['nama_pelanggan'] = $fallbacks[$index % count($fallbacks)];
        $changed = true;
    }
    if ($changed) {
        save_demo_state($state);
    }
    return $state;
}

function save_demo_state(array $state): void
{
    $_SESSION['demo_data'] = $state;
    $file = demo_state_file();
    $folder = dirname($file);
    if (!is_dir($folder)) {
        @mkdir($folder, 0775, true);
    }
    @file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function demo_menus(): array
{
    return demo_state()['menus'] ?? [];
}

function demo_tables(): array
{
    return demo_state()['tables'] ?? [];
}

function demo_users(): array
{
    return demo_state()['users'] ?? [];
}

function demo_partners(): array
{
    $state = demo_state();
    $partners = $state['partners'] ?? [];
    $users = $state['users'] ?? [];

    // Samakan dengan mode database: pemilik yang tampil memakai nama akun
    // mitra bila tertaut ke user, kolom pemilik bebas dipakai sebagai
    // cadangan. Dengan begitu rename user langsung tercermin.
    foreach ($partners as $index => $partner) {
        $owner = null;
        foreach ($users as $user) {
            if ((int)($user['id'] ?? 0) > 0 && (int)($user['id']) === (int)($partner['user_id'] ?? 0)) {
                $owner = $user;
                break;
            }
        }
        $partners[$index]['pemilik_usaha'] = $partner['pemilik'] ?? null;
        $partners[$index]['nama_akun'] = $owner['nama'] ?? null;
        $partners[$index]['username'] = $owner['username'] ?? null;
        $partners[$index]['pemilik'] = $owner['nama'] ?? ($partner['pemilik'] ?? null);
    }
    return $partners;
}

function demo_orders(): array
{
    return demo_state()['orders'] ?? [];
}

function demo_logs(): array
{
    return demo_state()['logs'] ?? [];
}

/**
 * Pesan singkat untuk setiap perpindahan status, dipakai di log dan notifikasi.
 */
function demo_status_message(string $newStatus): string
{
    return [
        'Diterima' => 'Pesanan diterima dapur.',
        'Diproses' => 'Pesanan sedang dimasak.',
        'Siap Saji' => 'Pesanan siap saji, siap diantar.',
        'Menunggu Bayar' => 'Pesanan diantar, menunggu pembayaran di kasir.',
    ][$newStatus] ?? ('Status pesanan diperbarui menjadi ' . status_label($newStatus) . '.');
}

function demo_order_by_id(int|string $id): ?array
{
    foreach (demo_orders() as $order) {
        if ((string)$order['id'] === (string)$id) {
            return $order;
        }
    }
    return null;
}

function demo_menu_by_id(int|string $id): ?array
{
    foreach (demo_menus() as $menu) {
        if ((string)$menu['id'] === (string)$id) {
            return $menu;
        }
    }
    return null;
}

function demo_categories(): array
{
    return [
        ['id' => 1, 'nama' => 'Minuman'],
        ['id' => 2, 'nama' => 'Makanan'],
        ['id' => 3, 'nama' => 'Produk UMKM'],
    ];
}

function demo_dashboard_data(): array
{
    $orders = demo_orders();
    $paid = array_values(array_filter($orders, static fn(array $order): bool => $order['status'] === 'Lunas'));
    $active = array_values(array_filter($orders, static fn(array $order): bool => !in_array($order['status'], ['Lunas', 'Dibatalkan'], true)));
    $ready = array_values(array_filter($orders, static fn(array $order): bool => $order['status'] === 'Siap Saji'));
    $waiting = array_values(array_filter($orders, static fn(array $order): bool => $order['status'] === 'Menunggu Bayar'));
    $umkmSales = 0;
    $umkmItems = 0;
    foreach ($paid as $order) {
        foreach ($order['items'] as $item) {
            if ($item['tipe'] === 'umkm') {
                $umkmSales += (int)$item['subtotal'];
                $umkmItems += (int)$item['qty'];
            }
        }
    }
    $revenue = array_sum(array_map(static fn(array $order): int => (int)$order['total'], $paid));
    $chart = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' days'));
        $label = date('d M', strtotime($day));
        $value = 0;
        foreach ($paid as $order) {
            if (date('Y-m-d', strtotime($order['created_at'])) === $day) {
                $value += (int)$order['total'];
            }
        }
        if ($i === 1) {
            $value = 286000;
        }
        $chart[] = ['label' => $label, 'value' => $value];
    }
    $topMenus = demo_menus();
    usort($topMenus, static fn(array $a, array $b): int => ($b['terlaris'] ?? 0) <=> ($a['terlaris'] ?? 0));
    $lowStock = array_values(array_filter(demo_menus(), static fn(array $menu): bool => (int)$menu['stok'] <= 8));
    return [
        'omzet' => $revenue,
        'transaksi' => count($paid),
        'active_orders' => count($active),
        'ready_orders' => count($ready),
        'waiting_orders' => count($waiting),
        'queue_orders' => count(array_filter($active, static fn(array $order): bool => in_array($order['status'], ['Baru', 'Diterima', 'Diproses'], true))),
        'umkm_sales' => $umkmSales,
        'umkm_items' => $umkmItems,
        'avg_transaction' => $paid ? (int)round($revenue / count($paid)) : 0,
        'chart' => $chart,
        'recent_orders' => array_slice($orders, 0, 6),
        'top_menus' => array_slice($topMenus, 0, 5),
        'low_stock' => $lowStock,
        'partners' => demo_partners(),
    ];
}

function demo_add_log(string $code, string $user, string $action, string $old = '-', string $new = '-'): void
{
    $state = demo_state();
    array_unshift($state['logs'], [
        'id' => (int)(($state['logs'][0]['id'] ?? 0) + 1),
        'pesanan_kode' => $code,
        'user_nama' => $user,
        'aksi' => $action,
        'status_lama' => $old,
        'status_baru' => $new,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    save_demo_state($state);
}

function demo_add_order(array $payload): ?array
{
    $state = demo_state();
    $rawItems = $payload['items'] ?? [];
    if (!is_array($rawItems) || !$rawItems) {
        return null;
    }
    $items = [];
    $total = 0;
    foreach ($rawItems as $raw) {
        $menu = demo_menu_by_id((int)($raw['menu_id'] ?? 0));
        $qty = max(1, min(99, (int)($raw['qty'] ?? 1)));
        if (!$menu || !(int)$menu['is_tersedia']) {
            continue;
        }
        $subtotal = (int)$menu['harga'] * $qty;
        $total += $subtotal;
        $items[] = [
            'id' => random_int(1000, 9999),
            'menu_id' => $menu['id'],
            'kode' => $menu['kode'],
            'nama' => $menu['nama'],
            'qty' => $qty,
            'harga_satuan' => $menu['harga'],
            'subtotal' => $subtotal,
            'catatan_item' => trim((string)($raw['catatan_item'] ?? '')),
            'status_item' => $menu['tipe'] === 'umkm' ? 'Siap Saji' : 'Baru',
            'tipe' => $menu['tipe'],
            'mitra' => $menu['mitra'],
            'foto' => $menu['foto'],
        ];
        foreach ($state['menus'] as $index => $storedMenu) {
            if ((int)$storedMenu['id'] === (int)$menu['id']) {
                $state['menus'][$index]['stok'] = max(0, (int)$storedMenu['stok'] - $qty);
            }
        }
    }
    if (!$items) {
        return null;
    }
    // Pesanan tanpa item cafe (produk UMKM) siap saji, tidak perlu dimasak.
    $perluDapur = (bool)array_filter($items, static fn(array $item): bool => $item['tipe'] === 'cafe');
    $nextId = (int)(max(array_map(static fn(array $order): int => (int)$order['id'], $state['orders'])) + 1);
    $order = [
        'id' => $nextId,
        'kode_pesanan' => 'ORD-' . date('Ymd') . '-' . str_pad((string)($nextId - 100), 4, '0', STR_PAD_LEFT),
        'meja_id' => !empty($payload['meja_id']) ? (int)$payload['meja_id'] : null,
        'meja_nomor' => null,
        'meja' => null,
        'tipe_pesanan' => ($payload['tipe_pesanan'] ?? 'dine_in') === 'takeaway' ? 'takeaway' : 'dine_in',
        'pelayan' => 'Raka Pratama',
        'pelayan_id' => 3,
        'nama_pelanggan' => sanitize_pelanggan($payload['nama_pelanggan'] ?? '', 80) ?: 'Pelanggan Umum',
        // Pesanan baru masuk antrian dapur; kasir menagih setelah pelayan mengantar.
        'status' => $perluDapur ? 'Baru' : 'Siap Saji',
        'total' => $total,
        'catatan' => trim((string)($payload['catatan'] ?? '')),
        'created_at' => date('Y-m-d H:i:s'),
        'items' => $items,
        'payment' => null,
    ];
    if ($order['meja_id']) {
        foreach ($state['tables'] as $index => $table) {
            if ((int)$table['id'] === (int)$order['meja_id']) {
                $order['meja_nomor'] = $table['nomor'];
                $order['meja'] = $table['nomor'];
                $state['tables'][$index]['status'] = 'terisi';
                $state['tables'][$index]['order_id'] = $nextId;
            }
        }
    }
    $state['orders'][] = $order;
    save_demo_state(demo_sync_tables($state));
    demo_add_log($order['kode_pesanan'], 'Raka Pratama', 'Buat pesanan', '-', (string)$order['status']);
    return $order;
}

/**
 * Memajukan status pesanan demo satu tahap sesuai alur linear.
 * Status pembayaran (Lunas) hanya lewat demo_process_payment.
 */
function demo_advance_order(int $orderId, string $newStatus, string $userName = 'Dimas Kitchen'): bool
{
    $state = demo_state();
    $transisi = [
        'Baru' => ['Diterima', 'Dibatalkan'],
        'Diterima' => ['Diproses', 'Dibatalkan'],
        'Diproses' => ['Siap Saji', 'Dibatalkan'],
        'Siap Saji' => ['Menunggu Bayar', 'Dibatalkan'],
        'Menunggu Bayar' => ['Dibatalkan'],
        'Lunas' => [],
        'Dibatalkan' => [],
    ];
    if ($newStatus === 'Dibatalkan') {
        return demo_cancel_order($orderId, $userName);
    }
    foreach ($state['orders'] as $index => $order) {
        if ((int)$order['id'] !== $orderId) {
            continue;
        }
        if (!in_array($newStatus, $transisi[$order['status']] ?? [], true)) {
            return false;
        }
        $old = (string)$order['status'];
        $state['orders'][$index]['status'] = $newStatus;
        foreach ($state['orders'][$index]['items'] as $itemIndex => $item) {
            if ($item['tipe'] !== 'umkm') {
                $state['orders'][$index]['items'][$itemIndex]['status_item'] = $newStatus;
            }
        }
        save_demo_state(demo_sync_tables($state));
        demo_add_log($order['kode_pesanan'], $userName, 'Ubah status', $old, $newStatus);
        return true;
    }
    return false;
}

/**
 * Membatalkan pesanan di mode demo: kembalikan stok menu dan lepas meja.
 */
function demo_cancel_order(int $orderId, string $userName = ' Iyyul'): bool
{
    $state = demo_state();
    foreach ($state['orders'] as $index => $order) {
        if ((int)$order['id'] !== $orderId) {
            continue;
        }
        if (in_array($order['status'], ['Lunas', 'Dibatalkan'], true)) {
            return false;
        }
        foreach ($order['items'] as $item) {
            foreach ($state['menus'] as $menuIndex => $menu) {
                if ((int)$menu['id'] === (int)$item['menu_id']) {
                    $state['menus'][$menuIndex]['stok'] = (int)$menu['stok'] + (int)$item['qty'];
                }
            }
        }
        foreach ($state['orders'][$index]['items'] as $itemIndex => $item) {
            $state['orders'][$index]['items'][$itemIndex]['status_item'] = 'Dibatalkan';
        }
        $state['orders'][$index]['status'] = 'Dibatalkan';
        foreach ($state['tables'] as $tableIndex => $table) {
            if ((int)$table['id'] === (int)($order['meja_id'] ?? 0)) {
                $state['tables'][$tableIndex]['status'] = 'tersedia';
                $state['tables'][$tableIndex]['order_id'] = null;
            }
        }
        save_demo_state(demo_sync_tables($state));
        demo_add_log($order['kode_pesanan'], $userName, 'Batalkan pesanan', $order['status'], 'Dibatalkan');
        return true;
    }
    return false;
}

function demo_update_order(int $orderId, array $payload): bool
{
    $state = demo_state();
    foreach ($state['orders'] as $index => $order) {
        if ((int)$order['id'] !== $orderId) {
            continue;
        }
        // Hanya pesan yang belum masuk proses dapur yang bisa diedit.
        if (!in_array($order['status'], ['Baru', 'Diterima'], true)) {
            return false;
        }
        foreach ($order['items'] as $oldItem) {
            foreach ($state['menus'] as $menuIndex => $menu) {
                if ((int)$menu['id'] === (int)$oldItem['menu_id']) {
                    $state['menus'][$menuIndex]['stok'] = (int)$menu['stok'] + (int)$oldItem['qty'];
                }
            }
        }
        $items = [];
        $total = 0;
        foreach (($payload['items'] ?? []) as $raw) {
            $menu = demo_menu_by_id((int)($raw['menu_id'] ?? 0));
            $qty = max(1, min(99, (int)($raw['qty'] ?? 1)));
            if (!$menu || (int)$menu['stok'] < $qty) {
                return false;
            }
            $subtotal = (int)$menu['harga'] * $qty;
            $total += $subtotal;
            $items[] = [
                'id' => random_int(1000, 9999), 'menu_id' => $menu['id'], 'kode' => $menu['kode'], 'nama' => $menu['nama'],
                'qty' => $qty, 'harga_satuan' => $menu['harga'], 'subtotal' => $subtotal, 'catatan_item' => trim((string)($raw['catatan_item'] ?? '')),
                'status_item' => $menu['tipe'] === 'umkm' ? 'Siap Saji' : $order['status'], 'tipe' => $menu['tipe'], 'mitra' => $menu['mitra'], 'foto' => $menu['foto'],
            ];
            foreach ($state['menus'] as $menuIndex => $storedMenu) {
                if ((int)$storedMenu['id'] === (int)$menu['id']) {
                    $state['menus'][$menuIndex]['stok'] = max(0, (int)$storedMenu['stok'] - $qty);
                }
            }
        }
        if (!$items) {
            return false;
        }
        $newTable = !empty($payload['meja_id']) ? (int)$payload['meja_id'] : null;
        foreach ($state['tables'] as $tableIndex => $table) {
            if ((int)$table['id'] === (int)($order['meja_id'] ?? 0)) {
                $state['tables'][$tableIndex]['status'] = 'tersedia';
                $state['tables'][$tableIndex]['order_id'] = null;
            }
        }
        $tableNumber = null;
        if ($newTable) {
            foreach ($state['tables'] as $tableIndex => $table) {
                if ((int)$table['id'] === $newTable && $table['status'] === 'tersedia') {
                    $state['tables'][$tableIndex]['status'] = 'terisi';
                    $state['tables'][$tableIndex]['order_id'] = $orderId;
                    $tableNumber = $table['nomor'];
                }
            }
        }
        $state['orders'][$index]['items'] = $items;
        $state['orders'][$index]['total'] = $total;
        $state['orders'][$index]['tipe_pesanan'] = ($payload['tipe_pesanan'] ?? 'dine_in') === 'takeaway' ? 'takeaway' : 'dine_in';
        $state['orders'][$index]['meja_id'] = $newTable;
        $state['orders'][$index]['meja_nomor'] = $tableNumber;
        $state['orders'][$index]['meja'] = $tableNumber;
        $state['orders'][$index]['catatan'] = trim((string)($payload['catatan'] ?? ''));
        $state['orders'][$index]['nama_pelanggan'] = sanitize_pelanggan($payload['nama_pelanggan'] ?? ($order['nama_pelanggan'] ?? ''), 80) ?: ($order['nama_pelanggan'] ?? 'Pelanggan Umum');
        save_demo_state(demo_sync_tables($state));
        demo_add_log($order['kode_pesanan'], 'Raka Pratama', 'Ubah pesanan', $order['status'], $state['orders'][$index]['status']);
        return true;
    }
    return false;
}

function demo_delete_order(int $orderId): bool
{
    $state = demo_state();
    foreach ($state['orders'] as $index => $order) {
        if ((int)$order['id'] !== $orderId) {
            continue;
        }
        // Hanya pesan sebelum masuk proses dapur yang bisa dibatalkan pelayan.
        if (!in_array($order['status'], ['Baru', 'Diterima'], true)) {
            return false;
        }
        foreach ($order['items'] as $item) {
            foreach ($state['menus'] as $menuIndex => $menu) {
                if ((int)$menu['id'] === (int)$item['menu_id']) {
                    $state['menus'][$menuIndex]['stok'] = (int)$menu['stok'] + (int)$item['qty'];
                }
            }
        }
        if ($order['meja_id']) {
            foreach ($state['tables'] as $tableIndex => $table) {
                if ((int)$table['id'] === (int)$order['meja_id']) {
                    $state['tables'][$tableIndex]['status'] = 'tersedia';
                    $state['tables'][$tableIndex]['order_id'] = null;
                }
            }
        }
        array_splice($state['orders'], $index, 1);
        save_demo_state(demo_sync_tables($state));
        demo_add_log($order['kode_pesanan'], 'Raka Pratama', 'Batalkan pesanan', $order['status'], 'Dibatalkan');
        return true;
    }
    return false;
}

function demo_process_payment(int $orderId, string $method, float $paid, string $userName = 'Iyyul'): ?array
{
    $state = demo_state();
    foreach ($state['orders'] as $index => $order) {
        if ((int)$order['id'] !== $orderId) {
            continue;
        }
        if (in_array($order['status'], ['Lunas', 'Dibatalkan'], true)) {
            return null;
        }
        // Kasir hanya menagih pesanan yang sudah diantar ke meja.
        if ($order['status'] !== 'Menunggu Bayar') {
            return null;
        }
        $total = (float)$order['total'];
        $paid = round($paid, 2);
        if ($paid < $total) {
            return null;
        }
        $payment = [
            'id' => (int)(max(array_map(static fn(array $row): int => (int)($row['payment']['id'] ?? 0), $state['orders'])) + 1),
            'nomor_struk' => 'STR-' . date('Ymd') . '-' . str_pad((string)(count($state['orders']) + 1), 4, '0', STR_PAD_LEFT),
            'metode' => in_array($method, ['tunai', 'qris', 'transfer'], true) ? $method : 'tunai',
            'jumlah_bayar' => $paid,
            'kembalian' => max(0, $paid - $total),
            'waktu_bayar' => date('Y-m-d H:i:s'),
        ];
        $state['orders'][$index]['payment'] = $payment;
        $state['orders'][$index]['status'] = 'Lunas';
        foreach ($state['orders'][$index]['items'] as $itemIndex => $item) {
            $state['orders'][$index]['items'][$itemIndex]['status_item'] = 'Siap Saji';
        }
        // Pelanggan sudah bayar: meja siap dipakai pelanggan berikutnya.
        save_demo_state(demo_sync_tables($state));
        demo_add_log($order['kode_pesanan'], $userName, 'Bayar pesanan', $order['status'], 'Lunas');
        return $state['orders'][$index];
    }
    return null;
}

function demo_add_menu(array $payload): ?array
{
    $state = demo_state();
    $name = trim((string)($payload['nama'] ?? ''));
    if ($name === '') {
        return null;
    }
    $type = ($payload['tipe'] ?? 'cafe') === 'umkm' ? 'umkm' : 'cafe';
    $category = (int)($payload['kategori_id'] ?? 1);
    $categoryName = $category === 3 ? 'Produk UMKM' : ($category === 2 ? 'Makanan' : 'Minuman');
    $nextId = (int)(max(array_map(static fn(array $menu): int => (int)$menu['id'], $state['menus'])) + 1);
    $menu = [
        'id' => $nextId,
        'kode' => (string)($payload['kode'] ?? (($type === 'umkm' ? 'UMKM-' : 'MKN-') . str_pad((string)$nextId, 3, '0', STR_PAD_LEFT))),
        'nama' => $name,
        'kategori_id' => $category,
        'kategori' => $categoryName,
        'harga' => max(0, (int)($payload['harga'] ?? 0)),
        'stok' => max(0, (int)($payload['stok'] ?? 0)),
        'tipe' => $type,
        'mitra_id' => $type === 'umkm' ? (int)($payload['mitra_id'] ?: null) : null,
        'mitra' => null,
        'is_tersedia' => 1,
        'terlaris' => 0,
        'deskripsi' => trim((string)($payload['deskripsi'] ?? '')),
        'foto' => trim((string)($payload['foto'] ?? 'assets/img/menu/default.svg')),
    ];
    if ($type === 'umkm' && $menu['mitra_id']) {
        foreach ($state['partners'] as $partner) {
            if ((int)$partner['id'] === (int)$menu['mitra_id']) {
                $menu['mitra'] = $partner['nama_usaha'];
            }
        }
    }
    $state['menus'][] = $menu;
    save_demo_state($state);
    return $menu;
}

function demo_update_menu(int $id, array $payload): bool
{
    $state = demo_state();
    foreach ($state['menus'] as $index => $menu) {
        if ((int)$menu['id'] !== $id) {
            continue;
        }
        $state['menus'][$index]['nama'] = trim((string)($payload['nama'] ?? $menu['nama']));
        $state['menus'][$index]['kode'] = trim((string)($payload['kode'] ?? $menu['kode']));
        $state['menus'][$index]['harga'] = max(0, (int)($payload['harga'] ?? $menu['harga']));
        $state['menus'][$index]['stok'] = max(0, (int)($payload['stok'] ?? $menu['stok']));
        $state['menus'][$index]['is_tersedia'] = isset($payload['is_tersedia']) ? 1 : 0;
        $state['menus'][$index]['deskripsi'] = trim((string)($payload['deskripsi'] ?? $menu['deskripsi']));
        save_demo_state($state);
        return true;
    }
    return false;
}

function demo_delete_menu(int $id): bool
{
    $state = demo_state();
    foreach (demo_orders() as $order) {
        foreach ($order['items'] as $item) {
            if ((int)$item['menu_id'] === $id) {
                return false;
            }
        }
    }
    foreach ($state['menus'] as $index => $menu) {
        if ((int)$menu['id'] === $id) {
            array_splice($state['menus'], $index, 1);
            save_demo_state($state);
            return true;
        }
    }
    return false;
}

function demo_add_user(array $payload): ?array
{
    $state = demo_state();
    $username = trim((string)($payload['username'] ?? ''));
    $name = trim((string)($payload['nama'] ?? ''));
    if (!$username || !$name) {
        return null;
    }
    foreach ($state['users'] as $user) {
        if (strcasecmp($user['username'], $username) === 0) {
            return null;
        }
    }
    $nextId = (int)(max(array_map(static fn(array $user): int => (int)$user['id'], $state['users'])) + 1);
    $user = ['id' => $nextId, 'nama' => $name, 'username' => $username, 'role' => in_array($payload['role'] ?? '', ['admin', 'kasir', 'pelayan', 'dapur', 'umkm'], true) ? $payload['role'] : 'pelayan', 'is_active' => isset($payload['is_active']) ? 1 : 0, 'last_login' => null];
    $state['users'][] = $user;
    save_demo_state($state);
    return $user;
}

function demo_update_user(int $id, array $payload): bool
{
    $state = demo_state();
    foreach ($state['users'] as $index => $user) {
        if ((int)$user['id'] !== $id) {
            continue;
        }
        $oldName = (string)($user['nama'] ?? '');
        $state['users'][$index]['nama'] = trim((string)($payload['nama'] ?? $user['nama']));
        $state['users'][$index]['username'] = trim((string)($payload['username'] ?? $user['username']));
        $state['users'][$index]['role'] = in_array($payload['role'] ?? '', ['admin', 'kasir', 'pelayan', 'dapur', 'umkm'], true) ? $payload['role'] : $user['role'];
        $state['users'][$index]['is_active'] = isset($payload['is_active']) ? 1 : 0;
        $newName = (string)$state['users'][$index]['nama'];

        // Demo menyimpan nama pengguna sebagai teks di beberapa tempat.
        // Samakan dengan mode database: seluruh jejak nama lama diganti.
        if ($newName !== '' && $oldName !== '' && $newName !== $oldName) {
            foreach ($state['orders'] as $orderIndex => $order) {
                if ((int)($order['pelayan_id'] ?? 0) === $id) {
                    $state['orders'][$orderIndex]['pelayan'] = $newName;
                }
            }
            foreach ($state['logs'] as $logIndex => $log) {
                if (($log['user_nama'] ?? '') === $oldName) {
                    $state['logs'][$logIndex]['user_nama'] = $newName;
                }
            }
            foreach ($state['partners'] as $partnerIndex => $partner) {
                if ((int)($partner['user_id'] ?? 0) === $id) {
                    $state['partners'][$partnerIndex]['pemilik'] = $newName;
                }
            }
        }
        save_demo_state($state);
        return true;
    }
    return false;
}

function demo_delete_user(int $id): bool
{
    $state = demo_state();
    foreach ($state['users'] as $index => $user) {
        if ((int)$user['id'] === $id) {
            array_splice($state['users'], $index, 1);
            save_demo_state($state);
            return true;
        }
    }
    return false;
}

function demo_update_partner(int $id, array $payload): bool
{
    $state = demo_state();
    foreach ($state['partners'] as $index => $partner) {
        if ((int)$partner['id'] !== $id) {
            continue;
        }
        $state['partners'][$index]['nama_usaha'] = trim((string)($payload['nama_usaha'] ?? $partner['nama_usaha']));
        $state['partners'][$index]['pemilik'] = trim((string)($payload['pemilik'] ?? $partner['pemilik']));
        $state['partners'][$index]['kontak'] = trim((string)($payload['kontak'] ?? $partner['kontak']));
        $state['partners'][$index]['komisi_persen'] = min(50, max(0, (float)($payload['komisi_persen'] ?? $partner['komisi_persen'])));
        $state['partners'][$index]['status'] = in_array($payload['status'] ?? '', ['pending', 'aktif', 'nonaktif'], true) ? (string)$payload['status'] : $partner['status'];
        save_demo_state($state);
        return true;
    }
    return false;
}

function demo_add_partner(array $payload): ?array
{
    $state = demo_state();
    $name = trim((string)($payload['nama_usaha'] ?? ''));
    if (!$name) {
        return null;
    }
    $nextId = (int)(max(array_map(static fn(array $partner): int => (int)$partner['id'], $state['partners'])) + 1);
    $partner = [
        'id' => $nextId,
        'nama_usaha' => $name,
        'pemilik' => trim((string)($payload['pemilik'] ?? '')),
        'kontak' => trim((string)($payload['kontak'] ?? '')),
        'komisi_persen' => min(50, max(0, (float)($payload['komisi_persen'] ?? 10))),
        'status' => in_array($payload['status'] ?? '', ['pending', 'aktif', 'nonaktif'], true) ? $payload['status'] : 'pending',
        'produk' => 0,
        'terjual' => 0,
        'penjualan' => 0,
        'komisi' => 0,
    ];
    $state['partners'][] = $partner;
    save_demo_state($state);
    return $partner;
}
