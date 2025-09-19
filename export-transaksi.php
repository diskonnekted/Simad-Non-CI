<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();

// Cek akses - hanya admin dan finance yang bisa export
if (!AuthStatic::hasRole(['admin', 'finance'])) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$db = getDatabase();

// Parameter filter
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? '';
$desa_id = $_GET['desa_id'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Build query dengan filter
$where_conditions = [];
$params = [];

if (!empty($start_date)) {
    $where_conditions[] = "DATE(t.created_at) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $where_conditions[] = "DATE(t.created_at) <= ?";
    $params[] = $end_date;
}

if (!empty($status)) {
    $where_conditions[] = "t.status_transaksi = ?";
    $params[] = $status;
}

if (!empty($desa_id)) {
    $where_conditions[] = "t.desa_id = ?";
    $params[] = $desa_id;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query untuk mengambil data transaksi
$query = "
    SELECT 
        t.id,
        t.nomor_invoice,
        t.created_at as tanggal_transaksi,
        d.nama_desa,
        d.kecamatan,
        d.kabupaten,
        u.nama_lengkap as sales_name,
        t.status_transaksi,
        t.status_pembayaran,
        t.total_amount,
        t.dp_amount,
        t.catatan,
        CASE 
            WHEN t.status_pembayaran = 'dp' THEN t.total_amount - t.dp_amount
            WHEN t.status_pembayaran = 'tempo' THEN t.total_amount
            ELSE 0
        END as sisa_hutang
    FROM transaksi t
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    {$where_clause}
    ORDER BY t.created_at DESC
";

$transaksi_data = $db->select($query, $params);

if ($format === 'csv') {
    // Set headers untuk download CSV
    $filename = 'export_transaksi_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    // BOM untuk UTF-8 agar Excel bisa baca dengan benar
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header CSV
    fputcsv($output, [
        'ID',
        'Nomor Invoice',
        'Tanggal Transaksi',
        'Nama Desa',
        'Kecamatan',
        'Kabupaten',
        'Sales',
        'Status Transaksi',
        'Status Pembayaran',
        'Total Amount',
        'DP Amount',
        'Sisa Hutang',
        'Catatan'
    ]);
    
    // Data rows
    foreach ($transaksi_data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['nomor_invoice'],
            date('d/m/Y H:i', strtotime($row['tanggal_transaksi'])),
            $row['nama_desa'],
            $row['kecamatan'],
            $row['kabupaten'],
            $row['sales_name'],
            ucfirst($row['status_transaksi']),
            ucfirst($row['status_pembayaran']),
            number_format($row['total_amount'], 0, ',', '.'),
            number_format($row['dp_amount'], 0, ',', '.'),
            number_format($row['sisa_hutang'], 0, ',', '.'),
            $row['catatan']
        ]);
    }
    
    fclose($output);
    exit;
}

// Jika bukan CSV, tampilkan form filter
$page_title = 'Export Data Transaksi';
require_once 'layouts/header.php';

// Ambil daftar desa untuk filter
$desa_list = $db->select("SELECT id, nama_desa FROM desa WHERE status = 'aktif' ORDER BY nama_desa");
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">
                <i class="fas fa-download mr-2 text-blue-500"></i>
                Export Data Transaksi
            </h1>
            <p class="text-sm text-gray-600 mt-1">Export data transaksi ke format CSV untuk analisis atau backup</p>
        </div>
        
        <div class="p-6">
            <form method="GET" action="export-transaksi.php" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Filter Tanggal -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Filter Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Transaksi</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Status</option>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="diproses" <?= $status === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                            <option value="dikirim" <?= $status === 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                            <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>
                    
                    <!-- Filter Desa -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Desa</label>
                        <select name="desa_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Desa</option>
                            <?php foreach ($desa_list as $desa): ?>
                                <option value="<?= $desa['id'] ?>" <?= $desa_id == $desa['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($desa['nama_desa']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Format Export -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Format Export</label>
                    <select name="format" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="csv" <?= $format === 'csv' ? 'selected' : '' ?>>CSV (Comma Separated Values)</option>
                    </select>
                </div>
                
                <!-- Preview Data -->
                <?php if (!empty($transaksi_data)): ?>
                <div class="bg-gray-50 p-4 rounded-md">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-eye mr-1"></i>
                        Preview Data (<?= count($transaksi_data) ?> transaksi)
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-2 py-1 text-left">Invoice</th>
                                    <th class="px-2 py-1 text-left">Tanggal</th>
                                    <th class="px-2 py-1 text-left">Desa</th>
                                    <th class="px-2 py-1 text-left">Status</th>
                                    <th class="px-2 py-1 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($transaksi_data, 0, 5) as $row): ?>
                                <tr class="border-t">
                                    <td class="px-2 py-1"><?= htmlspecialchars($row['nomor_invoice']) ?></td>
                                    <td class="px-2 py-1"><?= date('d/m/Y', strtotime($row['tanggal_transaksi'])) ?></td>
                                    <td class="px-2 py-1"><?= htmlspecialchars($row['nama_desa']) ?></td>
                                    <td class="px-2 py-1"><?= ucfirst($row['status_transaksi']) ?></td>
                                    <td class="px-2 py-1 text-right">Rp <?= number_format($row['total_amount'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (count($transaksi_data) > 5): ?>
                                <tr>
                                    <td colspan="5" class="px-2 py-1 text-center text-gray-500 italic">
                                        ... dan <?= count($transaksi_data) - 5 ?> transaksi lainnya
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="flex space-x-4">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <i class="fas fa-search mr-2"></i>
                        Preview Data
                    </button>
                    
                    <?php if (!empty($transaksi_data)): ?>
                    <a href="export-transaksi.php?<?= http_build_query(array_merge($_GET, ['format' => 'csv'])) ?>" 
                       class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        <i class="fas fa-download mr-2"></i>
                        Download CSV (<?= count($transaksi_data) ?> transaksi)
                    </a>
                    <?php endif; ?>
                    
                    <a href="transaksi.php" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'layouts/footer.php'; ?>