-- Empreintes HMAC à durée courte pour éviter les doublons d'événements de limitation.
CREATE TABLE IF NOT EXISTS auth_throttle_markers (
    fingerprint CHAR(64) NOT NULL PRIMARY KEY,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auth_throttle_markers_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
