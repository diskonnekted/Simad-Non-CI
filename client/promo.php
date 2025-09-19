<?php
require_once '../config/database.php';

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek login - TEMPORARY BYPASS FOR TESTING
if (!isset($_SESSION['desa_id'])) {
    // header('Location: login.php');
    // exit;
    // Set temporary session for testing
    $_SESSION['desa_id'] = 1;
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
}

// Inisialisasi variabel default
$kategori_list = [];

// Ambil kategori langsung tanpa menunggu try block
try {
    $pdo_kategori = getDBConnection();
    $kategori_stmt = $pdo_kategori->prepare("
        SELECT DISTINCT k.nama_kategori 
        FROM kategori_produk k 
        INNER JOIN produk p ON k.id = p.kategori_id 
        WHERE p.status = 'aktif'
        ORDER BY k.nama_kategori
    ");
    $kategori_stmt->execute();
    $kategori_list = $kategori_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Jika gagal, tetap gunakan array kosong
    $kategori_list = [];
}

// Ambil data desa
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM desa WHERE id = ?");
    $stmt->execute([$_SESSION['desa_id']]);
    $desa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Pagination parameters
    $promo_page = isset($_GET['promo_page']) ? max(1, intval($_GET['promo_page'])) : 1;
    $terlaris_page = isset($_GET['terlaris_page']) ? max(1, intval($_GET['terlaris_page'])) : 1;
    $items_per_page = 6;
    $promo_offset = ($promo_page - 1) * $items_per_page;
    $terlaris_offset = ($terlaris_page - 1) * $items_per_page;

    // Count total promo products
    $count_promo_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM produk p
        WHERE p.status = 'aktif' 
        AND (
            (p.harga_diskon IS NOT NULL AND p.harga_diskon > 0 AND p.harga_diskon < p.harga_satuan)
            OR p.stok_tersedia <= 10
            OR p.is_featured = 1
        )
    ");
    $count_promo_stmt->execute();
    $total_promo = $count_promo_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_promo_pages = ceil($total_promo / $items_per_page);

    // Ambil produk dengan promo (harga diskon atau stok terbatas)
    $promo_produk_stmt = $pdo->prepare("
        SELECT 
            p.*,
            k.nama_kategori as kategori_nama,
            CASE 
                WHEN p.harga_diskon IS NOT NULL AND p.harga_diskon > 0 THEN 
                    ROUND(((p.harga_satuan - p.harga_diskon) / p.harga_satuan) * 100)
                ELSE 0
            END as persentase_diskon
        FROM produk p
        LEFT JOIN kategori_produk k ON p.kategori_id = k.id
        WHERE p.status = 'aktif' 
        AND (
            (p.harga_diskon IS NOT NULL AND p.harga_diskon > 0 AND p.harga_diskon < p.harga_satuan)
            OR p.stok_tersedia <= 10
            OR p.is_featured = 1
        )
        ORDER BY 
            CASE 
                WHEN p.harga_diskon IS NOT NULL AND p.harga_diskon > 0 THEN 1
                WHEN p.stok_tersedia <= 5 THEN 2
                WHEN p.stok_tersedia <= 10 THEN 3
                WHEN p.is_featured = 1 THEN 4
                ELSE 5
            END,
            p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $promo_produk_stmt->execute([$items_per_page, $promo_offset]);
    $promo_produk = $promo_produk_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil layanan unggulan
    $layanan_unggulan_stmt = $pdo->prepare("
        SELECT * FROM layanan 
        WHERE status = 'aktif' 
        AND (is_featured = 1 OR harga_diskon IS NOT NULL)
        ORDER BY 
            CASE 
                WHEN harga_diskon IS NOT NULL AND harga_diskon > 0 THEN 1
                WHEN is_featured = 1 THEN 2
                ELSE 3
            END,
            created_at DESC
        LIMIT 6
    ");
    $layanan_unggulan_stmt->execute();
    $layanan_unggulan = $layanan_unggulan_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total bestselling products
    $count_terlaris_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.id) as total
        FROM produk p
        INNER JOIN transaksi t ON p.id = t.produk_id
        WHERE p.status = 'aktif'
    ");
    $count_terlaris_stmt->execute();
    $total_terlaris = $count_terlaris_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_terlaris_pages = ceil($total_terlaris / $items_per_page);

    // Ambil produk terlaris (berdasarkan jumlah transaksi)
    $terlaris_stmt = $pdo->prepare("
        SELECT 
            p.*,
            k.nama as kategori_nama,
            COUNT(t.id) as total_terjual,
            SUM(t.jumlah) as total_quantity
        FROM produk p
        LEFT JOIN kategori_produk k ON p.kategori_id = k.id
        LEFT JOIN transaksi t ON p.id = t.produk_id
        WHERE p.status = 'aktif'
        GROUP BY p.id
        HAVING total_terjual > 0
        ORDER BY total_terjual DESC, total_quantity DESC
        LIMIT ? OFFSET ?
    ");
    $terlaris_stmt->execute([$items_per_page, $terlaris_offset]);
    $produk_terlaris = $terlaris_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

// Fungsi untuk mendapatkan badge promo
function getPromoBadge($produk) {
    $badges = [];
    
    // Diskon
    if (!empty($produk['harga_diskon']) && $produk['harga_diskon'] < $produk['harga_satuan']) {
        $persentase = round((($produk['harga_satuan'] - $produk['harga_diskon']) / $produk['harga_satuan']) * 100);
        $badges[] = '<span class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 text-xs font-bold rounded-full z-10">-' . $persentase . '%</span>';
    }
    
    // Stok terbatas
    if ($produk['stok_tersedia'] <= 5) {
        $badges[] = '<span class="absolute top-2 right-2 bg-orange-500 text-white px-2 py-1 text-xs font-bold rounded-full z-10">Stok Terbatas</span>';
    } elseif ($produk['stok_tersedia'] <= 10) {
        $badges[] = '<span class="absolute top-2 right-2 bg-yellow-500 text-white px-2 py-1 text-xs font-bold rounded-full z-10">Stok Sedikit</span>';
    }
    
    // Featured - pindah ke posisi yang tidak mengganggu tombol
    if (!empty($produk['is_featured'])) {
        $badges[] = '<span class="absolute top-10 left-2 bg-blue-500 text-white px-2 py-1 text-xs font-bold rounded-full z-10"><i class="fas fa-star mr-1"></i>Unggulan</span>';
    }
    
    return implode('', $badges);
}

// Fungsi untuk format harga
function formatHarga($harga, $harga_diskon = null) {
    if ($harga_diskon && $harga_diskon < $harga) {
        return '<div class="space-y-1">' .
               '<span class="text-lg font-bold text-red-600">Rp ' . number_format($harga_diskon, 0, ',', '.') . '</span>' .
               '<span class="text-sm text-gray-500 line-through block">Rp ' . number_format($harga, 0, ',', '.') . '</span>' .
               '</div>';
    } else {
        return '<span class="text-lg font-bold text-gray-800">Rp ' . number_format($harga, 0, ',', '.') . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promo Produk - Portal Klien Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .promo-card {
            transition: all 0.3s ease;
        }
        .promo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .hero-promo {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        .flash-sale {
            animation: flash 2s infinite;
        }
        @keyframes flash {
            0%, 50%, 100% { opacity: 1; }
            25%, 75% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="dashboard.php" class="bg-white bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center text-white hover:bg-opacity-30 transition duration-200">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-white">Promo Produk</h1>
                        <p class="text-blue-100 text-sm"><?= htmlspecialchars($desa['nama_desa'] ?? '') ?>, <?= htmlspecialchars($desa['kecamatan'] ?? '') ?></p>
                    </div>
                </div>
                
                <a href="dashboard.php" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200">
                    <i class="fas fa-home mr-1"></i>Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-6 py-8">
        <!-- Hero Promo Banner -->
        <div class="hero-promo rounded-lg p-8 mb-8 text-white relative overflow-hidden">
            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-3xl font-bold mb-2">
                            <i class="fas fa-fire mr-2 flash-sale"></i>
                            Promo Spesial Hari Ini!
                        </h2>
                        <p class="text-xl mb-4">Dapatkan diskon hingga 50% untuk produk pilihan</p>
                        <a href="#promo-products" class="bg-white text-red-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-200">
                            <i class="fas fa-shopping-cart mr-2"></i>Lihat Promo
                        </a>
                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-tags text-6xl opacity-20"></i>
                    </div>
                </div>
            </div>
            <div class="absolute top-0 right-0 w-32 h-32 bg-white bg-opacity-10 rounded-full -mr-16 -mt-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white bg-opacity-10 rounded-full -ml-12 -mb-12"></div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-white rounded-lg shadow-md mb-8">
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <button onclick="showSection('promo')" class="tab-button active border-b-2 border-red-500 py-4 px-1 text-sm font-medium text-red-600">
                        <i class="fas fa-percent mr-2"></i>Promo Diskon
                    </button>
                    <button onclick="showSection('terlaris')" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-fire mr-2"></i>Terlaris
                    </button>
                    <button onclick="showSection('layanan')" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-star mr-2"></i>Layanan Unggulan
                    </button>
                </nav>
            </div>
        </div>

        <!-- Search Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row gap-4">
                <!-- Search Input -->
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Cari produk berdasarkan nama..." 
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Category Filter -->
                <div class="md:w-64">
                    <select id="categoryFilter" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Semua Kategori</option>

                        <?php foreach ($kategori_list as $kategori): ?>
                            <option value="<?= htmlspecialchars($kategori['nama_kategori']) ?>">
                                <?= htmlspecialchars($kategori['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Clear Button -->
                <button id="clearSearch" 
                        class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Reset
                </button>
            </div>
            
            <!-- Search Results Info -->
            <div id="searchInfo" class="mt-4 text-sm text-gray-600 hidden">
                <i class="fas fa-info-circle mr-1"></i>
                <span id="searchResultText"></span>
            </div>
        </div>

        <!-- Promo Products Section -->
        <div id="promo-section" class="section-content">
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6" id="promo-products">
                    <i class="fas fa-percent mr-2 text-red-600"></i>
                    Produk Promo & Diskon
                </h3>
                
                <?php if (empty($promo_produk)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-tags text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-600 mb-4">Belum ada produk promo saat ini</p>
                        <a href="order.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-shopping-cart mr-2"></i>Lihat Semua Produk
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($promo_produk as $produk): ?>
                            <div class="promo-card bg-white border border-gray-200 rounded-lg overflow-hidden relative flex flex-col h-full">
                                <?= getPromoBadge($produk) ?>
                                
                                <!-- Product Image -->
                                <div class="relative h-48 bg-gray-100 flex-shrink-0">
                                    <?php if (!empty($produk['gambar'])): ?>
                                        <img src="../uploads/produk/<?= htmlspecialchars($produk['gambar']) ?>" 
                                             alt="<?= htmlspecialchars($produk['nama_produk']) ?>" 
                                             class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="fas fa-image text-4xl text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-6 flex flex-col flex-grow">
                                    <div class="mb-3">
                                        <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">
                                            <?= htmlspecialchars($produk['kategori_nama'] ?? 'Umum') ?>
                                        </span>
                                    </div>
                                    
                                    <h4 class="font-semibold text-gray-800 mb-2 line-clamp-2 min-h-[3rem]">
                                        <?= htmlspecialchars($produk['nama_produk']) ?>
                                    </h4>
                                    
                                    <p class="text-sm text-gray-600 mb-4 line-clamp-2 flex-grow">
                                        <?= htmlspecialchars($produk['deskripsi'] ?? 'Tidak ada deskripsi') ?>
                                    </p>
                                    
                                    <div class="flex items-center justify-between mb-4">
                                        <?= formatHarga($produk['harga_satuan'], $produk['harga_diskon']) ?>
                                        <span class="text-sm text-gray-500">
                                            Stok: <?= $produk['stok_tersedia'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex space-x-2 mt-auto">
                                        <a href="order.php?produk=<?= $produk['id'] ?>" 
                                           class="flex-1 bg-red-600 text-white text-center py-2.5 rounded-lg hover:bg-red-700 transition duration-200 text-sm font-medium">
                                            <i class="fas fa-shopping-cart mr-1"></i>Pesan
                                        </a>
                                        <button onclick="showProductDetail(<?= htmlspecialchars(json_encode($produk)) ?>)" 
                                                class="bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-300 transition duration-200">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination for Promo Products -->
                    <?php if ($total_promo_pages > 1): ?>
                        <div class="flex justify-center mt-8">
                            <nav class="flex items-center space-x-2">
                                <?php if ($promo_page > 1): ?>
                                    <a href="?promo_page=<?= $promo_page - 1 ?>&terlaris_page=<?= $terlaris_page ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_promo_pages; $i++): ?>
                                    <?php if ($i == $promo_page): ?>
                                        <span class="px-3 py-2 text-sm font-medium text-white bg-red-600 border border-red-600 rounded-md">
                                            <?= $i ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="?promo_page=<?= $i ?>&terlaris_page=<?= $terlaris_page ?>" 
                                           class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                            <?= $i ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($promo_page < $total_promo_pages): ?>
                                    <a href="?promo_page=<?= $promo_page + 1 ?>&terlaris_page=<?= $terlaris_page ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Best Sellers Section -->
        <div id="terlaris-section" class="section-content hidden">
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-fire mr-2 text-orange-600"></i>
                    Produk Terlaris
                </h3>
                
                <?php if (empty($produk_terlaris)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-chart-line text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-600">Belum ada data penjualan</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($produk_terlaris as $index => $produk): ?>
                            <div class="promo-card bg-white border border-gray-200 rounded-lg overflow-hidden relative flex flex-col h-full">
                                <span class="absolute top-2 left-2 bg-orange-500 text-white px-2 py-1 text-xs font-bold rounded-full z-10">
                                    #<?= $index + 1 ?> Terlaris
                                </span>
                                
                                <!-- Product Image -->
                                <div class="relative h-48 bg-gray-100 flex-shrink-0">
                                    <?php if (!empty($produk['gambar'])): ?>
                                        <img src="../uploads/produk/<?= htmlspecialchars($produk['gambar']) ?>" 
                                             alt="<?= htmlspecialchars($produk['nama_produk']) ?>" 
                                             class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="fas fa-image text-4xl text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-6 flex flex-col flex-grow">
                                    <div class="mb-3">
                                        <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">
                                            <?= htmlspecialchars($produk['kategori_nama'] ?? 'Umum') ?>
                                        </span>
                                    </div>
                                    
                                    <h4 class="font-semibold text-gray-800 mb-2 line-clamp-2 min-h-[3rem]">
                                        <?= htmlspecialchars($produk['nama_produk']) ?>
                                    </h4>
                                    
                                    <p class="text-sm text-gray-600 mb-4 line-clamp-2 flex-grow">
                                        <?= htmlspecialchars($produk['deskripsi'] ?? 'Tidak ada deskripsi') ?>
                                    </p>
                                    
                                    <div class="flex items-center justify-between mb-4">
                                        <span class="text-lg font-bold text-gray-800">
                                            Rp <?= number_format($produk['harga_satuan'], 0, ',', '.') ?>
                                        </span>
                                        <span class="text-sm text-orange-600 font-medium">
                                            <?= $produk['total_quantity'] ?> terjual
                                        </span>
                                    </div>
                                    
                                    <div class="flex space-x-2 mt-auto">
                                        <a href="order.php?produk=<?= $produk['id'] ?>" 
                                           class="flex-1 bg-orange-600 text-white text-center py-2.5 rounded-lg hover:bg-orange-700 transition duration-200 text-sm font-medium">
                                            <i class="fas fa-shopping-cart mr-1"></i>Pesan
                                        </a>
                                        <button onclick="showProductDetail(<?= htmlspecialchars(json_encode($produk)) ?>)" 
                                                class="bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-300 transition duration-200">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination for Best Selling Products -->
                    <?php if ($total_terlaris_pages > 1): ?>
                        <div class="flex justify-center mt-8">
                            <nav class="flex items-center space-x-2">
                                <?php if ($terlaris_page > 1): ?>
                                    <a href="?promo_page=<?= $promo_page ?>&terlaris_page=<?= $terlaris_page - 1 ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_terlaris_pages; $i++): ?>
                                    <?php if ($i == $terlaris_page): ?>
                                        <span class="px-3 py-2 text-sm font-medium text-white bg-orange-600 border border-orange-600 rounded-md">
                                            <?= $i ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="?promo_page=<?= $promo_page ?>&terlaris_page=<?= $i ?>" 
                                           class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                            <?= $i ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($terlaris_page < $total_terlaris_pages): ?>
                                    <a href="?promo_page=<?= $promo_page ?>&terlaris_page=<?= $terlaris_page + 1 ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Featured Services Section -->
        <div id="layanan-section" class="section-content hidden">
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-star mr-2 text-yellow-600"></i>
                    Layanan Unggulan
                </h3>
                
                <?php if (empty($layanan_unggulan)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-concierge-bell text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-600">Belum ada layanan unggulan</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($layanan_unggulan as $layanan): ?>
                            <div class="promo-card bg-white border border-gray-200 rounded-lg overflow-hidden relative">
                                <?php if (!empty($layanan['is_featured'])): ?>
                                    <span class="absolute top-2 right-2 bg-yellow-500 text-white px-2 py-1 text-xs font-bold rounded-full">
                                        <i class="fas fa-star mr-1"></i>Unggulan
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($layanan['harga_diskon']) && $layanan['harga_diskon'] < $layanan['harga']): ?>
                                    <?php $persentase = round((($layanan['harga'] - $layanan['harga_diskon']) / $layanan['harga']) * 100); ?>
                                    <span class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 text-xs font-bold rounded-full">
                                        -<?= $persentase ?>%
                                    </span>
                                <?php endif; ?>
                                
                                <div class="p-6">
                                    <h4 class="font-semibold text-gray-800 mb-2">
                                        <?= htmlspecialchars($layanan['nama_layanan']) ?>
                                    </h4>
                                    
                                    <p class="text-sm text-gray-600 mb-4">
                                        <?= htmlspecialchars($layanan['deskripsi'] ?? 'Tidak ada deskripsi') ?>
                                    </p>
                                    
                                    <div class="mb-4">
                                        <?= formatHarga($layanan['harga'], $layanan['harga_diskon']) ?>
                                    </div>
                                    
                                    <a href="order.php?layanan=<?= $layanan['id'] ?>" 
                                       class="block w-full bg-yellow-600 text-white text-center py-2 rounded-lg hover:bg-yellow-700 transition duration-200 font-medium">
                                        <i class="fas fa-hand-point-right mr-2"></i>Pesan Layanan
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-bolt mr-2 text-yellow-600"></i>
                Aksi Cepat
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="order.php" class="bg-blue-600 text-white p-4 rounded-lg hover:bg-blue-700 transition duration-200 text-center">
                    <i class="fas fa-shopping-cart text-2xl mb-2"></i>
                    <div class="font-medium">Buat Pesanan</div>
                    <div class="text-sm opacity-90">Pesan produk atau layanan</div>
                </a>
                
                <a href="financial.php" class="bg-green-600 text-white p-4 rounded-lg hover:bg-green-700 transition duration-200 text-center">
                    <i class="fas fa-chart-line text-2xl mb-2"></i>
                    <div class="font-medium">Status Keuangan</div>
                    <div class="text-sm opacity-90">Cek pembayaran & hutang</div>
                </a>
                
                <a href="consultation.php" class="bg-purple-600 text-white p-4 rounded-lg hover:bg-purple-700 transition duration-200 text-center">
                    <i class="fas fa-comments text-2xl mb-2"></i>
                    <div class="font-medium">Konsultasi</div>
                    <div class="text-sm opacity-90">Hubungi admin</div>
                </a>
            </div>
        </div>
    </div>

    <!-- Product Detail Modal -->
    <div id="productModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Detail Produk</h3>
                    <button onclick="closeProductModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="modalContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="container mx-auto px-4 py-6">
            <div class="text-center text-gray-600 text-sm">
                © 2025 Portal Klien Desa - Sistem Manajemen Transaksi
            </div>
        </div>
    </footer>

    <script>
        // Tab functionality
        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.section-content').forEach(el => {
                el.classList.add('hidden');
            });
            
            // Show selected section
            document.getElementById(section + '-section').classList.remove('hidden');
            
            // Update active tab
            document.querySelectorAll('.tab-button').forEach(tab => {
                tab.classList.remove('active', 'border-red-500', 'text-red-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            event.target.classList.add('active', 'border-red-500', 'text-red-600');
            event.target.classList.remove('border-transparent', 'text-gray-500');
            
            // Store active tab in localStorage
            localStorage.setItem('activeTab', section);
        }
        
        // Initialize page with correct tab
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = localStorage.getItem('activeTab') || 'promo';
            const tabButton = document.querySelector(`[onclick="showSection('${activeTab}')"]`);
            if (tabButton) {
                tabButton.click();
            }
        });
        
        // Update pagination links to preserve active tab
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = localStorage.getItem('activeTab') || 'promo';
            const paginationLinks = document.querySelectorAll('nav a[href*="page="]');
            paginationLinks.forEach(link => {
                const url = new URL(link.href, window.location.origin);
                url.hash = activeTab;
                link.href = url.toString();
            });
        });
        
        // Product detail modal
        function showProductDetail(produk) {
            const modal = document.getElementById('productModal');
            const content = document.getElementById('modalContent');
            
            let hargaHTML = '';
            if (produk.harga_diskon && produk.harga_diskon < produk.harga_satuan) {
                const persentase = Math.round(((produk.harga_satuan - produk.harga_diskon) / produk.harga_satuan) * 100);
                hargaHTML = `
                    <div class="mb-4">
                        <div class="flex items-center space-x-2 mb-2">
                            <span class="text-xl font-bold text-red-600">Rp ${parseInt(produk.harga_diskon).toLocaleString('id-ID')}</span>
                            <span class="bg-red-500 text-white px-2 py-1 text-xs rounded-full">-${persentase}%</span>
                        </div>
                        <span class="text-sm text-gray-500 line-through">Rp ${parseInt(produk.harga_satuan).toLocaleString('id-ID')}</span>
                    </div>
                `;
            } else {
                hargaHTML = `
                    <div class="mb-4">
                        <span class="text-xl font-bold text-gray-800">Rp ${parseInt(produk.harga_satuan || 0).toLocaleString('id-ID')}</span>
                    </div>
                `;
            }
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">${produk.nama_produk || 'undefined'}</h4>
                        <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full">
                            ${produk.kategori_nama || 'Umum'}
                        </span>
                    </div>
                    
                    ${hargaHTML}
                    
                    <div>
                        <p class="text-sm text-gray-600 mb-2"><strong>Deskripsi:</strong></p>
                        <p class="text-sm text-gray-600">${produk.deskripsi || 'Mouse wireless untuk komputer'}</p>
                    </div>
                    
                    ${produk.spesifikasi ? `
                    <div>
                        <p class="text-sm text-gray-600 mb-2"><strong>Spesifikasi:</strong></p>
                        <p class="text-sm text-gray-600">${produk.spesifikasi}</p>
                    </div>
                    ` : ''}
                    
                    <div class="flex items-center justify-between text-sm text-gray-600">
                        <span>Stok tersedia: <strong>${produk.stok_tersedia || 'undefined'}</strong></span>
                        ${produk.total_quantity ? `<span>Terjual: <strong>${produk.total_quantity}</strong></span>` : ''}
                    </div>
                    
                    <div class="flex space-x-3 pt-4">
                        <a href="order.php?produk=${produk.id}" 
                           class="flex-1 bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-shopping-cart mr-2"></i>Pesan Sekarang
                        </a>
                        <button onclick="closeProductModal()" 
                                class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition duration-200">
                            Tutup
                        </button>
                    </div>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }
        
        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductModal();
            }
        });
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const clearButton = document.getElementById('clearSearch');
        const searchInfo = document.getElementById('searchInfo');
        const searchResultText = document.getElementById('searchResultText');
        
        let allProducts = [];
        
        // Collect all products on page load
        function collectProducts() {
            allProducts = [];
            const promoCards = document.querySelectorAll('.promo-card');
            promoCards.forEach(card => {
                const nameElement = card.querySelector('h4');
                const categoryElement = card.querySelector('.text-blue-600');
                if (nameElement && categoryElement) {
                    allProducts.push({
                        element: card,
                        name: nameElement.textContent.toLowerCase(),
                        category: categoryElement.textContent.trim()
                    });
                }
            });
        }
        
        // Filter products based on search criteria
        function filterProducts() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const selectedCategory = categoryFilter.value;
            
            let visibleCount = 0;
            
            allProducts.forEach(product => {
                const matchesSearch = searchTerm === '' || product.name.includes(searchTerm);
                const matchesCategory = selectedCategory === '' || product.category === selectedCategory;
                
                if (matchesSearch && matchesCategory) {
                    product.element.style.display = 'block';
                    visibleCount++;
                } else {
                    product.element.style.display = 'none';
                }
            });
            
            // Update search info
            if (searchTerm !== '' || selectedCategory !== '') {
                searchInfo.classList.remove('hidden');
                if (visibleCount === 0) {
                    searchResultText.textContent = 'Tidak ada produk yang ditemukan';
                } else {
                    searchResultText.textContent = `Menampilkan ${visibleCount} produk`;
                }
            } else {
                searchInfo.classList.add('hidden');
            }
        }
        
        // Clear search
        function clearSearch() {
            searchInput.value = '';
            categoryFilter.value = '';
            filterProducts();
            searchInput.focus();
        }
        
        // Event listeners
        searchInput.addEventListener('input', filterProducts);
        categoryFilter.addEventListener('change', filterProducts);
        clearButton.addEventListener('click', clearSearch);
        
        // Initialize on page load and tab switch
        document.addEventListener('DOMContentLoaded', collectProducts);
        
        // Re-collect products when switching tabs
        const originalShowSection = window.showSection;
        window.showSection = function(section) {
            originalShowSection(section);
            setTimeout(collectProducts, 100); // Small delay to ensure DOM is updated
        };
        
        // Smooth scroll to promo section
        document.querySelector('a[href="#promo-products"]').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('promo-products').scrollIntoView({
                behavior: 'smooth'
            });
        });
        
        // Auto-refresh promo data every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>