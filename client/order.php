<?php
require_once '../config/database.php';

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['desa_id'])) {
    header('Location: login.php');
    exit;
}

// Ambil data desa
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM desa WHERE id = ?");
    $stmt->execute([$_SESSION['desa_id']]);
    $desa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil kategori produk
    $kategori_stmt = $pdo->prepare("SELECT * FROM kategori_produk ORDER BY nama_kategori");
    $kategori_stmt->execute();
    $kategori_list = $kategori_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil produk aktif
    $produk_stmt = $pdo->prepare("
        SELECT p.*, k.nama_kategori as kategori_nama 
        FROM produk p 
        LEFT JOIN kategori_produk k ON p.kategori_id = k.id 
        WHERE p.status = 'aktif' 
        ORDER BY k.nama_kategori, p.nama_produk
    ");
    $produk_stmt->execute();
    $produk_list = $produk_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil layanan aktif
    $layanan_stmt = $pdo->prepare("SELECT * FROM layanan WHERE status = 'aktif' ORDER BY nama_layanan");
    $layanan_stmt->execute();
    $layanan_list = $layanan_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

$success = '';
$error = '';

// Proses pemesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe_pesanan = $_POST['tipe_pesanan'] ?? '';
    $item_id = $_POST['item_id'] ?? '';
    $jumlah = (int)($_POST['jumlah'] ?? 1);
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');
    $tanggal_dibutuhkan = $_POST['tanggal_dibutuhkan'] ?? '';
    
    // Validasi
    if (empty($tipe_pesanan) || empty($item_id) || $jumlah < 1 || empty($metode_pembayaran)) {
        $error = 'Semua field wajib harus diisi';
    } elseif (!in_array($tipe_pesanan, ['produk', 'layanan'])) {
        $error = 'Tipe pesanan tidak valid';
    } elseif (!in_array($metode_pembayaran, ['cash', 'transfer', 'hutang'])) {
        $error = 'Metode pembayaran tidak valid';
    } else {
        try {
            // Ambil data item
            if ($tipe_pesanan === 'produk') {
                $item_stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ? AND status = 'aktif'");
                $item_stmt->execute([$item_id]);
                $item = $item_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item) {
                    $error = 'Produk tidak ditemukan atau tidak aktif';
                } elseif ($item['stok_tersedia'] < $jumlah) {
                    $error = 'Stok produk tidak mencukupi. Stok tersedia: ' . $item['stok_tersedia'];
                }
            } else {
                $item_stmt = $pdo->prepare("SELECT * FROM layanan WHERE id = ? AND status = 'aktif'");
                $item_stmt->execute([$item_id]);
                $item = $item_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item) {
                    $error = 'Layanan tidak ditemukan atau tidak aktif';
                }
            }
            
            if (!$error && $item) {
                // Validasi harga berdasarkan tipe item
                if ($tipe_pesanan === 'produk') {
                    $harga_satuan = isset($item['harga_satuan']) ? $item['harga_satuan'] : null;
                } else {
                    $harga_satuan = isset($item['harga']) ? $item['harga'] : null;
                }
                
                if (empty($harga_satuan) || $harga_satuan <= 0) {
                    $error = 'Harga item tidak valid atau belum diatur';
                } else {
                    // Hitung total harga
                    $total_harga = $harga_satuan * $jumlah;
                    
                    // Status pembayaran berdasarkan metode
                    $status_pembayaran = ($metode_pembayaran === 'hutang') ? 'hutang' : 'pending';
                    
                    // Generate invoice number
                    $invoice_number = 'INV/' . date('Y/m/') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Insert transaksi header
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO transaksi (
                            nomor_invoice, desa_id, user_id, tanggal_transaksi, jenis_transaksi,
                            metode_pembayaran, total_amount, status_transaksi, status_pembayaran, catatan, created_at
                        ) VALUES (?, ?, 1, CURDATE(), ?, ?, ?, 'draft', ?, ?, NOW())
                    ");
                    
                    $jenis_transaksi = ($tipe_pesanan === 'produk') ? 'barang' : 'layanan';
                    
                    $insert_stmt->execute([
                        $invoice_number,
                        $_SESSION['desa_id'],
                        $jenis_transaksi,
                        $metode_pembayaran,
                        $total_harga,
                        $status_pembayaran,
                        $catatan ?: null
                    ]);
                    
                    $transaksi_id = $pdo->lastInsertId();
                    
                    // Insert transaksi detail
                    if ($tipe_pesanan === 'produk') {
                        $detail_stmt = $pdo->prepare("
                            INSERT INTO transaksi_detail (
                                transaksi_id, produk_id, nama_item, quantity, harga_satuan, subtotal
                            ) VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $detail_stmt->execute([
                            $transaksi_id,
                            $item_id,
                            $item['nama_produk'],
                            $jumlah,
                            $harga_satuan,
                            $total_harga
                        ]);
                    } else {
                        $detail_stmt = $pdo->prepare("
                            INSERT INTO transaksi_detail (
                                transaksi_id, layanan_id, nama_item, quantity, harga_satuan, subtotal
                            ) VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $detail_stmt->execute([
                            $transaksi_id,
                            $item_id,
                            $item['nama_layanan'],
                            $jumlah,
                            $harga_satuan,
                            $total_harga
                        ]);
                    }
                    
                    // Update stok produk jika perlu
                    if ($tipe_pesanan === 'produk') {
                        $update_stok = $pdo->prepare("UPDATE produk SET stok_tersedia = stok_tersedia - ? WHERE id = ?");
                        $update_stok->execute([$jumlah, $item_id]);
                    }
                    
                    $success = 'Pesanan berhasil dibuat dengan nomor invoice: ' . $invoice_number . '. Silakan lanjutkan ke pembayaran.';
                    
                    // Reset form
                    $_POST = [];
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan saat memproses pesanan: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemesanan - Portal Klien Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="dashboard.php" class="bg-white bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center text-white hover:bg-opacity-30 transition duration-200">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-white">Pemesanan</h1>
                        <p class="text-blue-100 text-sm"><?= htmlspecialchars($desa['nama_desa'] ?? '') ?>, <?= htmlspecialchars($desa['kecamatan'] ?? '') ?></p>
                    </div>
                </div>
                
                <a href="dashboard.php" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200">
                    <i class="fas fa-home mr-1"></i>Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-6 py-8">
        <!-- Page Info -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                <i class="fas fa-shopping-cart mr-2 text-green-600"></i>
                Pemesanan Produk & Layanan
            </h2>
            <p class="text-gray-600">
                Pesan produk dan layanan dengan berbagai metode pembayaran yang tersedia.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success) ?>
                <div class="mt-3 space-x-2">
                    <a href="financial.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                        <i class="fas fa-chart-line mr-1"></i>Lihat Status Keuangan
                    </a>
                    <button onclick="location.reload()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-plus mr-1"></i>Pesan Lagi
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Order Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6">
                        <i class="fas fa-clipboard-list mr-2 text-blue-600"></i>
                        Form Pemesanan
                    </h3>
                    
                    <form method="POST" class="space-y-6">
                        <!-- Tipe Pesanan -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Tipe Pesanan</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition duration-200">
                                    <input type="radio" name="tipe_pesanan" value="produk" class="mr-3" required>
                                    <div>
                                        <div class="font-medium text-gray-800">Produk</div>
                                        <div class="text-sm text-gray-600">Barang fisik yang dapat dikirim</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition duration-200">
                                    <input type="radio" name="tipe_pesanan" value="layanan" class="mr-3" required>
                                    <div>
                                        <div class="font-medium text-gray-800">Layanan</div>
                                        <div class="text-sm text-gray-600">Jasa atau layanan yang disediakan</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Kategori Produk Selection (only for produk) -->
                        <div id="kategoriSelection" class="hidden">
                            <label for="kategori_id" class="block text-sm font-medium text-gray-700 mb-2">Pilih Kategori Produk</label>
                            <select id="kategori_id" name="kategori_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($kategori_list as $kategori): ?>
                                    <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Item Selection -->
                        <div id="itemSelection" class="hidden">
                            <label for="item_id" class="block text-sm font-medium text-gray-700 mb-2">Pilih Item</label>
                            <select id="item_id" name="item_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">-- Pilih Item --</option>
                            </select>
                            <div id="itemDetails" class="mt-4 p-4 bg-gray-50 rounded-lg hidden">
                                <!-- Item details will be populated by JavaScript -->
                            </div>
                        </div>

                        <!-- Jumlah -->
                        <div id="jumlahSection" class="hidden">
                            <label for="jumlah" class="block text-sm font-medium text-gray-700 mb-2">Jumlah</label>
                            <input type="number" 
                                   id="jumlah" 
                                   name="jumlah" 
                                   min="1" 
                                   value="1"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   required>
                            <div id="totalHarga" class="mt-2 text-sm text-gray-600">
                                <!-- Total price will be calculated by JavaScript -->
                            </div>
                        </div>

                        <!-- Metode Pembayaran -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Metode Pembayaran</label>
                            <div class="space-y-3">
                                <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition duration-200">
                                    <input type="radio" name="metode_pembayaran" value="cash" class="mr-3" required>
                                    <div class="flex items-center">
                                        <i class="fas fa-money-bill-wave text-green-600 mr-3"></i>
                                        <div>
                                            <div class="font-medium text-gray-800">Cash / Tunai</div>
                                            <div class="text-sm text-gray-600">Pembayaran langsung saat pengiriman</div>
                                        </div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition duration-200">
                                    <input type="radio" name="metode_pembayaran" value="transfer" class="mr-3" required>
                                    <div class="flex items-center">
                                        <i class="fas fa-university text-blue-600 mr-3"></i>
                                        <div>
                                            <div class="font-medium text-gray-800">Transfer Bank</div>
                                            <div class="text-sm text-gray-600">Transfer ke rekening yang ditentukan</div>
                                        </div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition duration-200">
                                    <input type="radio" name="metode_pembayaran" value="hutang" class="mr-3" required>
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar-alt text-orange-600 mr-3"></i>
                                        <div>
                                            <div class="font-medium text-gray-800">Hutang / Kredit</div>
                                            <div class="text-sm text-gray-600">Pembayaran dengan sistem kredit</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Tanggal Dibutuhkan -->
                        <div>
                            <label for="tanggal_dibutuhkan" class="block text-sm font-medium text-gray-700 mb-2">
                                Tanggal Dibutuhkan (Opsional)
                            </label>
                            <input type="date" 
                                   id="tanggal_dibutuhkan" 
                                   name="tanggal_dibutuhkan" 
                                   min="<?= date('Y-m-d') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Catatan -->
                        <div>
                            <label for="catatan" class="block text-sm font-medium text-gray-700 mb-2">
                                Catatan Tambahan (Opsional)
                            </label>
                            <textarea id="catatan" 
                                      name="catatan" 
                                      rows="3"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Catatan khusus untuk pesanan ini..."></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4">
                            <button type="submit" 
                                    class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-200 font-medium">
                                <i class="fas fa-shopping-cart mr-2"></i>Buat Pesanan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Payment Info -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                        Informasi Pembayaran
                    </h3>
                    
                    <div class="space-y-4 text-sm">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-money-bill-wave text-green-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Cash / Tunai</div>
                                <div class="text-gray-600">Bayar langsung saat barang/layanan diterima</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-university text-blue-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Transfer Bank</div>
                                <div class="text-gray-600">Transfer ke rekening yang akan diberikan setelah pemesanan</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-calendar-alt text-orange-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Hutang / Kredit</div>
                                <div class="text-gray-600">Pembayaran dengan sistem kredit sesuai kesepakatan</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
                        Statistik
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Produk:</span>
                            <span class="font-medium text-gray-800"><?= count($produk_list) ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Layanan:</span>
                            <span class="font-medium text-gray-800"><?= count($layanan_list) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-link mr-2 text-teal-600"></i>
                        Tautan Cepat
                    </h3>
                    
                    <div class="space-y-2">
                        <a href="financial.php" class="block text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-chart-line mr-2"></i>Status Keuangan
                        </a>
                        
                        <a href="delivery.php" class="block text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-truck mr-2"></i>Konfirmasi Pengiriman
                        </a>
                        
                        <a href="promo.php" class="block text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-tags mr-2"></i>Promo Produk
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="container mx-auto px-4 py-6">
            <div class="text-center text-gray-600 text-sm">
                © 2025 Portal Klien Desa - Sistem Manajemen Transaksi
            </div>
        </div>
    </footer>

    <script>
        // Data produk dan layanan
        const produkData = <?= json_encode($produk_list) ?>;
        const layananData = <?= json_encode($layanan_list) ?>;
        
        // Handle tipe pesanan change
        document.querySelectorAll('input[name="tipe_pesanan"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const kategoriSelection = document.getElementById('kategoriSelection');
                const itemSelection = document.getElementById('itemSelection');
                const itemSelect = document.getElementById('item_id');
                const kategoriSelect = document.getElementById('kategori_id');
                const jumlahSection = document.getElementById('jumlahSection');
                
                // Clear previous options
                itemSelect.innerHTML = '<option value="">-- Pilih Item --</option>';
                kategoriSelect.value = '';
                
                if (this.value === 'produk') {
                    kategoriSelection.classList.remove('hidden');
                    itemSelection.classList.add('hidden');
                    jumlahSection.classList.add('hidden');
                } else if (this.value === 'layanan') {
                    kategoriSelection.classList.add('hidden');
                    itemSelection.classList.remove('hidden');
                    jumlahSection.classList.remove('hidden');
                    
                    layananData.forEach(layanan => {
                        const option = document.createElement('option');
                        option.value = layanan.id;
                        option.textContent = `${layanan.nama_layanan} - Rp ${parseInt(layanan.harga).toLocaleString('id-ID')}`;
                        option.dataset.item = JSON.stringify(layanan);
                        itemSelect.appendChild(option);
                    });
                }
            });
        });
        
        // Handle kategori change
        document.getElementById('kategori_id').addEventListener('change', function() {
            const itemSelection = document.getElementById('itemSelection');
            const itemSelect = document.getElementById('item_id');
            const jumlahSection = document.getElementById('jumlahSection');
            const kategoriId = this.value;
            
            // Clear previous options
            itemSelect.innerHTML = '<option value="">-- Pilih Produk --</option>';
            
            if (kategoriId) {
                // Filter produk berdasarkan kategori
                const filteredProduk = produkData.filter(produk => produk.kategori_id == kategoriId);
                
                filteredProduk.forEach(produk => {
                    const option = document.createElement('option');
                    option.value = produk.id;
                    option.textContent = `${produk.nama_produk} - Rp ${parseInt(produk.harga_satuan).toLocaleString('id-ID')}`;
                    option.dataset.item = JSON.stringify(produk);
                    itemSelect.appendChild(option);
                });
                
                itemSelection.classList.remove('hidden');
                jumlahSection.classList.remove('hidden');
            } else {
                itemSelection.classList.add('hidden');
                jumlahSection.classList.add('hidden');
            }
        });
        
        // Handle item selection change
        document.getElementById('item_id').addEventListener('change', function() {
            const itemDetails = document.getElementById('itemDetails');
            const jumlahInput = document.getElementById('jumlah');
            
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                const item = JSON.parse(selectedOption.dataset.item);
                const tipePesanan = document.querySelector('input[name="tipe_pesanan"]:checked').value;
                
                let detailsHTML = '';
                if (tipePesanan === 'produk') {
                    const stokTersedia = parseInt(item.stok_tersedia);
                    
                    if (stokTersedia <= 0) {
                        detailsHTML = `
                            <div class="space-y-2">
                                <h4 class="font-semibold text-gray-800">${item.nama_produk}</h4>
                                <p class="text-sm text-gray-600">${item.deskripsi || 'Tidak ada deskripsi'}</p>
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-green-600">Rp ${parseInt(item.harga_satuan).toLocaleString('id-ID')}</span>
                                    <span class="text-sm text-red-600 font-medium">Stok: ${stokTersedia} (Tidak Tersedia)</span>
                                </div>
                                <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded text-sm">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Produk ini sedang tidak tersedia. Silakan pilih produk lain.
                                </div>
                            </div>
                        `;
                        jumlahInput.disabled = true;
                        jumlahInput.value = 0;
                        jumlahInput.removeAttribute('max');
                    } else {
                        detailsHTML = `
                            <div class="space-y-2">
                                <h4 class="font-semibold text-gray-800">${item.nama_produk}</h4>
                                <p class="text-sm text-gray-600">${item.deskripsi || 'Tidak ada deskripsi'}</p>
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-green-600">Rp ${parseInt(item.harga_satuan).toLocaleString('id-ID')}</span>
                                    <span class="text-sm text-gray-600">Stok: ${stokTersedia}</span>
                                </div>
                            </div>
                        `;
                        jumlahInput.disabled = false;
                        jumlahInput.value = 1;
                        jumlahInput.max = stokTersedia;
                    }
                } else {
                    detailsHTML = `
                        <div class="space-y-2">
                            <h4 class="font-semibold text-gray-800">${item.nama_layanan}</h4>
                            <p class="text-sm text-gray-600">${item.deskripsi || 'Tidak ada deskripsi'}</p>
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-green-600">Rp ${parseInt(item.harga).toLocaleString('id-ID')}</span>
                            </div>
                        </div>
                    `;
                    jumlahInput.removeAttribute('max');
                }
                
                itemDetails.innerHTML = detailsHTML;
                itemDetails.classList.remove('hidden');
                
                // Update total harga
                updateTotalHarga();
            } else {
                itemDetails.classList.add('hidden');
            }
        });
        
        // Handle jumlah change
        document.getElementById('jumlah').addEventListener('input', updateTotalHarga);
        
        function updateTotalHarga() {
            const itemSelect = document.getElementById('item_id');
            const jumlahInput = document.getElementById('jumlah');
            const totalHargaDiv = document.getElementById('totalHarga');
            
            if (itemSelect.value && jumlahInput.value && !jumlahInput.disabled) {
                const selectedOption = itemSelect.options[itemSelect.selectedIndex];
                const item = JSON.parse(selectedOption.dataset.item);
                const tipePesanan = document.querySelector('input[name="tipe_pesanan"]:checked').value;
                
                let harga = 0;
                if (tipePesanan === 'produk') {
                    harga = parseInt(item.harga_satuan);
                } else {
                    harga = parseInt(item.harga);
                }
                
                const total = harga * parseInt(jumlahInput.value);
                
                totalHargaDiv.innerHTML = `
                    <strong>Total Harga: Rp ${total.toLocaleString('id-ID')}</strong>
                `;
            } else {
                totalHargaDiv.innerHTML = '';
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const tipePesanan = document.querySelector('input[name="tipe_pesanan"]:checked');
            const itemId = document.getElementById('item_id').value;
            const jumlah = document.getElementById('jumlah').value;
            const jumlahInput = document.getElementById('jumlah');
            const metodePembayaran = document.querySelector('input[name="metode_pembayaran"]:checked');
            
            if (!tipePesanan || !itemId || !jumlah || !metodePembayaran) {
                e.preventDefault();
                alert('Mohon lengkapi semua field yang wajib diisi!');
                return false;
            }
            
            if (parseInt(jumlah) < 1) {
                e.preventDefault();
                alert('Jumlah harus minimal 1!');
                return false;
            }
            
            // Check if product is available (not disabled)
            if (jumlahInput.disabled) {
                e.preventDefault();
                alert('Produk yang dipilih sedang tidak tersedia. Silakan pilih produk lain!');
                return false;
            }
            
            // Additional check for product stock
            if (tipePesanan.value === 'produk' && itemId) {
                const selectedOption = document.getElementById('item_id').options[document.getElementById('item_id').selectedIndex];
                const item = JSON.parse(selectedOption.dataset.item);
                const stokTersedia = parseInt(item.stok_tersedia);
                
                if (stokTersedia <= 0) {
                    e.preventDefault();
                    alert('Produk yang dipilih sedang tidak tersedia. Silakan pilih produk lain!');
                    return false;
                }
                
                if (parseInt(jumlah) > stokTersedia) {
                    e.preventDefault();
                    alert(`Jumlah yang diminta melebihi stok tersedia (${stokTersedia})!`);
                    return false;
                }
            }
        });
    </script>
</body>
</html>