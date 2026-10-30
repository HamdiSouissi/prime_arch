-- Module « crm-opportunites » : prospects, pipeline commercial et activités de relance.
-- Tables préfixées par `crm_` ; le module n’écrit dans le cœur (`clients`, `projects`) que lors
-- d’une conversion explicite, via les services de validation du noyau.
-- Aucun montant, aucune probabilité et aucune étape par défaut ne sont imposés par une règle
-- commerciale : le cabinet saisit ses propres valeurs.

CREATE TABLE IF NOT EXISTS crm_opportunites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    prospect_name VARCHAR(180) NOT NULL,
    client_type ENUM('individual', 'company', 'organization') NOT NULL DEFAULT 'individual',
    email VARCHAR(190) NULL,
    phone VARCHAR(32) NULL,
    city VARCHAR(120) NULL,
    origin ENUM('referral', 'website', 'network', 'walk_in', 'tender', 'social', 'other') NOT NULL DEFAULT 'other',
    stage ENUM('new', 'contact', 'meeting', 'proposal', 'negotiation', 'won', 'lost') NOT NULL DEFAULT 'new',
    estimated_amount DECIMAL(15,3) NULL,
    expected_close_on DATE NULL,
    next_action_on DATE NULL,
    owner_id INT UNSIGNED NULL,
    client_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    converted_at DATETIME NULL,
    lost_reason VARCHAR(500) NULL,
    notes VARCHAR(1000) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_crm_opportunite_owner FOREIGN KEY (owner_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_crm_opportunite_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_crm_opportunite_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_crm_opportunite_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_crm_opportunite_stage (stage, next_action_on),
    INDEX idx_crm_opportunite_owner (owner_id, stage),
    INDEX idx_crm_opportunite_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_activites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opportunite_id INT UNSIGNED NOT NULL,
    kind ENUM('call', 'meeting', 'visit', 'email', 'quote', 'note') NOT NULL DEFAULT 'note',
    occurred_on DATE NOT NULL,
    summary VARCHAR(255) NOT NULL,
    detail VARCHAR(1000) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_crm_activite_opportunite FOREIGN KEY (opportunite_id) REFERENCES crm_opportunites(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_crm_activite_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_crm_activite_opportunite (opportunite_id, occurred_on, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
