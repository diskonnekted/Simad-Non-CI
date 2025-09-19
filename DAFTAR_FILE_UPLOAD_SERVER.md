# Daftar File yang Perlu Diupload ke Server

Berdasarkan analisis perbandingan database lokal vs server, berikut adalah file-file yang perlu diupload ulang ke server karena menggunakan fitur-fitur baru yang ada di database lokal.

## 🔴 File Prioritas Tinggi (WAJIB Upload)

### 1. File Terkait Fitur PIN Desa
```
desa-reset-pin.php          # File utama untuk reset PIN desa
desa-view.php               # Menampilkan info PIN dan tombol reset
desa-profile.php            # Profile desa dengan session PIN
client/login.php            # Login portal desa menggunakan PIN
aplot/client/login.php      # Login portal desa (versi aplot)
aplot/desa-profile.php      # Profile desa (versi aplot)
```

### 2. File Terkait Activity Logs
```
desa-reset-pin.php          # Mencatat aktivitas reset PIN ke activity_logs
```

### 3. File Terkait Kategori Baru Biaya Operasional
```
biaya-add.php               # Form tambah biaya dengan kategori baru
biaya-edit.php              # Form edit biaya dengan kategori baru
biaya.php                   # List biaya dengan filter kategori baru
biaya-view.php              # Detail biaya dengan kategori baru
```

### 4. File Terkait Peralatan (Jika Ada Perubahan)
```
peralatan.php               # Manajemen peralatan
peralatan-add.php           # Tambah peralatan
peralatan-edit.php          # Edit peralatan
peralatan-view.php          # Detail peralatan
```

## 🟡 File Prioritas Sedang (Disarankan Upload)

### 1. File Database dan Konfigurasi
```
config/database.php         # Jika ada perubahan konfigurasi
check_database.php          # Untuk verifikasi database
check_tables.php            # Untuk verifikasi tabel
```

### 2. File Layout dan Template
```
layouts/header.php          # Jika ada perubahan menu/navigasi
layouts/footer.php          # Jika ada perubahan footer
```

### 3. File Dokumentasi dan Panduan
```
README.md                   # Dokumentasi terbaru
robots.txt                  # Update SEO rules
sitemap.xml                 # Update sitemap
```

## 🟢 File Opsional (Jika Diperlukan)

### 1. File Aplot (Jika Menggunakan Subfolder Aplot)
```
aplot/desa-profile.php
aplot/client/login.php
aplot/robots.txt
aplot/sitemap.xml
```

### 2. File Android App (Jika Ada Perubahan URL)
```
android-client-app/app/src/main/java/com/kode/clientapp/MainActivity.java
android-client-app/README.md
```

## 📋 Checklist Upload

### Sebelum Upload
- [ ] Backup database server terlebih dahulu
- [ ] Jalankan script SQL update incremental
- [ ] Verifikasi struktur database sudah sesuai
- [ ] Test fitur PIN di environment development

### File Wajib Upload (Urutan Prioritas)
1. [ ] `desa-reset-pin.php`
2. [ ] `desa-view.php`
3. [ ] `client/login.php`
4. [ ] `desa-profile.php`
5. [ ] `biaya-add.php`
6. [ ] `biaya-edit.php`
7. [ ] `biaya.php`
8. [ ] `biaya-view.php`

### File Opsional Upload
9. [ ] `aplot/client/login.php`
10. [ ] `aplot/desa-profile.php`
11. [ ] `peralatan.php`
12. [ ] `peralatan-add.php`
13. [ ] `peralatan-edit.php`
14. [ ] `peralatan-view.php`
15. [ ] `layouts/header.php`
16. [ ] `layouts/footer.php`

### Setelah Upload
- [ ] Test login desa dengan PIN
- [ ] Test reset PIN desa
- [ ] Test tambah/edit biaya dengan kategori baru
- [ ] Verifikasi activity logs tercatat
- [ ] Test semua fitur yang terkait perubahan database

## 🚨 File yang TIDAK Perlu Diupload

### File di Folder `unused/`
```
unused/*                    # Semua file di folder unused tidak perlu diupload
```

### File SQL dan Database
```
sql/manajemen_transaksi_desa.sql     # Database lokal, jangan upload
sql/simadorbitdev_simad.sql          # Database server, sudah ada
sql/update_incremental_server.sql    # Script update, jalankan manual
sql/PANDUAN_UPDATE_DATABASE.md       # Panduan, tidak perlu di server
```

### File Development
```
package.json
package-lock.json
tailwind.config.js
src/input.css
composer.json
composer.lock
.gitignore
```

## 📝 Catatan Penting

### 1. Urutan Upload yang Disarankan
1. **Database Update**: Jalankan script SQL terlebih dahulu
2. **Core Files**: Upload file-file utama (desa-reset-pin.php, client/login.php)
3. **Supporting Files**: Upload file pendukung (biaya-*.php, layouts)
4. **Optional Files**: Upload file opsional jika diperlukan

### 2. Verifikasi Setelah Upload
- Test login desa dengan nama desa, kecamatan, dan PIN
- Test reset PIN oleh admin
- Test tambah biaya dengan kategori 'peralatan' dan 'administrasi'
- Cek log activity_logs di database

### 3. Rollback Plan
- Simpan backup file lama sebelum upload
- Siapkan script rollback database jika diperlukan
- Monitor error log setelah upload

### 4. Koordinasi Tim
- Informasikan kepada tim tentang fitur PIN baru
- Berikan panduan penggunaan fitur reset PIN
- Update dokumentasi user manual jika diperlukan

---

**Total File Wajib Upload**: 8 file
**Total File Opsional**: 8 file
**Estimasi Waktu Upload**: 15-30 menit
**Estimasi Waktu Testing**: 30-60 menit