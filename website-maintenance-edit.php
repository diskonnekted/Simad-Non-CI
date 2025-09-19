<?php
require_once 'config/auth.php';
require_once 'config/database.php';

// Check if user is logged in
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check role access
if (!AuthStatic::hasRole(['admin', 'supervisor', 'programmer'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Set page title
$page_title = 'Edit Website Maintenance';

// Initialize database
$db = new Database();
$pdo = $db->getConnection();

// Get maintenance ID
$maintenance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$maintenance_id) {
    header('Location: website-maintenance.php');
    exit;
}

// Get maintenance data
$stmt = $pdo->prepare("SELECT id, desa_id, CONCAT(UPPER(SUBSTRING(nama_desa, 1, 1)), LOWER(SUBSTRING(nama_desa, 2))) as nama_desa, website_url, penanggung_jawab_id, programmer_id, deadline, status, keterangan, assignment_type, created_at, updated_at FROM website_maintenance WHERE id = ?");
$stmt->execute([$maintenance_id]);
$maintenance = $stmt->fetch();

if (!$maintenance) {
    header('Location: website-maintenance.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desa_id = isset($_POST['desa_id']) ? (int)$_POST['desa_id'] : 0;
    $nama_desa = trim($_POST['nama_desa']);
    $website_url = trim($_POST['website_url']);
    $penanggung_jawab_id = isset($_POST['penanggung_jawab_id']) ? (int)$_POST['penanggung_jawab_id'] : 0;
    $programmer_id = isset($_POST['programmer_id']) ? (int)$_POST['programmer_id'] : 0;
    $deadline = $_POST['deadline'];
    $keterangan = trim($_POST['keterangan']);
    $status = $_POST['status'];
    
    $errors = [];
    
    // Validation
    if (empty($nama_desa)) {
        $errors[] = "Nama desa harus diisi.";
    }
    
    if (empty($website_url)) {
        $errors[] = "Website URL harus diisi.";
    } elseif (!filter_var($website_url, FILTER_VALIDATE_URL)) {
        $errors[] = "Format website URL tidak valid.";
    }
    
    if (empty($penanggung_jawab_id)) {
        $errors[] = "Penanggung jawab harus dipilih.";
    }
    
    if (empty($programmer_id)) {
        $errors[] = "Programmer harus dipilih.";
    }
    
    // Validate desa_id if selected
    if ($desa_id > 0) {
        $check_desa_stmt = $pdo->prepare("SELECT id FROM desa WHERE id = ?");
        $check_desa_stmt->execute([$desa_id]);
        if (!$check_desa_stmt->fetch()) {
            $errors[] = "Desa yang dipilih tidak valid.";
        }
    }
    
    if (empty($deadline)) {
        $errors[] = "Deadline harus diisi.";
    }
    
    if (!in_array($status, ['maintenance', 'pending_verification', 'completed'])) {
        $errors[] = "Status tidak valid.";
    }
    
    // Check if website URL already exists (excluding current record)
    if (empty($errors)) {
        $check_stmt = $pdo->prepare("SELECT id FROM website_maintenance WHERE website_url = ? AND id != ?");
        $check_stmt->execute([$website_url, $maintenance_id]);
        if ($check_stmt->fetch()) {
            $errors[] = "Website URL sudah terdaftar dalam maintenance.";
        }
    }
    
    if (empty($errors)) {
        try {
            // Use NULL for desa_id if not selected (0)
            $desa_id_value = ($desa_id > 0) ? $desa_id : null;
            
            // Get penanggung jawab name
            $pj_stmt = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
            $pj_stmt->execute([$penanggung_jawab_id]);
            $penanggung_jawab_data = $pj_stmt->fetch();
            $penanggung_jawab = $penanggung_jawab_data ? $penanggung_jawab_data['nama_lengkap'] : '';
            
            // Get programmer name
            $prog_stmt = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
            $prog_stmt->execute([$programmer_id]);
            $programmer_data = $prog_stmt->fetch();
            $programmer = $programmer_data ? $programmer_data['nama_lengkap'] : '';
            
            $stmt = $pdo->prepare("UPDATE website_maintenance SET desa_id = ?, nama_desa = ?, website_url = ?, penanggung_jawab_id = ?, programmer_id = ?, penanggung_jawab = ?, programmer = ?, deadline = ?, keterangan = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$desa_id_value, $nama_desa, $website_url, $penanggung_jawab_id, $programmer_id, $penanggung_jawab, $programmer, $deadline, $keterangan, $status, $maintenance_id]);
            
            $success_message = "Data maintenance berhasil diperbarui!";
            
            // Refresh maintenance data
            $stmt = $pdo->prepare("SELECT id, desa_id, CONCAT(UPPER(SUBSTRING(nama_desa, 1, 1)), LOWER(SUBSTRING(nama_desa, 2))) as nama_desa, website_url, penanggung_jawab_id, programmer_id, deadline, status, keterangan, assignment_type, created_at, updated_at FROM website_maintenance WHERE id = ?");
            $stmt->execute([$maintenance_id]);
            $maintenance = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Get desa list for dropdown
$desa_stmt = $pdo->query("SELECT id, CONCAT(UPPER(SUBSTRING(nama_desa, 1, 1)), LOWER(SUBSTRING(nama_desa, 2))) as nama_desa FROM desa ORDER BY nama_desa");
$desa_list = $desa_stmt->fetchAll();

// Get users list for dropdown
$users_stmt = $pdo->query("SELECT id, nama_lengkap, role FROM users WHERE status = 'aktif' ORDER BY nama_lengkap");
$users_list = $users_stmt->fetchAll();

// Get current user
$current_user = AuthStatic::getCurrentUser();
$can_edit_status = ($current_user['role'] === 'admin' || $current_user['role'] === 'supervisor' || $current_user['id'] == $maintenance['penanggung_jawab_id']);

// Include header
require_once 'layouts/header.php';
?>

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="website-maintenance-detail.php?id=<?php echo $maintenance_id; ?>" 
                   class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Website Maintenance</h1>
                    <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($maintenance['nama_desa']); ?></p>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="website-maintenance-detail.php?id=<?php echo $maintenance_id; ?>" 
                   class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-list-check mr-2"></i>
                    Lihat Detail
                </a>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <?php if (isset($success_message)): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium">Terdapat kesalahan pada form:</h3>
                    <ul class="mt-2 text-sm list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Edit Informasi Website Maintenance</h3>
        </div>
        
        <form method="POST" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Desa Selection -->
                <div class="md:col-span-2">
                    <label for="desa_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Pilih Desa
                    </label>
                    <select id="desa_id" 
                            name="desa_id" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">-- Pilih Desa --</option>
                        <?php foreach ($desa_list as $desa): ?>
                            <option value="<?php echo $desa['id']; ?>" 
                                    data-nama="<?php echo htmlspecialchars($desa['nama_desa']); ?>"
                                    <?php echo ($maintenance['desa_id'] == $desa['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($desa['nama_desa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Pilih desa dari database atau isi manual di field nama desa</p>
                </div>

                <!-- Nama Desa -->
                <div>
                    <label for="nama_desa" class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Desa <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="nama_desa" 
                           name="nama_desa" 
                           value="<?php echo htmlspecialchars($maintenance['nama_desa']); ?>"
                           placeholder="Masukkan nama desa"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Website URL -->
                <div>
                    <label for="website_url" class="block text-sm font-medium text-gray-700 mb-2">
                        Website URL <span class="text-red-500">*</span>
                    </label>
                    <input type="url" 
                           id="website_url" 
                           name="website_url" 
                           value="<?php echo htmlspecialchars($maintenance['website_url']); ?>"
                           placeholder="https://example.com"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Penanggung Jawab -->
                <div>
                    <label for="penanggung_jawab_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Penanggung Jawab <span class="text-red-500">*</span>
                    </label>
                    <select id="penanggung_jawab_id" 
                            name="penanggung_jawab_id" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Pilih Penanggung Jawab</option>
                        <?php foreach ($users_list as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo ($maintenance['penanggung_jawab_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['nama_lengkap']); ?> (<?php echo htmlspecialchars($user['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Programmer -->
                <div>
                    <label for="programmer_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Programmer <span class="text-red-500">*</span>
                    </label>
                    <select id="programmer_id" 
                            name="programmer_id" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Pilih Programmer</option>
                        <?php foreach ($users_list as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo ($maintenance['programmer_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['nama_lengkap']); ?> (<?php echo htmlspecialchars($user['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Deadline -->
                <div>
                    <label for="deadline" class="block text-sm font-medium text-gray-700 mb-2">
                        Deadline <span class="text-red-500">*</span>
                        <?php if (!$can_edit_status): ?>
                            <span class="text-xs text-gray-500">(Hanya dapat diedit oleh Admin, Supervisor, atau Penanggung Jawab)</span>
                        <?php endif; ?>
                    </label>
                    <?php if ($can_edit_status): ?>
                        <input type="date" 
                               id="deadline" 
                               name="deadline" 
                               value="<?php echo date('Y-m-d', strtotime($maintenance['deadline'])); ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <?php else: ?>
                        <input type="text" 
                               value="<?php echo date('d/m/Y', strtotime($maintenance['deadline'])); ?>"
                               readonly 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed">
                        <input type="hidden" name="deadline" value="<?php echo date('Y-m-d', strtotime($maintenance['deadline'])); ?>">
                    <?php endif; ?>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status <span class="text-red-500">*</span>
                        <?php if (!$can_edit_status): ?>
                            <span class="text-xs text-gray-500">(Hanya dapat diedit oleh Admin, Supervisor, atau Penanggung Jawab)</span>
                        <?php endif; ?>
                    </label>
                    <?php if ($can_edit_status): ?>
                        <select id="status" 
                                name="status" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="maintenance" <?php echo ($maintenance['status'] === 'maintenance') ? 'selected' : ''; ?>>
                                Maintenance
                            </option>
                            <option value="pending_verification" <?php echo ($maintenance['status'] === 'pending_verification') ? 'selected' : ''; ?>>
                                Pending Verifikasi
                            </option>
                            <option value="completed" <?php echo ($maintenance['status'] === 'completed') ? 'selected' : ''; ?>>
                                Selesai
                            </option>
                        </select>
                    <?php else: ?>
                        <input type="text" 
                               value="<?php 
                                   switch($maintenance['status']) {
                                       case 'maintenance': echo 'Maintenance'; break;
                                       case 'pending_verification': echo 'Pending Verifikasi'; break;
                                       case 'completed': echo 'Selesai'; break;
                                       default: echo ucfirst($maintenance['status']);
                                   }
                               ?>" 
                               readonly 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($maintenance['status']); ?>">
                    <?php endif; ?>
                </div>

                <!-- Keterangan -->
                <div class="md:col-span-2">
                    <label for="keterangan" class="block text-sm font-medium text-gray-700 mb-2">
                        Keterangan
                    </label>
                    <textarea id="keterangan" 
                              name="keterangan" 
                              rows="4"
                              placeholder="Keterangan tambahan tentang maintenance ini..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"><?php echo htmlspecialchars($maintenance['keterangan']); ?></textarea>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-4">
                <a href="website-maintenance-detail.php?id=<?php echo $maintenance_id; ?>" 
                   class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>
                    Batal
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-save mr-2"></i>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <!-- Additional Information -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-md p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Informasi Status</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Maintenance:</strong> Website sedang dalam proses maintenance</li>
                        <li><strong>Pending Verifikasi:</strong> Maintenance selesai, menunggu verifikasi admin</li>
                        <li><strong>Selesai:</strong> Maintenance telah selesai dan diverifikasi</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Auto-fill nama desa when selecting from dropdown
document.getElementById('desa_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const namaDesa = selectedOption.getAttribute('data-nama');
    
    if (namaDesa) {
        document.getElementById('nama_desa').value = namaDesa;
    }
});

// Clear desa_id when manually typing nama_desa
document.getElementById('nama_desa').addEventListener('input', function() {
    const selectedOption = document.getElementById('desa_id').options[document.getElementById('desa_id').selectedIndex];
    const selectedNama = selectedOption.getAttribute('data-nama');
    
    if (this.value !== selectedNama) {
        document.getElementById('desa_id').value = '';
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>