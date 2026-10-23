# Database Sync Package (`cmdimu/db-sync-package`)

Package untuk sinkronisasi database MySQL/MariaDB (Local &harr; Remote) berbasis REST API untuk **Laravel**, **CodeIgniter 4 (CI4)**, dan **CodeIgniter 3 (CI3)**.

---

## 🚀 Fitur Utama

- **Multi-Framework**: Mendukung Laravel (Auto-discovery), CodeIgniter 4, dan CodeIgniter 3.
- **Autentikasi Fleksibel & Terproteksi (Login Ready)**:
  - **Auto-Detect**: Langsung terintegrasi otomatis jika user sudah login di Laravel (`Auth::check()`), CI4 (Shield/Session), atau CI3 (`$ci->session`).
  - **Built-in Login Page**: Dilengkapi layar login siap pakai `/db-sync/login` untuk aplikasi yang belum memiliki sistem autentikasi atau jika ingin login terpisah.
  - **Middleware Kustom**: Di Laravel dapat menambahkan middleware apapun (contoh: `DB_SYNC_MIDDLEWARE=web,auth`).
- **Tiga Arah Sinkronisasi**:
  - `Local -> Remote`: Mengunggah data lokal ke server remote.
  - `Remote -> Local`: Mengunduh data dari server remote dan memperbarui database lokal.
  - `Both (Two-Way)`: Sinkronisasi dua arah.
- **API Antar-Server Aman**: Dilengkapi autentikasi token berbasis API Key dengan proteksi *timing-attack* (`hash_equals`) dan pemisahan middleware CSRF untuk endpoint API.
- **Performa & Integritas**: Menggunakan transaksi PDO (`beginTransaction` & `commit`) dengan sanitasi nama tabel dan kolom untuk mencegah SQL Injection.
- **Dashboard UI Modern**: Dilengkapi fitur *Select All*, status indikator per-tabel, dan panel log eksekusi langsung (*real-time logs*).

---

## 📦 Instalasi

### 1. Via Composer (Jika dipublikasikan ke Packagist / VCS)

Jalankan perintah berikut pada root project Anda:
```bash
composer require cmdimu/db-sync-package
```

### 2. Instalasi Manual / Path Repository (Lokal)
Jika package diletakkan di dalam folder project (misal: `packages/db-sync-package`), tambahkan ke `composer.json` project utama:
```json
"repositories": [
    {
        "type": "path",
        "url": "packages/db-sync-package",
        "options": {
            "symlink": true
        }
    }
],
"require": {
    "cmdimu/db-sync-package": "*@dev"
}
```
Lalu jalankan:
```bash
composer update cmdimu/db-sync-package
```

---

## ⚙️ Konfigurasi Environment (`.env`)

Tambahkan variabel berikut ke dalam file `.env` di aplikasi Anda:

```env
# Mode aplikasi: client (pengirim/pemicu) atau server (penerima)
DB_SYNC_MODE=client

# URL Prefix untuk mengakses dashboard UI & API (default: db-sync)
DB_SYNC_PREFIX=db-sync

# URL aplikasi Remote tujuan (wajib diisi pada sisi client)
DB_SYNC_REMOTE_URL=https://remote-domain.com/db-sync

# API Key rahasia untuk autentikasi antar-server (wajib sama antara local & remote)
DB_SYNC_SECRET_KEY=GantiDenganSecretKeyYangPanjangDanAman123!

# Verifikasi SSL (set ke false untuk local development dengan self-signed cert / Laragon)
DB_SYNC_SSL_VERIFY=true

# ==========================================
# PENGATURAN AUTENTIKASI DASHBOARD
# ==========================================
# Driver auth: auto, builtin, framework, atau none (default: auto)
# auto      : Gunakan session framework jika user sudah login, jika belum gunakan built-in login
# builtin   : Selalu tampilkan halaman login khusus DB Sync
# framework : Hanya izinkan jika user login lewat auth framework
# none      : Matikan proteksi login (tidak disarankan)
DB_SYNC_AUTH_DRIVER=auto

# Kredensial untuk Built-in Login Dashboard (/db-sync/login)
DB_SYNC_AUTH_USERNAME=admin
DB_SYNC_AUTH_PASSWORD=PasswordAdminSuperAman123!

# (Khusus Laravel) Tambahan middleware untuk rute dashboard (default: web)
# Contoh jika ingin mewajibkan auth bawaan Laravel:
# DB_SYNC_MIDDLEWARE=web,auth
```

---

## 🛠️ Setup per Framework

### 1. Laravel (Laravel 8, 9, 10, 11+)
- **Service Provider otomatis terdeteksi** via Composer Package Discovery.
- Jika ingin mempublish tampilan dashboard & login:
  ```bash
  php artisan vendor:publish --tag=db-sync-views
  ```
- Akses dashboard di browser:
  ```
  http://domain-anda.test/db-sync
  ```
  *(Jika belum login, sistem akan otomatis mengarahkan ke `/db-sync/login`)*.

---

### 2. CodeIgniter 4
Tambahkan route pada file `app/Config/Routes.php`:

```php
$prefix = env('DB_SYNC_PREFIX', 'db-sync');

$routes->group($prefix, function($routes) {
    // Auth & Dashboard Web
    $routes->get('login', '\DbSync\Http\Controllers\SyncController::login');
    $routes->post('login', '\DbSync\Http\Controllers\SyncController::login');
    $routes->get('logout', '\DbSync\Http\Controllers\SyncController::logout');
    $routes->get('/', '\DbSync\Http\Controllers\SyncController::index');
    $routes->post('process', '\DbSync\Http\Controllers\SyncController::process');
    
    // Remote API Endpoint (Bypass CSRF Filter jika CSRF diaktifkan secara global)
    $routes->post('sync-api', '\DbSync\Http\Controllers\SyncController::handleApi');
});
```
> **Catatan CI4 CSRF**: Jika CSRF protection diaktifkan secara global di `app/Config/Filters.php`, tambahkan pengecualian (*except*) untuk URI `$prefix . '/sync-api'` agar request dari server remote tidak terblokir.

---

### 3. CodeIgniter 3
1. Pastikan Composer autoloader sudah diaktifkan di `application/config/config.php`:
   ```php
   $config['composer_autoload'] = 'vendor/autoload.php';
   ```

2. Buat controller di `application/controllers/DbSyncController.php` (atau copy dari `src/ci3/controllers/DbSyncController.php`):
   ```php
   <?php
   defined('BASEPATH') OR exit('No direct script access allowed');

   use DbSync\Http\Controllers\SyncController;

   class DbSyncController extends CI_Controller {
       private $controller;

       public function __construct() {
           parent::__construct();
           $this->controller = new SyncController();
       }

       public function login() { $this->controller->login(); }
       public function logout() { $this->controller->logout(); }
       public function index() { $this->controller->index(); }
       public function process() { $this->controller->process(); }
       public function handleApi() { $this->controller->handleApi(); }
   }
   ```

3. Tambahkan konfigurasi route di `application/config/routes.php`:
   ```php
   $prefix = $_ENV['DB_SYNC_PREFIX'] ?? 'db-sync';

   $route[$prefix . '/login'] = 'DbSyncController/login';
   $route[$prefix . '/logout'] = 'DbSyncController/logout';
   $route[$prefix] = 'DbSyncController/index';
   $route[$prefix . '/process'] = 'DbSyncController/process';
   $route[$prefix . '/sync-api'] = 'DbSyncController/handleApi';
   ```

---

## 🔒 Keamanan

1. Nilai `DB_SYNC_SECRET_KEY` dan `DB_SYNC_AUTH_PASSWORD` wajib dirahasiakan dan tidak dipublikasikan ke repository publik.
2. Setiap percobaan request AJAX `/process` akan memverifikasi sesi aktif; jika sesi kadaluarsa, respon `401 Unauthorized` akan dikembalikan dan UI akan mengarahkan pengguna ke halaman login.

---

## 📄 Lisensi

Package ini dirilis di bawah lisensi [MIT](LICENSE).
