-- Ejecutar una sola vez sobre v1, migración para las base de datos v1.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=1;
DELIMITER $$
DROP PROCEDURE IF EXISTS migrate_schema_v2$$
CREATE PROCEDURE migrate_schema_v2()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documentos_cabecera' AND COLUMN_NAME='version') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: ya aplicada o parcial; revisar schema_migrations/restaurar respaldo';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_cabecera WHERE BINARY estado_registro NOT IN ('BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO') OR fecha < '1000-01-01' OR DAYOFMONTH(fecha) = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: estado/fecha invalido en documentos_cabecera';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_cabecera WHERE BINARY estado_registro NOT IN ('BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO') OR fecha < '1000-01-01' OR DAYOFMONTH(fecha) = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: estado/fecha invalido en guias_cabecera';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_cabecera WHERE BINARY estado_registro NOT IN ('BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO') OR fecha_stock < '1000-01-01' OR DAYOFMONTH(fecha_stock) = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: estado/fecha invalido en stock_cabecera';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_detalle WHERE NOT (cantidad > 0)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: cantidad invalida en documentos_detalle';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_detalle WHERE NOT (cantidad > 0)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: cantidad invalida en guias_detalle';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_detalle WHERE NOT (cantidad >= 0)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: cantidad invalida en stock_detalle';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_detalle WHERE valor_unitario < 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: valor unitario negativo';
    END IF;
    IF EXISTS (SELECT 1 FROM lotes WHERE fecha_vencimiento < '1000-01-01' OR DAYOFMONTH(fecha_vencimiento) = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: fecha de lote invalida';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_detalle d JOIN stock_cabecera c ON c.id=d.stock_id GROUP BY c.fecha_stock,c.almacen_id,d.producto_id,IFNULL(d.lote_id,0),d.unidad_id HAVING COUNT(*) > 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: stock duplicado por fecha/almacen/producto/lote/unidad';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_detalle GROUP BY stock_id,producto_id,IFNULL(lote_id,0),unidad_id HAVING COUNT(*) > 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: lineas de stock duplicadas';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_detalle d JOIN lotes l ON l.id=d.lote_id JOIN stock_cabecera c ON c.id=d.stock_id WHERE l.producto_id <> d.producto_id OR d.lote_id <= 0 OR l.fecha_vencimiento < c.fecha_stock) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: lote/producto/vencimiento inconsistente';
    END IF;
    IF EXISTS (SELECT 1 FROM detalle_publicacion GROUP BY dataset,registro_id HAVING COUNT(*) > 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: publicaciones duplicadas; conciliar versiones primero';
    END IF;
    IF EXISTS (SELECT 1 FROM usuario_rol child LEFT JOIN usuarios parent ON child.usuario_id=parent.id WHERE child.usuario_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana usuario_rol.usuario_id';
    END IF;
    IF EXISTS (SELECT 1 FROM usuario_rol child LEFT JOIN roles parent ON child.rol_id=parent.id WHERE child.rol_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana usuario_rol.rol_id';
    END IF;
    IF EXISTS (SELECT 1 FROM rol_permiso child LEFT JOIN roles parent ON child.rol_id=parent.id WHERE child.rol_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana rol_permiso.rol_id';
    END IF;
    IF EXISTS (SELECT 1 FROM rol_permiso child LEFT JOIN permisos parent ON child.permiso_id=parent.id WHERE child.permiso_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana rol_permiso.permiso_id';
    END IF;
    IF EXISTS (SELECT 1 FROM provincias child LEFT JOIN departamentos parent ON child.departamento_id=parent.id WHERE child.departamento_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana provincias.departamento_id';
    END IF;
    IF EXISTS (SELECT 1 FROM distritos child LEFT JOIN provincias parent ON child.provincia_id=parent.id WHERE child.provincia_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana distritos.provincia_id';
    END IF;
    IF EXISTS (SELECT 1 FROM sucursales child LEFT JOIN empresas parent ON child.empresa_id=parent.id WHERE child.empresa_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana sucursales.empresa_id';
    END IF;
    IF EXISTS (SELECT 1 FROM sucursales child LEFT JOIN distritos parent ON child.distrito_id=parent.id WHERE child.distrito_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana sucursales.distrito_id';
    END IF;
    IF EXISTS (SELECT 1 FROM almacenes child LEFT JOIN sucursales parent ON child.sucursal_id=parent.id WHERE child.sucursal_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana almacenes.sucursal_id';
    END IF;
    IF EXISTS (SELECT 1 FROM clientes child LEFT JOIN departamentos parent ON child.departamento_id=parent.id WHERE child.departamento_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana clientes.departamento_id';
    END IF;
    IF EXISTS (SELECT 1 FROM clientes child LEFT JOIN provincias parent ON child.provincia_id=parent.id WHERE child.provincia_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana clientes.provincia_id';
    END IF;
    IF EXISTS (SELECT 1 FROM clientes child LEFT JOIN distritos parent ON child.distrito_id=parent.id WHERE child.distrito_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana clientes.distrito_id';
    END IF;
    IF EXISTS (SELECT 1 FROM productos child LEFT JOIN categorias_producto parent ON child.categoria_id=parent.id WHERE child.categoria_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana productos.categoria_id';
    END IF;
    IF EXISTS (SELECT 1 FROM productos child LEFT JOIN marcas parent ON child.marca_id=parent.id WHERE child.marca_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana productos.marca_id';
    END IF;
    IF EXISTS (SELECT 1 FROM productos child LEFT JOIN unidades_medida parent ON child.unidad_base_id=parent.id WHERE child.unidad_base_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana productos.unidad_base_id';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_cabecera child LEFT JOIN tipos_documento parent ON child.tipo_documento_id=parent.id WHERE child.tipo_documento_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana documentos_cabecera.tipo_documento_id';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_cabecera child LEFT JOIN clientes parent ON child.cliente_id=parent.id WHERE child.cliente_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana documentos_cabecera.cliente_id';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_cabecera child LEFT JOIN vendedores parent ON child.vendedor_id=parent.id WHERE child.vendedor_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana documentos_cabecera.vendedor_id';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_cabecera child LEFT JOIN sucursales parent ON child.sucursal_id=parent.id WHERE child.sucursal_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana documentos_cabecera.sucursal_id';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_cabecera child LEFT JOIN usuarios parent ON child.created_by=parent.id WHERE child.created_by IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana documentos_cabecera.created_by';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_detalle child LEFT JOIN documentos_cabecera parent ON child.documento_id=parent.id WHERE child.documento_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana documentos_detalle.documento_id';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_detalle child LEFT JOIN productos parent ON child.producto_id=parent.id WHERE child.producto_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana documentos_detalle.producto_id';
    END IF;
    IF EXISTS (SELECT 1 FROM documentos_detalle child LEFT JOIN unidades_medida parent ON child.unidad_id=parent.id WHERE child.unidad_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana documentos_detalle.unidad_id';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_cabecera child LEFT JOIN clientes parent ON child.cliente_id=parent.id WHERE child.cliente_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_cabecera.cliente_id';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_cabecera child LEFT JOIN vendedores parent ON child.vendedor_id=parent.id WHERE child.vendedor_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_cabecera.vendedor_id';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_cabecera child LEFT JOIN sucursales parent ON child.sucursal_id=parent.id WHERE child.sucursal_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_cabecera.sucursal_id';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_cabecera child LEFT JOIN departamentos parent ON child.departamento_id=parent.id WHERE child.departamento_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_cabecera.departamento_id';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_cabecera child LEFT JOIN provincias parent ON child.provincia_id=parent.id WHERE child.provincia_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_cabecera.provincia_id';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_cabecera child LEFT JOIN distritos parent ON child.distrito_id=parent.id WHERE child.distrito_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_cabecera.distrito_id';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_cabecera child LEFT JOIN usuarios parent ON child.created_by=parent.id WHERE child.created_by IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_cabecera.created_by';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_detalle child LEFT JOIN guias_cabecera parent ON child.guia_id=parent.id WHERE child.guia_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_detalle.guia_id';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_detalle child LEFT JOIN productos parent ON child.producto_id=parent.id WHERE child.producto_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_detalle.producto_id';
    END IF;
    IF EXISTS (SELECT 1 FROM guias_detalle child LEFT JOIN unidades_medida parent ON child.unidad_id=parent.id WHERE child.unidad_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana guias_detalle.unidad_id';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_cabecera child LEFT JOIN almacenes parent ON child.almacen_id=parent.id WHERE child.almacen_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana stock_cabecera.almacen_id';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_cabecera child LEFT JOIN usuarios parent ON child.created_by=parent.id WHERE child.created_by IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana stock_cabecera.created_by';
    END IF;
    IF EXISTS (SELECT 1 FROM lotes child LEFT JOIN productos parent ON child.producto_id=parent.id WHERE child.producto_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana lotes.producto_id';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_detalle child LEFT JOIN stock_cabecera parent ON child.stock_id=parent.id WHERE child.stock_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana stock_detalle.stock_id';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_detalle child LEFT JOIN lotes parent ON child.lote_id=parent.id WHERE child.lote_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana stock_detalle.lote_id';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_detalle child LEFT JOIN productos parent ON child.producto_id=parent.id WHERE child.producto_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana stock_detalle.producto_id';
    END IF;
    IF EXISTS (SELECT 1 FROM stock_detalle child LEFT JOIN unidades_medida parent ON child.unidad_id=parent.id WHERE child.unidad_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana stock_detalle.unidad_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_productos_bayer child LEFT JOIN partners parent ON child.partner_id=parent.id WHERE child.partner_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_productos_bayer.partner_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_productos_bayer child LEFT JOIN productos parent ON child.producto_id=parent.id WHERE child.producto_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_productos_bayer.producto_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_productos_bayer child LEFT JOIN unidades_medida parent ON child.unidad_bayer_id=parent.id WHERE child.unidad_bayer_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_productos_bayer.unidad_bayer_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_clientes child LEFT JOIN partners parent ON child.partner_id=parent.id WHERE child.partner_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_clientes.partner_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_clientes child LEFT JOIN clientes parent ON child.cliente_id=parent.id WHERE child.cliente_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_clientes.cliente_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_unidades child LEFT JOIN partners parent ON child.partner_id=parent.id WHERE child.partner_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_unidades.partner_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_unidades child LEFT JOIN unidades_medida parent ON child.unidad_id=parent.id WHERE child.unidad_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_unidades.unidad_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_sucursales child LEFT JOIN partners parent ON child.partner_id=parent.id WHERE child.partner_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_sucursales.partner_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_sucursales child LEFT JOIN sucursales parent ON child.sucursal_id=parent.id WHERE child.sucursal_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_sucursales.sucursal_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_almacenes child LEFT JOIN partners parent ON child.partner_id=parent.id WHERE child.partner_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_almacenes.partner_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_almacenes child LEFT JOIN almacenes parent ON child.almacen_id=parent.id WHERE child.almacen_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_almacenes.almacen_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_vendedores child LEFT JOIN partners parent ON child.partner_id=parent.id WHERE child.partner_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_vendedores.partner_id';
    END IF;
    IF EXISTS (SELECT 1 FROM homologacion_vendedores child LEFT JOIN vendedores parent ON child.vendedor_id=parent.id WHERE child.vendedor_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana homologacion_vendedores.vendedor_id';
    END IF;
    IF EXISTS (SELECT 1 FROM validaciones child LEFT JOIN usuarios parent ON child.usuario_id=parent.id WHERE child.usuario_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana validaciones.usuario_id';
    END IF;
    IF EXISTS (SELECT 1 FROM publicaciones child LEFT JOIN usuarios parent ON child.usuario_id=parent.id WHERE child.usuario_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana publicaciones.usuario_id';
    END IF;
    IF EXISTS (SELECT 1 FROM detalle_publicacion child LEFT JOIN publicaciones parent ON child.publicacion_id=parent.id WHERE child.publicacion_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana detalle_publicacion.publicacion_id';
    END IF;
    IF EXISTS (SELECT 1 FROM exportaciones child LEFT JOIN usuarios parent ON child.usuario_id=parent.id WHERE child.usuario_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana exportaciones.usuario_id';
    END IF;
    IF EXISTS (SELECT 1 FROM auditoria_acciones child LEFT JOIN usuarios parent ON child.usuario_id=parent.id WHERE child.usuario_id IS NOT NULL AND parent.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: referencia huerfana auditoria_acciones.usuario_id';
    END IF;
    IF EXISTS (SELECT 1 FROM bitacora_acceso b LEFT JOIN usuarios u ON u.id=b.usuario_id WHERE b.usuario_id IS NOT NULL AND u.id IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'V2: usuario inexistente en bitacora_acceso';
    END IF;

    ALTER TABLE lotes
        ADD UNIQUE KEY uq_lote_id_producto (id,producto_id),
        ADD CONSTRAINT ck_lotes_fecha CHECK (fecha_vencimiento IS NULL OR (fecha_vencimiento >= '1000-01-01' AND DAYOFMONTH(fecha_vencimiento) >= 1));

    ALTER TABLE documentos_cabecera
        ADD COLUMN updated_at DATETIME NULL,
        ADD COLUMN updated_by BIGINT NULL,
        ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1,
        ADD COLUMN validated_at DATETIME NULL,
        ADD COLUMN validated_by BIGINT NULL,
        ADD COLUMN observed_at DATETIME NULL,
        ADD COLUMN observed_by BIGINT NULL,
        ADD COLUMN cancelled_at DATETIME NULL,
        ADD COLUMN cancelled_by BIGINT NULL,
        ADD COLUMN published_by BIGINT NULL,
        ADD COLUMN observation_reason TEXT NULL,
        ADD COLUMN cancellation_reason TEXT NULL,
        ADD CONSTRAINT fk_documentos_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_documentos_validated_by FOREIGN KEY (validated_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_documentos_observed_by FOREIGN KEY (observed_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_documentos_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_documentos_published_by FOREIGN KEY (published_by) REFERENCES usuarios(id),
        ADD CONSTRAINT ck_documentos_estado CHECK (BINARY estado_registro IN ('BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO')),
        ADD CONSTRAINT ck_documentos_version CHECK (version >= 1),
        ADD CONSTRAINT ck_documentos_fecha CHECK (fecha >= '1000-01-01' AND DAYOFMONTH(fecha) >= 1),
        ADD INDEX ix_documentos_estado_fecha (estado_registro,fecha,id),
        ADD INDEX ix_documentos_ubicacion_fecha (sucursal_id,fecha,id);

    ALTER TABLE guias_cabecera
        ADD COLUMN updated_at DATETIME NULL,
        ADD COLUMN updated_by BIGINT NULL,
        ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1,
        ADD COLUMN validated_at DATETIME NULL,
        ADD COLUMN validated_by BIGINT NULL,
        ADD COLUMN observed_at DATETIME NULL,
        ADD COLUMN observed_by BIGINT NULL,
        ADD COLUMN cancelled_at DATETIME NULL,
        ADD COLUMN cancelled_by BIGINT NULL,
        ADD COLUMN published_by BIGINT NULL,
        ADD COLUMN observation_reason TEXT NULL,
        ADD COLUMN cancellation_reason TEXT NULL,
        ADD CONSTRAINT fk_guias_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_guias_validated_by FOREIGN KEY (validated_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_guias_observed_by FOREIGN KEY (observed_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_guias_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_guias_published_by FOREIGN KEY (published_by) REFERENCES usuarios(id),
        ADD CONSTRAINT ck_guias_estado CHECK (BINARY estado_registro IN ('BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO')),
        ADD CONSTRAINT ck_guias_version CHECK (version >= 1),
        ADD CONSTRAINT ck_guias_fecha CHECK (fecha >= '1000-01-01' AND DAYOFMONTH(fecha) >= 1),
        ADD INDEX ix_guias_estado_fecha (estado_registro,fecha,id),
        ADD INDEX ix_guias_ubicacion_fecha (sucursal_id,fecha,id);

    ALTER TABLE stock_cabecera
        ADD COLUMN updated_at DATETIME NULL,
        ADD COLUMN updated_by BIGINT NULL,
        ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1,
        ADD COLUMN validated_at DATETIME NULL,
        ADD COLUMN validated_by BIGINT NULL,
        ADD COLUMN observed_at DATETIME NULL,
        ADD COLUMN observed_by BIGINT NULL,
        ADD COLUMN cancelled_at DATETIME NULL,
        ADD COLUMN cancelled_by BIGINT NULL,
        ADD COLUMN published_by BIGINT NULL,
        ADD COLUMN observation_reason TEXT NULL,
        ADD COLUMN cancellation_reason TEXT NULL,
        ADD CONSTRAINT fk_stock_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_stock_validated_by FOREIGN KEY (validated_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_stock_observed_by FOREIGN KEY (observed_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_stock_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_stock_published_by FOREIGN KEY (published_by) REFERENCES usuarios(id),
        ADD CONSTRAINT ck_stock_estado CHECK (BINARY estado_registro IN ('BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO')),
        ADD CONSTRAINT ck_stock_version CHECK (version >= 1),
        ADD CONSTRAINT ck_stock_fecha CHECK (fecha_stock >= '1000-01-01' AND DAYOFMONTH(fecha_stock) >= 1),
        ADD INDEX ix_stock_estado_fecha (estado_registro,fecha_stock,id),
        ADD INDEX ix_stock_ubicacion_fecha (almacen_id,fecha_stock,id),
        ADD COLUMN idempotency_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
        ADD COLUMN request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
        ADD CONSTRAINT ck_stock_idempotency CHECK (CHAR_LENGTH(idempotency_key) BETWEEN 1 AND 64),
        ADD CONSTRAINT ck_stock_request_hash CHECK (CHAR_LENGTH(request_hash)=64 AND request_hash REGEXP '^[0-9a-f]+$'),
        ADD UNIQUE KEY uq_stock_idempotency (idempotency_key),
        ADD INDEX ix_stock_fecha_almacen (fecha_stock,almacen_id),
        ADD UNIQUE KEY uq_stock_contexto (id,fecha_stock,almacen_id);

    ALTER TABLE documentos_detalle
        ADD CONSTRAINT ck_documentos_cantidad CHECK (cantidad > 0),
        ADD CONSTRAINT ck_documentos_valor CHECK (valor_unitario >= 0);

    ALTER TABLE guias_detalle
        ADD CONSTRAINT ck_guias_cantidad CHECK (cantidad > 0);

    ALTER TABLE stock_detalle
        ADD CONSTRAINT ck_stock_cantidad CHECK (cantidad >= 0),
        ADD COLUMN lote_clave BIGINT GENERATED ALWAYS AS (IFNULL(lote_id,0)) STORED,
        ADD CONSTRAINT ck_stock_lote CHECK (lote_id IS NULL OR lote_id > 0),
        ADD CONSTRAINT fk_stock_lote_producto FOREIGN KEY (lote_id,producto_id) REFERENCES lotes(id,producto_id),
        ADD COLUMN fecha_stock DATE NULL,
        ADD COLUMN almacen_id INT NULL;

    UPDATE stock_detalle d JOIN stock_cabecera c ON c.id=d.stock_id
        SET d.fecha_stock=c.fecha_stock,d.almacen_id=c.almacen_id;
    ALTER TABLE stock_detalle
        MODIFY fecha_stock DATE NOT NULL,
        MODIFY almacen_id INT NOT NULL,
        ADD CONSTRAINT fk_stock_detalle_contexto FOREIGN KEY (stock_id,fecha_stock,almacen_id)
            REFERENCES stock_cabecera(id,fecha_stock,almacen_id) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD UNIQUE KEY uq_stock_existencia (fecha_stock,almacen_id,producto_id,lote_clave,unidad_id);

    ALTER TABLE homologacion_productos_bayer
        ADD COLUMN estado TINYINT NOT NULL DEFAULT 1,
        ADD COLUMN valid_from DATE NULL,
        ADD COLUMN valid_until DATE NULL,
        ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN created_by BIGINT NULL,
        ADD COLUMN updated_at DATETIME NULL,
        ADD COLUMN updated_by BIGINT NULL,
        ADD CONSTRAINT ck_hom_productos_estado CHECK (estado IN (0,1)),
        ADD CONSTRAINT ck_hom_productos_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
        ADD CONSTRAINT fk_hom_productos_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_hom_productos_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
        ADD INDEX ix_hom_productos_externo (partner_id,material_id,estado);

    ALTER TABLE homologacion_clientes
        ADD COLUMN estado TINYINT NOT NULL DEFAULT 1,
        ADD COLUMN valid_from DATE NULL,
        ADD COLUMN valid_until DATE NULL,
        ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN created_by BIGINT NULL,
        ADD COLUMN updated_at DATETIME NULL,
        ADD COLUMN updated_by BIGINT NULL,
        ADD CONSTRAINT ck_hom_clientes_estado CHECK (estado IN (0,1)),
        ADD CONSTRAINT ck_hom_clientes_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
        ADD CONSTRAINT fk_hom_clientes_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_hom_clientes_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
        ADD INDEX ix_hom_clientes_externo (partner_id,customer_id,estado);

    ALTER TABLE homologacion_unidades
        ADD COLUMN estado TINYINT NOT NULL DEFAULT 1,
        ADD COLUMN valid_from DATE NULL,
        ADD COLUMN valid_until DATE NULL,
        ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN created_by BIGINT NULL,
        ADD COLUMN updated_at DATETIME NULL,
        ADD COLUMN updated_by BIGINT NULL,
        ADD CONSTRAINT ck_hom_unidades_estado CHECK (estado IN (0,1)),
        ADD CONSTRAINT ck_hom_unidades_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
        ADD CONSTRAINT fk_hom_unidades_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_hom_unidades_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
        ADD INDEX ix_hom_unidades_externo (partner_id,external_unit_code,estado);

    ALTER TABLE homologacion_sucursales
        ADD COLUMN estado TINYINT NOT NULL DEFAULT 1,
        ADD COLUMN valid_from DATE NULL,
        ADD COLUMN valid_until DATE NULL,
        ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN created_by BIGINT NULL,
        ADD COLUMN updated_at DATETIME NULL,
        ADD COLUMN updated_by BIGINT NULL,
        ADD CONSTRAINT ck_hom_sucursales_estado CHECK (estado IN (0,1)),
        ADD CONSTRAINT ck_hom_sucursales_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
        ADD CONSTRAINT fk_hom_sucursales_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_hom_sucursales_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
        ADD INDEX ix_hom_sucursales_externo (partner_id,branch_id,estado);

    ALTER TABLE homologacion_almacenes
        ADD COLUMN estado TINYINT NOT NULL DEFAULT 1,
        ADD COLUMN valid_from DATE NULL,
        ADD COLUMN valid_until DATE NULL,
        ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN created_by BIGINT NULL,
        ADD COLUMN updated_at DATETIME NULL,
        ADD COLUMN updated_by BIGINT NULL,
        ADD CONSTRAINT ck_hom_almacenes_estado CHECK (estado IN (0,1)),
        ADD CONSTRAINT ck_hom_almacenes_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
        ADD CONSTRAINT fk_hom_almacenes_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_hom_almacenes_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
        ADD INDEX ix_hom_almacenes_externo (partner_id,warehouse_id,estado);

    ALTER TABLE homologacion_vendedores
        ADD COLUMN estado TINYINT NOT NULL DEFAULT 1,
        ADD COLUMN valid_from DATE NULL,
        ADD COLUMN valid_until DATE NULL,
        ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN created_by BIGINT NULL,
        ADD COLUMN updated_at DATETIME NULL,
        ADD COLUMN updated_by BIGINT NULL,
        ADD CONSTRAINT ck_hom_vendedores_estado CHECK (estado IN (0,1)),
        ADD CONSTRAINT ck_hom_vendedores_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
        ADD CONSTRAINT fk_hom_vendedores_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
        ADD CONSTRAINT fk_hom_vendedores_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
        ADD INDEX ix_hom_vendedores_externo (partner_id,sales_id,estado);

    ALTER TABLE validaciones
        ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1,
        ADD COLUMN correlation_id VARCHAR(64) NULL,
        ADD CONSTRAINT ck_validaciones_version CHECK (version >= 1),
        ADD INDEX ix_validaciones_registro (modulo,registro_id,version,validated_at);

    ALTER TABLE publicaciones
        ADD COLUMN cancellation_reason TEXT NULL,
        ADD COLUMN cancelled_at DATETIME NULL,
        ADD COLUMN cancelled_by BIGINT NULL,
        ADD COLUMN correlation_id VARCHAR(64) NULL,
        ADD CONSTRAINT fk_publicaciones_cancelled FOREIGN KEY (cancelled_by) REFERENCES usuarios(id),
        ADD INDEX ix_publicaciones_modulo_fecha (modulo,fecha_publicacion);

    ALTER TABLE detalle_publicacion
        ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1,
        ADD CONSTRAINT ck_publicacion_version CHECK (version >= 1),
        ADD UNIQUE KEY uq_publicacion_version (dataset,registro_id,version);

    ALTER TABLE exportaciones
        ADD COLUMN nombre_archivo VARCHAR(255) NULL,
        ADD COLUMN record_count INT UNSIGNED NOT NULL DEFAULT 0,
        ADD COLUMN resultado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
        ADD COLUMN duration_ms BIGINT UNSIGNED NULL,
        ADD COLUMN correlation_id VARCHAR(64) NULL,
        ADD INDEX ix_exportaciones_dataset_fecha (tipo_dataset,generated_at);

    ALTER TABLE auditoria_acciones
        ADD COLUMN user_agent VARCHAR(255) NULL,
        ADD COLUMN ip VARCHAR(45) NULL,
        ADD COLUMN metadata_json JSON NULL,
        ADD COLUMN resultado VARCHAR(20) NULL,
        ADD COLUMN correlation_id VARCHAR(64) NULL,
        ADD INDEX ix_auditoria_entidad (modulo,entidad_id,fecha_hora),
        ADD INDEX ix_auditoria_correlation (correlation_id);

    ALTER TABLE bitacora_acceso
        ADD CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id);

    -- Claves reservadas para registros historicos, sin inventar actores/fechas.
    UPDATE stock_cabecera SET idempotency_key=CONCAT('legacy-stock-',id), request_hash=SHA2(CONCAT('legacy-stock-',id),256);
    ALTER TABLE stock_cabecera MODIFY idempotency_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        MODIFY request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
    CREATE TABLE validaciones_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    validacion_id BIGINT NOT NULL,
    codigo VARCHAR(7) NOT NULL,
    campo VARCHAR(100) NOT NULL,
    severidad VARCHAR(20) NOT NULL,
    mensaje TEXT NOT NULL,
    resultado VARCHAR(20) NOT NULL,
    CONSTRAINT fk_validaciones_detalle FOREIGN KEY (validacion_id) REFERENCES validaciones(id) ON DELETE CASCADE,
    CONSTRAINT ck_validacion_codigo CHECK (BINARY codigo IN ('VAL-001','VAL-002','VAL-003','VAL-004','VAL-005','VAL-006','VAL-007')),
    CONSTRAINT ck_validacion_severidad CHECK (BINARY severidad IN ('BLOQUEANTE','ADVERTENCIA')),
    CONSTRAINT ck_validacion_resultado CHECK (BINARY resultado IN ('OK','ERROR')),
    INDEX ix_validacion_regla (validacion_id,codigo)
) ENGINE=InnoDB;
    CREATE TABLE schema_migrations (
    version INT PRIMARY KEY,
    description VARCHAR(150) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
    INSERT INTO schema_migrations(version,description) VALUES (2,'Schema v2 - Dia 1 Pedro');
END$$
CALL migrate_schema_v2()$$
DROP PROCEDURE migrate_schema_v2$$
DELIMITER ;
