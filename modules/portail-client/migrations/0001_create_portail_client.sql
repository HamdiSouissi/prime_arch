-- Module « portail-client » : liens d’accès en lecture seule pour les clients.
--
-- Sécurité : le jeton n’est jamais stocké en clair. Seul son empreinte SHA-256 est conservée ;
-- le lien complet n’est affiché qu’une fois, à la création. Les tables sont préfixées `portail_`
-- et le module n’écrit dans aucune table du noyau.

CREATE TABLE IF NOT EXISTS portail_jetons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    scope ENUM('client', 'project') NOT NULL DEFAULT 'project',
    project_id INT UNSIGNED NULL,
    can_view_documents TINYINT(1) NOT NULL DEFAULT 1,
    can_view_amounts TINYINT(1) NOT NULL DEFAULT 0,
    can_view_photos TINYINT(1) NOT NULL DEFAULT 1,
    expires_on DATE NOT NULL,
    revoked_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    access_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_portail_jetons_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_portail_jetons_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_portail_jetons_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_portail_jetons_client (client_id, expires_on),
    INDEX idx_portail_jetons_expiry (expires_on, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des consultations réussies : une ligne par affichage de l’espace client.
CREATE TABLE IF NOT EXISTS portail_acces (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jeton_id INT UNSIGNED NOT NULL,
    accessed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    page VARCHAR(40) NOT NULL DEFAULT 'espace',
    ip_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    CONSTRAINT fk_portail_acces_jeton FOREIGN KEY (jeton_id) REFERENCES portail_jetons(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_portail_acces_jeton (jeton_id, accessed_at),
    INDEX idx_portail_acces_ip (ip_hash, accessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentatives (acceptées ou refusées) : sert uniquement à la limitation de débit par adresse.
-- L’adresse IP n’est jamais stockée en clair, seulement son empreinte salée.
CREATE TABLE IF NOT EXISTS portail_tentatives (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_hash CHAR(64) NOT NULL,
    result ENUM('granted', 'denied') NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_portail_tentatives_ip (ip_hash, attempted_at),
    INDEX idx_portail_tentatives_date (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
