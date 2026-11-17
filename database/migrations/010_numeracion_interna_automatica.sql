-- 010: correlativos INTERNOS del ERP para documentos y guías.
-- No habilita ni certifica la emisión SUNAT. Aplicar después de 009.
-- La clave por ámbito bloquea asignaciones concurrentes usando FOR UPDATE.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS numeraciones_internas (
    ambito VARCHAR(24) PRIMARY KEY,
    siguiente BIGINT UNSIGNED NOT NULL DEFAULT 1,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT ck_numeraciones_siguiente CHECK (siguiente >= 1)
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (10,'Numeración interna automática de documentos y guías');
