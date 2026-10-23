<?php
declare(strict_types=1);

/**
 * Migrasi ringan untuk MySQL. Aplikasi tidak memerlukan proses install
 * tambahan: setiap kolom baru akan ditambahkan otomatis saat database
 * sudah terhubung namun skemanya masih versi lama.
 */
function ensure_schema(?PDO $pdo): void
{
    static $checked = false;
    if ($checked || !$pdo instanceof PDO) {
        return;
    }
    $checked = true;

    $migrations = [
        'pesanan' => [
            'nama_pelanggan' => 'ALTER TABLE pesanan ADD COLUMN nama_pelanggan VARCHAR(80) NULL AFTER pelayan_id',
        ],
        'users' => [
            'last_login' => 'ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER is_active',
        ],
    ];

    foreach ($migrations as $table => $columns) {
        try {
            $statement = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $existing = array_column($statement->fetchAll(), 'Field');
        } catch (Throwable $exception) {
            error_log('[KOPI NUSA] Gagal membaca struktur ' . $table . ': ' . $exception->getMessage());
            continue;
        }
        foreach ($columns as $column => $sql) {
            if (in_array($column, $existing, true)) {
                continue;
            }
            try {
                $pdo->exec($sql);
                error_log('[KOPI NUSA] Migrasi diterapkan: ' . $sql);
            } catch (Throwable $exception) {
                error_log('[KOPI NUSA] Migrasi gagal (' . $sql . '): ' . $exception->getMessage());
            }
        }
    }
}
