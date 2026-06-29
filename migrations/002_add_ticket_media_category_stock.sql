SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE jenis_tiket ADD COLUMN kategori VARCHAR(100) NULL AFTER nama_tiket',
        'DO 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'jenis_tiket'
      AND COLUMN_NAME = 'kategori'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE jenis_tiket ADD COLUMN foto VARCHAR(255) NULL AFTER kategori',
        'DO 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'jenis_tiket'
      AND COLUMN_NAME = 'foto'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE jenis_tiket ADD COLUMN stock INT NOT NULL DEFAULT 0 AFTER harga',
        'DO 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'jenis_tiket'
      AND COLUMN_NAME = 'stock'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE jenis_tiket
SET kategori = COALESCE(NULLIF(kategori, ''), 'Umum'),
    stock = CASE WHEN stock < 0 THEN 0 ELSE stock END;

UPDATE jenis_tiket
SET kategori = 'Umum',
    stock = 100
WHERE LOWER(nama_tiket) = 'reguler'
  AND stock = 0;

UPDATE jenis_tiket
SET kategori = 'Premium',
    stock = 50
WHERE LOWER(nama_tiket) = 'vip'
  AND stock = 0;
