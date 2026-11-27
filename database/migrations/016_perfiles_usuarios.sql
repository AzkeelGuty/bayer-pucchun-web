-- 016 - campos opcionales del perfil de usuarios.
-- Ejecutar sobre base existente tras la migración 015.
-- No cambia hashes de contraseñas, cuentas ni roles existentes.
ALTER TABLE usuarios
    ADD COLUMN foto_perfil VARCHAR(120) NULL AFTER email,
    ADD COLUMN cargo VARCHAR(100) NULL AFTER foto_perfil,
    ADD COLUMN telefono VARCHAR(25) NULL AFTER cargo,
    ADD COLUMN actualizado_en DATETIME NULL AFTER created_at;
