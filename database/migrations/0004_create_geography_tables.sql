-- Référentiel géographique tunisien (codes postaux importables séparément).
CREATE TABLE IF NOT EXISTS governorates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    iso_code CHAR(5) NOT NULL UNIQUE,
    name_fr VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delegations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    governorate_id INT UNSIGNED NOT NULL,
    code VARCHAR(12) NOT NULL UNIQUE,
    name_latin VARCHAR(150) NOT NULL,
    name_ar VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_delegations_governorate FOREIGN KEY (governorate_id) REFERENCES governorates(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_delegations_name (governorate_id, name_latin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS postal_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delegation_id INT UNSIGNED NULL,
    code CHAR(4) NOT NULL,
    locality_fr VARCHAR(150) NOT NULL DEFAULT '',
    locality_ar VARCHAR(150) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_postal_codes_delegation FOREIGN KEY (delegation_id) REFERENCES delegations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_postal_codes_locality (delegation_id, code, locality_fr),
    INDEX idx_postal_codes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
