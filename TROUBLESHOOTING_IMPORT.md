# Troubleshooting Import SQL Database

## Error #1050 - Table 'activity_logs' already exists

### Penyebab
Error ini terjadi ketika mencoba membuat tabel yang sudah ada di database. Hal ini umum terjadi saat:
- Import SQL dump yang berisi struktur tabel yang sudah ada
- Menjalankan script CREATE TABLE berkali-kali
- Database sudah memiliki tabel dengan nama yang sama

### Solusi

#### 1. Solusi Cepat (Recommended)
Ganti perintah `CREATE TABLE` dengan `CREATE TABLE IF NOT EXISTS` dalam file SQL:

```sql
-- Dari:
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  ...
);

-- Ke:
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL,
  ...
);
```

#### 2. Menggunakan Script Helper
Jalankan script helper yang sudah disediakan:

```bash
# Via command line
php check_table_conflicts.php

# Via browser
http://localhost:8000/check_table_conflicts.php?run=1
```

#### 3. Import dengan Script Helper
Jalankan script helper sebelum import:

```sql
-- Jalankan file ini terlebih dahulu
SOURCE database/import_helper.sql;

-- Kemudian jalankan import SQL utama
SOURCE your_main_sql_file.sql;
```

#### 4. Manual Drop Table (HATI-HATI)
Jika yakin ingin menghapus tabel yang ada:

```sql
-- Backup terlebih dahulu
CREATE TABLE activity_logs_backup AS SELECT * FROM activity_logs;

-- Hapus tabel yang ada
DROP TABLE IF EXISTS activity_logs;

-- Buat tabel baru
CREATE TABLE activity_logs (...)
```

### Tools yang Tersedia

#### 1. check_table_conflicts.php
Script PHP untuk memeriksa dan mengatasi konflik tabel:

**Fitur:**
- Cek apakah tabel sudah ada
- Tampilkan struktur tabel
- Hitung jumlah record
- Backup tabel sebelum drop
- Buat tabel dengan struktur yang benar

**Penggunaan:**
```bash
php check_table_conflicts.php
```

#### 2. fix_activity_logs_conflict.sql
Script SQL dengan berbagai opsi untuk mengatasi konflik:

**Opsi yang tersedia:**
- CREATE TABLE IF NOT EXISTS (recommended)
- DROP TABLE dan buat ulang
- ALTER TABLE untuk update struktur

#### 3. import_helper.sql
Script helper untuk import yang aman:

**Fitur:**
- Cek tabel sebelum membuat
- Buat tabel hanya jika belum ada
- Template untuk tabel umum
- Dokumentasi lengkap

### Langkah-langkah Import yang Aman

1. **Backup Database**
   ```bash
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Cek Konflik Tabel**
   ```bash
   php check_table_conflicts.php
   ```

3. **Jalankan Helper Script**
   ```sql
   SOURCE database/import_helper.sql;
   ```

4. **Modifikasi File SQL**
   - Ganti `CREATE TABLE` dengan `CREATE TABLE IF NOT EXISTS`
   - Gunakan `INSERT IGNORE` atau `ON DUPLICATE KEY UPDATE`

5. **Import File SQL**
   ```bash
   mysql -u username -p database_name < your_file.sql
   ```

### Contoh Modifikasi File SQL

#### Sebelum (Menyebabkan Error)
```sql
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Sesudah (Aman)
```sql
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Error Lainnya yang Mungkin Terjadi

#### #1062 - Duplicate entry
**Solusi:**
```sql
-- Gunakan INSERT IGNORE
INSERT IGNORE INTO table_name VALUES (...);

-- Atau ON DUPLICATE KEY UPDATE
INSERT INTO table_name VALUES (...) 
ON DUPLICATE KEY UPDATE column1=VALUES(column1);
```

#### #1146 - Table doesn't exist
**Solusi:**
- Pastikan urutan pembuatan tabel benar
- Cek foreign key dependencies
- Jalankan script pembuatan tabel terlebih dahulu

#### #1452 - Foreign key constraint fails
**Solusi:**
```sql
-- Disable foreign key checks sementara
SET FOREIGN_KEY_CHECKS = 0;
-- Import data
SET FOREIGN_KEY_CHECKS = 1;
```

### Tips Pencegahan

1. **Selalu backup sebelum import**
2. **Gunakan IF NOT EXISTS untuk CREATE TABLE**
3. **Gunakan INSERT IGNORE untuk data**
4. **Cek struktur database sebelum import**
5. **Test import di environment development dulu**
6. **Gunakan tools yang disediakan untuk cek konflik**

### Kontak Support

Jika masih mengalami masalah:
1. Jalankan `php check_table_conflicts.php` dan kirim outputnya
2. Sertakan pesan error lengkap
3. Jelaskan langkah yang sudah dicoba

---

**File terkait:**
- `check_table_conflicts.php` - Tool utama untuk cek konflik
- `database/fix_activity_logs_conflict.sql` - Script khusus activity_logs
- `database/import_helper.sql` - Helper untuk import aman
- `TROUBLESHOOTING_IMPORT.md` - Dokumentasi ini