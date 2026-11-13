-- 012: Emisión SUNAT separada del flujo interno del ERP.
-- Requiere migraciones 002 a 011. No modifica documentos ni guías históricos.
-- Identificadores INT-* nunca se convierten en series tributarias.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sunat_series_fiscales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ruc_emisor CHAR(11) NOT NULL,
    tipo_cpe CHAR(2) NOT NULL,
    serie CHAR(4) NOT NULL,
    ambiente ENUM('BETA','PRODUCCION') NOT NULL,
    siguiente BIGINT UNSIGNED NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sunat_serie (ruc_emisor,tipo_cpe,serie,ambiente),
    CONSTRAINT ck_sunat_correlativo CHECK (siguiente >= 1)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sunat_emisiones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    documento_id BIGINT NOT NULL,
    usuario_id BIGINT NOT NULL,
    ruc_emisor CHAR(11) NOT NULL,
    tipo_cpe CHAR(2) NOT NULL,
    serie CHAR(4) NOT NULL,
    correlativo BIGINT UNSIGNED NOT NULL,
    ambiente ENUM('BETA','PRODUCCION') NOT NULL,
    estado ENUM('EN_PROCESO','ACEPTADO','ACEPTADO_OBSERVADO','RECHAZADO','SIN_RESPUESTA') NOT NULL DEFAULT 'EN_PROCESO',
    codigo_cdr VARCHAR(12) NULL,
    mensaje_cdr TEXT NULL,
    observaciones TEXT NULL,
    xml_sha256 CHAR(64) NULL,
    xml_archivo VARCHAR(190) NULL,
    cdr_archivo VARCHAR(190) NULL,
    fecha_envio DATETIME NULL,
    fecha_respuesta DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sunat_documento_ambiente (documento_id,ambiente),
    UNIQUE KEY uq_sunat_fiscal (ruc_emisor,tipo_cpe,serie,correlativo,ambiente),
    KEY ix_sunat_estado (estado,creado_en),
    CONSTRAINT fk_sunat_documento FOREIGN KEY (documento_id) REFERENCES documentos_cabecera(id),
    CONSTRAINT fk_sunat_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (12,'Emision tributaria CPE y trazabilidad de XML/CDR');
