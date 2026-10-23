# Database Sync Package (`cmdimu/db-sync-package`)

Package untuk sinkronisasi database MySQL/MariaDB (Local &harr; Remote) berbasis REST API untuk **Laravel**, **CodeIgniter 4 (CI4)**, dan **CodeIgniter 3 (CI3)**.

---

## 🚀 Fitur Utama

- **Multi-Framework**: Mendukung Laravel (Auto-discovery), CodeIgniter 4, dan CodeIgniter 3.
- **Tiga Arah Sinkronisasi**:
  - `Local -> Remote`: Mengunggah data lokal ke server remote.
  - `Remote -> Local`: Mengunduh data dari server remote dan memperbarui database lokal.
  - `Both (Two-Way)`: Sinkronisasi dua arah.
- **Aman**: Dilengkapi autentikasi token berbasis API Key dengan proteksi *timing-attack* (`hash_equals`) dan pemisahan middleware CSRF untuk endpoint API.
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

# API Key rahasia untuk autentikasi sync (wajib sama antara local & remote)
DB_SYNC_SECRET_KEY=GantiDenganSecretKeyYangPanjangDanAman123!

# Verifikasi SSL (set ke false untuk local development dengan self-signed cert / Laragon)
DB_SYNC_SSL_VERIFY=true
```

---

## 🛠️ Setup per Framework

### 1. Laravel (Laravel 8, 9, 10, 11+)
- **Service Provider otomatis terdeteksi** via Composer Package Discovery.
- Jika ingin mempublish tampilan dashboard:
  ```bash
  php artisan vendor:publish --tag=db-sync-views
  ```
- Akses dashboard di browser:
  ```
  http://domain-anda.test/db-sync
  ```

---

### 2. CodeIgniter 4
Tambahkan route pada file `app/Config/Routes.php`:

```php
$prefix = env('DB_SYNC_PREFIX', 'db-sync');

$routes->group($prefix, function($routes) {
    // Dashboard & Action (Web)
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

       public function index() { $this->controller->index(); }
       public function process() { $this->controller->process(); }
       public function handleApi() { $this->controller->handleApi(); }
   }
   ```

3. Tambahkan konfigurasi route di `application/config/routes.php`:
   ```php
   $prefix = $_ENV['DB_SYNC_PREFIX'] ?? 'db-sync';

   $route[$prefix] = 'DbSyncController/index';
   $route[$prefix . '/process'] = 'DbSyncController/process';
   $route[$prefix . '/sync-api'] = 'DbSyncController/handleApi';
   ```

---

## 🔒 Keamanan

1. Jangan pernah mempublikasikan nilai `DB_SYNC_SECRET_KEY` ke repository publik.
2. Sangat disarankan untuk membatasi akses URL dashboard `/db-sync` di level web server (misal IP Whitelist) atau menambahkan middleware auth pada project Anda.

---

## 📄 Lisensi

Package ini dirilis di bawah lisensi [MIT](LICENSE).
       public function index() { $this->controller->index(); }
       public function process() { $this->controller->process(); }
       public function handleApi() { $this->controller->handleApi(); }
   }
   ```

3. Tambahkan konfigurasi route di `application/config/routes.php`:
   ```php
   $prefix = $_ENV['DB_SYNC_PREFIX'] ?? 'db-sync';

   $route[$prefix] = 'DbSyncController/index';
   $route[$prefix . '/process'] = 'DbSyncController/process';
   $route[$prefix . '/sync-api'] = 'DbSyncController/handleApi';
   ```

---

## 🔒 Keamanan

1. Jangan pernah mempublikasikan nilai `DB_SYNC_SECRET_KEY` ke repository publik.
2. Sangat disarankan untuk membatasi akses URL dashboard `/db-sync` di level web server (misal IP Whitelist) atau menambahkan middleware auth pada project Anda.

---

## 📄 Lisensi

Package ini dirilis di bawah lisensi [MIT](LICENSE).
