-- Tabel Perangkat Desa untuk Sistem Manajemen Desa
-- Created: 2025

-- Tabel Perangkat Desa
CREATE TABLE perangkat_desa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    desa_id INT NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    jabatan VARCHAR(50) NOT NULL,
    tempat_lahir VARCHAR(50),
    tanggal_lahir DATE,
    alamat TEXT,
    no_telepon VARCHAR(20),
    pendidikan VARCHAR(50),
    tahun_diangkat YEAR,
    no_sk VARCHAR(50),
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    foto VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (desa_id) REFERENCES desa(id) ON DELETE CASCADE
);

-- Index untuk performa
CREATE INDEX idx_perangkat_desa_desa_id ON perangkat_desa(desa_id);
CREATE INDEX idx_perangkat_desa_jabatan ON perangkat_desa(jabatan);
CREATE INDEX idx_perangkat_desa_status ON perangkat_desa(status);

-- Insert data contoh untuk Desa Adipasir (desa_id = 3)
INSERT INTO perangkat_desa (desa_id, nama_lengkap, jabatan, tempat_lahir, tanggal_lahir, alamat, no_telepon, pendidikan, tahun_diangkat, no_sk, status) VALUES
(3, 'Budi Santoso', 'Kepala Desa', 'Cilacap', '1975-05-15', 'Jl. Raya Adipasir No. 1', '081234567890', 'S1 Administrasi Negara', 2020, 'SK/001/2020', 'aktif'),
(3, 'Siti Aminah', 'Sekretaris Desa', 'Cilacap', '1980-08-20', 'Jl. Masjid Adipasir No. 5', '081234567891', 'D3 Administrasi', 2020, 'SK/002/2020', 'aktif'),
(3, 'Ahmad Fauzi', 'Kepala Dusun I', 'Cilacap', '1978-12-10', 'Dusun Krajan RT 01', '081234567892', 'SMA', 2021, 'SK/003/2021', 'aktif'),
(3, 'Rina Sari', 'Kepala Dusun II', 'Cilacap', '1982-03-25', 'Dusun Tengah RT 05', '081234567893', 'SMA', 2021, 'SK/004/2021', 'aktif'),
(3, 'Joko Widodo', 'Anggota BPD', 'Cilacap', '1970-06-30', 'Dusun Selatan RT 08', '081234567894', 'SMP', 2019, 'SK/005/2019', 'aktif'),
(3, 'Dewi Lestari', 'Anggota BPD', 'Cilacap', '1985-11-12', 'Jl. Sekolah Adipasir No. 3', '081234567895', 'SMA', 2019, 'SK/006/2019', 'aktif'),
(3, 'Hendra Gunawan', 'Kaur Keuangan', 'Cilacap', '1983-09-18', 'Jl. Pasar Adipasir No. 7', '081234567896', 'D3 Akuntansi', 2020, 'SK/007/2020', 'aktif'),
(3, 'Maya Indira', 'Kaur Umum', 'Cilacap', '1987-01-05', 'Dusun Utara RT 03', '081234567897', 'SMA', 2021, 'SK/008/2021', 'aktif');

SELECT 'Tabel perangkat_desa berhasil dibuat dengan data contoh untuk Desa Adipasir!' as status;