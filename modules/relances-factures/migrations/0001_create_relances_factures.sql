-- Module « relances-factures » : balance âgée, règles de relance, courriers et historique.
-- Les tables du module sont préfixées par `relances_` ; les tables du cœur sont seulement lues.

CREATE TABLE IF NOT EXISTS relances_regles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    delay_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    min_amount DECIMAL(18,3) UNSIGNED NOT NULL DEFAULT 0.000,
    tone ENUM('courtesy', 'firm', 'formal', 'final') NOT NULL DEFAULT 'courtesy',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rf_regle_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_rf_regle_name (name),
    INDEX idx_rf_regle_actives (is_active, delay_days, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS relances_modeles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tone ENUM('courtesy', 'firm', 'formal', 'final') NOT NULL,
    locale ENUM('fr', 'ar') NOT NULL,
    subject VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rf_modele_author FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_rf_modele_tone_locale (tone, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS relances_evenements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    regle_id INT UNSIGNED NULL,
    tone ENUM('courtesy', 'firm', 'formal', 'final') NOT NULL DEFAULT 'courtesy',
    channel ENUM('letter', 'call', 'other') NOT NULL DEFAULT 'letter',
    sent_on DATE NOT NULL,
    promise_on DATE NULL,
    promise_amount DECIMAL(18,3) UNSIGNED NULL,
    outcome ENUM('sent', 'promise', 'dispute', 'paid', 'ignored') NOT NULL DEFAULT 'sent',
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rf_evenement_document FOREIGN KEY (document_id) REFERENCES documents(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rf_evenement_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rf_evenement_regle FOREIGN KEY (regle_id) REFERENCES relances_regles(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_rf_evenement_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_rf_evenement_document (document_id, sent_on, id),
    INDEX idx_rf_evenement_client (client_id, sent_on, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS relances_exclusions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rf_exclusion_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rf_exclusion_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_rf_exclusion_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
