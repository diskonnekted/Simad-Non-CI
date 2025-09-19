<?php
define('KODE_APP', true);
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

$page_title = 'Buat Jadwal Kunjungan';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validasi input
        $desa_id = intval($_POST['desa_id'] ?? 0);
        $jenis_kunjungan = trim($_POST['jenis_kunjungan'] ?? '');
        $keperluan = trim($_POST['keperluan'] ?? '');
        $tanggal_kunjungan = trim($_POST['tanggal_kunjungan'] ?? '');
        $jam_kunjungan = trim($_POST['jam_kunjungan'] ?? '');
        $urgency = trim($_POST['urgency'] ?? 'normal');
        $catatan_kunjungan = trim($_POST['catatan_kunjungan'] ?? '');
        $estimasi_durasi = intval($_POST['estimasi_durasi'] ?? 0);
        
        // Validasi required fields
        $errors = [];
        
        if ($desa_id <= 0) {
            $errors[] = 'Desa harus dipilih.';
        }
        
        if (empty($jenis_kunjungan)) {
            $errors[] = 'Jenis kunjungan harus dipilih.';
        }
        
        if (empty($keperluan)) {
            $errors[] = 'Keperluan harus diisi.';
        }
        
        if (empty($tanggal_kunjungan)) {
            $errors[] = 'Tanggal kunjungan harus diisi.';
        }
        
        if (empty($jam_kunjungan)) {
            $errors[] = 'Jam kunjungan harus diisi.';
        }
        
        // Validasi tanggal tidak boleh masa lalu
        $datetime_kunjungan = $tanggal_kunjungan . ' ' . $jam_kunjungan;
        if (strtotime($datetime_kunjungan) < time()) {
            $errors[] = 'Tanggal dan jam kunjungan tidak boleh di masa lalu.';
        }
        
        // Cek apakah desa exists
        if ($desa_id > 0) {
            $desa = $db->select("SELECT id FROM desa WHERE id = ? AND status = 'aktif'", [$desa_id]);
            if (empty($desa)) {
                $errors[] = 'Desa tidak ditemukan atau tidak aktif.';
            }
        }
        
        // Field validasi selesai
        
        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }
        
        // Insert jadwal kunjungan
        $jadwal_data = [
            'desa_id' => $desa_id,
            'user_id' => $user['id'],
            'jenis_kunjungan' => $jenis_kunjungan,
            'tanggal_kunjungan' => $tanggal_kunjungan,
            'waktu_mulai' => $jam_kunjungan,
            'catatan_kunjungan' => $catatan_kunjungan,
            'estimasi_durasi' => $estimasi_durasi > 0 ? $estimasi_durasi : null,
            'urgensi' => $urgency,
            'status' => 'dijadwalkan'
        ];
        
        $jadwal_id = $db->insert('jadwal_kunjungan', $jadwal_data);
        
        if ($jadwal_id) {
            // Simpan data produk
            if (!empty($_POST['produk_id'])) {
                foreach ($_POST['produk_id'] as $index => $produk_id) {
                    if (!empty($produk_id)) {
                        $produk_data = [
                            'jadwal_id' => $jadwal_id,
                            'produk_id' => intval($produk_id),
                            'quantity' => intval($_POST['produk_quantity'][$index] ?? 1),
                            'catatan' => trim($_POST['produk_catatan'][$index] ?? '')
                        ];
                        $db->insert('jadwal_produk', $produk_data);
                    }
                }
            }
            
            // Simpan data personal
            if (!empty($_POST['personal_id'])) {
                foreach ($_POST['personal_id'] as $index => $personal_id) {
                    if (!empty($personal_id)) {
                        $personal_data = [
                            'jadwal_id' => $jadwal_id,
                            'user_id' => intval($personal_id),
                            'role_dalam_kunjungan' => trim($_POST['personal_role'][$index] ?? 'teknisi_pendamping'),
                            'catatan' => trim($_POST['personal_catatan'][$index] ?? '')
                        ];
                        $db->insert('jadwal_personal', $personal_data);
                    }
                }
            }
            
            // Simpan data peralatan
            if (!empty($_POST['peralatan_id'])) {
                foreach ($_POST['peralatan_id'] as $index => $peralatan_id) {
                    if (!empty($peralatan_id)) {
                        $peralatan_data = [
                            'jadwal_id' => $jadwal_id,
                            'peralatan_id' => intval($peralatan_id),
                            'quantity' => intval($_POST['peralatan_quantity'][$index] ?? 1),
                            'kondisi_awal' => trim($_POST['peralatan_kondisi'][$index] ?? 'baik'),
                            'catatan' => trim($_POST['peralatan_catatan'][$index] ?? '')
                        ];
                        $db->insert('jadwal_peralatan', $peralatan_data);
                    }
                }
            }
            
            // Simpan data biaya operasional
            if (!empty($_POST['biaya_id'])) {
                        foreach ($_POST['biaya_id'] as $index => $biaya_id) {
                            if (!empty($biaya_id)) {
                                $biaya_data = [
                                    'jadwal_id' => $jadwal_id,
                                    'biaya_operasional_id' => intval($biaya_id),
                                    'quantity' => floatval($_POST['biaya_quantity'][$index] ?? 1),
                                    'harga_satuan' => floatval($_POST['biaya_harga'][$index] ?? 0),
                                    'total_biaya' => floatval($_POST['biaya_total'][$index] ?? 0),
                                    'catatan' => trim($_POST['biaya_catatan'][$index] ?? '')
                                ];
                        $db->insert('jadwal_biaya', $biaya_data);
                    }
                }
            }
            
            header('Location: jadwal-view.php?id=' . $jadwal_id . '&success=' . urlencode('Jadwal kunjungan berhasil dibuat.'));
            exit;
        } else {
            throw new Exception('Gagal menyimpan jadwal kunjungan.');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Ambil daftar desa
$desa_list = $db->select("SELECT id, nama_desa, nama_kepala_desa, no_hp_kepala_desa, nama_sekdes, no_hp_sekdes, nama_admin_it, no_hp_admin_it FROM desa WHERE status = 'aktif' ORDER BY nama_desa");
$teknisi_list = $db->select("SELECT id, nama_lengkap FROM users WHERE role = 'teknisi' AND status = 'aktif' ORDER BY nama_lengkap");

require_once 'layouts/header.php';
?>
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-plus text-primary-600 mr-3"></i>
                                Buat Jadwal Kunjungan
                            </h1>
                            <p class="mt-1 text-sm text-gray-600">Tambah jadwal kunjungan lapangan baru</p>
                        </div>
                        <nav class="flex" aria-label="Breadcrumb">
                            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                                <li><a href="index.php" class="hover:text-gray-700">Dashboard</a></li>
                                <li><i class="fas fa-chevron-right text-xs"></i></li>
                                <li><a href="jadwal.php" class="hover:text-gray-700">Jadwal Kunjungan</a></li>
                                <li><i class="fas fa-chevron-right text-xs"></i></li>
                                <li class="text-gray-900 font-medium">Buat Jadwal</li>
                            </ol>
                        </nav>
                    </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Error Message -->
                <?php if (isset($error_message)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?= $error_message ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" id="jadwalForm" class="space-y-6">
                    <!-- Informasi Dasar -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-6">
                            <i class="fas fa-info-circle text-primary-600 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Informasi Dasar</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="desa_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Desa Tujuan <span class="text-red-500">*</span>
                                </label>
                                <select name="desa_id" id="desa_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                    <option value="">Pilih Desa</option>
                                    <?php foreach ($desa_list as $desa): ?>
                                    <option value="<?= $desa['id'] ?>" 
                                            data-kontak="<?= htmlspecialchars($desa['kontak_person'] ?? '') ?>"
                                            data-telepon="<?= htmlspecialchars($desa['telepon'] ?? '') ?>"
                                            <?= (isset($_POST['desa_id']) && $_POST['desa_id'] == $desa['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($desa['nama_desa']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="desaInfo" class="hidden mt-3 p-3 bg-gray-50 rounded-lg text-sm">
                                    <div class="flex justify-between">
                                        <span class="font-medium">Kontak Person:</span>
                                        <span id="kontakPerson">-</span>
                                    </div>
                                    <div class="flex justify-between mt-1">
                                        <span class="font-medium">Telepon:</span>
                                        <span id="teleponDesa">-</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label for="jenis_kunjungan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Jenis Kunjungan <span class="text-red-500">*</span>
                                </label>
                                <select name="jenis_kunjungan" id="jenis_kunjungan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                                    <option value="">Pilih Jenis Kunjungan</option>
                                    <option value="maintenance" <?= (isset($_POST['jenis_kunjungan']) && $_POST['jenis_kunjungan'] === 'maintenance') ? 'selected' : '' ?>>Maintenance</option>
                                    <option value="instalasi" <?= (isset($_POST['jenis_kunjungan']) && $_POST['jenis_kunjungan'] === 'instalasi') ? 'selected' : '' ?>>Instalasi</option>
                                    <option value="training" <?= (isset($_POST['jenis_kunjungan']) && $_POST['jenis_kunjungan'] === 'training') ? 'selected' : '' ?>>Training</option>
                                    <option value="support" <?= (isset($_POST['jenis_kunjungan']) && $_POST['jenis_kunjungan'] === 'support') ? 'selected' : '' ?>>Support</option>
                                    <option value="pengiriman_barang" <?= (isset($_POST['jenis_kunjungan']) && $_POST['jenis_kunjungan'] === 'pengiriman_barang') ? 'selected' : '' ?>>Pengiriman Barang</option>
                                    <option value="survei_lokasi" <?= (isset($_POST['jenis_kunjungan']) && $_POST['jenis_kunjungan'] === 'survei_lokasi') ? 'selected' : '' ?>>Survei Lokasi</option>
                                    <option value="lainnya" <?= (isset($_POST['jenis_kunjungan']) && $_POST['jenis_kunjungan'] === 'lainnya') ? 'selected' : '' ?>>Lainnya</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label for="keperluan" class="block text-sm font-medium text-gray-700 mb-2">
                                Keperluan/Tujuan Kunjungan <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="keperluan" id="keperluan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                   placeholder="Contoh: Maintenance server, instalasi software, training admin, dll."
                                   value="<?= htmlspecialchars($_POST['keperluan'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- Jadwal & Teknisi -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-6">
                            <i class="fas fa-calendar text-primary-600 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Jadwal & Teknisi</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="tanggal_kunjungan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tanggal Kunjungan <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="tanggal_kunjungan" id="tanggal_kunjungan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                       min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['tanggal_kunjungan'] ?? '') ?>" required>
                            </div>
                            
                            <div>
                                <label for="jam_kunjungan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Jam Kunjungan <span class="text-red-500">*</span>
                                </label>
                                <input type="time" name="jam_kunjungan" id="jam_kunjungan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                       value="<?= htmlspecialchars($_POST['jam_kunjungan'] ?? '09:00') ?>" required>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <div>
                                <label for="estimasi_durasi" class="block text-sm font-medium text-gray-700 mb-2">Estimasi Durasi (menit)</label>
                                <input type="number" name="estimasi_durasi" id="estimasi_durasi" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                       min="0" max="1440" value="<?= htmlspecialchars($_POST['estimasi_durasi'] ?? '120') ?>">
                                <p class="mt-1 text-sm text-gray-500">Perkiraan waktu yang dibutuhkan (opsional)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Tambahan -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-6">
                            <i class="fas fa-cog text-primary-600 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Detail Tambahan</h3>
                        </div>
                        
                        <div class="mb-6">
                            <label for="urgency" class="block text-sm font-medium text-gray-700 mb-2">Tingkat Urgency</label>
                            <select name="urgency" id="urgency" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="rendah" <?= (isset($_POST['urgency']) && $_POST['urgency'] === 'rendah') ? 'selected' : '' ?>>Rendah</option>
                                <option value="normal" <?= (isset($_POST['urgency']) && $_POST['urgency'] === 'normal') ? 'selected' : 'selected' ?>>Normal</option>
                                <option value="tinggi" <?= (isset($_POST['urgency']) && $_POST['urgency'] === 'tinggi') ? 'selected' : '' ?>>Tinggi</option>
                                <option value="urgent" <?= (isset($_POST['urgency']) && $_POST['urgency'] === 'urgent') ? 'selected' : '' ?>>Urgent</option>
                            </select>
                            <div class="mt-2 p-3 bg-gray-50 rounded-lg text-sm text-gray-600">
                                <div class="space-y-1">
                                    <div><span class="font-medium">Rendah:</span> Tidak mendesak, bisa dijadwalkan fleksibel</div>
                                    <div><span class="font-medium">Normal:</span> Kunjungan rutin sesuai jadwal</div>
                                    <div><span class="font-medium">Tinggi:</span> Perlu segera ditangani dalam 1-2 hari</div>
                                    <div><span class="font-medium">Urgent:</span> Harus segera ditangani hari ini</div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="catatan_kunjungan" class="block text-sm font-medium text-gray-700 mb-2">Catatan Kunjungan</label>
                            <textarea name="catatan_kunjungan" id="catatan_kunjungan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" rows="4" 
                                      placeholder="Informasi tambahan, persiapan khusus, atau catatan penting lainnya..."><?= htmlspecialchars($_POST['catatan_kunjungan'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Produk yang Dibawa -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-6">
                            <i class="fas fa-box text-primary-600 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Produk yang Dibawa</h3>
                        </div>
                        
                        <div id="produk-container">
                            <div class="produk-item border border-gray-200 rounded-lg p-4 mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Produk</label>
                                        <select name="produk_id[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                            <option value="">Pilih Produk</option>
                                            <?php
                                            $produk_list = $db->select("SELECT id, nama_produk, harga_satuan, satuan FROM produk WHERE status = 'aktif' ORDER BY nama_produk");
                                            foreach ($produk_list as $produk) {
                                                echo "<option value='{$produk['id']}' data-harga='{$produk['harga_satuan']}' data-satuan='{$produk['satuan']}'>{$produk['nama_produk']} - Rp " . number_format($produk['harga_satuan']) . "/{$produk['satuan']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                                        <input type="number" name="produk_quantity[]" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                                        <input type="text" name="produk_catatan[]" placeholder="Catatan produk..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button type="button" class="remove-produk text-red-600 hover:text-red-800 text-sm font-medium">
                                        <i class="fas fa-trash mr-1"></i>Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" id="add-produk" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                            <i class="fas fa-plus mr-2"></i>
                            Tambah Produk
                        </button>
                    </div>

                    <!-- Personal yang Ditetapkan -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-6">
                            <i class="fas fa-users text-primary-600 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Personal yang Ditetapkan</h3>
                        </div>
                        
                        <div id="personal-container">
                            <div class="personal-item border border-gray-200 rounded-lg p-4 mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Personal</label>
                                        <select name="personal_id[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                            <option value="">Pilih Personal</option>
                                            <?php
                                            $personal_list = $db->select("SELECT id, nama_lengkap, role FROM users WHERE status = 'aktif' ORDER BY nama_lengkap");
                                            foreach ($personal_list as $personal) {
                                                echo "<option value='{$personal['id']}'>{$personal['nama_lengkap']} ({$personal['role']})</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Role dalam Kunjungan</label>
                                        <select name="personal_role[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                            <option value="teknisi_utama">Teknisi Utama</option>
                                            <option value="teknisi_pendamping">Teknisi Pendamping</option>
                                            <option value="sales">Sales</option>
                                            <option value="supervisor">Supervisor</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                                        <input type="text" name="personal_catatan[]" placeholder="Catatan personal..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button type="button" class="remove-personal text-red-600 hover:text-red-800 text-sm font-medium">
                                        <i class="fas fa-trash mr-1"></i>Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" id="add-personal" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <i class="fas fa-plus mr-2"></i>
                            Tambah Personal
                        </button>
                    </div>

                    <!-- Peralatan yang Dibawa -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-6">
                            <i class="fas fa-tools text-primary-600 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Peralatan yang Dibawa</h3>
                        </div>
                        
                        <div id="peralatan-container">
                            <div class="peralatan-item border border-gray-200 rounded-lg p-4 mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Peralatan</label>
                                        <select name="peralatan_id[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                            <option value="">Pilih Peralatan</option>
                                            <?php
                                            $peralatan_list = $db->select("SELECT id, nama_peralatan, kategori, kondisi FROM peralatan WHERE status = 'tersedia' ORDER BY kategori, nama_peralatan");
                                            foreach ($peralatan_list as $peralatan) {
                                                echo "<option value='{$peralatan['id']}'>{$peralatan['nama_peralatan']} ({$peralatan['kategori']}) - {$peralatan['kondisi']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                                        <input type="number" name="peralatan_quantity[]" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Kondisi Awal</label>
                                        <select name="peralatan_kondisi[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                            <option value="baik">Baik</option>
                                            <option value="rusak_ringan">Rusak Ringan</option>
                                            <option value="rusak_berat">Rusak Berat</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                                        <input type="text" name="peralatan_catatan[]" placeholder="Catatan peralatan..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button type="button" class="remove-peralatan text-red-600 hover:text-red-800 text-sm font-medium">
                                        <i class="fas fa-trash mr-1"></i>Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" id="add-peralatan" class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-lg font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors">
                            <i class="fas fa-plus mr-2"></i>
                            Tambah Peralatan
                        </button>
                    </div>

                    <!-- Biaya Operasional -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-6">
                            <i class="fas fa-money-bill-wave text-primary-600 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-900">Biaya Operasional</h3>
                        </div>
                        
                        <div id="biaya-container">
                            <div class="biaya-item border border-gray-200 rounded-lg p-4 mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Biaya</label>
                                        <select name="biaya_id[]" class="biaya-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                            <option value="">Pilih Biaya</option>
                                            <?php
                                            $biaya_list = $db->select("SELECT id, nama_biaya, kategori, tarif_standar, satuan FROM biaya_operasional WHERE status = 'aktif' ORDER BY kategori, nama_biaya");
                                            foreach ($biaya_list as $biaya) {
                                                echo "<option value='{$biaya['id']}' data-tarif='{$biaya['tarif_standar']}' data-satuan='{$biaya['satuan']}'>{$biaya['nama_biaya']} ({$biaya['kategori']}) - Rp " . number_format($biaya['tarif_standar']) . "/{$biaya['satuan']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                                        <input type="number" name="biaya_quantity[]" min="0.1" step="0.1" value="1" class="biaya-quantity w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Harga Satuan</label>
                                        <input type="number" name="biaya_harga[]" min="0" step="1000" class="biaya-harga w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Total</label>
                                        <input type="number" name="biaya_total[]" min="0" class="biaya-total w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                                        <input type="text" name="biaya_catatan[]" placeholder="Catatan biaya..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button type="button" class="remove-biaya text-red-600 hover:text-red-800 text-sm font-medium">
                                        <i class="fas fa-trash mr-1"></i>Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <button type="button" id="add-biaya" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-lg font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Tambah Biaya
                            </button>
                            <div class="text-right">
                                <span class="text-sm text-gray-600">Total Biaya Operasional:</span>
                                <div class="text-lg font-semibold text-gray-900" id="total-biaya-operasional">Rp 0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-primary-600 border border-transparent rounded-lg font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Simpan Jadwal
                            </button>
                            <a href="jadwal.php" class="inline-flex items-center justify-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Kembali
                            </a>
                        </div>
                    </div>
                </form>
    </div>

<script src="js/jquery.min.js"></script>
<script>
        $(document).ready(function() {
            // Show desa info when selected
            $('#desa_id').change(function() {
                var selectedOption = $(this).find('option:selected');
                var kontak = selectedOption.data('kontak') || '-';
                var telepon = selectedOption.data('telepon') || '-';
                
                if ($(this).val()) {
                    $('#kontakPerson').text(kontak);
                    $('#teleponDesa').text(telepon);
                    $('#desaInfo').show();
                } else {
                    $('#desaInfo').hide();
                }
            });
            
            // Trigger change event if desa already selected
            if ($('#desa_id').val()) {
                $('#desa_id').trigger('change');
            }
            
            // Auto-capitalize keperluan
            $('#keperluan').on('input', function() {
                var value = $(this).val();
                if (value.length > 0) {
                    $(this).val(value.charAt(0).toUpperCase() + value.slice(1));
                }
            });
            
            // Set default estimasi durasi based on jenis kunjungan
            $('#jenis_kunjungan').change(function() {
                var jenis = $(this).val();
                var defaultDurasi = {
                    'maintenance': 120,
                    'instalasi': 180,
                    'training': 240,
                    'support': 90
                };
                
                if (jenis && defaultDurasi[jenis] && !$('#estimasi_durasi').val()) {
                    $('#estimasi_durasi').val(defaultDurasi[jenis]);
                }
            });
            
            // Produk Management
            let produkCounter = 1;
            
            $('#add-produk').click(function() {
                const newProduk = $('.produk-item:first').clone();
                newProduk.find('input, select').val('');
                newProduk.find('.produk-harga, .produk-total').val('0');
                $('#produk-container').append(newProduk);
                produkCounter++;
            });
            
            $(document).on('click', '.remove-produk', function() {
                if ($('.produk-item').length > 1) {
                    $(this).closest('.produk-item').remove();
                    updateTotalProduk();
                }
            });
            
            $(document).on('change', '.produk-select', function() {
                const selectedOption = $(this).find('option:selected');
                const harga = selectedOption.data('harga') || 0;
                const container = $(this).closest('.produk-item');
                container.find('.produk-harga').val(harga);
                updateProdukTotal(container);
            });
            
            $(document).on('input', '.produk-quantity, .produk-harga', function() {
                const container = $(this).closest('.produk-item');
                updateProdukTotal(container);
            });
            
            function updateProdukTotal(container) {
                const quantity = parseFloat(container.find('.produk-quantity').val()) || 0;
                const harga = parseFloat(container.find('.produk-harga').val()) || 0;
                const total = quantity * harga;
                container.find('.produk-total').val(total);
                updateTotalProduk();
            }
            
            function updateTotalProduk() {
                let total = 0;
                $('.produk-total').each(function() {
                    total += parseFloat($(this).val()) || 0;
                });
                $('#total-produk').text('Rp ' + total.toLocaleString('id-ID'));
            }

            // Personal Management
            let personalCounter = 1;
            
            $('#add-personal').click(function() {
                const newPersonal = $('.personal-item:first').clone();
                newPersonal.find('input, select').val('');
                $('#personal-container').append(newPersonal);
                personalCounter++;
            });
            
            $(document).on('click', '.remove-personal', function() {
                if ($('.personal-item').length > 1) {
                    $(this).closest('.personal-item').remove();
                }
            });

            // Peralatan Management
            let peralatanCounter = 1;
            
            $('#add-peralatan').click(function() {
                const newPeralatan = $('.peralatan-item:first').clone();
                newPeralatan.find('input, select').val('');
                newPeralatan.find('.peralatan-quantity').val('1');
                $('#peralatan-container').append(newPeralatan);
                peralatanCounter++;
            });
            
            $(document).on('click', '.remove-peralatan', function() {
                if ($('.peralatan-item').length > 1) {
                    $(this).closest('.peralatan-item').remove();
                }
            });

            // Biaya Operasional Management
            let biayaCounter = 1;
            
            $('#add-biaya').click(function() {
                const newBiaya = $('.biaya-item:first').clone();
                newBiaya.find('input, select').val('');
                newBiaya.find('.biaya-quantity').val('1');
                newBiaya.find('.biaya-harga, .biaya-total').val('0');
                $('#biaya-container').append(newBiaya);
                biayaCounter++;
            });
            
            $(document).on('click', '.remove-biaya', function() {
                if ($('.biaya-item').length > 1) {
                    $(this).closest('.biaya-item').remove();
                    updateTotalBiaya();
                }
            });
            
            $(document).on('change', '.biaya-select', function() {
                const selectedOption = $(this).find('option:selected');
                const tarif = selectedOption.data('tarif') || 0;
                const container = $(this).closest('.biaya-item');
                container.find('.biaya-harga').val(tarif);
                updateBiayaTotal(container);
            });
            
            $(document).on('input', '.biaya-quantity, .biaya-harga', function() {
                const container = $(this).closest('.biaya-item');
                updateBiayaTotal(container);
            });
            
            function updateBiayaTotal(container) {
                const quantity = parseFloat(container.find('.biaya-quantity').val()) || 0;
                const harga = parseFloat(container.find('.biaya-harga').val()) || 0;
                const total = quantity * harga;
                container.find('.biaya-total').val(total);
                updateTotalBiaya();
            }
            
            function updateTotalBiaya() {
                let total = 0;
                $('.biaya-total').each(function() {
                    total += parseFloat($(this).val()) || 0;
                });
                $('#total-biaya-operasional').text('Rp ' + total.toLocaleString('id-ID'));
            }
            
            // Initialize totals on page load
            updateTotalProduk();
            updateTotalBiaya();
            
            // Form validation
            $('#jadwalForm').submit(function(e) {
                var tanggal = $('#tanggal_kunjungan').val();
                var jam = $('#jam_kunjungan').val();
                
                if (tanggal && jam) {
                    var selectedDateTime = new Date(tanggal + 'T' + jam);
                    var now = new Date();
                    
                    if (selectedDateTime < now) {
                        e.preventDefault();
                        alert('Tanggal dan jam kunjungan tidak boleh di masa lalu.');
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
</body>
</html>
