# Perbaikan Halaman Produk View - Riwayat Stok

## Masalah
Halaman `produk-view.php?id=4` tidak menampilkan update stok dari pembelian, hanya menampilkan transaksi penjualan (keluar).

## Analisis Masalah
1. Query riwayat stok hanya mengambil data dari tabel `transaksi` (penjualan)
2. Data pembelian dari tabel `pembelian` dan `pembelian_detail` tidak ditampilkan
3. Tidak ada indikator jenis transaksi (masuk/keluar)

## Solusi yang Diterapkan

### 1. Modifikasi Query Database
- **Sebelum**: Hanya query dari `transaksi_detail` dan `transaksi`
- **Sesudah**: Dua query terpisah yang digabungkan:
  - Query transaksi keluar (penjualan)
  - Query transaksi masuk (pembelian)

### 2. Penambahan Kolom Jenis Transaksi
- Menambahkan kolom "Jenis" di tabel riwayat stok
- Badge hijau untuk transaksi masuk (+)
- Badge merah untuk transaksi keluar (-)

### 3. Perbaikan Tampilan
- Quantity ditampilkan dengan tanda + (hijau) untuk masuk
- Quantity ditampilkan dengan tanda - (merah) untuk keluar
- Link yang berbeda untuk detail:
  - Transaksi masuk → `pembelian-view.php`
  - Transaksi keluar → `transaksi-view.php`

## Kode yang Dimodifikasi

### Query Database (Baris 97-130)
```php
// Ambil riwayat perubahan stok (dari transaksi keluar)
$riwayat_keluar = $db->select("
    SELECT t.id, t.tanggal_transaksi, td.quantity, t.nomor_invoice, d.nama_desa,
           'keluar' as jenis, u.nama_lengkap as user_name
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    WHERE td.produk_id = ?
    ORDER BY t.tanggal_transaksi DESC
    LIMIT 10
", [$produk_id]);

// Ambil riwayat perubahan stok (dari pembelian masuk)
$riwayat_masuk = $db->select("
    SELECT p.id, p.tanggal_pembelian as tanggal_transaksi, pd.quantity_terima as quantity, 
           p.nomor_po as nomor_invoice, v.nama_vendor as nama_desa,
           'masuk' as jenis, u.nama_lengkap as user_name
    FROM pembelian_detail pd
    JOIN pembelian p ON pd.pembelian_id = p.id
    JOIN vendor v ON p.vendor_id = v.id
    JOIN users u ON p.user_id = u.id
    WHERE pd.produk_id = ? AND pd.quantity_terima > 0
    ORDER BY p.tanggal_pembelian DESC
    LIMIT 10
", [$produk_id]);

// Gabungkan dan urutkan berdasarkan tanggal
$riwayat_stok = array_merge($riwayat_keluar, $riwayat_masuk);
usort($riwayat_stok, function($a, $b) {
    return strtotime($b['tanggal_transaksi']) - strtotime($a['tanggal_transaksi']);
});
$riwayat_stok = array_slice($riwayat_stok, 0, 20);
```

### Header Tabel (Baris 424-431)
```html
<th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
<th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
<th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
<th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor/Desa</th>
<th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Dokumen</th>
```

### Tampilan Data (Baris 436-470)
```php
<?php if ($riwayat['jenis'] == 'masuk'): ?>
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
        <i class="fas fa-arrow-down mr-1"></i> Masuk
    </span>
<?php else: ?>
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
        <i class="fas fa-arrow-up mr-1"></i> Keluar
    </span>
<?php endif; ?>
```

## Hasil
✅ Halaman produk-view.php sekarang menampilkan:
- Riwayat transaksi keluar (penjualan)
- Riwayat transaksi masuk (pembelian)
- Indikator visual jenis transaksi
- Link yang sesuai untuk detail transaksi
- Data quantity yang diterima dari pembelian

## Testing
- URL: `http://localhost:8000/produk-view.php?id=4`
- Status: ✅ Berfungsi normal
- Data pembelian: ✅ Ditampilkan dengan benar
- No error: ✅ Tidak ada error SQL

## Catatan
- Menggunakan `quantity_terima` dari tabel `pembelian_detail` (bukan `quantity_pesan`)
- Hanya menampilkan pembelian yang sudah diterima (`quantity_terima > 0`)
- Data diurutkan berdasarkan tanggal terbaru
- Maksimal 20 record ditampilkan (10 masuk + 10 keluar)