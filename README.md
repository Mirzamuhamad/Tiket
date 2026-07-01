# Dashboard Pembelian Tiket Manual

Aplikasi PHP native untuk input penjualan tiket manual. Sistem berjalan sebagai SPA/AJAX tanpa reload penuh, memakai clean URL tanpa `.php`, dan database MySQL.

## Fitur Utama

- Login dinamis dari Master User.
- Master Anggota, Jenis Tiket, Master User.
- Upload foto tiket, kategori tiket, dan stock tiket.
- Stock tiket otomatis berkurang saat transaksi pembelian.
- Dashboard ringkasan dan chart penjualan.
- Laporan pembelian dan export Excel/CSV.
- Layout responsive untuk desktop dan mobile.

## Kebutuhan Lokal

- PHP 7.4+ dengan extension `pdo_mysql`.
- MySQL 8 atau MariaDB kompatibel.
- Apache dengan `mod_rewrite` aktif untuk clean URL.
- Laragon/XAMPP bisa dipakai.

## Instalasi Lokal Laragon

1. Letakkan folder project di:

```text
C:\laragon\www\Tiket
```

2. Pastikan MySQL berjalan.

3. Sesuaikan koneksi database jika perlu lewat environment:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=tiket_manual
DB_USER=root
DB_PASS=
```

Jika tidak diisi, aplikasi memakai default di atas.

4. Jalankan migrasi:

```powershell
php migrate.php
```

Jika `php` belum masuk PATH, gunakan PHP Laragon/XAMPP langsung, contoh:

```powershell
& "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe" migrate.php
```

5. Buka aplikasi:

```text
http://localhost/Tiket
```

Jika Apache Anda berjalan di port 8080:

```text
http://localhost:8080/tiket
```

6. Login awal:

```text
Username: admin
Password: admin123
```

Setelah masuk, ubah/tambah akun dari menu **Master User**.

## Struktur Penting

```text
assets/                 CSS dan JavaScript dashboard
config/database.php     Konfigurasi koneksi database
controllers/            API controller
core/                   Helper database, response, auth, export
migrations/             File SQL migration
uploads/tickets/        File foto tiket
index.php               Entry point aplikasi
migrate.php             Runner migration
```

## Menjalankan di Docker

File Docker sudah disiapkan:

- `Dockerfile`
- `docker-compose.yml`
- `.dockerignore`
- `docker/entrypoint.sh`

### Jalankan di Local/VPS

1. Pastikan Docker dan Docker Compose sudah terpasang.

2. Dari folder project, jalankan:

```bash
docker compose up -d --build
```

3. Aplikasi akan otomatis:

- Menunggu MySQL siap.
- Menjalankan `migrate.php`.
- Menyalakan Apache.

4. Buka:

```text
http://localhost:8080
```

Untuk VPS:

```text
http://IP-VPS:8080
```

### Environment Docker

Default pada `docker-compose.yml`:

```text
APP_PORT=8080
DB_NAME=tiket_manual
DB_USER=root
DB_PASS=root
DB_PUBLIC_PORT=3307
```

Jika ingin mengubah, buat file `.env` di folder project:

```bash
cp .env.docker.example .env
```

Lalu edit isinya:

```env
APP_PORT=8080
DB_NAME=tiket_manual
DB_USER=root
DB_PASS=password-yang-kuat
DB_PUBLIC_PORT=3307
```

Lalu jalankan ulang:

```bash
docker compose up -d --build
```

### Perintah Docker Berguna

Lihat log:

```bash
docker compose logs -f app
```

Jalankan migrasi manual:

```bash
docker compose exec app php migrate.php
```

Masuk container app:

```bash
docker compose exec app bash
```

Masuk MySQL:

```bash
docker compose exec db mysql -uroot -p
```

Stop container:

```bash
docker compose down
```

Stop dan hapus database volume:

```bash
docker compose down -v
```

## Catatan Deploy VPS

- Gunakan password MySQL kuat pada `.env`.
- Jika memakai domain, arahkan reverse proxy Nginx/Apache ke container app port `8080`.
- Folder `uploads` dipasang sebagai volume agar foto tiket tidak hilang saat container rebuild.
- Backup volume database secara berkala.
- Setelah deploy, segera ubah password akun `admin`.

## Troubleshooting

### Halaman API Mengembalikan HTML Bukan JSON

Pastikan URL aplikasi sesuai base path dan Apache rewrite aktif. Di Docker, `a2enmod rewrite` sudah diaktifkan.

### Export Excel Menjadi CSV

Jika extension `zip` tidak tersedia, aplikasi fallback ke CSV. Di Docker, extension `zip` sudah dipasang.

### Upload Foto Gagal

Pastikan folder berikut writable:

```text
uploads/tickets
```

Di Docker folder ini otomatis dibuat dan permission-nya disiapkan.

### Port 8080 Bentrok

Ubah `APP_PORT` di `.env`, contoh:

```env
APP_PORT=8081
```

Lalu jalankan:

```bash
docker compose up -d
```
