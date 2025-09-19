<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'supervisor', 'programmer'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $db->execute("DELETE FROM website_desa WHERE id = ?", [$id]);
        $success = 'Data website desa berhasil dihapus';
    } catch (Exception $e) {
        $error = 'Gagal menghapus data website desa: ' . $e->getMessage();
    }
}

// Handle search and filter
$search = $_GET['search'] ?? '';
$developer_filter = $_GET['developer'] ?? 'semua';
$sync_filter = $_GET['sync'] ?? 'semua';
$limit = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(d.nama_desa LIKE ? OR wd.website_url LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if ($developer_filter !== 'semua') {
    $where_conditions[] = "wd.developer_type = ?";
    $params[] = $developer_filter;
}

if ($sync_filter !== 'semua') {
    $where_conditions[] = "wd.opendata_sync = ?";
    $params[] = $sync_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM website_desa wd LEFT JOIN desa d ON wd.desa_id = d.id $where_clause";
$total_records = $db->select($count_query, $params)[0]['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// Get website data
$query = "
    SELECT wd.*, d.nama_desa, d.kecamatan, d.kabupaten
    FROM website_desa wd
    LEFT JOIN desa d ON wd.desa_id = d.id
    $where_clause
    ORDER BY d.nama_desa ASC
    LIMIT $limit OFFSET $offset
";

$website_list = $db->select($query, $params);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sistem Manajemen Website Desa - Kelola data website desa">
    <meta name="keywords" content="website, desa, manajemen, sistem">
    <title>Website Desa - Sistem Manajemen Desa</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Sweet Alert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="h-full bg-gray-50">
    <?php 
    $page_title = 'Website Desa';
    include 'layouts/header.php'; 
    ?>
    
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Website Desa</h1>
                <p class="text-sm text-gray-600 mt-1">Kelola data website desa dan informasi terkait</p>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
            
        <!-- Alerts -->
        <?php if (isset($success)): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Nama desa atau URL website" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Developer</label>
                    <select name="developer" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="semua" <?= $developer_filter === 'semua' ? 'selected' : '' ?>>Semua</option>
                        <option value="clasnet" <?= $developer_filter === 'clasnet' ? 'selected' : '' ?>>Clasnet</option>
                        <option value="bukan_clasnet" <?= $developer_filter === 'bukan_clasnet' ? 'selected' : '' ?>>Bukan Clasnet</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sinkron OpenData</label>
                    <select name="sync" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="semua" <?= $sync_filter === 'semua' ? 'selected' : '' ?>>Semua</option>
                        <option value="sinkron" <?= $sync_filter === 'sinkron' ? 'selected' : '' ?>>Sinkron</option>
                        <option value="proses" <?= $sync_filter === 'proses' ? 'selected' : '' ?>>Proses</option>
                        <option value="tidak_sinkron" <?= $sync_filter === 'tidak_sinkron' ? 'selected' : '' ?>>Tidak Sinkron</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md font-medium transition-colors duration-200">
                        <i class="fas fa-search mr-2"></i>
                        Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Tombol Tambah Website -->
        <div class="mb-6">
            <a href="website-desa-add.php" class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                <i class="fas fa-plus mr-2"></i>
                Tambah Website
            </a>
        </div>
        
        <!-- Data Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Daftar Website Desa</h2>
                <p class="text-sm text-gray-600 mt-1">Total: <?= number_format($total_records) ?> website</p>
                
                <!-- Keterangan Warning -->
                <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-yellow-600 mt-0.5 mr-2"></i>
                        <div class="text-sm">
                            <p class="font-medium text-yellow-800 mb-1">Keterangan Status Domain:</p>
                            <div class="space-y-1 text-yellow-700">
                                <div class="flex items-center">
                                    <span class="inline-block w-4 h-4 bg-yellow-100 border border-yellow-300 rounded mr-2"></span>
                                    <span class="text-yellow-800 bg-yellow-100 px-2 py-0.5 rounded text-xs font-medium mr-2">Nama Desa</span>
                                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-1"></i>
                                    <span>Website belum menggunakan domain resmi <strong>.desa.id</strong></span>
                                </div>
                                <div class="flex items-center">
                                    <span class="inline-block w-4 h-4 bg-gray-100 border border-gray-300 rounded mr-2"></span>
                                    <span class="text-gray-800 px-2 py-0.5 rounded text-xs font-medium mr-2">Nama Desa</span>
                                    <span>Website sudah menggunakan domain resmi <strong>.desa.id</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($website_list)): ?>
                <div class="p-8 text-center">
                    <i class="fas fa-globe text-gray-300 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada data website</h3>
                    <p class="text-gray-600 mb-4">Mulai dengan menambahkan website desa pertama</p>
                    <a href="website-desa-add.php" class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>
                        Tambah Website
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 table-fixed">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">Nama Desa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-64">Website</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Database</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Berita</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Developer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Sinkron OpenData</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                         <tbody class="bg-white divide-y divide-gray-200">
                             <?php foreach ($website_list as $index => $website): ?>
                             <tr class="hover:bg-gray-50">
                                 <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 w-16">
                                     <?= $offset + $index + 1 ?>
                                 </td>
                                 <td class="px-6 py-4 whitespace-nowrap w-48">
                                     <?php 
                                     // Cek apakah website menggunakan domain .desa.id
                                     $is_official_domain = strpos($website['website_url'], '.desa.id') !== false;
                                     $name_class = $is_official_domain ? 'text-gray-900' : 'text-yellow-800 bg-yellow-100 px-2 py-1 rounded';
                                     ?>
                                     <div class="text-sm font-medium <?= $name_class ?>">
                                         <?= htmlspecialchars($website['nama_desa'] ?: 'Tidak terdaftar') ?>
                                         <?php if (!$is_official_domain): ?>
                                         <i class="fas fa-exclamation-triangle ml-1 text-yellow-600" title="Website belum menggunakan domain resmi .desa.id"></i>
                                         <?php endif; ?>
                                     </div>
                                     <?php if ($website['kecamatan']): ?>
                                     <div class="text-sm text-gray-500">
                                         <?= htmlspecialchars($website['kecamatan']) ?>, <?= htmlspecialchars($website['kabupaten']) ?>
                                     </div>
                                     <?php endif; ?>
                                 </td>
                                 <td class="px-6 py-4 w-64">
                                     <a href="<?= htmlspecialchars($website['website_url']) ?>" target="_blank" 
                                        class="text-primary-600 hover:text-primary-800 text-sm block truncate" 
                                        title="<?= htmlspecialchars($website['website_url']) ?>">
                                         <?= htmlspecialchars($website['website_url']) ?>
                                         <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                     </a>
                                 </td>
                                 <td class="px-6 py-4 whitespace-nowrap w-24">
                                     <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $website['has_database'] === 'ada' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                         <?= $website['has_database'] === 'ada' ? 'Ada' : 'Tidak Ada' ?>
                                     </span>
                                 </td>
                                 <td class="px-6 py-4 whitespace-nowrap w-24">
                                     <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $website['news_active'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                         <?= $website['news_active'] === 'aktif' ? 'Aktif' : 'Tidak Aktif' ?>
                                     </span>
                                 </td>
                                 <td class="px-6 py-4 whitespace-nowrap w-32">
                                     <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $website['developer_type'] === 'clasnet' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' ?>">
                                         <?= $website['developer_type'] === 'clasnet' ? 'Clasnet' : 'Bukan Clasnet' ?>
                                     </span>
                                 </td>
                                 <td class="px-6 py-4 whitespace-nowrap w-32">
                                     <?php 
                                     $sync_class = '';
                                     $sync_text = '';
                                     switch($website['opendata_sync']) {
                                         case 'sinkron':
                                             $sync_class = 'bg-green-100 text-green-800';
                                             $sync_text = 'Sinkron';
                                             break;
                                         case 'proses':
                                             $sync_class = 'bg-yellow-100 text-yellow-800';
                                             $sync_text = 'Proses';
                                             break;
                                         default:
                                             $sync_class = 'bg-red-100 text-red-800';
                                             $sync_text = 'Tidak Sinkron';
                                     }
                                     ?>
                                     <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $sync_class ?>">
                                         <?= $sync_text ?>
                                     </span>
                                 </td>
                                 <td class="px-6 py-4 whitespace-nowrap text-sm font-medium w-24">
                                     <div class="flex space-x-2">
                                         <a href="website-desa-view.php?id=<?= $website['id'] ?>" 
                                            class="text-blue-600 hover:text-blue-800 transition-colors duration-200" 
                                            title="Lihat Detail">
                                             <i class="fas fa-eye"></i>
                                         </a>
                                         <a href="website-desa-edit.php?id=<?= $website['id'] ?>" 
                                            class="text-yellow-600 hover:text-yellow-800 transition-colors duration-200" 
                                            title="Edit">
                                             <i class="fas fa-edit"></i>
                                         </a>
                                         <button onclick="confirmDelete(<?= $website['id'] ?>, '<?= htmlspecialchars($website['nama_desa'] ?: $website['website_url'], ENT_QUOTES) ?>')" 
                                                 class="text-red-600 hover:text-red-800 transition-colors duration-200" 
                                                 title="Hapus">
                                             <i class="fas fa-trash"></i>
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
                 <div class="px-6 py-4 border-t border-gray-200">
                     <div class="flex items-center justify-between">
                         <div class="text-sm text-gray-700">
                             Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> dari <?= number_format($total_records) ?> data
                         </div>
                         <div class="flex space-x-1">
                             <?php if ($page > 1): ?>
                             <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&developer=<?= urlencode($developer_filter) ?>&sync=<?= urlencode($sync_filter) ?>" 
                                class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                 <i class="fas fa-chevron-left"></i>
                             </a>
                             <?php endif; ?>
                             
                             <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                             <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&developer=<?= urlencode($developer_filter) ?>&sync=<?= urlencode($sync_filter) ?>" 
                                class="px-3 py-2 text-sm <?= $i === $page ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?> border border-gray-300 rounded-md">
                                 <?= $i ?>
                             </a>
                             <?php endfor; ?>
                             
                             <?php if ($page < $total_pages): ?>
                             <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&developer=<?= urlencode($developer_filter) ?>&sync=<?= urlencode($sync_filter) ?>" 
                                class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                 <i class="fas fa-chevron-right"></i>
                             </a>
                             <?php endif; ?>
                         </div>
                     </div>
                 </div>
                 <?php endif; ?>
             <?php endif; ?>
         </div>
     </div>
     
     <script>
         function confirmDelete(id, name) {
             Swal.fire({
                 title: 'Konfirmasi Hapus',
                 text: `Apakah Anda yakin ingin menghapus website "${name}"?`,
                 icon: 'warning',
                 showCancelButton: true,
                 confirmButtonColor: '#dc2626',
                 cancelButtonColor: '#6b7280',
                 confirmButtonText: 'Ya, Hapus',
                 cancelButtonText: 'Batal'
             }).then((result) => {
                 if (result.isConfirmed) {
                     window.location.href = `?action=delete&id=${id}`;
                 }
             });
         }
     </script>
 </body>
 </html>