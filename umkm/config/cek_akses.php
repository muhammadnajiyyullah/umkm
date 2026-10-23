<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
// Dipakai sinkronisasi session: butuh daftar user dari database maupun demo.
require_once __DIR__ . '/data.php';

function wajib_login(): void
{
    // Segarkan nama/role di session agar perubahan dari admin langsung
    // tercermin di dashboard tanpa perlu logout.
    sync_user_session(db());
    if (!current_user()) {
        redirect('index.php');
    }
}

function wajib_role(array|string $roles): void
{
    wajib_login();
    $roles = (array)$roles;
    if (!in_array((string)(current_user()['role'] ?? ''), $roles, true)) {
        http_response_code(403);
        $user = current_user();
        render_access_denied($user['name'] ?? 'Pengguna');
    }
}

function render_access_denied(string $name = 'Pengguna'): never
{
    $user = current_user() ?: ['name' => $name, 'role' => ''];
    $active = '';
    $pageTitle = 'Akses Ditolak';
    require __DIR__ . '/../layout/header.php';
    echo '<section class="access-denied"><div class="access-denied-icon">' . icon('lock', 'icon-xl') . '</div><span class="eyebrow">403 · ROLE restricted</span><h1>Akses tidak tersedia</h1><p>Halo, ' . e($user['name']) . '. Halaman ini hanya dapat diakses oleh role yang berwenang.</p><a class="btn btn-primary" href="' . e(url('dashboard.php')) . '">Kembali ke dashboard ' . icon('arrow-right') . '</a></section>';
    require __DIR__ . '/../layout/footer.php';
    exit;
}
