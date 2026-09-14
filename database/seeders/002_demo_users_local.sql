-- DATOS DE PRUEBA PARA VALIDAR ROLES Y PERMISOS.
-- NO ejecutar en producción.
-- Cuentas de prueba para verificar permisos y vistas por rol.

INSERT INTO usuarios(nombre,email,password_hash,estado) VALUES
('Administrador Pucchún','admin@pucchun.pe','$2y$12$AiXc5/3cm787jLV44x5Tt.GvUE2PnvzvO48Esg9e9fkPMoGzb8hSK',1),
('María Torres Salazar','digitacion@pucchun.pe','$2y$12$Mtd7wDvFfSNhkcm9B1GVjuyCjX3A2IQ0oIXAZXYNr.LWAdR9WdLB6',1),
('Carlos Mendoza Ruiz','supervision@pucchun.pe','$2y$12$5ePAGIoqnKVkrOck7eUIDe2Y2RNjcCIqtsGufiliOvv2b6raufarC',1),
('Andrea Fernández Vega','gerencia@pucchun.pe','$2y$12$qFQYJw2scg10dj.qnRAPeePc8wNmlM3gM2.EpHQ7jaUTcaIcZyve2',1),
('Consulta Bayer','consulta@bayer.pe','$2y$12$/eNMM24FynAKhVJmniEsleDleGs15TxlGn1/Uolv/nAav5CHliI1W',1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),password_hash=VALUES(password_hash),estado=1;

INSERT IGNORE INTO usuario_rol(usuario_id,rol_id)
SELECT u.id,r.id FROM usuarios u JOIN roles r ON r.nombre='ADMIN' WHERE u.email='admin@pucchun.pe';
INSERT IGNORE INTO usuario_rol(usuario_id,rol_id)
SELECT u.id,r.id FROM usuarios u JOIN roles r ON r.nombre='DIGITADOR' WHERE u.email='digitacion@pucchun.pe';
INSERT IGNORE INTO usuario_rol(usuario_id,rol_id)
SELECT u.id,r.id FROM usuarios u JOIN roles r ON r.nombre='SUPERVISOR' WHERE u.email='supervision@pucchun.pe';
INSERT IGNORE INTO usuario_rol(usuario_id,rol_id)
SELECT u.id,r.id FROM usuarios u JOIN roles r ON r.nombre='GERENCIA' WHERE u.email='gerencia@pucchun.pe';
INSERT IGNORE INTO usuario_rol(usuario_id,rol_id)
SELECT u.id,r.id FROM usuarios u JOIN roles r ON r.nombre='BAYER' WHERE u.email='consulta@bayer.pe';
