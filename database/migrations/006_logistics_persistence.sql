-- Migracion 006: persistencia del proceso fisico de Logistica.
-- Requiere schema v2 (002) y sus catalogos. MySQL 8.0.16+ / MariaDB 10.11.
-- Aditiva: no modifica Guias, Stock ni tablas de otros equipos.
-- Ejecutar una sola vez. DDL no es transaccional: ante fallo parcial, revisar
-- la causa y usar el rollback separado antes de volver a aplicar.
-- Configurar la conexion de la aplicacion en UTC para DATETIME(6).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE logistica_preparaciones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sucursal_id INT NOT NULL,
    almacen_id INT NOT NULL,
    tipo_origen VARCHAR(20) NOT NULL,
    origen_ref VARCHAR(128) NOT NULL,
    fecha DATE NOT NULL,
    destino VARCHAR(500) NOT NULL,
    cliente_id BIGINT NULL,
    almacen_destino_id INT NULL,
    reserva_ref VARCHAR(128) NOT NULL,
    estado VARCHAR(40) NOT NULL DEFAULT 'EN_PREPARACION',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_log_preparacion_sede (id,sucursal_id),
    INDEX ix_log_preparacion_estado_fecha (sucursal_id,estado,fecha,id),
    INDEX ix_log_preparacion_fecha (sucursal_id,fecha,id),
    INDEX ix_log_preparacion_origen (sucursal_id,tipo_origen,origen_ref),
    CONSTRAINT fk_log_preparacion_sede FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_preparacion_almacen FOREIGN KEY (almacen_id)
        REFERENCES almacenes(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_preparacion_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_preparacion_destino FOREIGN KEY (almacen_destino_id)
        REFERENCES almacenes(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_preparacion_created FOREIGN KEY (created_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_preparacion_updated FOREIGN KEY (updated_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_preparacion_tipo CHECK (BINARY tipo_origen IN ('VENTA','TRASLADO')),
    CONSTRAINT ck_log_preparacion_estado CHECK
        (BINARY estado IN ('EN_PREPARACION','PREPARADA','CANCELADA')),
    CONSTRAINT ck_log_preparacion_version CHECK (version >= 1),
    CONSTRAINT ck_log_preparacion_fecha CHECK (fecha >= '1000-01-01' AND DAYOFMONTH(fecha) >= 1),
    CONSTRAINT ck_log_preparacion_origen CHECK (CHAR_LENGTH(TRIM(origen_ref)) > 0),
    CONSTRAINT ck_log_preparacion_reserva CHECK (CHAR_LENGTH(TRIM(reserva_ref)) > 0),
    CONSTRAINT ck_log_preparacion_destino CHECK (CHAR_LENGTH(TRIM(destino)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logistica_preparacion_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    preparacion_id BIGINT NOT NULL,
    posicion INT UNSIGNED NOT NULL,
    origen_linea_ref VARCHAR(128) NOT NULL,
    producto_id BIGINT NOT NULL,
    unidad_id INT NOT NULL,
    lote_id BIGINT NULL,
    cantidad DECIMAL(14,3) NOT NULL,
    UNIQUE KEY uq_log_preparacion_posicion (preparacion_id,posicion),
    UNIQUE KEY uq_log_preparacion_linea_padre (id,preparacion_id),
    CONSTRAINT fk_log_preparacion_detalle_padre FOREIGN KEY (preparacion_id)
        REFERENCES logistica_preparaciones(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_preparacion_detalle_producto FOREIGN KEY (producto_id)
        REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_preparacion_detalle_unidad FOREIGN KEY (unidad_id)
        REFERENCES unidades_medida(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_preparacion_detalle_lote FOREIGN KEY (lote_id,producto_id)
        REFERENCES lotes(id,producto_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_preparacion_detalle_posicion CHECK (posicion >= 1),
    CONSTRAINT ck_log_preparacion_detalle_origen CHECK (CHAR_LENGTH(TRIM(origen_linea_ref)) > 0),
    CONSTRAINT ck_log_preparacion_detalle_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logistica_despachos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    preparacion_id BIGINT NOT NULL,
    sucursal_id INT NOT NULL,
    fecha DATE NOT NULL,
    guia_id BIGINT NULL,
    guia_version INT UNSIGNED NULL,
    guia_snapshot LONGTEXT NULL,
    movimiento_salida_ref VARCHAR(128) NULL,
    resolucion_ref VARCHAR(128) NULL,
    estado VARCHAR(40) NOT NULL DEFAULT 'PENDIENTE',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_log_despacho_preparacion (preparacion_id),
    UNIQUE KEY uq_log_despacho_guia (guia_id),
    UNIQUE KEY uq_log_despacho_preparacion_padre (id,preparacion_id),
    UNIQUE KEY uq_log_despacho_sede (id,sucursal_id),
    INDEX ix_log_despacho_estado_fecha (sucursal_id,estado,fecha,id),
    INDEX ix_log_despacho_fecha (sucursal_id,fecha,id),
    CONSTRAINT fk_log_despacho_preparacion_sede FOREIGN KEY (preparacion_id,sucursal_id)
        REFERENCES logistica_preparaciones(id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_despacho_guia FOREIGN KEY (guia_id)
        REFERENCES guias_cabecera(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_despacho_created FOREIGN KEY (created_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_despacho_updated FOREIGN KEY (updated_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_despacho_estado CHECK (BINARY estado IN
        ('PENDIENTE','DESPACHADO','EN_TRANSITO','ENTREGADO','CANCELADO','CERRADO_CON_INCIDENCIA')),
    CONSTRAINT ck_log_despacho_version CHECK (version >= 1),
    CONSTRAINT ck_log_despacho_fecha CHECK (fecha >= '1000-01-01' AND DAYOFMONTH(fecha) >= 1),
    CONSTRAINT ck_log_despacho_guia_version CHECK
        ((guia_id IS NULL AND guia_version IS NULL)
        OR (guia_id IS NOT NULL AND guia_version IS NOT NULL AND guia_version >= 1)),
    CONSTRAINT ck_log_despacho_snapshot CHECK (guia_snapshot IS NULL OR JSON_VALID(guia_snapshot)),
    CONSTRAINT ck_log_despacho_movimiento CHECK
        (movimiento_salida_ref IS NULL OR CHAR_LENGTH(TRIM(movimiento_salida_ref)) > 0),
    CONSTRAINT ck_log_despacho_resolucion CHECK
        (resolucion_ref IS NULL OR CHAR_LENGTH(TRIM(resolucion_ref)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logistica_despacho_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    despacho_id BIGINT NOT NULL,
    preparacion_id BIGINT NOT NULL,
    preparacion_detalle_id BIGINT NOT NULL,
    producto_id BIGINT NOT NULL,
    unidad_id INT NOT NULL,
    lote_id BIGINT NULL,
    cantidad DECIMAL(14,3) NOT NULL,
    UNIQUE KEY uq_log_despacho_preparacion_linea (preparacion_detalle_id),
    UNIQUE KEY uq_log_despacho_linea_padre (id,despacho_id),
    CONSTRAINT fk_log_despacho_detalle_despacho FOREIGN KEY (despacho_id,preparacion_id)
        REFERENCES logistica_despachos(id,preparacion_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_despacho_detalle_preparacion FOREIGN KEY (preparacion_detalle_id,preparacion_id)
        REFERENCES logistica_preparacion_detalle(id,preparacion_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_despacho_detalle_producto FOREIGN KEY (producto_id)
        REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_despacho_detalle_unidad FOREIGN KEY (unidad_id)
        REFERENCES unidades_medida(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_despacho_detalle_lote FOREIGN KEY (lote_id,producto_id)
        REFERENCES lotes(id,producto_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_despacho_detalle_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logistica_entregas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    despacho_id BIGINT NOT NULL,
    sucursal_id INT NOT NULL,
    fecha DATE NOT NULL,
    recibido_por VARCHAR(200) NOT NULL,
    observaciones TEXT NULL,
    recepcion_ref VARCHAR(128) NULL,
    anulada TINYINT NOT NULL DEFAULT 0,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    anulada_by BIGINT NULL,
    anulada_at DATETIME(6) NULL,
    motivo_anulacion TEXT NULL,
    created_by BIGINT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_log_entrega_despacho_padre (id,despacho_id),
    UNIQUE KEY uq_log_entrega_sede (id,sucursal_id),
    INDEX ix_log_entrega_sede_fecha (sucursal_id,fecha,id),
    INDEX ix_log_entrega_despacho_vigente (despacho_id,anulada,id),
    CONSTRAINT fk_log_entrega_despacho_sede FOREIGN KEY (despacho_id,sucursal_id)
        REFERENCES logistica_despachos(id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_entrega_created FOREIGN KEY (created_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_entrega_anulada_by FOREIGN KEY (anulada_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_entrega_fecha CHECK (fecha >= '1000-01-01' AND DAYOFMONTH(fecha) >= 1),
    CONSTRAINT ck_log_entrega_version CHECK (version >= 1),
    CONSTRAINT ck_log_entrega_recibido CHECK (CHAR_LENGTH(TRIM(recibido_por)) > 0),
    CONSTRAINT ck_log_entrega_recepcion CHECK
        (recepcion_ref IS NULL OR CHAR_LENGTH(TRIM(recepcion_ref)) > 0),
    CONSTRAINT ck_log_entrega_anulada CHECK
        ((anulada=0 AND anulada_by IS NULL AND anulada_at IS NULL AND motivo_anulacion IS NULL)
        OR (anulada=1 AND anulada_by IS NOT NULL AND anulada_at IS NOT NULL
            AND motivo_anulacion IS NOT NULL AND CHAR_LENGTH(TRIM(motivo_anulacion)) > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logistica_entrega_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    entrega_id BIGINT NOT NULL,
    despacho_id BIGINT NOT NULL,
    despacho_detalle_id BIGINT NOT NULL,
    cantidad DECIMAL(14,3) NOT NULL,
    UNIQUE KEY uq_log_entrega_linea (entrega_id,despacho_detalle_id),
    CONSTRAINT fk_log_entrega_detalle_entrega FOREIGN KEY (entrega_id,despacho_id)
        REFERENCES logistica_entregas(id,despacho_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_entrega_detalle_despacho FOREIGN KEY (despacho_detalle_id,despacho_id)
        REFERENCES logistica_despacho_detalle(id,despacho_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_entrega_detalle_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logistica_operaciones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sucursal_id INT NOT NULL,
    clave VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    accion VARCHAR(60) NOT NULL,
    actor_id BIGINT NOT NULL,
    request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    result_json LONGTEXT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_log_operacion_clave (sucursal_id,clave),
    UNIQUE KEY uq_log_operacion_sede (id,sucursal_id),
    CONSTRAINT fk_log_operacion_sede FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_operacion_actor FOREIGN KEY (actor_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_operacion_clave CHECK (CHAR_LENGTH(clave) BETWEEN 1 AND 128),
    CONSTRAINT ck_log_operacion_accion CHECK (CHAR_LENGTH(TRIM(accion)) > 0),
    CONSTRAINT ck_log_operacion_hash CHECK
        (CHAR_LENGTH(request_hash)=64 AND request_hash REGEXP '^[0-9a-f]{64}$'),
    CONSTRAINT ck_log_operacion_resultado CHECK (result_json IS NULL OR JSON_VALID(result_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logistica_historial (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sucursal_id INT NOT NULL,
    preparacion_id BIGINT NULL,
    despacho_id BIGINT NULL,
    entrega_id BIGINT NULL,
    operacion_id BIGINT NOT NULL,
    accion VARCHAR(60) NOT NULL,
    estado_anterior VARCHAR(40) NULL,
    estado_nuevo VARCHAR(40) NULL,
    motivo TEXT NULL,
    actor_id BIGINT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX ix_log_historial_preparacion (sucursal_id,preparacion_id,id),
    INDEX ix_log_historial_despacho (sucursal_id,despacho_id,id),
    INDEX ix_log_historial_entrega (sucursal_id,entrega_id,id),
    INDEX ix_log_historial_sede_fecha (sucursal_id,created_at,id),
    CONSTRAINT fk_log_historial_preparacion_sede FOREIGN KEY (preparacion_id,sucursal_id)
        REFERENCES logistica_preparaciones(id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_historial_despacho_sede FOREIGN KEY (despacho_id,sucursal_id)
        REFERENCES logistica_despachos(id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_historial_entrega_sede FOREIGN KEY (entrega_id,sucursal_id)
        REFERENCES logistica_entregas(id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_historial_despacho_preparacion FOREIGN KEY (despacho_id,preparacion_id)
        REFERENCES logistica_despachos(id,preparacion_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_historial_entrega_despacho FOREIGN KEY (entrega_id,despacho_id)
        REFERENCES logistica_entregas(id,despacho_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_historial_operacion_sede FOREIGN KEY (operacion_id,sucursal_id)
        REFERENCES logistica_operaciones(id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_historial_actor FOREIGN KEY (actor_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_historial_agregado CHECK
        (preparacion_id IS NOT NULL OR despacho_id IS NOT NULL OR entrega_id IS NOT NULL),
    CONSTRAINT ck_log_historial_accion CHECK (CHAR_LENGTH(TRIM(accion)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schema_migrations(version,description)
VALUES (6,'Persistencia Logistica: preparaciones, despachos, entregas e idempotencia');
