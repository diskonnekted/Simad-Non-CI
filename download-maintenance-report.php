<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Try to load TCPDF - fallback if not available
$tcpdf_available = false;
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    $tcpdf_available = true;
} elseif (file_exists('lib/tcpdf/tcpdf.php')) {
    require_once 'lib/tcpdf/tcpdf.php';
    $tcpdf_available = true;
}

// If TCPDF is not available, provide HTML fallback
if (!$tcpdf_available) {
    // Redirect to HTML version
    header('Location: maintenance-report-html.php?id=' . (isset($_GET['id']) ? $_GET['id'] : '0'));
    exit;
}

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
    
    // Define checklist items based on assignment type
    if ($maintenance['assignment_type'] === 'instalasi_sid') {
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
        $optional_items = ['domain_resmi_kominfo'];
    } else {
        $checklist_items = [
            'pengecekan' => 'Pengecekan',
            'proses' => 'Proses',
            'selesai' => 'Selesai'
        ];
        $optional_items = [];
    }
    
    // Assignment type labels
    $assignment_types = [
        'instalasi_sid' => 'Instalasi SID',
        'perbaikan_error_404_505' => 'Perbaikan Error 404/505',
        'update_versi_aplikasi' => 'Update Versi Aplikasi',
        'perbaikan_ssl' => 'Perbaikan SSL',
        'pemindahan_hosting_server' => 'Pemindahan Hosting Server',
        'maintenance_lainnya' => 'Maintenance Lainnya'
    ];
    
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
    
    // Custom PDF class with header
    class MaintenancePDF extends TCPDF {
        public function Header() {
            // Logo Clasnet
            $logo_path = 'img/clasnet.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 15, 10, 30, 0, 'PNG');
            }
            
            // Kop surat
            $this->SetFont('helvetica', 'B', 16);
            $this->SetXY(50, 15);
            $this->Cell(0, 10, 'Clasnet Group', 0, 1, 'L');
            
            $this->SetFont('helvetica', '', 10);
            $this->SetXY(50, 25);
            $this->Cell(0, 5, 'Jl. Serulingmas No. 31, Banjarnegara, Jawa Tengah', 0, 1, 'L');
            $this->SetXY(50, 30);
            $this->Cell(0, 5, 'Telp: (0286) 123456 | Email: info@clasnet.id', 0, 1, 'L');
            
            // Garis pemisah
            $this->SetY(40);
            $this->Line(15, 40, 195, 40);
            
            // No title here - will be added in HTML content
        }
    }

    // Create PDF using custom class
    $pdf = new MaintenancePDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Clasnet');
    $pdf->SetAuthor('Clasnet');
    $pdf->SetTitle('Berita Acara Penyelesaian Pekerjaan Maintenance Website Desa');
    $pdf->SetSubject('Laporan Maintenance');
    
    // Header is now handled by custom MaintenancePDF class
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins for proper A4 format
    $pdf->SetMargins(20, 50, 20); // Left, Top, Right margins in mm
    $pdf->SetHeaderMargin(10); // Header margin
    $pdf->SetFooterMargin(15); // Footer margin
    
    // Enable header and footer
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Calculate completion percentage
    $completion_percentage = round(($completed_items / $total_items) * 100);
    
    // Create HTML content for PDF
    $html = '
<style>
    body { font-family: Arial, sans-serif; font-size: 10px; line-height: 1.2; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
    th, td { border: 1px solid #333; padding: 4px; text-align: left; vertical-align: middle; font-size: 9px; }
    th { background-color: #f0f0f0; font-weight: bold; text-align: center; vertical-align: middle; }
    .checklist table { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
    .checklist th, .checklist td { border: 1px solid #333; padding: 4px; text-align: left; vertical-align: middle; font-size: 9px; }
    .checklist th { background-color: #f0f0f0; font-weight: bold; text-align: center; vertical-align: middle; }
    .checklist td:first-child { text-align: left; width: 80%; }
     .checklist td:last-child { text-align: center; width: 20%; }
    .header { text-align: center; margin-bottom: 15px; }
    .header h1 { font-size: 14px; margin-bottom: 3px; font-weight: bold; }
    .header h2 { font-size: 12px; margin-top: 3px; font-weight: bold; }
    .checklist h3 { margin-bottom: 8px; text-align: center; font-size: 11px; font-weight: bold; }
    .signature-section { margin-top: 15px; width: 100%; page-break-inside: avoid; }
    .signature-section table { border-collapse: collapse; }
    .signature-section td { border: none !important; }
    .payment-notice { background-color: #e8f5e8; padding: 8px; margin: 10px 0; border: 2px solid #4CAF50; border-radius: 3px; font-size: 9px; }
    .company-info td { font-size: 9px; }
</style>

<div class="header">
    <h1>MAINTENANCE WEBSITE DESA</h1>
</div>

<div class="company-info">
    <table>
        <tr>
            <td width="30%"><strong>Nama Desa</strong></td>
            <td>' . htmlspecialchars((string)($maintenance['nama_desa'] ?? '-')) . '</td>
        </tr>
        <tr>
            <td><strong>Website</strong></td>
            <td>' . htmlspecialchars($maintenance['website_url'] ?? '-') . '</td>
        </tr>
        <tr>
            <td><strong>Jenis Maintenance</strong></td>
            <td>' . htmlspecialchars($assignment_types[$maintenance['assignment_type']] ?? $maintenance['assignment_type']) . '</td>
        </tr>
        <tr>
            <td><strong>Programmer</strong></td>
            <td>' . htmlspecialchars($maintenance['programmer_name'] ?? '-') . '</td>
        </tr>
        <tr>
            <td><strong>Tanggal Mulai</strong></td>
            <td>' . date('d F Y', strtotime($maintenance['created_at'])) . '</td>
        </tr>
        <tr>
            <td><strong>Tanggal Selesai</strong></td>
            <td>' . date('d F Y', strtotime($maintenance['updated_at'])) . '</td>
        </tr>
    </table>
</div>
        
<div class="checklist">
    <h3>CHECKLIST MAINTENANCE</h3>
    <table>
        <thead>
            <tr>
                <th>Item Checklist</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';
        
        // Use the same checklist items as defined above
        $checklist_items_pdf = $checklist_items;
        
        $no = 1;
        foreach ($checklist_items_pdf as $key => $label) {
            $status = isset($maintenance[$key]) && $maintenance[$key] ? 'Selesai' : 'Belum';
            $html .= '<tr>';
            $html .= '<td>' . $label . '</td>';
            $html .= '<td>' . $status . '</td>';
            $html .= '</tr>';
            $no++;
        }
        
        $html .= '</tbody>
    </table>
</div>

<div class="payment-notice">
    <p><strong>PEMBERITAHUAN PEMBAYARAN:</strong></p>
    <p>Berdasarkan hasil verifikasi dan penyelesaian seluruh item maintenance di atas, dengan ini dinyatakan bahwa pekerjaan maintenance website desa telah selesai 100% dan dapat dilakukan pembayaran sesuai dengan kesepakatan kontrak.</p>
</div>

<div class="signature-section">
    <table style="width: 100%; margin-top: 20px;">
        <tr>
            <td style="width: 50%; text-align: center; vertical-align: top; border: none; padding: 5px;">
                <p style="margin: 2px 0;"><strong>Menyetujui,</strong></p>
                <p style="margin: 2px 0;"><strong>Penanggung Jawab</strong></p>
                <br><br>
                <p style="margin: 2px 0;"><u>' . htmlspecialchars($maintenance['penanggung_jawab_nama'] ?? '-') . '</u></p>
            </td>
            <td style="width: 50%; text-align: center; vertical-align: top; border: none; padding: 5px;">
                <p style="margin: 2px 0;"><strong>Programmer</strong></p>
                <br><br><br>
                <p style="margin: 2px 0;"><u>' . htmlspecialchars($maintenance['programmer_name'] ?? '-') . '</u></p>
            </td>
        </tr>
    </table>
</div>';

    // Add footer with date
    $html .= '<div style="margin-top: 15px; text-align: center; font-size: 8px; color: #666;">';
    $html .= '<p><em>Dokumen ini dibuat secara otomatis pada ' . date('d F Y H:i:s') . '</em></p>';
    $html .= '</div>';

    // Output the PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output('berita_acara_maintenance_' . $maintenance_id . '.pdf', 'I');
    
} catch (Exception $e) {
    error_log("Error generating maintenance report: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    
    // If TCPDF fails, redirect to HTML version
    header('Location: maintenance-report-html.php?id=' . $maintenance_id . '&error=pdf_failed');
    exit;
}
?>