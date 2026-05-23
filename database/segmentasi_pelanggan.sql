-- =============================================
-- Tabel Segmentasi Pelanggan (RFM + K-Means Clustering)
-- MySQL / MariaDB
-- =============================================

USE pos_db;

-- ---------------------------------------------
-- Tabel: kelas_segmentasi
-- 27 kelas dari kombinasi 3R x 3F x 3M
-- Kelas R/F/M: 1 = Rendah, 2 = Sedang, 3 = Tinggi
-- Catatan: Untuk Recency, nilai RENDAH (hari sedikit) = pelanggan AKTIF (baik)
--          Untuk Frequency & Monetary, nilai TINGGI = pelanggan AKTIF (baik)
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS kelas_segmentasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(100) NOT NULL,
    kelas_recency TINYINT NOT NULL COMMENT '1=Rendah(aktif), 2=Sedang, 3=Tinggi(tidak aktif)',
    kelas_frequency TINYINT NOT NULL COMMENT '1=Rendah, 2=Sedang, 3=Tinggi',
    kelas_monetary TINYINT NOT NULL COMMENT '1=Rendah, 2=Sedang, 3=Tinggi',
    deskripsi TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_kelas_rfm (kelas_recency, kelas_frequency, kelas_monetary)
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Tabel: periode_segmentasi
-- Menyimpan konfigurasi dan hasil evaluasi setiap proses segmentasi
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS periode_segmentasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_periode VARCHAR(150) NOT NULL,
    tanggal_proses DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tanggal_transaksi_mulai DATE NOT NULL,
    tanggal_transaksi_selesai DATE NOT NULL,
    status ENUM('pending', 'proses', 'selesai', 'gagal') NOT NULL DEFAULT 'pending',
    keterangan TEXT DEFAULT NULL,

    -- Hasil clustering
    jumlah_pelanggan INT DEFAULT NULL COMMENT 'Total pelanggan yang diproses',
    jumlah_cluster INT DEFAULT NULL COMMENT 'Jumlah cluster optimal (dari elbow method)',

    -- Metrik evaluasi clustering
    inertia DOUBLE DEFAULT NULL COMMENT 'Sum of Squared Errors (SSE) / Within-Cluster Sum of Squares',
    silhouette_score DOUBLE DEFAULT NULL COMMENT 'Silhouette Coefficient (-1 s/d 1, semakin tinggi semakin baik)',
    davies_bouldin_index DOUBLE DEFAULT NULL COMMENT 'Davies-Bouldin Index (semakin rendah semakin baik)',
    calinski_harabasz_index DOUBLE DEFAULT NULL COMMENT 'Calinski-Harabasz Index (semakin tinggi semakin baik)',

    -- Data elbow method (JSON array of {k, inertia})
    elbow_data JSON DEFAULT NULL COMMENT 'Data elbow method: [{k:2, inertia:xxx}, {k:3, inertia:xxx}, ...]',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Tabel: periode_segmentasi_batas_kelas
-- Menyimpan batas rentang nilai R/F/M per periode
-- Batas dihitung menggunakan metode tertile (bagi 3 bagian sama rata)
-- Digunakan untuk mapping cluster ke kelas segmentasi
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS periode_segmentasi_batas_kelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_segmentasi_id INT NOT NULL,

    -- Batas Recency (dalam hari)
    recency_rendah_min DOUBLE NOT NULL COMMENT 'Batas bawah recency rendah (aktif)',
    recency_rendah_max DOUBLE NOT NULL COMMENT 'Batas atas recency rendah (aktif)',
    recency_sedang_min DOUBLE NOT NULL,
    recency_sedang_max DOUBLE NOT NULL,
    recency_tinggi_min DOUBLE NOT NULL COMMENT 'Batas bawah recency tinggi (tidak aktif)',
    recency_tinggi_max DOUBLE NOT NULL COMMENT 'Batas atas recency tinggi (tidak aktif)',

    -- Batas Frequency (jumlah transaksi)
    frequency_rendah_min DOUBLE NOT NULL,
    frequency_rendah_max DOUBLE NOT NULL,
    frequency_sedang_min DOUBLE NOT NULL,
    frequency_sedang_max DOUBLE NOT NULL,
    frequency_tinggi_min DOUBLE NOT NULL,
    frequency_tinggi_max DOUBLE NOT NULL,

    -- Batas Monetary (total belanja)
    monetary_rendah_min DOUBLE NOT NULL,
    monetary_rendah_max DOUBLE NOT NULL,
    monetary_sedang_min DOUBLE NOT NULL,
    monetary_sedang_max DOUBLE NOT NULL,
    monetary_tinggi_min DOUBLE NOT NULL,
    monetary_tinggi_max DOUBLE NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (periode_segmentasi_id) REFERENCES periode_segmentasi(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Tabel: cluster
-- Menyimpan hasil cluster beserta centroid dan mapping ke kelas segmentasi
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS cluster (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_segmentasi_id INT NOT NULL,
    nomor_cluster INT NOT NULL COMMENT 'Label cluster dari K-Means (0, 1, 2, ...)',
    kelas_segmentasi_id INT DEFAULT NULL COMMENT 'Mapping ke kelas segmentasi berdasarkan posisi centroid',

    -- Centroid (pusat cluster)
    centroid_recency DOUBLE NOT NULL,
    centroid_frequency DOUBLE NOT NULL,
    centroid_monetary DOUBLE NOT NULL,

    -- Statistik cluster
    jumlah_anggota INT NOT NULL DEFAULT 0,

    -- Rekomendasi bisnis dari LLM
    llm_rekomendasi_bisnis TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (periode_segmentasi_id) REFERENCES periode_segmentasi(id) ON DELETE CASCADE,
    FOREIGN KEY (kelas_segmentasi_id) REFERENCES kelas_segmentasi(id) ON DELETE SET NULL,
    UNIQUE KEY uk_periode_cluster (periode_segmentasi_id, nomor_cluster)
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Tabel: periode_segmentasi_pelanggan
-- Menyimpan data RFM setiap pelanggan per periode dan assignment cluster
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS periode_segmentasi_pelanggan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_segmentasi_id INT NOT NULL,
    customer_id INT NOT NULL,

    -- Nilai RFM
    recency INT NOT NULL COMMENT 'Jumlah hari sejak transaksi terakhir',
    frequency INT NOT NULL COMMENT 'Jumlah transaksi dalam periode',
    monetary DECIMAL(15,2) NOT NULL COMMENT 'Total belanja dalam periode',

    -- Nilai RFM yang sudah dinormalisasi (untuk input K-Means)
    recency_normalized DOUBLE DEFAULT NULL,
    frequency_normalized DOUBLE DEFAULT NULL,
    monetary_normalized DOUBLE DEFAULT NULL,

    -- Hasil clustering
    cluster_id INT DEFAULT NULL COMMENT 'FK ke tabel cluster setelah proses clustering selesai',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (periode_segmentasi_id) REFERENCES periode_segmentasi(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (cluster_id) REFERENCES cluster(id) ON DELETE SET NULL,
    UNIQUE KEY uk_periode_customer (periode_segmentasi_id, customer_id)
) ENGINE=InnoDB;

-- =============================================
-- Data Awal: 27 Kelas Segmentasi (3R x 3F x 3M)
-- =============================================
INSERT INTO kelas_segmentasi (nama_kelas, kelas_recency, kelas_frequency, kelas_monetary, deskripsi) VALUES
-- R1 (Rendah/Aktif) - Pelanggan yang baru bertransaksi
('R1-F1-M1', 1, 1, 1, 'Pelanggan baru aktif, frekuensi rendah, belanja rendah'),
('R1-F1-M2', 1, 1, 2, 'Pelanggan baru aktif, frekuensi rendah, belanja sedang'),
('R1-F1-M3', 1, 1, 3, 'Pelanggan baru aktif, frekuensi rendah, belanja tinggi'),
('R1-F2-M1', 1, 2, 1, 'Pelanggan baru aktif, frekuensi sedang, belanja rendah'),
('R1-F2-M2', 1, 2, 2, 'Pelanggan baru aktif, frekuensi sedang, belanja sedang'),
('R1-F2-M3', 1, 2, 3, 'Pelanggan baru aktif, frekuensi sedang, belanja tinggi'),
('R1-F3-M1', 1, 3, 1, 'Pelanggan baru aktif, frekuensi tinggi, belanja rendah'),
('R1-F3-M2', 1, 3, 2, 'Pelanggan baru aktif, frekuensi tinggi, belanja sedang'),
('R1-F3-M3', 1, 3, 3, 'Pelanggan champion - aktif, sering belanja, nilai tinggi'),

-- R2 (Sedang) - Pelanggan yang cukup aktif
('R2-F1-M1', 2, 1, 1, 'Pelanggan cukup aktif, frekuensi rendah, belanja rendah'),
('R2-F1-M2', 2, 1, 2, 'Pelanggan cukup aktif, frekuensi rendah, belanja sedang'),
('R2-F1-M3', 2, 1, 3, 'Pelanggan cukup aktif, frekuensi rendah, belanja tinggi'),
('R2-F2-M1', 2, 2, 1, 'Pelanggan cukup aktif, frekuensi sedang, belanja rendah'),
('R2-F2-M2', 2, 2, 2, 'Pelanggan cukup aktif, frekuensi sedang, belanja sedang'),
('R2-F2-M3', 2, 2, 3, 'Pelanggan cukup aktif, frekuensi sedang, belanja tinggi'),
('R2-F3-M1', 2, 3, 1, 'Pelanggan cukup aktif, frekuensi tinggi, belanja rendah'),
('R2-F3-M2', 2, 3, 2, 'Pelanggan cukup aktif, frekuensi tinggi, belanja sedang'),
('R2-F3-M3', 2, 3, 3, 'Pelanggan loyal - cukup aktif, sering belanja, nilai tinggi'),

-- R3 (Tinggi/Tidak Aktif) - Pelanggan yang sudah lama tidak bertransaksi
('R3-F1-M1', 3, 1, 1, 'Pelanggan hilang - tidak aktif, jarang belanja, nilai rendah'),
('R3-F1-M2', 3, 1, 2, 'Pelanggan tidak aktif, frekuensi rendah, belanja sedang'),
('R3-F1-M3', 3, 1, 3, 'Pelanggan tidak aktif, frekuensi rendah, belanja tinggi'),
('R3-F2-M1', 3, 2, 1, 'Pelanggan tidak aktif, frekuensi sedang, belanja rendah'),
('R3-F2-M2', 3, 2, 2, 'Pelanggan tidak aktif, frekuensi sedang, belanja sedang'),
('R3-F2-M3', 3, 2, 3, 'Pelanggan tidak aktif, frekuensi sedang, belanja tinggi'),
('R3-F3-M1', 3, 3, 1, 'Pelanggan tidak aktif, frekuensi tinggi, belanja rendah'),
('R3-F3-M2', 3, 3, 2, 'Pelanggan tidak aktif, frekuensi tinggi, belanja sedang'),
('R3-F3-M3', 3, 3, 3, 'Pelanggan berisiko hilang - dulunya champion, sekarang tidak aktif');
