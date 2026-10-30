-- Journal d'audit : métadonnées minimales, sans contenu des formulaires ni adresse IP brute.
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id INT UNSIGNED NULL,
    event_key VARCHAR(80) NOT NULL,
    subject_type VARCHAR(80) NOT NULL,
    subject_id VARCHAR(100) NULL,
    details JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_audit_logs_created (created_at, id),
    INDEX idx_audit_logs_actor_created (actor_id, created_at),
    INDEX idx_audit_logs_event_created (event_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
