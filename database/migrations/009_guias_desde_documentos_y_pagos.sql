-- 009: guía opcional desde comprobante, trazabilidad SUNAT y forma de pago.
-- Ejecutar después de 008, con respaldo de base de datos, una sola vez.
SET NAMES utf8mb4;
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guias_cabecera' AND COLUMN_NAME='documento_origen_id')=0,
    'ALTER TABLE guias_cabecera ADD COLUMN documento_origen_id BIGINT NULL AFTER id',
    'SET @noop_009 := 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guias_cabecera' AND INDEX_NAME='uq_guias_documento_origen')=0,
    'ALTER TABLE guias_cabecera ADD UNIQUE KEY uq_guias_documento_origen (documento_origen_id)',
    'SET @noop_009 := 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guias_cabecera' AND CONSTRAINT_NAME='fk_guias_documento_origen')=0,
    'ALTER TABLE guias_cabecera ADD CONSTRAINT fk_guias_documento_origen FOREIGN KEY (documento_origen_id) REFERENCES documentos_cabecera(id) ON DELETE RESTRICT',
    'SET @noop_009 := 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guias_cabecera' AND COLUMN_NAME='estado_sunat')=0,
    "ALTER TABLE guias_cabecera ADD COLUMN estado_sunat VARCHAR(20) NOT NULL DEFAULT 'SIN_VERIFICAR' AFTER documento_origen_id",
    'SET @noop_009 := 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documentos_cabecera' AND COLUMN_NAME='medio_pago')=0,
    'ALTER TABLE documentos_cabecera ADD COLUMN medio_pago VARCHAR(20) NULL AFTER estado_comprobante',
    'SET @noop_009 := 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documentos_cabecera' AND COLUMN_NAME='referencia_pago')=0,
    'ALTER TABLE documentos_cabecera ADD COLUMN referencia_pago VARCHAR(80) NULL AFTER medio_pago',
    'SET @noop_009 := 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(version,description)
VALUES(9,'Guía desde documento, estado SUNAT y medio de pago');
