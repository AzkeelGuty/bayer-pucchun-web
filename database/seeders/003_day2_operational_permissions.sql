-- Dia 2 Alisson: additive grants; run only after explicit approval. No implicit ADMIN access.
START TRANSACTION;
INSERT INTO permisos(codigo,nombre) VALUES('documents.read','documents.read') ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);
INSERT INTO rol_permiso(rol_id,permiso_id) SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='documents.read' WHERE r.nombre IN ('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA') ON DUPLICATE KEY UPDATE permiso_id=VALUES(permiso_id);
INSERT INTO permisos(codigo,nombre) VALUES('documents.create','documents.create') ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);
INSERT INTO rol_permiso(rol_id,permiso_id) SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='documents.create' WHERE r.nombre IN ('ADMIN','DIGITADOR') ON DUPLICATE KEY UPDATE permiso_id=VALUES(permiso_id);
INSERT INTO permisos(codigo,nombre) VALUES('guides.read','guides.read') ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);
INSERT INTO rol_permiso(rol_id,permiso_id) SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='guides.read' WHERE r.nombre IN ('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA') ON DUPLICATE KEY UPDATE permiso_id=VALUES(permiso_id);
INSERT INTO permisos(codigo,nombre) VALUES('guides.create','guides.create') ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);
INSERT INTO rol_permiso(rol_id,permiso_id) SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='guides.create' WHERE r.nombre IN ('ADMIN','DIGITADOR') ON DUPLICATE KEY UPDATE permiso_id=VALUES(permiso_id);
INSERT INTO permisos(codigo,nombre) VALUES('stock.read','stock.read') ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);
INSERT INTO rol_permiso(rol_id,permiso_id) SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='stock.read' WHERE r.nombre IN ('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA') ON DUPLICATE KEY UPDATE permiso_id=VALUES(permiso_id);
INSERT INTO permisos(codigo,nombre) VALUES('stock.create','stock.create') ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);
INSERT INTO rol_permiso(rol_id,permiso_id) SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='stock.create' WHERE r.nombre IN ('ADMIN','DIGITADOR') ON DUPLICATE KEY UPDATE permiso_id=VALUES(permiso_id);
COMMIT;
