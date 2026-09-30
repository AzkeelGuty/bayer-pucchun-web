-- Catálogos masivos entregados por el cliente + soporte de búsqueda incremental.
-- Compatible con una base Schema v2 existente. Ejecutar una sola vez.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS proveedores (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    estado TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET @has_tipo_art := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'tipo_art'
);
SET @sql_tipo_art := IF(@has_tipo_art = 0,
    'ALTER TABLE productos ADD COLUMN tipo_art VARCHAR(20) NULL AFTER nombre',
    'SELECT 1'
);
PREPARE stmt_tipo_art FROM @sql_tipo_art;
EXECUTE stmt_tipo_art;
DEALLOCATE PREPARE stmt_tipo_art;
