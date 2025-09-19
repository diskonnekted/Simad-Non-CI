<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Set page title
$page_title = 'Data Piutang';

// Redirect ke statistik jika ada parameter
if (isset($_GET['redirect']) && $_GET['redirect'] === 'statistik') {
    header('Location: statistik.php');
    exit();
}

// Filter pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$desa_filter = isset($_GET['desa']) ? $_GET['desa'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(d.nama_desa LIKE :search OR d.nama_kepala_desa LIKE :search OR t.nomor_invoice LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($desa_filter)) {
    $where_conditions[] = "p.desa_id = :desa_id";
    $params[':desa_id'] = $desa_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Hitung total records
$count_sql = "SELECT COUNT(*) as total 
              FROM piutang p
              LEFT JOIN desa d ON p.desa_id = d.id
              LEFT JOIN transaksi t ON p.transaksi_id = t.id
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = ceil($total_records / $records_per_page);

// Query utama untuk mengambil data piutang
$sql = "SELECT p.*, d.nama_desa, d.nama_kepala_desa, d.no_hp_kepala_desa,
               t.nomor_invoice, t.tanggal_transaksi,
               (SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran_piutang WHERE piutang_id = p.id) as total_dibayar
        FROM piutang p
        LEFT JOIN desa d ON p.desa_id = d.id
        LEFT JOIN transaksi t ON p.transaksi_id = t.id
        $where_clause
        ORDER BY p.tanggal_jatuh_tempo ASC, p.created_at DESC
        LIMIT $records_per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$piutang_list = $stmt->fetchAll();

// Hitung sisa piutang untuk setiap record
foreach ($piutang_list as &$piutang) {
    $piutang['sisa_piutang'] = ($piutang['jumlah_piutang'] ?? 0) - ($piutang['total_dibayar'] ?? 0);
}

// Query untuk dropdown desa
$desa_sql = "SELECT id, nama_desa FROM desa ORDER BY nama_desa";
$desa_stmt = $pdo->query($desa_sql);
$desa_list = $desa_stmt->fetchAll();

// Statistik
$stats_sql = "SELECT 
                COUNT(*) as total_piutang,
                COALESCE(SUM(jumlah_piutang), 0) as total_nilai,
                COALESCE(SUM(CASE WHEN status = 'lunas' THEN jumlah_piutang ELSE 0 END), 0) as total_lunas,
                COALESCE(SUM(CASE WHEN status = 'belum_lunas' THEN jumlah_piutang ELSE 0 END), 0) as total_belum_lunas
              FROM piutang";
$stats_stmt = $pdo->query($stats_sql);
$stats = $stats_stmt->fetch();

// Hitung total sisa piutang
$sisa_sql = "SELECT COALESCE(SUM(p.jumlah_piutang - COALESCE(pb.total_dibayar, 0)), 0) as total_sisa
             FROM piutang p
             LEFT JOIN (
                 SELECT piutang_id, SUM(jumlah_bayar) as total_dibayar
                 FROM pembayaran_piutang
                 GROUP BY piutang_id
             ) pb ON p.id = pb.piutang_id";
$sisa_stmt = $pdo->query($sisa_sql);
$total_sisa = $sisa_stmt->fetch()['total_sisa'] ?? 0;
?>

<?php 
include 'layouts/header.php'; 
?>

<!-- Piutang Content -->
<div class="max-w-7xl ml-0 lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Data Piutang</h1>
                    <p class="mt-2 text-gray-600">Kelola dan pantau piutang desa</p>
                </div>
                <div class="flex space-x-3">
                    <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="fas fa-chart-bar mr-2"></i>Dashboard
                    </a>
                    <a href="transaksi.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-list mr-2"></i>Transaksi
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistik Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-file-invoice text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Piutang</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_piutang'] ?? 0 ?></p>
                        <p class="text-sm text-gray-600">Rp <?= number_format($stats['total_nilai'] ?? 0, 0, ',', '.') ?></p>
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
                        <p class="text-sm font-medium text-gray-500">Sudah Lunas</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['piutang_lunas'] ?? 0 ?></p>
                        <p class="text-sm text-gray-600">Rp <?= number_format($stats['total_lunas'] ?? 0, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-circle text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Belum Lunas</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= ($stats['total_piutang'] ?? 0) - ($stats['piutang_lunas'] ?? 0) ?></p>
                        <p class="text-sm text-gray-600">Rp <?= number_format($total_sisa ?? 0, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-percentage text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Tingkat Pelunasan</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?= ($stats['total_nilai'] ?? 0) > 0 ? number_format((($stats['total_lunas'] ?? 0) / ($stats['total_nilai'] ?? 1)) * 100, 1) : 0 ?>%
                        </p>
                        <p class="text-sm text-gray-600">Persentase lunas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Data</h3>
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Pencarian</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Cari desa, kepala desa, atau invoice..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status Piutang</label>
                        <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Status</option>
                            <option value="lunas" <?= $status_filter === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                            <option value="belum_lunas" <?= $status_filter === 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                        </select>
                    </div>

                    <div>
                        <label for="desa" class="block text-sm font-medium text-gray-700 mb-1">Desa</label>
                        <select id="desa" name="desa" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Desa</option>
                            <?php foreach ($desa_list as $desa): ?>
                            <option value="<?= $desa['id'] ?>" <?= $desa_filter == $desa['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($desa['nama_desa']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabel Piutang -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Data Piutang (<?= number_format(count($piutang_list)) ?> piutang)
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Piutang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa Piutang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jatuh Tempo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                     <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($piutang_list)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                <p>Tidak ada data piutang</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($piutang_list as $index => $piutang): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $offset + $index + 1 ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="transaksi-view.php?id=<?= $piutang['transaksi_id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                    <?= htmlspecialchars($piutang['nomor_invoice'] ?? '-') ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div>
                                    <div class="font-medium"><?= htmlspecialchars($piutang['nama_desa'] ?? '-') ?></div>
                                    <div class="text-gray-500"><?= htmlspecialchars($piutang['nama_kepala_desa'] ?? '-') ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                Rp <?= number_format($piutang['jumlah_piutang'] ?? 0, 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <span class="<?= $piutang['sisa_piutang'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    Rp <?= number_format($piutang['sisa_piutang'] ?? 0, 0, ',', '.') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php
                                if (!empty($piutang['tanggal_jatuh_tempo'])) {
                                    $jatuh_tempo = new DateTime($piutang['tanggal_jatuh_tempo']);
                                    $today = new DateTime();
                                    $diff = $today->diff($jatuh_tempo);
                                    $class = 'text-gray-900';
                                    if ($today > $jatuh_tempo) {
                                        $class = 'text-red-600 font-semibold';
                                    } elseif ($diff->days <= 7) {
                                        $class = 'text-yellow-600 font-semibold';
                                    }
                                    echo '<span class="' . $class . '">' . $jatuh_tempo->format('d/m/Y') . '</span>';
                                } else {
                                    echo '<span class="text-gray-400">-</span>';
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $status = $piutang['sisa_piutang'] <= 0 ? 'lunas' : 'belum_lunas';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status === 'lunas' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $status === 'lunas' ? 'Lunas' : 'Belum Lunas' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="piutang-detail.php?id=<?= $piutang['id'] ?>" class="text-blue-600 hover:text-blue-900" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($piutang['sisa_piutang'] > 0): ?>
                                    <a href="pembayaran-add.php?piutang_id=<?= $piutang['id'] ?>" class="text-green-600 hover:text-green-900" title="Bayar Piutang">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($piutang['no_hp_kepala_desa'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $piutang['no_hp_kepala_desa']) ?>" target="_blank" class="text-green-600 hover:text-green-900" title="WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
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
        <div class="bg-white px-6 py-4 flex items-center justify-between border-t border-gray-200">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&desa=<?= urlencode($desa_filter) ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&desa=<?= urlencode($desa_filter) ?>" 
                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Menampilkan <span class="font-medium"><?= $offset + 1 ?></span> sampai 
                        <span class="font-medium"><?= min($offset + $records_per_page, $total_records) ?></span> dari 
                        <span class="font-medium"><?= $total_records ?></span> data
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&desa=<?= urlencode($desa_filter) ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1): ?>
                            <a href="?page=1&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&desa=<?= urlencode($desa_filter) ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                            <?php if ($start > 2): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $page): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">
                                <?= $i ?>
                            </span>
                            <?php else: ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&desa=<?= urlencode($desa_filter) ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <?= $i ?>
                            </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                            <?php endif; ?>
                            <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&desa=<?= urlencode($desa_filter) ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?= $total_pages ?></a>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&desa=<?= urlencode($desa_filter) ?>" 
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

<!-- Simple Footer -->
<footer class="bg-white border-t border-gray-200 mt-8">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">SIMAD</h3>
                <p class="text-sm text-gray-600">Sistem Informasi Manajemen Desa</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-600">&copy; <?= date('Y') ?> SIMAD. All rights reserved.</p>
                <p class="text-xs text-gray-500">Version 1.0.0</p>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript for mobile sidebar toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('toggleSidebarMobile');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    function openSidebar() {
        sidebar.classList.remove('hidden', '-translate-x-full');
        sidebar.classList.add('translate-x-0');
        overlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    
    function closeSidebar() {
        sidebar.classList.remove('translate-x-0');
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        
        // Hide sidebar completely after animation
        setTimeout(() => {
            if (window.innerWidth < 1024) {
                sidebar.classList.add('hidden');
            }
        }, 300);
    }
    
    if (toggleButton && sidebar && overlay) {
        // Toggle button click
        toggleButton.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('-translate-x-full')) {
                openSidebar();
            } else {
                closeSidebar();
            }
        });
        
        // Overlay click
        overlay.addEventListener('click', function() {
            closeSidebar();
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) { // lg breakpoint
                sidebar.classList.remove('hidden', '-translate-x-full');
                sidebar.classList.add('translate-x-0');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            } else {
                if (!sidebar.classList.contains('-translate-x-full')) {
                    closeSidebar();
                }
            }
        });
        
        // Initialize state based on screen size
        if (window.innerWidth < 1024) {
            sidebar.classList.add('hidden', '-translate-x-full');
            sidebar.classList.remove('translate-x-0');
        }
    }
});
</script>

</body>
</html>
