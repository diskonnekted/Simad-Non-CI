<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Parameter pencarian dan filter
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'nama';
$order = $_GET['order'] ?? 'ASC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query untuk users
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_lengkap LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "status = 'aktif'";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "status = 'tidak_aktif'";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validasi sort column
$sort_mapping = [
    'nama' => 'nama_lengkap',
    'nama_lengkap' => 'nama_lengkap',
    'email' => 'email',
    'username' => 'username',
    'role' => 'role',
    'status' => 'status',
    'created_at' => 'created_at',
    'updated_at' => 'updated_at'
];

if (!array_key_exists($sort, $sort_mapping)) {
    $sort = 'nama_lengkap';
}

$sort_column = $sort_mapping[$sort];
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Query untuk menghitung total
$count_query = "SELECT COUNT(*) as total FROM users {$where_clause}";
$total_result = $db->select($count_query, $params);
$total_records = $total_result[0]['total'];
$total_pages = ceil($total_records / $limit);

// Query untuk mengambil data users
$query = "SELECT * FROM users {$where_clause} ORDER BY {$sort_column} {$order} LIMIT {$limit} OFFSET {$offset}";
$users_list = $db->select($query, $params);

// Statistik users
$stats = $db->select("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN status = 'tidak_aktif' THEN 1 ELSE 0 END) as inactive_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'akunting' THEN 1 ELSE 0 END) as akunting_count,
        SUM(CASE WHEN role = 'supervisor' THEN 1 ELSE 0 END) as supervisor_count,
        SUM(CASE WHEN role = 'teknisi' THEN 1 ELSE 0 END) as teknisi_count,
        SUM(CASE WHEN role = 'programmer' THEN 1 ELSE 0 END) as programmer_count
    FROM users
")[0];

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    
    // Tidak bisa menghapus diri sendiri
    if ($delete_id == $user['id']) {
        $error = 'cannot_delete_self';
    } else {
        try {
            // Cek apakah user sedang digunakan dalam jadwal
            $usage_check = $db->select(
                "SELECT COUNT(*) as count FROM jadwal_personal WHERE user_id = ?",
                [$delete_id]
            );
            
            if ($usage_check[0]['count'] > 0) {
                // Nonaktifkan user jika sedang digunakan
                $db->execute("UPDATE users SET status = 'tidak_aktif' WHERE id = ?", [$delete_id]);
                $success = 'deactivated';
            } else {
                // Hapus user jika tidak sedang digunakan
                $db->execute("DELETE FROM users WHERE id = ?", [$delete_id]);
                $success = 'deleted';
            }
            
            if ($success) {
                header("Location: user.php?success={$success}");
                exit;
            }
        } catch (Exception $e) {
            $error = 'delete_failed';
        }
    }
}

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status_id'])) {
    $toggle_id = intval($_POST['toggle_status_id']);
    $new_status = $_POST['new_status'] ?? '';
    
    if ($toggle_id != $user['id'] && in_array($new_status, ['aktif', 'tidak_aktif'])) {
        try {
            $db->execute("UPDATE users SET status = ? WHERE id = ?", [$new_status, $toggle_id]);
            $success = $new_status === 'aktif' ? 'activated' : 'deactivated';
            header("Location: user.php?success={$success}");
            exit;
        } catch (Exception $e) {
            $error = 'status_update_failed';
        }
    }
}

// Helper functions
function getRoleBadge($role) {
    $badges = [
        'admin' => 'danger',
        'akunting' => 'success',
        'supervisor' => 'warning',
        'teknisi' => 'info',
        'programmer' => 'secondary'
    ];
    return $badges[$role] ?? 'secondary';
}

function getRoleText($role) {
    $roles = [
        'admin' => 'Administrator',
        'akunting' => 'Akunting',
        'supervisor' => 'Supervisor',
        'teknisi' => 'Teknisi',
        'programmer' => 'Programmer'
    ];
    return $roles[$role] ?? ucfirst($role);
}

function getStatusBadge($status) {
    return $status === 'aktif' ? 'success' : 'secondary';
}

function getStatusText($status) {
    return $status === 'aktif' ? 'Aktif' : 'Tidak Aktif';
}

function formatTanggal($date) {
    return $date ? date('d/m/Y H:i', strtotime($date)) : 'Belum pernah';
}

$page_title = 'Manajemen User';
require_once 'layouts/header.php';
?>



<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header Section -->
    <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center mb-8 space-y-4 lg:space-y-0">
        <div class="flex-1">
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Manajemen User</h1>
            <p class="mt-1 text-sm text-gray-600">Kelola pengguna sistem dan hak akses</p>
        </div>
        <div class="flex-shrink-0">
            <a href="user-add.php" class="w-full lg:w-auto inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-plus mr-2"></i>Tambah User
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-800">
                    <?php
                    $error_messages = [
                        'access_denied' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.',
                        'delete_failed' => 'Gagal menghapus user. Silakan coba lagi.',
                        'cannot_delete_self' => 'Anda tidak dapat menghapus akun Anda sendiri.',
                        'status_update_failed' => 'Gagal mengubah status user. Silakan coba lagi.'
                    ];
                    echo $error_messages[$error] ?? 'Terjadi kesalahan.';
                    ?>
                </p>
            </div>
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button type="button" class="inline-flex rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-red-50 focus:ring-red-600" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()">
                        <span class="sr-only">Dismiss</span>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-800">
                    <?php
                    $success_messages = [
                        'added' => 'User berhasil ditambahkan.',
                        'updated' => 'User berhasil diperbarui.',
                        'deleted' => 'User berhasil dihapus.',
                        'deactivated' => 'User berhasil dinonaktifkan.',
                        'activated' => 'User berhasil diaktifkan.'
                    ];
                    echo $success_messages[$success] ?? 'Operasi berhasil.';
                    ?>
                </p>
            </div>
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button type="button" class="inline-flex rounded-md p-1.5 text-green-500 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-green-50 focus:ring-green-600" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()">
                        <span class="sr-only">Dismiss</span>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-lg"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_users']); ?></p>
                    <p class="text-sm text-gray-600">Total User</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-check text-green-600 text-lg"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['active_users']); ?></p>
                    <p class="text-sm text-gray-600">User Aktif</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-times text-red-600 text-lg"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['inactive_users']); ?></p>
                    <p class="text-sm text-gray-600">User Tidak Aktif</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Role Distribution -->
    <div class="mb-8">
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Distribusi Role</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 lg:gap-6">
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="mb-2">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800"><?php echo number_format($stats['admin_count']); ?></span>
                        </div>
                        <div class="text-gray-500 text-sm">Administrator</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="mb-2">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800"><?php echo number_format($stats['akunting_count']); ?></span>
                        </div>
                        <div class="text-gray-500 text-sm">Akunting</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="mb-2">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800"><?php echo number_format($stats['supervisor_count']); ?></span>
                        </div>
                        <div class="text-gray-500 text-sm">Supervisor</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="mb-2">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-cyan-100 text-cyan-800"><?php echo number_format($stats['teknisi_count']); ?></span>
                        </div>
                        <div class="text-gray-500 text-sm">Teknisi</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="mb-2">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800"><?php echo number_format($stats['programmer_count']); ?></span>
                        </div>
                        <div class="text-gray-500 text-sm">Programmer</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Search -->
    <div class="bg-white shadow rounded-lg mb-8">
        <div class="px-4 py-4 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Filter & Pencarian</h3>
        </div>
        <div class="p-4 sm:p-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                        <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nama, email, atau username...">
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="role" name="role">
                            <option value="">Semua Role</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            <option value="akunting" <?php echo $role_filter === 'akunting' ? 'selected' : ''; ?>>Akunting</option>
                            <option value="supervisor" <?php echo $role_filter === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                            <option value="teknisi" <?php echo $role_filter === 'teknisi' ? 'selected' : ''; ?>>Teknisi</option>
                            <option value="programmer" <?php echo $role_filter === 'programmer' ? 'selected' : ''; ?>>Programmer</option>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="status" name="status">
                            <option value="">Semua Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Urutkan</label>
                        <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="sort" name="sort">
                            <option value="nama" <?php echo $sort === 'nama' ? 'selected' : ''; ?>>Nama</option>
                            <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Email</option>
                            <option value="role" <?php echo $sort === 'role' ? 'selected' : ''; ?>>Role</option>
                            <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Status</option>
                            <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Tanggal Dibuat</option>
                            <option value="last_login" <?php echo $sort === 'last_login' ? 'selected' : ''; ?>>Login Terakhir</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                    <a href="user.php" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Reset
                    </a>
                    <button type="submit" class="inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-search mr-2"></i>Cari
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white shadow rounded-lg mb-8">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">Daftar User</h3>
            <span class="text-gray-500 text-sm">Total: <?php echo number_format($total_records); ?> user</span>
        </div>
        <div class="overflow-hidden">
            <?php if (empty($users_list)): ?>
            <div class="text-center py-12">
                <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 mb-4">Tidak ada data user yang ditemukan.</p>
                <a href="user-add.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i>Tambah User Pertama
                </a>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Login Terakhir</th>
                            <th class="px-4 lg:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users_list as $usr): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 lg:px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full overflow-hidden bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center">
                                            <?php if ($usr['foto_profil']): ?>
                                                <img src="uploads/users/<?php echo htmlspecialchars($usr['foto_profil']); ?>" alt="Foto profil" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <img src="img/clasnet.png" alt="Logo Clasnet" class="w-6 h-6 object-contain">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ml-4 min-w-0 flex-1">
                                        <div class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($usr['nama_lengkap']); ?></div>
                                        <div class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($usr['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                <?php
                                $role_colors = [
                                    'admin' => 'bg-red-100 text-red-800',
                                    'akunting' => 'bg-green-100 text-green-800',
                                    'supervisor' => 'bg-yellow-100 text-yellow-800',
                                    'teknisi' => 'bg-cyan-100 text-cyan-800',
                                    'programmer' => 'bg-gray-100 text-gray-800'
                                ];
                                $role_color = $role_colors[$usr['role']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $role_color; ?>">
                                    <?php echo getRoleText($usr['role']); ?>
                                </span>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $usr['status'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo getStatusText($usr['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden sm:table-cell">
                                <?php echo formatTanggal($usr['last_login']); ?>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                                <div class="flex justify-center gap-1">
                                    <a href="user-view.php?id=<?php echo $usr['id']; ?>" 
                                       class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-white bg-cyan-600 hover:bg-cyan-700 focus:outline-none focus:ring-1 focus:ring-cyan-500" 
                                       title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="user-edit.php?id=<?php echo $usr['id']; ?>" 
                                       class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-1 focus:ring-yellow-500" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($usr['id'] != $user['id']): ?>
                                    <button type="button" 
                                            class="status-btn inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-white <?php echo $usr['status'] === 'aktif' ? 'bg-gray-600 hover:bg-gray-700 focus:ring-gray-500' : 'bg-green-600 hover:bg-green-700 focus:ring-green-500'; ?> focus:outline-none focus:ring-1" 
                                            data-user-id="<?php echo $usr['id']; ?>" 
                                            data-new-status="<?php echo $usr['status'] === 'aktif' ? 'tidak_aktif' : 'aktif'; ?>" 
                                            data-user-name="<?php echo htmlspecialchars($usr['nama_lengkap'], ENT_QUOTES); ?>" 
                                            title="<?php echo $usr['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                        <i class="fas fa-<?php echo $usr['status'] === 'aktif' ? 'user-times' : 'user-check'; ?>"></i>
                                    </button>
                                    <button type="button" 
                                            class="delete-btn inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-1 focus:ring-red-500" 
                                            data-user-id="<?php echo $usr['id']; ?>" 
                                            data-user-name="<?php echo htmlspecialchars($usr['nama_lengkap'], ENT_QUOTES); ?>" 
                                            title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex flex-col sm:flex-row items-center justify-between border-t border-gray-200 sm:px-6 space-y-3 sm:space-y-0">
                <div class="flex justify-between w-full sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <?php if ($i == $page): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600">
                                    <?php echo $i; ?>
                                </span>
                                <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <?php echo $i; ?>
                                </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">Apakah Anda yakin ingin menghapus user <strong id="deleteItemName" class="text-gray-900"></strong>?</p>
                <p class="text-sm text-gray-400 mt-2">Jika user sedang digunakan dalam jadwal, user akan dinonaktifkan. Jika tidak, user akan dihapus permanen.</p>
            </div>
            <div class="flex items-center justify-end px-4 py-3 space-x-2">
                <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300" onclick="closeDeleteModal()">
                    Batal
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="delete_id" id="deleteItemId">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Status Toggle Modal -->
<div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Konfirmasi Perubahan Status</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeStatusModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mt-2 px-7 py-3">
                <p id="statusMessage" class="text-sm text-gray-500"></p>
            </div>
            <div class="flex items-center justify-end px-4 py-3 space-x-2">
                <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300" onclick="closeStatusModal()">
                    Batal
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="toggle_status_id" id="statusUserId">
                    <input type="hidden" name="new_status" id="newStatus">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500" id="statusConfirmBtn">
                        Konfirmasi
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>



<script>
// Debug: Check if functions are being overwritten
console.log('Loading user.php scripts...');

function confirmDelete(id, name) {
    console.log('confirmDelete function called with:', {id: id, name: name, nameType: typeof name});
    
    try {
        // Validate parameters
        if (!id || !name) {
            console.error('Invalid parameters for confirmDelete');
            alert('Error: Invalid parameters');
            return;
        }
        
        const deleteModal = document.getElementById('deleteModal');
        const deleteItemId = document.getElementById('deleteItemId');
        const deleteItemName = document.getElementById('deleteItemName');
        
        console.log('Modal elements found:', {
            deleteModal: !!deleteModal,
            deleteItemId: !!deleteItemId,
            deleteItemName: !!deleteItemName
        });
        
        if (!deleteModal || !deleteItemId || !deleteItemName) {
            console.error('Required modal elements not found');
            alert('Error: Modal elements not found');
            return;
        }
        
        deleteItemId.value = id;
        deleteItemName.textContent = name;
        deleteModal.classList.remove('hidden');
        
        console.log('Modal should now be visible');
        
    } catch (error) {
        console.error('Error in confirmDelete:', error);
        alert('Terjadi kesalahan: ' + error.message);
    }
}

function closeDeleteModal() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.classList.add('hidden');
    }
}
    
function toggleStatus(id, newStatus, name) {
    try {
        if (!id || !newStatus || !name) {
            console.error('Invalid parameters for toggleStatus');
            return;
        }
        
        const statusModal = document.getElementById('statusModal');
        const statusUserId = document.getElementById('statusUserId');
        const newStatusInput = document.getElementById('newStatus');
        const statusMessage = document.getElementById('statusMessage');
        const confirmBtn = document.getElementById('statusConfirmBtn');
        
        if (!statusModal || !statusUserId || !newStatusInput || !statusMessage || !confirmBtn) {
            console.error('Required status modal elements not found');
            return;
        }
        
        statusUserId.value = id;
        newStatusInput.value = newStatus;
        
        const action = newStatus === 'aktif' ? 'mengaktifkan' : 'menonaktifkan';
        const statusText = newStatus === 'aktif' ? 'aktif' : 'tidak aktif';
        
        statusMessage.innerHTML = 
            `Apakah Anda yakin ingin ${action} user <strong>${name}</strong>?<br><small class="text-gray-400">Status akan berubah menjadi ${statusText}.</small>`;
        
        confirmBtn.textContent = newStatus === 'aktif' ? 'Aktifkan' : 'Nonaktifkan';
        
        if (newStatus === 'aktif') {
            confirmBtn.className = 'px-4 py-2 bg-green-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500';
        } else {
            confirmBtn.className = 'px-4 py-2 bg-gray-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500';
        }
        
        statusModal.classList.remove('hidden');
        
    } catch (error) {
        console.error('Error in toggleStatus:', error);
        alert('Terjadi kesalahan: ' + error.message);
    }
}

function closeStatusModal() {
    const statusModal = document.getElementById('statusModal');
    if (statusModal) {
        statusModal.classList.add('hidden');
    }
}

// Setup event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Setting up event listeners...');
    
    // Setup delete button event listeners
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            console.log('Delete button clicked for user:', userId, userName);
            confirmDelete(userId, userName);
        });
    });
    
    // Setup status button event listeners
    const statusButtons = document.querySelectorAll('.status-btn');
    statusButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const newStatus = this.getAttribute('data-new-status');
            const userName = this.getAttribute('data-user-name');
            console.log('Status button clicked for user:', userId, newStatus, userName);
            toggleStatus(userId, newStatus, userName);
        });
    });
    
    // Close modals when clicking outside
    const deleteModal = document.getElementById('deleteModal');
    const statusModal = document.getElementById('statusModal');
    
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    }
    
    if (statusModal) {
        statusModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });
    }
    
    console.log('Event listeners setup complete. Delete buttons found:', deleteButtons.length, 'Status buttons found:', statusButtons.length);
});
</script>

<!-- Additional script to ensure our functions are available BEFORE footer -->
<script>
// Ensure our functions are globally available and test them
window.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking functions:');
    console.log('confirmDelete available:', typeof confirmDelete);
    console.log('closeDeleteModal available:', typeof closeDeleteModal);
    console.log('toggleStatus available:', typeof toggleStatus);
    console.log('closeStatusModal available:', typeof closeStatusModal);
    
    // Test if we can find modal elements
    const deleteModal = document.getElementById('deleteModal');
    const deleteItemId = document.getElementById('deleteItemId');
    const deleteItemName = document.getElementById('deleteItemName');
    
    console.log('Modal elements found:', {
        deleteModal: !!deleteModal,
        deleteItemId: !!deleteItemId,
        deleteItemName: !!deleteItemName
    });
    

});

// Override any potential conflicts
window.confirmDelete = confirmDelete;
window.closeDeleteModal = closeDeleteModal;
window.toggleStatus = toggleStatus;
window.closeStatusModal = closeStatusModal;
</script>

<!-- Load footer after our scripts -->
<?php require_once 'layouts/footer.php'; ?>