<?php
// Start output buffering to prevent any output before PDF generation
ob_start();

require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'lib/tcpdf/tcpdf.php';

// Check if user is logged in
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check role access
if (!AuthStatic::hasRole(['admin', 'supervisor', 'programmer'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Get parameters
$nama_desa = isset($_GET['nama_desa']) ? trim($_GET['nama_desa']) : '';
$website_url = isset($_GET['website_url']) ? trim($_GET['website_url']) : '';
$penanggung_jawab_id = isset($_GET['penanggung_jawab_id']) ? (int)$_GET['penanggung_jawab_id'] : 0;
$programmer_id = isset($_GET['programmer_id']) ? (int)$_GET['programmer_id'] : 0;
$deadline = isset($_GET['deadline']) ? $_GET['deadline'] : '';
$keterangan = isset($_GET['keterangan']) ? trim($_GET['keterangan']) : '';

// Validate required parameters
if (empty($nama_desa) || empty($website_url) || empty($penanggung_jawab_id) || empty($programmer_id) || empty($deadline)) {
    die('Parameter tidak lengkap untuk generate kontrak.');
}

// Initialize database
$db = new Database();
$pdo = $db->getConnection();

try {
    // Get penanggung jawab data
    $pj_stmt = $pdo->prepare("SELECT nama_lengkap, email, no_hp FROM users WHERE id = ?");
    $pj_stmt->execute([$penanggung_jawab_id]);
    $penanggung_jawab = $pj_stmt->fetch();
    
    if (!$penanggung_jawab) {
        die('Data penanggung jawab tidak ditemukan.');
    }
    
    // Get programmer data
    $prog_stmt = $pdo->prepare("SELECT nama_lengkap, email, no_hp FROM users WHERE id = ?");
    $prog_stmt->execute([$programmer_id]);
    $programmer = $prog_stmt->fetch();
    
    if (!$programmer) {
        die('Data programmer tidak ditemukan.');
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Custom PDF class for contract
class ContractPDF extends TCPDF {
    public function Header() {
        // Logo
        $image_file = 'img/clasnet.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 15, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Company info
        $this->SetFont('helvetica', 'B', 16);
        $this->SetXY(50, 15);
        $this->Cell(0, 10, 'CLASNET GROUP', 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetXY(50, 22);
        $this->Cell(0, 5, 'Jl. Serulingmas No. 31, Banjarnegara', 0, 1, 'L');
        $this->SetXY(50, 27);
        $this->Cell(0, 5, 'Telp: (021) 1234-5678 | Email: info@clasnet.id', 0, 1, 'L');
        
        // Line separator
        $this->SetLineWidth(0.5);
        $this->Line(15, 40, 195, 40);
        
        $this->Ln(15);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

try {
    // Create new PDF document
    $pdf = new ContractPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Clasnet Group');
    $pdf->SetAuthor('Clasnet Group');
    $pdf->SetTitle('Surat Kontrak Pekerjaan Maintenance Website');
    $pdf->SetSubject('Kontrak Maintenance Website - ' . $nama_desa);
    
    // Set default header data
    $pdf->SetHeaderData('', 0, '', '');
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, 50, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Format dates
    $tanggal_kontrak = date('d F Y');
    $deadline_formatted = date('d F Y', strtotime($deadline));
    $bulan_tahun = date('F Y');
    
    // Contract number
    $nomor_kontrak = 'CNT/' . date('Y/m/') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Contract content
    $html = '
    <style>
        .title { font-size: 14pt; font-weight: bold; text-align: center; margin-bottom: 20px; }
        .subtitle { font-size: 12pt; font-weight: bold; text-align: center; margin-bottom: 15px; }
        .content { font-size: 10pt; line-height: 1.5; text-align: justify; }
        .bold { font-weight: bold; }
        .center { text-align: center; }
        .signature-table { margin-top: 30px; }
        .signature-cell { width: 50%; text-align: center; vertical-align: top; }
        .contract-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .pasal-title { text-align: center; font-weight: bold; margin: 15px 0 10px 0; }
    </style>
    
    <div class="title">SURAT KONTRAK PEKERJAAN</div>
    <div class="subtitle">MAINTENANCE WEBSITE DESA</div>
    
    <div class="content">
        <table style="width: 100%; margin-bottom: 20px;">
            <tr>
                <td style="width: 50%;"><span class="bold">Nomor Kontrak:</span> ' . $nomor_kontrak . '</td>
                <td style="width: 50%; text-align: right;"><span class="bold">Tanggal:</span> ' . $tanggal_kontrak . '</td>
            </tr>
        </table>
        
        <p>Yang bertanda tangan di bawah ini:</p>
        
        <p><span class="bold">PIHAK PERTAMA (PEMBERI KERJA):</span><br>
        Nama: <span class="bold">CLASNET GROUP</span><br>
        Alamat: Jl. Serulingmas No. 31, Banjarnegara<br>
        Telp: (021) 1234-5678<br>
        Diwakili oleh: <span class="bold">' . htmlspecialchars($penanggung_jawab['nama_lengkap'] ?? '') . '</span><br>
        Jabatan: Penanggung Jawab Proyek</p>
        
        <p><span class="bold">PIHAK KEDUA (PENERIMA KERJA):</span><br>
        Nama: <span class="bold">' . htmlspecialchars($programmer['nama_lengkap'] ?? '') . '</span><br>
        Telp: ' . htmlspecialchars($programmer['no_hp'] ?? 'Tidak tersedia') . '<br>
        Jabatan: Programmer/Developer</p>
        
        <p>Kedua belah pihak sepakat untuk mengadakan kontrak pekerjaan maintenance website dengan ketentuan sebagai berikut:</p>
        
        <div class="pasal-title">PASAL 1 - OBJEK PEKERJAAN</div>
        <p>Pihak Kedua akan melakukan pekerjaan maintenance dan pemeliharaan website untuk:<br>
        • Nama Desa: <span class="bold">' . htmlspecialchars($nama_desa) . '</span><br>
        • Website URL: <span class="bold">' . htmlspecialchars($website_url) . '</span></p>
        
        <div class="pasal-title">PASAL 2 - RUANG LINGKUP PEKERJAAN</div>
        <p>Pekerjaan maintenance meliputi checklist sebagaimana terlampir dalam Lampiran A yang mencakup:<br>
        • Install Website<br>
        • Setup Info Desa<br>
        • Import Database<br>
        • Pembuatan Menu Standar<br>
        • Foto dan Gambar Pengisi<br>
        • Berita Dummy Pengisi Artikel (Minimal 5 Berita)<br>
        • Tidak Ada Halaman 404<br>
        • Tidak Ada Halaman 505<br>
        • Sinkron OpenData<br>
        • Domain Resmi Kominfo (Opsional)<br>
        • Cek Fitur Surat Cetak<br>
        • Copy Template Surat<br>
        • Rubah Foto Background Login<br>
        • Rubah Foto Profil Desa<br>
        • Cek Semua Fitur<br>
        • Hidupkan Fitur Banner</p>
        
        <div class="pasal-title">PASAL 3 - WAKTU PELAKSANAAN</div>
        <p>• Mulai: ' . $tanggal_kontrak . '<br>
        • Deadline: <span class="bold">' . $deadline_formatted . '</span><br>
        • Durasi: Sesuai kesepakatan kedua belah pihak</p>
        
        <div class="pasal-title">PASAL 4 - KEWAJIBAN PELAKSANA</div>
        <p>• Menyelesaikan pekerjaan sesuai standar teknis dan waktu yang disepakati<br>
        • Memberikan laporan progres secara berkala (jika diminta)<br>
        • Tidak diperkenankan menyerahkan pekerjaan kepada pihak ketiga tanpa izin tertulis dari Pemberi Kerja<br>
        • Menjaga kerahasiaan data dan informasi klien<br>
        • Memberikan dokumentasi hasil pekerjaan<br>
        • Memberikan garansi untuk pekerjaan yang telah diselesaikan</p>
        
        <div class="pasal-title">PASAL 5 - KEWAJIBAN PIHAK PERTAMA</div>
        <p>• Memberikan akses yang diperlukan untuk maintenance<br>
        • Memberikan informasi dan data yang dibutuhkan<br>
        • Melakukan pembayaran sesuai kesepakatan<br>
        • Memberikan feedback dan approval yang diperlukan</p>
        
        <div class="pasal-title">PASAL 6 - SISTEM PEMBAYARAN</div>
        <p>Pembayaran dilakukan secara bertahap berdasarkan persentase penyelesaian pekerjaan, dengan ketentuan:<br>
        • Pembayaran dilakukan langsung setelah pekerjaan selesai dan disetujui oleh Pemberi Kerja<br>
        • Setiap tugas atau fitur yang selesai akan dinilai oleh Pemberi Kerja, dan pembayaran diberikan sesuai persentase kontribusi tugas tersebut terhadap total pekerjaan<br>
        • Total nilai kontrak: Rp [Jumlah Total] (dapat dibagi ke dalam milestone jika diperlukan)<br>
        • Pembayaran dilakukan melalui transfer bank ke rekening atas nama:<br>
        &nbsp;&nbsp;Nama: [Nama Rekening]<br>
        &nbsp;&nbsp;Bank: [Nama Bank]<br>
        &nbsp;&nbsp;No. Rekening: [Nomor Rekening]</p>
        
        <div class="pasal-title">PASAL 7 - HAK PEMBERI KERJA</div>
        <p>• Meminta revisi jika hasil pekerjaan tidak sesuai<br>
        • Menunda atau menghentikan pembayaran jika pekerjaan tidak memenuhi kualitas yang ditentukan<br>
        • Berhak memutus kontrak jika Pelaksana tidak memenuhi kewajiban tanpa pemberitahuan sebelumnya</p>
        
        <div class="pasal-title">PASAL 8 - KERAHASIAAN DATA</div>
        <p>Pelaksana wajib menjaga kerahasiaan data, kode, dan informasi internal Clasnet selama dan setelah masa kontrak.</p>
        
        <div class="pasal-title">PASAL 9 - FORCE MAJEURE</div>
        <p>Kedua belah pihak tidak dapat dimintai pertanggungjawaban atas keterlambatan atau kegagalan pelaksanaan kontrak yang disebabkan oleh keadaan kahar (force majeure).</p>
        
        <div class="pasal-title">PASAL 10 - PENYELESAIAN SENGKETA</div>
        <p>Segala perselisihan yang timbul akan diselesaikan secara musyawarah mufakat. Jika tidak tercapai kesepakatan, maka akan diselesaikan melalui jalur hukum yang berlaku.</p>
        
        <div class="pasal-title">PASAL 11 - LAMPIRAN</div>
        <p>Task list / daftar pekerjaan terlampir menjadi bagian yang tidak terpisahkan dari kontrak ini.</p>';
        
        if (!empty($keterangan)) {
            $html .= '<div class="pasal-title">CATATAN KHUSUS</div><p>' . nl2br(htmlspecialchars($keterangan)) . '</p>';
        }
        
        $html .= '
        <p>Demikian kontrak ini dibuat dalam rangkap 2 (dua) yang masing-masing mempunyai kekuatan hukum yang sama dan ditandatangani oleh kedua belah pihak.</p>
    </div>
    
    <div style="page-break-before: always;"></div>
    <div class="title">LAMPIRAN A</div>
    <div class="subtitle">CHECKLIST PEKERJAAN MAINTENANCE WEBSITE</div>
    
    <div class="content">
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background-color: #f5f5f5;">
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">No.</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Item Pekerjaan</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">1.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Install Website</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">2.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Setup Info Desa</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">3.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Import Database</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">4.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Pembuatan Menu Standar</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">5.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Foto dan Gambar Pengisi</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">6.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Berita Dummy Pengisi Artikel (Minimal 5 Berita)</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">7.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Tidak Ada Halaman 404</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">8.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Tidak Ada Halaman 505</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">9.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Sinkron OpenData</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">10.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Domain Resmi Kominfo (Opsional)</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">11.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Cek Fitur Surat Cetak</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">12.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Copy Template Surat</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">13.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Rubah Foto Background Login</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">14.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Rubah Foto Profil Desa</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">15.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Cek Semua Fitur</td></tr>
                <tr><td style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 8%;">16.</td><td style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 92%;">Hidupkan Fitur Banner</td></tr>
            </tbody>
        </table>
        
        <p style="margin-top: 20px; font-size: 9pt;"><span class="bold">Catatan:</span><br>
        • Checklist ini merupakan bagian yang tidak terpisahkan dari kontrak pekerjaan<br>
        • Setiap item harus diselesaikan sesuai standar yang telah ditetapkan<br>
        • Pembayaran akan dilakukan berdasarkan penyelesaian item-item dalam checklist ini</p>
    </div>
    
    <table class="signature-table" style="width: 100%; margin-top: 40px;">
        <tr>
            <td class="signature-cell">
                <p class="center bold">PIHAK PERTAMA</p>
                <p class="center bold">CLASNET GROUP</p>
                <br><br><br><br>
                <p class="center">_________________________</p>
                <p class="center bold">' . htmlspecialchars($penanggung_jawab['nama_lengkap'] ?? '') . '</p>
                <p class="center">Penanggung Jawab Proyek</p>
            </td>
            <td class="signature-cell">
                <p class="center bold">PIHAK KEDUA</p>
                <p class="center bold">PROGRAMMER</p>
                <br><br><br><br>
                <p class="center">_________________________</p>
                <p class="center bold">' . htmlspecialchars($programmer['nama_lengkap'] ?? '') . '</p>
                <p class="center">Programmer/Developer</p>
            </td>
        </tr>
    </table>
    
    <div style="margin-top: 20px; text-align: center; font-size: 8pt; color: #666;">
        <p>Dokumen ini dibuat secara elektronik dan sah tanpa tanda tangan basah</p>
        <p>Dicetak pada: ' . date('d F Y H:i:s') . '</p>
    </div>
    ';
    
    // Print text using writeHTMLCell()
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Clean output buffer before PDF output
    ob_end_clean();
    
    // Close and output PDF document
    $filename = 'Kontrak_Maintenance_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $nama_desa) . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'I');
    
} catch (Exception $e) {
    // Clean output buffer on error
    ob_end_clean();
    
    // If PDF generation fails, show error
    echo '<h1>Error Generate Kontrak</h1>';
    echo '<p>Terjadi kesalahan saat membuat kontrak: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="website-maintenance-add.php">Kembali ke Form</a></p>';
}
?>