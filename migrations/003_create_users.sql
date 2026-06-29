CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (nama, username, password_hash, status, created_at)
SELECT 'Administrator', 'admin', '$2y$10$TrDLZx8aSo6VHf7Mq3pmIu.WbF0NMWmrbDeGTEsC6oYmfgeFS31DG', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');
