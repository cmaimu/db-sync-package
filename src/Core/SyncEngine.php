<?php
namespace DbSync\Core;

use PDO;
use Exception;

class SyncEngine {
    private $pdo;
    private $dbConfig;

    public function __construct(array $dbConfig = []) {
        if (empty($dbConfig)) {
            $dbConfig = ConfigResolver::getDatabaseConfig();
        }

        $this->dbConfig = $dbConfig;
        $port = !empty($dbConfig['port']) ? (int)$dbConfig['port'] : 3306;
        $charset = !empty($dbConfig['charset']) ? $dbConfig['charset'] : 'utf8mb4';

        $dsn = "mysql:host={$dbConfig['host']};port={$port};dbname={$dbConfig['database']};charset={$charset}";

        $this->pdo = new PDO(
            $dsn,
            $dbConfig['username'],
            $dbConfig['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    /**
     * Mengambil daftar seluruh tabel yang ada di database lokal
     */
    public function getTables(): array {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Memvalidasi apakah nama tabel valid dan terdaftar di database
     */
    public function isValidTable(string $table): bool {
        // Hanya izinkan karakter alfanumerik dan underscore
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        $tables = $this->getTables();
        return in_array($table, $tables, true);
    }

    /**
     * Menghitung total baris pada suatu tabel
     */
    public function getRowCount(string $table): int {
        if (!$this->isValidTable($table)) {
            throw new Exception("Nama tabel tidak valid: '{$table}'");
        }
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ekspor data tabel dengan proteksi SQL dan opsi paging/chunking
     */
    public function exportData(string $table, int $offset = 0, ?int $limit = null): array {
        if (!$this->isValidTable($table)) {
            throw new Exception("Nama tabel tidak valid: '{$table}'");
        }

        $sql = "SELECT * FROM `{$table}`";
        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Impor data ke dalam tabel menggunakan transaksi PDO dan sanitasi kolom
     */
    public function importData(string $table, array $data): array {
        if (empty($data)) {
            return [
                'inserted' => 0,
                'message'  => 'Tidak ada data untuk diimpor'
            ];
        }

        if (!$this->isValidTable($table)) {
            throw new Exception("Nama tabel tidak valid: '{$table}'");
        }

        // Ambil dan validasi nama-nama kolom dari baris pertama
        $columns = array_keys($data[0]);
        foreach ($columns as $col) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                throw new Exception("Nama kolom tidak valid: '{$col}' pada tabel '{$table}'");
            }
        }

        $colsStr = '`' . implode('`, `', $columns) . '`';
        $placeholders = ':' . implode(', :', $columns);
        $sql = "REPLACE INTO `{$table}` ({$colsStr}) VALUES ({$placeholders})";

        $count = 0;
        try {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare($sql);
            foreach ($data as $row) {
                // Pastikan nilai NULL atau tipe data sesuai parameter
                $params = [];
                foreach ($columns as $col) {
                    $params[':' . $col] = $row[$col] ?? null;
                }
                $stmt->execute($params);
                $count++;
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } finally {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
        }

        return [
            'inserted' => $count,
            'message'  => "Berhasil memproses {$count} baris pada tabel '{$table}'"
        ];
    }

    /**
     * Mengirim atau meminta data ke server remote via cURL
     */
    public function sendToRemote(string $remoteUrl, string $apiKey, string $table, string $direction): array {
        $cleanUrl = rtrim($remoteUrl, '/') . '/sync-api';
        $ch = curl_init($cleanUrl);

        $payload = [
            'api_key' => $apiKey,
            'table'   => $table,
            'action'  => $direction,
            'data'    => ($direction === 'local_to_remote' || $direction === 'both') ? $this->exportData($table) : []
        ];

        $jsonData = json_encode($payload);
        $sslVerify = ConfigResolver::getEnv('DB_SYNC_SSL_VERIFY', 'true');
        $sslVerifyBool = !($sslVerify === false || $sslVerify === 'false' || $sslVerify === '0');

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ],
            CURLOPT_TIMEOUT        => 300, // 5 menit untuk tabel besar
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $sslVerifyBool,
            CURLOPT_SSL_VERIFYHOST => $sslVerifyBool ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        curl_close($ch);

        if ($curlErrNo !== 0) {
            return [
                'success' => false,
                'error'   => "Gagal koneksi ke remote ({$cleanUrl}): {$curlErr}"
            ];
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $decoded['error'] ?? "Remote server mengembalikan status HTTP {$httpCode}";
            return [
                'success' => false,
                'error'   => $msg,
                'raw'     => $response
            ];
        }

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'error'   => "Respon dari remote bukan JSON valid (HTTP {$httpCode}): " . substr(strip_tags($response), 0, 200)
            ];
        }

        return $decoded;
    }
}
