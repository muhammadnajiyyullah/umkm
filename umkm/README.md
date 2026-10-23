# KOPI NUSA — Sistem Informasi Pemesanan Cafe

Aplikasi web pemesanan cafe terintegrasi dengan penjualan produk UMKM, dibuat berdasarkan blueprint **Sistem Informasi Pemesanan Cafe**. Antarmuka menggunakan PHP, MySQL, JavaScript, dan CSS kustom yang responsif (tanpa ketergantungan CDN).

## Ringkasan Analisis Blueprint

Blueprint menjelaskan satu alur operasional yang menghubungkan empat tim internal dan satu mitra:

`Pelayan → Dapur → Pelayan/antar → Kasir → Struk → Laporan Admin`

### Peran dan hak akses

| Peran | Tanggung jawab utama |
| --- | --- |
| Admin | Kelola menu, user, mitra UMKM, laporan, dan log aktivitas |
| Pelayan | Pilih meja, buat/ubah/hapus pesanan, pantau status pesanan |
| Dapur | Terima antrian, ubah status menjadi Diproses dan Siap Saji |
| Kasir | Tagih pesanan, konfirmasi pembayaran, cetak struk, riwayat shift |
| Mitra UMKM | Melihat produk titipan, penjualan, dan komisi cafe |

### Status pesanan

`Baru → Diterima → Diproses → Siap Saji → Menunggu Bayar → Lunas`

Status `Dibatalkan` tersedia dengan catatan log. Perubahan status setelah proses dimulai tidak dapat diedit bebas; pembatalan harus dicatat.

### Modul UMKM

Produk UMKM memakai tabel `menu` yang sama dengan penanda `tipe = 'umkm'`. Produk tidak masuk antrian dapur karena merupakan barang jadi, dan komisi cafe dihitung dari `subtotal × komisi_persen / 100`.

## Fitur yang Dibuat

- Login berbasis role dengan CSRF token dan pembatasan akses server-side.
- Dashboard per role: omzet, antrian, grafik 7 hari, menu terlaris, stok menipis, dan rekap UMKM.
- Katalog menu dengan pencarian, kategori, foto, keranjang, catatan item, nama pelanggan, dan total otomatis.
- Pemilihan meja dine-in / takeaway.
- Alur status real-time untuk dapur memakai Fetch API.
- Pembayaran tunai, QRIS, dan transfer dengan kalkulasi kembalian.
- Struk thermal siap cetak dengan nomor berurutan.
- CRUD menu, user, mitra, dan produk UMKM.
- Laporan penjualan dengan filter tanggal, rekap komisi, cetak, dan ekspor CSV.
- Log aktivitas untuk audit perubahan pesanan.
- Mode demo lintas perangkat: state demo disimpan di `storage/demo_state.json` sehingga perubahan dari pelayan terlihat oleh dapur dan kasir.

## Menjalankan di Laragon

1. Salin folder project ini ke `C:\laragon\www\umkm`.
2. Jalankan Laragon dan klik **Start All**.
3. Buka `http://localhost/phpmyadmin`.
4. Buat database `db_cafe`, lalu import `database/db_cafe.sql`.
5. Buka `http://localhost/umkm`.

Konfigurasi koneksi bawaan mengikuti Laragon:

```text
host: 127.0.0.1
user: root
password: (kosong)
database: db_cafe
```

Bisa diubah melalui environment variable `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, dan `DB_NAME`, atau pada `config/koneksi.php`.

## Akun Awal

| Role | Username | Password |
| --- | --- | --- |
| Admin | `admin` | `admin123` |
| Kasir | `kasir` | `kasir123` |
| Pelayan | `pelayan` | `pelayan123` |
| Dapur | `dapur` | `dapur123` |
| Mitra UMKM | `umkm` | `umkm123` |

### Mode demo tanpa import database

Jika MySQL belum siap, buka halaman login lalu klik salah satu tombol role. Aplikasi otomatis memakai data demo. Untuk mengulang data demo dari awal, hapus file:

```text
storage/demo_state.json
```

### Cara kerja tombol role pada halaman login

Tombol role tidak memakai `user_id` yang dikodekan keras. Saat database tersedia, tombol tersebut memakai **akun nyata yang aktif untuk role itu** (misalnya akun `dapur` bernama *Agus*), sehingga `user_id` yang tersimpan di session selalu valid terhadap foreign key `users(id)` pada tabel `pesanan`, `pembayaran`, dan `log_pesanan`.

Hal ini penting karena `log_pesanan.user_id` memakai `ON DELETE SET NULL`: bila `user_id` menunjuk ke id yang tidak ada di `users`, penulisan log gagal dengan errno **1452** dan seluruh perubahan status pesanan dibatalkan oleh rollback.

Kalau database tidak tersedia, profil demo statis dipakai sebagai gantinya karena saat itu tidak ada foreign key yang perlu dipenuhi. Kolom `users.last_login` otomatis ditambahkan saat aplikasi pertama kali berjalan pada database lama.

### Mengganti nama user

Admin dapat mengganti nama lengkap user dari halaman **Kelola User**. Perubahan itu langsung berlaku di seluruh aplikasi:

- Nama pada header, sidebar, dan avatar di dashboard semua role ikut berubah **tanpa perlu logout**. Session disegarkan otomatis pada setiap request dari tabel `users`.
- Nama pelayan pada daftar pesanan, struk, dan log aktivitas berubah karena semuanya membaca langsung dari `users` lewat foreign key.
- Nama pemilik mitra UMKM ikut diperbarui. Halaman admin dan dashboard mitra membaca nama yang sama, dan pencocokan mitra memakai `user_id` (bukan teks nama) sehingga tidak lagi rusak setelah rename.
- Akun yang dinonaktifkan atau dihapus akan otomatis logout dari session yang sedang berjalan.

Mode demo tanpa database menerapkan aturan yang sama: nama pada pesanan, log, dan data mitra ikut diperbarui.

## Struktur Penting

```text
umkm/
├─ index.php                 login
├─ dashboard.php             dashboard sesuai role
├─ config/
│  ├─ koneksi.php            koneksi PDO
│  ├─ app.php                helper, session, ikon, format rupiah
│  ├─ cek_akses.php          login dan pembatasan role
│  ├─ data.php               query aplikasi
│  ├─ demo_data.php          data dan state mode demo
│  └─ schema.php             migrasi ringan kolom baru
├─ layout/                   header, sidebar, footer
├─ admin/                    menu, user, UMKM, laporan, log
├─ pelayan/                  pesanan baru, aktif, edit
├─ kasir/                    tagihan, pembayaran, struk, transaksi
├─ dapur/                    antrian dapur
├─ umkm/                     dashboard produk mitra
├─ api/                      endpoint AJAX
├─ assets/                   CSS, JavaScript, dan gambar lokal
├─ database/db_cafe.sql      import database
└─ storage/                  state demo (tidak diakses publik)
```

## Menjalankan pada Jaringan Lokal

1. Aktifkan Apache Laragon dan allow untuk jaringan lokal.
2. Jalankan `ipconfig` untuk mengetahui alamat IP server.
3. Pastikan port 80 diizinkan Windows Firewall untuk jaringan privat.
4. Dari tablet/laptop lain, buka `http://192.168.x.x/umkm`.

## Checklist Pengujian

- Login dengan role berbeda dan pastikan halaman role lain ditolak.
- Buat pesanan dengan beberapa item; total dan stok diperbarui.
- Ubah pesanan pada status `Baru`/`Diterima`; setelah `Diproses` harus ditolak.
- Dapur mengubah `Baru → Diterima → Diproses → Siap Saji`.
- Pelayan menandai `Siap Saji → Menunggu Bayar`.
- Kasir membayar; struk bernomor dan pesanan menjadi `Lunas`.
- Produk UMKM langsung berstatus siap dan komisi masuk laporan.
- Harga menu berubah tidak mengubah harga pada detail transaksi lama.

## Catatan Produksi

Versi ini berada di localhost/LAN sesuai batasan blueprint. Integrasi payment gateway otomatis, akun pelanggan, dan multi-cabang dapat dikembangkan pada tahap berikutnya.
