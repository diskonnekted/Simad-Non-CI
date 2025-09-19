# 🔧 Solusi Error "Access Denied" Database Hosting

## ❌ Error yang Terjadi
```
Error: SQLSTATE[HY000] [1045] Access denied for user 'u858602090_mad'@'localhost' (using password: YES)
```

## 🔍 Penyebab Masalah
Error ini terjadi karena:
1. **Kredensial database salah** - Username, password, atau nama database tidak sesuai
2. **Host database salah** - Hosting menggunakan host database yang berbeda dari `localhost`
3. **Database belum dibuat** - Database atau user belum dibuat di panel hosting
4. **User belum di-assign** - User database belum diberikan akses ke database

## 🛠️ Langkah-langkah Solusi

### 1. Verifikasi di Panel Hosting
**Masuk ke panel hosting Anda dan periksa:**
- ✅ Database `u858602090_mad` sudah dibuat
- ✅ User `u858602090_mad` sudah dibuat
- ✅ User sudah di-assign ke database dengan privilege penuh
- ✅ Password benar: `Dikantor@5474YAH`

### 2. Cek Host Database yang Benar
**Berbagai hosting menggunakan host database yang berbeda:**

| Provider Hosting | Host Database |
|------------------|---------------|
| InfinityFree | `sql200.infinityfree.com` atau `sql201.infinityfree.com` |
| 000webhost | `localhost` |
| Hostinger | `localhost` |
| cPanel Hosting | `localhost` |
| Custom Hosting | Cek dokumentasi atau hubungi support |

### 3. Gunakan Tool Troubleshooting
**Jalankan script troubleshooting yang sudah disediakan:**
1. Buka: `hosting_troubleshoot.php`
2. Isi form dengan kredensial database yang benar
3. Klik "Test Koneksi & Update Konfigurasi"
4. Script akan otomatis menguji berbagai kemungkinan host

### 4. Update Manual Konfigurasi
**Jika Anda sudah tahu host database yang benar:**

#### A. Update `config/install_temp.json`
```json
{
    "db_host": "HOST_DATABASE_YANG_BENAR",
    "db_name": "u858602090_mad",
    "db_user": "u858602090_mad",
    "db_pass": "Dikantor@5474YAH",
    "app_url": "https://simad.sistemdata.id"
}
```

#### B. Update `config/database.php`
```php
define('DB_HOST', 'HOST_DATABASE_YANG_BENAR');
define('DB_NAME', 'u858602090_mad');
define('DB_USER', 'u858602090_mad');
define('DB_PASS', 'Dikantor@5474YAH');
```

### 5. Test Koneksi Manual
**Buat file test sederhana:**
```php
<?php
$host = "HOST_DATABASE_YANG_BENAR";
$dbname = "u858602090_mad";
$username = "u858602090_mad";
$password = "Dikantor@5474YAH";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    echo "✅ Koneksi berhasil!";
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
```

## 🎯 Solusi Berdasarkan Provider Hosting

### InfinityFree
1. **Host Database:** `sql200.infinityfree.com` atau `sql201.infinityfree.com`
2. **Panel:** VistaPanel
3. **Lokasi:** MySQL Databases
4. **Catatan:** Database name dan username harus sama

### Hosting Lain (cPanel)
1. **Host Database:** Biasanya `localhost`
2. **Panel:** cPanel
3. **Lokasi:** MySQL Databases
4. **Catatan:** Pastikan user sudah di-assign ke database

## 🚀 Setelah Koneksi Berhasil

1. **Jalankan installer:** `install_hosting.php`
2. **Verifikasi aplikasi:** `index.php`
3. **Cek log error:** `logs/error.log`

## 📞 Bantuan Lebih Lanjut

**Jika masalah masih berlanjut:**
1. 📧 **Hubungi support hosting** untuk konfirmasi detail database
2. 📋 **Periksa email welcome** dari hosting
3. 📖 **Cek dokumentasi hosting** untuk panduan database
4. 🔍 **Gunakan tool troubleshooting** yang disediakan

## 📁 File yang Tersedia

- `hosting_troubleshoot.php` - Tool troubleshooting interaktif
- `test_hosting_connection.php` - Test berbagai konfigurasi database
- `install_hosting.php` - Installer untuk hosting
- `PANDUAN_INSTALASI_HOSTING.md` - Panduan lengkap instalasi

---

**💡 Tips:** Simpan informasi database yang benar untuk referensi di masa depan dan pastikan backup konfigurasi yang sudah berhasil.