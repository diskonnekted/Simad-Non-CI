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
           d.nama_desa, d.alamat, d.kontak_person, d.telepon as telepon_desa,
           d.email as email_desa, d.kecamatan, d.kabupaten,
           d.nama_kepala_desa, d.no_hp_kepala_desa,
           d.nama_sekdes, d.no_hp_sekdes,
           d.nama_admin_it, d.no_hp_admin_it,
           s.nama_lengkap as sales_nama, s.no_hp as sales_telepon,
           t.nama_lengkap as teknisi_nama, t.no_hp as teknisi_telepon
    FROM jadwal_kunjungan jk
    LEFT JOIN desa d ON jk.desa_id = d.id
    LEFT JOIN users s ON jk.user_id = s.id
    LEFT JOIN users t ON jk.teknisi_id = t.id
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

// Get produk yang dibawa
$produk_list = $db->select("
    SELECT jp.*, p.nama_produk, p.kode_produk, p.harga_satuan
    FROM jadwal_produk jp
    LEFT JOIN produk p ON jp.produk_id = p.id
    WHERE jp.jadwal_id = ?
    ORDER BY p.nama_produk
", [$jadwal_id]);

// Get peralatan yang dibawa
$peralatan_list = $db->select("
    SELECT jpr.*, pr.nama_peralatan, pr.kode_peralatan
    FROM jadwal_peralatan jpr
    LEFT JOIN peralatan pr ON jpr.peralatan_id = pr.id
    WHERE jpr.jadwal_id = ?
    ORDER BY pr.nama_peralatan
", [$jadwal_id]);

// Get personal yang disertakan
$personal_list = $db->select("
    SELECT jp.*, u.nama_lengkap as nama, u.email, u.role
    FROM jadwal_personal jp
    LEFT JOIN users u ON jp.user_id = u.id
    WHERE jp.jadwal_id = ?
    ORDER BY u.nama_lengkap
", [$jadwal_id]);

// Get biaya operasional
$biaya_list = $db->select("
    SELECT jb.*, bo.nama_biaya, bo.kategori
    FROM jadwal_biaya jb
    LEFT JOIN biaya_operasional bo ON jb.biaya_operasional_id = bo.id
    WHERE jb.jadwal_id = ?
    ORDER BY bo.nama_biaya
", [$jadwal_id]);

// Get system updates history
$updates = $db->select("
    SELECT su.*, u.nama_lengkap as user_nama
    FROM system_updates su
    LEFT JOIN users u ON su.user_id = u.id
    WHERE su.reference_type = 'jadwal_kunjungan' AND su.reference_id = ?
    ORDER BY su.created_at DESC
    LIMIT 10
", [$jadwal_id]);

// Format data for display
$status_labels = [
    'dijadwalkan' => 'Dijadwalkan',
    'selesai' => 'Selesai',
    'ditunda' => 'Ditunda',
    'dibatalkan' => 'Dibatalkan'
];

$urgency_labels = [
    'rendah' => 'Rendah',
    'normal' => 'Normal',
    'tinggi' => 'Tinggi',
    'urgent' => 'Urgent'
];

$jenis_labels = [
    'maintenance' => 'Maintenance',
    'instalasi' => 'Instalasi',
    'training' => 'Training',
    'support' => 'Support'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Jadwal Kunjungan - <?= htmlspecialchars($jadwal['keperluan']) ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            .container { max-width: none; margin: 0; }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .header h2 {
            margin: 5px 0 0 0;
            font-size: 14px;
            font-weight: normal;
            color: #666;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-section h3 {
            background: #f5f5f5;
            padding: 8px 12px;
            margin: 0 0 15px 0;
            font-size: 13px;
            font-weight: bold;
            border-left: 4px solid #007bff;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-item {
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
            vertical-align: top;
        }
        
        .info-value {
            display: inline-block;
            width: calc(100% - 130px);
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-dijadwalkan { background: #d1ecf1; color: #0c5460; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-ditunda { background: #fff3cd; color: #856404; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }
        
        .urgency-rendah { color: #28a745; }
        .urgency-normal { color: #007bff; }
        .urgency-tinggi { color: #fd7e14; }
        .urgency-urgent { color: #dc3545; font-weight: bold; }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .updates-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .updates-table th,
        .updates-table td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
            font-size: 11px;
        }
        
        .updates-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            height: 60px;
            margin-bottom: 5px;
        }
        
        .print-info {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-top: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .action-buttons {
            text-align: center;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .signature-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .info-label {
                width: 100px;
            }
            
            .info-value {
                width: calc(100% - 110px);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Action Buttons -->
        <div class="action-buttons no-print">
            <a href="javascript:window.print()" class="btn">
                <i class="fa fa-print"></i> Cetak
            </a>
            <a href="jadwal-view.php?id=<?= $jadwal_id ?>" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Kembali
            </a>
        </div>

        <!-- Header -->
        <div class="header">
            <h1>Jadwal Kunjungan Lapangan</h1>
            <h2>Sistem Manajemen Desa</h2>
        </div>

        <!-- Informasi Jadwal -->
        <div class="info-section">
            <h3>Informasi Jadwal</h3>
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Nomor Jadwal:</span>
                        <span class="info-value"><?= str_pad($jadwal['id'], 6, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Jenis Kunjungan:</span>
                        <span class="info-value"><?= $jenis_labels[$jadwal['jenis_kunjungan']] ?? $jadwal['jenis_kunjungan'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tanggal & Jam:</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($jadwal['tanggal_kunjungan'])) ?> WIB</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="status-badge status-<?= $jadwal['status'] ?>">
                                <?= $status_labels[$jadwal['status']] ?? $jadwal['status'] ?>
                            </span>
                        </span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Urgency:</span>
                        <span class="info-value urgency-<?= $jadwal['urgency'] ?>">
                            <?= $urgency_labels[$jadwal['urgency']] ?? $jadwal['urgency'] ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estimasi Durasi:</span>
                        <span class="info-value">
                            <?= $jadwal['estimasi_durasi'] ? $jadwal['estimasi_durasi'] . ' menit' : '-' ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Dibuat:</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($jadwal['created_at'])) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Terakhir Update:</span>
                        <span class="info-value"><?= $jadwal['updated_at'] ? date('d/m/Y H:i', strtotime($jadwal['updated_at'])) : '-' ?></span>
                    </div>
                </div>
                <div class="full-width">
                    <div class="info-item">
                        <span class="info-label">Keperluan:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['keperluan']) ?></span>
                    </div>
                    <?php if ($jadwal['catatan_kunjungan']): ?>
                    <div class="info-item">
                        <span class="info-label">Catatan Kunjungan:</span>
                        <span class="info-value"><?= nl2br(htmlspecialchars($jadwal['catatan_kunjungan'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Informasi Desa -->
        <div class="info-section">
            <h3>Informasi Desa Tujuan</h3>
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Nama Desa:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['nama_desa']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Kecamatan:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['kecamatan']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Kabupaten:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['kabupaten']) ?></span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Kontak Person:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['kontak_person']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Telepon:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['telepon_desa']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['email_desa'] ?: '-') ?></span>
                    </div>
                </div>
                <div class="full-width">
                    <div class="info-item">
                        <span class="info-label">Alamat:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['alamat']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tim Kunjungan -->
        <div class="info-section">
            <h3>Tim Kunjungan</h3>
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Sales:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['sales_nama']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Telepon Sales:</span>
                        <span class="info-value"><?= htmlspecialchars($jadwal['sales_telepon']) ?></span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Teknisi:</span>
                        <span class="info-value"><?= $jadwal['teknisi_nama'] ? htmlspecialchars($jadwal['teknisi_nama']) : 'Belum ditentukan' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Telepon Teknisi:</span>
                        <span class="info-value"><?= $jadwal['teknisi_telepon'] ? htmlspecialchars($jadwal['teknisi_telepon']) : '-' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Produk yang Dibawa -->
        <?php if (!empty($produk_list)): ?>
        <div class="info-section">
            <h3>Produk yang Dibawa</h3>
            <table class="updates-table">
                <thead>
                    <tr>
                        <th>Nama Produk</th>
                        <th>Kode</th>
                        <th>Quantity</th>
                        <th>Harga Satuan</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_produk = 0;
                    foreach ($produk_list as $produk): 
                        $subtotal = $produk['quantity'] * $produk['harga_satuan'];
                        $total_produk += $subtotal;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($produk['nama_produk']) ?></td>
                        <td><?= htmlspecialchars($produk['kode_produk']) ?></td>
                        <td><?= $produk['quantity'] ?> unit</td>
                        <td>Rp <?= number_format($produk['harga_satuan'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #f8f9fa; font-weight: bold;">
                        <td colspan="4">Total Nilai Produk</td>
                        <td>Rp <?= number_format($total_produk, 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Peralatan yang Dibawa -->
        <?php if (!empty($peralatan_list)): ?>
        <div class="info-section">
            <h3>Peralatan yang Dibawa</h3>
            <table class="updates-table">
                <thead>
                    <tr>
                        <th>Nama Peralatan</th>
                        <th>Kode</th>
                        <th>Quantity</th>
                        <th>Kondisi Awal</th>
                        <th>Kondisi Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($peralatan_list as $peralatan): ?>
                    <tr>
                        <td><?= htmlspecialchars($peralatan['nama_peralatan']) ?></td>
                        <td><?= htmlspecialchars($peralatan['kode_peralatan']) ?></td>
                        <td><?= $peralatan['quantity'] ?> unit</td>
                        <td><?= ucfirst($peralatan['kondisi_awal']) ?></td>
                        <td><?= $peralatan['kondisi_akhir'] ? ucfirst($peralatan['kondisi_akhir']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Personal yang Disertakan -->
        <?php if (!empty($personal_list)): ?>
        <div class="info-section">
            <h3>Personal yang Disertakan</h3>
            <table class="updates-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role Sistem</th>
                        <th>Role dalam Kunjungan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($personal_list as $personal): ?>
                    <tr>
                        <td><?= htmlspecialchars($personal['nama']) ?></td>
                        <td><?= htmlspecialchars($personal['email']) ?></td>
                        <td><?= ucfirst($personal['role']) ?></td>
                        <td><?= htmlspecialchars($personal['role_dalam_kunjungan']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Biaya Operasional -->
        <?php if (!empty($biaya_list)): ?>
        <div class="info-section">
            <h3>Biaya Operasional</h3>
            <table class="updates-table">
                <thead>
                    <tr>
                        <th>Nama Biaya</th>
                        <th>Kategori</th>
                        <th>Quantity</th>
                        <th>Harga Satuan</th>
                        <th>Total Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_biaya = 0;
                    foreach ($biaya_list as $biaya): 
                        $total_biaya += $biaya['total_biaya'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($biaya['nama_biaya']) ?></td>
                        <td><?= htmlspecialchars($biaya['kategori']) ?></td>
                        <td><?= $biaya['quantity'] ?></td>
                        <td>Rp <?= number_format($biaya['harga_satuan'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($biaya['total_biaya'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #f8f9fa; font-weight: bold;">
                        <td colspan="4">Total Biaya Operasional</td>
                        <td>Rp <?= number_format($total_biaya, 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Riwayat Update -->
        <?php if (!empty($updates)): ?>
        <div class="info-section">
            <h3>Riwayat Update</h3>
            <table class="updates-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                        <th>Catatan</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($updates as $update): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($update['created_at'])) ?></td>
                        <td><?= htmlspecialchars($update['action']) ?></td>
                        <td><?= htmlspecialchars($update['description']) ?></td>
                        <td><?= htmlspecialchars($update['user_nama']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Footer & Signature -->
        <div class="footer">
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <strong>Sales</strong><br>
                    <?= htmlspecialchars($jadwal['sales_nama']) ?>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <strong>Teknisi</strong><br>
                    <?= $jadwal['teknisi_nama'] ? htmlspecialchars($jadwal['teknisi_nama']) : '(Belum ditentukan)' ?>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <strong>Kontak Person Desa</strong><br>
                    <?= htmlspecialchars($jadwal['kontak_person']) ?>
                </div>
            </div>
            
            <div class="print-info">
                Dicetak pada: <?= date('d/m/Y H:i:s') ?> WIB<br>
                Dicetak oleh: <?= htmlspecialchars($user['nama']) ?> (<?= strtoupper($user['role']) ?>)
            </div>
        </div>
    </div>

    <script>
        // Auto print when page loads (optional)
        // window.onload = function() { window.print(); }
        
        // Print function
        function printPage() {
            window.print();
        }
        
        // Handle print button click
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on print button for keyboard accessibility
            var printBtn = document.querySelector('.btn');
            if (printBtn) {
                printBtn.focus();
            }
        });
    </script>
</body>
</html>
