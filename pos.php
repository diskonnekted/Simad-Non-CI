<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = getDatabase();
$user = AuthStatic::getCurrentUser();

// Ambil data untuk dropdown
$desa_list = $db->select("SELECT id, nama_desa, kecamatan FROM desa WHERE status = 'aktif' ORDER BY nama_desa");
$produk_list = $db->select("
    SELECT p.id, p.kode_produk, p.nama_produk, p.harga_satuan, p.stok_tersedia, kp.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori_produk kp ON p.kategori_id = kp.id 
    WHERE p.status = 'aktif' AND p.stok_tersedia > 0 
    ORDER BY p.nama_produk
");
$layanan_list = $db->select("
    SELECT id, kode_layanan, nama_layanan, harga, jenis_layanan 
    FROM layanan 
    WHERE status = 'aktif' 
    ORDER BY nama_layanan
");
$bank_list = $db->select("SELECT id, nama_bank, jenis_bank FROM bank WHERE status = 'aktif' ORDER BY nama_bank");

// Generate nomor invoice baru
function generateInvoiceNumber($db) {
    $today = date('Ymd');
    $prefix = 'INV' . $today;
    
    $last_invoice = $db->select("
        SELECT nomor_invoice 
        FROM transaksi 
        WHERE nomor_invoice LIKE ? 
        ORDER BY nomor_invoice DESC 
        LIMIT 1
    ", [$prefix . '%']);
    
    if (empty($last_invoice)) {
        return $prefix . '001';
    }
    
    $last_number = intval(substr($last_invoice[0]['nomor_invoice'], -3));
    $new_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
    
    return $prefix . $new_number;
}

$invoice_number = generateInvoiceNumber($db);

$page_title = 'Point of Sale (POS)';
// Custom header without sidebar for POS
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $page_title; ?> - Sistem Manajemen Transaksi Desa</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Form Styling Override -->
    <style>
        body input, body select, body textarea, body .form-control,
        form input, form select, form textarea, form .form-control,
        input[type="text"], input[type="url"], input[type="email"], input[type="password"], 
        input[type="date"], input[type="number"], input[type="tel"], 
        select, textarea, .form-control {
            color: #333 !important;
            background-color: #fff !important;
            border: 1px solid #BDC4C9 !important;
        }
        
        body input:focus, body select:focus, body textarea:focus, body .form-control:focus,
        form input:focus, form select:focus, form textarea:focus, form .form-control:focus,
        input:focus, select:focus, textarea:focus, .form-control:focus {
            color: #333 !important;
            background-color: #f7f7f7 !important;
            border-color: #2563eb !important;
            outline: none !important;
        }
        
        body select option, form select option, select option {
            color: #333 !important;
            background-color: #fff !important;
        }
        
        body input::placeholder, body textarea::placeholder,
        form input::placeholder, form textarea::placeholder,
        input::placeholder, textarea::placeholder {
            color: #999 !important;
            opacity: 1 !important;
        }
    </style>
</head>
<body class="h-full bg-gray-50">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <!-- Back Button -->
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 mr-4">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali ke Dashboard
                    </a>
                    
                    <!-- App Title -->
                    <div class="flex flex-col">
                        <div class="text-xl font-bold text-primary-600">SIMAD - POS</div>
                        <div class="text-xs text-primary-500 -mt-1">Point of Sale System</div>
                    </div>
                </div>
                
                <!-- User Info -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <?php if (!empty($user['foto_profil']) && file_exists('uploads/users/' . $user['foto_profil'])): ?>
                                <img class="w-8 h-8 rounded-full object-cover" src="uploads/users/<?php echo htmlspecialchars($user['foto_profil']); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($user['nama_lengkap']); ?>">
                            <?php else: ?>
                                <img class="w-8 h-8 rounded-full" src="img/profileimg.png" alt="user photo">
                            <?php endif; ?>
                        </div>
                        <div class="hidden md:block">
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($user['nama_lengkap'] ?? 'User'); ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?php echo ucfirst($user['role'] ?? 'user'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Logout Button -->
                    <a href="logout.php" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-sign-out-alt mr-1"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
<?php
?>

<style>
.pos-container {
    max-width: 1400px;
    margin: 0 auto;
}

.item-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    background: white;
    transition: all 0.2s;
}

.item-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
}

.cart-item {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 8px;
    background: #f9fafb;
}

.total-section {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-top: 20px;
}

.btn-add-item {
    background: #10b981;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-add-item:hover {
    background: #059669;
}

.btn-remove {
    background: #ef4444;
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
}

.btn-remove:hover {
    background: #dc2626;
}

.payment-method-card {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    margin: 8px 0;
    cursor: pointer;
    transition: all 0.2s;
}

.payment-method-card.selected {
    border-color: #3b82f6;
    background: #eff6ff;
}

.payment-method-card:hover {
    border-color: #3b82f6;
}
</style>

<div class="pos-container px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Point of Sale</h1>
                <p class="text-gray-600 mt-1">Sistem penjualan cepat dan mudah</p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-500">Invoice Number</div>
                <div class="text-xl font-bold text-blue-600" id="invoice-number"><?= $invoice_number ?></div>
                <div class="text-sm text-gray-500"><?= date('d/m/Y H:i') ?></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Produk & Layanan -->
        <div class="lg:col-span-2">
            <!-- Customer Selection -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-map-marker-alt text-blue-600 mr-2"></i>
                    Pilih Desa (Customer)
                </h3>
                <select id="desa-select" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Pilih Desa --</option>
                    <?php foreach ($desa_list as $desa): ?>
                    <option value="<?= $desa['id'] ?>" data-kecamatan="<?= htmlspecialchars($desa['kecamatan']) ?>">
                        <?= htmlspecialchars($desa['nama_desa']) ?> - <?= htmlspecialchars($desa['kecamatan']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tabs untuk Produk dan Layanan -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <button class="tab-btn active py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600" data-tab="produk">
                            <i class="fas fa-box mr-2"></i>Produk
                        </button>
                        <button class="tab-btn py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700" data-tab="layanan">
                            <i class="fas fa-cogs mr-2"></i>Layanan
                        </button>
                    </nav>
                </div>

                <!-- Tab Content Produk -->
                <div id="tab-produk" class="tab-content p-6">
                    <div class="mb-4">
                        <input type="text" id="search-produk" placeholder="Cari produk..." class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div id="produk-list" class="max-h-96 overflow-y-auto">
                        <?php foreach ($produk_list as $produk): ?>
                        <div class="item-card produk-item" data-search="<?= strtolower($produk['nama_produk'] . ' ' . $produk['kode_produk']) ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($produk['nama_produk']) ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?= htmlspecialchars($produk['kode_produk']) ?> | 
                                        <?= htmlspecialchars($produk['nama_kategori'] ?? 'Tanpa Kategori') ?> | 
                                        Stok: <?= $produk['stok_tersedia'] ?>
                                    </div>
                                    <div class="text-lg font-semibold text-blue-600">Rp <?= number_format($produk['harga_satuan'], 0, ',', '.') ?></div>
                                </div>
                                <button class="btn-add-item" onclick="addToCart('produk', <?= $produk['id'] ?>, '<?= htmlspecialchars($produk['nama_produk']) ?>', <?= $produk['harga_satuan'] ?>, <?= $produk['stok_tersedia'] ?>)">
                                    <i class="fas fa-plus mr-1"></i>Tambah
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tab Content Layanan -->
                <div id="tab-layanan" class="tab-content p-6 hidden">
                    <div class="mb-4">
                        <input type="text" id="search-layanan" placeholder="Cari layanan..." class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div id="layanan-list" class="max-h-96 overflow-y-auto">
                        <?php foreach ($layanan_list as $layanan): ?>
                        <div class="item-card layanan-item" data-search="<?= strtolower($layanan['nama_layanan'] . ' ' . $layanan['kode_layanan']) ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($layanan['nama_layanan']) ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?= htmlspecialchars($layanan['kode_layanan']) ?> | 
                                        <?= htmlspecialchars($layanan['jenis_layanan']) ?>
                                    </div>
                                    <div class="text-lg font-semibold text-green-600">Rp <?= number_format($layanan['harga'], 0, ',', '.') ?></div>
                                </div>
                                <button class="btn-add-item" onclick="addToCart('layanan', <?= $layanan['id'] ?>, '<?= htmlspecialchars($layanan['nama_layanan']) ?>', <?= $layanan['harga'] ?>, 999)">
                                    <i class="fas fa-plus mr-1"></i>Tambah
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Cart & Checkout -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-shopping-cart text-green-600 mr-2"></i>
                    Keranjang Belanja
                </h3>

                <!-- Cart Items -->
                <div id="cart-items" class="mb-6 max-h-64 overflow-y-auto">
                    <div id="empty-cart" class="text-center py-8 text-gray-500">
                        <i class="fas fa-shopping-cart text-4xl mb-3 text-gray-300"></i>
                        <p>Keranjang masih kosong</p>
                        <p class="text-sm">Tambahkan produk atau layanan</p>
                    </div>
                </div>

                <!-- Total Section -->
                <div class="total-section">
                    <div class="flex justify-between items-center mb-2">
                        <span>Subtotal:</span>
                        <span class="text-xl font-bold" id="subtotal">Rp 0</span>
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-lg">Total:</span>
                        <span class="text-2xl font-bold" id="total">Rp 0</span>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="mt-6">
                    <h4 class="font-semibold text-gray-900 mb-3">Metode Pembayaran</h4>
                    
                    <div class="payment-method-card" data-method="tunai">
                        <div class="flex items-center">
                            <input type="radio" name="payment_method" value="tunai" id="tunai" class="mr-3">
                            <label for="tunai" class="flex-1 cursor-pointer">
                                <div class="font-medium">Tunai</div>
                                <div class="text-sm text-gray-500">Pembayaran langsung</div>
                            </label>
                        </div>
                    </div>

                    <div class="payment-method-card" data-method="dp_pelunasan">
                        <div class="flex items-center">
                            <input type="radio" name="payment_method" value="dp_pelunasan" id="dp" class="mr-3">
                            <label for="dp" class="flex-1 cursor-pointer">
                                <div class="font-medium">DP + Pelunasan</div>
                                <div class="text-sm text-gray-500">Bayar sebagian dulu</div>
                            </label>
                        </div>
                        <div id="dp-amount-section" class="mt-3 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah DP</label>
                            <input type="number" id="dp-amount" class="w-full p-2 border border-gray-300 rounded" placeholder="Masukkan jumlah DP">
                        </div>
                    </div>

                    <div class="payment-method-card" data-method="tempo">
                        <div class="flex items-center">
                            <input type="radio" name="payment_method" value="tempo" id="tempo" class="mr-3">
                            <label for="tempo" class="flex-1 cursor-pointer">
                                <div class="font-medium">Tempo</div>
                                <div class="text-sm text-gray-500">Bayar nanti</div>
                            </label>
                        </div>
                        <div id="tempo-date-section" class="mt-3 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Jatuh Tempo</label>
                            <input type="date" id="tempo-date" class="w-full p-2 border border-gray-300 rounded">
                        </div>
                    </div>
                </div>

                <!-- Bank Selection (required for all payments) -->
                <div id="bank-selection" class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Bank <span class="text-red-500">*</span></label>
                    <select id="bank-select" class="w-full p-2 border border-gray-300 rounded-lg" required>
                        <option value="">-- Pilih Bank --</option>
                        <?php foreach ($bank_list as $bank): ?>
                        <option value="<?= $bank['id'] ?>"><?= htmlspecialchars($bank['nama_bank']) ?> (<?= $bank['jenis_bank'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Notes -->
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Catatan (Opsional)</label>
                    <textarea id="notes" rows="3" class="w-full p-2 border border-gray-300 rounded-lg" placeholder="Tambahkan catatan..."></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 space-y-3">
                    <button id="btn-checkout" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors disabled:bg-gray-400" disabled>
                        <i class="fas fa-credit-card mr-2"></i>
                        Proses Transaksi
                    </button>
                    <button id="btn-clear" class="w-full bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors">
                        <i class="fas fa-trash mr-2"></i>
                        Bersihkan Keranjang
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let cart = [];
let currentTotal = 0;

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tab = this.dataset.tab;
        
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active', 'border-blue-500', 'text-blue-600');
            b.classList.add('border-transparent', 'text-gray-500');
        });
        this.classList.add('active', 'border-blue-500', 'text-blue-600');
        this.classList.remove('border-transparent', 'text-gray-500');
        
        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.getElementById('tab-' + tab).classList.remove('hidden');
    });
});

// Add to cart function - make it globally accessible
window.addToCart = function(type, id, name, price, maxQty) {
    const existingItem = cart.find(item => item.type === type && item.id === id);
    
    if (existingItem) {
        if (existingItem.quantity < maxQty) {
            existingItem.quantity++;
            existingItem.subtotal = existingItem.quantity * existingItem.price;
        } else {
            alert('Stok tidak mencukupi!');
            return;
        }
    } else {
        cart.push({
            type: type,
            id: id,
            name: name,
            price: price,
            quantity: 1,
            subtotal: price,
            maxQty: maxQty
        });
    }
    
    updateCartDisplay();
    updateCheckoutButton();
};

// Remove from cart function - make it globally accessible
window.removeFromCart = function(index) {
    cart.splice(index, 1);
    updateCartDisplay();
    updateCheckoutButton();
};

// Update quantity function - make it globally accessible
window.updateQuantity = function(index, newQty) {
    if (newQty <= 0) {
        removeFromCart(index);
        return;
    }
    
    if (newQty > cart[index].maxQty) {
        alert('Stok tidak mencukupi!');
        return;
    }
    
    cart[index].quantity = newQty;
    cart[index].subtotal = cart[index].quantity * cart[index].price;
    updateCartDisplay();
    updateCheckoutButton();
};

// Update cart display function
function updateCartDisplay() {
    const cartContainer = document.getElementById('cart-items');
    let emptyCart = document.getElementById('empty-cart');
    
    if (!cartContainer) {
        console.error('Cart container not found');
        return;
    }
    
    if (cart.length === 0) {
        // Create empty cart element if it doesn't exist
        if (!emptyCart) {
            emptyCart = document.createElement('div');
            emptyCart.id = 'empty-cart';
            emptyCart.className = 'text-center py-8 text-gray-500';
            emptyCart.innerHTML = `
                <i class="fas fa-shopping-cart text-4xl mb-3 text-gray-300"></i>
                <p>Keranjang masih kosong</p>
                <p class="text-sm">Tambahkan produk atau layanan</p>
            `;
        }
        cartContainer.innerHTML = '';
        cartContainer.appendChild(emptyCart);
        currentTotal = 0;
    } else {
        let html = '';
        let total = 0;
        
        cart.forEach((item, index) => {
            total += item.subtotal;
            html += `
                <div class="cart-item">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                            <div class="font-medium text-sm">${item.name}</div>
                            <div class="text-xs text-gray-500">${item.type === 'produk' ? 'Produk' : 'Layanan'}</div>
                        </div>
                        <button class="btn-remove" onclick="removeFromCart(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <button class="bg-gray-200 text-gray-700 w-6 h-6 rounded text-xs" onclick="updateQuantity(${index}, ${item.quantity - 1})">-</button>
                            <span class="text-sm font-medium w-8 text-center">${item.quantity}</span>
                            <button class="bg-gray-200 text-gray-700 w-6 h-6 rounded text-xs" onclick="updateQuantity(${index}, ${item.quantity + 1})">+</button>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500">Rp ${number_format(item.price)}</div>
                            <div class="font-semibold text-sm">Rp ${number_format(item.subtotal)}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        cartContainer.innerHTML = html;
        currentTotal = total;
    }
    
    const subtotalEl = document.getElementById('subtotal');
    const totalEl = document.getElementById('total');
    
    if (subtotalEl) subtotalEl.textContent = 'Rp ' + number_format(currentTotal);
    if (totalEl) totalEl.textContent = 'Rp ' + number_format(currentTotal);
}

// Update checkout button state function
function updateCheckoutButton() {
    const btn = document.getElementById('btn-checkout');
    const desaSelect = document.getElementById('desa-select');
    const bankSelect = document.getElementById('bank-select');
    const paymentSelected = document.querySelector('input[name="payment_method"]:checked');
    
    if (!btn || !desaSelect || !bankSelect) return;
    
    const desaSelected = desaSelect.value;
    const bankSelected = bankSelect.value;
    
    if (cart.length > 0 && desaSelected && paymentSelected && bankSelected) {
        btn.disabled = false;
    } else {
        btn.disabled = true;
    }
}

// Search functionality
const searchProduk = document.getElementById('search-produk');
if (searchProduk) {
    searchProduk.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('.produk-item').forEach(item => {
            const searchData = item.dataset.search;
            if (searchData && searchData.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
}

const searchLayanan = document.getElementById('search-layanan');
if (searchLayanan) {
    searchLayanan.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('.layanan-item').forEach(item => {
            const searchData = item.dataset.search;
            if (searchData && searchData.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
}

// Payment method selection
document.querySelectorAll('.payment-method-card').forEach(card => {
    card.addEventListener('click', function() {
        const method = this.dataset.method;
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
        
        // Update card styles
        document.querySelectorAll('.payment-method-card').forEach(c => {
            c.classList.remove('selected');
        });
        this.classList.add('selected');
        
        // Show/hide additional fields
        document.getElementById('dp-amount-section').classList.add('hidden');
        document.getElementById('tempo-date-section').classList.add('hidden');
        
        // Always show bank selection for all payment methods
        document.getElementById('bank-selection').classList.remove('hidden');
        
        if (method === 'dp_pelunasan') {
            document.getElementById('dp-amount-section').classList.remove('hidden');
        } else if (method === 'tempo') {
            document.getElementById('tempo-date-section').classList.remove('hidden');
        }
        
        updateCheckoutButton();
    });
});

// Clear cart
const btnClear = document.getElementById('btn-clear');
if (btnClear) {
    btnClear.addEventListener('click', function() {
        if (confirm('Yakin ingin mengosongkan keranjang?')) {
            cart = [];
            updateCartDisplay();
            updateCheckoutButton();
        }
    });
}

// Desa selection change
const desaSelect = document.getElementById('desa-select');
if (desaSelect) {
    desaSelect.addEventListener('change', updateCheckoutButton);
}

// Bank selection change
const bankSelect = document.getElementById('bank-select');
if (bankSelect) {
    bankSelect.addEventListener('change', updateCheckoutButton);
}

// Checkout process
const btnCheckout = document.getElementById('btn-checkout');
if (btnCheckout) {
    btnCheckout.addEventListener('click', function() {
    if (cart.length === 0) {
        alert('Keranjang masih kosong!');
        return;
    }
    
    const desaId = document.getElementById('desa-select').value;
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const notes = document.getElementById('notes').value;
    const bankId = document.getElementById('bank-select').value;
    
    if (!bankId) {
        alert('Bank harus dipilih!');
        return;
    }
    
    let dpAmount = 0;
    let tempoDate = null;
    
    if (paymentMethod === 'dp_pelunasan') {
        dpAmount = parseFloat(document.getElementById('dp-amount').value) || 0;
        if (dpAmount <= 0 || dpAmount >= currentTotal) {
            alert('Jumlah DP tidak valid!');
            return;
        }
    }
    
    if (paymentMethod === 'tempo') {
        tempoDate = document.getElementById('tempo-date').value;
        if (!tempoDate) {
            alert('Tanggal jatuh tempo harus diisi!');
            return;
        }
    }
    
    // Prepare data
    const transactionData = {
        invoice_number: document.getElementById('invoice-number').textContent,
        desa_id: desaId,
        payment_method: paymentMethod,
        total_amount: currentTotal,
        dp_amount: dpAmount,
        tempo_date: tempoDate,
        bank_id: bankId,
        notes: notes,
        items: cart
    };
    
    // Send to server
    fetch('pos-process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(transactionData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Transaksi berhasil disimpan!');
            // Redirect to invoice
            window.open('pos-invoice.php?id=' + data.transaction_id, '_blank');
            // Reset form
            cart = [];
            updateCartDisplay();
            
            const desaSelectReset = document.getElementById('desa-select');
            if (desaSelectReset) desaSelectReset.value = '';
            
            const checkedPayment = document.querySelector('input[name="payment_method"]:checked');
            if (checkedPayment) checkedPayment.checked = false;
            
            document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('selected'));
            
            const notesField = document.getElementById('notes');
            if (notesField) notesField.value = '';
            
            updateCheckoutButton();
            // Generate new invoice number without reloading
            generateInvoiceNumber();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat memproses transaksi!');
    });
});
}



}); // End of DOMContentLoaded

// Number formatting function
function number_format(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

// Generate new invoice number
function generateInvoiceNumber() {
    fetch('pos-process.php?action=generate_invoice')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('invoice-number').textContent = data.invoice_number;
        }
    })
    .catch(error => {
        console.error('Error generating invoice number:', error);
    });
}
</script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>