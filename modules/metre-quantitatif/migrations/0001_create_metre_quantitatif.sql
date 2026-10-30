-- Module « metre-quantitatif » : lots, postes du DQE et bibliothèque de prix.
-- Tables préfixées par `metre_` ; les tables du cœur sont seulement lues (projets, clients).
-- Les montants sont stockés en DECIMAL(15,3) : jamais de flottant.

CREATE TABLE IF NOT EXISTS metre_lots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_metre_lot_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_metre_lot_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_metre_lot_name (project_id, name),
    INDEX idx_metre_lot_order (project_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS metre_bibliotheque_prix (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NULL,
    designation VARCHAR(180) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'u',
    unit_price DECIMAL(15,3) UNSIGNED NOT NULL DEFAULT 0.000,
    category VARCHAR(80) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_metre_lib_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_metre_lib_code (code),
    INDEX idx_metre_lib_category (category, designation),
    INDEX idx_metre_lib_active (is_active, designation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS metre_postes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lot_id INT UNSIGNED NOT NULL,
    library_id INT UNSIGNED NULL,
    code VARCHAR(40) NULL,
    designation VARCHAR(180) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'u',
    quantity DECIMAL(15,3) UNSIGNED NOT NULL DEFAULT 0.000,
    unit_price DECIMAL(15,3) UNSIGNED NOT NULL DEFAULT 0.000,
    margin_percent DECIMAL(6,3) UNSIGNED NOT NULL DEFAULT 0.000,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_metre_poste_lot FOREIGN KEY (lot_id) REFERENCES metre_lots(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_metre_poste_library FOREIGN KEY (library_id) REFERENCES metre_bibliotheque_prix(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_metre_poste_order (lot_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
