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
    return date('d/m/Y H:i', strtotime($tanggal));
}

// Parameter filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Awal bulan
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Hari ini
$produk_id = $_GET['produk_id'] ?? '';
$movement_type = $_GET['movement_type'] ?? '';
$search = $_GET['search'] ?? '';
$export = $_GET['export'] ?? '';

// Filter berdasarkan role
$role_condition = "";
$role_params = [];
if ($user['role'] === 'sales') {
    $role_condition = "AND sm.user_id = ?";
    $role_params[] = $user['id'];
}

// Build WHERE conditions
$where_conditions = ["DATE(sm.created_at) BETWEEN ? AND ?"];
$params = array_merge([$start_date, $end_date], $role_params);

if (!empty($produk_id)) {
    $where_conditions[] = "sm.produk_id = ?";
    $params[] = $produk_id;
}

if (!empty($movement_type)) {
    $where_conditions[] = "sm.movement_type = ?";
    $params[] = $movement_type;
}

if (!empty($search)) {
    $where_conditions[] = "(p.nama_produk LIKE ? OR sm.reference_number LIKE ? OR sm.keterangan LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);
if (!empty($role_condition)) {
    $where_clause .= " " . $role_condition;
}

// Query stock movement
$movement_query = "
    SELECT 
        sm.*,
        p.nama_produk,
        p.kode_produk,
        p.satuan,
        u.nama_lengkap as user_name,
        CASE 
            WHEN sm.reference_type = 'transaksi' THEN t.nomor_invoice
            WHEN sm.reference_type = 'pembelian' THEN pb.nomor_pembelian
            ELSE sm.reference_number
        END as reference_display
    FROM stock_movement sm
    JOIN produk p ON sm.produk_id = p.id
    LEFT JOIN users u ON sm.user_id = u.id
    LEFT JOIN transaksi t ON sm.reference_type = 'transaksi' AND sm.reference_id = t.id
    LEFT JOIN pembelian pb ON sm.reference_type = 'pembelian' AND sm.reference_id = pb.id
    WHERE {$where_clause}
    ORDER BY sm.created_at DESC
";

$movement_data = $db->select($movement_query, $params);

// Query untuk ringkasan
$summary_query = "
    SELECT 
        COUNT(*) as total_movement,
        SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END) as total_out,
        COUNT(DISTINCT produk_id) as produk_affected
    FROM stock_movement sm
    WHERE {$where_clause}
";

$summary = $db->select($summary_query, $params)[0];

// Stock movement per produk (top 10)
$produk_movement_query = "
    SELECT 
        p.nama_produk,
        p.kode_produk,
        p.stok_tersedia,
        SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as total_out,
        COUNT(sm.id) as total_movement
    FROM stock_movement sm
    JOIN produk p ON sm.produk_id = p.id
    WHERE {$where_clause}
    GROUP BY p.id, p.nama_produk, p.kode_produk, p.stok_tersedia
    ORDER BY total_movement DESC
    LIMIT 10
";

$produk_movement = $db->select($produk_movement_query, $params);

// Data untuk dropdown
$produk_list = $db->select("SELECT id, nama_produk, kode_produk FROM produk WHERE status = 'aktif' ORDER BY nama_produk");

// Export ke CSV
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stock_movement_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM untuk UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header CSV
    fputcsv($output, [
        'Tanggal',
        'Produk',
        'Kode Produk',
        'Tipe Movement',
        'Quantity',
        'Satuan',
        'Stock Sebelum',
        'Stock Sesudah',
        'Referensi',
        'Tipe Referensi',
        'User',
        'Keterangan'
    ]);
    
    // Data CSV
    foreach ($movement_data as $row) {
        fputcsv($output, [
            formatTanggal($row['created_at']),
            $row['nama_produk'],
            $row['kode_produk'],
            ucfirst($row['movement_type']),
            $row['quantity'],
            $row['satuan'],
            $row['stock_before'],
            $row['stock_after'],
            $row['reference_display'],
            ucfirst($row['reference_type']),
            $row['user_name'],
            $row['keterangan']
        ]);
    }
    
    fclose($output);
    exit;
}

$page_title = 'Stock Movement';
require_once 'layouts/header.php';
?>

<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Stock Movement</h1>
                    <p class="mt-2 text-gray-600">Tracking pergerakan stok produk dari transaksi</p>
                </div>
                <div class="flex space-x-3">
                    <a href="transaksi-dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="fas fa-chart-bar mr-2"></i>Dashboard
                    </a>
                    <a href="produk.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-boxes mr-2"></i>Produk
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Stock Movement</h3>
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

                    <!-- Produk -->
                    <div>
                        <label for="produk_id" class="block text-sm font-medium text-gray-700 mb-1">Produk</label>
                        <select id="produk_id" name="produk_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Produk</option>
                            <?php foreach ($produk_list as $produk): ?>
                            <option value="<?= $produk['id'] ?>" <?= $produk_id == $produk['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($produk['kode_produk']) ?> - <?= htmlspecialchars($produk['nama_produk']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tipe Movement -->
                    <div>
                        <label for="movement_type" class="block text-sm font-medium text-gray-700 mb-1">Tipe Movement</label>
                        <select id="movement_type" name="movement_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Tipe</option>
                            <option value="in" <?= $movement_type === 'in' ? 'selected' : '' ?>>Stock In</option>
                            <option value="out" <?= $movement_type === 'out' ? 'selected' : '' ?>>Stock Out</option>
                        </select>
                    </div>

                    <!-- Pencarian -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Pencarian</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Produk, referensi, keterangan..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                </div>
            </form>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exchange-alt text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Movement</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($summary['total_movement']) ?></p>
                        <p class="text-sm text-gray-600">Pergerakan stok</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-up text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Stock In</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($summary['total_in']) ?></p>
                        <p class="text-sm text-gray-600">Unit masuk</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-down text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Stock Out</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($summary['total_out']) ?></p>
                        <p class="text-sm text-gray-600">Unit keluar</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-boxes text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Produk Terpengaruh</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($summary['produk_affected']) ?></p>
                        <p class="text-sm text-gray-600">Jenis produk</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Top Produk Movement -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Top 10 Produk Movement</h3>
                    <div class="space-y-3">
                        <?php foreach ($produk_movement as $index => $item): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-semibold mr-3">
                                    <?= $index + 1 ?>
                                </span>
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['nama_produk']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($item['kode_produk']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900"><?= $item['total_movement'] ?> movement</p>
                                <p class="text-xs text-green-600">+<?= $item['total_in'] ?></p>
                                <p class="text-xs text-red-600">-<?= $item['total_out'] ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Detail Movement -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Detail Stock Movement (<?= number_format(count($movement_data)) ?> movement)
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Periode: <?= formatTanggal($start_date . ' 00:00:00') ?> - <?= formatTanggal($end_date . ' 23:59:59') ?>
                        </p>
                    </div>
                    <div class="overflow-x-auto max-h-96">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referensi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($movement_data)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-2xl mb-2"></i>
                                        <p>Tidak ada data movement</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($movement_data as $row): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-900">
                                        <?= date('d/m H:i', strtotime($row['created_at'])) ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                                        <div>
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($row['nama_produk']) ?></div>
                                            <div class="text-gray-500"><?= htmlspecialchars($row['kode_produk']) ?></div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $row['movement_type'] === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $row['movement_type'] === 'in' ? 'IN' : 'OUT' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-900 font-medium">
                                        <?= number_format($row['quantity']) ?> <?= htmlspecialchars($row['satuan']) ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-900">
                                        <div class="text-gray-500"><?= number_format($row['stock_before']) ?> →</div>
                                        <div class="font-medium"><?= number_format($row['stock_after']) ?></div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                                        <div>
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($row['reference_display']) ?></div>
                                            <div class="text-gray-500 capitalize"><?= htmlspecialchars($row['reference_type']) ?></div>
                                        </div>
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

        <!-- Tabel Movement Lengkap -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Riwayat Stock Movement Lengkap
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Sebelum</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Sesudah</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referensi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($movement_data)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                <p>Tidak ada data stock movement untuk periode yang dipilih</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($movement_data as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= formatTanggal($row['created_at']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div>
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['nama_produk']) ?></div>
                                    <div class="text-gray-500"><?= htmlspecialchars($row['kode_produk']) ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $row['movement_type'] === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $row['movement_type'] === 'in' ? 'Stock In' : 'Stock Out' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                <?= number_format($row['quantity']) ?> <?= htmlspecialchars($row['satuan']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= number_format($row['stock_before']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                <?= number_format($row['stock_after']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div>
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['reference_display']) ?></div>
                                    <div class="text-gray-500 capitalize"><?= htmlspecialchars($row['reference_type']) ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($row['user_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars($row['keterangan']) ?>
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