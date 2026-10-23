<?php
namespace DbSync\Core;

class ConfigResolver {
    /**
     * Mengambil nilai konfigurasi dari environment atau helper framework
     */
    public static function getEnv(string $key, $default = null) {
        // 1. Laravel env() helper jika tersedia
        if (function_exists('env')) {
            $val = env($key);
            if ($val !== null) {
                return $val;
            }
        }

        // 2. CodeIgniter 4 env() helper jika tersedia
        // (sudah tercakup oleh check function_exists('env'))

        // 3. $_ENV
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        // 4. $_SERVER
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        // 5. getenv()
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }

        return $default;
    }

    /**
     * Mendapatkan kredensial database secara otomatis berdasarkan framework
     */
    public static function getDatabaseConfig(): array {
        $config = [
            'host'     => '127.0.0.1',
            'port'     => 3306,
            'database' => '',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4'
        ];

        // 1. Coba deteksi koneksi Laravel
        if (class_exists('\Illuminate\Support\Facades\DB')) {
            try {
                $connection = \Illuminate\Support\Facades\DB::connection();
                $dbConfig = $connection->getConfig();
                if (!empty($dbConfig['database'])) {
                    $config['host']     = $dbConfig['host'] ?? '127.0.0.1';
                    $config['port']     = (int)($dbConfig['port'] ?? 3306);
                    $config['database'] = $dbConfig['database'];
                    $config['username'] = $dbConfig['username'] ?? 'root';
                    $config['password'] = $dbConfig['password'] ?? '';
                    $config['charset']  = $dbConfig['charset'] ?? 'utf8mb4';
                    return $config;
                }
            } catch (\Throwable $e) {
                // Lanjut ke fallback jika koneksi Laravel belum siap
            }
        }

        // 2. Coba deteksi CodeIgniter 4
        if (class_exists('\Config\Database')) {
            try {
                $db = \Config\Database::connect();
                if (!empty($db->database)) {
                    $config['host']     = $db->hostname ?: '127.0.0.1';
                    $config['port']     = (int)($db->port ?: 3306);
                    $config['database'] = $db->database;
                    $config['username'] = $db->username ?: 'root';
                    $config['password'] = $db->password ?: '';
                    $config['charset']  = $db->charset ?: 'utf8mb4';
                    return $config;
                }
            } catch (\Throwable $e) {
                // Lanjut ke fallback
            }
        }

        // 3. Coba deteksi CodeIgniter 3
        if (function_exists('get_instance')) {
            $ci = &get_instance();
            if (isset($ci->db) && !empty($ci->db->database)) {
                $config['host']     = $ci->db->hostname ?: '127.0.0.1';
                $config['port']     = (int)(!empty($ci->db->port) ? $ci->db->port : 3306);
                $config['database'] = $ci->db->database;
                $config['username'] = $ci->db->username ?: 'root';
                $config['password'] = $ci->db->password ?: '';
                $config['charset']  = $ci->db->char_set ?: 'utf8mb4';
                return $config;
            }
        }

        // 4. Fallback ke Environment Variables (.env / getenv)
        $config['host']     = (string) self::getEnv('DB_HOST', '127.0.0.1');
        $config['port']     = (int) self::getEnv('DB_PORT', 3306);
        $config['database'] = (string) self::getEnv('DB_DATABASE', '');
        $config['username'] = (string) self::getEnv('DB_USERNAME', 'root');
        $config['password'] = (string) self::getEnv('DB_PASSWORD', '');
        $config['charset']  = (string) self::getEnv('DB_CHARSET', 'utf8mb4');

        return $config;
    }

    /**
     * Mendapatkan CSRF Token untuk request form di browser (Laravel / CI4 / none)
     */
    public static function getCsrfToken(): array {
        // Laravel
        if (function_exists('csrf_token')) {
            return [
                'name'  => '_token',
                'value' => csrf_token(),
                'header'=> 'X-CSRF-TOKEN'
            ];
        }

        // CodeIgniter 4
        if (function_exists('csrf_token') && function_exists('csrf_hash')) {
            return [
                'name'  => csrf_token(),
                'value' => csrf_hash(),
                'header'=> 'X-CSRF-TOKEN'
            ];
        }

        return [
            'name'  => '',
            'value' => '',
            'header'=> ''
        ];
    }
}

