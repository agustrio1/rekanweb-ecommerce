# RekanWeb Commerce

Toko online custom berbasis PHP Native dan MySQL, dengan pembayaran Duitku POP dan pengiriman Biteship.
Dibuat sebagai portfolio RekanWeb (rekanweb.biz.id).

**Stack:** PHP 8.2+, MySQL 8+/MariaDB, Tailwind CSS, Alpine.js, HTMX, Tiptap, Vite.
Hosting hanya butuh PHP dan MySQL. Node.js, npm, dan Composer hanya dipakai di komputer pengembang.

## Persyaratan

- PHP 8.2 atau lebih baru dengan ekstensi: `pdo_mysql`, `mbstring`, `curl`, `openssl`, `fileinfo`, `dom`, `gd` (dengan WebP), `exif`.
- MySQL 8+ atau MariaDB 10.5+.
- Apache dengan `mod_rewrite` (cPanel standar sudah ada).
- Untuk pengembangan: Node.js 20+ dan npm.

Cek ekstensi PHP: `php -m`. Di cPanel: menu **Select PHP Version > Extensions**.

## Pengembangan lokal

```bash
git clone <repo> rekanweb-ecommerce && cd rekanweb-ecommerce
cp .env.example .env            # lalu isi nilainya (lihat tabel di bawah)
npm install
```

Buat database kosong dan impor:

```bash
mysql -u root -p -e "CREATE DATABASE rekanweb_ecommerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p rekanweb_ecommerce < database/rekanweb-install.sql
mkdir -p storage/logs storage/cache
```

Jalankan:

```bash
npm run build                                # sekali, atau gunakan "npm run dev" untuk Vite
php -S localhost:8000 -t public              # buka http://localhost:8000
```

Buat password admin Anda sendiri, jangan memakai password bawaan seed:

```bash
php scripts/hash-password.php admin@rekanweb.biz.id
```

Jalankan perintah `UPDATE` yang dicetak di database.

## Variabel `.env`

| Variabel | Keterangan |
|---|---|
| `APP_ENV` | `local` menampilkan error. `production` menyembunyikan error dan mencatatnya di `storage/logs/php-error.log`. Jika kosong dianggap production. |
| `APP_URL` | Alamat situs tanpa garis miring akhir, misalnya `https://tokoanda.com`. Dipakai untuk canonical, sitemap, dan URL callback pembayaran. |
| `APP_KEY` | 64 karakter acak untuk mengenkripsi API key di database. Buat dengan `php -r "echo bin2hex(random_bytes(32));"`. **Simpan salinannya.** Jika hilang, API key tersimpan tidak bisa dibuka dan harus diisi ulang. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Koneksi database. |
| `BITESHIP_API_KEY`, `DUITKU_MERCHANT_CODE`, `DUITKU_API_KEY`, `DUITKU_ENV` | Cadangan jika kredensial belum diisi lewat panel admin. Pengaturan di admin diutamakan. |
| `BITESHIP_WEBHOOK_HEADER`, `BITESHIP_WEBHOOK_SECRET` | Nama dan nilai header rahasia untuk memverifikasi webhook Biteship. Gunakan nilai acak panjang. |

File `.env` tidak boleh masuk Git dan tidak boleh dapat diakses dari web.

## Deploy ke shared hosting (cPanel)

Frontend sudah dibuild di komputer Anda, jadi hosting tidak perlu Node.js, npm, SSH, atau proses build.

### 1. Persiapan di komputer

```bash
npm run build
```

Pastikan folder `public/assets/` berisi hasil build (termasuk `.vite/manifest.json`). Kompres seluruh proyek **tanpa** `node_modules/`, `.git/`, `.env`, dan isi `storage/logs/` serta `storage/cache/`.

### 2. Database

1. cPanel > **MySQL Databases**: buat database, buat user, tambahkan user ke database dengan semua hak akses. Catat nama lengkapnya (biasanya berawalan nama akun cPanel).
2. cPanel > **phpMyAdmin**: pilih database, tab **Import**, unggah `database/rekanweb-install.sql`. Hasilnya harus 22 tabel.

### 3. Unggah file

Pilih salah satu tata letak.

**A. Document root bisa diarahkan ke folder `public` (disarankan).**
Unggah seluruh proyek ke satu folder, misalnya `/home/akun/rekanweb`. Di cPanel > **Domains**, ubah document root domain menjadi `/home/akun/rekanweb/public`. Tidak ada langkah tambahan.

**B. Document root tetap `public_html`.**
1. Unggah semua folder proyek (`app`, `bootstrap`, `config`, `database`, `resources`, `routes`, `scripts`, `storage`, `.env`) ke `/home/akun/rekanweb`, di luar `public_html`.
2. Salin **isi** folder `public/` (`index.php`, `.htaccess`, `assets/`, `uploads/`) ke `public_html`.
3. Buat file `public_html/.base-path` berisi satu baris:
   ```
   /home/akun/rekanweb
   ```
   Path ini harus sesuai lokasi sebenarnya di akun Anda. Lihat di cPanel > File Manager.

### 4. Konfigurasi

Salin `.env.example` menjadi `.env` di folder source, lalu isi:

```
APP_ENV=production
APP_URL=https://tokoanda.com
APP_KEY=<hasil perintah di bagian variabel>
DB_HOST=localhost
DB_DATABASE=<nama database lengkap>
DB_USERNAME=<user database>
DB_PASSWORD=<password>
BITESHIP_WEBHOOK_SECRET=<acak panjang>
```

Atur izin: `storage/`, `storage/logs/`, `storage/cache/`, dan `uploads/` harus bisa ditulis PHP (umumnya 755; naikkan ke 775 hanya jika unggahan gagal).

Di cPanel > **Select PHP Version**: pilih PHP 8.2 atau lebih baru dan aktifkan ekstensi pada daftar persyaratan. Aktifkan **OPcache** jika tersedia, karena ini penghemat waktu terbesar di sisi PHP.

### 5. SSL

cPanel > **SSL/TLS Status**: jalankan **AutoSSL** untuk domain. Setelah situs terbuka lewat `https://`, buka `.htaccess` di folder publik dan hapus tanda `#` pada tiga baris di bawah komentar "Paksa HTTPS".

### 6. Login pertama

Buka `https://tokoanda.com/login`, masuk sebagai admin, lalu:

1. Ganti password admin jika belum (lihat bagian pengembangan lokal).
2. **Pengaturan**: isi nama toko, logo, kontak, dan alamat asal pengiriman (pilih kecamatan lewat pencarian wilayah).
3. **Pengaturan > Kredensial API**: isi kunci Biteship, merchant code dan API key Duitku. Biarkan mode **Sandbox** dulu.

### 7. Daftarkan alamat callback

| Layanan | Di mana | Isi |
|---|---|---|
| Duitku | Dashboard proyek Duitku | Callback URL: `https://tokoanda.com/webhook/duitku`. Return URL: `https://tokoanda.com/pembayaran/selesai`. |
| Biteship | Dashboard > Integrations > Webhook | URL: `https://tokoanda.com/webhook/biteship`. Header: nama dan nilai sama dengan `BITESHIP_WEBHOOK_HEADER` dan `BITESHIP_WEBHOOK_SECRET`. Aktifkan event `order.status`, `order.waybill_id`, `order.price`. |

Callback hanya bisa menjangkau situs yang dapat diakses publik lewat HTTPS.

## Menguji di sandbox

Lakukan berurutan, di domain asli dengan mode Sandbox:

1. Daftar akun pelanggan, tambahkan alamat (pilih kecamatan dari daftar).
2. Masukkan produk ke keranjang, buka checkout. Pilihan kurir dan tarif harus muncul.
3. Buat pesanan, lanjutkan ke pembayaran Duitku sandbox, selesaikan pembayaran.
4. Di admin > Pesanan: status berubah ke **Perlu diproses** setelah callback Duitku masuk. Callback yang sama dikirim ulang tidak boleh menggandakan apa pun.
5. Klik **Buat pengiriman**. Nomor resi dan status pengiriman tersimpan.
6. Pantau perubahan status dari webhook Biteship di halaman detail pesanan pelanggan.

Jika callback tidak masuk, periksa `storage/logs/php-error.log` dan pastikan `APP_URL` benar.

## Pindah ke production

1. Pastikan seluruh langkah pengujian sandbox lulus.
2. **Pengaturan > Kredensial API**: ganti merchant code, API key Duitku, dan API key Biteship dengan kunci live. Pilih mode **Production**. Merchant code, API key, dan mode harus dari lingkungan yang sama.
3. Jangan mengganti mode saat masih ada pesanan menunggu pembayaran.
4. Lakukan satu transaksi nyata bernilai kecil dari awal sampai pengiriman.

## Pemeriksaan keamanan setelah deploy

Semua alamat ini harus memberi 403 atau 404, bukan isi file:

```
https://tokoanda.com/.env
https://tokoanda.com/assets/.vite/manifest.json
https://tokoanda.com/.base-path
https://tokoanda.com/uploads/tes.php
```

Periksa juga:
- `https://tokoanda.com/robots.txt` berisi aturan `Disallow` per jalur dan baris `Sitemap:`, bukan `Disallow: /`. Jika masih `Disallow: /`, `APP_ENV` belum `production`.
- `https://tokoanda.com/admin` tanpa login tidak menampilkan isi admin.
- Halaman error tidak menampilkan path server atau pesan database.
- Daftarkan `https://tokoanda.com/sitemap.xml` di Google Search Console.

## Pemeliharaan

- **Backup:** ekspor database dari phpMyAdmin secara berkala, dan simpan folder `uploads/` serta `.env` (terutama `APP_KEY`).
- **Log:** `storage/logs/php-error.log`. Periksa setelah deploy dan saat ada keluhan.
- **Pembaruan kode:** unggah file yang berubah. Jika ada perubahan CSS atau JS, jalankan `npm run build` dan unggah ulang folder `assets/`.
- **Audit:** semua aksi admin tercatat di tabel `admin_activity_logs`.

## Struktur proyek

```
app/            Controllers, Models, Services (Biteship, Duitku), Middleware, Core, Helpers
bootstrap/      Pemuatan awal: env, error handler, session, header keamanan
config/         Konfigurasi aplikasi, database, Biteship, Duitku
database/       Berkas SQL instalasi
resources/      View PHP, CSS, dan JavaScript sumber
routes/         web.php dan admin.php
scripts/        Alat CLI (misalnya hash-password.php)
storage/        Log dan cache (tidak publik)
public/         Satu-satunya folder yang boleh dapat diakses web
```