<?php
// Suppress all warnings and notices for clean JSON output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

session_start();
require_once __DIR__ . '/../config/database.php';

// Clean any previous output
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Cek session sederhana
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Hanya terima GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = getDatabase();
/** @var Database $db */

try {
    // Ambil parameter periode (default 6 bulan terakhir)
    $periode = intval($_GET['periode'] ?? 6);
    $periode = max(1, min(24, $periode)); // Batasi antara 1-24 bulan
    
    // Query data transaksi per bulan
    $transaksi_data = $db->select("
        SELECT 
            DATE_FORMAT(tanggal_transaksi, '%Y-%m') as bulan,
            COALESCE(SUM(total_amount), 0) as nominal_penjualan,
            COUNT(*) as jumlah_transaksi
        FROM transaksi 
        WHERE tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
        GROUP BY DATE_FORMAT(tanggal_transaksi, '%Y-%m')
        ORDER BY bulan ASC
    ", [$periode]);
    
    // Query data pembelian per bulan
    $pembelian_data = $db->select("
        SELECT 
            DATE_FORMAT(tanggal_pembelian, '%Y-%m') as bulan,
            COALESCE(SUM(total_amount), 0) as nominal_pembelian
        FROM pembelian 
        WHERE tanggal_pembelian >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
        GROUP BY DATE_FORMAT(tanggal_pembelian, '%Y-%m')
        ORDER BY bulan ASC
    ", [$periode]);
    
    // Generate array bulan untuk periode yang diminta
    $bulan_list = [];
    for ($i = $periode - 1; $i >= 0; $i--) {
        $bulan_list[] = date('Y-m', strtotime("-{$i} month"));
    }
    
    // Inisialisasi data chart
    $chart_data = [
        'labels' => [],
        'datasets' => [
            [
                'label' => 'Nominal Penjualan (Rp)',
                'data' => [],
                'borderColor' => 'rgb(59, 130, 246)',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                'yAxisID' => 'y',
                'type' => 'line',
                'tension' => 0.4
            ],
            [
                'label' => 'Jumlah Transaksi',
                'data' => [],
                'borderColor' => 'rgb(16, 185, 129)',
                'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                'yAxisID' => 'y1',
                'type' => 'bar'
            ]
        ]
    ];
    
    // Tambahkan dataset pembelian jika user memiliki akses
    if (!empty($pembelian_data)) {
        $chart_data['datasets'][] = [
            'label' => 'Nominal Pembelian (Rp)',
            'data' => [],
            'borderColor' => 'rgb(239, 68, 68)',
            'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
            'yAxisID' => 'y',
            'type' => 'line',
            'tension' => 0.4
        ];
    }
    
    // Konversi data ke array dengan key bulan
    $penjualan_by_month = [];
    foreach ($transaksi_data as $row) {
        $penjualan_by_month[$row['bulan']] = $row;
    }
    
    $pembelian_by_month = [];
    foreach ($pembelian_data as $row) {
        $pembelian_by_month[$row['bulan']] = $row;
    }
    
    // Isi data chart untuk setiap bulan
    foreach ($bulan_list as $bulan) {
        // Format label bulan
        $bulan_indo = [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Ags',
            '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
        ];
        
        $tahun = substr($bulan, 0, 4);
        $bulan_num = substr($bulan, 5, 2);
        $label = $bulan_indo[$bulan_num] . ' ' . $tahun;
        
        $chart_data['labels'][] = $label;
        
        // Data penjualan
        $penjualan = $penjualan_by_month[$bulan] ?? ['nominal_penjualan' => 0, 'jumlah_transaksi' => 0];
        $chart_data['datasets'][0]['data'][] = floatval($penjualan['nominal_penjualan']);
        $chart_data['datasets'][1]['data'][] = intval($penjualan['jumlah_transaksi']);
        
        // Data pembelian (jika ada)
        if (!empty($pembelian_data)) {
            $pembelian = $pembelian_by_month[$bulan] ?? ['nominal_pembelian' => 0];
            $chart_data['datasets'][2]['data'][] = floatval($pembelian['nominal_pembelian']);
        }
    }
    
    // Response sukses
    echo json_encode([
        'success' => true,
        'data' => $chart_data,
        'periode' => $periode
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Terjadi kesalahan saat mengambil data chart',
        'message' => $e->getMessage()
    ]);
}
?>