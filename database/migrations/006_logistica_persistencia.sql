-- Migracion 006: persistencia estructural G7 Logistica.
-- Requiere Schema v2 y migraciones previas. No modifica tablas existentes.
-- Reejecutable sobre la estructura creada por esta migracion; no reconcilia DDL divergente.
-- Acumulados, transiciones y append-only se protegeran en Repositories/Services.
-- Prerrequisito: base utf8mb4; tablas heredan su charset/collation.
-- Una escritura logica genera una entrada por agregado/version resultante.
-- Varios hechos comparten accion principal y metadata; no es event sourcing.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE IF NOT EXISTS logistica_preparaciones_cabecera (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    idempotency_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CONSTRAINT fk_lg_prep_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_prep_editor FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_prep_version CHECK (version >= 1),
    CONSTRAINT ck_lg_prep_key CHECK (CHAR_LENGTH(TRIM(idempotency_key)) BETWEEN 1 AND 64),
    CONSTRAINT ck_lg_prep_hash CHECK (CHAR_LENGTH(request_hash)=64 AND request_hash REGEXP '^[0-9a-f]+$'),
    UNIQUE KEY uq_lg_prep_key (idempotency_key),
    fecha_preparacion DATETIME NOT NULL,
    almacen_id INT NOT NULL,
    cliente_id BIGINT NULL,
    direccion_destino VARCHAR(255) NOT NULL,
    departamento_id INT NULL,
    provincia_id INT NULL,
    distrito_id INT NULL,
    estado_preparacion VARCHAR(20) NOT NULL DEFAULT 'EN_PREPARACION',
    observacion TEXT NULL,
    origen_sistema VARCHAR(40) NULL,
    origen_documento_ref VARCHAR(100) NULL,
    CONSTRAINT fk_lg_prep_almacen FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_prep_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_prep_dep FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_prep_prov FOREIGN KEY (provincia_id) REFERENCES provincias(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_prep_dist FOREIGN KEY (distrito_id) REFERENCES distritos(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_prep_estado CHECK (BINARY estado_preparacion IN ('EN_PREPARACION','PREPARADA','CANCELADA')),
    CONSTRAINT ck_lg_prep_destino CHECK (CHAR_LENGTH(TRIM(direccion_destino)) > 0),
    CONSTRAINT ck_lg_prep_geo CHECK ((departamento_id IS NULL AND provincia_id IS NULL AND distrito_id IS NULL) OR (departamento_id IS NOT NULL AND provincia_id IS NOT NULL AND distrito_id IS NOT NULL)),
    CONSTRAINT ck_lg_prep_origen CHECK ((origen_sistema IS NULL AND origen_documento_ref IS NULL) OR (origen_sistema IS NOT NULL AND origen_documento_ref IS NOT NULL AND CHAR_LENGTH(TRIM(origen_sistema))>0 AND CHAR_LENGTH(TRIM(origen_documento_ref))>0)),
    INDEX ix_lg_prep_estado_fecha (estado_preparacion,fecha_preparacion,id),
    INDEX ix_lg_prep_almacen_fecha (almacen_id,fecha_preparacion,id),
    INDEX ix_lg_prep_origen (origen_sistema,origen_documento_ref)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logistica_preparaciones_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    preparacion_id BIGINT NOT NULL,
    producto_id BIGINT NOT NULL,
    unidad_id INT NOT NULL,
    lote_id BIGINT NULL,
    cantidad_preparada DECIMAL(14,3) NOT NULL,
    origen_linea_ref VARCHAR(100) NULL,
    CONSTRAINT fk_lg_pd_prep FOREIGN KEY (preparacion_id) REFERENCES logistica_preparaciones_cabecera(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_pd_prod FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_pd_unidad FOREIGN KEY (unidad_id) REFERENCES unidades_medida(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_pd_lote FOREIGN KEY (lote_id,producto_id) REFERENCES lotes(id,producto_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_pd_cantidad CHECK (cantidad_preparada >= 0),
    UNIQUE KEY uq_lg_pd_contexto (id,preparacion_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logistica_despachos_cabecera (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    idempotency_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CONSTRAINT fk_lg_desp_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_desp_editor FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_desp_version CHECK (version >= 1),
    CONSTRAINT ck_lg_desp_key CHECK (CHAR_LENGTH(TRIM(idempotency_key)) BETWEEN 1 AND 64),
    CONSTRAINT ck_lg_desp_hash CHECK (CHAR_LENGTH(request_hash)=64 AND request_hash REGEXP '^[0-9a-f]+$'),
    UNIQUE KEY uq_lg_desp_key (idempotency_key),
    preparacion_id BIGINT NOT NULL,
    fecha_despacho DATETIME NOT NULL,
    salida_at DATETIME NULL,
    estado_fisico VARCHAR(25) NOT NULL DEFAULT 'PENDIENTE',
    guia_id BIGINT NULL,
    guia_version INT UNSIGNED NULL,
    observacion TEXT NULL,
    CONSTRAINT fk_lg_desp_prep FOREIGN KEY (preparacion_id) REFERENCES logistica_preparaciones_cabecera(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_desp_guia FOREIGN KEY (guia_id) REFERENCES guias_cabecera(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_desp_estado CHECK (BINARY estado_fisico IN ('PENDIENTE','EN_TRANSITO','ENTREGADO','CERRADO_CON_INCIDENCIA','CANCELADO')),
    CONSTRAINT ck_lg_desp_guia CHECK ((guia_id IS NULL AND guia_version IS NULL) OR (guia_id IS NOT NULL AND guia_version IS NOT NULL AND guia_version >= 1)),
    UNIQUE KEY uq_lg_desp_guia (guia_id),
    UNIQUE KEY uq_lg_desp_contexto (id,preparacion_id),
    INDEX ix_lg_desp_estado_fecha (estado_fisico,fecha_despacho,id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logistica_despachos_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    despacho_id BIGINT NOT NULL,
    preparacion_id BIGINT NOT NULL,
    preparacion_detalle_id BIGINT NOT NULL,
    cantidad_despachada DECIMAL(14,3) NOT NULL,
    CONSTRAINT fk_lg_dd_desp FOREIGN KEY (despacho_id,preparacion_id) REFERENCES logistica_despachos_cabecera(id,preparacion_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_dd_prep FOREIGN KEY (preparacion_detalle_id,preparacion_id) REFERENCES logistica_preparaciones_detalle(id,preparacion_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_dd_cantidad CHECK (cantidad_despachada > 0),
    UNIQUE KEY uq_lg_dd_linea (despacho_id,preparacion_detalle_id),
    UNIQUE KEY uq_lg_dd_contexto (id,despacho_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logistica_entregas_cabecera (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    idempotency_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CONSTRAINT fk_lg_ent_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_ent_editor FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_ent_version CHECK (version >= 1),
    CONSTRAINT ck_lg_ent_key CHECK (CHAR_LENGTH(TRIM(idempotency_key)) BETWEEN 1 AND 64),
    CONSTRAINT ck_lg_ent_hash CHECK (CHAR_LENGTH(request_hash)=64 AND request_hash REGEXP '^[0-9a-f]+$'),
    UNIQUE KEY uq_lg_ent_key (idempotency_key),
    despacho_id BIGINT NOT NULL,
    fecha_entrega DATETIME NOT NULL,
    resultado VARCHAR(20) NOT NULL,
    receptor_nombre VARCHAR(150) NULL,
    receptor_documento VARCHAR(30) NULL,
    observacion TEXT NULL,
    anulada_at DATETIME NULL,
    anulada_by BIGINT NULL,
    motivo_anulacion TEXT NULL,
    CONSTRAINT fk_lg_ent_desp FOREIGN KEY (despacho_id) REFERENCES logistica_despachos_cabecera(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_ent_anula FOREIGN KEY (anulada_by) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_ent_resultado CHECK (BINARY resultado IN ('CON_RECEPCION','RECHAZADA','FALLIDA')),
    CONSTRAINT ck_lg_ent_anulacion CHECK ((anulada_at IS NULL AND anulada_by IS NULL AND motivo_anulacion IS NULL) OR (anulada_at IS NOT NULL AND anulada_by IS NOT NULL AND motivo_anulacion IS NOT NULL AND CHAR_LENGTH(TRIM(motivo_anulacion))>0)),
    UNIQUE KEY uq_lg_ent_contexto (id,despacho_id),
    INDEX ix_lg_ent_desp_fecha (despacho_id,fecha_entrega,id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logistica_entregas_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    entrega_id BIGINT NOT NULL,
    despacho_id BIGINT NOT NULL,
    despacho_detalle_id BIGINT NOT NULL,
    cantidad_aceptada DECIMAL(14,3) NOT NULL,
    cantidad_rechazada DECIMAL(14,3) NOT NULL DEFAULT 0,
    motivo_rechazo VARCHAR(255) NULL,
    CONSTRAINT fk_lg_ed_ent FOREIGN KEY (entrega_id,despacho_id) REFERENCES logistica_entregas_cabecera(id,despacho_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_ed_desp FOREIGN KEY (despacho_detalle_id,despacho_id) REFERENCES logistica_despachos_detalle(id,despacho_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_ed_cantidad CHECK (cantidad_aceptada >= 0 AND cantidad_rechazada >= 0 AND cantidad_aceptada + cantidad_rechazada > 0),
    CONSTRAINT ck_lg_ed_rechazo CHECK (cantidad_rechazada = 0 OR (motivo_rechazo IS NOT NULL AND CHAR_LENGTH(TRIM(motivo_rechazo))>0)),
    UNIQUE KEY uq_lg_ed_linea (entrega_id,despacho_detalle_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logistica_incidencias (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    despacho_id BIGINT NOT NULL,
    despacho_detalle_id BIGINT NULL,
    tipo VARCHAR(30) NOT NULL,
    cantidad_afectada DECIMAL(14,3) NULL,
    descripcion TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    resuelta_at DATETIME NULL,
    resuelta_by BIGINT NULL,
    resolucion VARCHAR(40) NULL,
    observacion_resolucion TEXT NULL,
    referencia_externa VARCHAR(100) NULL,
    CONSTRAINT fk_lg_inc_desp FOREIGN KEY (despacho_id) REFERENCES logistica_despachos_cabecera(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_inc_linea FOREIGN KEY (despacho_detalle_id,despacho_id) REFERENCES logistica_despachos_detalle(id,despacho_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_inc_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_inc_resuelve FOREIGN KEY (resuelta_by) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_inc_tipo CHECK (BINARY tipo IN ('RECHAZO','FALTANTE','DANO','PERDIDA','RETRASO','DOCUMENTAL','ENTREGA_FALLIDA','OTRO')),
    CONSTRAINT ck_lg_inc_version CHECK (version >= 1),
    CONSTRAINT ck_lg_inc_cantidad CHECK (cantidad_afectada IS NULL OR (despacho_detalle_id IS NOT NULL AND cantidad_afectada > 0)),
    CONSTRAINT ck_lg_inc_descripcion CHECK (CHAR_LENGTH(TRIM(descripcion))>0),
    CONSTRAINT ck_lg_inc_resolucion CHECK ((resuelta_at IS NULL AND resuelta_by IS NULL AND resolucion IS NULL AND observacion_resolucion IS NULL) OR (resuelta_at IS NOT NULL AND resuelta_by IS NOT NULL AND resolucion IS NOT NULL AND CHAR_LENGTH(TRIM(resolucion))>0)),
    INDEX ix_lg_inc_pendientes (despacho_id,resuelta_at,id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logistica_historial (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    preparacion_id BIGINT NULL,
    despacho_id BIGINT NULL,
    entrega_id BIGINT NULL,
    version INT UNSIGNED NOT NULL,
    accion VARCHAR(40) NOT NULL,
    usuario_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo TEXT NULL,
    metadata_json JSON NULL,
    CONSTRAINT fk_lg_hist_prep FOREIGN KEY (preparacion_id) REFERENCES logistica_preparaciones_cabecera(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_hist_desp FOREIGN KEY (despacho_id) REFERENCES logistica_despachos_cabecera(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_hist_ent FOREIGN KEY (entrega_id) REFERENCES logistica_entregas_cabecera(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lg_hist_actor FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_lg_hist_entidad CHECK ((preparacion_id IS NOT NULL) + (despacho_id IS NOT NULL) + (entrega_id IS NOT NULL) = 1),
    CONSTRAINT ck_lg_hist_version CHECK (version >= 1),
    CONSTRAINT ck_lg_hist_accion CHECK (CHAR_LENGTH(TRIM(accion))>0),
    UNIQUE KEY uq_lg_hist_prep_version (preparacion_id,version),
    UNIQUE KEY uq_lg_hist_desp_version (despacho_id,version),
    UNIQUE KEY uq_lg_hist_ent_version (entrega_id,version)
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (6,'G7 Logistica - persistencia estructural MVP');
