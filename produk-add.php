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

$error = '';
$success = '';

// Ambil daftar kategori
$kategori_list = $db->select("
    SELECT id, nama_kategori 
    FROM kategori_produk 
    ORDER BY nama_kategori
");

// Ambil daftar vendor
$vendor_list = $db->select("
    SELECT id, nama_vendor 
    FROM vendor 
    WHERE status = 'aktif'
    ORDER BY nama_vendor
");

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $kode_produk = trim($_POST['kode_produk'] ?? '');
    $kategori_id = intval($_POST['kategori_id'] ?? 0);
    $vendor_id = intval($_POST['vendor_id'] ?? 0);


    $harga = floatval($_POST['harga'] ?? 0);
    // Hitung stok minimum otomatis berdasarkan rata-rata penjualan bulanan
    $stok_minimum = 0; // Default, akan dihitung setelah produk tersimpan
    $satuan = trim($_POST['satuan'] ?? '');
    $spesifikasi = trim($_POST['spesifikasi'] ?? '');
    $garansi = trim($_POST['garansi'] ?? '');
    $status = $_POST['status'] ?? 'aktif';
    
    // Validasi input
    if (empty($nama_produk)) {
        $error = 'Nama produk harus diisi.';
    } elseif (empty($kode_produk)) {
        $error = 'Kode produk harus diisi.';
    } elseif ($harga <= 0) {
        $error = 'Harga harus lebih dari 0.';

    } else {
        try {
            // Cek duplikasi kode produk
            $existing = $db->select(
                "SELECT id FROM produk WHERE kode_produk = ? AND status != 'deleted'",
                [$kode_produk]
            );
            
            if (!empty($existing)) {
                $error = 'Kode produk sudah digunakan. Silakan gunakan kode yang berbeda.';
            } else {
                // Handle file upload
                $gambar_filename = null;
                if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/produk/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $gambar_filename = uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $gambar_filename;
                        
                        if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                            $error = 'Gagal mengupload gambar.';
                        }
                    } else {
                        $error = 'Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.';
                    }
                }
                
                if (empty($error)) {
                    // Insert produk baru
                    $db->execute("
                        INSERT INTO produk (
                            nama_produk, kode_produk, kategori_id, vendor_id, 
                            harga_satuan, stok_minimal, satuan, spesifikasi, 
                            gambar, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $nama_produk, $kode_produk, $kategori_id ?: null, $vendor_id ?: null,
                        $harga, $stok_minimum, $satuan, $spesifikasi,
                        $gambar_filename, $status
                    ]);
                    
                    header('Location: produk.php?success=created');
                    exit;
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage();
        }
    }
}
$page_title = 'Tambah Produk';
require_once 'layouts/header.php';
?>

<style>
    .image-preview {
        max-width: 200px;
        max-height: 200px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 10px;
        display: none;
    }
</style>

<!-- Page Header -->
<div class="bg-white shadow-sm border-b border-gray-200 mb-6">
    <div class="px-6 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-plus text-primary-600 mr-3"></i>
                    Tambah Produk
                </h1>
                <p class="text-sm text-gray-600 mt-1">Tambah barang IT & ATK baru</p>
            </div>
        </div>
        
        <!-- Breadcrumb -->
        <nav class="flex mt-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="index.php" class="text-gray-500 hover:text-primary-600">Dashboard</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <a href="produk.php" class="text-gray-500 hover:text-primary-600">Produk</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <span class="text-gray-500">Tambah Produk</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>
</div>

<!-- Main Container -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="space-y-6">
                <!-- Alert Messages -->
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <div class="flex items-center">
                            <i class="fa fa-exclamation-triangle mr-2"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Informasi Dasar -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center mb-6 pb-3 border-b border-gray-200">
                            <i class="fa fa-info-circle text-blue-600 mr-3"></i>
                            <h2 class="text-lg font-semibold text-gray-900">Informasi Dasar</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nama_produk" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama Produk <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="nama_produk" name="nama_produk" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       value="<?= htmlspecialchars($_POST['nama_produk'] ?? '') ?>" 
                                       placeholder="Contoh: Laptop ASUS VivoBook" required>
                            </div>
                            <div>
                                <label for="kode_produk" class="block text-sm font-medium text-gray-700 mb-2">
                                    Kode Produk <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="kode_produk" name="kode_produk" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       value="<?= htmlspecialchars($_POST['kode_produk'] ?? '') ?>" 
                                       placeholder="Contoh: LP-ASUS-001" required>
                                <p class="text-sm text-gray-500 mt-1">Kode unik untuk identifikasi produk</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                            <div>
                                <label for="kategori_id" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                                <select id="kategori_id" name="kategori_id" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($kategori_list as $kategori): ?>
                                    <option value="<?= $kategori['id'] ?>" 
                                            <?= ($_POST['kategori_id'] ?? '') == $kategori['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="vendor_id" class="block text-sm font-medium text-gray-700 mb-2">Vendor</label>
                                <select id="vendor_id" name="vendor_id" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Pilih Vendor</option>
                                    <?php foreach ($vendor_list as $vendor): ?>
                                    <option value="<?= $vendor['id'] ?>" 
                                            <?= ($_POST['vendor_id'] ?? '') == $vendor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($vendor['nama_vendor']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="satuan" class="block text-sm font-medium text-gray-700 mb-2">Satuan</label>
                                <input type="text" id="satuan" name="satuan" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       value="<?= htmlspecialchars($_POST['satuan'] ?? '') ?>" 
                                       placeholder="Contoh: unit, pcs, set">
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label for="spesifikasi" class="block text-sm font-medium text-gray-700 mb-2">Spesifikasi</label>
                            <textarea id="spesifikasi" name="spesifikasi" rows="4" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                      placeholder="Detail spesifikasi teknis produk"><?= htmlspecialchars($_POST['spesifikasi'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Harga dan Stok -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center mb-6 pb-3 border-b border-gray-200">
                            <i class="fa fa-money text-green-600 mr-3"></i>
                            <h2 class="text-lg font-semibold text-gray-900">Harga dan Stok</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="harga" class="block text-sm font-medium text-gray-700 mb-2">
                                    Harga <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                                    <input type="number" id="harga" name="harga" 
                                           class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                           value="<?= $_POST['harga'] ?? '' ?>" 
                                           placeholder="0" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div>
                                <label for="stok_minimum" class="block text-sm font-medium text-gray-700 mb-2">Stok Minimum</label>
                                <input type="number" id="stok_minimum" name="stok_minimum" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       value="<?= $_POST['stok_minimum'] ?? '' ?>" 
                                       placeholder="Otomatis berdasarkan rata-rata penjualan" readonly>
                                <p class="text-sm text-gray-500 mt-1">Otomatis dihitung berdasarkan rata-rata penjualan bulanan</p>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Produk -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center mb-6 pb-3 border-b border-gray-200">
                            <i class="fa fa-list text-purple-600 mr-3"></i>
                            <h2 class="text-lg font-semibold text-gray-900">Detail Produk</h2>
                        </div>
                        

                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="garansi" class="block text-sm font-medium text-gray-700 mb-2">Garansi</label>
                                <input type="text" id="garansi" name="garansi" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       value="<?= htmlspecialchars($_POST['garansi'] ?? '') ?>" 
                                       placeholder="Contoh: 1 tahun, 6 bulan">
                            </div>

                        </div>
                    </div>

                    <!-- Gambar dan Status -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center mb-6 pb-3 border-b border-gray-200">
                            <i class="fa fa-image text-orange-600 mr-3"></i>
                            <h2 class="text-lg font-semibold text-gray-900">Gambar dan Status</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="gambar" class="block text-sm font-medium text-gray-700 mb-2">Gambar Produk</label>
                                <input type="file" id="gambar" name="gambar" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif" 
                                       onchange="previewImage(this)">
                                <p class="text-sm text-gray-500 mt-1">Format: JPG, JPEG, PNG, GIF. Maksimal 2MB.</p>
                                <img id="image-preview" class="mt-3 max-w-xs h-auto rounded-lg shadow-md hidden" alt="Preview">
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select id="status" name="status" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="aktif" <?= ($_POST['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= ($_POST['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 pt-6">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-200 flex items-center justify-center">
                            <i class="fa fa-save mr-2"></i>
                            Simpan Produk
                        </button>
                        <a href="produk.php" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-6 rounded-lg transition duration-200 flex items-center justify-center">
                            <i class="fa fa-arrow-left mr-2"></i>
                            Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('hidden');
            }
        }
        
        // Auto-generate kode produk based on nama produk
        document.getElementById('nama_produk').addEventListener('input', function() {
            const nama = this.value;
            const kodeInput = document.getElementById('kode_produk');
            if (nama && !kodeInput.value) {
                const kode = nama.toUpperCase()
                    .replace(/[^A-Z0-9\s]/g, '')
                    .replace(/\s+/g, '-')
                    .substring(0, 10);
                kodeInput.value = kode + '-' + Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            }
        });
        
        // Format harga input
        document.getElementById('harga').addEventListener('input', function() {
            let value = this.value.replace(/[^0-9.]/g, '');
            this.value = value;
        });
        
        // Validasi form sebelum submit
        document.querySelector('form').addEventListener('submit', function(e) {
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
    </script>
</div>

<?php require_once 'layouts/footer.php'; ?>
