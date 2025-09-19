# 🏘️ SIMAD - Sistem Informasi Manajemen Administrasi Desa

<div align="center">
  <img src="img/kode-icon.png" alt="SIMAD Logo" width="120" height="120">
  
  [![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
  [![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://mysql.com)
  [![Bootstrap](https://img.shields.io/badge/Bootstrap-4.x-purple.svg)](https://getbootstrap.com)
  [![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
  [![Status](https://img.shields.io/badge/Status-Active-brightgreen.svg)](#)
</div>

## 📋 Deskripsi

**SIMAD** adalah sistem informasi manajemen administrasi desa yang komprehensif, dirancang khusus untuk membantu pemerintah desa dalam mengelola berbagai aspek administrasi dan pelayanan masyarakat. Sistem ini menyediakan platform terintegrasi untuk manajemen data, layanan, keuangan, dan pelaporan desa.

### 🎯 Tujuan Utama
- Digitalisasi administrasi desa
- Meningkatkan efisiensi pelayanan publik
- Transparansi pengelolaan keuangan desa
- Kemudahan akses informasi bagi masyarakat
- Pelaporan yang akurat dan real-time

## ✨ Fitur Utama

### 🏛️ **Manajemen Administrasi**
- **Data Desa**: Pengelolaan profil dan informasi desa
- **Manajemen User**: Sistem role-based access control
- **Kategori Layanan**: Klasifikasi layanan administrasi
- **Jadwal Pelayanan**: Penjadwalan dan monitoring pelayanan

### 💼 **Layanan Publik**
- **Portal Layanan**: Katalog layanan administrasi desa
- **Tracking Status**: Pelacakan status permohonan
- **Galeri Dokumentasi**: Manajemen dokumen dan gambar
- **Notifikasi**: Sistem pemberitahuan otomatis

### 💰 **Manajemen Keuangan**
- **Transaksi**: Pencatatan pemasukan dan pengeluaran
- **Biaya Layanan**: Penetapan tarif layanan
- **Piutang**: Manajemen tagihan dan pembayaran
- **Laporan Keuangan**: Dashboard dan analisis finansial

### 📊 **Pelaporan & Analytics**
- **Dashboard Interaktif**: Visualisasi data real-time
- **Laporan Detail**: Export ke PDF dan Excel
- **Statistik Layanan**: Analisis performa layanan
- **Grafik Keuangan**: Monitoring kesehatan finansial

### 🌐 **Portal Klien**
- **Aplikasi Web**: Interface responsif untuk masyarakat
- **Aplikasi Android**: Mobile app untuk akses mudah
- **Konsultasi Online**: Fitur komunikasi dengan petugas
- **Kalender Layanan**: Jadwal dan agenda desa

## 🏗️ Arsitektur Sistem

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Client    │    │  Mobile Client  │    │  Admin Panel    │
│   (Portal)      │    │   (Android)     │    │   (Dashboard)   │
└─────────┬───────┘    └─────────┬───────┘    └─────────┬───────┘
          │                      │                      │
          └──────────────────────┼──────────────────────┘
                                 │
                    ┌─────────────┴─────────────┐
                    │      PHP Backend         │
                    │   (MVC Architecture)     │
                    └─────────────┬─────────────┘
                                  │
                    ┌─────────────┴─────────────┐
                    │     MySQL Database       │
                    │   (Relational Schema)    │
                    └───────────────────────────┘
```

## 🛠️ Teknologi yang Digunakan

### Backend
- **PHP 7.4+**: Server-side scripting
- **MySQL 5.7+**: Database management
- **Apache/Nginx**: Web server

### Frontend
- **HTML5 & CSS3**: Markup dan styling
- **Bootstrap 4**: Responsive framework
- **JavaScript/jQuery**: Client-side scripting
- **Chart.js**: Data visualization
- **DataTables**: Advanced table features

### Mobile
- **Android SDK**: Native Android development
- **WebView**: Hybrid app approach
- **Material Design**: UI/UX guidelines

### Tools & Libraries
- **Font Awesome**: Icon library
- **SweetAlert**: Beautiful alerts
- **Summernote**: WYSIWYG editor
- **FullCalendar**: Calendar component
- **Moment.js**: Date manipulation

## 📁 Struktur Direktori

```
kode/
├── 📁 android-client-app/     # Aplikasi Android WebView
│   ├── 📁 app/
│   │   ├── 📁 src/main/
│   │   │   ├── 📄 AndroidManifest.xml
│   │   │   ├── 📁 java/com/example/portalklien/
│   │   │   │   └── 📄 MainActivity.java
│   │   │   └── 📁 res/
│   │   │       ├── 📁 layout/
│   │   │       ├── 📁 values/
│   │   │       └── 📁 drawable/
│   │   └── 📄 build.gradle
│   ├── 📄 build.gradle
│   └── 📄 settings.gradle
├── 📁 client/                  # Portal klien web
│   ├── 📄 login.php           # Halaman login klien
│   ├── 📄 dashboard.php       # Dashboard klien
│   ├── 📄 consultation.php    # Konsultasi online
│   ├── 📄 order.php           # Pemesanan layanan
│   └── 📄 calendar.php        # Kalender kegiatan
├── 📁 config/                  # Konfigurasi sistem
│   ├── 📄 database.php        # Konfigurasi database
│   ├── 📄 auth.php            # Konfigurasi autentikasi
│   └── 📄 install_temp.json   # Template instalasi
├── 📁 css/                     # Stylesheet dan tema
│   ├── 📄 style.css           # Style utama
│   ├── 📄 bootstrap.css       # Bootstrap framework
│   └── 📁 plugin/             # Plugin CSS
├── 📁 js/                      # JavaScript libraries
│   ├── 📄 jquery.min.js       # jQuery library
│   ├── 📁 datatables/         # DataTables plugin
│   ├── 📁 chartist/           # Chart library
│   └── 📁 sweet-alert/        # SweetAlert plugin
├── 📁 img/                     # Assets dan gambar
│   ├── 📄 kode-icon.png       # Logo aplikasi
│   ├── 📁 profiles/           # Foto profil user
│   └── 📁 datatables/         # Icon DataTables
├── 📁 layouts/                 # Template layout
│   ├── 📄 header.php          # Header template
│   └── 📄 footer.php          # Footer template
├── 📁 database/                # Database schema
│   └── 📄 database.sql        # SQL schema dan data
├── 📄 index.php               # Halaman utama
├── 📄 login.php               # Sistem login admin
├── 📄 install.php             # Installer otomatis
└── 📄 *.php                   # Modul-modul sistem
```

## 🚀 Instalasi

### Persyaratan Sistem
- **PHP**: 7.4 atau lebih tinggi
- **MySQL**: 5.7 atau lebih tinggi
- **Web Server**: Apache/Nginx
- **Browser**: Chrome, Firefox, Safari (versi terbaru)
- **Android**: API Level 21+ (untuk mobile app)

### 📥 Instalasi Cepat

1. **Clone Repository**
   ```bash
   git clone https://github.com/username/simad-desa.git
   cd simad-desa
   ```

2. **Setup Database**
   ```sql
   CREATE DATABASE simad_desa;
   USE simad_desa;
   SOURCE database/database.sql;
   ```

3. **Konfigurasi Database**
   ```php
   // config/database.php
   $host = 'localhost';
   $username = 'your_username';
   $password = 'your_password';
   $database = 'simad_desa';
   ```

4. **Instalasi Otomatis** (Opsional)
   ```bash
   # Akses melalui browser
   http://localhost/simad-desa/install.php
   ```

5. **Jalankan Server**
   ```bash
   # Menggunakan PHP built-in server
   php -S localhost:8000
   
   # Atau menggunakan XAMPP/WAMP
   # Letakkan di folder htdocs/www
   ```

### 📱 Build Aplikasi Android

1. **Buka Android Studio**
2. **Import Project**: `android-client-app/`
3. **Sync Gradle**: Tunggu proses sinkronisasi
4. **Build APK**: Build → Generate Signed Bundle/APK

## 🎯 Penggunaan

### 👨‍💼 Admin Dashboard
1. **Login Admin**: `http://localhost:8000/login.php`
   - Username: `admin`
   - Password: `admin123`

2. **Fitur Utama**:
   - 📊 Dashboard dengan statistik real-time
   - 👥 Manajemen user dan role
   - 🏛️ Data desa dan profil
   - 💼 Layanan dan kategori
   - 💰 Transaksi dan keuangan
   - 📋 Laporan dan analytics

### 🌐 Portal Klien
1. **Akses Portal**: `http://localhost:8000/client/login.php`
2. **Registrasi**: Daftar akun baru melalui halaman register
3. **Layanan**:
   - 📝 Pengajuan layanan administrasi
   - 💬 Konsultasi online dengan petugas
   - 📅 Lihat jadwal dan agenda desa
   - 💳 Pembayaran biaya layanan

### 📱 Aplikasi Mobile
1. **Install APK** di perangkat Android
2. **Login** menggunakan akun portal klien
3. **Akses** semua fitur portal dalam format mobile-friendly

## 🔧 Konfigurasi Lanjutan

### 🔐 Keamanan
```php
// config/auth.php
$session_timeout = 3600; // 1 jam
$password_min_length = 8;
$enable_2fa = true;
```

### 📧 Email Configuration
```php
// config/email.php
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
$smtp_username = 'your-email@gmail.com';
$smtp_password = 'your-app-password';
```

### 🎨 Customization
- **Logo**: Ganti `img/kode-icon.png`
- **Tema**: Edit `css/style.css`
- **Warna**: Modifikasi variabel CSS di `css/root.css`

## 📊 Fitur Demo

| Modul | Status | Demo URL |
|-------|--------|----------|
| 🏠 Dashboard | ✅ | `/index.php` |
| 👥 User Management | ✅ | `/user.php` |
| 🏛️ Data Desa | ✅ | `/desa.php` |
| 💼 Layanan | ✅ | `/layanan.php` |
| 💰 Transaksi | ✅ | `/transaksi.php` |
| 📋 Laporan | ✅ | `/laporan.php` |
| 🌐 Portal Klien | ✅ | `/client/` |
| 📱 Mobile App | ✅ | `android-client-app/` |

## 🐛 Troubleshooting

### Masalah Umum

**❌ Database Connection Error**
```bash
# Periksa konfigurasi database
php check_database.php
```

**❌ Permission Denied**
```bash
# Set permission folder
chmod -R 755 .
chown -R www-data:www-data .
```

**❌ Android Build Error**
```bash
# Clean dan rebuild
./gradlew clean
./gradlew build
```

### 📞 Dukungan
- 📧 Email: support@simad-desa.com
- 💬 Discord: [SIMAD Community](https://discord.gg/simad)
- 📖 Wiki: [Documentation](https://github.com/username/simad-desa/wiki)
- 🐛 Issues: [GitHub Issues](https://github.com/username/simad-desa/issues)

## 🤝 Kontribusi

Kami sangat menghargai kontribusi dari komunitas! 🎉

### 📝 Cara Berkontribusi
1. **Fork** repository ini
2. **Create** branch fitur (`git checkout -b feature/AmazingFeature`)
3. **Commit** perubahan (`git commit -m 'Add some AmazingFeature'`)
4. **Push** ke branch (`git push origin feature/AmazingFeature`)
5. **Open** Pull Request

### 🏆 Contributors
<a href="https://github.com/username/simad-desa/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=username/simad-desa" />
</a>

## 📄 Lisensi

Proyek ini dilisensikan di bawah [MIT License](LICENSE) - lihat file LICENSE untuk detail lengkap.

```
MIT License

Copyright (c) 2024 SIMAD Development Team

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction...
```

## 🌟 Acknowledgments

- 🙏 Terima kasih kepada komunitas open source
- 💡 Inspirasi dari sistem e-government modern
- 🎨 UI/UX design menggunakan Material Design
- 📚 Dokumentasi menggunakan best practices

---

<div align="center">
  <p><strong>Dibuat dengan ❤️ untuk kemajuan desa Indonesia</strong></p>
  
  [![GitHub stars](https://img.shields.io/github/stars/username/simad-desa?style=social)](https://github.com/username/simad-desa/stargazers)
  [![GitHub forks](https://img.shields.io/github/forks/username/simad-desa?style=social)](https://github.com/username/simad-desa/network/members)
  [![GitHub issues](https://img.shields.io/github/issues/username/simad-desa)](https://github.com/username/simad-desa/issues)
  
  **[⭐ Star this repo](https://github.com/username/simad-desa) | [🐛 Report Bug](https://github.com/username/simad-desa/issues) | [💡 Request Feature](https://github.com/username/simad-desa/issues)**
</div>