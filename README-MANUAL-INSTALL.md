# Panduan Instalasi Manual (Lokal / Private Repo)

Panduan ini digunakan jika Anda mengembangkan atau menguji package ini secara lokal tanpa melalui Packagist.

---

### 1. Struktur Folder Project Anda

Tempatkan folder `db-sync-package` di dalam folder `packages/` pada project Anda:

```text
project-anda/
├── app/ (atau application/)
├── packages/
│   └── db-sync-package/      <-- Folder package ini diletakkan di sini
│       ├── composer.json
│       ├── src/
│       ├── LICENSE
│       └── README.md
├── composer.json              <-- File composer milik project utama
└── .env
```

---

### 2. Konfigurasi `composer.json` Project Utama

Buka file `composer.json` di root project Anda, lalu tambahkan bagian `repositories` bertipe `path` dan panggil package `cmdimu/db-sync-package`:

```json
{
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
}
```

---

### 3. Jalankan Update Composer

Jalankan perintah berikut di root folder project Anda:

```bash
composer update cmdimu/db-sync-package
```

Composer akan membuat symlink lokal dari folder `packages/db-sync-package` ke dalam folder `vendor/cmdimu/db-sync-package`.

---

### 4. Konfigurasi `.env`

Tambahkan variabel berikut ke dalam file `.env` project Anda:

```env
DB_SYNC_MODE=client
DB_SYNC_PREFIX=db-sync
DB_SYNC_REMOTE_URL=https://target-server.com/db-sync
DB_SYNC_SECRET_KEY=KunciRahasiaSuperAman123!
DB_SYNC_SSL_VERIFY=true
```

---

### 5. Verifikasi Akses

Buka browser dan akses URL:
```
http://domain-project-anda.test/db-sync
```
Dashboard sinkronisasi akan terbuka dan siap digunakan.
