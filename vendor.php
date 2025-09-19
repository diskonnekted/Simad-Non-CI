<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek login dan role
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Hanya admin dan sales yang bisa akses
if (!in_array($_SESSION['role'], ['admin', 'sales'])) {
    header('Location: 404.html');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $vendor_id = (int)$_GET['id'];
    
    // Cek apakah vendor digunakan dalam produk
    $check_query = "SELECT COUNT(*) as count FROM produk WHERE vendor_id = :vendor_id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':vendor_id', $vendor_id);
    $check_stmt->execute();
    $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($check_result['count'] > 0) {
        // Jika digunakan, nonaktifkan saja
        $update_query = "UPDATE vendor SET status = 'nonaktif' WHERE id = :id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':id', $vendor_id);
        $update_stmt->execute();
        $message = "Vendor berhasil dinonaktifkan karena masih digunakan dalam produk.";
    } else {
        // Jika tidak digunakan, hapus permanen
        $delete_query = "DELETE FROM vendor WHERE id = :id";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindParam(':id', $vendor_id);
        $delete_stmt->execute();
        $message = "Vendor berhasil dihapus.";
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$jenis_filter = isset($_GET['jenis']) ? $_GET['jenis'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'nama_vendor';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Validasi sort column
$allowed_sort = ['nama_vendor', 'nama_perusahaan', 'jenis_vendor', 'status', 'created_at'];
if (!in_array($sort, $allowed_sort)) {
    $sort = 'nama_vendor';
}

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(v.nama_vendor LIKE :search OR v.nama_perusahaan LIKE :search OR v.nama_kontak LIKE :search OR v.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($status_filter)) {
    $where_conditions[] = "v.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($jenis_filter)) {
    $where_conditions[] = "v.jenis_vendor = :jenis";
    $params[':jenis'] = $jenis_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get vendors with product count
$query = "
    SELECT v.*, 
           COUNT(p.id) as total_produk
    FROM vendor v
    LEFT JOIN produk p ON v.id = p.vendor_id
    $where_clause
    GROUP BY v.id
    ORDER BY v.$sort $order
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_vendor,
        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as vendor_aktif,
        SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as vendor_nonaktif
    FROM vendor
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

$page_title = 'Manajemen Vendor';
require_once 'layouts/header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manajemen Vendor</h1>
            <p class="text-sm text-gray-600 mt-1">Kelola data vendor dan supplier</p>
        </div>

    </div>
</div>

<!-- Main Container -->
<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-building text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Vendor</p>
                                <p class="text-2xl font-bold text-gray-900"><?= $stats['total_vendor'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Vendor Aktif</p>
                                <p class="text-2xl font-bold text-gray-900"><?= $stats['vendor_aktif'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <i class="fas fa-times-circle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Vendor Nonaktif</p>
                                <p class="text-2xl font-bold text-gray-900"><?= $stats['vendor_nonaktif'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow mb-6 p-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Cari vendor, perusahaan, kontak..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Semua Status</option>
                                <option value="aktif" <?= $status_filter == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $status_filter == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Vendor</label>
                            <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Semua Jenis</option>
                                <option value="supplier" <?= $jenis_filter == 'supplier' ? 'selected' : '' ?>>Supplier</option>
                                <option value="distributor" <?= $jenis_filter == 'distributor' ? 'selected' : '' ?>>Distributor</option>
                                <option value="manufacturer" <?= $jenis_filter == 'manufacturer' ? 'selected' : '' ?>>Manufacturer</option>
                                <option value="reseller" <?= $jenis_filter == 'reseller' ? 'selected' : '' ?>>Reseller</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2 flex items-end space-x-2">
                            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                            <a href="vendor-add.php" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Tambah Vendor
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Vendor Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'nama_vendor', 'order' => $sort == 'nama_vendor' && $order == 'ASC' ? 'DESC' : 'ASC'])) ?>" class="flex items-center">
                                            Vendor
                                            <?php if ($sort == 'nama_vendor'): ?>
                                                <i class="fas fa-sort-<?= $order == 'ASC' ? 'up' : 'down' ?> ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'jenis_vendor', 'order' => $sort == 'jenis_vendor' && $order == 'ASC' ? 'DESC' : 'ASC'])) ?>" class="flex items-center">
                                            Jenis
                                            <?php if ($sort == 'jenis_vendor'): ?>
                                                <i class="fas fa-sort-<?= $order == 'ASC' ? 'up' : 'down' ?> ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kontak</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'status', 'order' => $sort == 'status' && $order == 'ASC' ? 'DESC' : 'ASC'])) ?>" class="flex items-center">
                                            Status
                                            <?php if ($sort == 'status'): ?>
                                                <i class="fas fa-sort-<?= $order == 'ASC' ? 'up' : 'down' ?> ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($vendors as $vendor): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($vendor['nama_vendor']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($vendor['nama_perusahaan']) ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($vendor['kode_vendor']) ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php 
                                            switch($vendor['jenis_vendor']) {
                                                case 'supplier': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'distributor': echo 'bg-green-100 text-green-800'; break;
                                                case 'manufacturer': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'reseller': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= ucfirst($vendor['jenis_vendor']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div><?= htmlspecialchars($vendor['nama_kontak'] ?? '') ?></div>
                                        <div class="text-gray-500"><?= htmlspecialchars($vendor['no_hp'] ?? '') ?></div>
                                        <div class="text-gray-500"><?= htmlspecialchars($vendor['email'] ?? '') ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <?= $vendor['total_produk'] ?> produk
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?= $vendor['status'] == 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= ucfirst($vendor['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="vendor-add.php?id=<?= $vendor['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=delete&id=<?= $vendor['id'] ?>" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus vendor ini?')" 
                                               class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($vendors)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        Tidak ada data vendor ditemukan.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
        </div>

<!-- Main Container End -->
</div>

<?php if (isset($message)): ?>
<script>
    alert('<?= $message ?>');
</script>
<?php endif; ?>

<?php require_once 'layouts/footer.php'; ?>