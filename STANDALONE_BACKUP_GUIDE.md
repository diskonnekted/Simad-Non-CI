# Panduan Sistem Backup & Restore Mandiri

Sistem backup dan restore mandiri ini dirancang untuk dapat berjalan secara independen tanpa memerlukan koneksi ke aplikasi utama. Anda dapat mengupload file-file ini ke server mana pun dan menjalankannya langsung melalui browser.

## 📁 File yang Diperlukan

1. **standalone-backup.php** - Sistem backup mandiri
2. **standalone-restore.php** - Sistem restore mandiri
3. **STANDALONE_BACKUP_GUIDE.md** - Panduan penggunaan (file ini)

## 🚀 Cara Penggunaan

### A. Melakukan Backup

1. **Upload File**
   - Upload file `standalone-backup.php` ke server Anda
   - Pastikan server mendukung PHP dan ekstensi ZipArchive

2. **Akses Halaman Backup**
   - Buka browser dan akses: `http://yourserver.com/standalone-backup.php`
   - Ganti `yourserver.com` dengan domain/IP server Anda

3. **Isi Form Backup**
   - **Database Host**: Biasanya `localhost`
   - **Database Username**: Username database Anda
   - **Database Password**: Password database (kosongkan jika tidak ada)
   - **Database Name**: Nama database yang akan di-backup
   - **Path Folder Uploads**: Path lengkap ke folder uploads (opsional)

4. **Download Backup**
   - Klik tombol "📥 Download Backup"
   - File backup akan diunduh dalam format ZIP
   - Nama file: `backup_[database]_[tanggal]_[waktu].zip`

### B. Melakukan Restore

1. **Upload File**
   - Upload file `standalone-restore.php` ke server tujuan
   - Pastikan server mendukung PHP dan ekstensi ZipArchive

2. **Akses Halaman Restore**
   - Buka browser dan akses: `http://yourserver.com/standalone-restore.php`

3. **Isi Form Restore**
   - **File Backup**: Pilih file backup ZIP yang telah dibuat
   - **Database Host**: Host database tujuan
   - **Database Username**: Username database tujuan
   - **Database Password**: Password database tujuan
   - **Database Name**: Nama database tujuan
   - **Path Folder Uploads**: Path untuk restore file uploads (opsional)

4. **Mulai Restore**
   - Klik tombol "🔄 Mulai Restore"
   - Konfirmasi peringatan yang muncul
   - Tunggu proses restore selesai

## 📋 Persyaratan Sistem

### Server Requirements
- PHP 7.0 atau lebih baru
- Ekstensi PHP: mysqli, zip
- Memory limit: minimal 512MB (untuk database besar)
- Execution time: unlimited (untuk proses backup/restore yang lama)

### Database Requirements
- MySQL 5.6 atau lebih baru
- User database harus memiliki privilege:
  - SELECT, INSERT, UPDATE, DELETE
  - CREATE, DROP (untuk restore)
  - SHOW DATABASES (untuk backup)

## 📦 Isi File Backup

File backup yang dihasilkan berformat ZIP dan berisi:

1. **database_backup.sql** - Dump lengkap database
2. **uploads_backup.zip** - File uploads (jika ada)
3. **backup_info.json** - Informasi backup

### Contoh backup_info.json:
```json
{
    "created_at": "2024-01-15 14:30:25",
    "database": "my_database",
    "host": "localhost",
    "includes_uploads": true
}
```

## ⚠️ Peringatan Keamanan

1. **Jangan Tinggalkan File di Server Produksi**
   - Hapus file backup/restore setelah selesai digunakan
   - File ini dapat mengakses database dengan kredensial yang dimasukkan

2. **Gunakan Koneksi HTTPS**
   - Selalu gunakan HTTPS untuk melindungi kredensial database
   - Jangan gunakan di jaringan publik yang tidak aman

3. **Validasi File Backup**
   - Pastikan file backup berasal dari sumber terpercaya
   - Periksa isi file sebelum melakukan restore

4. **Backup Sebelum Restore**
   - Selalu buat backup database tujuan sebelum restore
   - Proses restore akan mengganti semua data yang ada

## 🔧 Troubleshooting

### Error: "Connection failed"
- Periksa kredensial database
- Pastikan database server berjalan
- Periksa firewall dan network connectivity

### Error: "Cannot create backup file"
- Periksa permission direktori temporary
- Pastikan disk space mencukupi
- Periksa ekstensi ZipArchive tersedia

### Error: "Memory limit exceeded"
- Tingkatkan memory_limit di php.ini
- Atau edit di awal file: `ini_set('memory_limit', '1G')`

### Error: "Maximum execution time exceeded"
- Tingkatkan max_execution_time di php.ini
- Atau sudah diset unlimited di file

### Upload File Gagal
- Periksa upload_max_filesize di php.ini
- Periksa post_max_size di php.ini
- Pastikan file backup tidak corrupt

## 📝 Tips Penggunaan

1. **Backup Berkala**
   - Lakukan backup secara berkala
   - Simpan file backup di lokasi yang aman
   - Beri nama file yang mudah diidentifikasi

2. **Test Restore**
   - Test proses restore di environment development
   - Pastikan semua data ter-restore dengan benar
   - Verifikasi file uploads juga ter-restore

3. **Monitoring**
   - Monitor log server saat backup/restore
   - Periksa error log jika ada masalah
   - Catat waktu yang diperlukan untuk backup/restore

## 📞 Support

Jika mengalami masalah:
1. Periksa error log server
2. Pastikan semua persyaratan sistem terpenuhi
3. Coba dengan database/file yang lebih kecil untuk testing
4. Periksa permission file dan direktori

---

**Catatan**: Sistem ini dirancang untuk kemudahan penggunaan dan portabilitas. Untuk environment produksi yang kompleks, pertimbangkan menggunakan solusi backup enterprise yang lebih robust.