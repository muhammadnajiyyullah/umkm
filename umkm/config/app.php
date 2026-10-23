<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/koneksi.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 *Mengembalikan base URL aplikasi secara relatif agar dapat dipindahkan
 *diantara folder www tanpa mengubah semua tautan.
 *
 *Base URL dihitung dengan membandingkan folder aplikasi (lokasi file
 *config/app.php) dengan folder skrip yang sedang dijalankan. Cara ini
 *tetap benar meski folder proyek bernama "umkm" sama dengan folder
 *role UMKM, dan saat aplikasi dipasang langsung di document root.
 */
function app_base_url(): string
{
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $urlDir = trim(str_replace('\\', '/', dirname($scriptName)), '/');

    $appRoot = realpath(dirname(__DIR__));
    $scriptFile = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptDir = $scriptFile !== '' ? realpath(dirname($scriptFile)) : false;

    if ($appRoot !== false && $scriptDir !== false) {
        $appRoot = str_replace('\\', '/', $appRoot);
        $scriptDir = str_replace('\\', '/', $scriptDir);
        if (str_starts_with(strtolower($scriptDir . '/'), strtolower($appRoot . '/'))) {
            $relative = trim(substr($scriptDir, strlen($appRoot)), '/');
            $levels = $relative === '' ? 0 : substr_count($relative, '/') + 1;
            $parts = $urlDir === '' ? [] : explode('/', $urlDir);
            if ($levels > 0 && count($parts) >= $levels) {
                $parts = array_slice($parts, 0, count($parts) - $levels);
                $urlDir = implode('/', $parts);
            }
            return $urlDir === '' ? '/' : '/' . $urlDir;
        }
    }

    //Cadangan: folder skrip tidak dapat dipetakan ke folder aplikasi.
    $parts = array_values(array_filter(explode('/', $urlDir)));
    $subfolders = ['admin', 'pelayan', 'kasir', 'dapur', 'umkm', 'api', 'assets', 'layout', 'config', 'database'];
    while (count($parts) > 1 && in_array(end($parts), $subfolders, true)) {
        array_pop($parts);
    }
    $base = implode('/', $parts);
    return $base === '' ? '/' : '/' . $base;
}

function url(string $path = ''): string
{
    $base = app_base_url();
    if ($path === '') {
        return $base === '/' ? '/' : $base . '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return ($base === '/' ? '' : $base) . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#i', $path) ? $path : url($path)));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money(float|int|string|null $amount): string
{
    $amount = (float)($amount ?? 0);
    return 'Rp' . number_format($amount, 0, ',', '.');
}

function compact_money(float|int|string|null $amount): string
{
    $amount = (float)($amount ?? 0);
    if ($amount >= 1000000) {
        return 'Rp' . number_format($amount / 1000000, 1, ',', '.') . 'jt';
    }
    if ($amount >= 1000) {
        return 'Rp' . number_format($amount / 1000, 0, ',', '.') . 'rb';
    }
    return money($amount);
}

function format_date_id(?string $date, bool $withTime = true): string
{
    if (!$date) {
        return '-';
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return '-';
    }
    $months = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];
    $text = date('d', $timestamp) . ' ' . $months[(int)date('n', $timestamp)] . ' ' . date('Y', $timestamp);
    return $withTime ? $text . ', ' . date('H:i', $timestamp) : $text;
}

function relative_time(?string $date): string
{
    if (!$date || !($timestamp = strtotime($date))) {
        return '-';
    }
    $seconds = max(0, time() - $timestamp);
    if ($seconds < 60) {
        return 'Baru saja';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' menit lalu';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' jam lalu';
    }
    return floor($seconds / 86400) . ' hari lalu';
}

function status_class(string $status): string
{
    $map = [
        'baru' => 'new',
        'diterima' => 'received',
        'diproses' => 'processing',
        'siap saji' => 'ready',
        'menunggu bayar' => 'waiting',
        'lunas' => 'paid',
        'dibatalkan' => 'cancelled',
    ];
    return $map[strtolower(trim($status))] ?? 'neutral';
}

function status_label(string $status): string
{
    $labels = [
        'baru' => 'Baru',
        'diterima' => 'Diterima',
        'diproses' => 'Diproses',
        'siap_saji' => 'Siap Saji',
        'siap saji' => 'Siap Saji',
        'menunggu_bayar' => 'Menunggu Bayar',
        'menunggu bayar' => 'Menunggu Bayar',
        'lunas' => 'Lunas',
        'dibatalkan' => 'Dibatalkan',
    ];
    $key = strtolower(trim($status));
    return $labels[$key] ?? ucfirst($status);
}

function role_label(string $role): string
{
    return [
        'admin' => 'Administrator',
        'kasir' => 'Kasir',
        'pelayan' => 'Pelayan',
        'dapur' => 'Dapur',
        'umkm' => 'Mitra UMKM',
    ][$role] ?? ucfirst($role);
}

function role_home(string $role): string
{
    return 'dashboard.php';
}

function order_type_label(string $type): string
{
    return $type === 'takeaway' ? 'Takeaway' : 'Makan di tempat';
}

/**
 * Membersihkan input nama pelanggan: rapikan spasi ganda dan batasi panjang.
 * Nilai kosong dikembalikan apa adanya supaya bisa divalidasi terpisah.
 */
function sanitize_pelanggan(mixed $value, int $maxLength = 80): string
{
    $name = trim((string)($value ?? ''));
    $name = (string)preg_replace('/\s+/u', ' ', $name);
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, $maxLength);
    }
    return substr($name, 0, $maxLength);
}

/**
 * Nama pelanggan siap tampil di seluruh tahap (dapur, kasir, struk).
 */
function pelanggan_name(mixed $value, string $fallback = 'Tanpa nama'): string
{
    $name = sanitize_pelanggan($value);
    return $name !== '' ? $name : $fallback;
}

function order_pelanggan(?array $order, string $fallback = 'Tanpa nama'): string
{
    return pelanggan_name($order['nama_pelanggan'] ?? '', $fallback);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $result = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $result .= function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1);
    }
    return strtoupper($result ?: 'KN');
}

function avatar_color(string $seed): string
{
    $colors = ['sage', 'clay', 'gold', 'blue', 'plum'];
    return $colors[abs(crc32($seed)) % count($colors)];
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function is_demo(): bool
{
    $user = current_user();
    return (bool)($user['demo'] ?? false);
}

/**
 * Putuskan session pengguna dan arahkan ke halaman login.
 *
 * Dipakai ketika akun dinonaktifkan atau dihapus admin, sehingga akses
 * tidak bisa dilanjutkan dengan session lama.
 */
function logout_paksa(): never
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    redirect('index.php');
}

/**
 * Sinkronkan profil di session dengan data terbaru pada tabel users.
 *
 * Nama, username, dan role bisa diubah admin kapan saja. Tanpa
 * penyegaran ini, dashboard tiap role masih menampilkan nama lama sampai
 * pengguna logout lalu login ulang. Sekalian memutus session bila akun
 * dinonaktifkan atau dihapus.
 *
 * Hanya satu query pencarian primary key per request.
 */
function sync_user_session(?PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $session = $_SESSION['user'] ?? null;
    if (!is_array($session) || empty($session['id'])) {
        return;
    }

    if (!$pdo) {
        // Mode demo tanpa database: sinkronkan dari state demo.
        if (!function_exists('demo_users')) {
            return;
        }
        foreach (demo_users() as $demoUser) {
            if ((int)($demoUser['id'] ?? 0) === (int)$session['id']) {
                if ((int)($demoUser['is_active'] ?? 1) !== 1) {
                    logout_paksa();
                }
                $_SESSION['user']['name'] = (string)($demoUser['nama'] ?? $session['name']);
                $_SESSION['user']['username'] = (string)($demoUser['username'] ?? $session['username']);
                $_SESSION['user']['role'] = (string)($demoUser['role'] ?? $session['role']);
                return;
            }
        }
        return;
    }

    try {
        $statement = $pdo->prepare('SELECT id, nama, username, role, is_active FROM users WHERE id = ? LIMIT 1');
        $statement->execute([(int)$session['id']]);
        $row = $statement->fetch();
    } catch (Throwable $exception) {
        error_log('[KOPI NUSA] Sinkronisasi session gagal: ' . $exception->getMessage());
        return;
    }

    if (!$row) {
        logout_paksa();
    }
    if ((int)$row['is_active'] !== 1) {
        logout_paksa();
    }

    $_SESSION['user']['name'] = (string)$row['nama'];
    $_SESSION['user']['username'] = (string)$row['username'];
    $_SESSION['user']['role'] = (string)$row['role'];
}

/**
 * Profil demo statis per role.
 *
 * Nilai id di sini HANYA dipakai ketika database sama sekali tidak
 * tersedia, sehingga tidak ada foreign key yang perlu dipenuhi.
 * Jangan memakai id ini sebagai user_id di database nyata.
 */
function demo_profiles(): array
{
    return [
        'admin' => ['id' => 1, 'nama' => 'Ayu Maharani', 'username' => 'admin'],
        'kasir' => ['id' => 2, 'nama' => 'Bima Saputra', 'username' => 'kasir'],
        'pelayan' => ['id' => 3, 'nama' => 'Raka Pratama', 'username' => 'pelayan'],
        'dapur' => ['id' => 4, 'nama' => 'Dimas Kitchen', 'username' => 'dapur'],
        'umkm' => ['id' => 5, 'nama' => 'Rasa Rumahan', 'username' => 'umkm'],
    ];
}

/**
 * Resolusi identitas untuk login mode demo.
 *
 * Id user demo tidak boleh dikodekan keras karena tabel pesanan,
 * pembayaran, dan log_pesanan memakai foreign key ke users(id).
 * Kalau id tersebut tidak ada di database, setiap penyisipan log gagal
 * dengan errno 1452 dan seluruh perubahan status dibatalkan transaksi.
 *
 * Karena itu, bila database tersedia, akun nyata untuk role tersebut
 * yang dipakai sehingga user_id selalu valid. Profil statis hanya
 * dipakai untuk mode demo tanpa database.
 */
function demo_identity(?PDO $pdo, string $role): array
{
    $profiles = demo_profiles();
    $profile = $profiles[$role] ?? $profiles['admin'];

    if ($pdo) {
        try {
            $statement = $pdo->prepare('SELECT id, nama, username, role FROM users WHERE role = ? AND is_active = 1 ORDER BY id LIMIT 1');
            $statement->execute([$role]);
            $row = $statement->fetch();
            if ($row) {
                return [
                    'id' => (int)$row['id'],
                    'nama' => (string)$row['nama'],
                    'username' => (string)$row['username'],
                    'role' => (string)$row['role'],
                ];
            }
        } catch (Throwable $exception) {
            error_log('[KOPI NUSA] Resolusi user demo gagal: ' . $exception->getMessage());
        }
    }

    return [
        'id' => (int)$profile['id'],
        'nama' => (string)$profile['nama'],
        'username' => (string)$profile['username'],
        'role' => $role,
    ];
}

/**
 * Pastikan id user benar-benar ada di tabel users sebelum dipakai pada
 * kolom ber-foreign key. Kalau tidak ada, kembalikan null supaya log
 * tetap tersimpan tanpa pemilik (kolomnya nullable dengan ON DELETE SET NULL).
 */
function safe_user_id(?PDO $pdo, mixed $userId): ?int
{
    $id = (int)$userId;
    if ($id < 1) {
        return null;
    }
    if (!$pdo) {
        return $id;
    }
    try {
        $statement = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        return $statement->fetchColumn() ? $id : null;
    } catch (Throwable $exception) {
        error_log('[KOPI NUSA] Pengecekan user gagal: ' . $exception->getMessage());
        return null;
    }
}

/**
 * Catat aktivitas pesanan ke log_pesanan.
 *
 * Penulisan log tidak boleh menggagalkan operasi bisnis: kegagalan
 * disimpan ke error log, bukan dilempar ke pengguna.
 */
function catat_aktivitas(
    ?PDO $pdo,
    ?int $pesananId,
    mixed $userId,
    string $aksi,
    ?string $statusLama = null,
    ?string $statusBaru = null,
    ?string $keterangan = null
): bool {
    if (!$pdo) {
        return false;
    }
    try {
        $statement = $pdo->prepare('INSERT INTO log_pesanan (pesanan_id, user_id, aksi, status_lama, status_baru, keterangan) VALUES (?, ?, ?, ?, ?, ?)');
        return $statement->execute([
            $pesananId,
            safe_user_id($pdo, $userId),
            $aksi,
            $statusLama,
            $statusBaru,
            $keterangan,
        ]);
    } catch (Throwable $exception) {
        error_log('[KOPI NUSA] Gagal menulis log_pesanan: ' . $exception->getMessage());
        return false;
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flash(): array
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
    }
}

function db_all(string $sql, array $params = []): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('[KOPI NUSA] Query gagal: ' . $exception->getMessage());
        return [];
    }
}

function db_one(string $sql, array $params = []): ?array
{
    $rows = db_all($sql, $params);
    return $rows[0] ?? null;
}

function db_exec(string $sql, array $params = []): bool
{
    $pdo = db();
    if (!$pdo) {
        return false;
    }
    try {
        $statement = $pdo->prepare($sql);
        return $statement->execute($params);
    } catch (Throwable $exception) {
        error_log('[KOPI NUSA] Eksekusi gagal: ' . $exception->getMessage());
        return false;
    }
}

function db_insert_id(): int
{
    $pdo = db();
    return $pdo ? (int)$pdo->lastInsertId() : 0;
}

/**
 * Pesan aman untuk ditampilkan ke pengguna.
 *
 * PDOException mewarisi RuntimeException, jadi pemeriksaan
 * "instanceof RuntimeException" saja tidak cukup: pesan error SQL mentah
 * ikut tampil ke layar (membocorkan nama tabel dan kolom) dan membuat
 * 원인 masalah sulit dipahami. Exception dari PDO dipisahkan dan
 * dicatat ke error log sebagai gantinya.
 */
function pesan_exception(Throwable $exception, string $fallback): string
{
    if ($exception instanceof PDOException) {
        error_log('[KOPI NUSA] Query gagal: ' . $exception->getMessage());
        return $fallback;
    }
    if ($exception instanceof RuntimeException) {
        return $exception->getMessage();
    }
    error_log('[KOPI NUSA] Error tak terduga: ' . $exception->getMessage());
    return $fallback;
}

function icon(string $name, string $class = ''): string
{
    $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'coffee' => '<path d="M4 8h13v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V8Z"/><path d="M17 10h1.5a2.5 2.5 0 0 1 0 5H17M7 4c0 1 1 1 1 2M11 4c0 1 1 1 1 2M15 4c0 1 1 1 1 2"/>',
        'receipt' => '<path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z"/><path d="M8 8h8M8 12h8M8 16h4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'package' => '<path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.4 1.4-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1 1.56V20h-2v-.5a1.7 1.7 0 0 0-1-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-1.4-1.4.06-.06A1.7 1.7 0 0 0 9.6 15a1.7 1.7 0 0 0-1.56-1H7.5v-2h.54a1.7 1.7 0 0 0 1.56-1A1.7 1.7 0 0 0 9.26 9l-.06-.06 1.4-1.4.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1-1.56V6h2v.38a1.7 1.7 0 0 0 1 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.4 1.4-.06.06A1.7 1.7 0 0 0 19.4 11a1.7 1.7 0 0 0 1.56 1h.54v2h-.54a1.7 1.7 0 0 0-1.56 1Z"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5M9 10h6M9 14h6M9 18h3"/>',
        'wallet' => '<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H19a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 17.5v-11Z"/><path d="M4 8h15.5a2.5 2.5 0 0 1 0 5H16"/><circle cx="16" cy="10.5" r=".7" fill="currentColor" stroke="none"/>',
        'chef' => '<path d="M6 10v10h12V10M4 10h16M8 10a3 3 0 1 1 2-5 4 4 0 0 1 4 0 3 3 0 1 1 2 5"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-5"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/>',
        'search' => '<circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-up' => '<path d="m18 15-6-6-6 6"/>',
        'arrow-down' => '<path d="m6 9 6 6 6-6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'x' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'printer' => '<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6zM18 12h.01"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        'trend' => '<path d="m3 17 6-6 4 4 8-9M15 6h6v6"/>',
        'trend-down' => '<path d="m3 7 6 6 4-4 8 9M15 18h6v-6"/>',
        'alert' => '<path d="M10.3 4.2 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'store' => '<path d="M3 10h18l-2-6H5l-2 6ZM5 10v10h14V10M9 20v-6h6v6M3 10a3 3 0 0 0 5 2 3 3 0 0 0 4 0 3 3 0 0 0 4 0 3 3 0 0 0 5-2"/>',
        'table' => '<path d="M3 7h18M5 7l-2 10M19 7l2 10M5 12h14M5 17h14"/>',
        'edit' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6"/>',
        'eye' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        'filter' => '<path d="M4 5h16l-6 7v5l-4 2v-7L4 5Z"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'more' => '<circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1" fill="currentColor" stroke="none"/>',
        'spark' => '<path d="m12 2 1.5 6.5L20 10l-6.5 1.5L12 18l-1.5-6.5L4 10l6.5-1.5L12 2ZM19 17l.6 2.4L22 20l-2.4.6L19 23l-.6-2.4L16 20l2.4-.6L19 17Z"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M10 5h4M11 18h2"/>',
        'refresh' => '<path d="M20 11a8.1 8.1 0 0 0-14.8-4L3 9M3 4v5h5M4 13a8.1 8.1 0 0 0 14.8 4L21 15m0 5v-5h-5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    ];
    $path = $icons[$name] ?? $icons['grid'];
    return '<svg' . $classAttr . ' width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function nav_items(string $role): array
{
    return [
        'admin' => [
            ['key' => 'dashboard', 'label' => 'Ringkasan', 'icon' => 'grid', 'href' => 'dashboard.php'],
            ['key' => 'menu', 'label' => 'Kelola Menu', 'icon' => 'coffee', 'href' => 'admin/menu.php'],
            ['key' => 'users', 'label' => 'Kelola User', 'icon' => 'users', 'href' => 'admin/user.php'],
            ['key' => 'umkm', 'label' => 'Mitra UMKM', 'icon' => 'package', 'href' => 'admin/umkm.php'],
            ['key' => 'reports', 'label' => 'Laporan Penjualan', 'icon' => 'trend', 'href' => 'admin/laporan.php'],
            ['key' => 'logs', 'label' => 'Log Aktivitas', 'icon' => 'clipboard', 'href' => 'admin/log.php'],
        ],
        'kasir' => [
            ['key' => 'dashboard', 'label' => 'Ringkasan', 'icon' => 'grid', 'href' => 'dashboard.php'],
            ['key' => 'bills', 'label' => 'Daftar Tagihan', 'icon' => 'receipt', 'href' => 'kasir/daftar_tagihan.php'],
            ['key' => 'transactions', 'label' => 'Riwayat Transaksi', 'icon' => 'wallet', 'href' => 'kasir/transaksi.php'],
        ],
        'pelayan' => [
            ['key' => 'dashboard', 'label' => 'Ringkasan', 'icon' => 'grid', 'href' => 'dashboard.php'],
            ['key' => 'new-order', 'label' => 'Pesanan Baru', 'icon' => 'plus', 'href' => 'pelayan/pesanan_baru.php'],
            ['key' => 'orders', 'label' => 'Pesanan Aktif', 'icon' => 'receipt', 'href' => 'pelayan/pesanan_aktif.php'],
        ],
        'dapur' => [
            ['key' => 'dashboard', 'label' => 'Ringkasan', 'icon' => 'grid', 'href' => 'dashboard.php'],
            ['key' => 'queue', 'label' => 'Antrian Dapur', 'icon' => 'chef', 'href' => 'dapur/antrian.php'],
        ],
        'umkm' => [
            ['key' => 'dashboard', 'label' => 'Ringkasan', 'icon' => 'grid', 'href' => 'dashboard.php'],
            ['key' => 'products', 'label' => 'Produk Saya', 'icon' => 'package', 'href' => 'umkm/produk.php'],
        ],
    ][$role] ?? [];
}

function json_attr(mixed $value): string
{
    return e(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
}

function image_url(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return url('assets/img/menu/default.svg');
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return url($path);
}
