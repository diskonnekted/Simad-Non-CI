<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../lib/tcpdf/tcpdf.php';

// Check authentication
if (!AuthStatic::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

// Check role permission
if (!AuthStatic::hasRole(['admin', 'sales'])) {
    header('Location: ../index.php?error=access_denied');
    exit;
}

try {
    $user = AuthStatic::getCurrentUser();
    $db = getDatabase();
    
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $kategori_filter = $_GET['kategori'] ?? '';
    $tanggal_opname = $_GET['tanggal_opname'] ?? date('Y-m-d');
    
    // Build query conditions - hanya produk yang sudah di-stock opname
    $where_conditions = ['p.status = ?', 'DATE(so.tanggal_opname) = ?'];
    $params = ['aktif', $tanggal_opname];
    
    if (!empty($search)) {
        $where_conditions[] = "(p.nama_produk LIKE ? OR p.kode_produk LIKE ? OR p.deskripsi LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($kategori_filter)) {
        $where_conditions[] = "p.kategori_id = ?";
        $params[] = $kategori_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Query untuk mengambil data produk yang sudah di-stock opname
    $query = "
        SELECT 
            p.id,
            p.kode_produk,
            p.nama_produk,
            p.stok_tersedia,
            p.stok_minimal,
            p.harga_satuan,
            k.nama_kategori,
            COALESCE(AVG(pd.harga_satuan), p.harga_satuan) as harga_average,
            COUNT(DISTINCT pd.id) as total_pembelian,
            so.stok_sistem,
            so.stok_fisik,
            so.selisih,
            so.keterangan,
            so.tanggal_opname,
            u.nama_lengkap as petugas_opname
        FROM produk p
        INNER JOIN stock_opname so ON p.id = so.produk_id
        LEFT JOIN kategori_produk k ON p.kategori_id = k.id
        LEFT JOIN pembelian_detail pd ON p.id = pd.produk_id
        LEFT JOIN pembelian pb ON pd.pembelian_id = pb.id AND pb.status_pembelian IN ('diterima_sebagian', 'diterima_lengkap')
        LEFT JOIN users u ON so.user_id = u.id
        WHERE {$where_clause}
        GROUP BY p.id, p.kode_produk, p.nama_produk, p.stok_tersedia, p.stok_minimal, p.harga_satuan, k.nama_kategori, so.stok_sistem, so.stok_fisik, so.selisih, so.keterangan, so.tanggal_opname, u.nama_lengkap
        ORDER BY p.nama_produk ASC
    ";
    
    $produk_list = $db->select($query, $params);
    
    // Helper function
    function formatRupiah($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
    
    function getStokStatus($stok_tersedia, $stok_minimal) {
        if ($stok_tersedia <= 0) {
            return 'Habis';
        } elseif ($stok_tersedia <= $stok_minimal) {
            return 'Rendah';
        } else {
            return 'Aman';
        }
    }
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('SMD System');
    $pdf->SetAuthor($user['nama_lengkap']);
    $pdf->SetTitle('Laporan Stock Opname');
    $pdf->SetSubject('Stock Opname Report');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'LAPORAN STOCK OPNAME', 'Tanggal: ' . date('d/m/Y', strtotime($tanggal_opname)) . '\nDicetak oleh: ' . $user['nama_lengkap']);
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Summary information
    $total_produk = count($produk_list);
    $total_nilai_stok_fisik = 0;
    $total_selisih_positif = 0;
    $total_selisih_negatif = 0;
    $produk_selisih_positif = 0;
    $produk_selisih_negatif = 0;
    $produk_sesuai = 0;
    
    foreach ($produk_list as $produk) {
        $total_nilai_stok_fisik += ($produk['stok_fisik'] * $produk['harga_average']);
        
        if ($produk['selisih'] > 0) {
            $total_selisih_positif += $produk['selisih'];
            $produk_selisih_positif++;
        } elseif ($produk['selisih'] < 0) {
            $total_selisih_negatif += abs($produk['selisih']);
            $produk_selisih_negatif++;
        } else {
            $produk_sesuai++;
        }
    }
    
    // Summary table
    $html = '
    <h3>Ringkasan Stock Opname - Tanggal: ' . date('d/m/Y', strtotime($tanggal_opname)) . '</h3>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <td><strong>Total Produk Diopname:</strong></td>
            <td>' . $total_produk . ' item</td>
            <td><strong>Produk Sesuai:</strong></td>
            <td>' . $produk_sesuai . ' item</td>
        </tr>
        <tr>
            <td><strong>Produk Lebih (Surplus):</strong></td>
            <td style="color: green;">' . $produk_selisih_positif . ' item (+' . number_format($total_selisih_positif) . ')</td>
            <td><strong>Produk Kurang (Minus):</strong></td>
            <td style="color: red;">' . $produk_selisih_negatif . ' item (-' . number_format($total_selisih_negatif) . ')</td>
        </tr>
        <tr>
            <td><strong>Total Nilai Stok Fisik:</strong></td>
            <td colspan="3">' . formatRupiah($total_nilai_stok_fisik) . '</td>
        </tr>
    </table>
    <br><br>
    ';
    
    // Product table header
    $html .= '
    <h3>Detail Produk yang Telah Di-Stock Opname</h3>
    <table border="1" cellpadding="3" cellspacing="0" style="font-size: 7px;">
        <thead>
            <tr style="background-color: #f0f0f0;">
                <th width="8%"><strong>Kode</strong></th>
                <th width="20%"><strong>Nama Produk</strong></th>
                <th width="10%"><strong>Kategori</strong></th>
                <th width="8%"><strong>Stok Sistem</strong></th>
                <th width="8%"><strong>Stok Fisik</strong></th>
                <th width="8%"><strong>Selisih</strong></th>
                <th width="10%"><strong>Harga Avg</strong></th>
                <th width="10%"><strong>Nilai Stok</strong></th>
                <th width="18%"><strong>Keterangan</strong></th>
            </tr>
        </thead>
        <tbody>
    ';
    
    // Product data
    foreach ($produk_list as $produk) {
        $nilai_stok = $produk['stok_fisik'] * $produk['harga_average'];
        $selisih_class = '';
        if ($produk['selisih'] > 0) {
            $selisih_class = 'color: green;';
        } elseif ($produk['selisih'] < 0) {
            $selisih_class = 'color: red;';
        }
        
        $html .= '
            <tr>
                <td>' . htmlspecialchars($produk['kode_produk']) . '</td>
                <td>' . htmlspecialchars($produk['nama_produk']) . '</td>
                <td>' . htmlspecialchars($produk['nama_kategori'] ?? '-') . '</td>
                <td>' . number_format($produk['stok_sistem']) . '</td>
                <td>' . number_format($produk['stok_fisik']) . '</td>
                <td style="' . $selisih_class . '">' . ($produk['selisih'] >= 0 ? '+' : '') . number_format($produk['selisih']) . '</td>
                <td>' . formatRupiah($produk['harga_average']) . '</td>
                <td>' . formatRupiah($nilai_stok) . '</td>
                <td>' . htmlspecialchars($produk['keterangan'] ?? '-') . '</td>
            </tr>
        ';
    }
    
    $html .= '
        </tbody>
    </table>
    <br><br>
    ';
    
    // Signature section
    $html .= '
    <table border="0" cellpadding="10" cellspacing="0" width="100%">
        <tr>
            <td width="50%" align="center">
                <strong>Petugas Stock Opname</strong><br><br><br><br>
                _________________________<br>
                Nama & Tanda Tangan
            </td>
            <td width="50%" align="center">
                <strong>Supervisor/Admin</strong><br><br><br><br>
                _________________________<br>
                Nama & Tanda Tangan
            </td>
        </tr>
    </table>
    ';
    
    // Print text using writeHTMLCell()
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $filename = 'Stock_Opname_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D'); // 'D' for download
    
} catch (Exception $e) {
    error_log("Stock opname PDF error: " . $e->getMessage());
    header('Location: ../stock-opname.php?error=' . urlencode('Gagal membuat PDF: ' . $e->getMessage()));
    exit;
}
?>