<?php
// Database Configuration for Localhost Development
define('DB_HOST', 'localhost');
define('DB_NAME', 'smd');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_URL', 'http://localhost/smd');
define('APP_NAME', 'SIMAD - Sistem Informasi Manajemen Desa');
define('APP_VERSION', '1.0.0');

// Security Configuration
define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_EXPIRE', 1800);

// File Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Error Reporting for Development
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL);

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
    
    public function update($table, $data, $where) {
        try {
            $setClause = [];
            foreach ($data as $key => $value) {
                $setClause[] = "{$key} = :{$key}";
            }
            $setClause = implode(', ', $setClause);
            
            $whereClause = [];
            foreach ($where as $key => $value) {
                $whereClause[] = "{$key} = :where_{$key}";
            }
            $whereClause = implode(' AND ', $whereClause);
            
            $query = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";
            $stmt = $this->connection->prepare($query);
            
            // Bind data parameters
            foreach ($data as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            
            // Bind where parameters
            foreach ($where as $key => $value) {
                $stmt->bindValue(":where_{$key}", $value);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database update error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function delete($table, $where) {
        try {
            $whereClause = [];
            foreach ($where as $key => $value) {
                $whereClause[] = "{$key} = :{$key}";
            }
            $whereClause = implode(' AND ', $whereClause);
            
            $query = "DELETE FROM {$table} WHERE {$whereClause}";
            $stmt = $this->connection->prepare($query);
            
            return $stmt->execute($where);
        } catch (PDOException $e) {
            error_log("Database delete error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function execute($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database execute error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
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

/**
 * Fungsi global untuk mendapatkan instance database
 * @return Database
 */
function getDatabase() {
    static $database = null;
    if ($database === null) {
        $database = new Database();
    }
    return $database;
}

/**
 * Fungsi untuk mendapatkan koneksi PDO langsung
 * @return PDO
 */
function getDBConnection() {
    return getDatabase()->getConnection();
}

// Check if running in production environment
function isProduction() {
    return !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', 'localhost:8000']);
}

// Get current environment
function getEnvironment() {
    return isProduction() ? 'production' : 'development';
}

// Initialize global $pdo variable for backward compatibility
$pdo = getDBConnection();

?>
