-- Module « honoraires-bareme » : barèmes d’honoraires, tranches par phase et décomptes.
-- Tables préfixées par `bareme_` ; les tables du cœur (`projects`, `project_phases`,
-- `documents`) sont seulement lues, sauf le devis créé par le cabinet depuis un décompte.
-- Les montants sont stockés en DECIMAL(15,3) : jamais de flottant.

CREATE TABLE IF NOT EXISTS bareme_missions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bareme_mission_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_bareme_mission_name (name),
    INDEX idx_bareme_mission_active (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bareme_tranches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mission_id INT UNSIGNED NOT NULL,
    phase_key VARCHAR(80) NOT NULL,
    min_amount DECIMAL(15,3) UNSIGNED NOT NULL DEFAULT 0.000,
    max_amount DECIMAL(15,3) UNSIGNED NULL,
    rate_percent DECIMAL(6,3) UNSIGNED NOT NULL DEFAULT 0.000,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bareme_tranche_mission FOREIGN KEY (mission_id) REFERENCES bareme_missions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_bareme_tranche_phase (mission_id, phase_key, min_amount, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bareme_decomptes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    mission_id INT UNSIGNED NULL,
    mission_name VARCHAR(150) NOT NULL,
    basis_amount DECIMAL(15,3) UNSIGNED NOT NULL DEFAULT 0.000,
    fee_amount DECIMAL(15,3) UNSIGNED NOT NULL DEFAULT 0.000,
    breakdown JSON NULL,
    status ENUM('draft', 'validated', 'quoted') NOT NULL DEFAULT 'draft',
    document_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bareme_decompte_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_bareme_decompte_mission FOREIGN KEY (mission_id) REFERENCES bareme_missions(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_bareme_decompte_document FOREIGN KEY (document_id) REFERENCES documents(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_bareme_decompte_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_bareme_decompte_project (project_id, created_at),
    INDEX idx_bareme_decompte_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
