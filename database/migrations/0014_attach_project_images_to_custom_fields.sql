-- Rattache une image de projet à un champ personnalisé de type « image ».
-- Une valeur NULL désigne les images générales du projet (carte « Images du projet »).
-- Rejouable : chaque évolution vérifie information_schema avant d'agir (voir 0006).
SET @has_custom_field_key = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_images' AND COLUMN_NAME = 'custom_field_key'
);
SET @custom_image_sql = IF(
    @has_custom_field_key = 0,
    'ALTER TABLE project_images ADD COLUMN custom_field_key VARCHAR(80) NULL AFTER uploaded_by',
    'SET @custom_image_noop = 0'
);
PREPARE prime_arch_custom_image_stmt FROM @custom_image_sql;
EXECUTE prime_arch_custom_image_stmt;
DEALLOCATE PREPARE prime_arch_custom_image_stmt;

-- Regroupe les images d'un champ donné pour une fiche projet.
SET @has_custom_field_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_images'
      AND INDEX_NAME = 'idx_project_images_custom_field'
);
SET @custom_image_sql = IF(
    @has_custom_field_index = 0,
    'ALTER TABLE project_images ADD INDEX idx_project_images_custom_field (project_id, custom_field_key, created_at)',
    'SET @custom_image_noop = 0'
);
PREPARE prime_arch_custom_image_stmt FROM @custom_image_sql;
EXECUTE prime_arch_custom_image_stmt;
DEALLOCATE PREPARE prime_arch_custom_image_stmt;
