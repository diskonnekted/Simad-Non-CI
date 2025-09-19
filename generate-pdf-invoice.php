<?php
require_once 'config/database.php';
require_once 'lib/tcpdf/tcpdf.php'; // TCPDF library

// Start session untuk mengakses data user
session_start();

$db = getDatabase();

// Ambil ID transaksi
$transaksi_id = $_GET['id'] ?? null;
if (!$transaksi_id) {
    header('Location: transaksi.php?error=invalid_id');
    exit;
}

// Ambil data transaksi
$transaksi = $db->select("
    SELECT t.*, d.nama_desa, d.kecamatan, d.kabupaten, d.nama_kepala_desa, d.no_hp_kepala_desa, d.nama_sekdes, d.no_hp_sekdes, d.nama_admin_it, d.no_hp_admin_it,
           u.nama_lengkap as sales_name, u.email as sales_email
    FROM transaksi t
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
", [$transaksi_id]);

if (empty($transaksi)) {
    header('Location: transaksi.php?error=not_found');
    exit;
}

$transaksi = $transaksi[0];

// Pengecekan akses dihapus untuk memungkinkan akses PDF langsung

// Ambil detail transaksi
$detail_transaksi = $db->select("
    SELECT * FROM transaksi_detail 
    WHERE transaksi_id = ? 
    ORDER BY id
", [$transaksi_id]);

// Ambil data piutang jika ada
$piutang = $db->select("
    SELECT * FROM piutang 
    WHERE transaksi_id = ? AND status = 'aktif'
", [$transaksi_id]);

$piutang = !empty($piutang) ? $piutang[0] : null;

// Helper functions
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function getStatusText($status) {
    $texts = [
        'draft' => 'Draft',
        'diproses' => 'Diproses',
        'dikirim' => 'Dikirim',
        'selesai' => 'Selesai'
    ];
    return $texts[$status] ?? $status;
}

function getPaymentTypeText($type) {
    $texts = [
        'tunai' => 'Tunai',
        'dp' => 'DP (Down Payment)',
        'tempo' => 'Tempo'
    ];
    return $texts[$type] ?? $type;
}

// Buat PDF menggunakan TCPDF
class InvoicePDF extends TCPDF {
    public function Header() {
        // Logo Clasnet
        $logo_path = 'img/clasnet.png';
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 15, 10, 30, 0, 'PNG');
        }
        
        // Kop surat
        $this->SetFont('helvetica', 'B', 16);
        $this->SetXY(50, 15);
        $this->Cell(0, 10, 'CLASNET', 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetXY(50, 25);
        $this->Cell(0, 5, 'Jl. Serulingmas No 31 Banjarnegara Jateng 53412', 0, 1, 'L');
        $this->SetXY(50, 30);
        $this->Cell(0, 5, 'Telp: 089628713789', 0, 1, 'L');
        
        // Garis pemisah
        $this->SetY(40);
        $this->Line(15, 40, 195, 40);
        
        // Title Invoice - rata kiri dan nomor invoice rata kanan
        $this->SetY(45);
        $this->SetFont('helvetica', 'B', 14);
        
        // Ambil nomor invoice dari data transaksi global
        global $transaksi;
        
        // INVOICE di kiri
        $this->Cell(90, 10, 'INVOICE', 0, 0, 'L');
        
        // Nomor Invoice di kanan
        $this->Cell(90, 10, $transaksi['nomor_invoice'], 0, 1, 'R');
    }
    
    public function Footer() {
        $this->SetY(-25);
        $this->SetFont('helvetica', '', 8);
        
        // Tanda tangan
        $this->SetY(-40);
        $this->Cell(0, 5, 'Banjarnegara, ' . date('d F Y'), 0, 1, 'R');
        $this->Ln(15);
        $this->Cell(0, 5, 'Hormat kami,', 0, 1, 'R');
        $this->Ln(5);
        $this->Cell(0, 5, 'CLASNET', 0, 1, 'R');
        
        // Nomor halaman
        $this->SetY(-15);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Inisialisasi PDF
$pdf = new InvoicePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('CLASNET');
$pdf->SetAuthor('CLASNET');
$pdf->SetTitle('Invoice - ' . $transaksi['nomor_invoice']);
$pdf->SetSubject('Invoice Transaksi');
$pdf->SetKeywords('Invoice, Transaksi, CLASNET');

// Set margins
$pdf->SetMargins(15, 60, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 50);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Start content after header
$pdf->SetY(60);

// Informasi Transaksi
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'INFORMASI TRANSAKSI', 0, 1, 'L');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 9);
$info_data = [
    ['Tanggal Invoice', date('d/m/Y H:i', strtotime($transaksi['created_at']))],
    ['Total Transaksi', formatRupiah($transaksi['total_amount'])]
];

// Add payment status information
if ($transaksi['status_pembayaran'] === 'lunas') {
    $info_data[] = ['Status Pembayaran', 'LUNAS'];
} elseif ($transaksi['status_pembayaran'] === 'dp') {
    $info_data[] = ['Status Pembayaran', 'DP (Belum Lunas)'];
} elseif ($transaksi['metode_pembayaran'] === 'tempo' && $transaksi['status_pembayaran'] === 'belum_bayar') {
    $info_data[] = ['Status Pembayaran', 'BELUM BAYAR (TEMPO)'];
    $info_data[] = ['Total Hutang', formatRupiah($transaksi['total_amount'])];
}

foreach ($info_data as $info) {
    $pdf->Cell(50, 6, $info[0] . ':', 0, 0, 'L');
    $pdf->Cell(0, 6, $info[1], 0, 1, 'L');
}
$pdf->Ln(5);

// Informasi Desa
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'INFORMASI DESA', 0, 1, 'L');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 9);
$desa_data = [
    ['Nama Desa', $transaksi['nama_desa']],
    ['Kecamatan', $transaksi['kecamatan']],
    ['Kabupaten', $transaksi['kabupaten']],
    ['Kontak Person', $transaksi['nama_kepala_desa'] ?? ''],
    ['No. Telepon', $transaksi['no_hp_kepala_desa'] ?? '']
];

foreach ($desa_data as $desa) {
    $pdf->Cell(50, 6, $desa[0] . ':', 0, 0, 'L');
    $pdf->Cell(0, 6, $desa[1], 0, 1, 'L');
}
$pdf->Ln(5);

// Detail Item Transaksi
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'DETAIL ITEM TRANSAKSI', 0, 1, 'L');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

// Header tabel
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(15, 10, 'No', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Tipe', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Nama Item', 1, 0, 'C', true);
$pdf->Cell(20, 10, 'Qty', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'Harga Satuan', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'Total', 1, 1, 'C', true);

// Data tabel
$pdf->SetFont('helvetica', '', 9);
$no = 1;
foreach ($detail_transaksi as $detail) {
    $tipe = !empty($detail['produk_id']) ? 'Produk' : 'Layanan';
    $nama_item = $detail['nama_item'];
    
    // Hitung tinggi cell yang diperlukan berdasarkan panjang nama item
    $base_height = 8;
    $chars_per_line = 35; // Karakter per baris dalam kolom nama item
    $line_height = 4; // Tinggi per baris teks
    $estimated_lines = max(1, ceil(strlen($nama_item) / $chars_per_line)); // Minimal 1 baris
    $cell_height = max($base_height, $estimated_lines * $line_height + 4); // Tambah padding
    
    // Simpan posisi awal
    $start_y = $pdf->GetY();
    
    // Kolom No
    $pdf->Cell(15, $cell_height, $no++, 1, 0, 'C');
    
    // Kolom Tipe
    $pdf->Cell(25, $cell_height, $tipe, 1, 0, 'C');
    
    // Kolom Nama Item - gunakan MultiCell untuk text wrapping
    $x_nama = $pdf->GetX();
    $y_nama = $pdf->GetY();
    
    // Buat border untuk kolom nama item
    $pdf->Rect($x_nama, $y_nama, 60, $cell_height);
    
    // Tulis nama item dengan MultiCell
    $pdf->SetXY($x_nama + 1, $y_nama + 1); // Sedikit padding
    $pdf->MultiCell(58, $line_height, $nama_item, 0, 'L', false, 0);
    
    // Pindah ke kolom berikutnya dengan posisi Y yang benar
    $pdf->SetXY($x_nama + 60, $y_nama);
    
    // Kolom Qty
    $pdf->Cell(20, $cell_height, number_format($detail['quantity'], 2), 1, 0, 'C');
    
    // Kolom Harga Satuan
    $pdf->Cell(35, $cell_height, formatRupiah($detail['harga_satuan']), 1, 0, 'R');
    
    // Kolom Total
    $pdf->Cell(35, $cell_height, formatRupiah($detail['subtotal']), 1, 0, 'R');
    
    // Pindah ke baris berikutnya
    $pdf->SetXY(15, $start_y + $cell_height);
}

// Total
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(155, 10, 'TOTAL', 1, 0, 'R', true);
$pdf->Cell(35, 10, formatRupiah($transaksi['total_amount']), 1, 1, 'R', true);

// Informasi Pembayaran dan Hutang
// Perbaikan logika untuk menangani semua metode pembayaran dengan benar
$pdf->Ln(3);

// Cek metode pembayaran dari database
$metode_pembayaran = $transaksi['metode_pembayaran'];
$status_pembayaran = $transaksi['status_pembayaran'];

if ($metode_pembayaran === 'dp_pelunasan' || $status_pembayaran === 'dp') {
    // DP Payment Info
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Cell(155, 8, 'Jumlah DP yang Dibayar', 1, 0, 'R', true);
    $pdf->Cell(35, 8, formatRupiah($transaksi['dp_amount']), 1, 1, 'R', true);
    
    // Sisa Hutang
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(255, 240, 240);
    $pdf->Cell(155, 10, 'SISA HUTANG', 1, 0, 'R', true);
    $pdf->Cell(35, 10, formatRupiah($transaksi['sisa_amount']), 1, 1, 'R', true);
    
    // Tanggal jatuh tempo jika ada
    if ($transaksi['tanggal_jatuh_tempo']) {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetFillColor(255, 255, 240);
        $pdf->Cell(155, 8, 'Jatuh Tempo Pelunasan', 1, 0, 'R', true);
        $pdf->Cell(35, 8, date('d/m/Y', strtotime($transaksi['tanggal_jatuh_tempo'])), 1, 1, 'R', true);
    }
    
} elseif ($metode_pembayaran === 'tempo' || ($status_pembayaran === 'belum_bayar' && $transaksi['sisa_amount'] > 0)) {
    // Total Hutang untuk Tempo
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(255, 240, 240);
    $pdf->Cell(155, 10, 'TOTAL HUTANG', 1, 0, 'R', true);
    $pdf->Cell(35, 10, formatRupiah($transaksi['total_amount']), 1, 1, 'R', true);
    
    if ($transaksi['tanggal_jatuh_tempo']) {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetFillColor(255, 255, 240);
        $pdf->Cell(155, 8, 'Jatuh Tempo Pembayaran', 1, 0, 'R', true);
        $pdf->Cell(35, 8, date('d/m/Y', strtotime($transaksi['tanggal_jatuh_tempo'])), 1, 1, 'R', true);
    }
    
} elseif ($metode_pembayaran === 'tunai' || $status_pembayaran === 'lunas') {
    // Status Lunas
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 255, 240);
    $pdf->Cell(155, 10, 'STATUS PEMBAYARAN', 1, 0, 'R', true);
    $pdf->Cell(35, 10, 'LUNAS', 1, 1, 'R', true);
}

// Informasi Piutang jika ada
if ($piutang) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'INFORMASI PIUTANG', 0, 1, 'L');
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(3);
    
    $pdf->SetFont('helvetica', '', 9);
    if ($piutang['status'] === 'lunas') {
        $pdf->SetFillColor(240, 255, 240);
        $pdf->Cell(0, 8, 'Status: LUNAS - Piutang telah dilunasi pada ' . date('d/m/Y H:i', strtotime($piutang['updated_at'])), 1, 1, 'L', true);
    } else {
        // Status piutang belum lunas
        $pdf->SetFillColor(255, 240, 240);
        $pdf->Cell(0, 8, 'Status: BELUM LUNAS', 1, 1, 'L', true);
        $pdf->Ln(2);
        
        $piutang_data = [
            ['Jumlah Piutang Awal', formatRupiah($piutang['jumlah_piutang'])],
            ['Sisa Piutang', formatRupiah($piutang['sisa_piutang'])]
        ];
        
        if ($piutang['tanggal_jatuh_tempo']) {
            $jatuh_tempo = date('d/m/Y', strtotime($piutang['tanggal_jatuh_tempo']));
            $is_overdue = strtotime($piutang['tanggal_jatuh_tempo']) < time();
            
            if ($is_overdue) {
                $piutang_data[] = ['Jatuh Tempo', $jatuh_tempo . ' (TERLAMBAT)'];
            } else {
                $piutang_data[] = ['Jatuh Tempo', $jatuh_tempo];
            }
        }
        
        foreach ($piutang_data as $piutang_info) {
            $pdf->Cell(50, 6, $piutang_info[0] . ':', 0, 0, 'L');
            $pdf->Cell(0, 6, $piutang_info[1], 0, 1, 'L');
        }
        
        // Peringatan jika terlambat
        if (isset($is_overdue) && $is_overdue) {
            $pdf->Ln(2);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(255, 0, 0);
            $pdf->Cell(0, 6, 'PERINGATAN: Pembayaran sudah melewati jatuh tempo!', 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }
    }
}

// Catatan jika ada
if ($transaksi['catatan']) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'CATATAN', 0, 1, 'L');
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(3);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 6, $transaksi['catatan'], 0, 'L');
}

// Output PDF
$filename = 'Invoice-' . $transaksi['nomor_invoice'] . '.pdf';
$pdf->Output($filename, 'D'); // 'D' untuk download, 'I' untuk tampil di browser
?>