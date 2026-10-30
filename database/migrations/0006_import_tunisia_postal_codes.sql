-- Rend cette évolution rejouable si une exécution précédente s'est interrompue après un ALTER TABLE.
SET @has_locality_fr = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postal_codes' AND COLUMN_NAME = 'locality_fr'
);
SET @has_locality_latin = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postal_codes' AND COLUMN_NAME = 'locality_latin'
);
SET @postal_sql = IF(
    @has_locality_fr > 0 AND @has_locality_latin = 0,
    'ALTER TABLE postal_codes RENAME COLUMN locality_fr TO locality_latin',
    'SET @postal_noop = 0'
);
PREPARE prime_arch_postal_stmt FROM @postal_sql;
EXECUTE prime_arch_postal_stmt;
DEALLOCATE PREPARE prime_arch_postal_stmt;

-- Certaines installations peuvent avoir déjà la colonne latine sous son nouveau nom.
SET @has_locality_latin = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postal_codes' AND COLUMN_NAME = 'locality_latin'
);
SET @postal_sql = IF(
    @has_locality_latin = 0,
    CONCAT('ALTER TABLE postal_codes ADD COLUMN locality_latin VARCHAR(150) NOT NULL DEFAULT ', QUOTE(''), ' AFTER code'),
    'SET @postal_noop = 0'
);
PREPARE prime_arch_postal_stmt FROM @postal_sql;
EXECUTE prime_arch_postal_stmt;
DEALLOCATE PREPARE prime_arch_postal_stmt;

SET @has_governorate_id = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postal_codes' AND COLUMN_NAME = 'governorate_id'
);
SET @postal_sql = IF(
    @has_governorate_id = 0,
    'ALTER TABLE postal_codes ADD COLUMN governorate_id INT UNSIGNED NULL AFTER delegation_id',
    'SET @postal_noop = 0'
);
PREPARE prime_arch_postal_stmt FROM @postal_sql;
EXECUTE prime_arch_postal_stmt;
DEALLOCATE PREPARE prime_arch_postal_stmt;

SET @has_source_delegation = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postal_codes' AND COLUMN_NAME = 'source_delegation'
);
SET @postal_sql = IF(
    @has_source_delegation = 0,
    CONCAT('ALTER TABLE postal_codes ADD COLUMN source_delegation VARCHAR(150) NOT NULL DEFAULT ', QUOTE(''), ' AFTER governorate_id'),
    'SET @postal_noop = 0'
);
PREPARE prime_arch_postal_stmt FROM @postal_sql;
EXECUTE prime_arch_postal_stmt;
DEALLOCATE PREPARE prime_arch_postal_stmt;

-- Préserve les éventuelles lignes existantes avant l'ajout de la contrainte.
UPDATE postal_codes AS postal
LEFT JOIN delegations AS delegation ON delegation.id = postal.delegation_id
SET postal.governorate_id = COALESCE(postal.governorate_id, delegation.governorate_id),
    postal.source_delegation = CASE
        WHEN postal.source_delegation = '' THEN COALESCE(delegation.name_latin, '')
        ELSE postal.source_delegation
    END
WHERE postal.delegation_id IS NOT NULL
  AND (postal.governorate_id IS NULL OR postal.source_delegation = '');

-- La clé composite historique sert aussi à la clé étrangère delegation_id.
-- Crée d'abord un index dédié pour pouvoir retirer l'ancienne clé unique sans casser cette FK.
SET @has_postal_delegation_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postal_codes' AND INDEX_NAME = 'idx_postal_codes_delegation'
);
SET @postal_sql = IF(
    @has_postal_delegation_index = 0,
    'ALTER TABLE postal_codes ADD INDEX idx_postal_codes_delegation (delegation_id)',
    'SET @postal_noop = 0'
);
PREPARE prime_arch_postal_stmt FROM @postal_sql;
EXECUTE prime_arch_postal_stmt;
DEALLOCATE PREPARE prime_arch_postal_stmt;

-- Remplace l'ancienne clé, si elle existe, par la clé qui conserve le libellé source.
SET @has_postal_locality_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postal_codes' AND INDEX_NAME = 'uq_postal_codes_locality'
);
SET @postal_sql = IF(
    @has_postal_locality_index > 0,
    'ALTER TABLE postal_codes DROP INDEX uq_postal_codes_locality',
    'SET @postal_noop = 0'
);
PREPARE prime_arch_postal_stmt FROM @postal_sql;
EXECUTE prime_arch_postal_stmt;
DEALLOCATE PREPARE prime_arch_postal_stmt;

SET @has_postal_governorate_fk = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'postal_codes'
      AND CONSTRAINT_NAME = 'fk_postal_codes_governorate' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @postal_sql = IF(
    @has_postal_governorate_fk = 0,
    'ALTER TABLE postal_codes ADD CONSTRAINT fk_postal_codes_governorate FOREIGN KEY (governorate_id) REFERENCES governorates(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SET @postal_noop = 0'
);
PREPARE prime_arch_postal_stmt FROM @postal_sql;
EXECUTE prime_arch_postal_stmt;
DEALLOCATE PREPARE prime_arch_postal_stmt;

SET @has_postal_locality_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postal_codes' AND INDEX_NAME = 'uq_postal_codes_locality'
);
SET @postal_sql = IF(
    @has_postal_locality_index = 0,
    'ALTER TABLE postal_codes ADD UNIQUE KEY uq_postal_codes_locality (governorate_id, source_delegation, code, locality_latin)',
    'SET @postal_noop = 0'
);
PREPARE prime_arch_postal_stmt FROM @postal_sql;
EXECUTE prime_arch_postal_stmt;
DEALLOCATE PREPARE prime_arch_postal_stmt;
