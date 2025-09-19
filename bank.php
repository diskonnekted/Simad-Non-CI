<?php
require_once 'config/auth.php';
require_once 'config/database.php';

// Get database connection
$pdo = getDBConnection();

// Cek akses role
if (!in_array($_SESSION['role'], ['admin', 'finance'])) {
    header('Location: index.php');
    exit;
}

// Handle form submission untuk tambah/edit bank
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode_bank = trim($_POST['kode_bank']);
    $nama_bank = trim($_POST['nama_bank']);
    $jenis_bank = $_POST['jenis_bank'];
    $deskripsi = trim($_POST['deskripsi']);
    $nomor_rekening = trim($_POST['nomor_rekening']);
    $atas_nama = trim($_POST['atas_nama']);
    $status = $_POST['status'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    try {
        if ($id > 0) {
            // Update bank
            $stmt = $pdo->prepare("UPDATE bank SET kode_bank = ?, nama_bank = ?, jenis_bank = ?, deskripsi = ?, nomor_rekening = ?, atas_nama = ?, status = ? WHERE id = ?");
            $stmt->execute([$kode_bank, $nama_bank, $jenis_bank, $deskripsi, $nomor_rekening, $atas_nama, $status, $id]);
            $success_message = "Data bank berhasil diperbarui!";
        } else {
            // Insert bank baru
            $stmt = $pdo->prepare("INSERT INTO bank (kode_bank, nama_bank, jenis_bank, deskripsi, nomor_rekening, atas_nama, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$kode_bank, $nama_bank, $jenis_bank, $deskripsi, $nomor_rekening, $atas_nama, $status]);
            $success_message = "Data bank berhasil ditambahkan!";
        }
    } catch (PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM bank WHERE id = ?");
        $stmt->execute([$id]);
        $success_message = "Data bank berhasil dihapus!";
    } catch (PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM bank WHERE id = ?");
    $stmt->execute([$id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all banks
$stmt = $pdo->query("SELECT * FROM bank ORDER BY nama_bank ASC");
$banks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function getJenisBankBadge($jenis) {
    $badges = [
        'bkk' => 'bg-blue-100 text-blue-800',
        'bank_umum' => 'bg-green-100 text-green-800',
        'cash' => 'bg-yellow-100 text-yellow-800'
    ];
    
    $labels = [
        'bkk' => 'BKK',
        'bank_umum' => 'Bank Umum',
        'cash' => 'Cash'
    ];
    
    $class = $badges[$jenis] ?? 'bg-gray-100 text-gray-800';
    $label = $labels[$jenis] ?? ucfirst($jenis);
    
    return "<span class='px-2 py-1 text-xs font-medium rounded-full {$class}'>{$label}</span>";
}

function getStatusBadge($status) {
    $badges = [
        'aktif' => 'bg-green-100 text-green-800',
        'nonaktif' => 'bg-red-100 text-red-800'
    ];
    
    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800';
    $label = ucfirst($status);
    
    return "<span class='px-2 py-1 text-xs font-medium rounded-full {$class}'>{$label}</span>";
}
$page_title = 'Manajemen Bank';
require_once 'layouts/header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manajemen Bank</h1>
            <p class="text-sm text-gray-600 mt-1">Kelola data bank untuk sistem pembayaran transaksi</p>
        </div>
    </div>
</div>

<!-- Main Container -->
<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">

        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Form Tambah/Edit Bank -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">
                <?= $edit_data ? 'Edit Bank' : 'Tambah Bank Baru' ?>
            </h2>
            
            <form method="POST" class="space-y-6">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Kode Bank -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kode Bank *</label>
                        <input type="text" name="kode_bank" required
                               value="<?= $edit_data ? htmlspecialchars($edit_data['kode_bank']) : '' ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Contoh: BKK001">
                    </div>
                    
                    <!-- Nama Bank -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Bank *</label>
                        <input type="text" name="nama_bank" required
                               value="<?= $edit_data ? htmlspecialchars($edit_data['nama_bank']) : '' ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Nama lengkap bank">
                    </div>
                    
                    <!-- Jenis Bank -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Bank *</label>
                        <select name="jenis_bank" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Pilih Jenis Bank</option>
                            <option value="bkk" <?= ($edit_data && $edit_data['jenis_bank'] == 'bkk') ? 'selected' : '' ?>>BKK</option>
                            <option value="bank_umum" <?= ($edit_data && $edit_data['jenis_bank'] == 'bank_umum') ? 'selected' : '' ?>>Bank Umum</option>
                            <option value="cash" <?= ($edit_data && $edit_data['jenis_bank'] == 'cash') ? 'selected' : '' ?>>Cash</option>
                        </select>
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="aktif" <?= ($edit_data && $edit_data['status'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($edit_data && $edit_data['status'] == 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                    
                    <!-- Nomor Rekening -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Rekening</label>
                        <input type="text" name="nomor_rekening"
                               value="<?= $edit_data ? htmlspecialchars($edit_data['nomor_rekening'] ?? '') : '' ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Nomor rekening bank">
                    </div>
                    
                    <!-- Atas Nama -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Atas Nama</label>
                        <input type="text" name="atas_nama"
                               value="<?= $edit_data ? htmlspecialchars($edit_data['atas_nama'] ?? '') : '' ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Nama pemilik rekening">
                    </div>
                </div>
                
                <!-- Deskripsi -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                    <textarea name="deskripsi" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Deskripsi atau keterangan tambahan"><?= $edit_data ? htmlspecialchars($edit_data['deskripsi'] ?? '') : '' ?></textarea>
                </div>
                
                <!-- Submit Buttons -->
                <div class="flex justify-end space-x-3">
                    <?php if ($edit_data): ?>
                        <a href="bank.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Batal
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <?= $edit_data ? 'Update Bank' : 'Tambah Bank' ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel Data Bank -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Daftar Bank</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Bank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rekening</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($banks)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    Belum ada data bank
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($banks as $bank): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($bank['kode_bank']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($bank['nama_bank']) ?></div>
                                        <?php if ($bank['deskripsi']): ?>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($bank['deskripsi']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= getJenisBankBadge($bank['jenis_bank']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($bank['nomor_rekening']): ?>
                                            <div><?= htmlspecialchars($bank['nomor_rekening']) ?></div>
                                            <?php if ($bank['atas_nama']): ?>
                                                <div class="text-xs text-gray-500">a.n. <?= htmlspecialchars($bank['atas_nama']) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= getStatusBadge($bank['status']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?edit=<?= $bank['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="?delete=<?= $bank['id'] ?>" 
                                           onclick="return confirm('Yakin ingin menghapus bank ini?')"
                                           class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<!-- Main Container End -->
</div>

<?php require_once 'layouts/footer.php'; ?>