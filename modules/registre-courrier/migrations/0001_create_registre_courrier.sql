-- Module « registre-courrier » : registre du courrier entrant et sortant.
-- Tables préfixées par le slug ; aucune table du cœur n’est modifiée.
-- Le noyau (projects, clients, users) est seulement lu.

CREATE TABLE IF NOT EXISTS courrier_registre (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sens ENUM('in', 'out') NOT NULL DEFAULT 'in',
    annee SMALLINT UNSIGNED NOT NULL,
    sequence INT UNSIGNED NOT NULL,
    numero VARCHAR(40) NOT NULL,
    date_courrier DATE NOT NULL,
    correspondant VARCHAR(150) NOT NULL,
    objet VARCHAR(200) NOT NULL,
    reference VARCHAR(80) NULL,
    mode ENUM('deposit', 'registered', 'email', 'hand', 'other') NOT NULL DEFAULT 'other',
    statut ENUM('todo', 'in_progress', 'done', 'archived') NOT NULL DEFAULT 'todo',
    project_id INT UNSIGNED NULL,
    client_id INT UNSIGNED NULL,
    echeance DATE NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_courrier_numero (numero),
    UNIQUE KEY uq_courrier_sequence (sens, annee, sequence),
    INDEX idx_courrier_date (date_courrier),
    INDEX idx_courrier_statut (statut, echeance),
    INDEX idx_courrier_project (project_id),
    INDEX idx_courrier_client (client_id),
    CONSTRAINT fk_courrier_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_courrier_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_courrier_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réglages de numérotation : une seule ligne, modifiable par le cabinet.
-- Aucun format n’est imposé par le module : préfixes, séparateur et nombre de chiffres.
CREATE TABLE IF NOT EXISTS courrier_reglages (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    prefix_in VARCHAR(10) NOT NULL DEFAULT 'CE',
    prefix_out VARCHAR(10) NOT NULL DEFAULT 'CS',
    separator VARCHAR(3) NOT NULL DEFAULT '-',
    digits TINYINT UNSIGNED NOT NULL DEFAULT 3,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO courrier_reglages (id, prefix_in, prefix_out, separator, digits)
VALUES (1, 'CE', 'CS', '-', 3)
ON DUPLICATE KEY UPDATE id = id;

-- Compteurs par sens et par année : ils permettent de reprendre une numérotation
-- existante (registre papier, reprise d’exercice) sans jamais réutiliser un numéro.
CREATE TABLE IF NOT EXISTS courrier_compteurs (
    sens ENUM('in', 'out') NOT NULL,
    annee SMALLINT UNSIGNED NOT NULL,
    dernier INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sens, annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
