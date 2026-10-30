-- Module « conformite-oat » : dossier professionnel du cabinet et assurances.
-- Tables préfixées par `conformite_` ; les tables du cœur (`users`, `projects`, `clients`)
-- sont seulement lues. Aucune validité légale n’est présumée : seules les dates saisies
-- par le cabinet déterminent les alertes.

CREATE TABLE IF NOT EXISTS conformite_inscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('registration', 'card', 'authorization', 'other') NOT NULL DEFAULT 'registration',
    label VARCHAR(150) NOT NULL,
    reference VARCHAR(120) NULL,
    issued_on DATE NULL,
    expires_on DATE NULL,
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conformite_inscription_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_conformite_inscription_expiry (expires_on),
    INDEX idx_conformite_inscription_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conformite_assurances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    insurer VARCHAR(150) NOT NULL,
    policy_number VARCHAR(120) NULL,
    coverage_amount DECIMAL(15,3) NULL,
    starts_on DATE NULL,
    ends_on DATE NOT NULL,
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conformite_assurance_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_conformite_assurance_expiry (ends_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conformite_formations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    provider VARCHAR(150) NULL,
    hours DECIMAL(5,2) NULL,
    completed_on DATE NOT NULL,
    expires_on DATE NULL,
    certificate_reference VARCHAR(120) NULL,
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conformite_formation_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_conformite_formation_expiry (expires_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conformite_partenaires (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    discipline VARCHAR(120) NULL,
    contact_name VARCHAR(150) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    agreement_reference VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conformite_partenaire_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_conformite_partenaire_active (is_active),
    INDEX idx_conformite_partenaire_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
