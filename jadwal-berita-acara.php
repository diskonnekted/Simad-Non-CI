<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();

// Get jadwal ID
$jadwal_id = intval($_GET['id'] ?? 0);
if ($jadwal_id <= 0) {
    header('Location: jadwal.php?error=' . urlencode('ID jadwal tidak valid.'));
    exit;
}

// Get jadwal data with related information
$jadwal = $db->select("
    SELECT jk.*, 
           d.nama_desa, d.alamat, d.nama_kepala_desa, d.no_hp_kepala_desa,
           d.nama_sekdes, d.no_hp_sekdes, d.nama_admin_it, d.no_hp_admin_it,
           u_sales.nama_lengkap as sales_name, u_sales.no_hp as sales_telepon,
           u_teknisi.nama_lengkap as teknisi_name, u_teknisi.no_hp as teknisi_telepon
    FROM jadwal_kunjungan jk
    JOIN desa d ON jk.desa_id = d.id
    JOIN users u_sales ON jk.user_id = u_sales.id
    LEFT JOIN users u_teknisi ON jk.teknisi_id = u_teknisi.id
    WHERE jk.id = ?
", [$jadwal_id]);

if (empty($jadwal)) {
    header('Location: jadwal.php?error=' . urlencode('Jadwal tidak ditemukan.'));
    exit;
}

$jadwal = $jadwal[0];

// Check authorization
if ($user['role'] === 'sales' && $jadwal['user_id'] != $user['id']) {
    header('Location: jadwal.php?error=' . urlencode('Anda hanya dapat mencetak jadwal yang Anda buat.'));
    exit;
}

// Generate nomor berita acara
$nomor_ba = 'BA/' . date('Y/m/d', strtotime($jadwal['tanggal_kunjungan'])) . '/' . str_pad($jadwal_id, 4, '0', STR_PAD_LEFT);

// Format tanggal dan waktu
$tanggal_kunjungan = date('d F Y', strtotime($jadwal['tanggal_kunjungan']));
$waktu_kunjungan = $jadwal['waktu_mulai'] ? date('H:i', strtotime($jadwal['waktu_mulai'])) : '00:00';

// Get produk yang dibawa
$produk_list = [];
try {
    $produk_list = $db->select("
        SELECT p.nama_produk, jp.quantity, kp.nama_kategori as kategori
        FROM jadwal_produk jp
        JOIN produk p ON jp.produk_id = p.id
        LEFT JOIN kategori_produk kp ON p.kategori_id = kp.id
        WHERE jp.jadwal_id = ?
    ", [$jadwal_id]);
} catch (Exception $e) {
    $produk_list = [];
}

// Get peralatan yang dibawa
$peralatan_list = [];
try {
    $peralatan_list = $db->select("
        SELECT pr.nama_peralatan, jpr.quantity as jumlah, jpr.kondisi_awal, jpr.kondisi_akhir
        FROM jadwal_peralatan jpr
        JOIN peralatan pr ON jpr.peralatan_id = pr.id
        WHERE jpr.jadwal_id = ?
    ", [$jadwal_id]);
} catch (Exception $e) {
    $peralatan_list = [];
}

// Get personal yang disertakan
$personal_list = [];
try {
    $personal_list = $db->select("
        SELECT u.nama_lengkap, jp.role_dalam_kunjungan, u.no_hp
        FROM jadwal_personal jp
        JOIN users u ON jp.user_id = u.id
        WHERE jp.jadwal_id = ?
    ", [$jadwal_id]);
} catch (Exception $e) {
    $personal_list = [];
}

// Get biaya operasional
$biaya_list = [];
try {
    $biaya_list = $db->select("
        SELECT bo.nama_biaya, jb.quantity as jumlah, bo.kategori
        FROM jadwal_biaya jb
        JOIN biaya_operasional bo ON jb.biaya_operasional_id = bo.id
        WHERE jb.jadwal_id = ?
    ", [$jadwal_id]);
} catch (Exception $e) {
    $biaya_list = [];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Acara Kunjungan - <?= htmlspecialchars($jadwal['jenis_kunjungan']) ?></title>
    <style>
        @page {
            size: 8.5in 13in; /* F4 size */
            margin: 2cm;
            orientation: portrait;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .logo {
            width: 80px;
            height: auto;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .company-details {
            flex: 1;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 0 0 10px 0;
        }
        
        .company-info {
            font-size: 12px;
            color: #666;
            line-height: 1.5;
        }
        
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 30px 0 20px 0;
            text-transform: uppercase;
        }
        
        .nomor {
            text-align: center;
            margin-bottom: 30px;
            font-weight: bold;
        }
        
        .content {
            margin-bottom: 30px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            width: 150px;
            font-weight: bold;
        }
        
        .info-value {
            flex: 1;
        }
        
        .section {
            margin: 20px 0;
        }
        
        .section-title {
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        
        .produk-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .produk-table th,
        .produk-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        
        .produk-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .checklist {
            margin: 5px 0;
        }
        
        .checklist-item {
            margin: 2px 0;
            font-size: 11px;
            line-height: 1.2;
        }
        
        .produk-table .checklist {
            margin: 0;
        }
        
        .produk-table .checklist-item {
            margin: 1px 0;
            font-size: 10px;
        }
        
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            width: 45%;
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            height: 80px;
            margin: 20px 0 10px 0;
        }
        
        .print-info {
            margin-top: 30px;
            font-size: 10px;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                -webkit-print-color-adjust: exact;
            }
        }
        
        .action-buttons {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0 5px;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
    </style>
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn">
            <i class="fa fa-print"></i> Cetak PDF
        </button>
        <a href="jadwal-view.php?id=<?= $jadwal_id ?>" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="header">
        <img src="img/clasnet.png" alt="Clasnet Group Logo" class="logo">
        <div class="company-details">
            <div class="company-name">CLASNET GROUP</div>
            <div class="company-info">
                Jalan Serulingmas No. 31, Banjarnegara<br>
                Telp: 089628713789<br>
                Email: info@clasnet.com | Website: www.clasnet.com
            </div>
        </div>
    </div>

    <div class="title">Berita Acara Kunjungan</div>
    <div class="nomor">Nomor: <?= htmlspecialchars($nomor_ba) ?></div>

    <div class="content">
        <div class="info-row">
            <div class="info-label">Tujuan</div>
            <div class="info-value">: <?= htmlspecialchars($jadwal['nama_desa']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Tanggal</div>
            <div class="info-value">: <?= $tanggal_kunjungan ?> pukul <?= $waktu_kunjungan ?> WIB</div>
        </div>
        <div class="info-row">
            <div class="info-label">Jenis Kunjungan</div>
            <div class="info-value">: <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $jadwal['jenis_kunjungan']))) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Keperluan</div>
            <div class="info-value">: <?= htmlspecialchars($jadwal['catatan_kunjungan'] ?: 'Tidak ada catatan khusus') ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Nama Personal</div>
            <div class="info-value">: <?= htmlspecialchars($jadwal['sales_name']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Nama Teknisi</div>
            <div class="info-value">: <?= htmlspecialchars($jadwal['teknisi_name'] ?: 'Tidak ditentukan') ?></div>
        </div>
    </div>

    <?php if (!empty($produk_list)): ?>
    <div class="section">
        <div class="section-title">Produk yang Dibawa</div>
        <table class="produk-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Jumlah</th>
                    <th>Kondisi Produk</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produk_list as $index => $produk): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($produk['nama_produk'] ?? 'Tidak ada nama') ?></td>
                    <td><?= htmlspecialchars($produk['kategori'] ?? 'Tidak ada kategori') ?></td>
                    <td><?= htmlspecialchars($produk['quantity'] ?? '0') ?></td>
                    <td>
                        <div class="checklist">
                            <div class="checklist-item">☐ Baik</div>
                            <div class="checklist-item">☐ Tidak Sesuai</div>
                            <div class="checklist-item">☐ Rusak</div>
                            <div class="checklist-item">☐ Cacat</div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="section">
        <div class="section-title">Produk yang Dibawa</div>
        <p><em>Tidak ada produk yang tercatat untuk kunjungan ini.</em></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($peralatan_list)): ?>
    <div class="section">
        <div class="section-title">Peralatan yang Dibawa</div>
        <table class="produk-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Peralatan</th>
                    <th>Jumlah</th>
                    <th>Kondisi Awal</th>
                    <th>Kondisi Saat Dibawa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($peralatan_list as $index => $peralatan): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($peralatan['nama_peralatan'] ?? 'Tidak ada nama') ?></td>
                    <td><?= htmlspecialchars($peralatan['jumlah'] ?? '0') ?></td>
                    <td><?= htmlspecialchars($peralatan['kondisi_awal'] ?? 'Tidak diketahui') ?></td>
                    <td><?= htmlspecialchars($peralatan['kondisi_akhir'] ?? 'Belum diperiksa') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="section">
        <div class="section-title">Peralatan yang Dibawa</div>
        <p><em>Tidak ada peralatan yang tercatat untuk kunjungan ini.</em></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($personal_list)): ?>
    <div class="section">
        <div class="section-title">Personal yang Disertakan</div>
        <table class="produk-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Lengkap</th>
                    <th>Role dalam Kunjungan</th>
                    <th>No. HP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($personal_list as $index => $personal): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($personal['nama_lengkap'] ?? 'Tidak ada nama') ?></td>
                    <td><?= htmlspecialchars($personal['role_dalam_kunjungan'] ?? 'Tidak ditentukan') ?></td>
                    <td><?= htmlspecialchars($personal['no_hp'] ?? 'Tidak ada nomor') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="section">
        <div class="section-title">Personal yang Disertakan</div>
        <p><em>Tidak ada personal tambahan yang tercatat untuk kunjungan ini.</em></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($biaya_list)): ?>
    <div class="section">
        <div class="section-title">Biaya Operasional</div>
        <table class="produk-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Biaya</th>
                    <th>Kategori</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($biaya_list as $index => $biaya): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($biaya['nama_biaya'] ?? 'Tidak ada nama') ?></td>
                    <td><?= htmlspecialchars($biaya['kategori'] ?? 'Tidak ada kategori') ?></td>
                    <td><?= htmlspecialchars($biaya['jumlah'] ?? '0') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="section">
        <div class="section-title">Biaya Operasional</div>
        <p><em>Tidak ada biaya operasional yang tercatat untuk kunjungan ini.</em></p>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Catatan Tambahan</div>
        <div style="border: 1px solid #ccc; min-height: 80px; padding: 10px;">
            <!-- Ruang untuk catatan manual -->
        </div>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div><strong>Perwakilan Desa</strong></div>
            <div class="signature-line"></div>
            <div>(...................................)</div>
            <div>Nama & Jabatan</div>
        </div>
        <div class="signature-box">
            <div><strong>Perwakilan Clasnet</strong></div>
            <div class="signature-line"></div>
            <div><?= htmlspecialchars($jadwal['sales_name'] ?? 'Tidak ditentukan') ?></div>
            <div>Sales Representative</div>
        </div>
    </div>

    <div class="print-info">
        Dicetak pada: <?= date('d/m/Y H:i:s') ?> WIB<br>
        Dicetak oleh: <?= htmlspecialchars($user['nama_lengkap'] ?? 'Unknown') ?> (<?= strtoupper($user['role'] ?? 'UNKNOWN') ?>)
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            // Add download button
            const downloadBtn = document.createElement('button');
            downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download PDF';
            downloadBtn.className = 'btn-download';
            downloadBtn.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #3b82f6;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                z-index: 1000;
                font-size: 14px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            `;
            
            downloadBtn.onclick = function() {
                window.print();
            };
            
            document.body.appendChild(downloadBtn);
            
            // Hide download button when printing
            window.addEventListener('beforeprint', function() {
                downloadBtn.style.display = 'none';
            });
            
            window.addEventListener('afterprint', function() {
                downloadBtn.style.display = 'block';
            });
        }

        // Print function
        function printPage() {
            window.print();
        }
    </script>
</body>
</html>