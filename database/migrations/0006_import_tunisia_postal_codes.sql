-- Étend le catalogue postal pour conserver la source et les rattachements non univoques.
ALTER TABLE postal_codes
    CHANGE COLUMN locality_fr locality_latin VARCHAR(150) NOT NULL DEFAULT '',
    ADD COLUMN governorate_id INT UNSIGNED NULL AFTER delegation_id,
    ADD COLUMN source_delegation VARCHAR(150) NOT NULL DEFAULT '' AFTER governorate_id;

-- Préserve les éventuelles lignes déjà présentes dans une installation existante.
UPDATE postal_codes AS postal
INNER JOIN delegations AS delegation ON delegation.id = postal.delegation_id
SET postal.governorate_id = delegation.governorate_id,
    postal.source_delegation = delegation.name_latin
WHERE postal.governorate_id IS NULL;

ALTER TABLE postal_codes
    DROP INDEX uq_postal_codes_locality,
    ADD CONSTRAINT fk_postal_codes_governorate FOREIGN KEY (governorate_id) REFERENCES governorates(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD UNIQUE KEY uq_postal_codes_locality (governorate_id, source_delegation, code, locality_latin);
