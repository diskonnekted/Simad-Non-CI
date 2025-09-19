<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek login dan role
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Hanya admin dan sales yang bisa akses
if (!in_array($_SESSION['role'], ['admin', 'sales'])) {
    header('Location: 404.html');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$edit_mode = false;
$vendor = null;
$message = '';
$error = '';

// Cek apakah mode edit
if (isset($_GET['id'])) {
    $edit_mode = true;
    $vendor_id = (int)$_GET['id'];
    
    $query = "SELECT * FROM vendor WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $vendor_id);
    $stmt->execute();
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        header('Location: vendor.php');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_vendor = trim($_POST['kode_vendor']);
    $nama_vendor = trim($_POST['nama_vendor']);
    $nama_perusahaan = trim($_POST['nama_perusahaan']);
    $alamat = trim($_POST['alamat']);
    $kota = trim($_POST['kota']);
    $provinsi = trim($_POST['provinsi']);
    $kode_pos = trim($_POST['kode_pos']);
    $nama_kontak = trim($_POST['nama_kontak']);
    $no_hp = trim($_POST['no_hp']);
    $email = trim($_POST['email']);
    $website = trim($_POST['website']);
    $jenis_vendor = $_POST['jenis_vendor'];
    $kategori_produk = trim($_POST['kategori_produk']);
    $rating = (float)$_POST['rating'];
    $status = $_POST['status'];
    $catatan = trim($_POST['catatan']);
    
    // Validasi
    if (empty($kode_vendor) || empty($nama_vendor) || empty($jenis_vendor)) {
        $error = 'Kode vendor, nama vendor, dan jenis vendor harus diisi.';
    } else {
        // Cek duplikasi kode vendor
        $check_query = "SELECT id FROM vendor WHERE kode_vendor = :kode_vendor";
        if ($edit_mode) {
            $check_query .= " AND id != :current_id";
        }
        
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':kode_vendor', $kode_vendor);
        if ($edit_mode) {
            $check_stmt->bindParam(':current_id', $vendor_id);
        }
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = 'Kode vendor sudah digunakan.';
        } else {
            try {
                if ($edit_mode) {
                    // Update vendor
                    $update_query = "
                        UPDATE vendor SET 
                            kode_vendor = :kode_vendor,
                            nama_vendor = :nama_vendor,
                            nama_perusahaan = :nama_perusahaan,
                            alamat = :alamat,
                            kota = :kota,
                            provinsi = :provinsi,
                            kode_pos = :kode_pos,
                            nama_kontak = :nama_kontak,
                            no_hp = :no_hp,
                            email = :email,
                            website = :website,
                            jenis_vendor = :jenis_vendor,
                            kategori_produk = :kategori_produk,
                            rating = :rating,
                            status = :status,
                            catatan = :catatan,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id
                    ";
                    
                    $stmt = $conn->prepare($update_query);
                    $stmt->bindParam(':id', $vendor_id);
                } else {
                    // Insert vendor baru
                    $insert_query = "
                        INSERT INTO vendor (
                            kode_vendor, nama_vendor, nama_perusahaan, alamat, kota, provinsi, 
                            kode_pos, nama_kontak, no_hp, email, website, jenis_vendor, 
                            kategori_produk, rating, status, catatan
                        ) VALUES (
                            :kode_vendor, :nama_vendor, :nama_perusahaan, :alamat, :kota, :provinsi,
                            :kode_pos, :nama_kontak, :no_hp, :email, :website, :jenis_vendor,
                            :kategori_produk, :rating, :status, :catatan
                        )
                    ";
                    
                    $stmt = $conn->prepare($insert_query);
                }
                
                // Bind parameters
                $stmt->bindParam(':kode_vendor', $kode_vendor);
                $stmt->bindParam(':nama_vendor', $nama_vendor);
                $stmt->bindParam(':nama_perusahaan', $nama_perusahaan);
                $stmt->bindParam(':alamat', $alamat);
                $stmt->bindParam(':kota', $kota);
                $stmt->bindParam(':provinsi', $provinsi);
                $stmt->bindParam(':kode_pos', $kode_pos);
                $stmt->bindParam(':nama_kontak', $nama_kontak);
                $stmt->bindParam(':no_hp', $no_hp);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':website', $website);
                $stmt->bindParam(':jenis_vendor', $jenis_vendor);
                $stmt->bindParam(':kategori_produk', $kategori_produk);
                $stmt->bindParam(':rating', $rating);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':catatan', $catatan);
                
                $stmt->execute();
                
                $message = $edit_mode ? 'Vendor berhasil diupdate.' : 'Vendor berhasil ditambahkan.';
                
                // Redirect setelah sukses
                header('Location: vendor.php?message=' . urlencode($message));
                exit;
                
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Generate kode vendor otomatis jika mode tambah
if (!$edit_mode && empty($_POST)) {
    $query = "SELECT kode_vendor FROM vendor ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $last_vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_vendor) {
        $last_number = (int)substr($last_vendor['kode_vendor'], 3);
        $new_number = $last_number + 1;
        $auto_kode = 'VEN' . str_pad($new_number, 3, '0', STR_PAD_LEFT);
    } else {
        $auto_kode = 'VEN001';
    }
}

require_once 'layouts/header.php'; ?>
<title><?= $edit_mode ? 'Edit' : 'Tambah' ?> Vendor - SIMAD</title>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center">
                        <a href="vendor.php" class="text-gray-600 hover:text-gray-900 mr-4">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900"><?= $edit_mode ? 'Edit' : 'Tambah' ?> Vendor</h1>
                            <p class="text-gray-600"><?= $edit_mode ? 'Ubah data vendor' : 'Tambah vendor baru' ?></p>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <div class="bg-white rounded-lg shadow">
                    <form method="POST" class="p-6">
                        <?php if ($error): ?>
                        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($message): ?>
                        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Kode Vendor -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kode Vendor *</label>
                                <input type="text" name="kode_vendor" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['kode_vendor']) : (isset($_POST['kode_vendor']) ? htmlspecialchars($_POST['kode_vendor']) : $auto_kode) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       required>
                            </div>
                            
                            <!-- Nama Vendor -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Vendor *</label>
                                <input type="text" name="nama_vendor" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['nama_vendor']) : (isset($_POST['nama_vendor']) ? htmlspecialchars($_POST['nama_vendor']) : '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       required>
                            </div>
                            
                            <!-- Nama Perusahaan -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Perusahaan</label>
                                <input type="text" name="nama_perusahaan" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['nama_perusahaan']) : (isset($_POST['nama_perusahaan']) ? htmlspecialchars($_POST['nama_perusahaan']) : '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Jenis Vendor -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Vendor *</label>
                                <select name="jenis_vendor" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="">Pilih Jenis Vendor</option>
                                    <option value="supplier" <?= ($edit_mode && $vendor['jenis_vendor'] == 'supplier') || (isset($_POST['jenis_vendor']) && $_POST['jenis_vendor'] == 'supplier') ? 'selected' : '' ?>>Supplier</option>
                                    <option value="distributor" <?= ($edit_mode && $vendor['jenis_vendor'] == 'distributor') || (isset($_POST['jenis_vendor']) && $_POST['jenis_vendor'] == 'distributor') ? 'selected' : '' ?>>Distributor</option>
                                    <option value="manufacturer" <?= ($edit_mode && $vendor['jenis_vendor'] == 'manufacturer') || (isset($_POST['jenis_vendor']) && $_POST['jenis_vendor'] == 'manufacturer') ? 'selected' : '' ?>>Manufacturer</option>
                                    <option value="reseller" <?= ($edit_mode && $vendor['jenis_vendor'] == 'reseller') || (isset($_POST['jenis_vendor']) && $_POST['jenis_vendor'] == 'reseller') ? 'selected' : '' ?>>Reseller</option>
                                </select>
                            </div>
                            
                            <!-- Nama Kontak -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Kontak</label>
                                <input type="text" name="nama_kontak" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['nama_kontak'] ?? '') : (isset($_POST['nama_kontak']) ? htmlspecialchars($_POST['nama_kontak']) : '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- No HP -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">No. HP</label>
                                <input type="text" name="no_hp" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['no_hp'] ?? '') : (isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" name="email" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['email'] ?? '') : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Website -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Website</label>
                                <input type="url" name="website" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['website'] ?? '') : (isset($_POST['website']) ? htmlspecialchars($_POST['website']) : '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Kota -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kota</label>
                                <input type="text" name="kota" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['kota'] ?? '') : (isset($_POST['kota']) ? htmlspecialchars($_POST['kota']) : '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Provinsi -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Provinsi</label>
                                <input type="text" name="provinsi" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['provinsi'] ?? '') : (isset($_POST['provinsi']) ? htmlspecialchars($_POST['provinsi']) : '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Kode Pos -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Kode Pos</label>
                                <input type="text" name="kode_pos" 
                                       value="<?= $edit_mode ? htmlspecialchars($vendor['kode_pos'] ?? '') : (isset($_POST['kode_pos']) ? htmlspecialchars($_POST['kode_pos']) : '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Rating -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Rating (0.00 - 5.00)</label>
                                <input type="number" name="rating" min="0" max="5" step="0.01" 
                                       value="<?= $edit_mode ? $vendor['rating'] : (isset($_POST['rating']) ? $_POST['rating'] : '0.00') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="aktif" <?= ($edit_mode && $vendor['status'] == 'aktif') || (isset($_POST['status']) && $_POST['status'] == 'aktif') || (!$edit_mode && !isset($_POST['status'])) ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= ($edit_mode && $vendor['status'] == 'nonaktif') || (isset($_POST['status']) && $_POST['status'] == 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Alamat -->
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Alamat</label>
                            <textarea name="alamat" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= $edit_mode ? htmlspecialchars($vendor['alamat'] ?? '') : (isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : '') ?></textarea>
                        </div>
                        
                        <!-- Kategori Produk -->
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Produk yang Disediakan</label>
                            <input type="text" name="kategori_produk" 
                                   value="<?= $edit_mode ? htmlspecialchars($vendor['kategori_produk'] ?? '') : (isset($_POST['kategori_produk']) ? htmlspecialchars($_POST['kategori_produk']) : '') ?>" 
                                   placeholder="Contoh: Komputer, Printer, ATK, dll" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <!-- Catatan -->
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                            <textarea name="catatan" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= $edit_mode ? htmlspecialchars($vendor['catatan'] ?? '') : (isset($_POST['catatan']) ? htmlspecialchars($_POST['catatan']) : '') ?></textarea>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="mt-6 flex justify-end space-x-3">
                            <a href="vendor.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Batal
                            </a>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <?= $edit_mode ? 'Update' : 'Simpan' ?> Vendor
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</div>

<?php require_once 'layouts/footer.php'; ?>