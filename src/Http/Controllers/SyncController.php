<?php
namespace DbSync\Http\Controllers;

use DbSync\Core\SyncEngine;
use DbSync\Core\ConfigResolver;
use DbSync\Core\AuthResolver;

class SyncController {
    private $engine;

    public function __construct() {
        $dbConfig = ConfigResolver::getDatabaseConfig();
        $this->engine = new SyncEngine($dbConfig);
    }

    /**
     * Halaman Login Dashboard
     */
    public function login() {
        $routePrefix = ConfigResolver::getEnv('DB_SYNC_PREFIX', 'db-sync');
        $basePath = '/' . trim($routePrefix, '/');

        // Jika sudah login, langsung lempar ke dashboard
        if (AuthResolver::isAuthenticated()) {
            header("Location: {$basePath}");
            exit;
        }

        $errorMessage = '';
        $csrf = ConfigResolver::getCsrfToken();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = (string)($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if (AuthResolver::attemptBuiltinLogin($username, $password)) {
                header("Location: {$basePath}");
                exit;
            } else {
                $errorMessage = 'Username atau Password tidak valid. Silakan periksa pengaturan .env Anda.';
            }
        }

        require __DIR__ . '/../../Views/login.php';
    }

    /**
     * Logout Session Dashboard
     */
    public function logout() {
        AuthResolver::logout();
        $routePrefix = ConfigResolver::getEnv('DB_SYNC_PREFIX', 'db-sync');
        $basePath = '/' . trim($routePrefix, '/');
        header("Location: {$basePath}/login");
        exit;
    }

    /**
     * Halaman Utama Dashboard Sinkronisasi
     */
    public function index() {
        $routePrefix = ConfigResolver::getEnv('DB_SYNC_PREFIX', 'db-sync');
        $basePath = '/' . trim($routePrefix, '/');

        // Proteksi autentikasi
        if (!AuthResolver::isAuthenticated()) {
            header("Location: {$basePath}/login");
            exit;
        }

        $tables = $this->engine->getTables();
        $csrf = ConfigResolver::getCsrfToken();
        $remoteUrl = ConfigResolver::getEnv('DB_SYNC_REMOTE_URL', '');
        $hasKey = !empty(ConfigResolver::getEnv('DB_SYNC_SECRET_KEY', ''));
        $userName = AuthResolver::getUserName();

        require __DIR__ . '/../../Views/index.php';
    }

    /**
     * Memproses sinkronisasi yang dipilih dari Dashboard UI
     */
    public function process() {
        header('Content-Type: application/json');

        // Proteksi autentikasi untuk request AJAX
        if (!AuthResolver::isAuthenticated()) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Unauthorized: Sesi Anda telah berakhir atau Anda belum login.'
            ]);
            return;
        }

        $tables = $_POST['tables'] ?? [];
        $directions = $_POST['direction'] ?? [];
        $remoteUrl = (string) ConfigResolver::getEnv('DB_SYNC_REMOTE_URL', '');
        $apiKey = (string) ConfigResolver::getEnv('DB_SYNC_SECRET_KEY', '');

        if (empty($remoteUrl)) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'DB_SYNC_REMOTE_URL belum dikonfigurasi di file environment (.env)'
            ]);
            return;
        }

        if (empty($apiKey)) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'DB_SYNC_SECRET_KEY belum dikonfigurasi di file environment (.env)'
            ]);
            return;
        }

        if (empty($tables) || !is_array($tables)) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Pilih minimal satu tabel untuk disinkronkan'
            ]);
            return;
        }

        $results = [];
        $hasFailures = false;

        foreach ($tables as $table) {
            $dir = $directions[$table] ?? 'local_to_remote';

            try {
                if ($dir === 'both') {
                    // 1. Tarik data remote terlebih dahulu
                    $remoteResponse = $this->engine->sendToRemote($remoteUrl, $apiKey, $table, 'remote_to_local');
                    if (empty($remoteResponse['success'])) {
                        $results[$table] = [
                            'success' => false,
                            'step'    => 'pull_remote',
                            'error'   => $remoteResponse['error'] ?? 'Gagal mengambil data dari remote'
                        ];
                        $hasFailures = true;
                        continue;
                    }

                    if (isset($remoteResponse['data']) && is_array($remoteResponse['data'])) {
                        $this->engine->importData($table, $remoteResponse['data']);
                    }

                    // 2. Kirim data lokal terbaru ke remote
                    $pushResponse = $this->engine->sendToRemote($remoteUrl, $apiKey, $table, 'local_to_remote');
                    $results[$table] = [
                        'success' => !empty($pushResponse['success']),
                        'details' => $pushResponse
                    ];
                    if (empty($pushResponse['success'])) {
                        $hasFailures = true;
                    }
                } elseif ($dir === 'remote_to_local') {
                    $remoteResponse = $this->engine->sendToRemote($remoteUrl, $apiKey, $table, 'remote_to_local');
                    if (!empty($remoteResponse['success']) && isset($remoteResponse['data'])) {
                        $importResult = $this->engine->importData($table, $remoteResponse['data']);
                        $results[$table] = [
                            'success' => true,
                            'message' => $importResult['message']
                        ];
                    } else {
                        $results[$table] = [
                            'success' => false,
                            'error'   => $remoteResponse['error'] ?? 'Gagal mengambil data dari remote'
                        ];
                        $hasFailures = true;
                    }
                } else { // local_to_remote
                    $pushResponse = $this->engine->sendToRemote($remoteUrl, $apiKey, $table, 'local_to_remote');
                    $results[$table] = $pushResponse;
                    if (empty($pushResponse['success'])) {
                        $hasFailures = true;
                    }
                }
            } catch (\Throwable $e) {
                $hasFailures = true;
                $results[$table] = [
                    'success' => false,
                    'error'   => $e->getMessage()
                ];
            }
        }

        echo json_encode([
            'status'  => $hasFailures ? 'partial_or_error' : 'success',
            'details' => $results
        ]);
    }

    /**
     * Endpoint API antar server (Remote Sync API)
     */
    public function handleApi() {
        header('Content-Type: application/json');

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Invalid JSON payload'
            ]);
            return;
        }

        $configuredKey = (string) ConfigResolver::getEnv('DB_SYNC_SECRET_KEY', '');
        $providedKey = (string) ($input['api_key'] ?? '');

        // Cegah bypass jika key belum diset
        if (empty($configuredKey) || !hash_equals($configuredKey, $providedKey)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Unauthorized: Invalid or missing API key'
            ]);
            return;
        }

        $table = (string) ($input['table'] ?? '');
        $action = (string) ($input['action'] ?? '');

        if (empty($table) || empty($action)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Parameter table dan action wajib diisi'
            ]);
            return;
        }

        try {
            if ($action === 'local_to_remote') {
                $rows = $input['data'] ?? [];
                $result = $this->engine->importData($table, $rows);
                echo json_encode([
                    'success' => true,
                    'message' => "Tabel '{$table}' berhasil diperbarui di remote",
                    'details' => $result
                ]);
            } elseif ($action === 'remote_to_local') {
                $data = $this->engine->exportData($table);
                echo json_encode([
                    'success' => true,
                    'message' => "Data tabel '{$table}' berhasil diambil",
                    'data'    => $data
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => "Action '{$action}' tidak didukung"
                ]);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }
}
