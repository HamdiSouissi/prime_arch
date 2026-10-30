-- Module « ged-versions » : dépôt documentaire privé, versions successives et rétention.
-- Tables préfixées par `ged_` ; aucune table du noyau n’est créée, modifiée ou supprimée.
-- Les fichiers eux-mêmes sont stockés hors de `public/`, sous `storage/modules/ged-versions/files/`.
-- Aucune règle de rétention légale n’est imposée : la date est saisie par le cabinet.

CREATE TABLE IF NOT EXISTS ged_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(40) NULL,
    title VARCHAR(180) NOT NULL,
    category ENUM('plan', 'dce', 'contract', 'permit', 'report', 'photo', 'correspondence', 'other')
        NOT NULL DEFAULT 'other',
    project_id INT UNSIGNED NULL,
    client_id INT UNSIGNED NULL,
    tags VARCHAR(255) NULL,
    description VARCHAR(1000) NULL,
    retention_until DATE NULL,
    archived_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ged_document_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_ged_document_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_ged_document_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_ged_document_project (project_id, category),
    INDEX idx_ged_document_client (client_id, category),
    INDEX idx_ged_document_title (title),
    INDEX idx_ged_document_retention (retention_until),
    INDEX idx_ged_document_archive (archived_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ged_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    stored_name VARCHAR(80) NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    checksum CHAR(64) NOT NULL,
    note VARCHAR(500) NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ged_version_document FOREIGN KEY (document_id) REFERENCES ged_documents(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ged_version_author FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_ged_version_number (document_id, version_number),
    UNIQUE KEY uq_ged_version_file (stored_name),
    INDEX idx_ged_version_document (document_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
