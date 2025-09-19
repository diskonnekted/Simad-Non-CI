# Database Comparison & Merge Tool

Tool untuk membandingkan dua database MySQL dan melakukan merge data yang dipilih.

## Fitur Utama

### 1. Perbandingan Struktur Database
- Membandingkan tabel yang ada di kedua database
- Mendeteksi tabel yang hanya ada di salah satu database
- Membandingkan struktur kolom (tipe data, null, key, default, extra)
- Menampilkan perbedaan struktur secara detail

### 2. Perbandingan Data
- Membandingkan data antar tabel dengan primary key
- Mendeteksi data yang hanya ada di salah satu database
- Mendeteksi data yang berbeda antara kedua database
- Menampilkan jumlah data yang identik

### 3. Merge Data Selektif
- Memilih data spesifik untuk di-merge
- Merge dari database 1 ke database 2 atau sebaliknya
- Konfirmasi sebelum melakukan merge
- Laporan hasil merge (sukses/error)

## Cara Penggunaan

### 1. Akses Tool
```
http://localhost:8000/database-compare.php
```

### 2. Konfigurasi Koneksi Database

#### Database 1 (Source)
- **Host**: localhost (default)
- **Username**: username database MySQL
- **Password**: password database MySQL
- **Database Name**: nama database pertama

#### Database 2 (Target)
- **Host**: localhost (default)
- **Username**: username database MySQL
- **Password**: password database MySQL
- **Database Name**: nama database kedua

### 3. Perbandingan Struktur
1. Isi kredensial kedua database
2. Klik tombol **"Compare Structure"**
3. Hasil akan menampilkan:
   - Tabel yang hanya ada di Database 1
   - Tabel yang hanya ada di Database 2
   - Tabel yang ada di kedua database dengan perbedaan struktur
   - Daftar tabel yang identik

### 4. Perbandingan Data
1. Setelah melakukan compare structure, klik nama tabel yang ingin dibandingkan
2. Atau klik tombol **"Compare Data"** dan masukkan nama tabel
3. Atur limit jumlah baris (default: 1000)
4. Hasil akan menampilkan:
   - Data yang hanya ada di Database 1
   - Data yang hanya ada di Database 2
   - Data yang berbeda antara kedua database
   - Jumlah data yang identik

### 5. Merge Data
1. Pada hasil perbandingan data, pilih data yang ingin di-merge:
   - Centang checkbox untuk data yang ingin di-merge
   - Gunakan "Select All" untuk memilih semua data
2. Klik tombol merge sesuai arah yang diinginkan:
   - **"Merge Selected to Database 2"**: merge dari DB1 ke DB2
   - **"Merge Selected to Database 1"**: merge dari DB2 ke DB1
3. Konfirmasi merge pada dialog yang muncul
4. Lihat hasil merge (jumlah sukses/error)

## Persyaratan Sistem

### Server Requirements
- PHP 7.4 atau lebih tinggi
- MySQL/MariaDB
- Extension PHP: mysqli
- Memory limit: minimal 1GB (untuk database besar)
- Execution time: unlimited (untuk proses yang lama)

### Database Requirements
- Kedua database harus memiliki primary key pada tabel yang akan dibandingkan
- User database harus memiliki privilege:
  - SELECT (untuk membaca data)
  - INSERT, UPDATE (untuk merge data)
  - SHOW TABLES, DESCRIBE (untuk struktur)

## Keamanan

### Backup Otomatis
- **PENTING**: Selalu backup database sebelum melakukan merge
- Tool ini tidak menyediakan backup otomatis
- Gunakan `standalone-backup.php` untuk backup manual

### Validasi Data
- Tool melakukan escape string untuk mencegah SQL injection
- Validasi input untuk mencegah error
- Konfirmasi sebelum melakukan perubahan

## Troubleshooting

### Error Koneksi Database
```
Connection failed to DB1/DB2: Access denied
```
**Solusi**: Periksa kredensial database (username, password, host)

### Error Primary Key
```
No primary key found for table
```
**Solusi**: Pastikan tabel memiliki primary key yang valid

### Error Memory/Timeout
```
Fatal error: Maximum execution time exceeded
```
**Solusi**: 
- Kurangi limit data yang dibandingkan
- Tingkatkan memory_limit dan max_execution_time di PHP

### Error Permission
```
Access denied for user
```
**Solusi**: Pastikan user database memiliki privilege yang cukup

## Tips Penggunaan

### 1. Performa
- Gunakan limit yang wajar (1000-5000 baris) untuk tabel besar
- Lakukan perbandingan pada jam non-peak
- Monitor penggunaan memory server

### 2. Best Practices
- Selalu backup sebelum merge
- Test pada database development terlebih dahulu
- Verifikasi hasil merge setelah proses selesai
- Dokumentasikan perubahan yang dilakukan

### 3. Batasan
- Tool ini dirancang untuk database dengan struktur yang mirip
- Tidak mendukung foreign key constraint checking
- Tidak mendukung trigger dan stored procedure

## Contoh Skenario Penggunaan

### Skenario 1: Sinkronisasi Database Development ke Production
1. Compare structure untuk memastikan struktur sama
2. Compare data untuk melihat perbedaan
3. Merge data baru dari development ke production

### Skenario 2: Merge Database dari Dua Cabang
1. Compare structure untuk identifikasi perbedaan
2. Sesuaikan struktur jika diperlukan
3. Compare dan merge data dari kedua database

### Skenario 3: Migrasi Data Parsial
1. Identifikasi data yang perlu dipindahkan
2. Pilih data spesifik untuk di-merge
3. Verifikasi hasil migrasi

## File Terkait

- `database-compare.php`: Tool utama
- `standalone-backup.php`: Tool backup database
- `standalone-restore.php`: Tool restore database
- `DATABASE_COMPARE_GUIDE.md`: Dokumentasi ini

## Support

Jika mengalami masalah:
1. Periksa log error PHP
2. Pastikan semua persyaratan terpenuhi
3. Test koneksi database secara manual
4. Periksa privilege user database

---

**Catatan**: Tool ini dirancang untuk administrator database yang berpengalaman. Gunakan dengan hati-hati pada database production.