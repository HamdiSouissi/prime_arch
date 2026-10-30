-- Informations de provenance et de cycle de vie pour les modules installés.
ALTER TABLE modules
    ADD COLUMN archive_sha256 CHAR(64) NULL AFTER version,
    ADD COLUMN manifest_json JSON NULL AFTER archive_sha256,
    ADD COLUMN installed_by INT UNSIGNED NULL AFTER manifest_json,
    ADD COLUMN install_hook_completed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_enabled,
    ADD CONSTRAINT fk_modules_installed_by
        FOREIGN KEY (installed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;
