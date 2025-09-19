# 🏛️ SIMAD - Sistem Informasi Manajemen Administrasi Desa

<div align="center">

![SIMAD Logo](img/kode-icon.png)

**Solusi Digital Terpadu untuk Administrasi dan Layanan Desa**

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![PWA](https://img.shields.io/badge/PWA-Ready-5A0FC8?style=flat&logo=pwa&logoColor=white)](https://web.dev/progressive-web-apps/)

**Dikembangkan oleh [Clasnet](https://clasnet.id) - Solusi IT Terdepan untuk Desa Digital**

</div>

---

## 📋 Daftar Isi

- [🎯 Tentang SIMAD](#-tentang-simad)
- [✨ Fitur Utama](#-fitur-utama)
- [🏗️ Arsitektur Sistem](#️-arsitektur-sistem)
- [⚙️ Persyaratan Sistem](#️-persyaratan-sistem)
- [🚀 Instalasi](#-instalasi)
- [📱 Penggunaan](#-penggunaan)
- [🔧 Konfigurasi](#-konfigurasi)
- [📊 Dashboard & Laporan](#-dashboard--laporan)
- [🌐 Portal Klien](#-portal-klien)
- [🛡️ Keamanan](#️-keamanan)
- [🤝 Kontribusi](#-kontribusi)
- [📞 Support](#-support)
- [📄 Lisensi](#-lisensi)

---

## 🎯 Tentang SIMAD

**SIMAD (Sistem Informasi Manajemen Administrasi Desa)** adalah platform digital komprehensif yang dikembangkan oleh **Clasnet** untuk membantu pemerintah desa dalam mengelola administrasi, layanan masyarakat, dan operasional harian secara efisien dan modern.

### 🌟 Visi & Misi

**Visi:** Mewujudkan desa digital yang mandiri dan modern melalui teknologi informasi terdepan.

**Misi:**
- 📈 Meningkatkan efisiensi pelayanan administrasi desa
- 💡 Memberikan solusi teknologi yang mudah digunakan
- 🤝 Memfasilitasi komunikasi antara pemerintah desa dan masyarakat
- 📊 Menyediakan data dan analisis untuk pengambilan keputusan

---

## ✨ Fitur Utama

### 🏛️ **Manajemen Desa**
- **📋 Profil Desa Lengkap**: Data desa, kepala desa, sekretaris, dan admin IT
- **👥 Manajemen Pengguna**: Multi-role (Admin, Supervisor, Teknisi, Akunting, Programmer)
- **🔐 Sistem Autentikasi**: Login aman dengan session management
- **📱 Responsive Design**: Akses optimal di desktop, tablet, dan mobile

### 💼 **Produk & Layanan**
- **🛒 Katalog Produk**: Manajemen barang IT & ATK dengan stok real-time
- **⚙️ Layanan Administrasi**: Katalog layanan desa dengan tarif
- **📂 Kategori Terstruktur**: Organisasi produk dan layanan yang rapi
- **💰 Penetapan Harga**: Sistem pricing yang fleksibel

### 💳 **Transaksi & Keuangan**
- **🧾 Pembuatan Invoice**: Sistem POS terintegrasi
- **💸 Tracking Pembayaran**: Monitoring status pembayaran real-time
- **📈 Manajemen Piutang**: Sistem reminder dan pelacakan tagihan
- **🏦 Multi-Bank Support**: Integrasi dengan berbagai bank
- **📊 Laporan Keuangan**: Dashboard finansial komprehensif

### 📅 **Penjadwalan & Tracking**
- **🗓️ Kalender Kunjungan**: Jadwal kunjungan ke desa
- **📍 Tracking Lokasi**: Pelacakan kegiatan lapangan
- **⏰ Reminder Otomatis**: Notifikasi jadwal dan deadline
- **📋 Agenda Kegiatan**: Manajemen event dan kegiatan desa

### 🌐 **Portal Klien**
- **👤 Registrasi Masyarakat**: Pendaftaran akun untuk warga desa
- **📝 Pengajuan Layanan**: Sistem permohonan online
- **💬 Konsultasi Digital**: Komunikasi langsung dengan petugas
- **📱 Mobile App**: Aplikasi Android untuk akses mudah

### 📊 **Pelaporan & Analytics**
- **📈 Dashboard Interaktif**: Visualisasi data real-time
- **📄 Export Multi-Format**: PDF, Excel, CSV
- **📊 Grafik & Chart**: Analisis tren dan performa
- **🎯 KPI Monitoring**: Indikator kinerja utama

### 🛠️ **Fitur Teknis**
- **🔄 PWA Support**: Progressive Web App untuk pengalaman native
- **☁️ Cloud Ready**: Siap deploy ke cloud hosting
- **🔒 Security First**: Enkripsi data dan proteksi CSRF
- **📱 Mobile Responsive**: Optimized untuk semua perangkat
- **🚀 Performance**: Optimasi kecepatan dan efisiensi

---

## 🏗️ Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────┐
│                    SIMAD Architecture                       │
├─────────────────────────────────────────────────────────────┤
│  Frontend Layer                                             │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐          │
│  │   Web App   │ │ Mobile App  │ │   PWA       │          │
│  │ (Bootstrap) │ │ (Android)   │ │ (Service    │          │
│  │             │ │             │ │  Worker)    │          │
│  └─────────────┘ └─────────────┘ └─────────────┘          │
├─────────────────────────────────────────────────────────────┤
│  Backend Layer                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │                PHP Application                          ││
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐      ││
│  │  │    Auth     │ │   Business  │ │     API     │      ││
│  │  │   Module    │ │    Logic    │ │   Endpoints │      ││
│  │  └─────────────┘ └─────────────┘ └─────────────┘      ││
│  └─────────────────────────────────────────────────────────┘│
├─────────────────────────────────────────────────────────────┤
│  Database Layer                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │                MySQL Database                           ││
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐      ││
│  │  │    Users    │ │   Desa      │ │ Transaksi   │      ││
│  │  │   Tables    │ │   Tables    │ │   Tables    │      ││
│  │  └─────────────┘ └─────────────┘ └─────────────┘      ││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

---

## ⚙️ Persyaratan Sistem

### 🖥️ **Server Requirements**

| Komponen | Minimum | Recommended |
|----------|---------|-------------|
| **PHP** | 7.4+ | 8.0+ |
| **MySQL** | 5.7+ | 8.0+ |
| **Web Server** | Apache 2.4+ / Nginx 1.18+ | Apache 2.4+ / Nginx 1.20+ |
| **Memory** | 512MB | 2GB+ |
| **Storage** | 1GB | 5GB+ |
| **Bandwidth** | 10Mbps | 100Mbps+ |

### 📱 **Client Requirements**

| Platform | Minimum | Recommended |
|----------|---------|-------------|
| **Browser** | Chrome 70+, Firefox 65+, Safari 12+ | Latest versions |
| **Mobile** | Android 7.0+, iOS 12+ | Android 10+, iOS 14+ |
| **Screen** | 320px width | 1024px+ width |
| **Internet** | 1Mbps | 5Mbps+ |

### 🔧 **PHP Extensions**
```
- PDO & PDO_MySQL
- OpenSSL
- Mbstring
- Tokenizer
- XML
- Ctype
- JSON
- BCMath
- Fileinfo
- GD (untuk image processing)
```

---

## 🚀 Instalasi

### 📦 **1. Download & Extract**

```bash
# Clone repository
git clone https://github.com/diskonnekted/Simad-Non-CI.git
cd Simad-Non-CI

# Atau download ZIP dan extract
wget https://github.com/diskonnekted/Simad-Non-CI/archive/main.zip
unzip main.zip
```

### 🗄️ **2. Setup Database**

```sql
-- Buat database baru
CREATE DATABASE simad_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import struktur database
mysql -u username -p simad_db < database/database.sql

-- Import data sample (opsional)
mysql -u username -p simad_db < simadorbitdev_smd.sql
```

### ⚙️ **3. Konfigurasi**

**Edit file `config/database.php`:**
```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'simad_db');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_URL', 'http://localhost/simad');
define('APP_NAME', 'SIMAD - Sistem Desa');
define('APP_VERSION', '2.0.0');
?>
```

### 🔐 **4. Setup Permissions**

```bash
# Set permissions untuk folder upload dan cache
chmod 755 uploads/
chmod 755 tmp/
chmod 755 export/
chmod 755 backup/

# Set ownership (jika diperlukan)
chown -R www-data:www-data /path/to/simad/
```

### 🌐 **5. Web Server Configuration**

**Apache (.htaccess sudah included):**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/simad;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 🎯 **6. Instalasi Otomatis**

Akses `http://your-domain.com/install.php` untuk setup otomatis:

1. **Cek Requirements** - Verifikasi server requirements
2. **Database Setup** - Konfigurasi koneksi database
3. **Admin Account** - Buat akun administrator pertama
4. **Finalisasi** - Selesaikan instalasi

---

## 📱 Penggunaan

### 👨‍💼 **Admin Dashboard**

**Login:** `http://your-domain.com/login.php`

**Default Admin:**
- Username: `admin`
- Password: `admin123`

**Menu Utama:**
- 🏠 **Dashboard**: Overview statistik dan grafik
- 👥 **Pengguna**: Manajemen user dan role
- 🏛️ **Data Desa**: Profil dan informasi desa
- 💼 **Produk & Layanan**: Katalog dan inventory
- 💳 **Transaksi**: Pembuatan dan tracking transaksi
- 💰 **Keuangan**: Piutang, pembayaran, dan laporan
- 📅 **Jadwal**: Kalender kunjungan dan agenda
- 📊 **Laporan**: Analytics dan export data

### 🌐 **Portal Klien**

**Akses:** `http://your-domain.com/client/`

**Fitur untuk Masyarakat:**
- 📝 **Registrasi**: Daftar akun baru
- 🔐 **Login**: Akses portal pribadi
- 📋 **Layanan**: Ajukan permohonan administrasi
- 💬 **Konsultasi**: Chat dengan petugas desa
- 📅 **Kalender**: Lihat jadwal dan agenda
- 🛒 **Pemesanan**: Order produk dan layanan
- 📱 **Mobile App**: Download aplikasi Android

### 📱 **Progressive Web App (PWA)**

**Instalasi PWA:**
1. Buka website di browser mobile
2. Tap menu "Add to Home Screen"
3. Konfirmasi instalasi
4. Akses seperti aplikasi native

**Fitur PWA:**
- ⚡ **Offline Support**: Akses terbatas tanpa internet
- 🔔 **Push Notifications**: Notifikasi real-time
- 📱 **Native Feel**: Pengalaman seperti aplikasi mobile
- 🚀 **Fast Loading**: Caching untuk performa optimal

---

## 🔧 Konfigurasi

### 🎨 **Customization**

**Logo & Branding:**
```php
// Ganti logo di folder img/
img/kode-icon.png      // Logo utama
img/icon-72x72.png     // PWA icon 72x72
img/icon-96x96.png     // PWA icon 96x96
favicon.ico            // Browser favicon
```

**Tema & Warna:**
```css
/* Edit css/root.css */
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
}
```

### 📧 **Email Configuration**

**Setup SMTP:**
```php
// config/email.php
$email_config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_secure' => 'tls',
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'SIMAD System'
];
```

### 🔔 **Notification Settings**

**Push Notifications:**
```javascript
// js/pwa-features.js
const notificationConfig = {
    enabled: true,
    vapidKey: 'your-vapid-key',
    serverKey: 'your-server-key'
};
```

### 💾 **Backup Configuration**

**Automated Backup:**
```php
// config/backup.php
$backup_config = [
    'enabled' => true,
    'schedule' => 'daily', // daily, weekly, monthly
    'retention' => 30,     // days to keep backups
    'storage' => 'local',  // local, cloud
    'email_reports' => true
];
```

---

## 📊 Dashboard & Laporan

### 📈 **Dashboard Analytics**

**Statistik Real-time:**
- 💰 **Pendapatan Harian/Bulanan**
- 📊 **Grafik Transaksi**
- 👥 **Jumlah Pengguna Aktif**
- 🏛️ **Status Desa**
- 📋 **Piutang Outstanding**
- 📅 **Jadwal Hari Ini**

**Visualisasi Data:**
- 📊 **Chart.js**: Grafik interaktif
- 📈 **Trend Analysis**: Analisis tren bulanan
- 🎯 **KPI Widgets**: Key Performance Indicators
- 🗺️ **Geographic View**: Peta sebaran desa

### 📄 **Sistem Laporan**

**Jenis Laporan:**
- 💰 **Laporan Keuangan**: Income statement, cash flow
- 📊 **Laporan Transaksi**: Detail per periode
- 👥 **Laporan Pengguna**: Aktivitas dan engagement
- 🏛️ **Laporan Desa**: Profil dan statistik
- 📅 **Laporan Jadwal**: Kunjungan dan kegiatan

**Format Export:**
- 📄 **PDF**: Layout profesional dengan header/footer
- 📊 **Excel**: Data terstruktur untuk analisis
- 📋 **CSV**: Format universal untuk import/export
- 🖨️ **Print**: Optimized untuk pencetakan

---

## 🌐 Portal Klien

### 👤 **Registrasi & Login**

**Proses Registrasi:**
1. **Form Pendaftaran**: Data pribadi dan kontak
2. **Verifikasi Email**: Konfirmasi melalui email
3. **Aktivasi Akun**: Admin approval (opsional)
4. **Login Pertama**: Setup profil lengkap

**Fitur Login:**
- 🔐 **Secure Authentication**: Password hashing
- 🔄 **Remember Me**: Persistent login
- 🔑 **Password Reset**: Recovery via email
- 📱 **Multi-device**: Akses dari berbagai perangkat

### 📋 **Layanan Online**

**Jenis Layanan:**
- 📄 **Surat Keterangan**: KTP, KK, Domisili, dll
- 💼 **Izin Usaha**: SIUP, TDP, IMB
- 🎓 **Pendidikan**: Beasiswa, bantuan sekolah
- 🏥 **Kesehatan**: BPJS, rujukan kesehatan
- 🌾 **Pertanian**: Bantuan pupuk, bibit

**Proses Pengajuan:**
1. **Pilih Layanan**: Browse katalog layanan
2. **Isi Form**: Data dan dokumen pendukung
3. **Upload Berkas**: Scan dokumen required
4. **Submit**: Kirim permohonan
5. **Tracking**: Monitor status real-time
6. **Notifikasi**: Update via email/SMS
7. **Pengambilan**: Jadwal pickup/delivery

### 💬 **Konsultasi Online**

**Fitur Chat:**
- 💬 **Real-time Messaging**: Chat langsung dengan petugas
- 📎 **File Sharing**: Upload dokumen dan gambar
- 🕐 **Chat History**: Riwayat percakapan tersimpan
- 👥 **Multi-agent**: Routing ke petugas yang tepat
- 📱 **Mobile Optimized**: Chat responsive di mobile

**Kategori Konsultasi:**
- 📋 **Administrasi**: Bantuan pengisian form
- 💰 **Keuangan**: Informasi biaya dan pembayaran
- 🔧 **Teknis**: Bantuan penggunaan sistem
- 📞 **Umum**: Informasi dan keluhan

---

## 🛡️ Keamanan

### 🔐 **Authentication & Authorization**

**Security Features:**
- 🔑 **Password Hashing**: Bcrypt encryption
- 🛡️ **CSRF Protection**: Token-based validation
- 🔒 **Session Security**: Secure session handling
- 🚫 **SQL Injection Prevention**: Prepared statements
- 🛡️ **XSS Protection**: Input sanitization

**Role-based Access:**
```php
// Roles dan permissions
$roles = [
    'admin' => ['full_access'],
    'supervisor' => ['manage_desa', 'view_reports'],
    'teknisi' => ['manage_products', 'handle_support'],
    'akunting' => ['manage_finance', 'view_reports'],
    'programmer' => ['system_maintenance', 'debug_access']
];
```

### 🔒 **Data Protection**

**Encryption:**
- 🔐 **Database**: Sensitive data encryption
- 🛡️ **File Upload**: Virus scanning
- 🔒 **Communication**: HTTPS enforcement
- 💾 **Backup**: Encrypted backup files

**Privacy Compliance:**
- 📋 **Data Minimization**: Collect only necessary data
- 🔄 **Data Retention**: Automated cleanup policies
- 👤 **User Rights**: Data access and deletion rights
- 📊 **Audit Logs**: Complete activity tracking

### 🚨 **Security Monitoring**

**Logging System:**
```php
// Activity logging
$security_events = [
    'login_attempts',
    'failed_authentications',
    'privilege_escalations',
    'data_modifications',
    'system_access'
];
```

**Alerts & Notifications:**
- 🚨 **Failed Login Attempts**: Brute force detection
- 🔍 **Suspicious Activity**: Anomaly detection
- 📧 **Security Reports**: Daily/weekly summaries
- 🛡️ **Incident Response**: Automated threat response

---

## 🤝 Kontribusi

### 👨‍💻 **Development Guidelines**

**Code Standards:**
- 📝 **PSR-12**: PHP coding standards
- 🎯 **Clean Code**: Readable dan maintainable
- 📚 **Documentation**: Inline comments dan docs
- 🧪 **Testing**: Unit dan integration tests

**Git Workflow:**
```bash
# Fork repository
git fork https://github.com/diskonnekted/Simad-Non-CI.git

# Create feature branch
git checkout -b feature/new-feature

# Commit changes
git commit -m "feat: add new feature"

# Push dan create PR
git push origin feature/new-feature
```

### 🐛 **Bug Reports**

**Issue Template:**
```markdown
## Bug Description
Brief description of the bug

## Steps to Reproduce
1. Step one
2. Step two
3. Step three

## Expected Behavior
What should happen

## Actual Behavior
What actually happens

## Environment
- PHP Version:
- MySQL Version:
- Browser:
- OS:
```

### 💡 **Feature Requests**

**Request Template:**
```markdown
## Feature Description
Clear description of the proposed feature

## Use Case
Why is this feature needed?

## Proposed Solution
How should this feature work?

## Alternatives
Any alternative solutions considered?
```

---

## 📞 Support

### 🏢 **Clasnet Support**

**Contact Information:**
- 🌐 **Website**: [https://clasnet.id](https://clasnet.id)
- 📧 **Email**: support@clasnet.id
- 📱 **WhatsApp**: +62 812-3456-7890
- 📞 **Phone**: +62 21-1234-5678

**Support Hours:**
- 🕘 **Senin - Jumat**: 08:00 - 17:00 WIB
- 🕘 **Sabtu**: 08:00 - 12:00 WIB
- 🚨 **Emergency**: 24/7 untuk klien premium

### 📚 **Documentation**

**Resources:**
- 📖 **User Manual**: Panduan lengkap penggunaan
- 🎥 **Video Tutorials**: Tutorial step-by-step
- 💡 **FAQ**: Pertanyaan yang sering diajukan
- 🔧 **API Documentation**: Dokumentasi teknis

**Training & Consultation:**
- 👨‍🏫 **On-site Training**: Pelatihan di lokasi
- 💻 **Remote Training**: Pelatihan online
- 🤝 **Consultation**: Konsultasi implementasi
- 🛠️ **Custom Development**: Pengembangan khusus

### 🆘 **Emergency Support**

**Critical Issues:**
- 🚨 **System Down**: Response time < 1 hour
- 🔒 **Security Breach**: Immediate response
- 💾 **Data Loss**: Recovery assistance
- 🐛 **Critical Bugs**: Priority fixing

**Support Channels:**
- 📧 **Email**: emergency@clasnet.id
- 📱 **WhatsApp**: +62 812-EMERGENCY
- 📞 **Hotline**: +62 21-EMERGENCY
- 💬 **Live Chat**: Available on website

---

## 📄 Lisensi

### 📋 **License Information**

**SIMAD** dikembangkan oleh **Clasnet** dan dilisensikan untuk penggunaan komersial dan non-komersial dengan ketentuan sebagai berikut:

**Hak Penggunaan:**
- ✅ **Penggunaan Komersial**: Diizinkan untuk keperluan bisnis
- ✅ **Modifikasi**: Dapat dimodifikasi sesuai kebutuhan
- ✅ **Distribusi**: Dapat didistribusikan dengan credit
- ✅ **Private Use**: Penggunaan internal organisasi

**Kewajiban:**
- 📝 **Attribution**: Wajib mencantumkan credit Clasnet
- 📋 **License Notice**: Sertakan notice lisensi
- 🔄 **Share Alike**: Modifikasi harus menggunakan lisensi sama
- 📊 **Reporting**: Laporan penggunaan untuk statistik

**Pembatasan:**
- ❌ **Trademark Use**: Tidak boleh menggunakan trademark Clasnet
- ❌ **Liability**: Clasnet tidak bertanggung jawab atas kerusakan
- ❌ **Warranty**: Tidak ada garansi tersurat atau tersirat
- ❌ **Resale**: Tidak boleh dijual ulang tanpa izin

### 🤝 **Partnership**

**Menjadi Partner Clasnet:**
- 🏢 **Reseller Program**: Program kemitraan reseller
- 🎓 **Training Certification**: Sertifikasi implementer
- 💼 **Business Partnership**: Kerjasama strategis
- 🌐 **Regional Partner**: Partner wilayah

**Benefits:**
- 💰 **Revenue Sharing**: Bagi hasil yang menarik
- 🎯 **Marketing Support**: Dukungan pemasaran
- 🔧 **Technical Support**: Support teknis prioritas
- 📈 **Business Growth**: Peluang pengembangan bisnis

---

<div align="center">

## 🚀 **Ready to Transform Your Village?**

**SIMAD** adalah solusi terdepan untuk digitalisasi desa. Bergabunglah dengan ratusan desa yang telah merasakan manfaat teknologi modern.

[![Get Started](https://img.shields.io/badge/Get%20Started-Now-success?style=for-the-badge&logo=rocket)](https://clasnet.id/contact)
[![Demo](https://img.shields.io/badge/Try%20Demo-Free-blue?style=for-the-badge&logo=play)](http://demo.simad.clasnet.id)
[![Contact](https://img.shields.io/badge/Contact%20Us-Support-orange?style=for-the-badge&logo=phone)](mailto:support@clasnet.id)

---

**© 2025 Clasnet. All rights reserved.**

*Dikembangkan dengan ❤️ untuk kemajuan desa Indonesia*

</div>