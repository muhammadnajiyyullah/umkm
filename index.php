<?php
declare(strict_types=1);

require_once __DIR__ . '/config/cek_akses.php';

if (isset($_GET['logout'])) {
    logout_paksa();
}

// Sinkronkan profil session dengan data user terbaru (nama/role/aktif).
sync_user_session(db());

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$username = trim((string)($_POST['username'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir tidak valid. Silakan coba lagi.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $demoRequested = trim((string)($_POST['demo_login'] ?? ''));
        $user = null;
        $isDemo = false;
        $pdo = db();

        if ($demoRequested !== '') {
            // Login lewat tombol role. Id user diambil dari tabel users
            // sehingga foreign key pada pesanan/pembayaran/log selalu valid.
            $user = demo_identity($pdo, $demoRequested);
            $isDemo = true;
        } else {
            if ($pdo) {
                try {
                    $statement = $pdo->prepare('SELECT id, nama, username, role, password FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
                    $statement->execute([$username]);
                    $row = $statement->fetch();
                    if ($row && password_verify($password, (string)$row['password'])) {
                        $user = $row;
                    }
                } catch (Throwable $exception) {
                    error_log('[KOPI NUSA] Login database gagal: ' . $exception->getMessage());
                }
            }
            if (!$user && $username !== '' && $password === $username . '123' && array_key_exists($username, demo_profiles())) {
                // Akun demo ketik manual. Fallback ke akun nyata untuk role
                // tersebut supaya id user yang dipakai benar-benar ada.
                $user = demo_identity($pdo, $username);
                $isDemo = true;
            }
        }

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'name' => (string)$user['nama'],
                'username' => (string)$user['username'],
                'role' => (string)$user['role'],
                'demo' => $isDemo,
            ];
            if (!$isDemo && $pdo) {
                try {
                    $statement = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                    $statement->execute([(int)$user['id']]);
                } catch (Throwable $exception) {
                    error_log('[KOPI NUSA] Update login gagal: ' . $exception->getMessage());
                }
            }
            redirect('dashboard.php');
        }
        $error = 'Username atau password tidak sesuai. Gunakan akun demo di bawah untuk menjelajah aplikasi.';
    }
}

$pageTitle = 'Masuk Workspace';
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#202a25">
  <title>Masuk · KOPI NUSA</title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body data-base="<?= e(app_base_url()) ?>" data-csrf="<?= e(csrf_token()) ?>">
<main class="auth-page">
  <div class="auth-shell">
    <section class="auth-visual">
      <div class="auth-brand">
        <span class="brand-mark"><span class="brand-mark-dot"></span><?= icon('coffee') ?></span>
        <span class="brand-copy"><strong>KOPI<span>NUSA</span></strong><small>CAFE · COFFEE · COMMUNITY</small></span>
      </div>
      <div class="auth-cup-art" aria-hidden="true">
        <svg viewBox="0 0 140 140" fill="none"><circle cx="70" cy="70" r="59" stroke="#f3e6b8" stroke-opacity=".3"/><circle cx="70" cy="70" r="43" stroke="#f3e6b8" stroke-opacity=".2" stroke-dasharray="3 7"/><path d="M41 64h58v29c0 15-10 25-25 25h-8c-15 0-25-10-25-25V64Z" fill="#f3e6b8" fill-opacity=".14" stroke="#f3e6b8" stroke-width="2"/><path d="M99 75h8c12 0 16 15 6 22-4 3-8 4-14 4" stroke="#f3e6b8" stroke-width="5" stroke-linecap="round"/><path d="M55 46c-5-8 5-10 0-18M70 46c-5-8 5-10 0-18M85 46c-5-8 5-10 0-18" stroke="#f3e6b8" stroke-width="2.5" stroke-linecap="round" stroke-opacity=".7"/></svg>
      </div>
      <div class="auth-copy">
        <span class="eyebrow">Operational workspace · 01</span>
        <h1>Satu alur,<br>semua<sub>terukur</sub>.</h1>
        <p>Ruang kerja untuk membuat pesanan lebih cepat, menjaga dapur tetap sinkron, dan pencatatan produk UMKM tetap transparan.</p>
        <div class="flex gap-9 mt-18"><span class="demo-tag"><?= icon('spark') ?> Mode demo siap</span><span class="demo-tag"><?= icon('lock') ?> Local &amp; aman</span></div>
      </div>
      <div class="auth-metrics">
        <div class="auth-metric"><strong>5</strong><span>role operasional</span></div>
        <div class="auth-metric"><strong>1</strong><span>alur terpadu</span></div>
        <div class="auth-metric"><strong>24/7</strong><span>catatan otomatis</span></div>
      </div>
    </section>

    <section class="auth-form-side">
      <div class="auth-form-head">
        <span class="eyebrow">Welcome back</span>
        <h2>Masuk ke workspace</h2>
        <p>Gunakan akun tim Anda untuk melanjutkan operasional cafe.</p>
      </div>
      <?php if ($error): ?><div class="flash flash-danger mb-14"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
      <form class="auth-form" method="post" autocomplete="on">
        <?= csrf_input() ?>
        <div class="auth-field">
          <label class="form-label" for="username">Username</label>
          <div class="auth-control">
            <span class="auth-field-icon"><?= icon('user') ?></span>
            <input class="form-control" id="username" name="username" value="<?= e($username) ?>" placeholder="mis. admin" autocomplete="username" required>
          </div>
        </div>
        <div class="auth-field">
          <label class="form-label" for="password">Password</label>
          <div class="auth-control">
            <span class="auth-field-icon"><?= icon('lock') ?></span>
            <input class="form-control has-toggle" id="password" name="password" type="password" placeholder="Masukkan password" autocomplete="current-password" required>
            <button class="password-toggle" type="button" data-toggle-password="password" aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button>
          </div>
        </div>
        <div class="flex items-center justify-between"><label class="checkbox-line"><input type="checkbox" name="remember"> Ingat perangkat ini</label><span class="muted" style="font-size:10px">Lokal · aman</span></div>
        <button class="btn btn-primary auth-submit" type="submit">Masuk ke dashboard <?= icon('arrow-right') ?></button>
      </form>
      <div class="auth-divider"><span>atau gunakan mode demo</span></div>
      <div class="demo-roles">
        <?php foreach ([['admin', 'Admin'], ['kasir', 'Kasir'], ['pelayan', 'Pelayan'], ['dapur', 'Dapur'], ['umkm', 'UMKM']] as [$role, $label]): ?>
          <form method="post" class="contents"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="demo_login" value="<?= e($role) ?>"><button class="demo-role" type="submit"><?= e($label) ?></button></form>
        <?php endforeach; ?>
      </div>
      <p class="auth-note"><strong>Akun demo:</strong> <code>admin / admin123</code>, <code>kasir / kasir123</code>, <code>pelayan / pelayan123</code>, <code>dapur / dapur123</code>, atau <code>umkm / umkm123</code>.</p>
    </section>
  </div>
</main>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
