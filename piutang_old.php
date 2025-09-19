<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$page_title = "Manajemen Piutang";
require_once 'layouts/header.php';

// Fungsi helper untuk format tanggal
function formatTanggal($tanggal) {
    if (!$tanggal || $tanggal == '0000-00-00') return '-';
    return date('d/m/Y', strtotime($tanggal));
}

// Fungsi helper untuk format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Handle pencarian dan filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$desa_filter = isset($_GET['desa']) ? trim($_GET['desa']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$tanggal_dari = isset($_GET['tanggal_dari']) ? trim($_GET['tanggal_dari']) : '';
$tanggal_sampai = isset($_GET['tanggal_sampai']) ? trim($_GET['tanggal_sampai']) : '';

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build query dengan kondisi filter
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.nomor_transaksi LIKE ? OR c.nama LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($desa_filter)) {
    $where_conditions[] = "c.desa = ?";
    $params[] = $desa_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($tanggal_dari)) {
    $where_conditions[] = "p.tanggal_jatuh_tempo >= ?";
    $params[] = $tanggal_dari;
}

if (!empty($tanggal_sampai)) {
    $where_conditions[] = "p.tanggal_jatuh_tempo <= ?";
    $params[] = $tanggal_sampai;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query untuk menghitung total records
$count_sql = "SELECT COUNT(*) as total 
              FROM piutang p 
              LEFT JOIN customers c ON p.customer_id = c.id 
              $where_clause";

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Sorting
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'tanggal_jatuh_tempo';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Validasi kolom sorting
$allowed_sorts = ['nomor_transaksi', 'nama', 'tanggal_jatuh_tempo', 'jumlah_piutang', 'sisa_piutang', 'status'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'tanggal_jatuh_tempo';
}

// Query utama untuk mengambil data piutang
$sql = "SELECT p.*, c.nama as customer_nama, c.desa, c.telepon 
        FROM piutang p 
        LEFT JOIN customers c ON p.customer_id = c.id 
        $where_clause 
        ORDER BY $sort_by $sort_order 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$piutang_list = $stmt->fetchAll();

// Query untuk statistik
$stats_sql = "SELECT 
                COUNT(*) as total_piutang,
                SUM(p.jumlah_piutang) as total_nilai,
                SUM(p.sisa_piutang) as total_sisa,
                COUNT(CASE WHEN p.status = 'belum_lunas' THEN 1 END) as belum_lunas,
                COUNT(CASE WHEN p.status = 'lunas' THEN 1 END) as lunas,
                COUNT(CASE WHEN p.tanggal_jatuh_tempo < CURDATE() AND p.status = 'belum_lunas' THEN 1 END) as jatuh_tempo
              FROM piutang p 
              LEFT JOIN customers c ON p.customer_id = c.id 
              $where_clause";

$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

// Handle pembayaran piutang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bayar_piutang') {
    $piutang_id = (int)$_POST['piutang_id'];
    $jumlah_bayar = (float)$_POST['jumlah_bayar'];
    $tanggal_bayar = $_POST['tanggal_bayar'];
    $keterangan = trim($_POST['keterangan']);
    
    try {
        $pdo->beginTransaction();
        
        // Ambil data piutang
        $piutang_stmt = $pdo->prepare("SELECT * FROM piutang WHERE id = ?");
        $piutang_stmt->execute([$piutang_id]);
        $piutang = $piutang_stmt->fetch();
        
        if (!$piutang) {
            throw new Exception("Data piutang tidak ditemukan");
        }
        
        if ($jumlah_bayar <= 0) {
            throw new Exception("Jumlah pembayaran harus lebih dari 0");
        }
        
        if ($jumlah_bayar > $piutang['sisa_piutang']) {
            throw new Exception("Jumlah pembayaran tidak boleh melebihi sisa piutang");
        }
        
        // Insert ke tabel pembayaran_piutang
        $insert_pembayaran = $pdo->prepare("
            INSERT INTO pembayaran_piutang (piutang_id, tanggal_bayar, jumlah_bayar, keterangan, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insert_pembayaran->execute([$piutang_id, $tanggal_bayar, $jumlah_bayar, $keterangan]);
        
        // Update sisa piutang
        $sisa_baru = $piutang['sisa_piutang'] - $jumlah_bayar;
        $status_baru = $sisa_baru <= 0 ? 'lunas' : 'belum_lunas';
        
        $update_piutang = $pdo->prepare("
            UPDATE piutang 
            SET sisa_piutang = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $update_piutang->execute([$sisa_baru, $status_baru, $piutang_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Pembayaran piutang berhasil dicatat";
        header("Location: piutang.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Ambil daftar desa untuk filter
$desa_sql = "SELECT DISTINCT desa FROM customers WHERE desa IS NOT NULL AND desa != '' ORDER BY desa";
$desa_stmt = $pdo->prepare($desa_sql);
$desa_stmt->execute();
$desa_list = $desa_stmt->fetchAll();
?>

    <!-- Main Content -->
    <div class="p-4 sm:p-6">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Manajemen Piutang</h1>
            <p class="text-gray-600">Kelola data piutang dan pembayaran customer</p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-file-invoice-dollar text-blue-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Piutang</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['total_piutang']); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-money-bill-wave text-green-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Nilai</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo formatRupiah($stats['total_nilai'] ?? 0); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Sisa Piutang</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo formatRupiah($stats['total_sisa'] ?? 0); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-clock text-red-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Jatuh Tempo</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['jatuh_tempo']); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Filter Data</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Pencarian</label>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nomor transaksi atau nama customer" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                    </div>
                    
                    <div>
                        <label for="desa" class="block text-sm font-medium text-gray-700">Desa</label>
                        <select name="desa" id="desa" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">Semua Desa</option>
                            <?php foreach ($desa_list as $desa): ?>
                                <option value="<?php echo htmlspecialchars($desa['desa']); ?>" <?php echo $desa_filter === $desa['desa'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($desa['desa']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            <option value="">Semua Status</option>
                            <option value="belum_lunas" <?php echo $status_filter === 'belum_lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                            <option value="lunas" <?php echo $status_filter === 'lunas' ? 'selected' : ''; ?>>Lunas</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="tanggal_dari" class="block text-sm font-medium text-gray-700">Tanggal Dari</label>
                        <input type="date" name="tanggal_dari" id="tanggal_dari" value="<?php echo htmlspecialchars($tanggal_dari); ?>" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                    </div>
                    
                    <div>
                        <label for="tanggal_sampai" class="block text-sm font-medium text-gray-700">Tanggal Sampai</label>
                        <input type="date" name="tanggal_sampai" id="tanggal_sampai" value="<?php echo htmlspecialchars($tanggal_sampai); ?>" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <a href="piutang.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-times mr-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Data Piutang</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Daftar piutang customer dengan status pembayaran</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'nomor_transaksi', 'order' => ($sort_by === 'nomor_transaksi' && $sort_order === 'ASC') ? 'desc' : 'asc'])); ?>" class="group inline-flex">
                                    No. Transaksi
                                    <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort"></i>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'nama', 'order' => ($sort_by === 'nama' && $sort_order === 'ASC') ? 'desc' : 'asc'])); ?>" class="group inline-flex">
                                    Customer
                                    <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort"></i>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'tanggal_jatuh_tempo', 'order' => ($sort_by === 'tanggal_jatuh_tempo' && $sort_order === 'ASC') ? 'desc' : 'asc'])); ?>" class="group inline-flex">
                                    Jatuh Tempo
                                    <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort"></i>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'jumlah_piutang', 'order' => ($sort_by === 'jumlah_piutang' && $sort_order === 'ASC') ? 'desc' : 'asc'])); ?>" class="group inline-flex">
                                    Jumlah Piutang
                                    <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort"></i>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'sisa_piutang', 'order' => ($sort_by === 'sisa_piutang' && $sort_order === 'ASC') ? 'desc' : 'asc'])); ?>" class="group inline-flex">
                                    Sisa Piutang
                                    <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort"></i>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'status', 'order' => ($sort_by === 'status' && $sort_order === 'ASC') ? 'desc' : 'asc'])); ?>" class="group inline-flex">
                                    Status
                                    <span class="ml-2 flex-none rounded text-gray-400 group-hover:text-gray-500">
                                        <i class="fas fa-sort"></i>
                                    </span>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($piutang_list)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 whitespace-nowrap text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2 text-gray-300"></i>
                                    <p>Tidak ada data piutang ditemukan</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($piutang_list as $piutang): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($piutang['nomor_transaksi']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($piutang['customer_nama']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($piutang['desa']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php 
                                        $jatuh_tempo = formatTanggal($piutang['tanggal_jatuh_tempo']);
                                        $is_overdue = $piutang['tanggal_jatuh_tempo'] < date('Y-m-d') && $piutang['status'] === 'belum_lunas';
                                        ?>
                                        <span class="<?php echo $is_overdue ? 'text-red-600 font-semibold' : ''; ?>">
                                            <?php echo $jatuh_tempo; ?>
                                            <?php if ($is_overdue): ?>
                                                <i class="fas fa-exclamation-triangle ml-1"></i>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatRupiah($piutang['jumlah_piutang']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatRupiah($piutang['sisa_piutang']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($piutang['status'] === 'lunas'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Lunas
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-clock mr-1"></i>
                                                Belum Lunas
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($piutang['status'] === 'belum_lunas'): ?>
                                            <button onclick="openPaymentModal(<?php echo $piutang['id']; ?>, '<?php echo htmlspecialchars($piutang['customer_nama']); ?>', <?php echo $piutang['sisa_piutang']; ?>)" 
                                                    class="text-primary-600 hover:text-primary-900 mr-3">
                                                <i class="fas fa-money-bill-wave mr-1"></i>
                                                Bayar
                                            </button>
                                        <?php endif; ?>
                                        <a href="piutang-detail.php?id=<?php echo $piutang['id']; ?>" class="text-gray-600 hover:text-gray-900">
                                            <i class="fas fa-eye mr-1"></i>
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo ($offset + 1); ?></span> to 
                            <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span> of 
                            <span class="font-medium"><?php echo $total_records; ?></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $page ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Pembayaran -->
    <div id="paymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Pembayaran Piutang</h3>
                    <button onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="action" value="bayar_piutang">
                    <input type="hidden" name="piutang_id" id="piutang_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer</label>
                        <p id="customer_name" class="text-sm text-gray-900 bg-gray-50 p-2 rounded"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sisa Piutang</label>
                        <p id="sisa_piutang" class="text-sm text-gray-900 bg-gray-50 p-2 rounded font-semibold"></p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="jumlah_bayar" class="block text-sm font-medium text-gray-700 mb-2">Jumlah Bayar *</label>
                        <input type="number" name="jumlah_bayar" id="jumlah_bayar" required min="1" step="0.01"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    
                    <div class="mb-4">
                        <label for="tanggal_bayar" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Bayar *</label>
                        <input type="date" name="tanggal_bayar" id="tanggal_bayar" required value="<?php echo date('Y-m-d'); ?>"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    
                    <div class="mb-6">
                        <label for="keterangan" class="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
                        <textarea name="keterangan" id="keterangan" rows="3" 
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                                  placeholder="Keterangan pembayaran (opsional)"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closePaymentModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-primary-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-primary-700">
                            Simpan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openPaymentModal(piutangId, customerName, sisaPiutang) {
            document.getElementById('piutang_id').value = piutangId;
            document.getElementById('customer_name').textContent = customerName;
            document.getElementById('sisa_piutang').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(sisaPiutang);
            document.getElementById('jumlah_bayar').max = sisaPiutang;
            document.getElementById('paymentModal').classList.remove('hidden');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
            document.getElementById('paymentForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });
    </script>

<?php require_once 'layouts/footer.php'; ?>
