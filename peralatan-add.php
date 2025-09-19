<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'supervisor'])) {
    header('Location: peralatan.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_peralatan = trim($_POST['kode_peralatan'] ?? '');
    $nama_peralatan = trim($_POST['nama_peralatan'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $kondisi = trim($_POST['kondisi'] ?? '');
    $lokasi_penyimpanan = trim($_POST['lokasi_penyimpanan'] ?? '');
    $tanggal_beli = trim($_POST['tanggal_beli'] ?? '');
    $harga_beli = trim($_POST['harga_beli'] ?? '');
    $masa_garansi = trim($_POST['masa_garansi'] ?? '');
    $status = trim($_POST['status'] ?? 'tersedia');
    
    // Validasi input
    $errors = [];
    
    if (empty($kode_peralatan)) {
        $errors[] = 'Kode peralatan harus diisi.';
    }
    
    if (empty($nama_peralatan)) {
        $errors[] = 'Nama peralatan harus diisi.';
    }
    
    if (empty($kategori)) {
        $errors[] = 'Kategori harus dipilih.';
    }
    
    if (empty($kondisi)) {
        $errors[] = 'Kondisi harus dipilih.';
    }
    
    if (empty($lokasi_penyimpanan)) {
        $errors[] = 'Lokasi penyimpanan harus diisi.';
    }
    
    if (!empty($harga_beli) && !is_numeric(str_replace(',', '', $harga_beli))) {
        $errors[] = 'Harga beli harus berupa angka.';
    }
    
    if (!empty($tanggal_beli) && !DateTime::createFromFormat('Y-m-d', $tanggal_beli)) {
        $errors[] = 'Format tanggal beli tidak valid.';
    }
    
    // Cek duplikasi kode peralatan
    if (empty($errors)) {
        $existing = $db->select(
            "SELECT id FROM peralatan WHERE kode_peralatan = ?",
            [$kode_peralatan]
        );
        
        if (!empty($existing)) {
            $errors[] = 'Kode peralatan sudah digunakan.';
        }
    }
    
    if (empty($errors)) {
        try {
            // Convert harga_beli
            $harga_beli_value = !empty($harga_beli) ? floatval(str_replace(',', '', $harga_beli)) : null;
            
            $query = "
                INSERT INTO peralatan (
                    kode_peralatan, nama_peralatan, kategori, deskripsi, kondisi,
                    lokasi_penyimpanan, tanggal_beli, harga_beli, masa_garansi, status,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ";
            
            $params = [
                $kode_peralatan,
                $nama_peralatan,
                $kategori,
                $deskripsi,
                $kondisi,
                $lokasi_penyimpanan,
                !empty($tanggal_beli) ? $tanggal_beli : null,
                $harga_beli_value,
                !empty($masa_garansi) ? $masa_garansi : null,
                $status
            ];
            
            $db->execute($query, $params);
            
            header('Location: peralatan.php?success=added');
            exit;
        } catch (Exception $e) {
            $error = 'Gagal menambahkan peralatan: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$page_title = 'Tambah Peralatan';
require_once 'layouts/header.php';
?>

<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tambah Peralatan</h1>
                <p class="text-gray-600">Tambahkan peralatan kerja baru</p>
            </div>
            <a href="peralatan.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Kembali
            </a>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 relative">
            <span class="block sm:inline"><?php echo $error; ?></span>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Informasi Peralatan</h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" id="peralatanForm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="kode_peralatan" class="block text-sm font-medium text-gray-700 mb-2">Kode Peralatan <span class="text-red-500">*</span></label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="kode_peralatan" name="kode_peralatan" 
                                           value="<?php echo htmlspecialchars($_POST['kode_peralatan'] ?? ''); ?>" 
                                           placeholder="Contoh: PRL001" required>
                                    <p class="text-sm text-gray-500 mt-1">Kode unik untuk identifikasi peralatan</p>
                                </div>
                                <div>
                                    <label for="nama_peralatan" class="block text-sm font-medium text-gray-700 mb-2">Nama Peralatan <span class="text-red-500">*</span></label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="nama_peralatan" name="nama_peralatan" 
                                           value="<?php echo htmlspecialchars($_POST['nama_peralatan'] ?? ''); ?>" 
                                           placeholder="Nama peralatan" required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <label for="kategori" class="block text-sm font-medium text-gray-700 mb-2">Kategori <span class="text-red-500">*</span></label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="kategori" name="kategori" required>
                                        <option value="">Pilih Kategori</option>
                                        <option value="elektronik" <?php echo ($_POST['kategori'] ?? '') === 'elektronik' ? 'selected' : ''; ?>>Elektronik</option>
                                        <option value="mekanik" <?php echo ($_POST['kategori'] ?? '') === 'mekanik' ? 'selected' : ''; ?>>Mekanik</option>
                                        <option value="komputer" <?php echo ($_POST['kategori'] ?? '') === 'komputer' ? 'selected' : ''; ?>>Komputer</option>
                                        <option value="kendaraan" <?php echo ($_POST['kategori'] ?? '') === 'kendaraan' ? 'selected' : ''; ?>>Kendaraan</option>
                                        <option value="furniture" <?php echo ($_POST['kategori'] ?? '') === 'furniture' ? 'selected' : ''; ?>>Furniture</option>
                                        <option value="lainnya" <?php echo ($_POST['kategori'] ?? '') === 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="kondisi" class="block text-sm font-medium text-gray-700 mb-2">Kondisi <span class="text-red-500">*</span></label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="kondisi" name="kondisi" required>
                                        <option value="">Pilih Kondisi</option>
                                        <option value="baik" <?php echo ($_POST['kondisi'] ?? '') === 'baik' ? 'selected' : ''; ?>>Baik</option>
                                        <option value="rusak" <?php echo ($_POST['kondisi'] ?? '') === 'rusak' ? 'selected' : ''; ?>>Rusak</option>
                                        <option value="maintenance" <?php echo ($_POST['kondisi'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-6">
                                <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="deskripsi" name="deskripsi" rows="3" 
                                          placeholder="Deskripsi detail peralatan..."><?php echo htmlspecialchars($_POST['deskripsi'] ?? ''); ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <div>
                                    <label for="lokasi_penyimpanan" class="block text-sm font-medium text-gray-700 mb-2">Lokasi Penyimpanan <span class="text-red-500">*</span></label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="lokasi_penyimpanan" name="lokasi_penyimpanan" 
                                           value="<?php echo htmlspecialchars($_POST['lokasi_penyimpanan'] ?? ''); ?>" 
                                           placeholder="Contoh: Gudang A, Rak 1" required>
                                </div>
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="status" name="status">
                                        <option value="tersedia" <?php echo ($_POST['status'] ?? 'tersedia') === 'tersedia' ? 'selected' : ''; ?>>Tersedia</option>
                                        <option value="digunakan" <?php echo ($_POST['status'] ?? '') === 'digunakan' ? 'selected' : ''; ?>>Digunakan</option>
                                        <option value="tidak_tersedia" <?php echo ($_POST['status'] ?? '') === 'tidak_tersedia' ? 'selected' : ''; ?>>Tidak Tersedia</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                                <div>
                                    <label for="tanggal_beli" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Beli</label>
                                    <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="tanggal_beli" name="tanggal_beli" 
                                           value="<?php echo htmlspecialchars($_POST['tanggal_beli'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label for="harga_beli" class="block text-sm font-medium text-gray-700 mb-2">Harga Beli</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                                        <input type="text" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="harga_beli" name="harga_beli" 
                                               value="<?php echo htmlspecialchars($_POST['harga_beli'] ?? ''); ?>" 
                                               placeholder="0" onkeyup="formatCurrency(this)">
                                    </div>
                                </div>
                                <div>
                                    <label for="masa_garansi" class="block text-sm font-medium text-gray-700 mb-2">Masa Garansi</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="masa_garansi" name="masa_garansi" 
                                           value="<?php echo htmlspecialchars($_POST['masa_garansi'] ?? ''); ?>" 
                                           placeholder="Contoh: 2 tahun">
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 mt-8">
                                <a href="peralatan.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-times mr-2"></i>Batal
                                </a>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-save mr-2"></i>Simpan Peralatan
                                </button>
                            </div>
                    </form>
                </div>
            </div>
        </div>

            <div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Panduan Pengisian</h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-6">
                            <h4 class="text-blue-600 font-medium mb-2">Kode Peralatan</h4>
                            <p class="text-sm text-gray-600 mb-2">Format yang disarankan:</p>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• PRL001 - untuk peralatan umum</li>
                                <li>• ELK001 - untuk elektronik</li>
                                <li>• MKN001 - untuk mekanik</li>
                            </ul>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="text-blue-600 font-medium mb-2">Kategori</h4>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li><strong>Elektronik:</strong> Komputer, printer, dll</li>
                                <li><strong>Mekanik:</strong> Alat-alat mekanik</li>
                                <li><strong>Komputer:</strong> Hardware komputer</li>
                                <li><strong>Kendaraan:</strong> Motor, mobil dinas</li>
                                <li><strong>Furniture:</strong> Meja, kursi, lemari</li>
                            </ul>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="text-blue-600 font-medium mb-2">Status Kondisi</h4>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li><strong>Baik:</strong> Berfungsi normal</li>
                                <li><strong>Rusak:</strong> Tidak berfungsi</li>
                                <li><strong>Maintenance:</strong> Sedang diperbaiki</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function formatCurrency(input) {
    // Remove non-numeric characters except decimal point
    let value = input.value.replace(/[^\d]/g, '');
    
    // Format with thousand separators
    if (value) {
        value = parseInt(value).toLocaleString('id-ID');
    }
    
    input.value = value;
}

// Form validation
document.getElementById('peralatanForm').addEventListener('submit', function(e) {
    const requiredFields = ['kode_peralatan', 'nama_peralatan', 'kategori', 'kondisi', 'lokasi_penyimpanan'];
    let isValid = true;
    
    requiredFields.forEach(function(fieldName) {
        const field = document.getElementById(fieldName);
        if (!field.value.trim()) {
            field.classList.add('border-red-500', 'ring-red-500');
            field.classList.remove('border-gray-300');
            isValid = false;
        } else {
            field.classList.remove('border-red-500', 'ring-red-500');
            field.classList.add('border-gray-300');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Mohon lengkapi semua field yang wajib diisi.');
    }
});

// Reset validation styling on input
document.querySelectorAll('input, select, textarea').forEach(function(field) {
    field.addEventListener('input', function() {
        if (this.value.trim()) {
            this.classList.remove('border-red-500', 'ring-red-500');
            this.classList.add('border-gray-300');
        }
    });
});
</script>

<?php require_once 'layouts/footer.php'; ?>