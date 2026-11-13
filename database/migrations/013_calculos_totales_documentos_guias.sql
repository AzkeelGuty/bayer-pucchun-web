-- 013: modalidad del IGV e importes comerciales opcionales en las guías.
-- Los comprobantes y guías anteriores conservan NULL: no se presume IGV ni precio.
ALTER TABLE documentos_cabecera
    ADD COLUMN IF NOT EXISTS precio_igv_modalidad ENUM('INCLUIDO','ADICIONAL') NULL DEFAULT NULL;

ALTER TABLE guias_cabecera
    ADD COLUMN IF NOT EXISTS precio_igv_modalidad ENUM('INCLUIDO','ADICIONAL') NULL DEFAULT NULL;

ALTER TABLE guias_detalle
    ADD COLUMN IF NOT EXISTS valor_unitario DECIMAL(14,2) NULL DEFAULT NULL;

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (13,'Modalidad IGV e importes de productos en documentos y guias');
