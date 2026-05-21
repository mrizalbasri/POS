# POS (Point of Sale)

Proyek POS sederhana berbasis PHP untuk contoh dan keperluan belajar.

Ringkasan

- Aplikasi kasir (Point of Sale) sederhana menggunakan PHP dan MySQL.
- Struktur file dasar termasuk halaman login, dashboard, dan script untuk generate data.

Fitur

- Autentikasi pengguna sederhana (`login.php`, `logout.php`, `includes/auth.php`).
- Halaman dashboard (`dashboard.php`) dan halaman utama (`index.php`).
- Skrip bantuan untuk generate data (`generate_data.php`, `generate_data_process.php`).

Persyaratan

- PHP 7.4+ dengan ekstensi `mysqli` atau `pdo_mysql` terpasang.
- MySQL / MariaDB.
- Web server seperti Apache atau Nginx.

Instalasi & Konfigurasi

1. Clone atau salin repo ini ke folder web server Anda.
2. Buat database MySQL lalu impor struktur contoh di `database/pos_db.sql`.
   - Contoh perintah:

```
mysql -u root -p pos_db < database/pos_db.sql
```

3. Update konfigurasi koneksi database di `config/database.php` sesuai kredensial Anda.
4. Jika perlu, sesuaikan pengaturan aplikasi di `config/app.php`.

Menjalankan Aplikasi

- Letakkan seluruh folder di dalam root web server (mis. `www` atau `htdocs`).
- Akses aplikasi melalui browser: `http://localhost/` (atau path dimana folder ditempatkan).

File Penting

- `index.php` — Halaman utama.
- `login.php`, `logout.php` — Autentikasi.
- `dashboard.php` — Tampilan setelah login.
- `includes/auth.php` — Logika pengecekan autentikasi.
- `config/database.php`, `config/app.php` — Pengaturan koneksi dan aplikasi.
- `database/pos_db.sql` — Skrip SQL untuk membuat tabel contoh.

Tips & Troubleshooting

- Jika koneksi database gagal, pastikan host, username, password, dan nama database di `config/database.php` benar.
- Periksa log error PHP / web server untuk pesan runtime.

Lisensi

- Proyek ini disediakan untuk tujuan pembelajaran; silakan gunakan atau modifikasi sesuai kebutuhan.

Jika Anda mau, saya bisa menambahkan petunjuk instalasi lebih rinci, contoh akun default, atau terjemahan README ke Bahasa Inggris.
