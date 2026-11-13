-- ============================================================
-- MIGRACIÓN 008 - ESTADO COMERCIAL DEL COMPROBANTE
-- Separa el estado del comprobante (VIGENTE/ANULADO) del flujo
-- interno del ERP (BORRADOR/VALIDADO/OBSERVADO/PUBLICADO).
-- También incorpora Nota de crédito y Nota de débito.
-- Compatible con MariaDB/MySQL y segura para reejecución.
-- ============================================================

SET NAMES utf8mb4;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='documentos_cabecera'
      AND COLUMN_NAME='estado_comprobante'
);

SET @column_sql := IF(
    @column_exists=0,
    "ALTER TABLE documentos_cabecera ADD COLUMN estado_comprobante ENUM('VIGENTE','ANULADO') NOT NULL DEFAULT 'VIGENTE' AFTER sucursal_id",
    'SET @column_noop := 1'
);
PREPARE stmt_column FROM @column_sql;
EXECUTE stmt_column;
DEALLOCATE PREPARE stmt_column;

-- Conserva la intención de documentos anulados con el modelo anterior.
UPDATE documentos_cabecera
SET estado_comprobante='ANULADO'
WHERE estado_registro='ANULADO';

-- Si el documento ya había sido publicado antes de anularse, vuelve a quedar
-- PUBLICADO en el flujo interno para que continúe visible en API/reportes,
-- pero su estado comercial permanece ANULADO.
UPDATE documentos_cabecera
SET estado_registro='PUBLICADO'
WHERE estado_registro='ANULADO'
  AND published_at IS NOT NULL;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='documentos_cabecera'
      AND INDEX_NAME='ix_documentos_comprobante_fecha'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_documentos_comprobante_fecha ON documentos_cabecera(estado_comprobante,fecha,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

INSERT INTO tipos_documento(codigo,nombre,sunat_code)
VALUES
('FAC','Factura','01'),
('BOL','Boleta','03'),
('NCR','Nota de crédito','07'),
('NDB','Nota de débito','08')
ON DUPLICATE KEY UPDATE
    nombre=VALUES(nombre),
    sunat_code=VALUES(sunat_code);

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (8,'Estado comercial del comprobante y tipos 07/08');
