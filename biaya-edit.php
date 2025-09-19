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

$biaya_id = intval($_GET['id'] ?? 0);
$error = '';
$success = '';

if (!$biaya_id) {
    header('Location: biaya.php?error=invalid_id');
    exit;
}

// Ambil data biaya operasional
try {
    $biaya = $db->select(
        "SELECT * FROM biaya_operasional WHERE id = ?",
        [$biaya_id]
    );
    
    if (empty($biaya)) {
        header('Location: biaya.php?error=not_found');
        exit;
    }
    
    $biaya = $biaya[0];
} catch (Exception $e) {
    header('Location: biaya.php?error=database_error');
    exit;
}

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
            // Cek duplikasi kode biaya (kecuali untuk biaya yang sedang diedit)
            $existing = $db->select(
                "SELECT id FROM biaya_operasional WHERE kode_biaya = ? AND id != ?",
                [$kode_biaya, $biaya_id]
            );
            
            if (!empty($existing)) {
                $error = 'Kode biaya sudah digunakan. Silakan gunakan kode yang berbeda.';
            } else {
                // Update biaya operasional
                $db->execute(
                    "UPDATE biaya_operasional 
                     SET kode_biaya = ?, nama_biaya = ?, kategori = ?, tarif_standar = ?, satuan = ?, deskripsi = ?, updated_at = NOW()
                     WHERE id = ?",
                    [$kode_biaya, $nama_biaya, $kategori, $tarif_standar, $satuan, $deskripsi, $biaya_id]
                );
                
                header('Location: biaya.php?success=updated');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Gagal memperbarui data biaya operasional. Silakan coba lagi.';
        }
    }
} else {
    // Pre-fill form dengan data existing
    $_POST = [
        'kode_biaya' => $biaya['kode_biaya'],
        'nama_biaya' => $biaya['nama_biaya'],
        'kategori' => $biaya['kategori'],
        'tarif_standar' => $biaya['tarif_standar'],
        'satuan' => $biaya['satuan'],
        'deskripsi' => $biaya['deskripsi']
    ];
}

$page_title = 'Edit Biaya Operasional - ' . $biaya['nama_biaya'];
require_once 'layouts/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Biaya Operasional</h1>
            <p class="mt-1 text-sm text-gray-600">Perbarui informasi biaya operasional</p>
        </div>
        <div class="flex space-x-3">
            <a href="biaya-view.php?id=<?php echo $biaya['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-cyan-600 hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
                <i class="fas fa-eye mr-2"></i>Lihat Detail
            </a>
            <a href="biaya.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i>Kembali
            </a>
        </div>
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
                                    <option value="konsumsi" <?php echo ($_POST['kategori'] ?? '') === 'konsumsi' ? 'selected' : ''; ?>>Konsumsi</option>
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
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">Rp</span>
                                    </div>
                                    <input type="number" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm pl-10" id="tarif_standar" name="tarif_standar" 
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
                            <a href="biaya-view.php?id=<?php echo $biaya['id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-times mr-2"></i>Batal
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Data Lama -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-cyan-600">Data Sebelumnya</h3>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Kode Biaya</label>
                        <div><code class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($biaya['kode_biaya']); ?></code></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Nama Biaya</label>
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($biaya['nama_biaya']); ?></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Kategori</label>
                        <div>
                            <?php
                            $badges = [
                                'transportasi' => 'bg-blue-100 text-blue-800',
                                'konsumsi' => 'bg-green-100 text-green-800',
                                'peralatan' => 'bg-cyan-100 text-cyan-800',
                                'administrasi' => 'bg-yellow-100 text-yellow-800',
                                'lainnya' => 'bg-gray-100 text-gray-800'
                            ];
                            $badge_class = $badges[$biaya['kategori']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $badge_class; ?>">
                                <?php echo ucfirst($biaya['kategori']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Tarif Standar</label>
                        <div class="text-sm font-semibold text-green-600">
                            Rp <?php echo number_format($biaya['tarif_standar'], 0, ',', '.'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Satuan</label>
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($biaya['satuan']); ?></div>
                    </div>
                    
                    <?php if (!empty($biaya['deskripsi'])): ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Deskripsi</label>
                        <div class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($biaya['deskripsi'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Panduan Edit -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-yellow-600">Panduan Edit</h3>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-blue-600 mb-2">Perhatian Penting</h4>
                        <ul class="list-disc list-inside space-y-1 text-sm text-gray-600">
                            <li>Perubahan tarif standar tidak akan mempengaruhi jadwal yang sudah ada</li>
                            <li>Kode biaya harus tetap unik</li>
                            <li>Kategori sebaiknya tidak diubah jika sudah digunakan</li>
                        </ul>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-blue-600 mb-2">Tips Edit</h4>
                        <ul class="list-disc list-inside space-y-1 text-sm text-gray-600">
                            <li>Gunakan preview untuk melihat perubahan</li>
                            <li>Pastikan satuan sesuai dengan tarif</li>
                            <li>Deskripsi yang jelas membantu pengguna lain</li>
                        </ul>
                    </div>
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-800">
                                    <span class="font-medium">Peringatan:</span> Perubahan akan langsung tersimpan dan dapat mempengaruhi perhitungan biaya di jadwal baru.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview Perubahan -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-green-600">Preview Perubahan</h3>
                </div>
                <div class="p-6">
                    <div id="preview-content">
                        <p class="text-sm text-gray-500">Preview akan muncul saat Anda mengubah data</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Data original untuk perbandingan
const originalData = {
    kode_biaya: <?php echo json_encode($biaya['kode_biaya']); ?>,
    nama_biaya: <?php echo json_encode($biaya['nama_biaya']); ?>,
    kategori: <?php echo json_encode($biaya['kategori']); ?>,
    tarif_standar: <?php echo $biaya['tarif_standar']; ?>,
    satuan: <?php echo json_encode($biaya['satuan']); ?>,
    deskripsi: <?php echo json_encode($biaya['deskripsi']); ?>
};

// Update preview
function updatePreview() {
    const kode = document.getElementById('kode_biaya').value;
    const nama = document.getElementById('nama_biaya').value;
    const kategori = document.getElementById('kategori').value;
    const tarif = document.getElementById('tarif_standar').value;
    const satuan = document.getElementById('satuan').value;
    const deskripsi = document.getElementById('deskripsi').value;
    
    const previewContent = document.getElementById('preview-content');
    
    // Cek apakah ada perubahan
    const hasChanges = 
        kode !== originalData.kode_biaya ||
        nama !== originalData.nama_biaya ||
        kategori !== originalData.kategori ||
        parseFloat(tarif) !== originalData.tarif_standar ||
        satuan !== originalData.satuan ||
        deskripsi !== originalData.deskripsi;
    
    if (!hasChanges) {
        previewContent.innerHTML = '<p class="text-sm text-gray-500">Tidak ada perubahan</p>';
        return;
    }
    
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
            'konsumsi': 'bg-green-100 text-green-800',
            'peralatan': 'bg-cyan-100 text-cyan-800',
            'administrasi': 'bg-yellow-100 text-yellow-800',
            'lainnya': 'bg-gray-100 text-gray-800'
        };
        return badges[kat] || 'bg-gray-100 text-gray-800';
    };
    
    let changesHtml = '<div class="text-sm">';
    
    if (kode !== originalData.kode_biaya) {
        changesHtml += `
            <div class="mb-3">
                <span class="font-medium text-gray-700">Kode:</span><br>
                <del class="text-gray-500">${originalData.kode_biaya}</del><br>
                <span class="text-green-600 font-medium">${kode}</span>
            </div>
        `;
    }
    
    if (nama !== originalData.nama_biaya) {
        changesHtml += `
            <div class="mb-3">
                <span class="font-medium text-gray-700">Nama:</span><br>
                <del class="text-gray-500">${originalData.nama_biaya}</del><br>
                <span class="text-green-600 font-medium">${nama}</span>
            </div>
        `;
    }
    
    if (kategori !== originalData.kategori) {
        changesHtml += `
            <div class="mb-3">
                <span class="font-medium text-gray-700">Kategori:</span><br>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">${originalData.kategori}</span><br>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getBadgeClass(kategori)}">${kategori}</span>
            </div>
        `;
    }
    
    if (parseFloat(tarif) !== originalData.tarif_standar) {
        changesHtml += `
            <div class="mb-3">
                <span class="font-medium text-gray-700">Tarif:</span><br>
                <del class="text-gray-500">${formatRupiah(originalData.tarif_standar)}</del><br>
                <span class="text-green-600 font-medium">${formatRupiah(tarif)}</span>
            </div>
        `;
    }
    
    if (satuan !== originalData.satuan) {
        changesHtml += `
            <div class="mb-3">
                <span class="font-medium text-gray-700">Satuan:</span><br>
                <del class="text-gray-500">${originalData.satuan}</del><br>
                <span class="text-green-600 font-medium">${satuan}</span>
            </div>
        `;
    }
    
    if (deskripsi !== originalData.deskripsi) {
        changesHtml += `
            <div class="mb-3">
                <span class="font-medium text-gray-700">Deskripsi:</span><br>
                <span class="text-sm text-gray-500">Deskripsi telah diubah</span>
            </div>
        `;
    }
    
    changesHtml += '</div>';
    
    previewContent.innerHTML = changesHtml;
}

// Add event listeners untuk real-time preview
['kode_biaya', 'nama_biaya', 'kategori', 'tarif_standar', 'satuan', 'deskripsi'].forEach(id => {
    const element = document.getElementById(id);
    if (element) {
        element.addEventListener('input', updatePreview);
        element.addEventListener('change', updatePreview);
    }
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
    
    // Konfirmasi jika ada perubahan signifikan
    const tarif_lama = originalData.tarif_standar;
    const perubahan_tarif = Math.abs(tarif - tarif_lama) / tarif_lama * 100;
    
    if (perubahan_tarif > 20) {
        if (!confirm(`Perubahan tarif cukup besar (${perubahan_tarif.toFixed(1)}%). Apakah Anda yakin ingin melanjutkan?`)) {
            e.preventDefault();
            return false;
        }
    }
});

// Initial preview update
updatePreview();
</script>

<?php require_once 'layouts/footer.php'; ?>