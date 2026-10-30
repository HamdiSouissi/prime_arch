-- Module « suivi-chantier » : journal de chantier, réserves et réceptions.
-- Les tables du module sont préfixées par `chantier_` ; aucune table du cœur n’est modifiée.

CREATE TABLE IF NOT EXISTS chantier_visites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    visit_date DATE NOT NULL,
    weather ENUM('clear', 'cloudy', 'rain', 'wind', 'heat', 'cold', 'other') NOT NULL DEFAULT 'clear',
    workforce SMALLINT UNSIGNED NULL,
    observations TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chantier_visite_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_chantier_visite_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_chantier_visite_project_date (project_id, visit_date, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chantier_reserves (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    visit_id INT UNSIGNED NULL,
    reference VARCHAR(30) NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('minor', 'major', 'critical') NOT NULL DEFAULT 'minor',
    status ENUM('open', 'lifted', 'contested') NOT NULL DEFAULT 'open',
    opened_on DATE NOT NULL,
    lifted_on DATE NULL,
    lift_note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chantier_reserve_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_chantier_reserve_visit FOREIGN KEY (visit_id) REFERENCES chantier_visites(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_chantier_reserve_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_chantier_reserve_reference (project_id, reference),
    INDEX idx_chantier_reserve_status (project_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chantier_receptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    kind ENUM('provisional', 'final') NOT NULL DEFAULT 'provisional',
    reception_date DATE NOT NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chantier_reception_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_chantier_reception_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_chantier_reception_project (project_id, reception_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
