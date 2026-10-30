-- Documents commerciaux, modèles éditables, numérotation d'émission et règlements.
CREATE TABLE IF NOT EXISTS document_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(80) NOT NULL UNIQUE,
    document_type ENUM('quote', 'contract', 'invoice', 'credit_note') NOT NULL,
    locale ENUM('fr', 'ar') NOT NULL,
    name VARCHAR(120) NOT NULL,
    title VARCHAR(180) NOT NULL,
    terms_text TEXT NULL,
    legal_notice TEXT NULL,
    validity_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    payment_terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_document_templates_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_document_templates_type_locale (document_type, locale, is_active, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_number VARCHAR(40) NULL UNIQUE,
    document_type ENUM('quote', 'contract', 'invoice', 'credit_note') NOT NULL,
    status ENUM('draft', 'issued', 'void') NOT NULL DEFAULT 'draft',
    locale ENUM('fr', 'ar') NOT NULL DEFAULT 'fr',
    title VARCHAR(180) NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NULL,
    project_phase_assignment_id INT UNSIGNED NULL,
    template_id INT UNSIGNED NULL,
    reference_document_id INT UNSIGNED NULL,
    billing_kind ENUM('standard', 'deposit', 'phase') NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'TND',
    tax_rate DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0.00,
    stamp_amount DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0.000,
    withholding_rate DECIMAL(6,3) UNSIGNED NOT NULL DEFAULT 0.000,
    subtotal DECIMAL(18,3) UNSIGNED NOT NULL DEFAULT 0.000,
    tax_amount DECIMAL(18,3) UNSIGNED NOT NULL DEFAULT 0.000,
    withholding_amount DECIMAL(18,3) UNSIGNED NOT NULL DEFAULT 0.000,
    total_amount DECIMAL(18,3) UNSIGNED NOT NULL DEFAULT 0.000,
    validity_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    payment_terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    terms_text TEXT NULL,
    legal_notice TEXT NULL,
    notes TEXT NULL,
    issue_date DATE NULL,
    due_date DATE NULL,
    valid_until DATE NULL,
    issued_at DATETIME NULL,
    issued_by INT UNSIGNED NULL,
    issued_snapshot JSON NULL,
    pdf_path VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_documents_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documents_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_documents_phase FOREIGN KEY (project_phase_assignment_id) REFERENCES project_phase_assignments(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_documents_template FOREIGN KEY (template_id) REFERENCES document_templates(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_documents_reference FOREIGN KEY (reference_document_id) REFERENCES documents(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_documents_issued_by FOREIGN KEY (issued_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_documents_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_documents_status_date (status, issue_date, id),
    INDEX idx_documents_client (client_id, created_at, id),
    INDEX idx_documents_project (project_id, created_at, id),
    INDEX idx_documents_reference (reference_document_id, document_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(9,3) UNSIGNED NOT NULL,
    unit VARCHAR(40) NOT NULL DEFAULT '',
    unit_price DECIMAL(12,3) UNSIGNED NOT NULL,
    line_total DECIMAL(18,3) UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_document_lines_document FOREIGN KEY (document_id) REFERENCES documents(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_document_lines_order (document_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_sequences (
    document_type ENUM('quote', 'contract', 'invoice', 'credit_note') NOT NULL,
    calendar_year SMALLINT UNSIGNED NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (document_type, calendar_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    paid_on DATE NOT NULL,
    amount DECIMAL(18,3) UNSIGNED NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'cheque', 'card', 'other') NOT NULL,
    reference VARCHAR(120) NULL,
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_document_payments_document FOREIGN KEY (document_id) REFERENCES documents(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_document_payments_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_document_payments_document_date (document_id, paid_on, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
