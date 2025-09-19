<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Check if user is logged in
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get maintenance ID
$maintenance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$maintenance_id) {
    header('Location: website-desa.php');
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get maintenance data
    $stmt = $pdo->prepare("
        SELECT wm.*, d.nama_desa, d.alamat,
               mc.*, u.nama_lengkap as programmer_name, u2.nama_lengkap as penanggung_jawab_nama
        FROM website_maintenance wm 
        LEFT JOIN desa d ON wm.desa_id = d.id 
        LEFT JOIN maintenance_checklist mc ON wm.id = mc.maintenance_id
        LEFT JOIN users u ON wm.programmer_id = u.id
        LEFT JOIN users u2 ON wm.penanggung_jawab_id = u2.id
        WHERE wm.id = ?
    ");
    $stmt->execute([$maintenance_id]);
    $maintenance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$maintenance) {
        header('Location: website-desa.php');
        exit;
    }
    
    // Define checklist items with labels
    $checklist_items = [
        'install_website' => 'Install Website',
        'setup_info_desa' => 'Setup Info Desa',
        'import_database' => 'Import Database',
        'menu_standar' => 'Pembuatan Menu Standar',
        'foto_gambar' => 'Foto dan Gambar Pengisi',
        'berita_dummy' => 'Berita Dummy Pengisi Artikel (Minimal 5 Berita)',
        'no_404_page' => 'Tidak Ada Halaman 404',
        'no_505_page' => 'Tidak Ada Halaman 505',
        'sinkron_opendata' => 'Sinkron OpenData',
        'domain_resmi_kominfo' => 'Domain Resmi Kominfo (Opsional)',
        'cek_fitur_surat_cetak' => 'Cek Fitur Surat Cetak',
        'copy_template_surat' => 'Copy Template Surat',
        'rubah_foto_background_login' => 'Rubah Foto Background Login',
        'rubah_foto_profil_desa' => 'Rubah Foto Profil Desa',
        'cek_semua_fitur' => 'Cek Semua Fitur',
        'hidupkan_fitur_banner' => 'Hidupkan Fitur Banner'
    ];
    
    // Optional items
    $optional_items = ['domain_resmi_kominfo'];
    
    // Calculate completion
    $completed_items = 0;
    $total_items = 0;
    
    foreach ($checklist_items as $key => $label) {
        if (!in_array($key, $optional_items)) {
            $total_items++;
            if (isset($maintenance[$key]) && $maintenance[$key]) {
                $completed_items++;
            }
        }
    }
    
    $completion_percentage = round(($completed_items / $total_items) * 100);
    
} catch (Exception $e) {
    error_log("Error generating maintenance report: " . $e->getMessage());
    header('Location: website-maintenance-detail.php?id=' . $maintenance_id . '&error=report_failed');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Maintenance Website Desa</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .header h2 {
            font-size: 14px;
            margin-top: 5px;
            font-weight: normal;
            color: #666;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        
        .info-table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        
        .info-table td:first-child {
            background-color: #f5f5f5;
            font-weight: bold;
            width: 30%;
        }
        
        .checklist-section {
            margin-bottom: 25px;
        }
        
        .checklist-title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        
        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .checklist-table th,
        .checklist-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
            vertical-align: middle;
        }
        
        .checklist-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .checklist-table td:last-child {
            text-align: center;
            width: 20%;
            font-weight: bold;
        }
        
        .status-completed {
            color: #28a745;
        }
        
        .status-pending {
            color: #dc3545;
        }
        
        .payment-notice {
            background-color: #e8f5e8;
            padding: 15px;
            margin: 20px 0;
            border: 2px solid #4CAF50;
            border-radius: 5px;
        }
        
        .payment-notice h3 {
            margin-top: 0;
            color: #2e7d32;
        }
        
        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 20px;
            border: none;
        }
        
        .signature-space {
            height: 80px;
            margin: 20px 0;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .print-button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .print-button:hover {
            background-color: #0056b3;
        }
        
        .back-button {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
            margin-left: 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-button:hover {
            background-color: #545b62;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="print-button" onclick="window.print()">🖨️ Cetak Laporan</button>
        <a href="website-maintenance-detail.php?id=<?php echo $maintenance_id; ?>" class="back-button">← Kembali</a>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'pdf_failed'): ?>
            <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <strong>Info:</strong> PDF generator tidak tersedia. Menampilkan versi HTML yang dapat dicetak.
            </div>
        <?php endif; ?>
    </div>

    <div class="header">
        <h1>Berita Acara Penyelesaian Pekerjaan</h1>
        <h1>Maintenance Website Desa</h1>
        <h2>PT. Clasnet Teknologi Indonesia</h2>
    </div>

    <table class="info-table">
        <tr>
            <td>Nama Desa</td>
            <td><?php echo htmlspecialchars($maintenance['nama_desa'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td>Website</td>
            <td><?php echo htmlspecialchars($maintenance['website_url'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td>Programmer</td>
            <td><?php echo htmlspecialchars($maintenance['programmer_name'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td>Tanggal Mulai</td>
            <td><?php echo date('d F Y', strtotime($maintenance['created_at'])); ?></td>
        </tr>
        <tr>
            <td>Tanggal Selesai</td>
            <td><?php echo date('d F Y', strtotime($maintenance['updated_at'])); ?></td>
        </tr>
        <tr>
            <td>Progress Penyelesaian</td>
            <td><strong><?php echo $completion_percentage; ?>% Selesai</strong></td>
        </tr>
    </table>

    <div class="checklist-section">
        <div class="checklist-title">Checklist Maintenance Website</div>
        <table class="checklist-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Item Checklist</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($checklist_items as $key => $label): 
                    $status = isset($maintenance[$key]) && $maintenance[$key] ? 'Selesai' : 'Belum';
                    $status_class = $status === 'Selesai' ? 'status-completed' : 'status-pending';
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($label); ?></td>
                    <td class="<?php echo $status_class; ?>"><?php echo $status; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($completion_percentage >= 100): ?>
    <div class="payment-notice">
        <h3>PEMBERITAHUAN PEMBAYARAN</h3>
        <p>Berdasarkan hasil verifikasi dan penyelesaian seluruh item maintenance di atas, dengan ini dinyatakan bahwa pekerjaan maintenance website desa telah selesai <strong>100%</strong> dan dapat dilakukan pembayaran sesuai dengan kesepakatan kontrak.</p>
    </div>
    <?php endif; ?>

    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td>
                    <strong>Menyetujui,</strong><br>
                    <strong>Penanggung Jawab</strong>
                    <div class="signature-space"></div>
                    <u><?php echo htmlspecialchars($maintenance['penanggung_jawab_nama'] ?? '-'); ?></u>
                </td>
                <td>
                    <strong>Programmer</strong>
                    <div class="signature-space"></div>
                    <u><?php echo htmlspecialchars($maintenance['programmer_name'] ?? '-'); ?></u>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <em>Dokumen ini dibuat secara otomatis pada <?php echo date('d F Y H:i:s'); ?></em><br>
        <em>PT. Clasnet Teknologi Indonesia</em>
    </div>
</body>
</html>