<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get PDO connection
$pdo = getDBConnection();

$success_message = '';
$error_message = '';

// Handle delete image
if (isset($_POST['delete_image']) && isset($_POST['layanan_id'])) {
    $layanan_id = $_POST['layanan_id'];
    
    try {
        // Get current image path
        $stmt = $pdo->prepare("SELECT gambar FROM layanan WHERE id = ?");
        $stmt->execute([$layanan_id]);
        $layanan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($layanan && !empty($layanan['gambar'])) {
            // Delete file if exists
            if (file_exists($layanan['gambar'])) {
                unlink($layanan['gambar']);
            }
            
            // Update database
            $stmt = $pdo->prepare("UPDATE layanan SET gambar = NULL WHERE id = ?");
            $stmt->execute([$layanan_id]);
            
            $success_message = "Gambar berhasil dihapus!";
        }
    } catch (PDOException $e) {
        $error_message = "Error menghapus gambar: " . $e->getMessage();
    }
}

// Get all layanan with their images
try {
    $stmt = $pdo->query("
        SELECT l.*, k.nama_kategori 
        FROM layanan l 
        LEFT JOIN kategori k ON l.kategori_id = k.id 
        ORDER BY l.nama_layanan
    ");
    $layanan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error mengambil data layanan: " . $e->getMessage();
    $layanan_list = [];
}

// Format currency function
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
$page_title = 'Daftar Gambar Layanan';
require_once 'layouts/header.php';
?>

<style>
    .image-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }
</style>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                <i class="fas fa-spinner fa-spin text-blue-600"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">Memproses...</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">Mohon tunggu sebentar.</p>
            </div>
        </div>
    </div>
</div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Daftar Gambar Layanan</h1>
                <p class="text-sm text-gray-600 mt-1">Kelola gambar untuk setiap layanan</p>
            </div>
            <div class="mt-4 sm:mt-0 flex space-x-2">
                <a href="layanan-gambar-add.php" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Tambah Gambar
                </a>
                <a href="layanan.php" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-list mr-2"></i>
                    Daftar Layanan
                </a>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Layanan Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($layanan_list as $layanan): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <!-- Image Section -->
                        <div class="h-48 bg-gray-200 flex items-center justify-center">
                            <?php if (!empty($layanan['gambar']) && file_exists($layanan['gambar'])): ?>
                                <img src="<?= htmlspecialchars($layanan['gambar']) ?>" 
                                     alt="<?= htmlspecialchars($layanan['nama_layanan']) ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="text-center text-gray-500">
                                    <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="text-sm">Tidak ada gambar</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Content Section -->
                        <div class="p-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                <?= htmlspecialchars($layanan['nama_layanan']) ?>
                            </h3>
                            
                            <div class="space-y-2 text-sm text-gray-600">
                                <p><span class="font-medium">Kategori:</span> <?= htmlspecialchars($layanan['nama_kategori'] ?? 'Tidak ada kategori') ?></p>
                                <p><span class="font-medium">Harga:</span> <?= formatRupiah($layanan['harga']) ?></p>
                                <p><span class="font-medium">Status:</span> 
                                    <span class="<?= $layanan['status'] === 'aktif' ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= ucfirst($layanan['status']) ?>
                                    </span>
                                </p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-4 flex space-x-2">
                                <a href="layanan-view.php?id=<?= $layanan['id'] ?>" 
                                   class="flex-1 bg-blue-600 text-white text-center py-2 px-3 rounded text-sm hover:bg-blue-700">
                                    Lihat Detail
                                </a>
                                
                                <?php if (!empty($layanan['gambar'])): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus gambar ini?')">
                                        <input type="hidden" name="layanan_id" value="<?= $layanan['id'] ?>">
                                        <button type="submit" name="delete_image" 
                                                class="bg-red-600 text-white py-2 px-3 rounded text-sm hover:bg-red-700">
                                            Hapus Gambar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="layanan-gambar-add.php" 
                                       class="bg-green-600 text-white py-2 px-3 rounded text-sm hover:bg-green-700">
                                        Tambah Gambar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($layanan_list)): ?>
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada layanan</h3>
                    <p class="text-gray-500">Belum ada layanan yang terdaftar dalam sistem.</p>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistik Gambar</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php
                    $total_layanan = count($layanan_list);
                    $layanan_dengan_gambar = count(array_filter($layanan_list, function($l) { return !empty($l['gambar']); }));
                    $layanan_tanpa_gambar = $total_layanan - $layanan_dengan_gambar;
                    ?>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600"><?= $total_layanan ?></div>
                        <div class="text-sm text-gray-600">Total Layanan</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600"><?= $layanan_dengan_gambar ?></div>
                        <div class="text-sm text-gray-600">Dengan Gambar</div>
                    </div>
                    
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600"><?= $layanan_tanpa_gambar ?></div>
                        <div class="text-sm text-gray-600">Tanpa Gambar</div>
                    </div>
                </div>
            </div>
        
        <!-- Main Container End -->
    </div>

<?php require_once 'layouts/footer.php'; ?>