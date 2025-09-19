# 🚀 PANDUAN INSTALASI SIMAD DI HOSTING

Panduan lengkap untuk menginstal aplikasi SIMAD (Sistem Informasi Manajemen Desa) di hosting dengan database MySQL.

## 📋 Persyaratan Hosting

### Minimum Requirements
- **PHP**: 7.4 atau lebih tinggi
- **MySQL**: 5.7 atau lebih tinggi (atau MariaDB 10.2+)
- **Web Server**: Apache atau Nginx
- **Storage**: Minimal 100MB ruang disk
- **Memory**: Minimal 128MB PHP memory limit

### Extensions PHP yang Diperlukan
- `pdo_mysql`
- `mysqli`
- `json`
- `session`
- `fileinfo`
- `gd` (untuk upload gambar)

## 🗂️ Struktur File yang Diupload

Pastikan semua file berikut sudah diupload ke hosting:

```
simad/
├── config/
│   └── (akan dibuat otomatis)
├── database/
│   └── database.sql
├── assets/
├── pages/
├── includes/
├── uploads/ (akan dibuat otomatis)
├── logs/ (akan dibuat otomatis)
├── index.php
├── install_hosting.php
└── (file lainnya...)
```

## 🔧 Langkah-Langkah Instalasi

### Step 1: Persiapan Database di Hosting

1. **Login ke Control Panel Hosting** (cPanel, Plesk, dll)
2. **Buat Database MySQL Baru**:
   - Nama database: `u858602090_mad` (sesuai konfigurasi)
   - Username: `u858602090_mad`
   - Password: `Dikantor@5474YAH`
3. **Berikan Privilege Penuh** kepada user untuk database tersebut

### Step 2: Upload File Aplikasi

1. **Compress** semua file aplikasi menjadi ZIP
2. **Upload** file ZIP ke hosting via File Manager atau FTP
3. **Extract** file ZIP di direktori public_html atau domain folder
4. **Set Permission** folder uploads dan logs ke 755 atau 777

### Step 3: Jalankan Instalasi Otomatis

1. **Akses URL Instalasi**:
   ```
   https://simad.sistemdata.id/install_hosting.php
   ```

2. **Script akan otomatis**:
   - Test koneksi database
   - Membaca file `database/database.sql`
   - Mengeksekusi semua SQL statements
   - Membuat tabel dan insert data sample
   - Membuat file konfigurasi `config/database.php`
   - Membuat direktori yang diperlukan

3. **Monitor Progress**:
   - Script akan menampilkan progress real-time
   - Lihat berapa statement SQL yang berhasil/gagal
   - Periksa tabel yang berhasil dibuat

### Step 4: Verifikasi Instalasi

1. **Cek Hasil Instalasi**:
   - Pastikan muncul pesan "🎉 INSTALASI HOSTING BERHASIL! 🎉"
   - Minimal 15+ tabel harus berhasil dibuat
   - File `config/database.php` harus terbuat

2. **Test Aplikasi**:
   ```
   https://simad.sistemdata.id/index.php
   ```

3. **Login Admin**:
   - Username: `admin`
   - Password: `admin123` (ganti setelah login pertama)

### Step 5: Keamanan Post-Installation

1. **Hapus File Instalasi**:
   ```bash
   rm install_hosting.php
   ```

2. **Ganti Password Admin**:
   - Login ke aplikasi
   - Masuk ke menu User Management
   - Ganti password default admin

3. **Set Permission yang Aman**:
   - File PHP: 644
   - Direktori: 755
   - Config files: 600

## 🔍 Troubleshooting

### Error: "Access denied for user"

**Penyebab**: Username/password database salah atau user tidak memiliki akses

**Solusi**:
1. Periksa kembali kredensial database di control panel
2. Pastikan user memiliki privilege penuh ke database
3. Edit konfigurasi di `install_hosting.php` jika perlu

### Error: "Table doesn't exist"

**Penyebab**: File `database.sql` tidak ditemukan atau rusak

**Solusi**:
1. Pastikan file `database/database.sql` sudah diupload
2. Periksa permission file (harus readable)
3. Cek isi file tidak corrupt

### Error: "Can't create table"

**Penyebab**: User database tidak memiliki privilege CREATE

**Solusi**:
1. Berikan privilege penuh (ALL PRIVILEGES) ke user database
2. Atau gunakan user root jika memungkinkan

### Error: "File not found" untuk config

**Penyebab**: Direktori config tidak writable

**Solusi**:
1. Buat direktori `config/` manual jika belum ada
2. Set permission direktori ke 755 atau 777

### Aplikasi tidak bisa diakses

**Penyebab**: File `index.php` tidak ditemukan atau error PHP

**Solusi**:
1. Pastikan file `index.php` ada di root domain
2. Periksa error log hosting untuk detail error
3. Pastikan PHP version compatibility

## 📊 Monitoring dan Maintenance

### Backup Database

```bash
# Via command line (jika tersedia)
mysqldump -u u858602090_mad -p u858602090_mad > backup_simad.sql

# Via phpMyAdmin
# Export > Custom > SQL format
```

### Update Aplikasi

1. **Backup** database dan file terlebih dahulu
2. **Upload** file aplikasi terbaru
3. **Jalankan** script update jika ada
4. **Test** fungsionalitas aplikasi

### Monitor Performance

- **Database Size**: Periksa ukuran database secara berkala
- **File Uploads**: Monitor direktori uploads
- **Error Logs**: Periksa file `logs/error.log`
- **Session Files**: Bersihkan session lama jika perlu

## 🆘 Support dan Bantuan

### Jika Instalasi Gagal Total

1. **Manual Installation via phpMyAdmin**:
   - Login ke phpMyAdmin
   - Import file `database/database.sql`
   - Buat file `config/database.php` manual

2. **Hubungi Support Hosting**:
   - Minta bantuan untuk troubleshoot database
   - Periksa log error server

3. **Alternative Installation**:
   - Gunakan script `smart_sql_install.php` (untuk development)
   - Modifikasi konfigurasi sesuai hosting

### File Konfigurasi Manual

Jika script gagal membuat `config/database.php`, buat manual:

```php
<?php
// Production Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u858602090_mad');
define('DB_USER', 'u858602090_mad');
define('DB_PASS', 'Dikantor@5474YAH');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_URL', 'https://simad.sistemdata.id');
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
?>
```

## ✅ Checklist Post-Installation

- [ ] Database berhasil dibuat dan terkoneksi
- [ ] Minimal 15+ tabel berhasil dibuat
- [ ] File `config/database.php` terbuat
- [ ] Direktori `uploads/` dan `logs/` terbuat
- [ ] Aplikasi dapat diakses via browser
- [ ] Login admin berhasil
- [ ] File instalasi sudah dihapus
- [ ] Password admin sudah diganti
- [ ] Permission file sudah diatur dengan aman
- [ ] Backup database sudah dibuat

---

**🎉 Selamat! Aplikasi SIMAD sudah siap digunakan di hosting!**

Untuk pertanyaan lebih lanjut, silakan hubungi tim support atau baca dokumentasi aplikasi.