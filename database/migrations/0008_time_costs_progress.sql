-- Taux horaires internes historisés, saisies de temps et mesures de progression.
CREATE TABLE IF NOT EXISTS user_hourly_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    cost_rate_per_hour DECIMAL(12,3) UNSIGNED NOT NULL,
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hourly_rates_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hourly_rates_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_hourly_rates_user_date (user_id, effective_from),
    INDEX idx_hourly_rates_effective (effective_from, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS time_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    project_phase_assignment_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    duration_hours DECIMAL(6,2) UNSIGNED NOT NULL,
    description VARCHAR(500) NOT NULL,
    is_billable TINYINT(1) NOT NULL DEFAULT 1,
    hourly_rate_snapshot DECIMAL(12,3) UNSIGNED NOT NULL,
    cost_amount DECIMAL(18,3) UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_time_entries_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_time_entries_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_time_entries_phase FOREIGN KEY (project_phase_assignment_id) REFERENCES project_phase_assignments(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_time_entries_user_date (user_id, work_date, id),
    INDEX idx_time_entries_project_date (project_id, work_date, id),
    INDEX idx_time_entries_phase (project_phase_assignment_id, work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les ALTER sont conditionnels afin qu'une relance après une migration interrompue soit sûre.
SET @migration_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'budget_hours') = 0,
    'ALTER TABLE projects ADD COLUMN budget_hours DECIMAL(9,2) UNSIGNED NULL',
    'SELECT 1'
);
PREPARE phase3_stmt FROM @migration_sql;
EXECUTE phase3_stmt;
DEALLOCATE PREPARE phase3_stmt;

SET @migration_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'fee_budget_amount') = 0,
    'ALTER TABLE projects ADD COLUMN fee_budget_amount DECIMAL(18,3) UNSIGNED NULL',
    'SELECT 1'
);
PREPARE phase3_stmt FROM @migration_sql;
EXECUTE phase3_stmt;
DEALLOCATE PREPARE phase3_stmt;

SET @migration_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_phase_assignments' AND COLUMN_NAME = 'weight_percent') = 0,
    'ALTER TABLE project_phase_assignments ADD COLUMN weight_percent DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE phase3_stmt FROM @migration_sql;
EXECUTE phase3_stmt;
DEALLOCATE PREPARE phase3_stmt;

SET @migration_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_phase_assignments' AND COLUMN_NAME = 'budget_hours') = 0,
    'ALTER TABLE project_phase_assignments ADD COLUMN budget_hours DECIMAL(9,2) UNSIGNED NULL',
    'SELECT 1'
);
PREPARE phase3_stmt FROM @migration_sql;
EXECUTE phase3_stmt;
DEALLOCATE PREPARE phase3_stmt;

SET @migration_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_phase_assignments' AND COLUMN_NAME = 'progress_percent') = 0,
    'ALTER TABLE project_phase_assignments ADD COLUMN progress_percent DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE phase3_stmt FROM @migration_sql;
EXECUTE phase3_stmt;
DEALLOCATE PREPARE phase3_stmt;

UPDATE project_phase_assignments
SET progress_percent = 100.00
WHERE status = 'completed' AND progress_percent <> 100.00;
