-- Module « permis-batir » : dossiers de permis de bâtir.
-- Les tables du module sont préfixées par son slug ; aucune table du cœur n’est modifiée.

CREATE TABLE IF NOT EXISTS permis_batir_dossiers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    reference VARCHAR(40) NOT NULL,
    commune VARCHAR(150) NOT NULL,
    status ENUM('preparation', 'deposited', 'instruction', 'complement', 'granted', 'refused', 'withdrawn')
        NOT NULL DEFAULT 'preparation',
    deposit_number VARCHAR(60) NULL,
    deposit_date DATE NULL,
    decision_number VARCHAR(60) NULL,
    decision_date DATE NULL,
    deadline_date DATE NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permis_batir_reference (reference),
    INDEX idx_permis_batir_project (project_id),
    INDEX idx_permis_batir_status_deadline (status, deadline_date),
    CONSTRAINT fk_permis_batir_dossier_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_permis_batir_dossier_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permis_batir_pieces (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NULL,
    label VARCHAR(150) NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_deposited TINYINT(1) NOT NULL DEFAULT 0,
    deposited_on DATE NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_permis_batir_piece_dossier FOREIGN KEY (dossier_id) REFERENCES permis_batir_dossiers(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_permis_batir_piece_dossier (dossier_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permis_batir_evenements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    kind ENUM('deposit', 'complement', 'response', 'visit', 'decision', 'withdrawal', 'note')
        NOT NULL DEFAULT 'note',
    summary VARCHAR(180) NOT NULL,
    details TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_permis_batir_event_dossier FOREIGN KEY (dossier_id) REFERENCES permis_batir_dossiers(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_permis_batir_event_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_permis_batir_event_dossier (dossier_id, event_date, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
