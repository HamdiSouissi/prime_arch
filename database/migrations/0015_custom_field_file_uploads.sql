-- Champs personnalisés : fichiers AutoCAD (.dwg / .dxf) rattachés aux fiches projets.
--
-- Deux évolutions, chacune rejouable :
--   1. l'ENUM `custom_field_definitions.field_type` reçoit « image » (oublié en 0014) et
--      « file » (plans AutoCAD) : sans cela MySQL tronque ou refuse la valeur, et le champ
--      ne peut pas être créé depuis les réglages ;
--   2. la table `project_files` conserve les métadonnées des fichiers privés, le contenu
--      restant hors de la racine publique (storage/uploads/projects).
--
-- Une valeur `custom_field_key` identifie le champ d'accueil : chaque champ « fichier »
-- possède sa propre liste, comme les champs « image » avec `project_images`.

SET @has_field_type = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'custom_field_definitions'
      AND COLUMN_NAME = 'field_type' AND COLUMN_TYPE LIKE '%''file''%'
);
SET @field_type_sql = IF(
    @has_field_type = 0,
    'ALTER TABLE custom_field_definitions MODIFY COLUMN field_type ENUM(''text'', ''number'', ''date'', ''select'', ''checkbox'', ''image'', ''file'') NOT NULL',
    'SET @field_type_noop = 0'
);
PREPARE prime_arch_field_type_stmt FROM @field_type_sql;
EXECUTE prime_arch_field_type_stmt;
DEALLOCATE PREPARE prime_arch_field_type_stmt;

CREATE TABLE IF NOT EXISTS project_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    custom_field_key VARCHAR(80) NOT NULL,
    original_name VARCHAR(180) NOT NULL,
    stored_name VARCHAR(64) NOT NULL UNIQUE,
    extension VARCHAR(10) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    checksum CHAR(64) NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_project_files_project FOREIGN KEY (project_id) REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_project_files_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_project_files_custom_field (project_id, custom_field_key, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
