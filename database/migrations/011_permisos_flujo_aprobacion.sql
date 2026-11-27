-- 011: permisos de flujo de aprobación para roles internos autorizados.
-- No concede acciones a DIGITADOR, GERENCIA ni BAYER.
-- ADMIN puede validar y publicar; SUPERVISOR puede validar/observar,
-- mientras que publicar para SUPERVISOR requiere concesión explícita.
-- Es idempotente y no altera datos operativos existentes.
START TRANSACTION;

INSERT IGNORE INTO permisos(codigo,nombre,descripcion) VALUES
  ('validation.review','Validar y observar','Permite validar borradores y observar registros'),
  ('publications.publish','Publicar y anular','Permite publicar registros validados y anular publicaciones');

INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id FROM roles r
JOIN permisos p ON p.codigo IN ('validation.review','publications.publish')
WHERE r.nombre='ADMIN';

INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id FROM roles r
JOIN permisos p ON p.codigo='validation.review'
WHERE r.nombre='SUPERVISOR';

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (11,'Permisos de validacion y publicacion para ADMIN y SUPERVISOR');

COMMIT;
