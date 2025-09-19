<?php
/**
 * Purchase Process Indicator Component
 * Menampilkan indikator proses pembelian dari PO hingga stok masuk
 * 
 * @param array $pembelian Data pembelian dari database
 * @param array $penerimaan_data Data penerimaan (optional)
 */

function renderPurchaseProcessIndicator($pembelian, $penerimaan_data = null) {
    // Tentukan tahap berdasarkan status
    $current_step = getCurrentStep($pembelian, $penerimaan_data);
    $steps = getPurchaseSteps();
    
    echo '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">';
    echo '<div class="bg-white rounded-lg shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-shadow duration-300">';
    echo '<div class="flex items-center justify-between mb-6">';
    echo '<h3 class="text-lg font-semibold text-gray-900">Status Proses Pembelian</h3>';
    $nomor_po = isset($pembelian['nomor_po']) ? htmlspecialchars($pembelian['nomor_po']) : 'N/A';
    echo '<span class="text-sm text-gray-500">PO: ' . $nomor_po . '</span>';
    echo '</div>';
    
    // Progress bar
    $progress_percentage = (($current_step - 1) / (count($steps) - 1)) * 100;
    echo '<div class="mb-8">';
    echo '<div class="flex justify-between text-xs text-gray-600 mb-2">';
    echo '<span>Mulai</span>';
    echo '<span>' . round($progress_percentage) . '%</span>';
    echo '<span>Selesai</span>';
    echo '</div>';
    echo '<div class="w-full bg-gray-200 rounded-full h-2">';
    echo '<div class="progress-bar h-2 rounded-full" style="width: ' . $progress_percentage . '%"></div>';
    echo '</div>';
    echo '</div>';
    
    // Horizontal Timeline Steps
    echo '<div class="relative overflow-x-auto py-4">';
    echo '<div class="flex justify-between items-start relative min-w-max">';
    
    // Timeline line
    echo '<div class="absolute top-8 left-0 right-0 h-1 bg-gray-200 rounded-full z-0"></div>';
    echo '<div class="absolute top-8 left-0 h-1 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full z-10 transition-all duration-700 ease-in-out" style="width: ' . $progress_percentage . '%"></div>';
    
    foreach ($steps as $index => $step) {
        $step_number = $index + 1;
        $is_completed = $step_number <= $current_step;
        $is_current = $step_number == $current_step;
        
        renderHorizontalStep($step, $step_number, $is_completed, $is_current, $pembelian, $index, count($steps));
    }
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
}

function getCurrentStep($pembelian, $penerimaan_data) {
    // Step 1: PO Creation (selalu selesai jika PO ada)
    if (empty($pembelian)) return 1;
    
    // Pastikan $pembelian adalah array, bukan integer
    if (is_numeric($pembelian)) {
        return 1; // Return step 1 jika hanya ID yang diberikan
    }
    
    // Pastikan key 'status_pembelian' ada
    if (!isset($pembelian['status_pembelian'])) {
        return 1;
    }
    
    // Step 2: PO Sent
    if ($pembelian['status_pembelian'] == 'draft') return 1;
    
    // Step 3: Goods Received
    if ($pembelian['status_pembelian'] == 'dikirim') return 2;
    
    // Step 4: Stock Updated
    if ($pembelian['status_pembelian'] == 'diterima_sebagian') return 3;
    
    // Step 5: Process Complete
    if ($pembelian['status_pembelian'] == 'diterima_lengkap') {
        // Cek apakah semua item sudah diterima dan stok terupdate
        return 5; // Assume complete jika status diterima_lengkap
    }
    
    if ($pembelian['status_pembelian'] == 'dibatalkan') return 1; // Reset ke awal jika dibatalkan
    
    return 1;
}

function getPurchaseSteps() {
    return [
        [
            'title' => 'PO Creation',
            'description' => 'Purchase Order dibuat dan disetujui',
            'icon' => 'fas fa-file-alt',
            'action' => null
        ],
        [
            'title' => 'PO Sent',
            'description' => 'PO dikirim ke vendor',
            'icon' => 'fas fa-paper-plane',
            'action' => 'update_status_dikirim'
        ],
        [
            'title' => 'Goods Received',
            'description' => 'Barang diterima dari vendor',
            'icon' => 'fas fa-truck',
            'action' => 'create_penerimaan'
        ],
        [
            'title' => 'Stock Updated',
            'description' => 'Stok produk diperbarui otomatis',
            'icon' => 'fas fa-boxes',
            'action' => null
        ],
        [
            'title' => 'Process Complete',
            'description' => 'Proses pembelian selesai',
            'icon' => 'fas fa-check-circle',
            'action' => null
        ]
    ];
}

function renderStep($step, $step_number, $is_completed, $is_current, $pembelian) {
    $status_class = '';
    $icon_class = '';
    $text_class = '';
    
    if ($is_completed) {
        $status_class = 'process-step completed';
        $icon_class = 'text-green-600';
        $text_class = 'text-green-800';
    } elseif ($is_current) {
        $status_class = 'process-step current';
        $icon_class = 'text-blue-600';
        $text_class = 'text-blue-800';
    } else {
        $status_class = 'process-step pending';
        $icon_class = 'text-gray-400';
        $text_class = 'text-gray-600';
    }
    
    echo '<div class="flex items-start space-x-4 p-4 rounded-lg border-2 ' . $status_class . '">';
    
    // Step number/icon
    echo '<div class="flex-shrink-0">';
    if ($is_completed) {
        echo '<div class="w-8 h-8 step-indicator completed rounded-full flex items-center justify-center">';
        echo '<i class="fas fa-check text-white text-sm"></i>';
        echo '</div>';
    } elseif ($is_current) {
        echo '<div class="w-8 h-8 step-indicator current rounded-full flex items-center justify-center text-white">';
        echo '<span class="text-sm font-semibold">' . $step_number . '</span>';
        echo '</div>';
    } else {
        echo '<div class="w-8 h-8 bg-white border-2 border-current rounded-full flex items-center justify-center ' . $icon_class . '">';
        echo '<span class="text-sm font-semibold">' . $step_number . '</span>';
        echo '</div>';
    }
    echo '</div>';
    
    // Step content
    echo '<div class="flex-1 min-w-0">';
    echo '<div class="flex items-center justify-between">';
    echo '<div>';
    echo '<h4 class="text-sm font-semibold ' . $text_class . '">';
    echo '<i class="' . $step['icon'] . ' mr-2"></i>';
    echo htmlspecialchars($step['title']);
    echo '</h4>';
    echo '<p class="text-xs ' . $text_class . ' mt-1">' . htmlspecialchars($step['description']) . '</p>';
    echo '</div>';
    
    // Action button
    if ($is_current && $step['action'] && canPerformAction($step['action'], $pembelian)) {
        echo '<div class="ml-4">';
        echo getActionButton($step['action'], $pembelian);
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
}

function renderHorizontalStep($step, $step_number, $is_completed, $is_current, $pembelian, $index, $total_steps) {
    $icon_class = '';
    $text_class = '';
    $bg_class = '';
    
    if ($is_completed) {
        $icon_class = 'text-white';
        $text_class = 'text-green-700';
        $bg_class = 'bg-green-500';
    } elseif ($is_current) {
        $icon_class = 'text-white';
        $text_class = 'text-blue-700';
        $bg_class = 'bg-blue-500';
    } else {
        $icon_class = 'text-gray-400';
        $text_class = 'text-gray-500';
        $bg_class = 'bg-gray-200';
    }
    
    $width_class = 'flex-1';
    if ($total_steps <= 5) {
        $width_class = 'w-1/5';
    }
    
    echo '<div class="' . $width_class . ' flex flex-col items-center relative z-20 px-2">';
    
    // Step circle
    echo '<div class="w-16 h-16 ' . $bg_class . ' rounded-full flex items-center justify-center mb-4 shadow-lg border-4 border-white transition-all duration-300 hover:scale-110 hover:shadow-xl">';
    if ($is_completed) {
        echo '<i class="fas fa-check ' . $icon_class . ' text-lg"></i>';
    } else {
        echo '<i class="' . $step['icon'] . ' ' . $icon_class . ' text-lg"></i>';
    }
    echo '</div>';
    
    // Step content
    echo '<div class="text-center max-w-32">';
    echo '<h4 class="text-sm font-bold ' . $text_class . ' mb-2 tracking-wide">' . htmlspecialchars($step['title']) . '</h4>';
    echo '<p class="text-xs ' . $text_class . ' leading-relaxed opacity-90">' . htmlspecialchars($step['description']) . '</p>';
    
    // Action button for current step
    if ($is_current && $step['action'] && canPerformAction($step['action'], $pembelian)) {
        echo '<div class="mt-3">';
        echo getCompactActionButton($step['action'], $pembelian);
        echo '</div>';
    }
    echo '</div>';
    
    echo '</div>';
}

function canPerformAction($action, $pembelian) {
    global $user;
    
    // Cek role permission
    if (!AuthStatic::hasRole(['admin', 'akunting'])) {
        return false;
    }
    
    // Pastikan $pembelian adalah array dan memiliki key yang diperlukan
    if (!is_array($pembelian) || !isset($pembelian['status_pembelian'])) {
        return false;
    }
    
    switch ($action) {
        case 'update_status_dikirim':
            return $pembelian['status_pembelian'] == 'draft';
        case 'create_penerimaan':
            return in_array($pembelian['status_pembelian'], ['dikirim', 'diterima_sebagian']);
        case 'update_stock':
            return in_array($pembelian['status_pembelian'], ['diterima_sebagian', 'diterima_lengkap']);
        case 'view_stock':
        case 'process_complete':
            return true;
        default:
            return false;
    }
}

function getActionButton($action, $pembelian) {
    $buttons = '';
    
    // Pastikan $pembelian memiliki key 'id'
    if (!is_array($pembelian) || !isset($pembelian['id'])) {
        return '';
    }
    
    switch ($action) {
        case 'update_status_dikirim':
            $buttons .= '<div class="flex flex-wrap gap-2">';
            $buttons .= '<a href="pembelian-edit.php?id=' . $pembelian['id'] . '" class="action-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"><i class="fas fa-edit mr-2"></i>Edit PO</a>';
            $buttons .= '<button onclick="updatePOStatus(' . $pembelian['id'] . ', \'dikirim\')" class="action-btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"><i class="fas fa-paper-plane mr-2"></i>Kirim PO</button>';
            $buttons .= '</div>';
            return $buttons;
        
        case 'create_penerimaan':
            $buttons .= '<div class="flex flex-wrap gap-2">';
            $buttons .= '<a href="penerimaan-add.php?pembelian_id=' . $pembelian['id'] . '" class="action-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"><i class="fas fa-truck mr-2"></i>Buat Penerimaan</a>';
            $buttons .= '<a href="pembelian-view.php?id=' . $pembelian['id'] . '" class="action-btn bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"><i class="fas fa-eye mr-2"></i>Lihat PO</a>';
            $buttons .= '</div>';
            return $buttons;
        
        case 'update_stock':
             $buttons .= '<div class="flex flex-wrap gap-2">';
             $buttons .= '<button onclick="updateStock(' . $pembelian['id'] . ')" class="action-btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"><i class="fas fa-boxes mr-2"></i>Update Stok</button>';
             $buttons .= '<a href="penerimaan.php?pembelian_id=' . $pembelian['id'] . '" class="action-btn bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"><i class="fas fa-list mr-2"></i>Lihat Penerimaan</a>';
             $buttons .= '</div>';
             return $buttons;
             
         case 'view_stock':
             $buttons .= '<div class="flex flex-wrap gap-2">';
             $buttons .= '<span class="inline-flex items-center text-green-600 font-medium"><i class="fas fa-check-circle mr-2"></i>Stok telah diupdate</span>';
             $buttons .= '<a href="stok.php" class="action-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"><i class="fas fa-warehouse mr-2"></i>Lihat Stok</a>';
             $buttons .= '</div>';
             return $buttons;
             
         case 'process_complete':
             $buttons .= '<div class="flex flex-wrap gap-2">';
             $buttons .= '<span class="inline-flex items-center text-green-600 font-medium"><i class="fas fa-check-double mr-2"></i>Proses selesai</span>';
             $buttons .= '<a href="pembelian.php" class="action-btn bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"><i class="fas fa-list mr-2"></i>Daftar Pembelian</a>';
             $buttons .= '</div>';
             return $buttons;
             
         default:
             return '';
    }
}

function getCompactActionButton($action, $pembelian) {
    $buttons = '';
    
    // Pastikan $pembelian memiliki key 'id'
    if (!is_array($pembelian) || !isset($pembelian['id'])) {
        return '';
    }
    
    switch ($action) {
        case 'update_status_dikirim':
             $buttons .= '<button onclick="updatePOStatus(' . $pembelian['id'] . ', \'dikirim\')" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center transition-all duration-200 hover:scale-105 shadow-md hover:shadow-lg"><i class="fas fa-paper-plane mr-1"></i>Kirim</button>';
             return $buttons;
         
         case 'create_penerimaan':
             $buttons .= '<a href="penerimaan-add.php?pembelian_id=' . $pembelian['id'] . '" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center transition-all duration-200 hover:scale-105 shadow-md hover:shadow-lg"><i class="fas fa-truck mr-1"></i>Terima</a>';
             return $buttons;
         
         case 'update_stock':
              $buttons .= '<button onclick="updateStock(' . $pembelian['id'] . ')" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center transition-all duration-200 hover:scale-105 shadow-md hover:shadow-lg"><i class="fas fa-boxes mr-1"></i>Update</button>';
              return $buttons;
              
          case 'view_stock':
              $buttons .= '<a href="stok.php" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center transition-all duration-200 hover:scale-105 shadow-md hover:shadow-lg"><i class="fas fa-warehouse mr-1"></i>Lihat</a>';
              return $buttons;
              
          case 'process_complete':
              $buttons .= '<span class="inline-flex items-center text-green-600 font-semibold text-xs bg-green-50 px-2 py-1 rounded-full"><i class="fas fa-check-double mr-1"></i>Selesai</span>';
              return $buttons;
             
         default:
             return '';
    }
}

// JavaScript untuk update status
function renderPurchaseProcessJS() {
    echo '
<script>
function updatePOStatus(pembelianId, status) {
    if (confirm(\'Apakah Anda yakin ingin mengubah status PO ini?\')) {
        fetch(\'api/update_po_status.php\', {
            method: \'POST\',
            headers: {
                \'Content-Type\': \'application/json\',
            },
            body: JSON.stringify({
                pembelian_id: pembelianId,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(\'Error: \' + data.message);
            }
        })
        .catch(error => {
            console.error(\'Error:\', error);
            alert(\'Terjadi kesalahan saat mengupdate status\');
        });
    }
}

function updateStock(pembelianId) {
    if (confirm(\'Apakah Anda yakin ingin mengupdate stok berdasarkan penerimaan barang ini?\')) {
        fetch(\'api/update_stock.php\', {
            method: \'POST\',
            headers: {
                \'Content-Type\': \'application/json\',
            },
            body: JSON.stringify({
                pembelian_id: pembelianId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(\'Stok berhasil diupdate!\');
                location.reload();
            } else {
                alert(\'Error: \' + data.message);
            }
        })
        .catch(error => {
            console.error(\'Error:\', error);
            alert(\'Terjadi kesalahan saat mengupdate stok\');
        });
    }
}
</script>
    ';
}
?>