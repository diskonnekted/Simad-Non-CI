<?php
// Database Configuration for Hosting Server
// File ini khusus untuk server hosting desaonline.cloud
// Ganti nama file ini menjadi database.php di server

// Database Configuration for Production Hosting
// PENTING: Sesuaikan kredensial ini dengan database hosting Anda
define('DB_HOST', 'localhost'); // atau sesuai dengan host database hosting
define('DB_NAME', 'smd'); // sesuaikan dengan nama database di hosting
define('DB_USER', 'smdadmin'); // sesuaikan dengan username database hosting
define('DB_PASS', 'Dikantor@5474'); // sesuaikan dengan password database hosting
define('DB_CHARSET', 'utf8mb4');

// Application Configuration for Production
define('APP_URL', 'https://desaonline.cloud');
define('APP_NAME', 'SIMAD - Sistem Informasi Manajemen Desa');
define('APP_VERSION', '1.0.0');

// Security Configuration
define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_EXPIRE', 1800);

// File Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Error Reporting for Production (disable display_errors)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error_log');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Timezone
date_default_timezone_set('Asia/Jakarta');

/**
 * Database Class untuk koneksi dan operasi database
 */
class Database {
    private $connection;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please contact administrator.");
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function select($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database select error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database selectOne error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function insert($table, $data) {
        try {
            $columns = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->connection->prepare($query);
            $stmt->execute($data);
            
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database insert error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $setClause = [];
            foreach (array_keys($data) as $key) {
                $setClause[] = "{$key} = :{$key}";
            }
            $setClause = implode(', ', $setClause);
            
            $query = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array_merge($data, $whereParams));
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database update error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function delete($table, $where, $whereParams = []) {
        try {
            $query = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->connection->prepare($query);
            $stmt->execute($whereParams);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database delete error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
}

// Global database instance
$db = new Database();

// Helper function untuk mendapatkan koneksi database
function getDB() {
    global $db;
    return $db;
}

// Helper function untuk mendapatkan PDO connection
function getPDO() {
    global $db;
    return $db->getConnection();
}

?>

<!-- 
INSTRUKSI UPLOAD KE SERVER:
1. Upload file ini ke server hosting
2. Rename file ini menjadi 'database.php' di folder config/
3. Edit kredensial database sesuai dengan hosting:
   - DB_HOST: biasanya 'localhost' atau IP server database
   - DB_NAME: nama database yang dibuat di hosting
   - DB_USER: username database hosting
   - DB_PASS: password database hosting
4. Pastikan database sudah dibuat dan tabel-tabel sudah diimport
5. Test akses dengan file test_connection.php
-->