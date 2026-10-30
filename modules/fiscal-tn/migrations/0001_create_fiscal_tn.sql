-- Module « fiscal-tn » : échéancier des obligations fiscales et suivi des déclarations.
-- Tables préfixées par `fiscal_` ; les tables du cœur sont seulement lues.
-- Aucun taux, aucune périodicité et aucun barème ne sont enregistrés par défaut : le cabinet
-- saisit ses propres échéances, qui doivent être confirmées par son expert-comptable.

CREATE TABLE IF NOT EXISTS fiscal_echeances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('vat', 'withholding', 'tcl', 'other') NOT NULL DEFAULT 'vat',
    label VARCHAR(150) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    due_on DATE NOT NULL,
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fiscal_echeance_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_fiscal_echeance_due (due_on),
    INDEX idx_fiscal_echeance_kind (kind, due_on),
    INDEX idx_fiscal_echeance_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_declarations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    echeance_id INT UNSIGNED NOT NULL,
    status ENUM('to_prepare', 'filed', 'paid') NOT NULL DEFAULT 'to_prepare',
    amount DECIMAL(15,3) NULL,
    filed_on DATE NULL,
    paid_on DATE NULL,
    reference VARCHAR(120) NULL,
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fiscal_declaration_echeance FOREIGN KEY (echeance_id) REFERENCES fiscal_echeances(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_fiscal_declaration_author FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uniq_fiscal_declaration_echeance (echeance_id),
    INDEX idx_fiscal_declaration_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
