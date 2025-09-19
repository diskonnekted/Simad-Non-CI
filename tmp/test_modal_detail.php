<?php
require_once 'config/database.php';

$pdo = getDBConnection();

// Ambil satu produk untuk testing
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        k.nama_kategori as kategori_nama,
        CASE 
            WHEN p.harga_diskon IS NOT NULL AND p.harga_diskon > 0 THEN 
                ROUND(((p.harga_satuan - p.harga_diskon) / p.harga_satuan) * 100)
            ELSE 0
        END as persentase_diskon
    FROM produk p
    LEFT JOIN kategori_produk k ON p.kategori_id = k.id
    WHERE p.status = 'aktif' 
    LIMIT 1
");
$stmt->execute();
$produk = $stmt->fetch(PDO::FETCH_ASSOC);

if ($produk) {
    echo "<h2>Data Produk untuk Modal:</h2>";
    echo "<pre>";
    print_r($produk);
    echo "</pre>";
    
    echo "<h3>Test Modal JavaScript:</h3>";
    echo "<button onclick='showProductDetail(" . htmlspecialchars(json_encode($produk)) . ")'>Test Modal</button>";
    
    echo "<div id='productModal' class='hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50'>";
    echo "<div class='bg-white rounded-lg p-6 max-w-md w-full mx-4'>";
    echo "<div class='flex justify-between items-center mb-4'>";
    echo "<h3 class='text-lg font-semibold'>Detail Produk</h3>";
    echo "<button onclick='closeProductModal()' class='text-gray-500 hover:text-gray-700'>×</button>";
    echo "</div>";
    echo "<div id='modalContent'></div>";
    echo "</div>";
    echo "</div>";
    
    echo "<script>";
    echo "function showProductDetail(produk) {";
    echo "    const modal = document.getElementById('productModal');";
    echo "    const content = document.getElementById('modalContent');";
    echo "    ";
    echo "    let hargaHTML = '';";
    echo "    if (produk.harga_diskon && produk.harga_diskon < produk.harga_satuan) {";
    echo "        const persentase = Math.round(((produk.harga_satuan - produk.harga_diskon) / produk.harga_satuan) * 100);";
    echo "        hargaHTML = `<div class='mb-4'><div class='flex items-center space-x-2 mb-2'><span class='text-xl font-bold text-red-600'>Rp \${parseInt(produk.harga_diskon).toLocaleString('id-ID')}</span><span class='bg-red-500 text-white px-2 py-1 text-xs rounded-full'>-\${persentase}%</span></div><span class='text-sm text-gray-500 line-through'>Rp \${parseInt(produk.harga_satuan).toLocaleString('id-ID')}</span></div>`;";
    echo "    } else {";
    echo "        hargaHTML = `<div class='mb-4'><span class='text-xl font-bold text-gray-800'>Rp \${parseInt(produk.harga_satuan || 0).toLocaleString('id-ID')}</span></div>`;";
    echo "    }";
    echo "    ";
    echo "    content.innerHTML = `<div class='space-y-4'><div><h4 class='font-semibold text-gray-800 mb-2'>\${produk.nama_produk || 'undefined'}</h4><span class='text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-full'>\${produk.kategori_nama || 'Umum'}</span></div>\${hargaHTML}<div><p class='text-sm text-gray-600 mb-2'><strong>Deskripsi:</strong></p><p class='text-sm text-gray-600'>\${produk.deskripsi || 'Mouse wireless untuk komputer'}</p></div><div class='flex items-center justify-between text-sm text-gray-600'><span>Stok tersedia: <strong>\${produk.stok_tersedia || 'undefined'}</strong></span></div></div>`;";
    echo "    ";
    echo "    modal.classList.remove('hidden');";
    echo "}";
    echo "";
    echo "function closeProductModal() {";
    echo "    document.getElementById('productModal').classList.add('hidden');";
    echo "}";
    echo "</script>";
} else {
    echo "Tidak ada produk ditemukan.";
}
?>