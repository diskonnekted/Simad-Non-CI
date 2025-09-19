# SIMAD - Panduan Deployment ke Hosting

Panduan lengkap untuk mengupload dan menginstall aplikasi SIMAD (Sistem Informasi Manajemen Desa) ke hosting website dengan domain `simad.sistemdata.id`.

## 📋 Persiapan Sebelum Upload

### 1. Persyaratan Hosting
Pastikan hosting Anda mendukung:
- **PHP 7.4 atau lebih tinggi**
- **MySQL 5.7 atau MariaDB 10.2+**
- **PDO Extension** (biasanya sudah aktif)
- **mod_rewrite** untuk Apache
- **SSL Certificate** (untuk HTTPS)

### 2. Persiapan Database
1. Login ke **cPanel** atau panel hosting Anda
2. Buka **MySQL Databases**
3. Buat database baru (contoh: `simad_database`)
4. Buat user database dan berikan semua privileges
5. Catat informasi berikut:
   - Database Host (biasanya `localhost`)
   - Database Name
   - Database Username
   - Database Password

## 🚀 Langkah-langkah Deployment

### Step 1: Upload File ke Hosting

1. **Compress Project**
   ```bash
   # Buat file ZIP dari project (exclude folder yang tidak perlu)
   zip -r simad.zip . -x "node_modules/*" "tidak\ dipakai/*" ".git/*" "*.log"
   ```

2. **Upload via File Manager atau FTP**
   - Login ke cPanel → File Manager
   - Navigasi ke folder `public_html` (atau subdomain folder)
   - Upload file `simad.zip`
   - Extract file ZIP
   - Hapus file ZIP setelah extract

3. **Set Permissions**
   ```
   config/          → 755
   uploads/         → 755 (akan dibuat otomatis)
   logs/            → 755 (akan dibuat otomatis)
   .htaccess        → 644
   ```

### Step 2: Konfigurasi Domain

1. **Subdomain Setup** (jika menggunakan subdomain)
   - Di cPanel → Subdomains
   - Buat subdomain: `simad.sistemdata.id`
   - Document Root: `/public_html/simad` (sesuaikan path)

2. **DNS Configuration**
   - Pastikan DNS A record mengarah ke IP hosting
   - Tunggu propagasi DNS (1-24 jam)

### Step 3: Instalasi Aplikasi

1. **Akses Installer**
   - Buka browser: `https://simad.sistemdata.id/install.php`
   - Ikuti wizard instalasi

2. **System Requirements Check**
   - Installer akan mengecek persyaratan sistem
   - Pastikan semua requirements berwarna hijau (OK)

3. **Database Configuration**
   - Masukkan informasi database yang sudah disiapkan:
     ```
     Database Host: localhost
     Database Name: simad_database
     Database Username: [username dari hosting]
     Database Password: [password dari hosting]
     Application URL: https://simad.sistemdata.id
     ```

4. **Install Database Tables**
   - Klik "Install Database"
   - Tunggu proses pembuatan tabel selesai

5. **Create Admin User**
   - Buat akun administrator:
     ```
     Admin Username: admin
     Admin Password: [password yang kuat]
     Admin Email: [email admin]
     ```

6. **Complete Installation**
   - Klik "Complete Installation"
   - Installer akan membuat file konfigurasi production

### Step 4: Finalisasi

1. **Hapus File Installer**
   ```bash
   # Hapus file install.php untuk keamanan
   rm install.php
   ```

2. **Test Aplikasi**
   - Akses: `https://simad.sistemdata.id`
   - Login dengan akun admin yang dibuat
   - Test semua fitur utama

## 🔧 Konfigurasi Lanjutan

### SSL Certificate
1. **Aktifkan SSL** di cPanel → SSL/TLS
2. **Force HTTPS** dengan mengedit `.htaccess`:
   ```apache
   # Uncomment baris berikut di .htaccess
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

### Email Configuration
Jika aplikasi menggunakan email:
1. Setup **SMTP** di hosting
2. Update konfigurasi email di `config/database_production.php`

### Backup Otomatis
1. Setup **cron job** untuk backup database:
   ```bash
   # Jalankan setiap hari jam 2 pagi
   0 2 * * * mysqldump -u [username] -p[password] [database] > /path/to/backup/simad_$(date +\%Y\%m\%d).sql
   ```

## 🛡️ Keamanan

### File Permissions
```
config/database_production.php → 600 (hanya owner yang bisa read/write)
config/installed.lock         → 644
.htaccess                     → 644
uploads/                      → 755
logs/                         → 755
```

### Security Headers
File `.htaccess` sudah dikonfigurasi dengan:
- X-Frame-Options
- X-XSS-Protection
- X-Content-Type-Options
- Referrer-Policy

### File Protection
- Config files dilindungi dari akses langsung
- Database files tidak dapat diakses
- Version control files (.git) diblokir

## 🔍 Troubleshooting

### Error 500 Internal Server Error
1. **Check Error Logs**
   ```bash
   tail -f logs/error.log
   # atau check error log di cPanel
   ```

2. **Common Issues:**
   - File permissions salah
   - PHP version tidak kompatibel
   - Missing PHP extensions
   - Database connection error

### Database Connection Error
1. **Verify Database Credentials**
2. **Check Database Server Status**
3. **Test Connection:**
   ```php
   <?php
   try {
       $pdo = new PDO("mysql:host=localhost;dbname=simad_database", $username, $password);
       echo "Connection successful!";
   } catch(PDOException $e) {
       echo "Connection failed: " . $e->getMessage();
   }
   ?>
   ```

### File Upload Issues
1. **Check Upload Directory Permissions**
2. **Verify PHP Upload Settings:**
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   max_execution_time = 300
   ```

## 📞 Support

Jika mengalami masalah:
1. **Check Error Logs** di `logs/error.log`
2. **Contact Hosting Support** untuk masalah server
3. **Review Documentation** untuk konfigurasi aplikasi

## 📝 Checklist Deployment

- [ ] Hosting requirements terpenuhi
- [ ] Database dibuat dan dikonfigurasi
- [ ] Files diupload ke hosting
- [ ] Permissions diset dengan benar
- [ ] Domain/subdomain dikonfigurasi
- [ ] SSL certificate aktif
- [ ] Installer dijalankan
- [ ] Admin user dibuat
- [ ] File install.php dihapus
- [ ] Aplikasi ditest dan berfungsi
- [ ] Backup strategy diimplementasi

---

**SIMAD - Sistem Informasi Manajemen Desa**  
Version 1.0.0  
Developed for efficient village management