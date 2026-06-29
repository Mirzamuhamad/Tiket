CREATE TABLE IF NOT EXISTS anggota (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    no_anggota VARCHAR(50) NOT NULL,
    nama VARCHAR(200) NOT NULL,
    alamat TEXT NULL,
    no_hp VARCHAR(20) NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_anggota_no_anggota (no_anggota),
    KEY idx_anggota_nama (nama),
    KEY idx_anggota_no_hp (no_hp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jenis_tiket (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_tiket VARCHAR(100) NOT NULL,
    kategori VARCHAR(100) NULL,
    foto VARCHAR(255) NULL,
    harga DECIMAL(18,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_jenis_tiket_nama (nama_tiket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pembelian_tiket (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    no_transaksi VARCHAR(30) NOT NULL,
    tanggal DATETIME NOT NULL,
    anggota_id BIGINT UNSIGNED NOT NULL,
    total_bayar DECIMAL(18,2) NOT NULL DEFAULT 0,
    keterangan TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pembelian_no_transaksi (no_transaksi),
    KEY idx_pembelian_tanggal (tanggal),
    KEY idx_pembelian_anggota (anggota_id),
    CONSTRAINT fk_pembelian_anggota
        FOREIGN KEY (anggota_id) REFERENCES anggota(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pembelian_tiket_detail (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pembelian_id BIGINT UNSIGNED NOT NULL,
    tiket_id BIGINT UNSIGNED NOT NULL,
    harga DECIMAL(18,2) NOT NULL,
    qty INT NOT NULL,
    subtotal DECIMAL(18,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_detail_pembelian (pembelian_id),
    KEY idx_detail_tiket (tiket_id),
    CONSTRAINT fk_detail_pembelian
        FOREIGN KEY (pembelian_id) REFERENCES pembelian_tiket(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detail_tiket
        FOREIGN KEY (tiket_id) REFERENCES jenis_tiket(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO anggota (no_anggota, nama, alamat, no_hp, status, created_at)
SELECT 'AGT001', 'Budi Santoso', 'Jakarta Selatan', '081234567001', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM anggota WHERE no_anggota = 'AGT001');

INSERT INTO anggota (no_anggota, nama, alamat, no_hp, status, created_at)
SELECT 'AGT002', 'Siti Aminah', 'Bekasi', '081234567002', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM anggota WHERE no_anggota = 'AGT002');

INSERT INTO jenis_tiket (nama_tiket, harga, status)
SELECT 'Reguler', 50000, 1
WHERE NOT EXISTS (SELECT 1 FROM jenis_tiket WHERE nama_tiket = 'Reguler');

INSERT INTO jenis_tiket (nama_tiket, harga, status)
SELECT 'VIP', 125000, 1
WHERE NOT EXISTS (SELECT 1 FROM jenis_tiket WHERE nama_tiket = 'VIP');
