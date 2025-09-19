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

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: peralatan.php?error=invalid_id');
    exit;
}

// Ambil data peralatan
$peralatan = $db->select(
    "SELECT * FROM peralatan WHERE id = ?",
    [$id]
);

if (empty($peralatan)) {
    header('Location: peralatan.php?error=not_found');
    exit;
}

$peralatan = $peralatan[0];

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
    
    // Cek duplikasi kode peralatan (kecuali untuk record yang sedang diedit)
    if (empty($errors)) {
        $existing = $db->select(
            "SELECT id FROM peralatan WHERE kode_peralatan = ? AND id != ?",
            [$kode_peralatan, $id]
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
                UPDATE peralatan SET 
                    kode_peralatan = ?, 
                    nama_peralatan = ?, 
                    kategori = ?, 
                    deskripsi = ?, 
                    kondisi = ?,
                    lokasi_penyimpanan = ?, 
                    tanggal_beli = ?, 
                    harga_beli = ?, 
                    masa_garansi = ?, 
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
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
                $status,
                $id
            ];
            
            $db->execute($query, $params);
            
            header('Location: peralatan.php?success=updated');
            exit;
        } catch (Exception $e) {
            $error = 'Gagal memperbarui peralatan: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
} else {
    // Populate form dengan data existing
    $_POST = [
        'kode_peralatan' => $peralatan['kode_peralatan'],
        'nama_peralatan' => $peralatan['nama_peralatan'],
        'kategori' => $peralatan['kategori'],
        'deskripsi' => $peralatan['deskripsi'],
        'kondisi' => $peralatan['kondisi'],
        'lokasi_penyimpanan' => $peralatan['lokasi_penyimpanan'],
        'tanggal_beli' => $peralatan['tanggal_beli'],
        'harga_beli' => $peralatan['harga_beli'] ? number_format($peralatan['harga_beli'], 0, ',', ',') : '',
        'masa_garansi' => $peralatan['masa_garansi'],
        'status' => $peralatan['status']
    ];
}

// Define form_data untuk digunakan di form
$form_data = [
    'kode_peralatan' => $_POST['kode_peralatan'] ?? '',
    'nama_peralatan' => $_POST['nama_peralatan'] ?? '',
    'kategori' => $_POST['kategori'] ?? '',
    'deskripsi' => $_POST['deskripsi'] ?? '',
    'kondisi' => $_POST['kondisi'] ?? '',
    'lokasi_penyimpanan' => $_POST['lokasi_penyimpanan'] ?? '',
    'tanggal_beli' => $_POST['tanggal_beli'] ?? '',
    'harga_beli' => $_POST['harga_beli'] ?? '',
    'masa_garansi' => $_POST['masa_garansi'] ?? '',
    'status' => $_POST['status'] ?? 'tersedia'
];

$page_title = 'Edit Peralatan - ' . $peralatan['nama_peralatan'];
require_once 'layouts/header.php';
?>

<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 mb-1">Edit Peralatan</h1>
            <p class="text-gray-600">Perbarui informasi peralatan</p>
        </div>
        <div class="flex gap-2">
            <a href="peralatan-view.php?id=<?php echo $peralatan['id']; ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-eye mr-2"></i>Lihat Detail
            </a>
            <a href="peralatan.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                <i class="fas fa-arrow-left mr-2"></i>Kembali
            </a>
        </div>
    </div>

    <!-- Breadcrumb -->
    <nav class="flex mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                    <i class="fas fa-home mr-2"></i>Dashboard
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <a href="peralatan.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">Peralatan</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Edit</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Alert Messages -->
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 relative" role="alert">
        <span class="block sm:inline"><?php echo $error; ?></span>
        <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <title>Close</title>
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </span>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Section -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h6 class="text-lg font-semibold text-gray-800">Informasi Peralatan</h6>
                </div>
                <div class="p-6">
                    <form method="POST" id="peralatanForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="kode_peralatan" class="block text-sm font-medium text-gray-700 mb-2">Kode Peralatan <span class="text-red-500">*</span></label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="kode_peralatan" name="kode_peralatan" 
                                       value="<?php echo htmlspecialchars($form_data['kode_peralatan']); ?>" 
                                       placeholder="Contoh: PRL001" required>
                                <p class="mt-1 text-sm text-gray-500">Kode unik untuk identifikasi peralatan</p>
                            </div>
                            <div>
                                <label for="nama_peralatan" class="block text-sm font-medium text-gray-700 mb-2">Nama Peralatan <span class="text-red-500">*</span></label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="nama_peralatan" name="nama_peralatan" 
                                       value="<?php echo htmlspecialchars($form_data['nama_peralatan']); ?>" 
                                       placeholder="Nama peralatan" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="kategori" class="block text-sm font-medium text-gray-700 mb-2">Kategori <span class="text-red-500">*</span></label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="kategori" name="kategori" required>
                                    <option value="">Pilih Kategori</option>
                                    <option value="elektronik" <?php echo $form_data['kategori'] === 'elektronik' ? 'selected' : ''; ?>>Elektronik</option>
                                    <option value="mekanik" <?php echo $form_data['kategori'] === 'mekanik' ? 'selected' : ''; ?>>Mekanik</option>
                                    <option value="komputer" <?php echo $form_data['kategori'] === 'komputer' ? 'selected' : ''; ?>>Komputer</option>
                                    <option value="kendaraan" <?php echo $form_data['kategori'] === 'kendaraan' ? 'selected' : ''; ?>>Kendaraan</option>
                                    <option value="furniture" <?php echo $form_data['kategori'] === 'furniture' ? 'selected' : ''; ?>>Furniture</option>
                                    <option value="lainnya" <?php echo $form_data['kategori'] === 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label for="kondisi" class="block text-sm font-medium text-gray-700 mb-2">Kondisi <span class="text-red-500">*</span></label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="kondisi" name="kondisi" required>
                                    <option value="">Pilih Kondisi</option>
                                    <option value="baik" <?php echo $form_data['kondisi'] === 'baik' ? 'selected' : ''; ?>>Baik</option>
                                    <option value="rusak" <?php echo $form_data['kondisi'] === 'rusak' ? 'selected' : ''; ?>>Rusak</option>
                                    <option value="maintenance" <?php echo $form_data['kondisi'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="deskripsi" name="deskripsi" rows="3" 
                                      placeholder="Deskripsi detail peralatan..."><?php echo htmlspecialchars($form_data['deskripsi']); ?></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="lokasi_penyimpanan" class="block text-sm font-medium text-gray-700 mb-2">Lokasi Penyimpanan <span class="text-red-500">*</span></label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="lokasi_penyimpanan" name="lokasi_penyimpanan" 
                                       value="<?php echo htmlspecialchars($form_data['lokasi_penyimpanan']); ?>" 
                                       placeholder="Contoh: Gudang A, Rak 1" required>
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="status" name="status">
                                    <option value="tersedia" <?php echo $form_data['status'] === 'tersedia' ? 'selected' : ''; ?>>Tersedia</option>
                                    <option value="digunakan" <?php echo $form_data['status'] === 'digunakan' ? 'selected' : ''; ?>>Digunakan</option>
                                    <option value="tidak_tersedia" <?php echo $form_data['status'] === 'tidak_tersedia' ? 'selected' : ''; ?>>Tidak Tersedia</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <label for="tanggal_beli" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Beli</label>
                                <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="tanggal_beli" name="tanggal_beli" 
                                       value="<?php echo htmlspecialchars($form_data['tanggal_beli']); ?>">
                            </div>
                            <div>
                                <label for="harga_beli" class="block text-sm font-medium text-gray-700 mb-2">Harga Beli</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">Rp</span>
                                    </div>
                                    <input type="text" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="harga_beli" name="harga_beli" 
                                           value="<?php echo htmlspecialchars($form_data['harga_beli']); ?>" 
                                           placeholder="0" onkeyup="formatCurrency(this)">
                                </div>
                            </div>
                            <div>
                                <label for="masa_garansi" class="block text-sm font-medium text-gray-700 mb-2">Masa Garansi</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="masa_garansi" name="masa_garansi" 
                                       value="<?php echo htmlspecialchars($form_data['masa_garansi']); ?>" 
                                       placeholder="Contoh: 2 tahun">
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <a href="peralatan.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                <i class="fas fa-times mr-2"></i>Batal
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Info Section -->
        <div class="lg:col-span-1">
            <!-- Current Equipment Info -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h6 class="text-lg font-semibold text-gray-800">Informasi Saat Ini</h6>
                </div>
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-green-500 to-green-700 flex items-center justify-center text-white mb-3 mx-auto">
                            <i class="fas fa-tools fa-2x"></i>
                        </div>
                        <h6 class="text-lg font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($peralatan['nama_peralatan']); ?></h6>
                        <p class="text-gray-500 text-sm mb-2"><?php echo htmlspecialchars($peralatan['kode_peralatan']); ?></p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $peralatan['kondisi'] === 'baik' ? 'bg-green-100 text-green-800' : ($peralatan['kondisi'] === 'rusak' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                            <?php echo ucfirst($peralatan['kondisi']); ?>
                        </span>
                    </div>
                    
                    <div class="space-y-3">
                        <div>
                            <span class="text-sm text-gray-500">Kategori:</span>
                            <p class="text-gray-900 font-medium"><?php echo ucfirst($peralatan['kategori']); ?></p>
                        </div>
                        
                        <div>
                            <span class="text-sm text-gray-500">Lokasi:</span>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($peralatan['lokasi_penyimpanan']); ?></p>
                        </div>
                        
                        <div>
                            <span class="text-sm text-gray-500">Dibuat:</span>
                            <p class="text-gray-900 font-medium"><?php echo date('d/m/Y H:i', strtotime($peralatan['created_at'])); ?></p>
                        </div>
                        
                        <div>
                            <span class="text-sm text-gray-500">Diperbarui:</span>
                            <p class="text-gray-900 font-medium"><?php echo date('d/m/Y H:i', strtotime($peralatan['updated_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panduan Pengisian -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h6 class="text-lg font-semibold text-gray-800">Panduan Pengisian</h6>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <h4 class="text-blue-600 font-medium mb-2">Kode Peralatan</h4>
                        <p class="text-sm text-gray-600 mb-2">Format yang disarankan:</p>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>PRL001 - untuk peralatan umum</li>
                            <li>ELK001 - untuk elektronik</li>
                            <li>MKN001 - untuk mekanik</li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <h4 class="text-blue-600 font-medium mb-2">Status Kondisi</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li><strong>Baik:</strong> Berfungsi normal</li>
                            <li><strong>Rusak:</strong> Tidak berfungsi</li>
                            <li><strong>Maintenance:</strong> Sedang diperbaiki</li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <h4 class="text-blue-600 font-medium mb-2">Status Ketersediaan</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li><strong>Tersedia:</strong> Siap digunakan</li>
                            <li><strong>Digunakan:</strong> Sedang dipakai</li>
                            <li><strong>Tidak Tersedia:</strong> Tidak dapat digunakan</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Statistik Penggunaan -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h6 class="text-lg font-semibold text-gray-800">Statistik Penggunaan</h6>
                </div>
                <div class="p-6">
                    <?php
                    $stats = $db->select("
                        SELECT 
                            COUNT(*) as total_penggunaan,
                            COUNT(CASE WHEN jk.status = 'selesai' THEN 1 END) as penggunaan_selesai
                        FROM jadwal_peralatan jp
                        JOIN jadwal_kunjungan jk ON jp.jadwal_id = jk.id
                        WHERE jp.peralatan_id = ?
                    ", [$id]);
                    
                    $stat = $stats[0] ?? ['total_penggunaan' => 0, 'penggunaan_selesai' => 0];
                    ?>
                    
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-blue-600"><?php echo $stat['total_penggunaan']; ?></div>
                            <div class="text-sm text-gray-600">Total Penggunaan</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-green-600"><?php echo $stat['penggunaan_selesai']; ?></div>
                            <div class="text-sm text-gray-600">Selesai</div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="peralatan-view.php?id=<?php echo $peralatan['id']; ?>" class="inline-flex items-center justify-center w-full px-4 py-2 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-eye mr-2"></i>Lihat Detail Lengkap
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Format currency input
function formatCurrency(input) {
    let value = input.value.replace(/[^\d]/g, '');
    if (value) {
        value = parseInt(value).toLocaleString('id-ID');
    }
    input.value = value;
}

// Form validation
document.getElementById('peralatanForm').addEventListener('submit', function(e) {
    const kode = document.getElementById('kode_peralatan').value;
    const nama = document.getElementById('nama_peralatan').value;
    const kategori = document.getElementById('kategori').value;
    const kondisi = document.getElementById('kondisi').value;
    const lokasi = document.getElementById('lokasi_penyimpanan').value;
    
    if (!kode || !nama || !kategori || !kondisi || !lokasi) {
        e.preventDefault();
        alert('Mohon lengkapi semua field yang wajib diisi!');
        return false;
    }
});

// Warn before leaving if form has changes
let formChanged = false;
const form = document.getElementById('peralatanForm');
const inputs = form.querySelectorAll('input, select, textarea');

inputs.forEach(input => {
    input.addEventListener('change', () => {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

form.addEventListener('submit', () => {
    formChanged = false;
});
</script>

<?php require_once 'layouts/footer.php'; ?>