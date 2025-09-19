# Solusi Error #1044 - Access Denied for User to Database

## Deskripsi Error
```
#1044 - Access denied for user 'cpses_sim4a5qcf0'@'localhost' to database 'simadevorbitdev_simad'
```

Error ini terjadi ketika user database tidak memiliki akses ke database yang ditentukan di hosting.

## Penyebab Umum
1. **Database name salah** - Nama database di konfigurasi tidak sesuai dengan yang ada di hosting
2. **User tidak memiliki privilege** - User database belum diberikan akses ke database
3. **Konfigurasi database salah** - File konfigurasi masih menggunakan setting localhost

## Solusi Langkah demi Langkah

### 1. Periksa Informasi Database di cPanel
- Login ke cPanel hosting Anda
- Buka **MySQL Databases**
- Catat informasi berikut:
  - Database name (biasanya: `cpanel_username_database_name`)
  - Database username (biasanya: `cpanel_username_db_user`)
  - Database password

### 2. Update Konfigurasi Database untuk Hosting

Edit file `config/database_production.php` dengan kredensial yang benar:

```php
<?php
// Production Database Configuration
// GANTI DENGAN KREDENSIAL HOSTING ANDA
define('DB_HOST', 'localhost');                    // Biasanya 'localhost'
define('DB_NAME', 'cpses_sim4a5qcf0_simad');      // Nama database dari cPanel
define('DB_USER', 'cpses_sim4a5qcf0_user');       // Username database dari cPanel
define('DB_PASS', 'password_database_anda');       // Password database dari cPanel
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_URL', 'https://simadevorbitdev.sistemdata.id'); // URL domain Anda
define('APP_NAME', 'SIMAD - Sistem Informasi Manajemen Desa');
define('APP_VERSION', '1.0.0');

// Security Configuration
define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_EXPIRE', 1800);

// File Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Error Reporting for Production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Database Connection Function
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please contact administrator.");
    }
}

// Check if running in production environment
function isProduction() {
    return !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', 'localhost:8000']);
}

// Get current environment
function getEnvironment() {
    return isProduction() ? 'production' : 'development';
}
?>
```

### 3. Buat File Auto-Detect Environment

Buat file `config/database_auto.php` untuk otomatis memilih konfigurasi:

```php
<?php
// Auto-detect environment and load appropriate config
if (!in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', 'localhost:8000'])) {
    // Production environment
    require_once __DIR__ . '/database_production.php';
} else {
    // Development environment
    require_once __DIR__ . '/database.php';
}
?>
```

### 4. Update File Utama untuk Menggunakan Auto-Config

Ganti semua `require_once 'config/database.php'` dengan:
```php
require_once 'config/database_auto.php';
```

### 5. Verifikasi Database di cPanel

1. **Pastikan database sudah dibuat:**
   - Masuk ke cPanel → MySQL Databases
   - Pastikan database dengan nama yang benar sudah ada

2. **Pastikan user memiliki akses:**
   - Di bagian "Add User To Database"
   - Pilih user dan database
   - Berikan privilege "ALL PRIVILEGES"

3. **Test koneksi:**
   - Buat file `test_connection.php`:
   ```php
   <?php
   require_once 'config/database_production.php';
   
   try {
       $pdo = getDBConnection();
       echo "Koneksi database berhasil!";
   } catch (Exception $e) {
       echo "Error: " . $e->getMessage();
   }
   ?>
   ```

### 6. Import Database

Setelah koneksi berhasil, import struktur database:

1. **Via phpMyAdmin:**
   - Login ke phpMyAdmin di hosting
   - Pilih database yang benar
   - Import file `database/database.sql`

2. **Via File PHP:**
   - Upload file `import_database.php` yang sudah ada
   - Akses via browser untuk import otomatis

## Troubleshooting Tambahan

### Jika Masih Error Setelah Update Konfigurasi:

1. **Periksa nama database yang tepat:**
   ```sql
   SHOW DATABASES;
   ```

2. **Periksa privilege user:**
   ```sql
   SHOW GRANTS FOR 'username'@'localhost';
   ```

3. **Buat user baru jika diperlukan:**
   - Di cPanel → MySQL Databases
   - Buat user baru dengan password yang kuat
   - Assign ke database dengan ALL PRIVILEGES

### Error Umum Lainnya:

- **"Database does not exist"** → Pastikan nama database benar
- **"Access denied for user"** → Periksa username dan password
- **"Host not allowed"** → Gunakan 'localhost' sebagai DB_HOST

## Checklist Verifikasi

- [ ] Database sudah dibuat di cPanel
- [ ] User database sudah dibuat di cPanel
- [ ] User sudah di-assign ke database dengan ALL PRIVILEGES
- [ ] File `database_production.php` sudah diupdate dengan kredensial yang benar
- [ ] Test koneksi berhasil
- [ ] Database sudah diimport
- [ ] Aplikasi bisa diakses tanpa error

## Kontak Support

Jika masih mengalami masalah, hubungi:
- Support hosting provider untuk bantuan database
- Developer aplikasi untuk konfigurasi khusus

---
**Catatan:** Selalu backup database sebelum melakukan perubahan konfigurasi.