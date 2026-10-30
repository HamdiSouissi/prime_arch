-- Tentatives de connexion échouées, sans conservation de l'adresse email ou IP en clair.
CREATE TABLE IF NOT EXISTS auth_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fingerprint CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auth_attempts_fingerprint_time (fingerprint, attempted_at),
    INDEX idx_auth_attempts_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
