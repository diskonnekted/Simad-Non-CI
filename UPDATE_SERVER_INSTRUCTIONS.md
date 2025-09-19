# 🔧 Instruksi Update Database Server Production

## Database Target
- **Nama Database:** `simadorbitdev_simad`
- **Tujuan:** Menambahkan kolom `tanggal_selesai` ke tabel `jadwal_kunjungan`
- **Alasan:** Mengatasi error SQL "Unknown column 'tanggal_selesai' in 'SET'"

## ⚠️ PENTING - Sebelum Memulai

### 1. Backup Database
```bash
# Via command line
mysqldump -u username -p simadorbitdev_simad > backup_simadorbitdev_simad_$(date +%Y%m%d_%H%M%S).sql

# Atau via cPanel/phpMyAdmin:
# - Masuk ke phpMyAdmin
# - Pilih database simadorbitdev_simad
# - Klik tab "Export"
# - Pilih "Quick" export method
# - Klik "Go" untuk download backup
```

### 2. Verifikasi Akses Database
- Pastikan Anda memiliki akses ke database server
- Pastikan user database memiliki privilege `ALTER TABLE`
- Test koneksi database terlebih dahulu

## 📋 Metode Update

### Metode 1: Via Script PHP (Direkomendasikan)

1. **Upload file `update_server_database.php` ke server**
2. **Edit kredensial database di file tersebut:**
   ```php
   $server_config = [
       'host' => 'localhost', // Ganti dengan host server Anda
       'dbname' => 'simadorbitdev_simad',
       'username' => 'your_username', // Ganti dengan username database
       'password' => 'your_password', // Ganti dengan password database
       'charset' => 'utf8mb4'
   ];
   ```
3. **Akses script via browser:**
   ```
   https://yourdomain.com/path/to/update_server_database.php
   ```
4. **Verifikasi hasil update di browser**
5. **Hapus file script setelah selesai untuk keamanan**

### Metode 2: Via SQL Script

1. **Masuk ke phpMyAdmin atau MySQL command line**
2. **Pilih database `simadorbitdev_simad`**
3. **Copy-paste isi file `update_server_database.sql`**
4. **Jalankan script SQL**
5. **Verifikasi hasil dengan query:**
   ```sql
   DESCRIBE jadwal_kunjungan;
   ```

### Metode 3: Via Command Line MySQL

```bash
# Login ke MySQL
mysql -u username -p

# Pilih database
USE simadorbitdev_simad;

# Jalankan ALTER TABLE
ALTER TABLE jadwal_kunjungan ADD COLUMN tanggal_selesai TIMESTAMP NULL AFTER status;

# Verifikasi
DESCRIBE jadwal_kunjungan;
```

## ✅ Verifikasi Update Berhasil

### 1. Cek Struktur Tabel
```sql
DESCRIBE jadwal_kunjungan;
```
**Expected:** Kolom `tanggal_selesai` dengan tipe `TIMESTAMP` dan `NULL` allowed

### 2. Test Aplikasi
- Akses halaman edit jadwal: `jadwal-view.php?id=X`
- Pastikan tidak ada error SQL saat menyimpan
- Verifikasi fitur edit jadwal berfungsi normal

### 3. Cek Log Error
- Monitor log error server untuk memastikan tidak ada error baru
- Pastikan aplikasi berjalan normal

## 🔍 Troubleshooting

### Error: "Access denied for user"
**Solusi:**
- Pastikan username dan password database benar
- Pastikan user memiliki privilege yang cukup
- Hubungi administrator hosting jika perlu

### Error: "Table 'jadwal_kunjungan' doesn't exist"
**Solusi:**
- Pastikan nama database sudah benar
- Cek apakah tabel ada dengan query: `SHOW TABLES LIKE 'jadwal_kunjungan';`
- Pastikan struktur database sudah sesuai

### Error: "Column 'tanggal_selesai' already exists"
**Solusi:**
- Ini normal jika kolom sudah ada sebelumnya
- Verifikasi dengan `DESCRIBE jadwal_kunjungan;`
- Tidak perlu action tambahan

## 📝 Catatan Tambahan

### Rollback (Jika Diperlukan)
Jika terjadi masalah, Anda bisa menghapus kolom dengan:
```sql
ALTER TABLE jadwal_kunjungan DROP COLUMN tanggal_selesai;
```

### Monitoring Setelah Update
- Monitor performa aplikasi
- Cek log error secara berkala
- Test semua fitur yang terkait dengan jadwal

### File yang Perlu Dihapus Setelah Update
- `update_server_database.php` (untuk keamanan)
- File backup lama (setelah konfirmasi update berhasil)

## 📞 Support

Jika mengalami kesulitan:
1. Cek log error server
2. Verifikasi kredensial database
3. Pastikan backup sudah dibuat
4. Hubungi administrator sistem jika diperlukan

---

**Dibuat:** $(date)
**Versi:** 1.0
**Status:** Ready for Production