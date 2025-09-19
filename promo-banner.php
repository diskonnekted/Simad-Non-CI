<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Cek role admin
AuthStatic::requireRole(['admin']);

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $judul = trim($_POST['judul']);
                $deskripsi = trim($_POST['deskripsi']);
                $posisi = $_POST['posisi'];
                $status = $_POST['status'];
                $tanggal_mulai = $_POST['tanggal_mulai'] ?: null;
                $tanggal_berakhir = $_POST['tanggal_berakhir'] ?: null;
                
                // Handle file upload
                $gambar = '';
                if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/promo/';
                    $file_extension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'promo_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                            $gambar = $filename;
                        } else {
                            $error = 'Gagal mengupload gambar.';
                        }
                    } else {
                        $error = 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.';
                    }
                }
                
                if (!$error && $judul && $posisi) {
                    try {
                        $db->insert('promo_banners', [
                            'judul' => $judul,
                            'deskripsi' => $deskripsi,
                            'gambar' => $gambar,
                            'posisi' => $posisi,
                            'status' => $status,
                            'tanggal_mulai' => $tanggal_mulai,
                            'tanggal_berakhir' => $tanggal_berakhir
                        ]);
                        $message = 'Promo banner berhasil ditambahkan!';
                    } catch (Exception $e) {
                        $error = 'Gagal menambahkan promo banner: ' . $e->getMessage();
                    }
                } elseif (!$error) {
                    $error = 'Judul dan posisi harus diisi.';
                }
                break;
                
            case 'edit':
                $id = $_POST['id'];
                $judul = trim($_POST['judul']);
                $deskripsi = trim($_POST['deskripsi']);
                $posisi = $_POST['posisi'];
                $status = $_POST['status'];
                $tanggal_mulai = $_POST['tanggal_mulai'] ?: null;
                $tanggal_berakhir = $_POST['tanggal_berakhir'] ?: null;
                
                $update_data = [
                    'judul' => $judul,
                    'deskripsi' => $deskripsi,
                    'posisi' => $posisi,
                    'status' => $status,
                    'tanggal_mulai' => $tanggal_mulai,
                    'tanggal_berakhir' => $tanggal_berakhir
                ];
                
                // Handle file upload if new file is provided
                if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/promo/';
                    $file_extension = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $filename = 'promo_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                            // Delete old image
                            $old_banner = $db->select('SELECT gambar FROM promo_banners WHERE id = ?', [$id]);
                            if ($old_banner && $old_banner[0]['gambar'] && file_exists($upload_dir . $old_banner[0]['gambar'])) {
                                unlink($upload_dir . $old_banner[0]['gambar']);
                            }
                            $update_data['gambar'] = $filename;
                        } else {
                            $error = 'Gagal mengupload gambar baru.';
                        }
                    } else {
                        $error = 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.';
                    }
                }
                
                if (!$error && $judul && $posisi) {
                    try {
                        $db->update('promo_banners', $update_data, ['id' => $id]);
                        $message = 'Promo banner berhasil diperbarui!';
                    } catch (Exception $e) {
                        $error = 'Gagal memperbarui promo banner: ' . $e->getMessage();
                    }
                } elseif (!$error) {
                    $error = 'Judul dan posisi harus diisi.';
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                try {
                    // Delete image file
                    $banner = $db->select('SELECT gambar FROM promo_banners WHERE id = ?', [$id]);
                    if ($banner && $banner[0]['gambar'] && file_exists('uploads/promo/' . $banner[0]['gambar'])) {
                        unlink('uploads/promo/' . $banner[0]['gambar']);
                    }
                    
                    $db->delete('promo_banners', ['id' => $id]);
                    $message = 'Promo banner berhasil dihapus!';
                } catch (Exception $e) {
                    $error = 'Gagal menghapus promo banner: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all promo banners
$promo_banners = $db->select("
    SELECT * FROM promo_banners 
    ORDER BY posisi ASC, created_at DESC
");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Promo Banner - Sistem Manajemen Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <?php include 'layouts/header.php'; ?>

    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-images mr-2 text-blue-600"></i>
                        Manajemen Promo Banner
                    </h1>
                    <p class="text-gray-600 mt-1">Kelola banner promo yang ditampilkan di halaman utama</p>
                </div>
                <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Tambah Banner
                </button>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Promo Banners Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($promo_banners as $banner): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- Banner Image -->
                <div class="aspect-w-16 aspect-h-9 bg-gray-200">
                    <?php if ($banner['gambar'] && file_exists('uploads/promo/' . $banner['gambar'])): ?>
                        <img src="uploads/promo/<?= htmlspecialchars($banner['gambar']) ?>" 
                             alt="<?= htmlspecialchars($banner['judul']) ?>"
                             class="w-full h-48 object-cover">
                    <?php else: ?>
                        <div class="w-full h-48 bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                            <div class="text-center text-white">
                                <i class="fas fa-image text-4xl mb-2"></i>
                                <p class="text-sm">Tidak ada gambar</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Banner Info -->
                <div class="p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($banner['judul']) ?></h3>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-1 text-xs rounded-full <?= $banner['status'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= ucfirst($banner['status']) ?>
                            </span>
                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                Posisi <?= $banner['posisi'] ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($banner['deskripsi']): ?>
                    <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($banner['deskripsi']) ?></p>
                    <?php endif; ?>
                    
                    <div class="text-xs text-gray-500 mb-3">
                        <?php if ($banner['tanggal_mulai'] || $banner['tanggal_berakhir']): ?>
                            <i class="fas fa-calendar mr-1"></i>
                            <?= $banner['tanggal_mulai'] ? date('d/m/Y', strtotime($banner['tanggal_mulai'])) : 'Tidak terbatas' ?> - 
                            <?= $banner['tanggal_berakhir'] ? date('d/m/Y', strtotime($banner['tanggal_berakhir'])) : 'Tidak terbatas' ?>
                        <?php else: ?>
                            <i class="fas fa-infinity mr-1"></i>Tidak ada batas waktu
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($banner)) ?>)" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm transition duration-200">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button onclick="deleteBanner(<?= $banner['id'] ?>)" 
                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm transition duration-200">
                            <i class="fas fa-trash mr-1"></i>Hapus
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($promo_banners)): ?>
            <div class="col-span-2 text-center py-12">
                <i class="fas fa-images text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-600 mb-4">Belum ada promo banner</p>
                <button onclick="openAddModal()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Tambah Banner Pertama
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="bannerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Tambah Promo Banner</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="bannerForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="bannerId">
                
                <div class="space-y-4">
                    <div>
                        <label for="judul" class="block text-sm font-medium text-gray-700 mb-1">Judul Banner *</label>
                        <input type="text" name="judul" id="judul" required 
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="deskripsi" id="deskripsi" rows="3" 
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    
                    <div>
                        <label for="posisi" class="block text-sm font-medium text-gray-700 mb-1">Posisi Banner *</label>
                        <select name="posisi" id="posisi" required 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="1">Posisi 1 (Kiri)</option>
                            <option value="2">Posisi 2 (Kanan)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" 
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="tanggal_mulai" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" id="tanggal_mulai" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="tanggal_berakhir" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Berakhir</label>
                            <input type="date" name="tanggal_berakhir" id="tanggal_berakhir" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label for="gambar" class="block text-sm font-medium text-gray-700 mb-1">Gambar Banner</label>
                        <input type="file" name="gambar" id="gambar" accept="image/*" 
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, GIF, WebP. Maksimal 2MB.</p>
                    </div>
                </div>
                
                <div class="flex space-x-3 mt-6">
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg transition duration-200">
                        Batal
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <span id="submitText">Tambah Banner</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Promo Banner';
            document.getElementById('formAction').value = 'add';
            document.getElementById('submitText').textContent = 'Tambah Banner';
            document.getElementById('bannerForm').reset();
            document.getElementById('bannerId').value = '';
            document.getElementById('bannerModal').classList.remove('hidden');
        }
        
        function openEditModal(banner) {
            document.getElementById('modalTitle').textContent = 'Edit Promo Banner';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('submitText').textContent = 'Perbarui Banner';
            document.getElementById('bannerId').value = banner.id;
            document.getElementById('judul').value = banner.judul;
            document.getElementById('deskripsi').value = banner.deskripsi || '';
            document.getElementById('posisi').value = banner.posisi;
            document.getElementById('status').value = banner.status;
            document.getElementById('tanggal_mulai').value = banner.tanggal_mulai || '';
            document.getElementById('tanggal_berakhir').value = banner.tanggal_berakhir || '';
            document.getElementById('bannerModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('bannerModal').classList.add('hidden');
        }
        
        function deleteBanner(id) {
            Swal.fire({
                title: 'Hapus Banner?',
                text: 'Banner yang dihapus tidak dapat dikembalikan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // Close modal when clicking outside
        document.getElementById('bannerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>