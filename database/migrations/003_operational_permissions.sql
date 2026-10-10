-- Migración 003: permisos operativos granulares para instalaciones Schema v2 existentes.
-- Motivo: el backend integrado exige permisos persistidos documents.*, guides.* y stock.*.
-- Esta migración NO cambia tablas ni reemplaza Schema v2; solo inserta permisos y asignaciones de rol.
-- Es idempotente: puede ejecutarse nuevamente sin duplicar registros.

START TRANSACTION;

INSERT IGNORE INTO permisos(codigo,nombre,descripcion) VALUES
('documents.read','Consultar documentos','Consultar documentos según rol'),
('documents.create','Crear documentos','Crear y editar documentos en borrador'),
('guides.read','Consultar guías','Consultar guías de remisión según rol'),
('guides.create','Crear guías','Crear y editar guías en borrador'),
('stock.read','Consultar stock','Consultar cargas y movimientos de stock'),
('stock.create','Crear stock','Crear y editar cargas de stock en borrador');

-- ADMIN: lectura y captura completa de los tres módulos.
INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo IN (
    'documents.read','documents.create',
    'guides.read','guides.create',
    'stock.read','stock.create'
)
WHERE r.nombre='ADMIN';

-- DIGITADOR: lectura y captura; no valida ni publica.
INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo IN (
    'documents.read','documents.create',
    'guides.read','guides.create',
    'stock.read','stock.create'
)
WHERE r.nombre='DIGITADOR';

-- SUPERVISOR: solo consulta operativa; validación/publicación se controla con sus permisos específicos.
INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo IN ('documents.read','guides.read','stock.read')
WHERE r.nombre='SUPERVISOR';

-- GERENCIA: solo consulta operativa.
INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo IN ('documents.read','guides.read','stock.read')
WHERE r.nombre='GERENCIA';

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (3,'Permisos operativos granulares - Documentos, Guias y Stock');

COMMIT;
