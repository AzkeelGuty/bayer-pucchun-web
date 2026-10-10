-- ============================================================
-- MIGRACIÓN 007 - TIPOS DE DOCUMENTO DE VENTA
-- Asegura las dos opciones usadas en captura de documentos.
-- No modifica registros existentes.
-- ============================================================

SET NAMES utf8mb4;

INSERT INTO tipos_documento(codigo,nombre,sunat_code)
VALUES
('FAC','Factura','01'),
('BOL','Boleta','03')
ON DUPLICATE KEY UPDATE
    nombre=VALUES(nombre),
    sunat_code=VALUES(sunat_code);

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (7,'Factura y Boleta para captura de documentos');
