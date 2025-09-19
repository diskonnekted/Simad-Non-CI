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
/** @var Database $db */

// Fungsi helper untuk format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi helper untuk format tanggal
function formatTanggal($tanggal) {
    return date('d/m/Y', strtotime($tanggal));
}

// Parameter filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Awal bulan
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Hari ini
$status = $_GET['status'] ?? '';
$desa_id = $_GET['desa_id'] ?? '';
$sales_id = $_GET['sales_id'] ?? '';
$metode_pembayaran = $_GET['metode_pembayaran'] ?? '';
$export = $_GET['export'] ?? '';

// Filter berdasarkan role
$role_condition = "";
$role_params = [];
if ($user['role'] === 'sales') {
    $role_condition = "AND t.user_id = ?";
    $role_params[] = $user['id'];
    $sales_id = $user['id']; // Force sales filter untuk role sales
}

// Build WHERE conditions
$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?"];
$params = array_merge([$start_date, $end_date], $role_params);

if (!empty($status)) {
    $where_conditions[] = "t.status_transaksi = ?";
    $params[] = $status;
}

if (!empty($desa_id)) {
    $where_conditions[] = "t.desa_id = ?";
    $params[] = $desa_id;
}

if (!empty($sales_id) && $user['role'] !== 'sales') {
    $where_conditions[] = "t.user_id = ?";
    $params[] = $sales_id;
}

if (!empty($metode_pembayaran)) {
    $where_conditions[] = "t.metode_pembayaran = ?";
    $params[] = $metode_pembayaran;
}

$where_clause = implode(' AND ', $where_conditions);
if (!empty($role_condition)) {
    $where_clause .= " " . $role_condition;
}

// Query untuk laporan
$laporan_query = "
    SELECT 
        t.id,
        t.nomor_invoice,
        t.tanggal_transaksi,
        t.total_amount,
        t.status_transaksi,
        t.metode_pembayaran,
        t.created_at,
        d.nama_desa,
        d.kecamatan,
        u.nama_lengkap as sales_name,
        COALESCE(p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0), 0) as sisa_piutang,
        CASE 
            WHEN p.id IS NOT NULL THEN 'Ya'
            ELSE 'Tidak'
        END as ada_piutang
    FROM transaksi t
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN piutang p ON t.id = p.transaksi_id AND p.status IN ('belum_jatuh_tempo', 'mendekati_jatuh_tempo')
    LEFT JOIN (
        SELECT piutang_id, SUM(jumlah_bayar) as total_bayar
        FROM pembayaran
        WHERE piutang_id IS NOT NULL
        GROUP BY piutang_id
    ) pembayaran ON p.id = pembayaran.piutang_id
    WHERE {$where_clause}
    ORDER BY t.created_at DESC
";

$laporan_data = $db->select($laporan_query, $params);

// Query untuk ringkasan
$ringkasan_query = "
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(t.total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN t.status_transaksi = 'selesai' THEN t.total_amount ELSE 0 END), 0) as nilai_selesai,
        COALESCE(SUM(CASE WHEN t.status_transaksi = 'draft' THEN t.total_amount ELSE 0 END), 0) as nilai_draft,
        COALESCE(SUM(CASE WHEN t.status_transaksi = 'proses' THEN t.total_amount ELSE 0 END), 0) as nilai_proses,
        COALESCE(SUM(p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)), 0) as total_piutang
    FROM transaksi t
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN piutang p ON t.id = p.transaksi_id AND p.status IN ('belum_jatuh_tempo', 'mendekati_jatuh_tempo')
    LEFT JOIN (
        SELECT piutang_id, SUM(jumlah_bayar) as total_bayar
        FROM pembayaran
        WHERE piutang_id IS NOT NULL
        GROUP BY piutang_id
    ) pembayaran ON p.id = pembayaran.piutang_id
    WHERE {$where_clause}
";

$ringkasan = $db->select($ringkasan_query, $params)[0];

// Data untuk dropdown
$desa_list = $db->select("SELECT id, nama_desa, kecamatan FROM desa WHERE status = 'aktif' ORDER BY nama_desa");
$sales_list = [];
if ($user['role'] === 'admin') {
    $sales_list = $db->select("SELECT id, nama_lengkap FROM users WHERE role IN ('admin', 'sales') AND status = 'aktif' ORDER BY nama_lengkap");
}

// Export ke CSV
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_transaksi_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM untuk UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header informasi
    fputcsv($output, ['LAPORAN TRANSAKSI']);
    fputcsv($output, ['Periode: ' . formatTanggal($start_date) . ' - ' . formatTanggal($end_date)]);
    fputcsv($output, ['Digenerate: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, ['Total Transaksi: ' . $ringkasan['total_transaksi']]);
    fputcsv($output, ['Total Nilai: ' . formatRupiah($ringkasan['total_nilai'])]);
    fputcsv($output, []);
    
    // Header CSV
    fputcsv($output, [
        'No Invoice',
        'Tanggal Transaksi', 
        'Desa',
        'Kecamatan',
        'Sales',
        'Total Amount (Rp)',
        'Status',
        'Metode Pembayaran',
        'Ada Piutang',
        'Sisa Piutang (Rp)',
        'Tanggal Input'
    ]);
    
    // Data CSV
    foreach ($laporan_data as $row) {
        fputcsv($output, [
            $row['nomor_invoice'],
            formatTanggal($row['tanggal_transaksi']),
            $row['nama_desa'],
            $row['kecamatan'],
            $row['sales_name'],
            $row['total_amount'],
            ucfirst($row['status_transaksi']),
            ucfirst(str_replace('_', ' ', $row['metode_pembayaran'])),
            $row['ada_piutang'],
            $row['sisa_piutang'],
            formatTanggal($row['created_at'])
        ]);
    }
    
    fclose($output);
    exit;
}

// Export ke Excel (HTML table)
if ($export === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_transaksi_' . date('Y-m-d_H-i-s') . '.xls"');
    
    echo "<html><head><meta charset='utf-8'><style>";
    echo "table { border-collapse: collapse; width: 100%; }";
    echo "th, td { border: 1px solid #000; padding: 8px; text-align: left; }";
    echo "th { background-color: #f2f2f2; font-weight: bold; }";
    echo ".number { text-align: right; }";
    echo ".center { text-align: center; }";
    echo "</style></head><body>";
    
    echo "<h2>LAPORAN TRANSAKSI</h2>";
    echo "<table>";
    echo "<tr><td><strong>Periode:</strong></td><td>" . formatTanggal($start_date) . " - " . formatTanggal($end_date) . "</td></tr>";
    echo "<tr><td><strong>Digenerate:</strong></td><td>" . date('d/m/Y H:i:s') . "</td></tr>";
    echo "<tr><td><strong>Total Transaksi:</strong></td><td>" . number_format($ringkasan['total_transaksi']) . " transaksi</td></tr>";
    echo "<tr><td><strong>Total Nilai:</strong></td><td>" . formatRupiah($ringkasan['total_nilai']) . "</td></tr>";
    echo "<tr><td><strong>Total Piutang:</strong></td><td>" . formatRupiah($ringkasan['total_piutang']) . "</td></tr>";
    echo "</table><br>";
    
    echo "<table>";
    echo "<tr>";
    echo "<th>No Invoice</th>";
    echo "<th>Tanggal Transaksi</th>";
    echo "<th>Desa</th>";
    echo "<th>Kecamatan</th>";
    echo "<th>Sales</th>";
    echo "<th>Total Amount</th>";
    echo "<th>Status</th>";
    echo "<th>Metode Pembayaran</th>";
    echo "<th>Ada Piutang</th>";
    echo "<th>Sisa Piutang</th>";
    echo "<th>Tanggal Input</th>";
    echo "</tr>";
    
    foreach ($laporan_data as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['nomor_invoice']) . "</td>";
        echo "<td class='center'>" . formatTanggal($row['tanggal_transaksi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_desa']) . "</td>";
        echo "<td>" . htmlspecialchars($row['kecamatan']) . "</td>";
        echo "<td>" . htmlspecialchars($row['sales_name']) . "</td>";
        echo "<td class='number'>" . formatRupiah($row['total_amount']) . "</td>";
        echo "<td class='center'>" . ucfirst($row['status_transaksi']) . "</td>";
        echo "<td class='center'>" . ucfirst(str_replace('_', ' ', $row['metode_pembayaran'])) . "</td>";
        echo "<td class='center'>" . $row['ada_piutang'] . "</td>";
        echo "<td class='number'>" . formatRupiah($row['sisa_piutang']) . "</td>";
        echo "<td class='center'>" . formatTanggal($row['created_at']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</body></html>";
    exit;
}

$page_title = 'Laporan Transaksi';
require_once 'layouts/header.php';
?>

<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Laporan Transaksi</h1>
                    <p class="mt-2 text-gray-600">Laporan detail dan analisis transaksi</p>
                </div>
                <div class="flex space-x-3">
                    <a href="transaksi-dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="fas fa-chart-bar mr-2"></i>Dashboard
                    </a>
                    <a href="transaksi.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-list mr-2"></i>Daftar Transaksi
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Laporan</h3>
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <!-- Tanggal Mulai -->
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Tanggal Selesai -->
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Status</option>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="proses" <?= $status === 'proses' ? 'selected' : '' ?>>Proses</option>
                            <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>

                    <!-- Desa -->
                    <div>
                        <label for="desa_id" class="block text-sm font-medium text-gray-700 mb-1">Desa</label>
                        <select id="desa_id" name="desa_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Desa</option>
                            <?php foreach ($desa_list as $desa): ?>
                            <option value="<?= $desa['id'] ?>" <?= $desa_id == $desa['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($desa['nama_desa']) ?> - <?= htmlspecialchars($desa['kecamatan']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($user['role'] === 'admin'): ?>
                    <!-- Sales -->
                    <div>
                        <label for="sales_id" class="block text-sm font-medium text-gray-700 mb-1">Sales</label>
                        <select id="sales_id" name="sales_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Sales</option>
                            <?php foreach ($sales_list as $sales): ?>
                            <option value="<?= $sales['id'] ?>" <?= $sales_id == $sales['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sales['nama_lengkap']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Metode Pembayaran -->
                    <div>
                        <label for="metode_pembayaran" class="block text-sm font-medium text-gray-700 mb-1">Metode Pembayaran</label>
                        <select id="metode_pembayaran" name="metode_pembayaran" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Metode</option>
                            <option value="tunai" <?= $metode_pembayaran === 'tunai' ? 'selected' : '' ?>>Tunai</option>
                            <option value="transfer" <?= $metode_pembayaran === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                            <option value="piutang" <?= $metode_pembayaran === 'piutang' ? 'selected' : '' ?>>Piutang</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="?" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="fas fa-refresh mr-2"></i>Reset
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                        <i class="fas fa-file-csv mr-2"></i>Export CSV
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition duration-200">
                        <i class="fas fa-file-excel mr-2"></i>Export Excel
                    </a>
                </div>
            </form>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-receipt text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Transaksi</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($ringkasan['total_transaksi']) ?></p>
                        <p class="text-sm text-gray-600"><?= formatRupiah($ringkasan['total_nilai']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Transaksi Selesai</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= formatRupiah($ringkasan['nilai_selesai']) ?></p>
                        <p class="text-sm text-gray-600">Nilai selesai</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Transaksi Proses</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= formatRupiah($ringkasan['nilai_proses']) ?></p>
                        <p class="text-sm text-gray-600">Nilai proses</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-credit-card text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Piutang</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= formatRupiah($ringkasan['total_piutang']) ?></p>
                        <p class="text-sm text-gray-600">Sisa piutang</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Laporan -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Data Transaksi (<?= number_format(count($laporan_data)) ?> transaksi)
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    Periode: <?= formatTanggal($start_date) ?> - <?= formatTanggal($end_date) ?>
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pembayaran</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Piutang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($laporan_data)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                <p>Tidak ada data transaksi untuk periode yang dipilih</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($laporan_data as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="transaksi-view.php?id=<?= $row['id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                    <?= htmlspecialchars($row['nomor_invoice']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= formatTanggal($row['tanggal_transaksi']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div>
                                    <div class="font-medium"><?= htmlspecialchars($row['nama_desa']) ?></div>
                                    <div class="text-gray-500"><?= htmlspecialchars($row['kecamatan']) ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($row['sales_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                <?= formatRupiah($row['total_amount']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php
                                    echo $row['status_transaksi'] === 'selesai' ? 'bg-green-100 text-green-800' :
                                        ($row['status_transaksi'] === 'draft' ? 'bg-gray-100 text-gray-800' :
                                        ($row['status_transaksi'] === 'proses' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'));
                                ?>">
                                    <?= ucfirst($row['status_transaksi']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="capitalize"><?= htmlspecialchars($row['metode_pembayaran']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if ($row['ada_piutang'] === 'Ya'): ?>
                                <div class="text-red-600 font-medium">
                                    <?= formatRupiah($row['sisa_piutang']) ?>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="transaksi-view.php?id=<?= $row['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (AuthStatic::hasRole(['admin', 'sales']) && $row['status_transaksi'] === 'draft'): ?>
                                <a href="transaksi-edit.php?id=<?= $row['id'] ?>" class="text-green-600 hover:text-green-900" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layouts/footer.php'; ?>