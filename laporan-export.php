<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan otorisasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'finance', 'sales'])) {
    header('Location: index.php?error=' . urlencode('Anda tidak memiliki akses ke halaman ini.'));
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Get date range from URL parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$export_type = $_GET['type'] ?? 'summary';

// Validate date range
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_' . date('Y-m-d', strtotime($start_date)) . '_to_' . date('Y-m-d', strtotime($end_date)) . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Start output buffering
ob_start();

// Get data based on export type
switch ($export_type) {
    case 'transaksi':
        $data = $db->select("
            SELECT 
                t.invoice_number,
                t.created_at,
                d.nama_desa,
                u.nama as sales_nama,
                t.payment_method,
                t.total_amount,
                t.amount_paid,
                t.remaining_amount,
                t.due_date,
                t.status_transaksi as status
            FROM transaksi t
            LEFT JOIN desa d ON t.desa_id = d.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE DATE(t.created_at) BETWEEN ? AND ?
            ORDER BY t.created_at DESC
        ", [$start_date, $end_date]);
        
        $title = 'Laporan Transaksi';
        $headers = [
            'No Invoice',
            'Tanggal',
            'Desa',
            'Sales',
            'Metode Bayar',
            'Total Amount',
            'Amount Paid',
            'Remaining',
            'Due Date',
            'Status'
        ];
        break;
        
    case 'piutang':
        $data = $db->select("
            SELECT 
                t.invoice_number,
                t.created_at,
                d.nama_desa,
                u.nama as sales_nama,
                t.total_amount,
                t.amount_paid,
                t.remaining_amount,
                t.due_date,
                CASE 
                    WHEN t.due_date < CURDATE() THEN 'Terlambat'
                    WHEN t.due_date = CURDATE() THEN 'Jatuh Tempo Hari Ini'
                    ELSE 'Belum Jatuh Tempo'
                END as status_piutang
            FROM transaksi t
            LEFT JOIN desa d ON t.desa_id = d.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.payment_method = 'tempo' AND t.remaining_amount > 0
            ORDER BY t.due_date ASC
        ");
        
        $title = 'Laporan Piutang';
        $headers = [
            'No Invoice',
            'Tanggal Transaksi',
            'Desa',
            'Sales',
            'Total Amount',
            'Amount Paid',
            'Sisa Piutang',
            'Due Date',
            'Status Piutang'
        ];
        break;
        
    case 'sales':
        $data = $db->select("
            SELECT 
                u.nama as sales_nama,
                COUNT(t.id) as total_transaksi,
                SUM(t.total_amount) as total_penjualan,
                SUM(t.amount_paid) as total_terbayar,
                SUM(t.remaining_amount) as total_piutang,
                AVG(t.total_amount) as rata_rata_transaksi
            FROM users u
            LEFT JOIN transaksi t ON u.id = t.user_id AND DATE(t.created_at) BETWEEN ? AND ?
            WHERE u.role = 'sales' AND u.status = 'aktif'
            GROUP BY u.id, u.nama
            ORDER BY total_penjualan DESC
        ", [$start_date, $end_date]);
        
        $title = 'Laporan Kinerja Sales';
        $headers = [
            'Nama Sales',
            'Total Transaksi',
            'Total Penjualan',
            'Total Terbayar',
            'Total Piutang',
            'Rata-rata per Transaksi'
        ];
        break;
        
    case 'produk':
        $data = $db->select("
            SELECT 
                p.kode_produk,
                p.nama_produk,
                k.nama_kategori,
                SUM(td.quantity) as total_terjual,
                SUM(td.subtotal) as total_pendapatan,
                AVG(td.price) as harga_rata_rata,
                p.stok_current
            FROM produk p
            LEFT JOIN kategori k ON p.kategori_id = k.id
            LEFT JOIN transaksi_detail td ON p.id = td.item_id AND td.item_type = 'produk'
            LEFT JOIN transaksi t ON td.transaksi_id = t.id AND DATE(t.created_at) BETWEEN ? AND ?
            WHERE p.status = 'aktif'
            GROUP BY p.id, p.kode_produk, p.nama_produk, k.nama_kategori, p.stok_current
            ORDER BY total_terjual DESC
        ", [$start_date, $end_date]);
        
        $title = 'Laporan Produk';
        $headers = [
            'Kode Produk',
            'Nama Produk',
            'Kategori',
            'Total Terjual',
            'Total Pendapatan',
            'Harga Rata-rata',
            'Stok Saat Ini'
        ];
        break;
        
    case 'layanan':
        $data = $db->select("
            SELECT 
                l.kode_layanan,
                l.nama_layanan,
                k.nama_kategori,
                SUM(td.quantity) as total_terjual,
                SUM(td.subtotal) as total_pendapatan,
                AVG(td.price) as harga_rata_rata,
                l.durasi_estimasi
            FROM layanan l
            LEFT JOIN kategori k ON l.kategori_id = k.id
            LEFT JOIN transaksi_detail td ON l.id = td.item_id AND td.item_type = 'layanan'
            LEFT JOIN transaksi t ON td.transaksi_id = t.id AND DATE(t.created_at) BETWEEN ? AND ?
            WHERE l.status = 'aktif'
            GROUP BY l.id, l.kode_layanan, l.nama_layanan, k.nama_kategori, l.durasi_estimasi
            ORDER BY total_terjual DESC
        ", [$start_date, $end_date]);
        
        $title = 'Laporan Layanan';
        $headers = [
            'Kode Layanan',
            'Nama Layanan',
            'Kategori',
            'Total Terjual',
            'Total Pendapatan',
            'Harga Rata-rata',
            'Durasi Estimasi (menit)'
        ];
        break;
        
    default: // summary
        // Get summary data
        $summary_data = [];
        
        // Total Pendapatan
        $pendapatan = $db->select("
            SELECT 
                COUNT(*) as total_transaksi,
                SUM(total_amount) as total_pendapatan,
                SUM(amount_paid) as total_terbayar,
                SUM(remaining_amount) as total_piutang,
                SUM(CASE WHEN payment_method = 'tunai' THEN total_amount ELSE 0 END) as pendapatan_tunai,
                SUM(CASE WHEN payment_method = 'dp' THEN amount_paid ELSE 0 END) as pendapatan_dp,
                SUM(CASE WHEN payment_method = 'tempo' THEN amount_paid ELSE 0 END) as pendapatan_tempo
            FROM transaksi 
            WHERE DATE(created_at) BETWEEN ? AND ?
        ", [$start_date, $end_date]);
        
        $summary_data[] = ['Kategori', 'Nilai'];
        $summary_data[] = ['Total Transaksi', $pendapatan[0]['total_transaksi'] ?? 0];
        $summary_data[] = ['Total Pendapatan', 'Rp ' . number_format($pendapatan[0]['total_pendapatan'] ?? 0, 0, ',', '.')];
        $summary_data[] = ['Total Terbayar', 'Rp ' . number_format($pendapatan[0]['total_terbayar'] ?? 0, 0, ',', '.')];
        $summary_data[] = ['Total Piutang', 'Rp ' . number_format($pendapatan[0]['total_piutang'] ?? 0, 0, ',', '.')];
        $summary_data[] = ['Pendapatan Tunai', 'Rp ' . number_format($pendapatan[0]['pendapatan_tunai'] ?? 0, 0, ',', '.')];
        $summary_data[] = ['Pendapatan DP', 'Rp ' . number_format($pendapatan[0]['pendapatan_dp'] ?? 0, 0, ',', '.')];
        $summary_data[] = ['Pendapatan Tempo', 'Rp ' . number_format($pendapatan[0]['pendapatan_tempo'] ?? 0, 0, ',', '.')];
        
        $data = $summary_data;
        $title = 'Laporan Ringkasan';
        $headers = [];
        break;
}

// Format currency function
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount ?? 0, 0, ',', '.');
}

// Format number function
function formatNumber($number) {
    return number_format($number ?? 0, 0, ',', '.');
}

// Format date function
function formatDate($date) {
    return $date ? date('d/m/Y', strtotime($date)) : '-';
}

// Format datetime function
function formatDateTime($datetime) {
    return $datetime ? date('d/m/Y H:i', strtotime($datetime)) : '-';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $title ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .info {
            margin-bottom: 15px;
        }
        .number {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2><?= $title ?></h2>
        <p>Periode: <?= formatDate($start_date) ?> - <?= formatDate($end_date) ?></p>
        <p>Dicetak pada: <?= date('d/m/Y H:i:s') ?> oleh <?= htmlspecialchars($user['nama']) ?></p>
    </div>
    
    <table>
        <?php if (!empty($headers)): ?>
        <thead>
            <tr>
                <?php foreach ($headers as $header): ?>
                <th><?= htmlspecialchars($header) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <?php endif; ?>
        <tbody>
            <?php foreach ($data as $row): ?>
            <tr>
                <?php if ($export_type === 'transaksi'): ?>
                    <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                    <td><?= formatDateTime($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['nama_desa']) ?></td>
                    <td><?= htmlspecialchars($row['sales_nama']) ?></td>
                    <td><?= ucfirst($row['payment_method']) ?></td>
                    <td class="number"><?= formatCurrency($row['total_amount']) ?></td>
                    <td class="number"><?= formatCurrency($row['amount_paid']) ?></td>
                    <td class="number"><?= formatCurrency($row['remaining_amount']) ?></td>
                    <td><?= $row['due_date'] ? formatDate($row['due_date']) : '-' ?></td>
                    <td><?= ucfirst($row['status']) ?></td>
                <?php elseif ($export_type === 'piutang'): ?>
                    <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                    <td><?= formatDateTime($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['nama_desa']) ?></td>
                    <td><?= htmlspecialchars($row['sales_nama']) ?></td>
                    <td class="number"><?= formatCurrency($row['total_amount']) ?></td>
                    <td class="number"><?= formatCurrency($row['amount_paid']) ?></td>
                    <td class="number"><?= formatCurrency($row['remaining_amount']) ?></td>
                    <td><?= formatDate($row['due_date']) ?></td>
                    <td><?= htmlspecialchars($row['status_piutang']) ?></td>
                <?php elseif ($export_type === 'sales'): ?>
                    <td><?= htmlspecialchars($row['sales_nama']) ?></td>
                    <td class="number"><?= formatNumber($row['total_transaksi']) ?></td>
                    <td class="number"><?= formatCurrency($row['total_penjualan']) ?></td>
                    <td class="number"><?= formatCurrency($row['total_terbayar']) ?></td>
                    <td class="number"><?= formatCurrency($row['total_piutang']) ?></td>
                    <td class="number"><?= formatCurrency($row['rata_rata_transaksi']) ?></td>
                <?php elseif ($export_type === 'produk'): ?>
                    <td><?= htmlspecialchars($row['kode_produk']) ?></td>
                    <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                    <td class="number"><?= formatNumber($row['total_terjual']) ?></td>
                    <td class="number"><?= formatCurrency($row['total_pendapatan']) ?></td>
                    <td class="number"><?= formatCurrency($row['harga_rata_rata']) ?></td>
                    <td class="number"><?= formatNumber($row['stok_current']) ?></td>
                <?php elseif ($export_type === 'layanan'): ?>
                    <td><?= htmlspecialchars($row['kode_layanan']) ?></td>
                    <td><?= htmlspecialchars($row['nama_layanan']) ?></td>
                    <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                    <td class="number"><?= formatNumber($row['total_terjual']) ?></td>
                    <td class="number"><?= formatCurrency($row['total_pendapatan']) ?></td>
                    <td class="number"><?= formatCurrency($row['harga_rata_rata']) ?></td>
                    <td class="number"><?= formatNumber($row['durasi_estimasi']) ?></td>
                <?php else: // summary ?>
                    <td><?= htmlspecialchars($row[0]) ?></td>
                    <td class="number"><?= htmlspecialchars($row[1]) ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if ($export_type === 'transaksi' || $export_type === 'piutang'): ?>
    <div style="margin-top: 20px;">
        <h4>Ringkasan:</h4>
        <p>Total Records: <?= count($data) ?></p>
        <?php if ($export_type === 'transaksi'): ?>
        <p>Total Pendapatan: <?= formatCurrency(array_sum(array_column($data, 'total_amount'))) ?></p>
        <p>Total Terbayar: <?= formatCurrency(array_sum(array_column($data, 'amount_paid'))) ?></p>
        <p>Total Piutang: <?= formatCurrency(array_sum(array_column($data, 'remaining_amount'))) ?></p>
        <?php elseif ($export_type === 'piutang'): ?>
        <p>Total Piutang: <?= formatCurrency(array_sum(array_column($data, 'remaining_amount'))) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</body>
</html>
<?php
// Get the content and clean the buffer
$content = ob_get_clean();

// Output the content
echo $content;

// Log the export activity
try {
    $db->insert('system_updates', [
        'user_id' => $user['id'],
        'action' => 'export_laporan',
        'description' => "Export laporan {$export_type} periode {$start_date} - {$end_date}",
        'reference_type' => 'laporan',
        'reference_id' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    // Ignore logging errors
}
?>
