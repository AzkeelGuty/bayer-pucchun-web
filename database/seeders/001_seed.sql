INSERT IGNORE INTO roles(nombre,descripcion) VALUES
('ADMIN','Administrador técnico'),
('DIGITADOR','Captura información'),
('SUPERVISOR','Valida y publica'),
('GERENCIA','Consulta analítica'),
('BAYER','Consulta y descarga datos publicados');

INSERT IGNORE INTO permisos(codigo,nombre,descripcion) VALUES
('users.manage','Gestionar usuarios','Alta, estado y roles de usuarios'),
('catalogs.view','Consultar catálogos','Consulta de maestros internos'),
('catalogs.manage','Gestionar catálogos','Administración de datos maestros'),
('homologations.manage','Gestionar homologaciones','Mapeo Pucchún-Bayer'),
('operations.capture','Capturar operación','Crear y editar borradores'),
('operations.view','Consultar operación','Consulta de documentos, guías y stock'),
('validation.review','Validar información','Revisión y observación de datos'),
('publications.publish','Publicar información','Habilita datos para consumo'),
('dashboard.puchun.view','Dashboard Pucchún','Analítica operativa interna'),
('dashboard.bayer.view','Dashboard Bayer','Analítica externa publicada'),
('published_data.view','Consultar datos publicados','Solo información publicada'),
('exports.create','Generar exportaciones','XLSX, CSV, JSON, TXT y PDF'),
('audit.view','Consultar auditoría','Trazabilidad de acciones'),
('api.consume','Consumir API futura','Acceso de solo lectura cuando la API esté habilitada');

INSERT IGNORE INTO partners(codigo,nombre,tipo) VALUES ('BAYER','Bayer','FABRICANTE');

INSERT IGNORE INTO usuarios(nombre,email,password_hash,estado) VALUES
('Administrador Pucchún','admin@pucchun.pe','$2y$12$AiXc5/3cm787jLV44x5Tt.GvUE2PnvzvO48Esg9e9fkPMoGzb8hSK',1);
INSERT IGNORE INTO usuario_rol(usuario_id,rol_id)
SELECT u.id,r.id FROM usuarios u,roles r WHERE u.email='admin@pucchun.pe' AND r.nombre='ADMIN';

-- ADMIN: gobierno completo.
INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p WHERE r.nombre='ADMIN';

-- DIGITADOR: captura y consulta operativa.
INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo IN ('catalogs.view','operations.capture','operations.view','dashboard.puchun.view')
WHERE r.nombre='DIGITADOR';

-- SUPERVISOR: calidad, publicación y control.
INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo IN ('catalogs.view','operations.view','validation.review','publications.publish','dashboard.puchun.view','exports.create','audit.view')
WHERE r.nombre='SUPERVISOR';

-- GERENCIA: analítica y reportes sin captura.
INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo IN ('operations.view','dashboard.puchun.view','published_data.view','exports.create','audit.view')
WHERE r.nombre='GERENCIA';

-- BAYER: consulta externa publicada.
INSERT IGNORE INTO rol_permiso(rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo IN ('dashboard.bayer.view','published_data.view','exports.create','api.consume')
WHERE r.nombre='BAYER';
