<?php
require_once 'config/database.php';

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect jika sudah login sebagai desa
if (isset($_SESSION['desa_id'])) {
    header('Location: client/dashboard.php');
    exit;
}

// Redirect admin ke dashboard
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['admin', 'supervisor', 'teknisi', 'sales'])) {
    header('Location: dashboard.php');
    exit;
}

// Redirect programmer ke dashboard programmer
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'programmer') {
    header('Location: dashboard-programmer.php');
    exit;
}

// Redirect akunting ke dashboard finance
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'akunting') {
    header('Location: dashboard-finance.php');
    exit;
}

// Ambil kategori untuk filter
$kategori_list = [];
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
    $kategori_list = [];
}

// Ambil produk promo untuk ditampilkan
$promo_produk = [];
try {
    $pdo = getDBConnection();
    
    // Ambil 12 produk promo dan featured terbaru
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
            OR p.is_featured = 1
        )
        ORDER BY 
            CASE 
                WHEN p.harga_diskon IS NOT NULL AND p.harga_diskon > 0 THEN 1
                WHEN p.is_featured = 1 THEN 2
                ELSE 3
            END,
            p.created_at DESC
        LIMIT 12
    ");
    $promo_produk_stmt->execute();
    $promo_produk = $promo_produk_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $promo_produk = [];
}

// Pagination untuk semua produk
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Ambil semua produk dengan pagination
$all_produk = [];
$total_produk = 0;
try {
    // Hitung total produk
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM produk p
        WHERE p.status = 'aktif'
    ");
    $count_stmt->execute();
    $total_produk = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ambil produk dengan pagination
    $all_produk_stmt = $pdo->prepare("
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
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $all_produk_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $all_produk_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $all_produk_stmt->execute();
    $all_produk = $all_produk_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $all_produk = [];
    $total_produk = 0;
}

// Hitung total halaman
$total_pages = ceil($total_produk / $limit);

// Function untuk badge promo
function getPromoBadge($produk) {
    $badges = [];
    
    if ($produk['harga_diskon'] && $produk['harga_diskon'] > 0 && $produk['harga_diskon'] < $produk['harga_satuan']) {
        $badges[] = '<span class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full z-10">-' . $produk['persentase_diskon'] . '%</span>';
    }
    
    if ($produk['stok_tersedia'] <= 5) {
        $badges[] = '<span class="absolute top-2 right-2 bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full z-10">Stok Terbatas</span>';
    } elseif ($produk['stok_tersedia'] <= 10) {
        $badges[] = '<span class="absolute top-2 right-2 bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded-full z-10">Stok Sedikit</span>';
    }
    
    if ($produk['is_featured']) {
        $badges[] = '<span class="absolute top-8 left-2 bg-blue-500 text-white text-xs font-bold px-2 py-1 rounded-full z-10">Unggulan</span>';
    }
    
    return implode('', $badges);
}

// Function untuk format harga
function formatHarga($harga) {
    return 'Rp ' . number_format($harga, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMAD - Solusi Belanja Online untuk Kebutuhan Desa</title>
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
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
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
                        <i class="fas fa-bolt text-2xl text-blue-600"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white">SIMAD</h1>
                        <p class="text-blue-100 text-sm">Sistem Informasi Manajemen Pengadaan Peralatan Desa</p>
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <button onclick="showLoginModal()" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200">
                        <i class="fas fa-user mr-1"></i>Login Desa
                    </button>
                    <a href="login.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                        <i class="fas fa-cog mr-1"></i>Login Admin
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Hero Promo Banner -->
        <div class="hero-promo rounded-lg p-8 mb-8 text-white relative overflow-hidden">
            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-4xl font-bold mb-2">
                            <i class="fas fa-fire mr-2 flash-sale"></i>
                            Cara Belanja Mudah Kebutuhan untuk Pelayanan Desa!
                        </h2>
                        <p class="text-xl mb-4">Toko online yang melayani desa dengan produk berkualitas dan terpercaya</p>
                        <button onclick="showLoginModal()" class="bg-white text-red-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-200">
                            <i class="fas fa-shopping-cart mr-2"></i>Mulai Berbelanja
                        </button>
                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-store text-8xl opacity-20"></i>
                    </div>
                </div>
            </div>
            <div class="absolute top-0 right-0 w-32 h-32 bg-white bg-opacity-10 rounded-full -mr-16 -mt-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white bg-opacity-10 rounded-full -ml-12 -mb-12"></div>
        </div>

        <!-- Produk Promo Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-percent mr-2 text-red-600"></i>
                    Produk Promo & Diskon
                </h3>
                <button onclick="showLoginModal()" class="text-blue-600 hover:text-blue-800 font-medium">
                    Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                </button>
            </div>
            
            <?php if (empty($promo_produk)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-tags text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-600 mb-4">Belum ada produk promo saat ini</p>
                    <button onclick="showLoginModal()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login untuk Melihat Produk
                    </button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($promo_produk as $produk): ?>
                        <div class="promo-card bg-white border border-gray-200 rounded-lg overflow-hidden relative flex flex-col h-full cursor-pointer" onclick="showLoginModal()">
                            <?= getPromoBadge($produk) ?>
                            
                            <!-- Product Image -->
                            <div class="aspect-w-1 aspect-h-1 w-full bg-gray-200 rounded-t-lg overflow-hidden">
                                <?php if ($produk['gambar']): ?>
                                    <img src="uploads/produk/<?= htmlspecialchars($produk['gambar']) ?>" 
                                         alt="<?= htmlspecialchars($produk['nama_produk']) ?>"
                                         class="w-full h-48 object-cover">
                                <?php else: ?>
                                    <div class="w-full h-48 bg-gray-300 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-500 text-3xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Product Info -->
                            <div class="p-4 flex-1 flex flex-col">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-800 mb-2 line-clamp-2">
                                        <?= htmlspecialchars($produk['nama_produk']) ?>
                                    </h4>
                                    
                                    <p class="text-sm text-gray-600 mb-2">
                                        <i class="fas fa-tag mr-1"></i>
                                        <?= htmlspecialchars($produk['kategori_nama'] ?? 'Tanpa Kategori') ?>
                                    </p>
                                    
                                    <p class="text-sm text-gray-600 mb-3">
                                        <i class="fas fa-box mr-1"></i>
                                        Stok: <?= number_format($produk['stok_tersedia']) ?>
                                    </p>
                                </div>
                                
                                <!-- Price -->
                                <div class="mt-auto">
                                    <?php if ($produk['harga_diskon'] && $produk['harga_diskon'] > 0): ?>
                                        <div class="flex items-center space-x-2 mb-2">
                                            <span class="text-lg font-bold text-red-600">
                                                <?= formatHarga($produk['harga_diskon']) ?>
                                            </span>
                                            <span class="text-sm text-gray-500 line-through">
                                                <?= formatHarga($produk['harga_satuan']) ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-2">
                                            <span class="text-lg font-bold text-gray-800">
                                                <?= formatHarga($produk['harga_satuan']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <button class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200">
                                        <i class="fas fa-shopping-cart mr-2"></i>Pesan Sekarang
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Promo Banner Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <?php
            // Ambil data promo banner dari database
            try {
                $promo_banners = [];
                $stmt = $pdo->prepare("SELECT * FROM promo_banners WHERE status = 'aktif' ORDER BY posisi ASC LIMIT 2");
                $stmt->execute();
                $promo_banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($promo_banners)) {
                    foreach ($promo_banners as $banner) {
                        echo '<div class="bg-white rounded-lg shadow-md overflow-hidden">';
                        echo '<div class="aspect-w-16 aspect-h-9 bg-gray-200">';
                        
                        if (!empty($banner['gambar']) && file_exists('uploads/promo/' . $banner['gambar'])) {
                            echo '<img src="uploads/promo/' . htmlspecialchars($banner['gambar']) . '" alt="' . htmlspecialchars($banner['judul']) . '" class="w-full h-64 object-cover">';
                        } else {
                            $gradient = $banner['posisi'] == 1 ? 'from-blue-500 to-purple-600' : 'from-green-500 to-teal-600';
                            $icon = $banner['posisi'] == 1 ? 'fa-percent' : 'fa-gift';
                            $title = $banner['posisi'] == 1 ? 'Promo Spesial' : 'Penawaran Khusus';
                            $subtitle = $banner['posisi'] == 1 ? 'Dapatkan penawaran terbaik' : 'Hemat lebih banyak hari ini';
                            
                            echo '<div class="w-full h-64 bg-gradient-to-r ' . $gradient . ' flex items-center justify-center">';
                            echo '<div class="text-center text-white">';
                            echo '<i class="fas ' . $icon . ' text-4xl mb-2"></i>';
                            echo '<h3 class="text-xl font-bold">' . $title . '</h3>';
                            echo '<p class="text-sm opacity-90">' . $subtitle . '</p>';
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        echo '</div>';
                        echo '<div class="p-4">';
                        echo '<h3 class="font-semibold text-gray-800 mb-2">' . htmlspecialchars($banner['judul']) . '</h3>';
                        if (!empty($banner['deskripsi'])) {
                            echo '<p class="text-sm text-gray-600">' . htmlspecialchars($banner['deskripsi']) . '</p>';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    // Fallback jika tidak ada data promo
                    echo '<div class="bg-white rounded-lg shadow-md overflow-hidden">';
                    echo '<div class="aspect-w-16 aspect-h-9 bg-gray-200">';
                    echo '<div class="w-full h-64 bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">';
                    echo '<div class="text-center text-white">';
                    echo '<i class="fas fa-percent text-4xl mb-2"></i>';
                    echo '<h3 class="text-xl font-bold">Promo Spesial</h3>';
                    echo '<p class="text-sm opacity-90">Dapatkan penawaran terbaik</p>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '<div class="p-4">';
                    echo '<h3 class="font-semibold text-gray-800 mb-2">Promo Banner</h3>';
                    echo '<p class="text-sm text-gray-600">Jangan lewatkan penawaran menarik ini!</p>';
                    echo '</div>';
                    echo '</div>';
                    
                    echo '<div class="bg-white rounded-lg shadow-md overflow-hidden">';
                    echo '<div class="aspect-w-16 aspect-h-9 bg-gray-200">';
                    echo '<div class="w-full h-64 bg-gradient-to-r from-green-500 to-teal-600 flex items-center justify-center">';
                    echo '<div class="text-center text-white">';
                    echo '<i class="fas fa-gift text-4xl mb-2"></i>';
                    echo '<h3 class="text-xl font-bold">Penawaran Khusus</h3>';
                    echo '<p class="text-sm opacity-90">Hemat lebih banyak hari ini</p>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '<div class="p-4">';
                    echo '<h3 class="font-semibold text-gray-800 mb-2">Promo Banner</h3>';
                    echo '<p class="text-sm text-gray-600">Jangan lewatkan penawaran menarik ini!</p>';
                    echo '</div>';
                    echo '</div>';
                }
            } catch (Exception $e) {
                // Error handling - tampilkan fallback
                echo '<div class="bg-white rounded-lg shadow-md overflow-hidden">';
                echo '<div class="aspect-w-16 aspect-h-9 bg-gray-200">';
                echo '<div class="w-full h-48 bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">';
                echo '<div class="text-center text-white">';
                echo '<i class="fas fa-percent text-4xl mb-2"></i>';
                echo '<h3 class="text-xl font-bold">Promo Spesial</h3>';
                echo '<p class="text-sm opacity-90">Dapatkan penawaran terbaik</p>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="p-4">';
                echo '<h3 class="font-semibold text-gray-800 mb-2">Promo Banner</h3>';
                echo '<p class="text-sm text-gray-600">Jangan lewatkan penawaran menarik ini!</p>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="bg-white rounded-lg shadow-md overflow-hidden">';
                echo '<div class="aspect-w-16 aspect-h-9 bg-gray-200">';
                echo '<div class="w-full h-48 bg-gradient-to-r from-green-500 to-teal-600 flex items-center justify-center">';
                echo '<div class="text-center text-white">';
                echo '<i class="fas fa-gift text-4xl mb-2"></i>';
                echo '<h3 class="text-xl font-bold">Penawaran Khusus</h3>';
                echo '<p class="text-sm opacity-90">Hemat lebih banyak hari ini</p>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="p-4">';
                echo '<h3 class="font-semibold text-gray-800 mb-2">Promo Banner</h3>';
                echo '<p class="text-sm text-gray-600">Jangan lewatkan penawaran menarik ini!</p>';
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- Semua Produk Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-cube mr-2 text-blue-600"></i>
                    Semua Produk
                </h3>
                <div class="text-sm text-gray-600">
                    Menampilkan <?= $offset + 1 ?>-<?= min($offset + $limit, $total_produk) ?> dari <?= number_format($total_produk) ?> produk
                </div>
            </div>
            
            <?php if (empty($all_produk)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-cube text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-600 mb-4">Belum ada produk tersedia saat ini</p>
                    <button onclick="showLoginModal()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login untuk Melihat Produk
                    </button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                    <?php foreach ($all_produk as $produk): ?>
                        <div class="promo-card bg-white border border-gray-200 rounded-lg overflow-hidden relative flex flex-col h-full cursor-pointer" onclick="showLoginModal()">
                            <?= getPromoBadge($produk) ?>
                            
                            <!-- Product Image -->
                            <div class="aspect-w-1 aspect-h-1 w-full bg-gray-200 rounded-t-lg overflow-hidden">
                                <?php if ($produk['gambar']): ?>
                                    <img src="uploads/produk/<?= htmlspecialchars($produk['gambar']) ?>" 
                                         alt="<?= htmlspecialchars($produk['nama_produk']) ?>"
                                         class="w-full h-48 object-cover">
                                <?php else: ?>
                                    <div class="w-full h-48 bg-gray-300 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-500 text-3xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Product Info -->
                            <div class="p-4 flex-1 flex flex-col">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-800 mb-2 line-clamp-2">
                                        <?= htmlspecialchars($produk['nama_produk']) ?>
                                    </h4>
                                    
                                    <p class="text-sm text-gray-600 mb-2">
                                        <i class="fas fa-tag mr-1"></i>
                                        <?= htmlspecialchars($produk['kategori_nama'] ?? 'Tanpa Kategori') ?>
                                    </p>
                                    
                                    <p class="text-sm text-gray-600 mb-3">
                                        <i class="fas fa-box mr-1"></i>
                                        Stok: <?= number_format($produk['stok_tersedia']) ?>
                                    </p>
                                </div>
                                
                                <!-- Price -->
                                <div class="mt-auto">
                                    <?php if ($produk['harga_diskon'] && $produk['harga_diskon'] > 0): ?>
                                        <div class="flex items-center space-x-2 mb-2">
                                            <span class="text-lg font-bold text-red-600">
                                                <?= formatHarga($produk['harga_diskon']) ?>
                                            </span>
                                            <span class="text-sm text-gray-500 line-through">
                                                <?= formatHarga($produk['harga_satuan']) ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-2">
                                            <span class="text-lg font-bold text-gray-800">
                                                <?= formatHarga($produk['harga_satuan']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <button class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200">
                                        <i class="fas fa-shopping-cart mr-2"></i>Pesan Sekarang
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex items-center justify-center space-x-2">
                        <!-- Previous Button -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition duration-200">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=<?= $i ?>" class="px-3 py-2 <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded-lg transition duration-200">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <!-- Next Button -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition duration-200">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center mt-4 text-sm text-gray-600">
                        Halaman <?= $page ?> dari <?= $total_pages ?> (Total: <?= number_format($total_produk) ?> produk)
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Features Section -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-shipping-fast text-blue-600 text-2xl"></i>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">Pengiriman Cepat</h4>
                <p class="text-sm text-gray-600">Pengiriman ke seluruh desa dengan cepat dan aman</p>
            </div>
            
            <a href="tracking-desa.php" class="bg-white rounded-lg shadow-md p-6 text-center hover:shadow-lg transition duration-200 block">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-search-location text-green-600 text-2xl"></i>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">Tracking Desa</h4>
                <p class="text-sm text-gray-600">Lacak pesanan hingga sampai ke desa Anda</p>
            </a>
            
            <a href="peta-desa.php" class="bg-white rounded-lg shadow-md p-6 text-center hover:shadow-lg transition duration-200 block">
                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-map-marked-alt text-purple-600 text-2xl"></i>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">Peta Desa</h4>
                <p class="text-sm text-gray-600">Jangkauan ke seluruh desa di Indonesia</p>
            </a>
            
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-award text-orange-600 text-2xl"></i>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">Produk Berkualitas</h4>
                <p class="text-sm text-gray-600">Produk terpilih dengan kualitas terbaik</p>
            </div>
        </div>


    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="login-card rounded-lg shadow-xl max-w-md w-full p-8 relative">
            <button onclick="hideLoginModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
            
            <div class="text-center mb-6">
                <div class="bg-blue-100 rounded-full w-16 h-16 mx-auto flex items-center justify-center mb-4">
                    <i class="fas fa-map-marker-alt text-2xl text-blue-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Login Desa</h2>
                <p class="text-gray-600">Masuk untuk mengakses produk dan layanan</p>
            </div>
            
            <form action="client/login.php" method="POST" class="space-y-4">
                <div>
                    <label for="kecamatan" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map mr-1"></i>Kecamatan
                    </label>
                    <select id="kecamatan" 
                            name="kecamatan" 
                            required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            onchange="loadDesaByKecamatan()">
                        <option value="">Pilih Kecamatan</option>
                    </select>
                </div>
                
                <div>
                    <label for="nama_desa" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1"></i>Nama Desa
                    </label>
                    <select id="nama_desa" 
                            name="nama_desa" 
                            required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            disabled>
                        <option value="">Pilih Desa</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Pilih kecamatan terlebih dahulu</p>
                </div>
                
                <div>
                    <label for="pin" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-1"></i>PIN Desa
                    </label>
                    <input type="password" 
                           id="pin" 
                           name="pin" 
                           required 
                           maxlength="6"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent pin-input"
                           placeholder="••••••">
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Butuh PIN Login?</strong><br>
                        Hubungi admin di WhatsApp: 
                        <a href="https://wa.me/6285117041846" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">
                            +62 851-1704-1846
                        </a>
                    </p>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition duration-200 font-semibold">
                    <i class="fas fa-sign-in-alt mr-2"></i>Masuk ke Portal
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Belum terdaftar? 
                    <button onclick="showRegisterModal()" class="text-blue-600 hover:text-blue-800 font-medium">Daftar Desa Baru</button>
                </p>
            </div>
        </div>
    </div>

    <!-- Modal Pendaftaran Desa -->
    <div id="registerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-plus-circle mr-2 text-green-600"></i>Pendaftaran Desa Baru
                </h3>
                <button onclick="hideRegisterModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Informasi Pendaftaran:</strong><br>
                        Untuk mendaftarkan desa baru, silahkan hubungi admin melalui WhatsApp dengan menyertakan data lengkap desa.
                    </p>
                </div>
                
                <form id="registerForm" class="space-y-4">
                    <div>
                        <label for="reg_nama_desa" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1"></i>Nama Desa
                        </label>
                        <input type="text" 
                               id="reg_nama_desa" 
                               name="nama_desa" 
                               required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Contoh: Adipasir">
                    </div>
                    
                    <div>
                        <label for="reg_kecamatan" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map mr-1"></i>Kecamatan
                        </label>
                        <input type="text" 
                               id="reg_kecamatan" 
                               name="kecamatan" 
                               required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Contoh: Rakit">
                    </div>
                    
                    <div>
                        <label for="reg_kabupaten" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-building mr-1"></i>Kabupaten
                        </label>
                        <input type="text" 
                               id="reg_kabupaten" 
                               name="kabupaten" 
                               required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Contoh: Banjarnegara">
                    </div>
                    
                    <div>
                        <label for="reg_nama_kepala_desa" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-1"></i>Nama Kepala Desa
                        </label>
                        <input type="text" 
                               id="reg_nama_kepala_desa" 
                               name="nama_kepala_desa" 
                               required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Nama lengkap kepala desa">
                    </div>
                    
                    <div>
                        <label for="reg_no_hp" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1"></i>No. HP/WhatsApp
                        </label>
                        <input type="tel" 
                               id="reg_no_hp" 
                               name="no_hp" 
                               required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Contoh: 08123456789">
                    </div>
                    
                    <div>
                        <label for="reg_email" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-1"></i>Email Desa (Opsional)
                        </label>
                        <input type="email" 
                               id="reg_email" 
                               name="email" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="email@desa.id">
                    </div>
                    
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-sm text-green-800">
                            <i class="fas fa-whatsapp mr-2"></i>
                            <strong>Langkah Selanjutnya:</strong><br>
                            Setelah mengisi form, klik tombol "Kirim via WhatsApp" untuk mengirim data pendaftaran ke admin.
                        </p>
                    </div>
                    
                    <button type="button" onclick="sendRegistrationToWhatsApp()" class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition duration-200 font-semibold">
                        <i class="fab fa-whatsapp mr-2"></i>Kirim via WhatsApp
                    </button>
                </form>
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

    <script>
        // Modal functions
        function showLoginModal() {
            document.getElementById('loginModal').classList.remove('hidden');
            loadKecamatanData();
        }
        
        function hideLoginModal() {
            document.getElementById('loginModal').classList.add('hidden');
        }
        
        function showRegisterModal() {
            document.getElementById('registerModal').classList.remove('hidden');
        }
        
        function hideRegisterModal() {
            document.getElementById('registerModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('loginModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideLoginModal();
            }
        });
        
        document.getElementById('registerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideRegisterModal();
            }
        });
        
        // Load kecamatan data
        async function loadKecamatanData() {
            try {
                const response = await fetch('api/get_desa_data.php?action=get_kecamatan');
                const data = await response.json();
                console.log('Kecamatan data:', data);
                
                if (data.success) {
                    const kecamatanSelect = document.getElementById('kecamatan');
                    kecamatanSelect.innerHTML = '<option value="">Pilih Kecamatan</option>';
                    
                    data.data.forEach(kecamatan => {
                        // Skip empty kecamatan
                        if (kecamatan && kecamatan.trim() !== '') {
                            const option = document.createElement('option');
                            option.value = kecamatan;
                            option.textContent = kecamatan;
                            kecamatanSelect.appendChild(option);
                        }
                    });
                    console.log('Kecamatan options added:', kecamatanSelect.children.length);
                }
            } catch (error) {
                console.error('Error loading kecamatan:', error);
            }
        }
        
        // Load desa by kecamatan
        async function loadDesaByKecamatan() {
            const kecamatan = document.getElementById('kecamatan').value;
            const desaSelect = document.getElementById('nama_desa');
            
            if (!kecamatan) {
                desaSelect.innerHTML = '<option value="">Pilih Desa</option>';
                desaSelect.disabled = true;
                return;
            }
            
            try {
                const response = await fetch(`api/get_desa_data.php?action=get_desa&kecamatan=${encodeURIComponent(kecamatan)}`);
                const data = await response.json();
                
                if (data.success) {
                    desaSelect.innerHTML = '<option value="">Pilih Desa</option>';
                    
                    data.data.forEach(desa => {
                        const option = document.createElement('option');
                        option.value = desa.nama_desa;
                        option.textContent = desa.nama_desa;
                        desaSelect.appendChild(option);
                    });
                    
                    desaSelect.disabled = false;
                }
            } catch (error) {
                console.error('Error loading desa:', error);
            }
        }
        
        // Send registration to WhatsApp
        function sendRegistrationToWhatsApp() {
            const form = document.getElementById('registerForm');
            const formData = new FormData(form);
            
            // Validate required fields
            const requiredFields = ['nama_desa', 'kecamatan', 'kabupaten', 'nama_kepala_desa', 'no_hp'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const value = formData.get(field);
                if (!value || value.trim() === '') {
                    isValid = false;
                    document.getElementById('reg_' + field).classList.add('border-red-500');
                } else {
                    document.getElementById('reg_' + field).classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                alert('Mohon lengkapi semua field yang wajib diisi!');
                return;
            }
            
            // Create WhatsApp message
            const message = `*PENDAFTARAN DESA BARU - SIMAD*\n\n` +
                          `Nama Desa: ${formData.get('nama_desa')}\n` +
                          `Kecamatan: ${formData.get('kecamatan')}\n` +
                          `Kabupaten: ${formData.get('kabupaten')}\n` +
                          `Kepala Desa: ${formData.get('nama_kepala_desa')}\n` +
                          `No. HP: ${formData.get('no_hp')}\n` +
                          `Email: ${formData.get('email') || 'Tidak ada'}\n\n` +
                          `Mohon diproses pendaftaran desa baru ini. Terima kasih.`;
            
            const whatsappUrl = `https://wa.me/6285117041846?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
            
            // Reset form and close modal
            form.reset();
            hideRegisterModal();
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLoginModal();
            }
        });
    </script>
</body>
</html>