<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'sales'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */
$error = '';
$success = '';
$produk_id = intval($_GET['id'] ?? 0);

if (!$produk_id) {
    header('Location: produk.php?error=invalid_id');
    exit;
}

// Ambil data produk
$produk = $db->select("
    SELECT p.*, k.nama_kategori
    FROM produk p
    LEFT JOIN kategori_produk k ON p.kategori_id = k.id
    WHERE p.id = ? AND p.status != 'deleted'
", [$produk_id]);

if (empty($produk)) {
    header('Location: produk.php?error=not_found');
    exit;
}

$produk = $produk[0];

// Cek otorisasi - sales hanya bisa edit produk yang dibuat sendiri
if ($user['role'] === 'sales' && $produk['created_by'] != $user['id']) {
    header('Location: produk.php?error=access_denied');
    exit;
}

// Ambil daftar kategori
$kategori_list = $db->select("
    SELECT id, nama_kategori 
    FROM kategori_produk 
    ORDER BY nama_kategori
");

// Ambil data harga pembelian terakhir dari vendor
$harga_pembelian = $db->select("
    SELECT pd.harga_satuan as harga_beli, p.tanggal_pembelian, v.nama_vendor,
           p.nomor_po, pd.quantity_pesan
    FROM pembelian_detail pd
    JOIN pembelian p ON pd.pembelian_id = p.id
    JOIN vendor v ON p.vendor_id = v.id
    WHERE pd.produk_id = ? AND p.status_pembelian != 'dibatalkan'
    ORDER BY p.tanggal_pembelian DESC, p.id DESC
    LIMIT 1
", [$produk_id]);

$harga_beli_terakhir = !empty($harga_pembelian) ? $harga_pembelian[0] : null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $kode_produk = trim($_POST['kode_produk'] ?? '');
    $kategori_id = intval($_POST['kategori_id'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga = floatval($_POST['harga'] ?? 0);
    $harga_diskon = !empty($_POST['harga_diskon']) ? floatval($_POST['harga_diskon']) : null;
    $stok = intval($_POST['stok'] ?? 0);
    $stok_minimal = intval($_POST['stok_minimum'] ?? 0);
    $satuan = trim($_POST['satuan'] ?? '');
    $spesifikasi = trim($_POST['spesifikasi'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    $status = $_POST['status'] ?? 'aktif';
    
    // Validasi input
    if (empty($nama_produk)) {
        $error = 'Nama produk harus diisi.';
    } elseif (empty($kode_produk)) {
        $error = 'Kode produk harus diisi.';
    } elseif ($harga <= 0) {
        $error = 'Harga harus lebih dari 0.';
    } elseif ($stok < 0) {
        $error = 'Stok tidak boleh negatif.';
    } elseif ($harga_diskon !== null && $harga_diskon >= $harga) {
        $error = 'Harga diskon harus lebih kecil dari harga normal.';
    } elseif ($harga_diskon !== null && $harga_diskon < 0) {
        $error = 'Harga diskon tidak boleh negatif.';
    } else {
        try {
            // Cek duplikasi kode produk (kecuali produk ini sendiri)
            $existing = $db->select(
                "SELECT id FROM produk WHERE kode_produk = ? AND id != ? AND status != 'deleted'",
                [$kode_produk, $produk_id]
            );
            
            if (!empty($existing)) {
                $error = 'Kode produk sudah digunakan. Silakan gunakan kode yang berbeda.';
            } else {
                // Handle file upload
                $gambar_filename = $produk['gambar']; // Keep existing image by default
                
                if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/produk/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        // Delete old image if exists
                        if ($produk['gambar'] && file_exists($upload_dir . $produk['gambar'])) {
                            unlink($upload_dir . $produk['gambar']);
                        }
                        
                        $gambar_filename = uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $gambar_filename;
                        
                        if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                            $error = 'Gagal mengupload gambar.';
                        }
                    } else {
                        $error = 'Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.';
                    }
                }
                
                // Handle remove image
                if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                    if ($produk['gambar'] && file_exists($upload_dir . $produk['gambar'])) {
                        unlink($upload_dir . $produk['gambar']);
                    }
                    $gambar_filename = null;
                }
                
                if (empty($error)) {
                    // Update produk
                    $db->execute("
                        UPDATE produk SET 
                            nama_produk = ?, kode_produk = ?, kategori_id = ?, deskripsi = ?, 
                            harga_satuan = ?, harga_diskon = ?, stok_tersedia = ?, stok_minimal = ?, satuan = ?, spesifikasi = ?, 
                            gambar = ?, status = ?, is_featured = ?
                        WHERE id = ?
                    ", [
                        $nama_produk, $kode_produk, $kategori_id ?: null, $deskripsi,
                        $harga, $harga_diskon, $stok, $stok_minimal, $satuan, $spesifikasi,
                        $gambar_filename, $status, $is_featured, $produk_id
                    ]);
                    
                    // Redirect dengan filter status yang sesuai agar produk yang diupdate tetap terlihat
                    header('Location: produk.php?success=updated&status=' . urlencode($status));
                    exit;
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage();
        }
    }
}

// Cek apakah produk pernah digunakan dalam transaksi
$usage_check = $db->select(
    "SELECT COUNT(*) as count FROM transaksi_detail WHERE produk_id = ?",
    [$produk_id]
);
$is_used = $usage_check[0]['count'] > 0;
?>
<?php
$page_title = 'Edit Produk';
require_once 'layouts/header.php';
?>

<!-- Content -->
<div class="ml-0 px-2 sm:px-4 lg:px-6 py-8 max-w-screen-xl">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Edit Produk</h1>
                <p class="text-gray-600 mt-2"><?= htmlspecialchars($produk['nama_produk']) ?></p>
                <nav class="flex mt-3" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="dashboard.php" class="text-gray-700 hover:text-primary-600">
                                <i class="fas fa-home mr-1"></i>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 mx-2 text-sm"></i>
                                <a href="produk.php" class="text-gray-700 hover:text-primary-600">
                                    Produk
                                </a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 mx-2 text-sm"></i>
                                <span class="text-gray-500">Edit Produk</span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Terakhir diubah</p>
                <p class="text-lg font-semibold text-gray-900"><?= date('d/m/Y H:i', strtotime($produk['updated_at'] ?? $produk['created_at'])) ?></p>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    <?php if ($error || $is_used): ?>
    <div class="mb-8">
        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-600 mr-3"></i>
                <div class="flex-1">
                    <p class="text-red-800 font-medium"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($is_used): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                <div class="flex-1">
                    <p class="text-yellow-800 font-medium">Produk ini pernah digunakan dalam transaksi</p>
                    <p class="text-yellow-700 text-sm mt-1">Perubahan harga dan kode produk dapat mempengaruhi laporan historis.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="mb-6">
        <div class="flex flex-wrap gap-3">
            <a href="produk-view.php?id=<?= $produk_id ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-eye mr-2"></i>
                Lihat Detail
            </a>
            <a href="produk.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Kembali ke Daftar
            </a>
            <?php if (AuthStatic::hasRole(['admin'])): ?>
            <button type="button" class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors" 
                    onclick="confirmDelete(<?= $produk_id ?>, '<?= htmlspecialchars($produk['nama_produk']) ?>')">
                <i class="fas fa-trash mr-2"></i>
                Hapus Produk
            </button>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <!-- Informasi Dasar -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    Informasi Dasar
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="nama_produk" class="block text-sm font-medium text-gray-700 mb-2">
                                Nama Produk <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="nama_produk" name="nama_produk" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                   value="<?= htmlspecialchars($produk['nama_produk'] ?? '') ?>" 
                                   placeholder="Contoh: Laptop ASUS VivoBook" required>
                        </div>
                        <div>
                            <label for="kode_produk" class="block text-sm font-medium text-gray-700 mb-2">
                                Kode Produk <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="kode_produk" name="kode_produk" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                   value="<?= htmlspecialchars($produk['kode_produk'] ?? '') ?>" 
                                   placeholder="Contoh: LP-ASUS-001" required>
                            <p class="text-xs text-gray-500 mt-1">Kode unik untuk identifikasi produk</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label for="kategori_id" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                            <select id="kategori_id" name="kategori_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($kategori_list as $kategori): ?>
                                <option value="<?= $kategori['id'] ?>" 
                                        <?= $produk['kategori_id'] == $kategori['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="satuan" class="block text-sm font-medium text-gray-700 mb-2">Satuan</label>
                            <input type="text" id="satuan" name="satuan" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                   value="<?= htmlspecialchars($produk['satuan'] ?? '') ?>" 
                                   placeholder="Contoh: unit, pcs, set">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                  placeholder="Deskripsi singkat tentang produk"><?= htmlspecialchars($produk['deskripsi'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Harga Pembelian dan Profit -->
                <div class="bg-blue-50 p-6 rounded-lg shadow-sm mb-6">
                    <div class="flex items-center mb-6 pb-3 border-b border-blue-200">
                        <i class="fa fa-shopping-cart text-blue-600 mr-2"></i>
                        <h2 class="text-lg font-semibold text-gray-800">Data Pembelian & Perhitungan Profit</h2>
                    </div>
                    
                    <?php if ($harga_beli_terakhir): ?>
                    <!-- Info Harga Pembelian Terakhir -->
                    <div class="bg-white p-4 rounded-lg mb-4">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Harga Pembelian Terakhir</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Harga Beli:</span>
                                <div class="font-semibold text-blue-600">Rp <?= number_format($harga_beli_terakhir['harga_beli'], 0, ',', '.') ?></div>
                            </div>
                            <div>
                                <span class="text-gray-500">Vendor:</span>
                                <div class="font-medium"><?= htmlspecialchars($harga_beli_terakhir['nama_vendor']) ?></div>
                            </div>
                            <div>
                                <span class="text-gray-500">Tanggal:</span>
                                <div class="font-medium"><?= date('d/m/Y', strtotime($harga_beli_terakhir['tanggal_pembelian'])) ?></div>
                            </div>
                            <div>
                                <span class="text-gray-500">No. PO:</span>
                                <div class="font-medium"><?= htmlspecialchars($harga_beli_terakhir['nomor_po']) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Input Manual Harga Beli -->
                    <div class="bg-white p-4 rounded-lg mb-4">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Input Harga Pembelian Manual</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="manual_harga_beli" class="block text-sm font-medium text-gray-700 mb-2">Harga Beli (Rp)</label>
                                <input type="number" id="manual_harga_beli" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       placeholder="0" min="0" step="any" onchange="calculatePrice()">
                                <p class="text-xs text-gray-500 mt-1">Masukkan harga beli untuk kalkulasi profit</p>
                            </div>
                            <div class="flex items-end">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 w-full">
                                    <p class="text-xs text-yellow-700">
                                        <i class="fa fa-info-circle mr-1"></i>
                                        Tidak ada data pembelian dari vendor. Gunakan input manual untuk kalkulasi.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Kalkulator Profit -->
                    <div class="bg-white p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Kalkulator Harga Jual</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="profit_percentage" class="block text-sm font-medium text-gray-700 mb-2">Persentase Profit (%)</label>
                                <input type="number" id="profit_percentage" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       placeholder="30" min="0" step="0.1" onchange="calculatePrice()">
                                <p class="text-xs text-gray-500 mt-1">Masukkan persentase keuntungan yang diinginkan</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Harga Jual Hasil Kalkulasi</label>
                                <div class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-gray-700" id="calculated_price">
                                    Rp 0
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Harga otomatis berdasarkan profit</p>
                            </div>
                            <div>
                                <button type="button" onclick="useCalculatedPrice()" 
                                        class="mt-6 w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                                    <i class="fa fa-arrow-down mr-1"></i> Gunakan Harga Ini
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Harga dan Stok -->
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="flex items-center mb-6 pb-3 border-b border-gray-200">
                        <i class="fa fa-money text-primary mr-2"></i>
                        <h2 class="text-lg font-semibold text-gray-800">Harga dan Stok</h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div>
                            <label for="harga" class="block text-sm font-medium text-gray-700 mb-2">
                                Harga Normal <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">Rp</span>
                                </div>
                                <input type="number" id="harga" name="harga" 
                                       class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                       value="<?= $produk['harga_satuan'] ?? '' ?>" 
                                       placeholder="0" min="0" step="0.01" required>
                            </div>
                            <?php if ($is_used): ?>
                            <p class="text-xs text-yellow-600 mt-1">
                                <i class="fa fa-warning mr-1"></i> Hati-hati mengubah harga produk yang sudah pernah dijual
                            </p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label for="harga_diskon" class="block text-sm font-medium text-gray-700 mb-2">
                                Harga Diskon
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">Rp</span>
                                </div>
                                <input type="number" id="harga_diskon" name="harga_diskon" 
                                       class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                       value="<?= $produk['harga_diskon'] ?? '' ?>" 
                                       placeholder="0" min="0" step="0.01">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ada diskon</p>
                        </div>
                        <div>
                            <label for="stok" class="block text-sm font-medium text-gray-700 mb-2">Stok Saat Ini</label>
                            <input type="number" id="stok" name="stok" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                   value="<?= $produk['stok_tersedia'] ?? '' ?>" 
                                   placeholder="0" min="0">
                            <p class="text-xs text-gray-500 mt-1">Stok fisik yang tersedia saat ini</p>
                        </div>
                        <div>
                            <label for="stok_minimum" class="block text-sm font-medium text-gray-700 mb-2">Stok Minimum</label>
                            <input type="number" id="stok_minimum" name="stok_minimum" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                   value="<?= $produk['stok_minimum'] ?? '' ?>" 
                                   placeholder="10" min="0">
                            <p class="text-xs text-gray-500 mt-1">Batas minimum untuk notifikasi stok rendah</p>
                        </div>
                    </div>
                </div>

                    <!-- Detail Produk -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mt-6">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Detail Produk
                        </h4>
                        
                        <div>
                            <label for="spesifikasi" class="block text-sm font-medium text-gray-700 mb-2">Spesifikasi</label>
                            <textarea id="spesifikasi" name="spesifikasi" rows="4" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                      placeholder="Detail spesifikasi teknis produk"><?= htmlspecialchars($produk['spesifikasi'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Gambar dan Status -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mt-6">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Gambar dan Status
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="gambar" class="block text-sm font-medium text-gray-700 mb-2">Gambar Produk</label>
                                
                                <?php if ($produk['gambar']): ?>
                                <div class="mb-4">
                                    <p class="text-sm text-gray-600 mb-2">Gambar Saat Ini:</p>
                                    <img src="uploads/produk/<?= htmlspecialchars($produk['gambar']) ?>" 
                                         class="w-32 h-32 object-cover rounded-lg border border-gray-300" alt="Gambar Produk">
                                    <label class="flex items-center mt-2">
                                        <input type="checkbox" name="remove_image" value="1" id="remove_image" 
                                               class="rounded border-gray-300 text-red-600 focus:ring-red-500"> 
                                        <span class="ml-2 text-sm text-red-600">Hapus gambar</span>
                                    </label>
                                </div>
                                <?php endif; ?>
                                
                                <input type="file" id="gambar" name="gambar" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif" 
                                       onchange="previewImage(this)">
                                <p class="text-xs text-gray-500 mt-1">Format: JPG, JPEG, PNG, GIF. Maksimal 2MB. Kosongkan jika tidak ingin mengubah gambar.</p>
                                <img id="image-preview" class="w-32 h-32 object-cover rounded-lg border border-gray-300 mt-2" alt="Preview" style="display: none;">
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select id="status" name="status" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="aktif" <?= ($_POST['status'] ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= ($_POST['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                                <?php if ($is_used): ?>
                                <p class="text-xs text-blue-600 mt-1">
                                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                    Produk yang pernah dijual tidak dapat dihapus, hanya dapat dinonaktifkan
                                </p>
                                <?php endif; ?>
                                
                                <!-- Produk Unggulan -->
                                <div class="mt-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_featured" value="1" 
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" 
                                               <?= (!empty($_POST['is_featured']) && $_POST['is_featured'] == '1') ? 'checked' : '' ?>>
                                        <span class="ml-2 text-sm font-medium text-gray-700 flex items-center">
                                            <svg class="w-4 h-4 text-yellow-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                            </svg>
                                            Produk Unggulan
                                        </span>
                                    </label>
                                    <p class="text-xs text-gray-500 mt-1">Produk unggulan akan ditampilkan di halaman promo client</p>
                                </div>
                            </div>
                        </div>
                    </div>

            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-save text-green-600 mr-2"></i>
                    Simpan Perubahan
                </h2>
            </div>
            <div class="p-6">
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Simpan Perubahan
                    </button>
                    <a href="produk-view.php?id=<?= $produk_id ?>" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-eye mr-2"></i>
                        Lihat Detail
                    </a>
                    <a href="produk.php" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali ke Daftar
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeDeleteModal()">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-700">Apakah Anda yakin ingin menghapus produk <strong id="delete-product-name"></strong>?</p>
                    <p class="text-sm text-yellow-600 mt-2">
                        <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        Jika produk pernah digunakan dalam transaksi, produk akan dinonaktifkan. Jika belum pernah digunakan, produk akan dihapus permanen.
                    </p>
                </div>
                <div class="flex justify-end space-x-3">
                    <form method="POST" action="produk.php" id="deleteForm" class="flex space-x-3">
                        <input type="hidden" name="delete_id" id="delete_id">
                        <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors duration-200" onclick="closeDeleteModal()">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors duration-200">Ya, Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }
        
        // Toggle submenu
        function toggleSubmenu(element) {
            const submenu = element.nextElementSibling;
            const icon = element.querySelector('.transform');
            
            if (submenu.classList.contains('hidden')) {
                submenu.classList.remove('hidden');
                icon.classList.add('rotate-90');
            } else {
                submenu.classList.add('hidden');
                icon.classList.remove('rotate-90');
            }
        }
        
        // Preview image function
        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            const removeCheckbox = document.getElementById('remove_image');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    // Uncheck remove image if new image is selected
                    if (removeCheckbox) {
                        removeCheckbox.checked = false;
                    }
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Profit Calculator Functions
        function calculatePrice() {
            const profitPercentage = parseFloat(document.getElementById('profit_percentage').value) || 0;
            
            // Ambil harga beli dari data terakhir atau input manual
            let hargaBeli = <?= $harga_beli_terakhir ? $harga_beli_terakhir['harga_beli'] : 0 ?>;
            
            // Jika tidak ada data pembelian, gunakan input manual
            if (hargaBeli === 0) {
                const manualHargaBeli = document.getElementById('manual_harga_beli');
                if (manualHargaBeli) {
                    hargaBeli = parseFloat(manualHargaBeli.value) || 0;
                }
            }
            
            if (hargaBeli > 0 && profitPercentage >= 0) {
                const hargaJual = hargaBeli * (1 + profitPercentage / 100);
                document.getElementById('calculated_price').textContent = 'Rp ' + formatNumber(Math.round(hargaJual));
            } else {
                document.getElementById('calculated_price').textContent = 'Rp 0';
            }
        }
        
        function useCalculatedPrice() {
            const calculatedText = document.getElementById('calculated_price').textContent;
            const price = calculatedText.replace('Rp ', '').replace(/\./g, '');
            
            if (price && price !== '0') {
                document.getElementById('harga').value = price;
                // Show success message
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fa fa-check mr-1"></i> Berhasil!';
                button.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                button.classList.add('bg-green-600');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('bg-green-600');
                    button.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            } else {
                alert('Silakan masukkan harga beli dan persentase profit terlebih dahulu!');
            }
        }
        
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Modal functions
        function confirmDelete(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete-product-name').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Handle remove image checkbox
        document.addEventListener('DOMContentLoaded', function() {
            const removeImageCheckbox = document.getElementById('remove_image');
            if (removeImageCheckbox) {
                removeImageCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        document.getElementById('gambar').value = '';
                        document.getElementById('image-preview').style.display = 'none';
                    }
                });
            }
            
            // Format harga input
            const hargaInput = document.getElementById('harga');
            if (hargaInput) {
                hargaInput.addEventListener('input', function() {
                    let value = this.value.replace(/[^0-9.]/g, '');
                    this.value = value;
                });
            }
            
            // Validasi form sebelum submit
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const nama = document.getElementById('nama_produk').value.trim();
                    const kode = document.getElementById('kode_produk').value.trim();
                    const harga = parseFloat(document.getElementById('harga').value);
                    
                    if (!nama) {
                        alert('Nama produk harus diisi.');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (!kode) {
                        alert('Kode produk harus diisi.');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (!harga || harga <= 0) {
                        alert('Harga harus diisi dan lebih dari 0.');
                        e.preventDefault();
                        return false;
                    }
                    
                    return true;
                });
            }
        });
    </script>
    
<?php require_once 'layouts/footer.php'; ?>
