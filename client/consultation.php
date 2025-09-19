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

$success = '';
$error = '';

// Ambil data desa
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM desa WHERE id = ?");
    $stmt->execute([$_SESSION['desa_id']]);
    $desa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Proses form konsultasi
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_consultation'])) {
        $kategori = trim($_POST['kategori'] ?? '');
        $subjek = trim($_POST['subjek'] ?? '');
        $pesan = trim($_POST['pesan'] ?? '');
        $prioritas = $_POST['prioritas'] ?? 'normal';
        $kontak_balik = trim($_POST['kontak_balik'] ?? '');
        
        // Validasi input
        if (empty($kategori) || empty($subjek) || empty($pesan)) {
            $error = 'Semua field wajib diisi!';
        } elseif (strlen($subjek) < 5) {
            $error = 'Subjek minimal 5 karakter!';
        } elseif (strlen($pesan) < 10) {
            $error = 'Pesan minimal 10 karakter!';
        } else {
            try {
                // Simpan konsultasi ke database
                $stmt = $pdo->prepare("
                    INSERT INTO konsultasi 
                    (desa_id, kategori, subjek, pesan, prioritas, kontak_balik, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $_SESSION['desa_id'],
                    $kategori,
                    $subjek,
                    $pesan,
                    $prioritas,
                    $kontak_balik
                ]);
                
                $success = 'Konsultasi berhasil dikirim! Admin akan merespons dalam 1x24 jam.';
                
                // Reset form
                $_POST = [];
                
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan saat mengirim konsultasi: ' . $e->getMessage();
            }
        }
    }
    
    // Ambil riwayat konsultasi
    $riwayat_stmt = $pdo->prepare("
        SELECT k.*, 
               CASE 
                   WHEN k.status = 'pending' THEN 'Menunggu Respons'
                   WHEN k.status = 'in_progress' THEN 'Sedang Diproses'
                   WHEN k.status = 'resolved' THEN 'Selesai'
                   WHEN k.status = 'closed' THEN 'Ditutup'
                   ELSE 'Tidak Diketahui'
               END as status_text,
               CASE 
                   WHEN k.prioritas = 'low' THEN 'Rendah'
                   WHEN k.prioritas = 'normal' THEN 'Normal'
                   WHEN k.prioritas = 'high' THEN 'Tinggi'
                   WHEN k.prioritas = 'urgent' THEN 'Mendesak'
                   ELSE 'Normal'
               END as prioritas_text
        FROM konsultasi k
        WHERE k.desa_id = ?
        ORDER BY k.created_at DESC
        LIMIT 10
    ");
    $riwayat_stmt->execute([$_SESSION['desa_id']]);
    $riwayat_konsultasi = $riwayat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil FAQ
    $faq_stmt = $pdo->prepare("
        SELECT * FROM faq 
        WHERE status = 'aktif' 
        ORDER BY urutan ASC, created_at DESC
        LIMIT 10
    ");
    $faq_stmt->execute();
    $faq_list = $faq_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

// Fungsi untuk mendapatkan warna status
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'in_progress': return 'bg-blue-100 text-blue-800';
        case 'resolved': return 'bg-green-100 text-green-800';
        case 'closed': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

// Fungsi untuk mendapatkan warna prioritas
function getPriorityColor($prioritas) {
    switch ($prioritas) {
        case 'low': return 'bg-green-100 text-green-800';
        case 'normal': return 'bg-blue-100 text-blue-800';
        case 'high': return 'bg-orange-100 text-orange-800';
        case 'urgent': return 'bg-red-100 text-red-800';
        default: return 'bg-blue-100 text-blue-800';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsultasi - Portal Klien Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .consultation-card {
            transition: all 0.3s ease;
        }
        .consultation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .faq-item {
            transition: all 0.3s ease;
        }
        .faq-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .faq-content.active {
            max-height: 500px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient shadow-lg">
        <div class="max-w-6xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="dashboard.php" class="bg-white bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center text-white hover:bg-opacity-30 transition duration-200">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-white">Konsultasi</h1>
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
        <!-- Navigation Tabs -->
        <div class="bg-white rounded-lg shadow-md mb-8">
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <button onclick="showSection('form')" class="tab-button active border-b-2 border-purple-500 py-4 px-1 text-sm font-medium text-purple-600">
                        <i class="fas fa-edit mr-2"></i>Buat Konsultasi
                    </button>
                    <button onclick="showSection('riwayat')" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-history mr-2"></i>Riwayat
                    </button>
                    <button onclick="showSection('faq')" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-question-circle mr-2"></i>FAQ
                    </button>
                </nav>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form Konsultasi Section -->
        <div id="form-section" class="section-content">
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-edit mr-2 text-purple-600"></i>
                    Buat Konsultasi Baru
                </h3>
                
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Kategori -->
                        <div>
                            <label for="kategori" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tags mr-1"></i>Kategori Konsultasi *
                            </label>
                            <select name="kategori" id="kategori" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="">Pilih Kategori</option>
                                <option value="produk" <?= (isset($_POST['kategori']) && $_POST['kategori'] === 'produk') ? 'selected' : '' ?>>Produk</option>
                                <option value="layanan" <?= (isset($_POST['kategori']) && $_POST['kategori'] === 'layanan') ? 'selected' : '' ?>>Layanan</option>
                                <option value="pembayaran" <?= (isset($_POST['kategori']) && $_POST['kategori'] === 'pembayaran') ? 'selected' : '' ?>>Pembayaran</option>
                                <option value="pengiriman" <?= (isset($_POST['kategori']) && $_POST['kategori'] === 'pengiriman') ? 'selected' : '' ?>>Pengiriman</option>
                                <option value="teknis" <?= (isset($_POST['kategori']) && $_POST['kategori'] === 'teknis') ? 'selected' : '' ?>>Masalah Teknis</option>
                                <option value="umum" <?= (isset($_POST['kategori']) && $_POST['kategori'] === 'umum') ? 'selected' : '' ?>>Pertanyaan Umum</option>
                                <option value="keluhan" <?= (isset($_POST['kategori']) && $_POST['kategori'] === 'keluhan') ? 'selected' : '' ?>>Keluhan</option>
                                <option value="saran" <?= (isset($_POST['kategori']) && $_POST['kategori'] === 'saran') ? 'selected' : '' ?>>Saran</option>
                            </select>
                        </div>
                        
                        <!-- Prioritas -->
                        <div>
                            <label for="prioritas" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Prioritas
                            </label>
                            <select name="prioritas" id="prioritas" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="normal" <?= (isset($_POST['prioritas']) && $_POST['prioritas'] === 'normal') ? 'selected' : '' ?>>Normal</option>
                                <option value="low" <?= (isset($_POST['prioritas']) && $_POST['prioritas'] === 'low') ? 'selected' : '' ?>>Rendah</option>
                                <option value="high" <?= (isset($_POST['prioritas']) && $_POST['prioritas'] === 'high') ? 'selected' : '' ?>>Tinggi</option>
                                <option value="urgent" <?= (isset($_POST['prioritas']) && $_POST['prioritas'] === 'urgent') ? 'selected' : '' ?>>Mendesak</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Subjek -->
                    <div>
                        <label for="subjek" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-heading mr-1"></i>Subjek Konsultasi *
                        </label>
                        <input type="text" name="subjek" id="subjek" required 
                               value="<?= htmlspecialchars($_POST['subjek'] ?? '') ?>"
                               placeholder="Masukkan subjek konsultasi (minimal 5 karakter)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <!-- Pesan -->
                    <div>
                        <label for="pesan" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-comment mr-1"></i>Pesan Konsultasi *
                        </label>
                        <textarea name="pesan" id="pesan" rows="6" required 
                                  placeholder="Jelaskan detail konsultasi Anda (minimal 10 karakter)"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= htmlspecialchars($_POST['pesan'] ?? '') ?></textarea>
                        <p class="text-sm text-gray-500 mt-1">Semakin detail informasi yang Anda berikan, semakin baik respons yang akan Anda terima.</p>
                    </div>
                    
                    <!-- Kontak Balik -->
                    <div>
                        <label for="kontak_balik" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1"></i>Kontak untuk Respons (Opsional)
                        </label>
                        <input type="text" name="kontak_balik" id="kontak_balik" 
                               value="<?= htmlspecialchars($_POST['kontak_balik'] ?? $desa['hp_kepala_desa'] ?? '') ?>"
                               placeholder="Nomor HP atau email untuk dihubungi kembali"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-sm text-gray-500 mt-1">Jika kosong, admin akan merespons melalui sistem ini.</p>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex items-center justify-between pt-4">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-1"></i>
                            Admin akan merespons dalam 1x24 jam
                        </div>
                        <button type="submit" name="submit_consultation" 
                                class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition duration-200 font-medium">
                            <i class="fas fa-paper-plane mr-2"></i>Kirim Konsultasi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Riwayat Konsultasi Section -->
        <div id="riwayat-section" class="section-content hidden">
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-history mr-2 text-blue-600"></i>
                    Riwayat Konsultasi
                </h3>
                
                <?php if (empty($riwayat_konsultasi)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-comments text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-600 mb-4">Belum ada riwayat konsultasi</p>
                        <button onclick="showSection('form')" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Buat Konsultasi Pertama
                        </button>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($riwayat_konsultasi as $konsultasi): ?>
                            <div class="consultation-card border border-gray-200 rounded-lg p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h4 class="font-semibold text-gray-800">
                                                <?= htmlspecialchars($konsultasi['subjek']) ?>
                                            </h4>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?= getStatusColor($konsultasi['status']) ?>">
                                                <?= htmlspecialchars($konsultasi['status_text']) ?>
                                            </span>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?= getPriorityColor($konsultasi['prioritas']) ?>">
                                                <?= htmlspecialchars($konsultasi['prioritas_text']) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="flex items-center space-x-4 text-sm text-gray-600 mb-3">
                                            <span><i class="fas fa-tag mr-1"></i><?= ucfirst(htmlspecialchars($konsultasi['kategori'])) ?></span>
                                            <span><i class="fas fa-calendar mr-1"></i><?= date('d/m/Y H:i', strtotime($konsultasi['created_at'])) ?></span>
                                        </div>
                                        
                                        <p class="text-gray-700 mb-4">
                                            <?= nl2br(htmlspecialchars($konsultasi['pesan'])) ?>
                                        </p>
                                        
                                        <?php if (!empty($konsultasi['respons'])): ?>
                                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mt-4">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-reply text-blue-600 mr-2"></i>
                                                    <span class="font-medium text-blue-800">Respons Admin:</span>
                                                    <?php if (!empty($konsultasi['respons_at'])): ?>
                                                        <span class="text-sm text-blue-600 ml-2">
                                                            (<?= date('d/m/Y H:i', strtotime($konsultasi['respons_at'])) ?>)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-blue-700">
                                                    <?= nl2br(htmlspecialchars($konsultasi['respons'])) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($konsultasi['kontak_balik'])): ?>
                                            <div class="text-sm text-gray-600 mt-3">
                                                <i class="fas fa-phone mr-1"></i>
                                                Kontak: <?= htmlspecialchars($konsultasi['kontak_balik']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- FAQ Section -->
        <div id="faq-section" class="section-content hidden">
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-question-circle mr-2 text-green-600"></i>
                    Pertanyaan yang Sering Diajukan (FAQ)
                </h3>
                
                <?php if (empty($faq_list)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-question-circle text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-600">Belum ada FAQ tersedia</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($faq_list as $index => $faq): ?>
                            <div class="faq-item border border-gray-200 rounded-lg">
                                <button onclick="toggleFAQ(<?= $index ?>)" 
                                        class="w-full text-left p-4 hover:bg-gray-50 transition duration-200 flex items-center justify-between">
                                    <span class="font-medium text-gray-800">
                                        <?= htmlspecialchars($faq['pertanyaan']) ?>
                                    </span>
                                    <i class="fas fa-chevron-down transform transition-transform duration-200" id="faq-icon-<?= $index ?>"></i>
                                </button>
                                
                                <div class="faq-content px-4 pb-4" id="faq-content-<?= $index ?>">
                                    <div class="text-gray-700 leading-relaxed">
                                        <?= nl2br(htmlspecialchars($faq['jawaban'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-headset mr-2 text-blue-600"></i>
                Butuh Bantuan Langsung?
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <i class="fas fa-phone text-blue-600 text-2xl mb-2"></i>
                    <div class="font-medium text-gray-800">Telepon</div>
                    <div class="text-sm text-gray-600">0800-1234-5678</div>
                    <div class="text-xs text-gray-500">Senin-Jumat 08:00-17:00</div>
                </div>
                
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <i class="fas fa-envelope text-green-600 text-2xl mb-2"></i>
                    <div class="font-medium text-gray-800">Email</div>
                    <div class="text-sm text-gray-600">support@portaldesa.id</div>
                    <div class="text-xs text-gray-500">Respons dalam 24 jam</div>
                </div>
                
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <i class="fab fa-whatsapp text-purple-600 text-2xl mb-2"></i>
                    <div class="font-medium text-gray-800">WhatsApp</div>
                    <div class="text-sm text-gray-600">0812-3456-7890</div>
                    <div class="text-xs text-gray-500">Chat langsung</div>
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
        // Tab functionality
        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.section-content').forEach(el => {
                el.classList.add('hidden');
            });
            
            // Show selected section
            document.getElementById(section + '-section').classList.remove('hidden');
            
            // Update active tab
            document.querySelectorAll('.tab-button').forEach(tab => {
                tab.classList.remove('active', 'border-purple-500', 'text-purple-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            event.target.classList.add('active', 'border-purple-500', 'text-purple-600');
            event.target.classList.remove('border-transparent', 'text-gray-500');
        }
        
        // FAQ toggle functionality
        function toggleFAQ(index) {
            const content = document.getElementById(`faq-content-${index}`);
            const icon = document.getElementById(`faq-icon-${index}`);
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.style.transform = 'rotate(0deg)';
            } else {
                // Close all other FAQs
                document.querySelectorAll('.faq-content').forEach(el => {
                    el.classList.remove('active');
                });
                document.querySelectorAll('[id^="faq-icon-"]').forEach(el => {
                    el.style.transform = 'rotate(0deg)';
                });
                
                // Open selected FAQ
                content.classList.add('active');
                icon.style.transform = 'rotate(180deg)';
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const subjek = document.getElementById('subjek').value.trim();
            const pesan = document.getElementById('pesan').value.trim();
            
            if (subjek.length < 5) {
                e.preventDefault();
                alert('Subjek minimal 5 karakter!');
                return;
            }
            
            if (pesan.length < 10) {
                e.preventDefault();
                alert('Pesan minimal 10 karakter!');
                return;
            }
        });
        
        // Character counter for textarea
        const textarea = document.getElementById('pesan');
        const charCounter = document.createElement('div');
        charCounter.className = 'text-sm text-gray-500 mt-1 text-right';
        textarea.parentNode.appendChild(charCounter);
        
        function updateCharCounter() {
            const length = textarea.value.length;
            charCounter.textContent = `${length} karakter`;
            
            if (length < 10) {
                charCounter.className = 'text-sm text-red-500 mt-1 text-right';
            } else {
                charCounter.className = 'text-sm text-gray-500 mt-1 text-right';
            }
        }
        
        textarea.addEventListener('input', updateCharCounter);
        updateCharCounter();
        
        // Auto-save draft (localStorage)
        const formElements = ['kategori', 'subjek', 'pesan', 'prioritas', 'kontak_balik'];
        
        formElements.forEach(elementId => {
            const element = document.getElementById(elementId);
            if (element) {
                // Load saved data
                const savedValue = localStorage.getItem(`consultation_${elementId}`);
                if (savedValue && !element.value) {
                    element.value = savedValue;
                }
                
                // Save on change
                element.addEventListener('input', function() {
                    localStorage.setItem(`consultation_${elementId}`, this.value);
                });
            }
        });
        
        // Clear draft after successful submission
        <?php if ($success): ?>
            formElements.forEach(elementId => {
                localStorage.removeItem(`consultation_${elementId}`);
            });
        <?php endif; ?>
    </script>
</body>
</html>