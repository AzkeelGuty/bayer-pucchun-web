-- ============================================================
-- MIGRACIÓN 006 - ÍNDICES DE RENDIMIENTO
-- Optimiza autocompletados, sugerencias y tablas paginadas.
-- Compatible con MariaDB/MySQL y segura para reejecución.
-- ============================================================

SET NAMES utf8mb4;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='clientes'
      AND INDEX_NAME='ix_clientes_codigo'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_clientes_codigo ON clientes(codigo)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='clientes'
      AND INDEX_NAME='ix_clientes_razon_social'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_clientes_razon_social ON clientes(razon_social)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='productos'
      AND INDEX_NAME='ix_productos_estado_nombre'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_productos_estado_nombre ON productos(estado,nombre,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='vendedores'
      AND INDEX_NAME='ix_vendedores_estado_nombres'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_vendedores_estado_nombres ON vendedores(estado,nombres,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='sucursales'
      AND INDEX_NAME='ix_sucursales_estado_nombre'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_sucursales_estado_nombre ON sucursales(estado,nombre,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='almacenes'
      AND INDEX_NAME='ix_almacenes_estado_nombre'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_almacenes_estado_nombre ON almacenes(estado,nombre,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='documentos_cabecera'
      AND INDEX_NAME='ix_documentos_cliente_fecha'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_documentos_cliente_fecha ON documentos_cabecera(cliente_id,fecha,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='guias_cabecera'
      AND INDEX_NAME='ix_guias_cliente_fecha'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_guias_cliente_fecha ON guias_cabecera(cliente_id,fecha,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='publicaciones'
      AND INDEX_NAME='ix_publicaciones_fecha'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_publicaciones_fecha ON publicaciones(fecha_publicacion,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='auditoria_acciones'
      AND INDEX_NAME='ix_auditoria_fecha'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_auditoria_fecha ON auditoria_acciones(fecha_hora,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='exportaciones'
      AND INDEX_NAME='ix_exportaciones_fecha'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_exportaciones_fecha ON exportaciones(generated_at,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='bitacora_acceso'
      AND INDEX_NAME='ix_bitacora_fecha'
);
SET @idx_sql := IF(
    @idx_exists=0,
    'CREATE INDEX ix_bitacora_fecha ON bitacora_acceso(fecha_hora,id)',
    'SET @idx_noop := 1'
);
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (6,'Indices de rendimiento y autocompletado remoto');
