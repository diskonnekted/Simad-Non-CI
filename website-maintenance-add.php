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
$page_title = 'Tambah Website Maintenance';

// Initialize database
$db = new Database();
$pdo = $db->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desa_id = isset($_POST['desa_id']) ? (int)$_POST['desa_id'] : 0;
    $nama_desa = trim($_POST['nama_desa']);
    $website_url = trim($_POST['website_url']);
    $penanggung_jawab_id = isset($_POST['penanggung_jawab_id']) ? (int)$_POST['penanggung_jawab_id'] : 0;
    $programmer_id = isset($_POST['programmer_id']) ? (int)$_POST['programmer_id'] : 0;
    $deadline = $_POST['deadline'];
    $assignment_type = $_POST['assignment_type'];
    $keterangan = trim($_POST['keterangan']);
    
    $errors = [];
    
    // Validation
    if (empty($website_url)) {
        $errors[] = "Website URL harus diisi.";
    } elseif (!filter_var($website_url, FILTER_VALIDATE_URL)) {
        $errors[] = "Format website URL tidak valid.";
    }
    
    // Validate desa_id if selected
    if ($desa_id > 0) {
        $check_desa_stmt = $pdo->prepare("SELECT id FROM desa WHERE id = ?");
        $check_desa_stmt->execute([$desa_id]);
        if (!$check_desa_stmt->fetch()) {
            $errors[] = "Desa yang dipilih tidak valid.";
        }
    }
    
    if (empty($penanggung_jawab_id)) {
        $errors[] = "Penanggung jawab harus dipilih.";
    }
    
    if (empty($programmer_id)) {
        $errors[] = "Programmer harus dipilih.";
    }
    
    if (empty($deadline)) {
        $errors[] = "Deadline harus diisi.";
    } elseif (strtotime($deadline) < strtotime('today')) {
        $errors[] = "Deadline tidak boleh kurang dari hari ini.";
    }
    
    if (empty($assignment_type)) {
        $errors[] = "Jenis penugasan harus dipilih.";
    }
    
    // Check if website URL already exists
    if (empty($errors)) {
        $check_stmt = $pdo->prepare("SELECT id FROM website_maintenance WHERE website_url = ?");
        $check_stmt->execute([$website_url]);
        if ($check_stmt->fetch()) {
            $errors[] = "Website URL sudah terdaftar dalam maintenance.";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Check if we need to create a new desa record
            if ($desa_id == 0) {
                // Check if desa with this name already exists
                $check_desa_name_stmt = $pdo->prepare("SELECT id FROM desa WHERE nama_desa = ?");
                $check_desa_name_stmt->execute([$nama_desa]);
                $existing_desa = $check_desa_name_stmt->fetch();
                
                if ($existing_desa) {
                    // Use existing desa_id
                    $desa_id_value = $existing_desa['id'];
                } else {
                    // Create new desa record
                    $insert_desa_stmt = $pdo->prepare("INSERT INTO desa (nama_desa, created_at) VALUES (?, NOW())");
                    $insert_desa_stmt->execute([$nama_desa]);
                    $desa_id_value = $pdo->lastInsertId();
                }
            } else {
                // Use selected desa_id
                $desa_id_value = $desa_id;
            }
            
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
            
            $stmt = $pdo->prepare("INSERT INTO website_maintenance (desa_id, nama_desa, website_url, penanggung_jawab_id, programmer_id, penanggung_jawab, programmer, deadline, assignment_type, keterangan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'maintenance')");
            $stmt->execute([$desa_id_value, $nama_desa, $website_url, $penanggung_jawab_id, $programmer_id, $penanggung_jawab, $programmer, $deadline, $assignment_type, $keterangan]);
            
            $maintenance_id = $pdo->lastInsertId();
            
            // Create checklist based on assignment type
            if ($assignment_type == 'instalasi_sid') {
                // Use existing detailed checklist for Instalasi SID
                $checklist_items = [
                    'Persiapan server dan domain',
                    'Download dan ekstrak aplikasi SID',
                    'Konfigurasi database',
                    'Instalasi aplikasi SID',
                    'Konfigurasi dasar sistem',
                    'Import data desa',
                    'Testing fungsionalitas',
                    'Training pengguna',
                    'Dokumentasi dan serah terima'
                ];
                
                foreach ($checklist_items as $index => $item) {
                    $stmt_checklist = $pdo->prepare("INSERT INTO maintenance_checklist (maintenance_id, item_name, item_order, status) VALUES (?, ?, ?, 'pending')");
                    $stmt_checklist->execute([$maintenance_id, $item, $index + 1]);
                }
            } else {
                // Use simple checklist for other assignment types
                $simple_checklist = [
                    'Diterima',
                    'Diproses', 
                    'Selesai'
                ];
                
                foreach ($simple_checklist as $index => $item) {
                    $stmt_checklist = $pdo->prepare("INSERT INTO maintenance_checklist_simple (maintenance_id, item_name, item_order, status) VALUES (?, ?, ?, 'pending')");
                    $stmt_checklist->execute([$maintenance_id, $item, $index + 1]);
                }
            }
            
            $pdo->commit();
            
            header('Location: website-maintenance-detail.php?id=' . $maintenance_id);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
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

// Get current user for header
$user = AuthStatic::getCurrentUser();

// Include header
require_once 'layouts/header.php';
?>

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <a href="website-maintenance.php" 
               class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tambah Website Maintenance</h1>
                <p class="mt-2 text-gray-600">Tambahkan website desa baru untuk proses maintenance</p>
            </div>
        </div>
    </div>

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
            <h3 class="text-lg font-medium text-gray-900">Informasi Website Maintenance</h3>
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
                                    <?php echo (isset($_POST['desa_id']) && $_POST['desa_id'] == $desa['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($desa['nama_desa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Pilih desa dari database atau isi manual di field nama desa</p>
                </div>

                <!-- Nama Desa -->
                <div>
                    <label for="nama_desa" class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Desa
                    </label>
                    <input type="text" 
                           id="nama_desa" 
                           name="nama_desa" 
                           value="<?php echo isset($_POST['nama_desa']) ? htmlspecialchars($_POST['nama_desa']) : ''; ?>"
                           placeholder="Masukkan nama desa"

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
                           value="<?php echo isset($_POST['website_url']) ? htmlspecialchars($_POST['website_url']) : ''; ?>"
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
                                    <?php echo (isset($_POST['penanggung_jawab_id']) && $_POST['penanggung_jawab_id'] == $user['id']) ? 'selected' : ''; ?>>
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
                                    <?php echo (isset($_POST['programmer_id']) && $_POST['programmer_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['nama_lengkap']); ?> (<?php echo htmlspecialchars($user['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Deadline -->
                <div>
                    <label for="deadline" class="block text-sm font-medium text-gray-700 mb-2">
                        Deadline <span class="text-red-500">*</span>
                    </label>
                    <input type="date" 
                           id="deadline" 
                           name="deadline" 
                           value="<?php echo isset($_POST['deadline']) ? $_POST['deadline'] : ''; ?>"
                           min="<?php echo date('Y-m-d'); ?>"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>

                <!-- Jenis Penugasan -->
                <div>
                    <label for="assignment_type" class="block text-sm font-medium text-gray-700 mb-2">
                        Jenis Penugasan <span class="text-red-500">*</span>
                    </label>
                    <select id="assignment_type" 
                            name="assignment_type" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Pilih Jenis Penugasan</option>
                        <option value="instalasi_sid" <?php echo (isset($_POST['assignment_type']) && $_POST['assignment_type'] == 'instalasi_sid') ? 'selected' : ''; ?>>Instalasi SID</option>
                        <option value="perbaikan_error_404_505" <?php echo (isset($_POST['assignment_type']) && $_POST['assignment_type'] == 'perbaikan_error_404_505') ? 'selected' : ''; ?>>Perbaikan Error 404/505</option>
                        <option value="update_versi_aplikasi" <?php echo (isset($_POST['assignment_type']) && $_POST['assignment_type'] == 'update_versi_aplikasi') ? 'selected' : ''; ?>>Update Versi Aplikasi</option>
                        <option value="perbaikan_ssl" <?php echo (isset($_POST['assignment_type']) && $_POST['assignment_type'] == 'perbaikan_ssl') ? 'selected' : ''; ?>>Perbaikan SSL</option>
                        <option value="pemindahan_hosting_server" <?php echo (isset($_POST['assignment_type']) && $_POST['assignment_type'] == 'pemindahan_hosting_server') ? 'selected' : ''; ?>>Pemindahan Hosting Server</option>
                        <option value="maintenance_lainnya" <?php echo (isset($_POST['assignment_type']) && $_POST['assignment_type'] == 'maintenance_lainnya') ? 'selected' : ''; ?>>Maintenance Lainnya</option>
                    </select>
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
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"><?php echo isset($_POST['keterangan']) ? htmlspecialchars($_POST['keterangan']) : ''; ?></textarea>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-4">
                <a href="website-maintenance.php" 
                   class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>
                    Batal
                </a>
                <button type="button" 
                        id="generateContractBtn"
                        class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-file-contract mr-2"></i>
                    Generate Surat Kontrak
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-save mr-2"></i>
                    Simpan Maintenance
                </button>
            </div>
        </form>
    </div>
</main>

<script>
// Auto-fill nama desa and website URL when selecting from dropdown
document.getElementById('desa_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const namaDesa = selectedOption.getAttribute('data-nama');
    const desaId = this.value;
    
    if (namaDesa && desaId) {
        // Fill nama desa
        document.getElementById('nama_desa').value = namaDesa;
        
        // Fetch website URL from API
        fetch(`api/get-website-url.php?desa_id=${desaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.website_url) {
                    document.getElementById('website_url').value = data.website_url;
                } else {
                    // Clear website URL if no website found
                    document.getElementById('website_url').value = '';
                }
            })
            .catch(error => {
                console.error('Error fetching website URL:', error);
                // Clear website URL on error
                document.getElementById('website_url').value = '';
            });
    } else {
        document.getElementById('nama_desa').value = '';
        document.getElementById('website_url').value = '';
    }
});

// Clear desa_id when manually typing nama_desa
document.getElementById('nama_desa').addEventListener('input', function() {
    if (this.value !== document.getElementById('desa_id').options[document.getElementById('desa_id').selectedIndex].getAttribute('data-nama')) {
        document.getElementById('desa_id').value = '';
    }
});

// Handle Generate Contract button
document.getElementById('generateContractBtn').addEventListener('click', function() {
    // Validate required fields
    const namaDesa = document.getElementById('nama_desa').value.trim();
    const websiteUrl = document.getElementById('website_url').value.trim();
    const penanggungJawabId = document.getElementById('penanggung_jawab_id').value;
    const programmerId = document.getElementById('programmer_id').value;
    const deadline = document.getElementById('deadline').value;
    
    if (!namaDesa || !websiteUrl || !penanggungJawabId || !programmerId || !deadline) {
        alert('Mohon lengkapi semua field yang wajib diisi sebelum generate kontrak.');
        return;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('nama_desa', namaDesa);
    formData.append('website_url', websiteUrl);
    formData.append('penanggung_jawab_id', penanggungJawabId);
    formData.append('programmer_id', programmerId);
    formData.append('deadline', deadline);
    formData.append('keterangan', document.getElementById('keterangan').value.trim());
    
    // Open contract in new window
    const url = 'generate-contract.php?' + new URLSearchParams({
        nama_desa: namaDesa,
        website_url: websiteUrl,
        penanggung_jawab_id: penanggungJawabId,
        programmer_id: programmerId,
        deadline: deadline,
        keterangan: document.getElementById('keterangan').value.trim()
    });
    
    window.open(url, '_blank');
});
</script>

<?php require_once 'layouts/footer.php'; ?>