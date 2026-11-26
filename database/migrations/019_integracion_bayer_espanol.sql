-- 019: maestros y referencias fiscales de la integración Bayer v2.
-- Conserva los documentos históricos y la numeración interna.
SET NAMES utf8mb4;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vendedores' AND COLUMN_NAME='dni')=0,
'ALTER TABLE vendedores ADD COLUMN dni CHAR(8) NULL AFTER codigo','SET @noop019=1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='almacenes' AND COLUMN_NAME='distrito_id')=0,
'ALTER TABLE almacenes ADD COLUMN distrito_id INT NULL, ADD CONSTRAINT fk_almacen_despacho_geo FOREIGN KEY(distrito_id) REFERENCES distritos(id)','SET @noop019=1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS bayer_referencias_fiscales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    documento_id BIGINT NULL,
    guia_id BIGINT NULL,
    ruc_emisor CHAR(11) NOT NULL,
    tipo_cpe CHAR(2) NOT NULL,
    numero_fiscal VARCHAR(13) NULL,
    distrito_despacho_id INT NULL,
    registrado_por BIGINT NOT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_brf_documento (documento_id),
    UNIQUE KEY uq_brf_guia (guia_id),
    UNIQUE KEY uq_brf_numero (ruc_emisor,tipo_cpe,numero_fiscal),
    FOREIGN KEY(documento_id) REFERENCES documentos_cabecera(id) ON DELETE RESTRICT,
    FOREIGN KEY(guia_id) REFERENCES guias_cabecera(id) ON DELETE RESTRICT,
    FOREIGN KEY(distrito_despacho_id) REFERENCES distritos(id),
    FOREIGN KEY(registrado_por) REFERENCES usuarios(id),
    CONSTRAINT ck_brf_entidad CHECK ((documento_id IS NULL) <> (guia_id IS NULL))
) ENGINE=InnoDB;
INSERT IGNORE INTO schema_migrations(version,description)
VALUES (19,'Integracion Bayer en espanol, DNI, origen y referencias fiscales');
