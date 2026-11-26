-- PUCCHUN DATA HUB | Actualizacion 019 y control de datos existentes
-- Seleccionar la base del ERP en phpMyAdmin. Requiere migraciones hasta 018.
-- Exportar respaldo antes de importar. No modifica cantidades, precios ni numeracion existente.
-- Es repetible; la instalacion contiene DDL y MySQL puede confirmar implicitamente.
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

-- CONSULTAS DE CONTROL. Solo lectura sobre datos de negocio.
-- El número de registros API corresponde a líneas de producto, no cabeceras.
SELECT DATABASE() AS base_actual;
SELECT version,description,applied_at FROM schema_migrations ORDER BY version DESC;

SELECT 'FACTURAS / DOCUMENTOS' AS conjunto, COUNT(*) AS publicados,
 COALESCE(SUM(CASE WHEN em.estado IN ('ACEPTADO','ACEPTADO_OBSERVADO') OR (em.id IS NULL AND rf.numero_fiscal IS NOT NULL) THEN 1 ELSE 0 END),0) AS disponibles_api_v2,
 COALESCE(SUM(CASE WHEN em.estado IN ('ACEPTADO','ACEPTADO_OBSERVADO') OR (em.id IS NULL AND rf.numero_fiscal IS NOT NULL) THEN 0 ELSE 1 END),0) AS pendientes_numero_fiscal
FROM documentos_cabecera h
JOIN sucursales s ON s.id=h.sucursal_id JOIN empresas e ON e.id=s.empresa_id
LEFT JOIN sunat_emisiones em ON em.documento_id=h.id AND em.ambiente='PRODUCCION' AND em.ruc_emisor=e.ruc
LEFT JOIN bayer_referencias_fiscales rf ON rf.documento_id=h.id AND rf.ruc_emisor=e.ruc
WHERE h.estado_registro='PUBLICADO'
UNION ALL
SELECT 'GUIAS',COUNT(*),COALESCE(SUM(rf.numero_fiscal IS NOT NULL),0),COALESCE(SUM(rf.numero_fiscal IS NULL),0)
FROM guias_cabecera h
JOIN sucursales s ON s.id=h.sucursal_id JOIN empresas e ON e.id=s.empresa_id
LEFT JOIN bayer_referencias_fiscales rf ON rf.guia_id=h.id AND rf.ruc_emisor=e.ruc
WHERE h.estado_registro='PUBLICADO'
UNION ALL
SELECT 'STOCK',COUNT(*),COUNT(*),0
FROM stock_cabecera h WHERE h.estado_registro='PUBLICADO';

-- Identificar documentos pendientes: NO se acredita una emisión por el formato.
SELECT h.id AS id_documento,h.numero AS referencia_existente,h.fecha,
 td.codigo AS tipo_documento,e.ruc AS ruc_distribuidor,s.nombre AS sucursal,
 COALESCE(em.estado,'SIN_EMISION_PRODUCCION') AS estado_emision
FROM documentos_cabecera h JOIN tipos_documento td ON td.id=h.tipo_documento_id
JOIN sucursales s ON s.id=h.sucursal_id JOIN empresas e ON e.id=s.empresa_id
LEFT JOIN sunat_emisiones em ON em.documento_id=h.id AND em.ambiente='PRODUCCION' AND em.ruc_emisor=e.ruc
LEFT JOIN bayer_referencias_fiscales rf ON rf.documento_id=h.id AND rf.ruc_emisor=e.ruc
WHERE h.estado_registro='PUBLICADO'
AND CASE WHEN em.estado IN ('ACEPTADO','ACEPTADO_OBSERVADO') THEN 0
         WHEN em.id IS NULL AND rf.numero_fiscal IS NOT NULL THEN 0 ELSE 1 END=1
ORDER BY h.fecha DESC,h.id DESC;

SELECT h.id AS id_guia,h.numero AS referencia_existente,h.fecha,
 h.estado_sunat,s.nombre AS sucursal,h.documento_origen_id
FROM guias_cabecera h JOIN sucursales s ON s.id=h.sucursal_id
JOIN empresas e ON e.id=s.empresa_id
LEFT JOIN bayer_referencias_fiscales rf ON rf.guia_id=h.id AND rf.ruc_emisor=e.ruc
WHERE h.estado_registro='PUBLICADO' AND rf.numero_fiscal IS NULL
ORDER BY h.fecha DESC,h.id DESC;

-- Datos maestros actuales. No se inventan RUC ni DNI.
SELECT id,ruc,razon_social FROM empresas ORDER BY id;
SELECT id,codigo,dni,nombres,apellidos FROM vendedores
WHERE dni IS NULL OR dni NOT REGEXP '^[0-9]{8}$' ORDER BY codigo;
SELECT s.id,s.codigo,s.nombre,dp.nombre AS departamento,p.nombre AS provincia,d.nombre AS distrito
FROM sucursales s LEFT JOIN distritos d ON d.id=s.distrito_id
LEFT JOIN provincias p ON p.id=d.provincia_id LEFT JOIN departamentos dp ON dp.id=p.departamento_id
ORDER BY s.codigo;
SELECT a.id,a.codigo,a.nombre,s.nombre AS sucursal,dp.nombre AS departamento,p.nombre AS provincia,d.nombre AS distrito,
CASE WHEN a.distrito_id IS NOT NULL THEN 'ALMACEN' WHEN s.distrito_id IS NOT NULL THEN 'SUCURSAL' ELSE 'SIN_UBICACION' END AS fuente_ubicacion
FROM almacenes a JOIN sucursales s ON s.id=a.sucursal_id
LEFT JOIN distritos d ON d.id=COALESCE(a.distrito_id,s.distrito_id)
LEFT JOIN provincias p ON p.id=d.provincia_id LEFT JOIN departamentos dp ON dp.id=p.departamento_id
ORDER BY a.codigo;
