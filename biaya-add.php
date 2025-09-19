<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'akunting'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_biaya = trim($_POST['kode_biaya'] ?? '');
    $nama_biaya = trim($_POST['nama_biaya'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $tarif_standar = floatval($_POST['tarif_standar'] ?? 0);
    $satuan = trim($_POST['satuan'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    
    // Validasi input
    if (empty($kode_biaya)) {
        $error = 'Kode biaya harus diisi.';
    } elseif (empty($nama_biaya)) {
        $error = 'Nama biaya harus diisi.';
    } elseif (empty($kategori)) {
        $error = 'Kategori harus dipilih.';
    } elseif ($tarif_standar <= 0) {
        $error = 'Tarif standar harus lebih dari 0.';
    } elseif (empty($satuan)) {
        $error = 'Satuan harus diisi.';
    } else {
        try {
            // Cek duplikasi kode biaya
            $existing = $db->select(
                "SELECT id FROM biaya_operasional WHERE kode_biaya = ?",
                [$kode_biaya]
            );
            
            if (!empty($existing)) {
                $error = 'Kode biaya sudah digunakan. Silakan gunakan kode yang berbeda.';
            } else {
                // Insert biaya operasional baru
                $db->execute(
                    "INSERT INTO biaya_operasional (kode_biaya, nama_biaya, kategori, tarif_standar, satuan, deskripsi, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$kode_biaya, $nama_biaya, $kategori, $tarif_standar, $satuan, $deskripsi]
                );
                
                header('Location: biaya.php?success=added');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Gagal menyimpan data biaya operasional. Silakan coba lagi.';
        }
    }
}

$page_title = 'Tambah Biaya Operasional';
require_once 'layouts/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Tambah Biaya Operasional</h1>
            <p class="mt-1 text-sm text-gray-600">Tambahkan biaya operasional baru ke sistem</p>
        </div>
        <a href="biaya.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-800"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button type="button" class="inline-flex bg-red-50 rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-red-50 focus:ring-red-600" onclick="this.parentElement.parentElement.parentElement.parentElement.style.display='none'">
                        <span class="sr-only">Dismiss</span>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Form Section -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Informasi Biaya Operasional</h3>
                </div>
                <div class="p-6">
                    <form method="POST" id="biayaForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="kode_biaya" class="block text-sm font-medium text-gray-700 mb-2">Kode Biaya <span class="text-red-500">*</span></label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="kode_biaya" name="kode_biaya" 
                                       value="<?php echo htmlspecialchars($_POST['kode_biaya'] ?? ''); ?>" 
                                       placeholder="Contoh: BYA001" maxlength="20" required>
                                <p class="mt-1 text-sm text-gray-500">Kode unik untuk identifikasi biaya operasional</p>
                            </div>
                            <div>
                                <label for="kategori" class="block text-sm font-medium text-gray-700 mb-2">Kategori <span class="text-red-500">*</span></label>
                                <select class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="kategori" name="kategori" required>
                                    <option value="">Pilih Kategori</option>
                                    <option value="transportasi" <?php echo ($_POST['kategori'] ?? '') === 'transportasi' ? 'selected' : ''; ?>>Transportasi</option>
                                    <option value="akomodasi" <?php echo ($_POST['kategori'] ?? '') === 'akomodasi' ? 'selected' : ''; ?>>Akomodasi</option>
                                    <option value="konsumsi" <?php echo ($_POST['kategori'] ?? '') === 'konsumsi' ? 'selected' : ''; ?>>Konsumsi</option>
                                    <option value="komunikasi" <?php echo ($_POST['kategori'] ?? '') === 'komunikasi' ? 'selected' : ''; ?>>Komunikasi</option>
                                    <option value="peralatan" <?php echo ($_POST['kategori'] ?? '') === 'peralatan' ? 'selected' : ''; ?>>Peralatan</option>
                                    <option value="administrasi" <?php echo ($_POST['kategori'] ?? '') === 'administrasi' ? 'selected' : ''; ?>>Administrasi</option>
                                    <option value="lainnya" <?php echo ($_POST['kategori'] ?? '') === 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label for="nama_biaya" class="block text-sm font-medium text-gray-700 mb-2">Nama Biaya <span class="text-red-500">*</span></label>
                            <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="nama_biaya" name="nama_biaya" 
                                   value="<?php echo htmlspecialchars($_POST['nama_biaya'] ?? ''); ?>" 
                                   placeholder="Contoh: Biaya Transportasi Kendaraan" maxlength="100" required>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="tarif_standar" class="block text-sm font-medium text-gray-700 mb-2">Tarif Standar <span class="text-red-500">*</span></label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">Rp</span>
                                    </div>
                                    <input type="number" class="w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="tarif_standar" name="tarif_standar" 
                                           value="<?php echo htmlspecialchars($_POST['tarif_standar'] ?? ''); ?>" 
                                           placeholder="0" min="0" step="0.01" required>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Tarif standar per satuan</p>
                            </div>
                            <div>
                                <label for="satuan" class="block text-sm font-medium text-gray-700 mb-2">Satuan <span class="text-red-500">*</span></label>
                                <input type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="satuan" name="satuan" 
                                       value="<?php echo htmlspecialchars($_POST['satuan'] ?? ''); ?>" 
                                       placeholder="Contoh: per km, per hari, per unit" maxlength="20" required>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                            <textarea class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="deskripsi" name="deskripsi" rows="4" 
                                      placeholder="Deskripsi detail tentang biaya operasional ini..."><?php echo htmlspecialchars($_POST['deskripsi'] ?? ''); ?></textarea>
                            <p class="mt-1 text-sm text-gray-500">Penjelasan detail tentang biaya operasional (opsional)</p>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-8">
                            <a href="biaya.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-times mr-2"></i>Batal
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>Simpan Biaya
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="lg:col-span-1">
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-cyan-600">Panduan Pengisian</h3>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-blue-600 mb-2">Kode Biaya</h4>
                        <p class="text-sm text-gray-600 mb-2">Format yang disarankan:</p>
                        <ul class="text-sm text-gray-600 list-disc list-inside space-y-1">
                            <li>BYA001, BYA002, dst.</li>
                            <li>TRP001 (Transportasi)</li>
                            <li>KNS001 (Konsumsi)</li>
                            <li>PRL001 (Peralatan)</li>
                        </ul>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-blue-600 mb-2">Kategori Biaya</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li><span class="font-medium">Transportasi:</span> BBM, sewa kendaraan</li>
                            <li><span class="font-medium">Akomodasi:</span> Hotel, penginapan</li>
                            <li><span class="font-medium">Konsumsi:</span> Makanan, minuman</li>
                            <li><span class="font-medium">Komunikasi:</span> Telepon, internet</li>
                            <li><span class="font-medium">Peralatan:</span> Sewa alat, maintenance</li>
                            <li><span class="font-medium">Administrasi:</span> ATK, dokumen</li>
                            <li><span class="font-medium">Lainnya:</span> Biaya lain-lain</li>
                        </ul>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-blue-600 mb-2">Tarif & Satuan</h4>
                        <p class="text-sm text-gray-600 mb-2">Contoh kombinasi:</p>
                        <ul class="text-sm text-gray-600 list-disc list-inside space-y-1">
                            <li>Rp 5.000 per km</li>
                            <li>Rp 50.000 per hari</li>
                            <li>Rp 25.000 per unit</li>
                            <li>Rp 100.000 per paket</li>
                        </ul>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-800">
                                    <span class="font-medium">Tips:</span> Pastikan kode biaya unik dan mudah diingat untuk memudahkan pencarian dan penggunaan.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview Card -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-green-600">Preview</h3>
                </div>
                <div class="p-6">
                    <div id="preview-content">
                        <p class="text-sm text-gray-500">Preview akan muncul saat Anda mengisi form</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-generate kode biaya
document.getElementById('kategori').addEventListener('change', function() {
    const kategori = this.value;
    const kodeInput = document.getElementById('kode_biaya');
    
    if (kategori && !kodeInput.value) {
        const prefixes = {
            'transportasi': 'TRP',
            'akomodasi': 'AKM',
            'konsumsi': 'KNS',
            'komunikasi': 'KMK',
            'peralatan': 'PRL',
            'administrasi': 'ADM',
            'lainnya': 'BYA'
        };
        
        const prefix = prefixes[kategori] || 'BYA';
        const number = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0');
        kodeInput.value = prefix + number;
    }
    
    updatePreview();
});

// Format currency input
document.getElementById('tarif_standar').addEventListener('input', function() {
    updatePreview();
});

// Update preview
function updatePreview() {
    const kode = document.getElementById('kode_biaya').value;
    const nama = document.getElementById('nama_biaya').value;
    const kategori = document.getElementById('kategori').value;
    const tarif = document.getElementById('tarif_standar').value;
    const satuan = document.getElementById('satuan').value;
    
    const previewContent = document.getElementById('preview-content');
    
    if (kode || nama || kategori || tarif || satuan) {
        const formatRupiah = (amount) => {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(amount || 0);
        };
        
        const getBadgeClass = (kat) => {
            const badges = {
                'transportasi': 'bg-blue-100 text-blue-800',
                'akomodasi': 'bg-purple-100 text-purple-800',
                'konsumsi': 'bg-green-100 text-green-800',
                'komunikasi': 'bg-indigo-100 text-indigo-800',
                'peralatan': 'bg-cyan-100 text-cyan-800',
                'administrasi': 'bg-yellow-100 text-yellow-800',
                'lainnya': 'bg-gray-100 text-gray-800'
            };
            return badges[kat] || 'bg-gray-100 text-gray-800';
        };
        
        previewContent.innerHTML = `
            <div class="mb-3">
                <span class="text-sm font-medium text-gray-700">Kode:</span> <code class="text-sm bg-gray-100 px-2 py-1 rounded">${kode || '-'}</code>
            </div>
            <div class="mb-3">
                <span class="text-sm font-medium text-gray-700">Nama:</span> <span class="text-sm text-gray-900">${nama || '-'}</span>
            </div>
            <div class="mb-3">
                <span class="text-sm font-medium text-gray-700">Kategori:</span> 
                ${kategori ? `<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getBadgeClass(kategori)}">${kategori.charAt(0).toUpperCase() + kategori.slice(1)}</span>` : '<span class="text-sm text-gray-900">-</span>'}
            </div>
            <div class="mb-3">
                <span class="text-sm font-medium text-gray-700">Tarif:</span> <span class="text-sm text-gray-900">${formatRupiah(tarif)} ${satuan ? 'per ' + satuan : ''}</span>
            </div>
        `;
    } else {
        previewContent.innerHTML = '<p class="text-sm text-gray-500">Preview akan muncul saat Anda mengisi form</p>';
    }
}

// Add event listeners for real-time preview
['kode_biaya', 'nama_biaya', 'satuan'].forEach(id => {
    document.getElementById(id).addEventListener('input', updatePreview);
});

// Form validation
document.getElementById('biayaForm').addEventListener('submit', function(e) {
    const kode = document.getElementById('kode_biaya').value.trim();
    const nama = document.getElementById('nama_biaya').value.trim();
    const kategori = document.getElementById('kategori').value;
    const tarif = parseFloat(document.getElementById('tarif_standar').value);
    const satuan = document.getElementById('satuan').value.trim();
    
    if (!kode || !nama || !kategori || !tarif || tarif <= 0 || !satuan) {
        e.preventDefault();
        alert('Mohon lengkapi semua field yang wajib diisi.');
        return false;
    }
    
    if (kode.length < 3) {
        e.preventDefault();
        alert('Kode biaya minimal 3 karakter.');
        return false;
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>