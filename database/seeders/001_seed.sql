INSERT IGNORE INTO roles(nombre,descripcion) VALUES ('ADMIN','Administrador técnico'),('DIGITADOR','Captura información'),('SUPERVISOR','Valida y publica'),('GERENCIA','Consulta analítica'),('BAYER','Consulta y descarga datos publicados');
INSERT IGNORE INTO partners(codigo,nombre,tipo) VALUES ('BAYER','Bayer','FABRICANTE');
INSERT IGNORE INTO usuarios(nombre,email,password_hash,estado) VALUES ('Administrador','admin@local.test','$2y$12$blEEcZ5u.doRxHW5tlWO/ebUALjhHRs09y5TibYbD7N5W3v73X3Fu',1);
INSERT IGNORE INTO usuario_rol(usuario_id,rol_id) SELECT u.id,r.id FROM usuarios u,roles r WHERE u.email='admin@local.test' AND r.nombre='ADMIN';
