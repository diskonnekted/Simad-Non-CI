<?php
require_once 'config/database.php';

// Tidak perlu autentikasi untuk halaman public

// Handle search dan filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$opendata_filter = isset($_GET['opendata']) ? $_GET['opendata'] : '';
$kecamatan_filter = isset($_GET['kecamatan']) ? $_GET['kecamatan'] : '';

// Build WHERE conditions
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(d.nama_desa LIKE ? OR wd.website_url LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}



if (!empty($opendata_filter)) {
    $where_conditions[] = "wd.opendata_sync = ?";
    $params[] = $opendata_filter;
}

if (!empty($kecamatan_filter)) {
    $where_conditions[] = "d.kecamatan = ?";
    $params[] = $kecamatan_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
try {
    $pdo = getDBConnection();
    $count_sql = "SELECT COUNT(*) FROM website_desa wd 
                  LEFT JOIN desa d ON wd.desa_id = d.id 
                  $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_records = 0;
}

// Pagination
$records_per_page = 20;
$total_pages = ceil($total_records / $records_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get data
try {
    $sql = "SELECT wd.*, d.nama_desa, d.kecamatan, d.kabupaten
            FROM website_desa wd
            LEFT JOIN desa d ON wd.desa_id = d.id
            $where_clause
            ORDER BY d.nama_desa ASC
            LIMIT $records_per_page OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $website_desa = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $website_desa = [];
    $error_message = "Error: " . $e->getMessage();
}

// Get unique kecamatan for filter
try {
    $kecamatan_stmt = $pdo->prepare("SELECT DISTINCT kecamatan FROM desa WHERE kecamatan IS NOT NULL AND kecamatan != '' ORDER BY kecamatan");
    $kecamatan_stmt->execute();
    $kecamatan_list = $kecamatan_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $kecamatan_list = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Website Desa - SIMAD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .status-badge {
            transition: all 0.3s ease;
        }
        .table-hover:hover {
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="bg-white rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-globe text-2xl text-blue-600"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Tracking Website Desa</h1>
                        <p class="text-blue-100 text-sm">Informasi Website Desa & Sinkronisasi OpenData</p>
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <a href="peta-desa.php" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200">
                        <i class="fas fa-globe-americas mr-1"></i>Peta Desa
                    </a>
                    <a href="index.php" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200">
                        <i class="fas fa-home mr-1"></i>Beranda
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Info Banner -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <div class="flex items-start space-x-3">
                <i class="fas fa-info-circle text-blue-600 text-xl mt-1"></i>
                <div>
                    <h3 class="text-lg font-semibold text-blue-800 mb-2">Informasi Tracking Website Desa</h3>
                    <p class="text-blue-700 mb-2">
                        Halaman ini menampilkan informasi website desa yang terdaftar dalam sistem SIMAD, 
                        termasuk status sinkronisasi dengan OpenData Kabupaten.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="flex items-center space-x-2">
                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <i class="fas fa-check mr-1"></i>Aktif
                            </span>
                            <span class="text-sm text-blue-700">Website aktif dan dapat diakses</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <i class="fas fa-sync mr-1"></i>Sinkron
                            </span>
                            <span class="text-sm text-blue-700">Tersinkron dengan OpenData</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter dan Search -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search mr-1"></i>Cari Desa/Website
                        </label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="Nama desa atau URL website"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="kecamatan" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1"></i>Kecamatan
                        </label>
                        <select id="kecamatan" 
                                name="kecamatan" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Semua Kecamatan</option>
                            <?php foreach ($kecamatan_list as $kecamatan): ?>
                                <option value="<?= htmlspecialchars($kecamatan) ?>" <?= $kecamatan_filter === $kecamatan ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kecamatan) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    

                    
                    <div>
                        <label for="opendata" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-database mr-1"></i>OpenData
                        </label>
                        <select id="opendata" 
                                name="opendata" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Semua Status</option>
                            <option value="1" <?= $opendata_filter === '1' ? 'selected' : '' ?>>Tersinkron</option>
                            <option value="0" <?= $opendata_filter === '0' ? 'selected' : '' ?>>Belum Sinkron</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                    </div>
                </div>
                
                <?php if (!empty($search) || !empty($developer_filter) || !empty($opendata_filter) || !empty($kecamatan_filter)): ?>
                    <div class="flex items-center justify-between pt-4 border-t">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-filter mr-1"></i>
                            Filter aktif: 
                            <?php if (!empty($search)): ?>
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs mr-1">Pencarian: <?= htmlspecialchars($search) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($kecamatan_filter)): ?>
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs mr-1">Kecamatan: <?= htmlspecialchars($kecamatan_filter) ?></span>
                            <?php endif; ?>

                            <?php if (!empty($opendata_filter)): ?>
                                <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs mr-1">OpenData: <?= $opendata_filter === '1' ? 'Tersinkron' : 'Belum Sinkron' ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="tracking-desa.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-times mr-1"></i>Reset Filter
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Statistik -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <?php
            // Hitung statistik
            try {
                $stats_pdo = getDBConnection();
                
                // Total website
                $total_stmt = $stats_pdo->prepare("SELECT COUNT(*) FROM website_desa");
                $total_stmt->execute();
                $total_websites = $total_stmt->fetchColumn();
                
                // Website dengan database
                $db_stmt = $stats_pdo->prepare("SELECT COUNT(*) FROM website_desa WHERE has_database = 1");
                $db_stmt->execute();
                $websites_with_db = $db_stmt->fetchColumn();
                
                // Website dengan berita aktif
                $news_stmt = $stats_pdo->prepare("SELECT COUNT(*) FROM website_desa WHERE news_active = 1");
                $news_stmt->execute();
                $websites_with_news = $news_stmt->fetchColumn();
                
                // Website tersinkron OpenData
                $sync_stmt = $stats_pdo->prepare("SELECT COUNT(*) FROM website_desa WHERE opendata_sync = 1");
                $sync_stmt->execute();
                $synced_websites = $sync_stmt->fetchColumn();
            } catch (Exception $e) {
                $total_websites = $websites_with_db = $websites_with_news = $synced_websites = 0;
            }
            ?>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 rounded-full p-3 mr-4">
                        <i class="fas fa-globe text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Website</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($total_websites) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 rounded-full p-3 mr-4">
                        <i class="fas fa-database text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Dengan Database</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($websites_with_db) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-purple-100 rounded-full p-3 mr-4">
                        <i class="fas fa-newspaper text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Berita Aktif</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($websites_with_news) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-orange-100 rounded-full p-3 mr-4">
                        <i class="fas fa-sync text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Sinkron OpenData</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($synced_websites) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Data -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-table mr-2"></i>
                        Data Website Desa
                    </h3>
                    <div class="text-sm text-gray-600">
                        Menampilkan <?= count($website_desa) ?> dari <?= number_format($total_records) ?> data
                    </div>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="p-6">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                            <span class="text-red-800"><?= htmlspecialchars($error_message) ?></span>
                        </div>
                    </div>
                </div>
            <?php elseif (empty($website_desa)): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada data ditemukan</h3>
                    <p class="text-gray-600 mb-4">Coba ubah filter pencarian atau hapus filter yang aktif.</p>
                    <?php if (!empty($search) || !empty($developer_filter) || !empty($opendata_filter) || !empty($kecamatan_filter)): ?>
                        <a href="tracking-desa.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-refresh mr-2"></i>Reset Filter
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Desa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Website</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Database</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Berita</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sinkron OpenData</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sinkron JDIH</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($website_desa as $index => $data): ?>
                                <tr class="table-hover">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= $offset + $index + 1 ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars(ucwords(strtolower($data['nama_desa'] ?? ''))) ?>
                                            <?php if (strpos(strtolower($data['website_url'] ?? ''), '.desa.id') !== false): ?>
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-certificate mr-1"></i>Domain Resmi
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars(ucwords(strtolower($data['kecamatan'] ?? ''))) ?>, <?= htmlspecialchars(ucwords(strtolower($data['kabupaten'] ?? ''))) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($data['website_url']): ?>
                                            <a href="<?= htmlspecialchars($data['website_url']) ?>" 
                                               target="_blank" 
                                               class="text-blue-600 hover:text-blue-800 text-sm">
                                                <i class="fas fa-external-link-alt mr-1"></i>
                                                <?= htmlspecialchars($data['website_url']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($data['has_database']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i>Ada
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <i class="fas fa-times mr-1"></i>Tidak Ada
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($data['news_active']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-check mr-1"></i>Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <i class="fas fa-times mr-1"></i>Tidak Aktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($data['opendata_sync']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-sync mr-1"></i>Tersinkron
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times mr-1"></i>Belum Sinkron
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($data['opendata_sync']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-gavel mr-1"></i>Tersinkron
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <i class="fas fa-times mr-1"></i>Belum Sinkron
                                            </span>
                                        <?php endif; ?>
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
                                <?php if ($current_page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Sebelumnya
                                    </a>
                                <?php endif; ?>
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" 
                                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Selanjutnya
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Menampilkan
                                        <span class="font-medium"><?= $offset + 1 ?></span>
                                        sampai
                                        <span class="font-medium"><?= min($offset + $records_per_page, $total_records) ?></span>
                                        dari
                                        <span class="font-medium"><?= number_format($total_records) ?></span>
                                        hasil
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($current_page > 1): ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" 
                                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <?php if ($i == $current_page): ?>
                                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600">
                                                    <?= $i ?>
                                                </span>
                                            <?php else: ?>
                                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                    <?= $i ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($current_page < $total_pages): ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" 
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
        
        <!-- Keterangan -->
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <i class="fas fa-info-circle text-yellow-600 text-lg mt-0.5"></i>
                <div>
                    <h4 class="text-sm font-medium text-yellow-800 mb-2">Keterangan Status:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-yellow-700">
                        <div><strong>Domain Resmi:</strong> Website menggunakan domain .desa.id yang resmi</div>
                        <div><strong>Database:</strong> Website memiliki sistem database terintegrasi</div>
                        <div><strong>Berita Aktif:</strong> Fitur berita/artikel pada website aktif</div>
                        <div><strong>Sinkron OpenData:</strong> Data website tersinkronisasi dengan OpenData Kabupaten</div>
                        <div><strong>Sinkron JDIH:</strong> Sinkronisasi dengan sistem JDIH (Jaringan Dokumentasi dan Informasi Hukum)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">SIMAD</h3>
                    <p class="text-gray-300">Sistem Informasi Manajemen Pengadaan Peralatan Desa.</p>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Kontak</h3>
                    <div class="space-y-2 text-gray-300">
                        <p><i class="fas fa-envelope mr-2"></i>info@clasnet.id</p>
                        <p><i class="fas fa-phone mr-2"></i>+62 851-1704-1846</p>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Ikuti Kami</h3>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-300 hover:text-white"><i class="fab fa-facebook text-xl"></i></a>
                        <a href="#" class="text-gray-300 hover:text-white"><i class="fab fa-twitter text-xl"></i></a>
                        <a href="#" class="text-gray-300 hover:text-white"><i class="fab fa-instagram text-xl"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-300">
                <p>&copy; 2024 SIMAD. Semua hak dilindungi. | Developed by <a href="https://clasnet.co.id" target="_blank" class="text-blue-400 hover:text-blue-300">clasnet.co.id</a></p>
            </div>
        </div>
    </footer>
</body>
</html>