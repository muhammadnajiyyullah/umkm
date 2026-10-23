-- KOPI NUSA / Sistem Informasi Pemesanan Cafe
-- Import file ini melalui phpMyAdmin sebelum membuka aplikasi dengan database.
--
-- Catatan untuk database yang sudah pernah di-import: kolom nama_pelanggan
-- ditambahkan otomatis oleh aplikasi (config/schema.php) saat pertama kali
-- halaman dibuka. Bila ingin menambahkannya manual, jalankan:
--   ALTER TABLE pesanan ADD COLUMN nama_pelanggan VARCHAR(80) NULL AFTER pelayan_id;

CREATE DATABASE IF NOT EXISTS db_cafe
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE db_cafe;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','kasir','pelayan','dapur','umkm') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kategori (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS meja (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nomor VARCHAR(10) NOT NULL UNIQUE,
  kapasitas TINYINT UNSIGNED NOT NULL DEFAULT 4,
  status ENUM('tersedia','terisi') NOT NULL DEFAULT 'tersedia'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mitra_umkm (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  nama_usaha VARCHAR(100) NOT NULL,
  pemilik VARCHAR(100) NULL,
  kontak VARCHAR(30) NULL,
  komisi_persen DECIMAL(5,2) NOT NULL DEFAULT 10,
  status ENUM('pending','aktif','nonaktif') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_mitra_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS menu (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NULL,
  nama VARCHAR(100) NOT NULL,
  kategori_id INT NULL,
  harga DECIMAL(12,2) NOT NULL DEFAULT 0,
  stok INT NOT NULL DEFAULT 0,
  foto VARCHAR(255) NULL,
  tipe ENUM('cafe','umkm') NOT NULL DEFAULT 'cafe',
  mitra_id INT NULL,
  deskripsi VARCHAR(255) NULL,
  is_tersedia TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL,
  FOREIGN KEY (mitra_id) REFERENCES mitra_umkm(id) ON DELETE SET NULL,
  INDEX idx_menu_tipe (tipe),
  INDEX idx_menu_tersedia (is_tersedia)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pesanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_pesanan VARCHAR(30) NOT NULL UNIQUE,
  meja_id INT NULL,
  tipe_pesanan ENUM('dine_in','takeaway') NOT NULL DEFAULT 'dine_in',
  pelayan_id INT NOT NULL,
  nama_pelanggan VARCHAR(80) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Baru',
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  catatan TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (meja_id) REFERENCES meja(id) ON DELETE SET NULL,
  FOREIGN KEY (pelayan_id) REFERENCES users(id),
  INDEX idx_pesanan_status (status),
  INDEX idx_pesanan_pelanggan (nama_pelanggan),
  INDEX idx_pesanan_tanggal (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_pesanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pesanan_id INT NOT NULL,
  menu_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  harga_satuan DECIMAL(12,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  catatan_item VARCHAR(255) NULL,
  status_item VARCHAR(20) NOT NULL DEFAULT 'Baru',
  FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
  FOREIGN KEY (menu_id) REFERENCES menu(id),
  INDEX idx_detail_pesanan (pesanan_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pembayaran (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pesanan_id INT NOT NULL,
  kasir_id INT NOT NULL,
  nomor_struk VARCHAR(30) NOT NULL UNIQUE,
  metode ENUM('tunai','qris','transfer') NOT NULL DEFAULT 'tunai',
  jumlah_bayar DECIMAL(12,2) NOT NULL,
  kembalian DECIMAL(12,2) NOT NULL DEFAULT 0,
  waktu_bayar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pesanan_id) REFERENCES pesanan(id),
  FOREIGN KEY (kasir_id) REFERENCES users(id),
  INDEX idx_payment_order (pesanan_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS log_pesanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pesanan_id INT NULL,
  user_id INT NULL,
  aksi VARCHAR(50) NOT NULL,
  status_lama VARCHAR(20) NULL,
  status_baru VARCHAR(20) NULL,
  keterangan VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_log_created (created_at)
) ENGINE=InnoDB;

-- Data awal. Password semua akun demo: nama_role + 123
INSERT IGNORE INTO users (id, nama, username, password, role, is_active) VALUES
  (1, 'Naji', 'admin', '$2y$10$rF4602IBefTQFskTct/t0Oqmzb1JXZMTljxfgtwLxcP1N0QugNOEC', 'admin', 1),
  (2, 'Iyyul', 'kasir', '$2y$10$Chp1ECoRGVaDuz8gNauP/OAddIa227LVqMbBSZFUAHAwG2Az1TuIa', 'kasir', 1),
  (3, 'Raka Pratama', 'pelayan', '$2y$10$twetdg3Fei1HULy6AO4.6ONPnEQD62prHjGqnrMovJXkjJZ2bI2s.', 'pelayan', 1),
  (4, 'Dimas Kitchen', 'dapur', '$2y$10$vUmQLSYUsQEC.ZTovGCUUeUXdXApJseBKFeXJtXoTyaM2lQA90qzK', 'dapur', 1),
  (5, 'Rasa Rumahan', 'umkm', '$2y$10$aW6Y3hEXA8aPvBgwlfk3SudgmpPL5WU0NBsFeMa2ZUK2OLPyK.BHm', 'umkm', 1);

INSERT IGNORE INTO kategori (id, nama) VALUES
  (1, 'Minuman'),
  (2, 'Makanan'),
  (3, 'Produk UMKM');

-- Meja 02 (pesanan 104), 07 (pesanan 101), 03 (pesanan 102), dan 09 (pesanan 105) terisi.
INSERT IGNORE INTO meja (id, nomor, kapasitas, status) VALUES
  (1, '01', 4, 'tersedia'), (2, '02', 4, 'terisi'), (3, '03', 6, 'terisi'),
  (4, '04', 4, 'tersedia'), (5, '05', 4, 'tersedia'), (6, '06', 4, 'tersedia'),
  (7, '07', 4, 'terisi'), (8, '08', 4, 'tersedia'), (9, '09', 6, 'terisi'),
  (10, '10', 4, 'tersedia');

INSERT IGNORE INTO mitra_umkm (id, user_id, nama_usaha, pemilik, kontak, komisi_persen, status) VALUES
  (1, 5, 'Rasa Rumahan', 'Nadia Putri', '0812 3456 7890', 10.00, 'aktif'),
  (2, NULL, 'Bumi Snack', 'Fajar Nugroho', '0813 9087 6543', 12.00, 'aktif'),
  (3, NULL, 'Sari Buah', 'Lina Wulandari', '0857 4432 1109', 10.00, 'pending');

INSERT IGNORE INTO menu (id, kode, nama, kategori_id, harga, stok, foto, tipe, mitra_id, deskripsi, is_tersedia) VALUES
  (1, 'MKN-001', 'Cappuccino', 1, 28000, 24, 'assets/img/menu/cappuccino.svg', 'cafe', NULL, 'Espresso, steamed milk, dan busa lembut.', 1),
  (2, 'MKN-002', 'Matcha Latte', 1, 32000, 18, 'assets/img/menu/matcha-latte.svg', 'cafe', NULL, 'Matcha premium dengan susu oat yang gurih.', 1),
  (3, 'MKN-003', 'Cold Brew Tonic', 1, 30000, 15, 'assets/img/menu/cold-brew.svg', 'cafe', NULL, 'Cold brew-blackcurrant dengan rasa segar.', 1),
  (4, 'MKN-004', 'Butter Croissant', 2, 22000, 12, 'assets/img/menu/croissant.svg', 'cafe', NULL, 'Croissant buttery, renyah di luar dan lembut di dalam.', 1),
  (5, 'MKN-005', 'Nasi Goreng Kampung', 2, 32000, 9, 'assets/img/menu/nasi-goreng.svg', 'cafe', NULL, 'Nasi goreng smoky dengan telur dan garnish.', 1),
  (6, 'UMKM-001', 'Cookies Cokelat', 3, 18000, 20, 'assets/img/menu/cookies.svg', 'umkm', 1, 'Homemade cookies dengan filling cokelat Belgia.', 1),
  (7, 'UMKM-002', 'Keripik Pisang', 3, 15000, 7, 'assets/img/menu/keripik-pisang.svg', 'umkm', 2, 'Keripik pisang renyah dengan bumbu yang pas.', 1),
  (8, 'UMKM-003', 'Es Citrus Lumut', 3, 16000, 16, 'assets/img/menu/es-citrus.svg', 'umkm', 3, 'Es markis lumpur-orange yang menyegarkan.', 1);

-- Alur linear satu status:
--   Dapur   : Baru -> Diterima -> Diproses -> Siap Saji
--   Pelayan : Siap Saji -> Menunggu Bayar (antar ke meja)
--   Kasir   : Menunggu Bayar -> Lunas (bayar + cetak struk)
-- Meja 02 dan 09 terisi oleh pesanan yang masih berjalan.
INSERT IGNORE INTO pesanan (id, kode_pesanan, meja_id, tipe_pesanan, pelayan_id, nama_pelanggan, status, total, catatan, created_at) VALUES
  (101, 'ORD-20260925-0101', 7, 'dine_in', 3, 'Bu Sari Wijaya', 'Menunggu Bayar', 50000, 'Tolong es sedikit lebih banyak.', DATE_SUB(NOW(), INTERVAL 24 MINUTE)),
  (102, 'ORD-20260925-0102', 3, 'dine_in', 3, 'Kak Reza', 'Menunggu Bayar', 96000, 'Satu tanpa gula.', DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
  (103, 'ORD-20260925-0103', 5, 'dine_in', 3, 'Ibu Nur', 'Lunas', 60000, '', DATE_SUB(NOW(), INTERVAL 52 MINUTE)),
  (104, 'ORD-20260925-0104', 2, 'dine_in', 3, 'Andi Saputra', 'Baru', 60000, '', DATE_SUB(NOW(), INTERVAL 31 MINUTE)),
  (105, 'ORD-20260925-0105', 9, 'dine_in', 3, 'Bu Tuti Aminah', 'Diproses', 112000, 'Pedas level 2.', DATE_SUB(NOW(), INTERVAL 40 MINUTE)),
  (106, 'ORD-20260925-0106', NULL, 'takeaway', 3, 'Pak Hendra', 'Diterima', 30000, 'Bungkus rapat.', DATE_SUB(NOW(), INTERVAL 42 MINUTE)),
  (107, 'ORD-20260925-0107', 1, 'dine_in', 3, 'Dewi Lestari', 'Lunas', 78000, '', DATE_SUB(NOW(), INTERVAL 70 MINUTE));

INSERT IGNORE INTO detail_pesanan (pesanan_id, menu_id, qty, harga_satuan, subtotal, catatan_item, status_item) VALUES
  (101, 1, 1, 28000, 28000, '', 'Menunggu Bayar'),
  (101, 4, 1, 22000, 22000, '', 'Siap Saji'),
  (102, 1, 1, 28000, 28000, '', 'Menunggu Bayar'),
  (102, 2, 1, 32000, 32000, '', 'Menunggu Bayar'),
  (102, 6, 2, 18000, 36000, '', 'Siap Saji'),
  (103, 3, 1, 30000, 30000, '', 'Siap Saji'),
  (103, 7, 2, 15000, 30000, '', 'Siap Saji'),
  (104, 1, 1, 28000, 28000, '', 'Baru'),
  (104, 5, 1, 32000, 32000, '', 'Baru'),
  (105, 2, 1, 32000, 32000, '', 'Diproses'),
  (105, 5, 2, 32000, 64000, 'Pedas level 2', 'Diproses'),
  (105, 8, 1, 16000, 16000, '', 'Siap Saji'),
  (106, 3, 1, 30000, 30000, '', 'Diterima'),
  (107, 1, 2, 28000, 56000, '', 'Siap Saji'),
  (107, 4, 1, 22000, 22000, '', 'Siap Saji');

INSERT IGNORE INTO pembayaran (id, pesanan_id, kasir_id, nomor_struk, metode, jumlah_bayar, kembalian, waktu_bayar) VALUES
  (501, 103, 2, 'STR-20260925-0001', 'qris', 60000, 0, DATE_SUB(NOW(), INTERVAL 48 MINUTE)),
  (502, 107, 2, 'STR-20260925-0002', 'tunai', 100000, 22000, DATE_SUB(NOW(), INTERVAL 66 MINUTE));

INSERT IGNORE INTO log_pesanan (pesanan_id, user_id, aksi, status_lama, status_baru, keterangan, created_at) VALUES
  (103, 2, 'Bayar pesanan', 'Menunggu Bayar', 'Lunas', 'qris', DATE_SUB(NOW(), INTERVAL 48 MINUTE)),
  (101, 3, 'Ubah status', 'Siap Saji', 'Menunggu Bayar', 'Pesanan diantar, menunggu pembayaran di kasir', DATE_SUB(NOW(), INTERVAL 24 MINUTE)),
  (104, 3, 'Buat pesanan', '-', 'Baru', 'Pelanggan: Andi Saputra', DATE_SUB(NOW(), INTERVAL 31 MINUTE)),
  (106, 4, 'Ubah status', 'Baru', 'Diterima', 'Pesanan diterima dapur.', DATE_SUB(NOW(), INTERVAL 42 MINUTE));
