# Panduan Deploy ke VPS Bersih (Ubuntu 24.04)

Panduan ini memasang Tagihan Jaringan (macbill_v3) di VPS baru. Jalankan sebagai root atau user dengan sudo. Ganti nilai bertanda GANTI.

## 1. Paket sistem

```bash
apt update
apt install -y software-properties-common ca-certificates curl git unzip nginx mysql-server
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath php8.4-opcache php8.4-readline
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
```

- Syarat aplikasi: PHP 8.3 atau lebih baru dan Laravel 13 (lihat composer.json).
- Ekstensi `pdo_mysql` (paket php8.4-mysql) wajib. Tanpanya migrasi gagal karena driver tidak ditemukan. Setelah memasangnya jalankan `systemctl reload php8.4-fpm`.
- Pasang Node dari NodeSource, bukan nvm milik root, supaya build tidak bergantung pada satu akun. Node hanya dipakai saat build aset.

## 2. Database

```bash
mysql <<'SQL'
CREATE DATABASE tagihan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tagihan'@'localhost' IDENTIFIED BY 'GANTI_SANDI_KUAT';
GRANT ALL PRIVILEGES ON tagihan.* TO 'tagihan'@'localhost';
SQL
```

## 3. Kode dan konfigurasi

```bash
cd /var/www
git clone https://github.com/macdevi/macbill_v3.git tagihan
cd tagihan
composer install --no-dev --optimize-autoloader --no-interaction
cp deploy/env.production.example .env
php artisan key:generate --force
npm ci
npm run build
chown root:www-data .env && chmod 640 .env
chown -R www-data:www-data storage bootstrap/cache
```

Edit `.env`: `APP_URL`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Variabel penting:

- `BILLING_RESET_ENABLED=false`. Jangan diubah ke true di produksi: itu mengizinkan `billing:reset` menghapus semua data billing.
- `WILAYAH_ENFORCE=true` membatasi admin wilayah ke datanya sendiri.
- `SESSION_SECURE_COOKIE=true` hanya jika diakses lewat HTTPS. Untuk uji lewat `http://127.0.0.1` biarkan false, kalau tidak login gagal.

Jika repo privat, gunakan deploy key atau token saat `git clone`.

## 4. Migrasi dan admin pertama

```bash
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan app:install
sudo -u www-data php artisan optimize
```

Selalu jalankan artisan sebagai `www-data`. Jika dijalankan sebagai root, berkas log dan cache dimiliki root dan web server tidak bisa menulisnya (gejala: halaman error 500). `app:install` meminta kata sandi secara interaktif dan hanya berjalan bila belum ada super admin.

## 5. Nginx

```bash
cp deploy/nginx.conf.example /etc/nginx/sites-available/tagihan
ln -s /etc/nginx/sites-available/tagihan /etc/nginx/sites-enabled/tagihan
ls /run/php/
nginx -t && systemctl reload nginx
```

Pastikan `fastcgi_pass` memakai soket versi PHP yang benar (`ls /run/php/`). Jika ada beberapa versi PHP-FPM di server, salah soket membuat ekstensi atau driver tidak ditemukan. Untuk akses uji dari komputer sendiri: `ssh -L 8090:127.0.0.1:8090 user@server`, lalu buka `http://127.0.0.1:8090`.

Untuk HTTPS: arahkan domain ke server, pakai varian produksi di berkas contoh, lalu `apt install -y certbot python3-certbot-nginx && certbot --nginx -d GANTI.contoh.com`. Setelah itu ubah `APP_URL=https://...` dan `SESSION_SECURE_COOKIE=true`, lalu jalankan `sudo -u www-data php artisan optimize`.

## 6. Pemeriksaan

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8090/login   # 200
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8090/        # 302
sudo -u www-data php artisan migrate:status | tail -n 5
sudo -u www-data php artisan about --only=environment
```

## 7. Scheduler (cron)

Scheduler menjalankan `billing:generate-invoices --commit` setiap hari pukul 00:10 WIB (17:10 UTC). Pasang cron ini hanya saat siap menerbitkan tagihan sungguhan, dan jangan pasang jika aplikasi billing lain masih mengendalikan router yang sama.

Jalankan `crontab -u www-data -e`, lalu tambahkan baris:

```
* * * * * cd /var/www/tagihan && php artisan schedule:run >> /dev/null 2>&1
```

Simulasi tanpa menerbitkan apa pun: `sudo -u www-data php artisan billing:generate-invoices` (tanpa `--commit`).

## 8. Cadangan database

Buat folder `install -d -m 700 /root/backup`, lalu tambahkan baris ini lewat `crontab -e` (root):

```
0 2 * * * mysqldump --single-transaction tagihan | gzip > /root/backup/tagihan_$(date +\%F).sql.gz && find /root/backup -name 'tagihan_*.sql.gz' -mtime +14 -delete
```

Uji pemulihan cadangan secara berkala.

## 9. Rilis berikutnya

Jalankan `./deploy.sh` dari folder proyek: mode pemeliharaan, `git pull`, composer, build aset, migrasi, optimize, lalu reload PHP-FPM sesuai versi php yang terpasang.

## 10. Masalah umum

- Error 500 tanpa pesan: periksa `storage/logs` dan pemilik `storage` serta `bootstrap/cache` (harus www-data).
- `could not find driver`: ekstensi `php8.4-mysql` belum terpasang atau PHP-FPM belum di-reload.
- Setelah mengubah `.env`: jalankan `sudo -u www-data php artisan optimize` agar cache konfigurasi diperbarui.
