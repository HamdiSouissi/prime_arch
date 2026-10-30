-- Répertoire client, contacts et adresses tunisiennes.
CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(32) NULL UNIQUE,
    client_type ENUM('individual', 'company', 'organization') NOT NULL,
    display_name VARCHAR(180) NOT NULL,
    legal_name VARCHAR(180) NULL,
    tax_id VARCHAR(32) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(32) NULL,
    address_line VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    governorate_id INT UNSIGNED NULL,
    delegation_id INT UNSIGNED NULL,
    postal_code CHAR(4) NULL,
    status ENUM('prospect', 'active', 'archived') NOT NULL DEFAULT 'prospect',
    notes TEXT NULL,
    custom_fields JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_clients_governorate FOREIGN KEY (governorate_id) REFERENCES governorates(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_clients_delegation FOREIGN KEY (delegation_id) REFERENCES delegations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_clients_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_clients_name (display_name),
    INDEX idx_clients_type_status (client_type, status),
    INDEX idx_clients_governorate (governorate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    job_title VARCHAR(100) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(32) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_client_contacts_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_client_contacts_client (client_id, is_primary, full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phases d'architecte configurables par le cabinet.
CREATE TABLE IF NOT EXISTS project_phases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phase_key VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project_phases_active_order (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(32) NULL UNIQUE,
    client_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    project_type VARCHAR(120) NULL,
    description TEXT NULL,
    status ENUM('planning', 'active', 'on_hold', 'completed', 'cancelled') NOT NULL DEFAULT 'planning',
    address_line VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    governorate_id INT UNSIGNED NULL,
    delegation_id INT UNSIGNED NULL,
    postal_code CHAR(4) NULL,
    start_date DATE NULL,
    target_end_date DATE NULL,
    custom_fields JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_projects_governorate FOREIGN KEY (governorate_id) REFERENCES governorates(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_projects_delegation FOREIGN KEY (delegation_id) REFERENCES delegations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_projects_client (client_id, updated_at),
    INDEX idx_projects_status (status, updated_at),
    INDEX idx_projects_name (name),
    INDEX idx_projects_governorate (governorate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_phase_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    project_phase_id INT UNSIGNED NOT NULL,
    phase_name VARCHAR(150) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('pending', 'in_progress', 'completed', 'skipped') NOT NULL DEFAULT 'pending',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_project_phase_assignments_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_project_phase_assignments_phase FOREIGN KEY (project_phase_id) REFERENCES project_phases(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_project_phase_assignment (project_id, project_phase_id),
    INDEX idx_project_phase_assignments_status (project_id, status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Définitions typées ; les valeurs de chaque fiche sont conservées dans leur colonne JSON.
CREATE TABLE IF NOT EXISTS custom_field_definitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('client', 'project') NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    label VARCHAR(120) NOT NULL,
    field_type ENUM('text', 'number', 'date', 'select', 'checkbox') NOT NULL,
    options_json JSON NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_custom_fields_entity_key (entity_type, field_key),
    INDEX idx_custom_fields_entity_active (entity_type, is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
