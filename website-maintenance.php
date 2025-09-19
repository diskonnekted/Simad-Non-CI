<?php
// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once 'config/auth.php';
require_once 'config/database.php';

// Check if user is logged in
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check role access
if (!AuthStatic::hasRole(['admin', 'supervisor', 'programmer'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Set page title
$page_title = 'Website Maintenance';

// Initialize database
$db = new Database();
$pdo = $db->getConnection();

// Handle delete action
if (isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM website_maintenance WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        $success_message = "Data maintenance berhasil dihapus!";
    } catch (PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get maintenance data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];

// Get current user
$current_user = AuthStatic::getCurrentUser();

// Filter by programmer_id if user is a programmer
if (AuthStatic::hasRole(['programmer'])) {
    $where_clause = "WHERE wm.programmer_id = ?";
    $params = [$current_user['id']];
    
    if (!empty($search)) {
        $where_clause .= " AND (d.nama_desa LIKE ? OR wm.website_url LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
} else {
    if (!empty($search)) {
        $where_clause = "WHERE d.nama_desa LIKE ? OR wm.website_url LIKE ? OR u2.nama_lengkap LIKE ?";
        $search_param = "%{$search}%";
        $params = [$search_param, $search_param, $search_param];
    }
}

// Get total count
$count_query = "SELECT COUNT(*) FROM website_maintenance wm LEFT JOIN desa d ON wm.desa_id = d.id LEFT JOIN users u2 ON wm.programmer_id = u2.id {$where_clause}";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get maintenance data with checklist completion percentage
$query = "SELECT wm.*, d.nama_desa as desa_name,
                 u2.nama_lengkap as programmer_nama,
                 (
                    SELECT COUNT(*) 
                    FROM maintenance_checklist mc 
                    WHERE mc.maintenance_id = wm.id 
                    AND (mc.install_website = 1 AND mc.setup_info_desa = 1 AND mc.import_database = 1 
                         AND mc.menu_standar = 1 AND mc.foto_gambar = 1 AND mc.berita_dummy = 1 
                         AND mc.no_404_page = 1 AND mc.no_505_page = 1 AND mc.sinkron_opendata = 1 
                         AND mc.domain_resmi_kominfo = 1 AND mc.submitted_for_verification = 1 
                         AND mc.verified_by_admin = 1)
                 ) as completed_items,
                 (
                    SELECT COUNT(*) * 12 
                    FROM maintenance_checklist mc 
                    WHERE mc.maintenance_id = wm.id
                 ) as total_items
          FROM website_maintenance wm 
          LEFT JOIN desa d ON wm.desa_id = d.id 
          LEFT JOIN users u2 ON wm.programmer_id = u2.id
          {$where_clause}
          ORDER BY wm.created_at DESC 
          LIMIT {$limit} OFFSET {$offset}";


$stmt = $pdo->prepare($query);
$stmt->execute($params);
$maintenance_data = $stmt->fetchAll();

// Calculate completion percentage for each maintenance
foreach ($maintenance_data as &$row) {
    // Get actual checklist data
    $checklist_query = "SELECT * FROM maintenance_checklist WHERE maintenance_id = ?";
    $checklist_stmt = $pdo->prepare($checklist_query);
    $checklist_stmt->execute([$row['id']]);
    $checklist = $checklist_stmt->fetch();
    
    if ($checklist) {
        $completed = 0;
        $total = 12;
        
        $fields = ['install_website', 'setup_info_desa', 'import_database', 'menu_standar', 
                  'foto_gambar', 'berita_dummy', 'no_404_page', 'no_505_page', 
                  'sinkron_opendata', 'domain_resmi_kominfo', 'submitted_for_verification', 'verified_by_admin'];
        
        foreach ($fields as $field) {
            if ($checklist[$field] == 1) {
                $completed++;
            }
        }
        
        $row['completion_percentage'] = $total > 0 ? round(($completed / $total) * 100) : 0;
        
        // Auto-update status based on completion percentage
        if ($row['completion_percentage'] == 100 && $row['status'] != 'completed') {
            $update_status_query = "UPDATE website_maintenance SET status = 'completed' WHERE id = ?";
            $update_status_stmt = $pdo->prepare($update_status_query);
            $update_status_stmt->execute([$row['id']]);
            $row['status'] = 'completed';
        } elseif ($row['completion_percentage'] > 0 && $row['completion_percentage'] < 100 && $row['status'] == 'completed') {
            $update_status_query = "UPDATE website_maintenance SET status = 'maintenance' WHERE id = ?";
            $update_status_stmt = $pdo->prepare($update_status_query);
            $update_status_stmt->execute([$row['id']]);
            $row['status'] = 'maintenance';
        }
    } else {
        $row['completion_percentage'] = 0;
    }
}
unset($row);

// Include header
require_once 'layouts/header.php';
?>

<main class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Website Maintenance</h1>
                <p class="mt-2 text-gray-600">Kelola website desa yang sedang dalam proses maintenance</p>
            </div>
            <a href="website-maintenance-add.php" 
               class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md font-medium transition-colors duration-200">
                <i class="fas fa-plus mr-2"></i>
                Tambah Maintenance
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success_message)): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex gap-4 items-end">
            <div class="flex-1">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                <input type="text" 
                       id="search" 
                       name="search" 
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Cari berdasarkan nama desa, website, penanggung jawab, atau programmer..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            </div>
            <button type="submit" 
                    class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md font-medium transition-colors duration-200">
                <i class="fas fa-search mr-2"></i>
                Cari
            </button>
            <?php if (!empty($search)): ?>
                <a href="website-maintenance.php" 
                   class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <?php
        // Get statistics
        $stats_query = "SELECT 
                          COUNT(*) as total,
                          SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                          SUM(CASE WHEN status = 'pending_verification' THEN 1 ELSE 0 END) as pending,
                          SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                        FROM website_maintenance";
        $stats = $pdo->query($stats_query)->fetch();
        ?>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-globe text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Website</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-tools text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Maintenance</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['maintenance']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Pending Verifikasi</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['pending']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Selesai</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['completed']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Daftar Website Maintenance</h3>
            <p class="text-sm text-gray-600 mt-1">Total: <?php echo number_format($total_records); ?> website</p>
        </div>
        
        <?php if (empty($maintenance_data)): ?>
            <div class="text-center py-12">
                <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada data maintenance</h3>
                <p class="text-gray-600 mb-4">Belum ada website yang sedang dalam proses maintenance.</p>
                <a href="website-maintenance-add.php" 
                   class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-plus mr-2"></i>
                    Tambah Maintenance Pertama
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200" style="table-layout: fixed;">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 12%;">Nama Desa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 20%;">Website</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 10%;">Programmer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 15%;">Jenis Penugasan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 10%;">Deadline</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 12%;">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 11%;">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 120px; min-width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($maintenance_data as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($row['desa_name'] ?? $row['nama_desa']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <a href="<?php echo htmlspecialchars($row['website_url']); ?>" 
                                           target="_blank" 
                                           class="text-primary-600 hover:text-primary-800 hover:underline">
                                            <?php echo htmlspecialchars($row['website_url']); ?>
                                            <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['programmer_nama'] ?? 'Tidak ada'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php 
                                        $assignment_types = [
                                            'instalasi_sid' => 'Instalasi SID',
                                            'perbaikan_error_404_505' => 'Perbaikan Error 404/505',
                                            'update_versi_aplikasi' => 'Update Versi Aplikasi',
                                            'perbaikan_ssl' => 'Perbaikan SSL',
                                            'pemindahan_hosting_server' => 'Pemindahan Hosting Server',
                                            'maintenance_lainnya' => 'Maintenance Lainnya'
                                        ];
                                        echo htmlspecialchars($assignment_types[$row['assignment_type']] ?? 'Tidak diketahui');
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php 
                                            $deadline = new DateTime($row['deadline']);
                                            echo $deadline->format('d/m/Y');
                                            ?>
                                        </div>
                                        <?php 
                                        $now = new DateTime();
                                        $diff = $now->diff($deadline);
                                        $is_overdue = $now > $deadline;
                                        
                                        if ($is_overdue && $row['status'] !== 'completed') {
                                            echo '<div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">';
                                            echo '<i class="fas fa-exclamation-triangle mr-1"></i>Terlambat';
                                            echo '</div>';
                                        } elseif ($diff->days <= 3 && $row['status'] !== 'completed') {
                                            echo '<div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">';
                                            echo '<i class="fas fa-clock mr-1"></i>' . $diff->days . ' hari lagi';
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo $row['completion_percentage']; ?>%
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_classes = [
                                        'maintenance' => 'bg-yellow-100 text-yellow-800',
                                        'pending_verification' => 'bg-orange-100 text-orange-800',
                                        'completed' => 'bg-green-100 text-green-800'
                                    ];
                                    
                                    $status_labels = [
                                        'maintenance' => 'Maintenance',
                                        'pending_verification' => 'Pending Verifikasi',
                                        'completed' => 'Selesai'
                                    ];
                                    
                                    $status_icons = [
                                        'maintenance' => 'fas fa-tools',
                                        'pending_verification' => 'fas fa-clock',
                                        'completed' => 'fas fa-check-circle'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_classes[$row['status']]; ?>">
                                        <i class="<?php echo $status_icons[$row['status']]; ?> mr-1"></i>
                                        <?php echo $status_labels[$row['status']]; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium" style="width: 120px; min-width: 120px;">
                                    <div class="flex items-center justify-center space-x-1">
                                        <a href="website-maintenance-detail.php?id=<?php echo $row['id']; ?>" 
                                           class="inline-flex items-center justify-center w-8 h-8 text-primary-600 hover:text-primary-900 hover:bg-primary-50 rounded-full transition-colors" 
                                           title="Detail">
                                            <i class="fas fa-eye text-sm"></i>
                                        </a>
                                        <a href="website-maintenance-edit.php?id=<?php echo $row['id']; ?>" 
                                           class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-full transition-colors" 
                                           title="Edit">
                                            <i class="fas fa-edit text-sm"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['desa_name'] ?? $row['nama_desa'], ENT_QUOTES); ?>')" 
                                                class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-900 hover:bg-red-50 rounded-full transition-colors" 
                                                title="Hapus">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Sebelumnya
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Selanjutnya
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Menampilkan <span class="font-medium"><?php echo $offset + 1; ?></span> sampai 
                                    <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span> dari 
                                    <span class="font-medium"><?php echo number_format($total_records); ?></span> hasil
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <div class="flex items-center mb-4">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
        </div>
        <div class="text-center">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Konfirmasi Hapus</h3>
            <p class="text-sm text-gray-500 mb-4">Apakah Anda yakin ingin menghapus data maintenance untuk <span id="deleteItemName" class="font-medium"></span>? Tindakan ini tidak dapat dibatalkan.</p>
        </div>
        <div class="flex justify-center space-x-4">
            <button onclick="closeDeleteModal()" 
                    class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md font-medium transition-colors duration-200">
                Batal
            </button>
            <form id="deleteForm" method="POST" class="inline">
                <input type="hidden" name="delete_id" id="deleteId">
                <button type="submit" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-trash mr-2"></i>
                    Hapus
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteItemName').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>