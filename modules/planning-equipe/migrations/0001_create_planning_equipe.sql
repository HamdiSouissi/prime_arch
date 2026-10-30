-- Module « planning-equipe » : affectations prévisionnelles et congés des collaborateurs.
-- Tables préfixées par `planning_` ; les tables du cœur (`users`, `projects`,
-- `project_phase_assignments`, `time_entries`) sont seulement lues.
-- Les heures sont stockées en DECIMAL(4,2) : jamais d’arrondi flottant côté calcul.

CREATE TABLE IF NOT EXISTS planning_affectations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    project_phase_assignment_id INT UNSIGNED NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    daily_hours DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_planning_affectation_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_planning_affectation_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_planning_affectation_phase FOREIGN KEY (project_phase_assignment_id)
        REFERENCES project_phase_assignments(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_planning_affectation_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_planning_affectation_period (user_id, start_date, end_date),
    INDEX idx_planning_affectation_project (project_id, start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planning_conges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    kind ENUM('vacation', 'sick', 'training', 'other') NOT NULL DEFAULT 'vacation',
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_planning_conge_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_planning_conge_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_planning_conge_period (user_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
