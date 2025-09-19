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
$page_title = 'Detail Website Maintenance';

// Initialize database
$db = new Database();
$pdo = $db->getConnection();

// Get maintenance ID
$maintenance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$maintenance_id) {
    header('Location: website-maintenance.php');
    exit;
}

// Handle admin message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_admin_message'])) {
        try {
            // Only admin can send messages
            if (AuthStatic::hasRole(['admin'])) {
                $message = trim($_POST['admin_message']);
                if (!empty($message)) {
                    $current_user = AuthStatic::getCurrentUser();
                    $stmt = $pdo->prepare("INSERT INTO admin_messages (maintenance_id, admin_id, message) VALUES (?, ?, ?)");
                    $stmt->execute([$maintenance_id, $current_user['id'], $message]);
                    $success_message = "Pesan berhasil dikirim ke programmer!";
                } else {
                    $error_message = "Pesan tidak boleh kosong.";
                }
            } else {
                $error_message = "Hanya admin yang dapat mengirim pesan.";
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    } elseif (isset($_POST['send_programmer_reply'])) {
        try {
            // Only programmer can send replies
            if (AuthStatic::hasRole(['programmer'])) {
                $reply = trim($_POST['programmer_reply']);
                $admin_message_id = (int)$_POST['admin_message_id'];
                if (!empty($reply) && $admin_message_id > 0) {
                    $current_user = AuthStatic::getCurrentUser();
                    $stmt = $pdo->prepare("INSERT INTO programmer_replies (admin_message_id, programmer_id, reply) VALUES (?, ?, ?)");
                    $stmt->execute([$admin_message_id, $current_user['id'], $reply]);
                    $success_message = "Balasan berhasil dikirim ke admin!";
                } else {
                    $error_message = "Balasan tidak boleh kosong.";
                }
            } else {
                $error_message = "Hanya programmer yang dapat mengirim balasan.";
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_checklist'])) {
        try {
            $pdo->beginTransaction();
            
            // Get maintenance data to determine assignment type
            $maintenance_stmt = $pdo->prepare("SELECT assignment_type FROM website_maintenance WHERE id = ?");
            $maintenance_stmt->execute([$maintenance_id]);
            $maintenance_data = $maintenance_stmt->fetch();
            
            // Update checklist items based on assignment type
            if ($maintenance_data['assignment_type'] === 'instalasi_sid') {
                $checklist_items = [
                    'install_website',
                    'setup_info_desa',
                    'import_database',
                    'menu_standar',
                    'foto_gambar',
                    'berita_dummy',
                    'no_404_page',
                    'no_505_page',
                    'sinkron_opendata',
                    'domain_resmi_kominfo',
                    'cek_fitur_surat_cetak',
                    'copy_template_surat',
                    'rubah_foto_background_login',
                    'rubah_foto_profil_desa',
                    'cek_semua_fitur',
                    'hidupkan_fitur_banner'
                ];
            } else {
                $checklist_items = [
                    'pengecekan',
                    'proses',
                    'selesai'
                ];
            }
            
            // Check if record exists for this maintenance_id
            $check_stmt = $pdo->prepare("SELECT id FROM maintenance_checklist WHERE maintenance_id = ?");
            $check_stmt->execute([$maintenance_id]);
            $existing_record = $check_stmt->fetch();
            
            // Prepare update data
            $update_data = [];
            foreach ($checklist_items as $item) {
                $update_data[$item] = isset($_POST[$item]) ? 1 : 0;
            }
            
            if ($existing_record) {
                // Update existing record
                $update_fields = [];
                $update_values = [];
                foreach ($update_data as $field => $value) {
                    $update_fields[] = "$field = ?";
                    $update_values[] = $value;
                }
                $update_values[] = $maintenance_id;
                
                $update_sql = "UPDATE maintenance_checklist SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE maintenance_id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute($update_values);
            } else {
                // Insert new record
                $insert_fields = array_keys($update_data);
                $insert_values = array_values($update_data);
                array_unshift($insert_fields, 'maintenance_id');
                array_unshift($insert_values, $maintenance_id);
                
                $placeholders = str_repeat('?,', count($insert_fields) - 1) . '?';
                $insert_sql = "INSERT INTO maintenance_checklist (" . implode(', ', $insert_fields) . ") VALUES ($placeholders)";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute($insert_values);
            }
            
            $pdo->commit();
            $success_message = "Checklist berhasil diperbarui!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    } elseif (isset($_POST['submit_verification'])) {
        try {
            // Check if all required items are completed based on assignment type
            if ($maintenance['assignment_type'] === 'instalasi_sid') {
                $required_items = [
                    'install_website',
                    'setup_info_desa',
                    'import_database',
                    'menu_standar',
                    'foto_gambar',
                    'berita_dummy',
                    'no_404_page',
                    'no_505_page',
                    'sinkron_opendata',
                    'domain_resmi_kominfo'
                ];
            } else {
                $required_items = [
                    'pengecekan',
                    'proses',
                    'selesai'
                ];
            }
            
            $completed_count = 0;
            foreach ($required_items as $item) {
                if (isset($_POST[$item])) {
                    $completed_count++;
                }
            }
            
            if ($completed_count === count($required_items)) {
                // All items completed, update status to pending verification
                $update_stmt = $pdo->prepare("UPDATE website_maintenance SET status = 'pending_verification', updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$maintenance_id]);
                
                $success_message = "Website berhasil disubmit untuk verifikasi admin!";
            } else {
                $error_message = "Semua checklist harus diselesaikan sebelum submit untuk verifikasi.";
            }
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get maintenance data
$stmt = $pdo->prepare("SELECT wm.*, d.nama_desa as desa_name,
                             u1.nama_lengkap as penanggung_jawab_nama,
                             u2.nama_lengkap as programmer_nama
                      FROM website_maintenance wm 
                      LEFT JOIN desa d ON wm.desa_id = d.id 
                      LEFT JOIN users u1 ON wm.penanggung_jawab_id = u1.id
                      LEFT JOIN users u2 ON wm.programmer_id = u2.id
                      WHERE wm.id = ?");
$stmt->execute([$maintenance_id]);
$maintenance = $stmt->fetch();

if (!$maintenance) {
    header('Location: website-maintenance.php');
    exit;
}

// Get checklist data
$checklist_stmt = $pdo->prepare("SELECT * FROM maintenance_checklist WHERE maintenance_id = ?");
$checklist_stmt->execute([$maintenance_id]);
$checklist_row = $checklist_stmt->fetch();
$checklist_data = $checklist_row ? $checklist_row : [];

// Get admin messages and programmer replies
// Filter communication based on user role
$current_user = AuthStatic::getCurrentUser();
$messages_query = "
    SELECT am.*, u.nama_lengkap as admin_name,
           pr.id as reply_id, pr.reply, pr.updated_at as reply_created_at,
           u2.nama_lengkap as programmer_name
    FROM admin_messages am
    LEFT JOIN users u ON am.admin_id = u.id
    LEFT JOIN programmer_replies pr ON am.id = pr.admin_message_id
    LEFT JOIN users u2 ON pr.programmer_id = u2.id
    WHERE am.maintenance_id = ?";

// If user is programmer, only show messages for maintenance assigned to them
if (AuthStatic::hasRole(['programmer']) && !AuthStatic::hasRole(['admin'])) {
    // Check if this programmer is assigned to this maintenance
    if ($maintenance['programmer_id'] != $current_user['id']) {
        // This programmer is not assigned to this maintenance, show no messages
        $messages_raw = [];
    } else {
        // This programmer is assigned, show all messages for this maintenance
        $messages_query .= " ORDER BY am.created_at ASC, pr.created_at ASC";
        $messages_stmt = $pdo->prepare($messages_query);
        $messages_stmt->execute([$maintenance_id]);
        $messages_raw = $messages_stmt->fetchAll();
    }
} else {
    // Admin and supervisor can see all messages
    $messages_query .= " ORDER BY am.created_at ASC, pr.created_at ASC";
    $messages_stmt = $pdo->prepare($messages_query);
    $messages_stmt->execute([$maintenance_id]);
    $messages_raw = $messages_stmt->fetchAll();
}

// Group messages and replies
$admin_messages = [];
foreach ($messages_raw as $row) {
    $message_id = $row['id'];
    if (!isset($admin_messages[$message_id])) {
        $admin_messages[$message_id] = [
            'id' => $row['id'],
            'message' => $row['message'],
            'admin_name' => $row['admin_name'],
            'created_at' => $row['created_at'],
            'replies' => []
        ];
    }
    
    if ($row['reply_id']) {
        $admin_messages[$message_id]['replies'][] = [
            'id' => $row['reply_id'],
            'reply' => $row['reply'],
            'programmer_name' => $row['programmer_name'],
            'created_at' => $row['reply_created_at']
        ];
    }
}

// Define checklist items based on assignment type
if ($maintenance['assignment_type'] === 'instalasi_sid') {
    // Full checklist for SID installation
    $checklist_items = [
        'install_website' => 'Install Website',
        'setup_info_desa' => 'Setup Info Desa',
        'import_database' => 'Import Database',
        'menu_standar' => 'Pembuatan Menu Standar',
        'foto_gambar' => 'Foto dan Gambar Pengisi',
        'berita_dummy' => 'Berita Dummy Pengisi Artikel (Minimal 5 Berita)',
        'no_404_page' => 'Tidak Ada Halaman 404',
        'no_505_page' => 'Tidak Ada Halaman 505',
        'sinkron_opendata' => 'Sinkron OpenData',
        'domain_resmi_kominfo' => 'Domain Resmi Kominfo (Opsional)',
        'cek_fitur_surat_cetak' => 'Cek Fitur Surat Cetak',
        'copy_template_surat' => 'Copy Template Surat',
        'rubah_foto_background_login' => 'Rubah Foto Background Login',
        'rubah_foto_profil_desa' => 'Rubah Foto Profil Desa',
        'cek_semua_fitur' => 'Cek Semua Fitur',
        'hidupkan_fitur_banner' => 'Hidupkan Fitur Banner'
    ];
    
    // Define optional items (tidak masuk perhitungan persentase)
    $optional_items = ['domain_resmi_kominfo'];
} else {
    // Simple checklist for other maintenance types
    $checklist_items = [
        'pengecekan' => 'Pengecekan',
        'proses' => 'Proses',
        'selesai' => 'Selesai'
    ];
    
    // No optional items for simple checklist
    $optional_items = [];
}

// Calculate completion percentage (excluding optional items)
$completed_items = 0;
$total_items = 0;

foreach ($checklist_items as $key => $label) {
    // Skip optional items from calculation
    if (!in_array($key, $optional_items)) {
        $total_items++;
        if (isset($checklist_data[$key]) && $checklist_data[$key] == 1) {
            $completed_items++;
        }
    }
}

$completion_percentage = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;

// Include header
require_once 'layouts/header.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="website-maintenance.php" 
                   class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Detail Website Maintenance</h1>
                    <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($maintenance['nama_desa']); ?></p>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="website-maintenance-edit.php?id=<?php echo $maintenance['id']; ?>" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-edit mr-2"></i>
                    Edit
                </a>
                <a href="generate-contract-detail.php?id=<?php echo $maintenance['id']; ?>" 
                   target="_blank"
                   class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md font-medium transition-colors duration-200">
                    <i class="fas fa-file-contract mr-2"></i>
                    Generate Kontrak
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success_message)): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2">
            <!-- Website Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Informasi Website</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Desa</label>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($maintenance['nama_desa']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Maintenance</label>
                            <p class="text-sm text-gray-900">
                                <?php 
                                $assignment_types = [
                                    'instalasi_sid' => 'Instalasi SID',
                                    'perbaikan_error_404_505' => 'Perbaikan Error 404/505',
                                    'update_versi_aplikasi' => 'Update Versi Aplikasi',
                                    'perbaikan_ssl' => 'Perbaikan SSL',
                                    'pemindahan_hosting_server' => 'Pemindahan Hosting Server',
                                    'maintenance_lainnya' => 'Maintenance Lainnya'
                                ];
                                echo htmlspecialchars($assignment_types[$maintenance['assignment_type']] ?? $maintenance['assignment_type']);
                                ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Website URL</label>
                            <p class="text-sm text-gray-900">
                                <a href="<?php echo htmlspecialchars($maintenance['website_url']); ?>" 
                                   target="_blank" 
                                   class="text-primary-600 hover:text-primary-800 hover:underline">
                                    <?php echo htmlspecialchars($maintenance['website_url']); ?>
                                    <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                </a>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Penanggung Jawab</label>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($maintenance['penanggung_jawab_nama'] ?? 'Tidak ada'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Programmer</label>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($maintenance['programmer_nama'] ?? 'Tidak ada'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deadline</label>
                            <p class="text-sm text-gray-900">
                                <?php 
                                $deadline = new DateTime($maintenance['deadline']);
                                $now = new DateTime();
                                $diff = $now->diff($deadline);
                                $is_overdue = $now > $deadline;
                                
                                echo $deadline->format('d F Y');
                                
                                if ($is_overdue && $maintenance['status'] !== 'completed') {
                                    echo '<span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">';
                                    echo '<i class="fas fa-exclamation-triangle mr-1"></i>Terlambat ' . $diff->days . ' hari';
                                    echo '</span>';
                                } elseif ($diff->days <= 3 && $maintenance['status'] !== 'completed') {
                                    echo '<span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">';
                                    echo '<i class="fas fa-clock mr-1"></i>' . $diff->days . ' hari lagi';
                                    echo '</span>';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <p class="text-sm text-gray-900">
                                <?php
                                $status_classes = [
                                    'maintenance' => 'bg-yellow-100 text-yellow-800',
                                    'pending_verification' => 'bg-orange-100 text-orange-800',
                                    'completed' => 'bg-green-100 text-green-800'
                                ];
                                
                                $status_labels = [
                                    'maintenance' => 'Maintenance',
                                    'pending_verification' => 'Pending Verifikasi',
                                    'completed' => 'Selesai'
                                ];
                                
                                $status_icons = [
                                    'maintenance' => 'fas fa-tools',
                                    'pending_verification' => 'fas fa-clock',
                                    'completed' => 'fas fa-check-circle'
                                ];
                                ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $status_classes[$maintenance['status']]; ?>">
                                    <i class="<?php echo $status_icons[$maintenance['status']]; ?> mr-2"></i>
                                    <?php echo $status_labels[$maintenance['status']]; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <?php if (!empty($maintenance['keterangan'])): ?>
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                            <p class="text-sm text-gray-900 bg-gray-50 p-3 rounded-md"><?php echo nl2br(htmlspecialchars($maintenance['keterangan'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Maintenance Checklist -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Checklist Pekerjaan Maintenance</h3>
                        <span class="text-sm text-gray-600"><?php echo $completed_items; ?>/<?php echo $total_items; ?> selesai</span>
                    </div>
                </div>
                <form method="POST" class="p-6">
                    <div class="space-y-6">
                        <?php foreach ($checklist_items as $key => $label): 
                            $is_completed = isset($checklist_data[$key]) ? $checklist_data[$key] == 1 : false;
                            $notes = ''; // Notes functionality removed as per table structure
                        ?>
                            <div class="border border-gray-200 rounded-lg p-4 <?php echo $is_completed ? 'bg-green-50 border-green-200' : 'bg-gray-50'; ?>">
                                <div class="flex items-start space-x-3">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" 
                                               id="<?php echo $key; ?>" 
                                               name="<?php echo $key; ?>" 
                                               value="1"
                                               <?php echo $is_completed ? 'checked' : ''; ?>
                                               <?php echo $maintenance['status'] === 'completed' ? 'disabled' : ''; ?>
                                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                                    </div>
                                    <div class="flex-1">
                                        <label for="<?php echo $key; ?>" class="text-sm font-medium text-gray-900 cursor-pointer">
                                            <?php echo htmlspecialchars($label); ?>
                                            <?php if ($is_completed): ?>
                                                <i class="fas fa-check-circle text-green-600 ml-2"></i>
                                            <?php endif; ?>
                                        </label>
                                        <div class="mt-2">
                                            <textarea name="<?php echo $key; ?>_notes" 
                                                      placeholder="Catatan untuk <?php echo htmlspecialchars($label); ?>..."
                                                      <?php echo $maintenance['status'] === 'completed' ? 'readonly' : ''; ?>
                                                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 <?php echo $maintenance['status'] === 'completed' ? 'bg-gray-100' : ''; ?>"
                                                      rows="2"><?php echo htmlspecialchars($notes); ?></textarea>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($maintenance['status'] !== 'completed'): ?>
                        <div class="mt-8 flex justify-between items-center">
                            <button type="submit" 
                                    name="update_checklist"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Simpan Checklist
                            </button>
                            
                            <?php if ($completion_percentage === 100 && $maintenance['status'] === 'maintenance'): ?>
                                <button type="submit" 
                                        name="submit_verification"
                                        onclick="return confirm('Apakah Anda yakin semua pekerjaan maintenance telah selesai dan siap untuk diverifikasi admin?')"
                                        class="inline-flex items-center px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md font-medium transition-colors duration-200">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Submit untuk Verifikasi
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Admin-Programmer Communication -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Komunikasi Admin - Programmer</h3>
                </div>
                <div class="p-6">
                    <!-- Admin Message Form (Only for Admin) -->
                    <?php if (AuthStatic::hasRole(['admin'])): ?>
                        <div class="mb-6">
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label for="admin_message" class="block text-sm font-medium text-gray-700 mb-2">
                                        Pesan untuk Programmer
                                    </label>
                                    <textarea name="admin_message" 
                                              id="admin_message"
                                              rows="4" 
                                              placeholder="Tulis pesan atau instruksi untuk programmer..."
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                              required></textarea>
                                </div>
                                <button type="submit" 
                                        name="send_admin_message"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium transition-colors duration-200">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Kirim Pesan
                                </button>
                            </form>
                        </div>
                        <hr class="my-6">
                    <?php endif; ?>

                    <!-- Messages Display -->
                    <div class="space-y-6">
                        <?php if (empty($admin_messages)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-comments text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500">Belum ada komunikasi antara admin dan programmer.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($admin_messages as $message): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <!-- Admin Message -->
                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-user-shield text-blue-600"></i>
                                            </div>
                                            <div class="ml-3 flex-1">
                                                <div class="flex items-center justify-between mb-2">
                                                    <h4 class="text-sm font-medium text-blue-800">
                                                        <?php echo htmlspecialchars($message['admin_name']); ?> (Admin)
                                                    </h4>
                                                    <span class="text-xs text-blue-600">
                                                        <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
                                                    </span>
                                                </div>
                                                <p class="text-sm text-blue-700">
                                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Programmer Replies -->
                                    <?php if (!empty($message['replies'])): ?>
                                        <div class="ml-8 space-y-3">
                                            <?php foreach ($message['replies'] as $reply): ?>
                                                <div class="bg-green-50 border-l-4 border-green-400 p-3">
                                                    <div class="flex items-start">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-code text-green-600"></i>
                                                        </div>
                                                        <div class="ml-3 flex-1">
                                                            <div class="flex items-center justify-between mb-2">
                                                                <h5 class="text-sm font-medium text-green-800">
                                                                    <?php echo htmlspecialchars($reply['programmer_name']); ?> (Programmer)
                                                                </h5>
                                                                <span class="text-xs text-green-600">
                                                                    <?php echo date('d/m/Y H:i', strtotime($reply['created_at'])); ?>
                                                                </span>
                                                            </div>
                                                            <p class="text-sm text-green-700">
                                                                <?php echo nl2br(htmlspecialchars($reply['reply'])); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Programmer Reply Form (Only for Programmer) -->
                                    <?php if (AuthStatic::hasRole(['programmer'])): ?>
                                        <div class="ml-8 mt-4">
                                            <form method="POST" class="space-y-3">
                                                <input type="hidden" name="admin_message_id" value="<?php echo $message['id']; ?>">
                                                <div>
                                                    <label for="programmer_reply_<?php echo $message['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">
                                                        Balasan Anda
                                                    </label>
                                                    <textarea name="programmer_reply" 
                                                              id="programmer_reply_<?php echo $message['id']; ?>"
                                                              rows="3" 
                                                              placeholder="Tulis balasan Anda..."
                                                              class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                                              required></textarea>
                                                </div>
                                                <button type="submit" 
                                                        name="send_programmer_reply"
                                                        class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-medium transition-colors duration-200">
                                                    <i class="fas fa-reply mr-2"></i>
                                                    Balas
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Progress Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Progress Maintenance</h3>
                </div>
                <div class="p-6">
                    <div class="text-center mb-4">
                        <div class="relative inline-flex items-center justify-center w-24 h-24">
                            <svg class="w-24 h-24 transform -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="40" stroke="#e5e7eb" stroke-width="8" fill="none"></circle>
                                <circle cx="50" cy="50" r="40" stroke="#10b981" stroke-width="8" fill="none" 
                                        stroke-dasharray="<?php echo 2 * pi() * 40; ?>" 
                                        stroke-dashoffset="<?php echo 2 * pi() * 40 * (1 - $completion_percentage / 100); ?>"
                                        stroke-linecap="round"></circle>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-2xl font-bold text-gray-900"><?php echo $completion_percentage; ?>%</span>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2"><?php echo $completed_items; ?> dari <?php echo $total_items; ?> tugas selesai</p>
                    </div>
                    
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Selesai</span>
                            <span class="font-medium text-green-600"><?php echo $completed_items; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Belum Selesai</span>
                            <span class="font-medium text-gray-900"><?php echo $total_items - $completed_items; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total Tugas</span>
                            <span class="font-medium text-gray-900"><?php echo $total_items; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Item Opsional</span>
                            <span class="font-medium text-blue-600"><?php echo count($optional_items); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Timeline</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-calendar-plus text-blue-600 text-sm"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Maintenance Dimulai</p>
                                <p class="text-xs text-gray-500"><?php echo date('d F Y', strtotime($maintenance['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($maintenance['status'] === 'pending_verification' || $maintenance['status'] === 'completed'): ?>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-clock text-orange-600 text-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">Submit Verifikasi</p>
                                    <p class="text-xs text-gray-500"><?php echo date('d F Y', strtotime($maintenance['updated_at'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($maintenance['status'] === 'completed'): ?>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-check-circle text-green-600 text-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">Maintenance Selesai</p>
                                    <p class="text-xs text-gray-500"><?php echo date('d F Y', strtotime($maintenance['updated_at'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-flag-checkered text-gray-600 text-sm"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Target Deadline</p>
                                <p class="text-xs text-gray-500"><?php echo date('d F Y', strtotime($maintenance['deadline'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($completion_percentage > 0): ?>
            <!-- Download Report Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Laporan Penyelesaian</h3>
                </div>
                <div class="p-6">
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="fas fa-file-pdf text-red-500 text-4xl mb-2"></i>
                            <h4 class="text-lg font-medium text-gray-900">Laporan Progress Maintenance</h4>
                            <p class="text-sm text-gray-600 mt-1">Download laporan PDF progress saat ini (<?php echo $completion_percentage; ?>% selesai)</p>
                        </div>
                        <a href="download-maintenance-report.php?id=<?php echo $maintenance['id']; ?>" 
                           class="inline-flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-md font-medium transition-colors duration-200">
                            <i class="fas fa-download mr-2"></i>
                            Download Laporan PDF
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'layouts/footer.php'; ?>