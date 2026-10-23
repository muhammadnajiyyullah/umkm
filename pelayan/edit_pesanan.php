<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/cek_akses.php';
wajib_role(['pelayan', 'admin']);
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    redirect('pelayan/pesanan_baru.php?id=' . $id);
}
redirect('pelayan/pesanan_aktif.php');
