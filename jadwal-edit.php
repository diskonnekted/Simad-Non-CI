<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan otorisasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'sales'])) {
    header('Location: index.php?error=' . urlencode('Anda tidak memiliki akses ke halaman ini.'));
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Get jadwal ID
$jadwal_id = intval($_GET['id'] ?? 0);
if ($jadwal_id <= 0) {
    header('Location: jadwal.php?error=' . urlencode('ID jadwal tidak valid.'));
    exit;
}

// Get existing jadwal data
$jadwal = $db->select(
    "SELECT * FROM jadwal_kunjungan WHERE id = ?",
    [$jadwal_id]
);

if (empty($jadwal)) {
    header('Location: jadwal.php?error=' . urlencode('Jadwal tidak ditemukan.'));
    exit;
}

$jadwal = $jadwal[0];

// Check authorization
if ($user['role'] === 'sales' && $jadwal['user_id'] != $user['id']) {
    header('Location: jadwal.php?error=' . urlencode('Anda hanya dapat mengedit jadwal yang Anda buat.'));
    exit;
}

// Check if jadwal can be edited
if (!in_array($jadwal['status'], ['dijadwalkan', 'ditunda'])) {
    header('Location: jadwal-view.php?id=' . $jadwal_id . '&error=' . urlencode('Jadwal dengan status "' . $jadwal['status'] . '" tidak dapat diedit.'));
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validasi input
        $desa_id = intval($_POST['desa_id'] ?? 0);
        $jenis_kunjungan = trim($_POST['jenis_kunjungan'] ?? '');
        $tanggal_kunjungan = trim($_POST['tanggal_kunjungan'] ?? '');
        $jam_kunjungan = trim($_POST['jam_kunjungan'] ?? '');
        $catatan_kunjungan = trim($_POST['catatan_kunjungan'] ?? '');
        
        // Validasi required fields
        $errors = [];
        
        if ($desa_id <= 0) {
            $errors[] = 'Desa harus dipilih.';
        }
        
        if (empty($jenis_kunjungan)) {
            $errors[] = 'Jenis kunjungan harus dipilih.';
        }
        

        
        if (empty($tanggal_kunjungan)) {
            $errors[] = 'Tanggal kunjungan harus diisi.';
        }
        
        if (empty($jam_kunjungan)) {
            $errors[] = 'Jam kunjungan harus diisi.';
        }
        
        // Validasi tanggal tidak boleh masa lalu (kecuali untuk admin)
        $datetime_kunjungan = $tanggal_kunjungan . ' ' . $jam_kunjungan;
        if ($user['role'] !== 'admin' && strtotime($datetime_kunjungan) < time()) {
            $errors[] = 'Tanggal dan jam kunjungan tidak boleh di masa lalu.';
        }
        
        // Cek apakah desa exists
        if ($desa_id > 0) {
            $desa = $db->select("SELECT id FROM desa WHERE id = ? AND status = 'aktif'", [$desa_id]);
            if (empty($desa)) {
                $errors[] = 'Desa tidak ditemukan atau tidak aktif.';
            }
        }
        

        
        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }
        
        // Update jadwal kunjungan
        $update_data = [
            'desa_id' => $desa_id,
            'jenis_kunjungan' => $jenis_kunjungan,
            'tanggal_kunjungan' => $tanggal_kunjungan,
            'waktu_mulai' => $jam_kunjungan,
            'catatan_kunjungan' => $catatan_kunjungan,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $success = $db->update('jadwal_kunjungan', $update_data, ['id' => $jadwal_id]);
        
        if ($success) {
            header('Location: jadwal-view.php?id=' . $jadwal_id . '&success=' . urlencode('Jadwal kunjungan berhasil diperbarui.'));
            exit;
        } else {
            throw new Exception('Gagal memperbarui jadwal kunjungan.');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Ambil daftar desa
$desa_list = $db->select("SELECT id, nama_desa, nama_kepala_desa, no_hp_kepala_desa, nama_sekdes, no_hp_sekdes, nama_admin_it, no_hp_admin_it FROM desa WHERE status = 'aktif' ORDER BY nama_desa");

// Prepare form data with fallbacks
$form_data = [
    'desa_id' => $_POST['desa_id'] ?? $jadwal['desa_id'],
    'jenis_kunjungan' => $_POST['jenis_kunjungan'] ?? $jadwal['jenis_kunjungan'],
    'tanggal_kunjungan' => $_POST['tanggal_kunjungan'] ?? date('Y-m-d', strtotime($jadwal['tanggal_kunjungan'])),
    'jam_kunjungan' => $_POST['jam_kunjungan'] ?? ($jadwal['waktu_mulai'] ? date('H:i', strtotime($jadwal['waktu_mulai'])) : '09:00'),
    'catatan_kunjungan' => $_POST['catatan_kunjungan'] ?? $jadwal['catatan_kunjungan']
];
$page_title = 'Edit Jadwal Kunjungan';
require_once 'layouts/header.php';
?>
<style>
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .form-section h4 {
            margin-top: 0;
            color: #007bff;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .required {
            color: #dc3545;
        }
        .form-group label {
            font-weight: 600;
        }
        .desa-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        .urgency-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .datetime-group {
            display: flex;
            gap: 15px;
        }
        .datetime-group .form-group {
            flex: 1;
        }
        .current-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-dijadwalkan { background: #d1ecf1; color: #0c5460; }
        .status-ditunda { background: #fff3cd; color: #856404; }
        @media (max-width: 768px) {
            .datetime-group {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>

<!-- Main Container -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-edit text-primary-600 mr-3"></i>
                    Edit Jadwal Kunjungan
                </h1>
                <p class="mt-2 text-gray-600">Perbarui informasi jadwal kunjungan</p>
            </div>
            <div class="flex space-x-3">
                <a href="jadwal-view.php?id=<?= $jadwal_id ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class="fas fa-eye mr-2"></i>
                    Lihat Detail
                </a>
                <a href="jadwal.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali
                </a>
            </div>
        </div>
    </div>

    <!-- Breadcrumb -->
    <nav class="flex mb-8" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="index.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600">
                    <i class="fas fa-home mr-2"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <a href="jadwal.php" class="text-sm font-medium text-gray-700 hover:text-primary-600">Jadwal Kunjungan</a>
                </div>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <a href="jadwal-view.php?id=<?= $jadwal_id ?>" class="text-sm font-medium text-gray-700 hover:text-primary-600">Detail</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <span class="text-sm font-medium text-gray-500">Edit</span>
                </div>
            </li>
        </ol>
    </nav>
            </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm"><?= $error_message ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Current Info Card -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
        <div class="flex items-center mb-4">
            <i class="fas fa-info-circle text-blue-500 mr-2"></i>
            <h3 class="text-lg font-semibold text-blue-900">Informasi Jadwal Saat Ini</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <div class="flex items-center">
                    <span class="font-medium text-gray-700 w-32">Jenis Kunjungan:</span>
                    <span class="text-gray-900"><?= htmlspecialchars(ucfirst($jadwal['jenis_kunjungan'])) ?></span>
                </div>
                <div class="flex items-center">
                    <span class="font-medium text-gray-700 w-32">Tanggal:</span>
                    <span class="text-gray-900"><?= date('d/m/Y H:i', strtotime($jadwal['tanggal_kunjungan'])) ?></span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex items-center">
                    <span class="font-medium text-gray-700 w-24">Status:</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $jadwal['status'] === 'dijadwalkan' ? 'bg-blue-100 text-blue-800' : ($jadwal['status'] === 'ditunda' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                        <?= ucfirst($jadwal['status']) ?>
                    </span>
                </div>
                <div class="flex items-center">
                    <span class="font-medium text-gray-700 w-24">Dibuat:</span>
                    <span class="text-gray-900"><?= date('d/m/Y H:i', strtotime($jadwal['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" id="jadwalForm" class="space-y-8">
        <!-- Informasi Dasar Card -->
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                    Informasi Dasar
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="desa_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Desa Tujuan <span class="text-red-500">*</span>
                        </label>
                        <select name="desa_id" id="desa_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Pilih Desa</option>
                            <?php foreach ($desa_list as $desa): ?>
                            <option value="<?= $desa['id'] ?>" 
                                    data-kontak="<?= htmlspecialchars($desa['nama_kepala_desa'] ?? '') ?>"
                                    data-telepon="<?= htmlspecialchars($desa['no_hp_kepala_desa'] ?? '') ?>"
                                    <?= $form_data['desa_id'] == $desa['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($desa['nama_desa']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="desaInfo" class="mt-3 p-3 bg-gray-50 rounded-md hidden">
                            <div class="text-sm text-gray-600">
                                <div class="flex items-center mb-1">
                                    <span class="font-medium w-24">Kontak:</span>
                                    <span id="kontakPerson">-</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="font-medium w-24">Telepon:</span>
                                    <span id="teleponDesa">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="jenis_kunjungan" class="block text-sm font-medium text-gray-700 mb-2">
                            Jenis Kunjungan <span class="text-red-500">*</span>
                        </label>
                        <select name="jenis_kunjungan" id="jenis_kunjungan" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Pilih Jenis Kunjungan</option>
                            <option value="maintenance" <?= $form_data['jenis_kunjungan'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="instalasi" <?= $form_data['jenis_kunjungan'] === 'instalasi' ? 'selected' : '' ?>>Instalasi</option>
                            <option value="training" <?= $form_data['jenis_kunjungan'] === 'training' ? 'selected' : '' ?>>Training</option>
                            <option value="support" <?= $form_data['jenis_kunjungan'] === 'support' ? 'selected' : '' ?>>Support</option>
                            <option value="pengiriman_barang" <?= $form_data['jenis_kunjungan'] === 'pengiriman_barang' ? 'selected' : '' ?>>Pengiriman Barang</option>
                            <option value="survei_lokasi" <?= $form_data['jenis_kunjungan'] === 'survei_lokasi' ? 'selected' : '' ?>>Survei Lokasi</option>
                            <option value="lainnya" <?= $form_data['jenis_kunjungan'] === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jadwal & Teknisi Card -->
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-calendar text-green-500 mr-2"></i>
                    Jadwal & Teknisi
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="tanggal_kunjungan" class="block text-sm font-medium text-gray-700 mb-2">
                            Tanggal Kunjungan <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="tanggal_kunjungan" id="tanggal_kunjungan" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               <?= $user['role'] !== 'admin' ? 'min="' . date('Y-m-d') . '"' : '' ?>
                               value="<?= htmlspecialchars($form_data['tanggal_kunjungan']) ?>" required>
                        <?php if ($user['role'] !== 'admin'): ?>
                        <p class="text-sm text-gray-500 mt-1">Tanggal tidak boleh di masa lalu</p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="jam_kunjungan" class="block text-sm font-medium text-gray-700 mb-2">
                            Jam Kunjungan <span class="text-red-500">*</span>
                        </label>
                        <input type="time" name="jam_kunjungan" id="jam_kunjungan" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="<?= htmlspecialchars($form_data['jam_kunjungan']) ?>" required>
                    </div>
                </div>

            </div>
        </div>

        <!-- Detail Tambahan Card -->
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-cog text-purple-500 mr-2"></i>
                    Detail Tambahan
                </h3>
            </div>
            <div class="p-6">
                <div>
                    <label for="catatan_kunjungan" class="block text-sm font-medium text-gray-700 mb-2">
                        Catatan Kunjungan
                    </label>
                    <textarea name="catatan_kunjungan" id="catatan_kunjungan" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                              rows="4" 
                              placeholder="Informasi tambahan, persiapan khusus, atau catatan penting lainnya..."><?= htmlspecialchars($form_data['catatan_kunjungan'] ?? '') ?></textarea>
                    <p class="text-sm text-gray-500 mt-1">Catatan yang sudah ada akan tetap tersimpan</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="p-6">
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>
                        Simpan Perubahan
                    </button>
                    <a href="jadwal-view.php?id=<?= $jadwal_id ?>" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali
                    </a>
                    <a href="jadwal.php" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-list mr-2"></i>
                        Daftar Jadwal
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

    <!-- JavaScript -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle desa selection
            $('#desa_id').change(function() {
                var selectedOption = $(this).find('option:selected');
                var kontak = selectedOption.data('kontak') || '-';
                var telepon = selectedOption.data('telepon') || '-';
                
                if ($(this).val()) {
                    $('#kontakPerson').text(kontak);
                    $('#teleponDesa').text(telepon);
                    $('#desaInfo').removeClass('hidden');
                } else {
                    $('#desaInfo').addClass('hidden');
                }
            });
            
            // Trigger change event if desa already selected
            if ($('#desa_id').val()) {
                $('#desa_id').trigger('change');
            }
            
            // Auto-capitalize catatan kunjungan
            $('#catatan_kunjungan').on('input', function() {
                var value = $(this).val();
                if (value.length > 0) {
                    $(this).val(value.charAt(0).toUpperCase() + value.slice(1));
                }
            });
            
            // Form validation
            $('#jadwalForm').submit(function(e) {
                var tanggal = $('#tanggal_kunjungan').val();
                var jam = $('#jam_kunjungan').val();
                var isAdmin = <?= $user['role'] === 'admin' ? 'true' : 'false' ?>;
                
                if (tanggal && jam && !isAdmin) {
                    var selectedDateTime = new Date(tanggal + 'T' + jam);
                    var now = new Date();
                    
                    if (selectedDateTime < now) {
                        e.preventDefault();
                        alert('Tanggal dan jam kunjungan tidak boleh di masa lalu.');
                        return false;
                    }
                }
                
                // Confirm if making significant changes
                var originalDate = '<?= date('Y-m-d', strtotime($jadwal['tanggal_kunjungan'])) ?>';
                var originalTime = '<?= date('H:i', strtotime($jadwal['tanggal_kunjungan'])) ?>';
                
                if (tanggal !== originalDate || jam !== originalTime) {
                    if (!confirm('Anda mengubah tanggal/waktu kunjungan. Pastikan semua pihak terkait sudah diberitahu. Lanjutkan?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
</div>

<?php require_once 'layouts/footer.php'; ?>
