# Panduan Mengatasi Masalah PHP di Hosting

## 🚨 Masalah yang Terjadi

**Gejala:**
- File PHP tidak bisa dibuka di hosting
- Browser mendownload file PHP instead of menjalankannya
- Error 500 saat mengakses file PHP
- Website menampilkan "This page isn't working"

## 🔍 Penyebab Masalah

### 1. **PHP Tidak Aktif di Hosting**
- PHP version tidak diaktifkan
- Hosting tidak mendukung PHP
- Konfigurasi server bermasalah

### 2. **File .htaccess Bermasalah**
- .htaccess corrupt atau salah konfigurasi
- Conflict dengan server configuration
- Missing PHP handler

### 3. **Permission File Salah**
- File PHP permission tidak sesuai
- Folder permission tidak benar

### 4. **Struktur File Tidak Benar**
- File tidak ter-upload dengan benar
- Path file salah

## ✅ Solusi Langkah demi Langkah

### **STEP 1: Test Dasar Hosting**

1. **Upload dan akses file test:**
   ```
   test_hosting.html
   ```
   - Jika bisa dibuka → Server hosting OK
   - Jika tidak bisa dibuka → Masalah hosting dasar

2. **Test PHP sederhana:**
   ```
   test_php_hosting.php
   ```
   - Jika bisa dibuka → PHP berjalan
   - Jika download/error → PHP bermasalah

### **STEP 2: Cek Panel Hosting**

1. **Login ke cPanel/Panel Hosting**
2. **Cek PHP Version:**
   - Pastikan PHP 7.4+ aktif
   - Enable PHP extensions yang diperlukan
3. **Cek Error Logs:**
   - Lihat error terbaru
   - Catat pesan error spesifik

### **STEP 3: Perbaiki File .htaccess**

1. **Backup .htaccess lama:**
   ```bash
   mv .htaccess .htaccess.old
   ```

2. **Gunakan .htaccess minimal:**
   ```bash
   # Rename .htaccess_backup menjadi .htaccess
   mv .htaccess_backup .htaccess
   ```

3. **Test website setelah perubahan**

### **STEP 4: Set Permission yang Benar**

**Permission yang diperlukan:**
```
Folders: 755
PHP Files: 644
Config Files: 644
.htaccess: 644
```

**Cara set permission:**
```bash
# Via File Manager hosting
Klik kanan file → Permissions → Set ke 644

# Via FTP
chmod 755 folders/
chmod 644 *.php
chmod 644 config/*
```

### **STEP 5: Upload File dengan Benar**

1. **Struktur folder yang benar:**
   ```
   public_html/
   ├── .htaccess
   ├── index.php
   ├── config/
   │   ├── database.php
   │   └── auth.php
   ├── css/
   ├── js/
   └── ...
   ```

2. **Upload mode:**
   - Gunakan mode ASCII untuk .php, .html, .css, .js
   - Gunakan mode Binary untuk gambar

### **STEP 6: Test Koneksi Database**

1. **Setelah PHP berjalan, test database:**
   ```
   fix_database_hosting.php
   ```

2. **Verifikasi include files:**
   ```
   verify_includes.php
   ```

## 🛠️ Tools Troubleshooting

### **File yang Sudah Dibuat:**

1. **`test_hosting.html`** - Test dasar server
2. **`test_php_hosting.php`** - Test PHP dan extensions
3. **`fix_database_hosting.php`** - Fix masalah database
4. **`verify_includes.php`** - Verifikasi include files
5. **`.htaccess_backup`** - Backup .htaccess yang benar

### **Cara Menggunakan:**

1. **Upload semua file ke hosting**
2. **Akses test_hosting.html terlebih dahulu**
3. **Ikuti instruksi di halaman test**
4. **Gunakan tools sesuai hasil diagnosis**

## 🆘 Solusi Berdasarkan Hosting Provider

### **InfinityFree / iFastNet:**
```
- PHP harus diaktifkan di panel
- Gunakan .htaccess minimal
- Cek PHP version di panel
- Pastikan domain sudah propagasi
```

### **cPanel Hosting:**
```
- Cek PHP Selector
- Enable required extensions
- Cek Error Logs di cPanel
- Set correct file permissions
```

### **Shared Hosting Lainnya:**
```
- Hubungi support untuk PHP activation
- Minta bantuan set PHP version
- Cek dokumentasi hosting
```

## 📋 Checklist Troubleshooting

- [ ] ✅ File HTML bisa dibuka
- [ ] ✅ PHP version aktif di panel hosting
- [ ] ✅ File .htaccess tidak corrupt
- [ ] ✅ Permission file sudah benar (644)
- [ ] ✅ File PHP ter-upload dengan lengkap
- [ ] ✅ test_php_hosting.php bisa diakses
- [ ] ✅ Database connection berhasil
- [ ] ✅ Website utama bisa dibuka

## 🔧 Emergency Fix

**Jika semua gagal, buat file PHP minimal:**

```php
<?php
// test.php - File test minimal
echo "PHP is working!";
echo "<br>PHP Version: " . PHP_VERSION;
echo "<br>Server: " . $_SERVER['SERVER_SOFTWARE'];
?>
```

**Upload dan akses file ini. Jika bisa dibuka, PHP berjalan.**

## 📞 Bantuan Lebih Lanjut

### **Jika masalah masih berlanjut:**

1. **Screenshot hasil test_hosting.html**
2. **Screenshot error dari browser**
3. **Copy error log dari hosting panel**
4. **Hubungi support hosting dengan informasi:**
   - Domain name
   - Error message
   - PHP version yang diinginkan
   - Screenshot masalah

### **Informasi untuk Support:**
```
Domain: simad.sistemdata.id
Masalah: PHP files tidak bisa diakses
Error: [copy error message]
PHP Version: 7.4+ required
Extensions needed: PDO, PDO_MySQL, MySQLi
```

## 🎯 Hasil yang Diharapkan

Setelah perbaikan berhasil:
- ✅ File PHP dapat diakses normal
- ✅ test_php_hosting.php menampilkan info PHP
- ✅ Database connection berhasil
- ✅ Website utama dapat dibuka
- ✅ Tidak ada error 500

---

**Catatan:** Ikuti langkah-langkah secara berurutan dan test setiap step sebelum melanjutkan ke step berikutnya.