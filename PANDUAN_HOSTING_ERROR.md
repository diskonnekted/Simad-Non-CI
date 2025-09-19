# Panduan Mengatasi Error "Call to undefined function getDatabase()" di Hosting

## 🚨 Masalah yang Terjadi

Error log menunjukkan:
```
PHP Fatal error: Uncaught Error: Call to undefined function getDatabase() 
in /home/u858602090/domains/simad.sistemdata.id/public_html/config/auth.php:20
```

## 🔍 Penyebab Masalah

1. **File `config/database.php` tidak ter-upload dengan lengkap**
2. **File `config/database.php` masih menggunakan versi lama**
3. **Fungsi `getDatabase()` tidak terdefinisi di file database.php**
4. **Permission file tidak sesuai di hosting**
5. **Path include tidak benar di hosting**

## ✅ Solusi Langkah demi Langkah

### 1. Verifikasi File Lokal

**Jalankan script verifikasi:**
- Buka: `http://localhost:8000/verify_includes.php`
- Pastikan semua test berhasil di lokal
- Jika ada error, gunakan tombol "Buat Database.php Sederhana"

### 2. Upload File yang Benar ke Hosting

**File yang harus di-upload:**
```
config/database.php     (File utama - WAJIB)
config/auth.php         (Sudah benar)
index.php              (File utama)
```

**Cara upload yang benar:**
1. Login ke File Manager hosting atau FTP
2. Navigasi ke folder `public_html`
3. Upload file `config/database.php` (overwrite yang lama)
4. Pastikan struktur folder:
   ```
   public_html/
   ├── config/
   │   ├── database.php
   │   └── auth.php
   ├── index.php
   └── ...
   ```

### 3. Set Permission File

**Permission yang benar:**
- `config/database.php` → 644
- `config/auth.php` → 644
- `index.php` → 644
- Folder `config/` → 755

### 4. Verifikasi di Hosting

**Upload dan jalankan script debug:**
1. Upload `fix_database_hosting.php` ke root hosting
2. Akses: `https://simad.sistemdata.id/fix_database_hosting.php`
3. Jalankan diagnosis dan perbaikan otomatis

### 5. Test Koneksi Database

**Pastikan kredensial database benar:**
```php
DB_HOST: localhost
DB_NAME: u858602090_mad
DB_USER: u858602090_mad
DB_PASS: Madrasah123
```

## 🛠️ Script Perbaikan Otomatis

### A. Fix Database Hosting
```bash
# Upload dan akses:
https://simad.sistemdata.id/fix_database_hosting.php
```

### B. Verify Includes
```bash
# Upload dan akses:
https://simad.sistemdata.id/verify_includes.php
```

## 📋 Checklist Perbaikan

- [ ] ✅ File `config/database.php` ter-upload dengan lengkap
- [ ] ✅ Permission file sudah benar (644)
- [ ] ✅ Fungsi `getDatabase()` ada di database.php
- [ ] ✅ Class `Database` terdefinisi dengan benar
- [ ] ✅ Kredensial database sudah benar
- [ ] ✅ Koneksi database berhasil
- [ ] ✅ Website dapat diakses tanpa error 500

## 🔧 Solusi Cepat (Emergency Fix)

Jika masalah masih berlanjut, buat file `config/database.php` minimal:

```php
<?php
// Emergency database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u858602090_mad');
define('DB_USER', 'u858602090_mad');
define('DB_PASS', 'Madrasah123');

class Database {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

function getDatabase() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}
?>
```

## 📞 Bantuan Lebih Lanjut

### Jika masalah masih berlanjut:

1. **Cek Error Log Hosting:**
   - Login cPanel → Error Logs
   - Lihat error terbaru

2. **Hubungi Support Hosting:**
   - Tanyakan tentang PHP version
   - Minta bantuan cek permission file

3. **Test Manual:**
   ```bash
   # Akses langsung file:
   https://simad.sistemdata.id/config/database.php
   # Seharusnya tidak menampilkan apa-apa (blank page)
   ```

## 🎯 Hasil yang Diharapkan

Setelah perbaikan berhasil:
- ✅ Website dapat diakses normal
- ✅ Tidak ada error 500
- ✅ Login system berfungsi
- ✅ Database terhubung dengan baik

---

**Catatan:** Simpan file ini sebagai referensi dan ikuti langkah-langkah secara berurutan untuk hasil terbaik.