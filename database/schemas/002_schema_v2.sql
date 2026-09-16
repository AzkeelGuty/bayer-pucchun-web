-- schema v2 - Dia 1 Pedro
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=1;
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE,
    descripcion VARCHAR(150),
    estado TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(60) NOT NULL UNIQUE,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(150)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    estado TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usuario_rol (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT NOT NULL,
    rol_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_rol(usuario_id,rol_id),
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY(rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE rol_permiso (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    UNIQUE KEY uq_rol_permiso(rol_id,permiso_id),
    FOREIGN KEY(rol_id) REFERENCES roles(id),
    FOREIGN KEY(permiso_id) REFERENCES permisos(id)
) ENGINE=InnoDB;

CREATE TABLE empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ruc CHAR(11) NOT NULL UNIQUE,
    razon_social VARCHAR(150) NOT NULL,
    nombre_comercial VARCHAR(120),
    estado TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE provincias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    departamento_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_provincia(departamento_id,nombre),
    FOREIGN KEY(departamento_id) REFERENCES departamentos(id)
) ENGINE=InnoDB;

CREATE TABLE distritos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provincia_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_distrito(provincia_id,nombre),
    FOREIGN KEY(provincia_id) REFERENCES provincias(id)
) ENGINE=InnoDB;

CREATE TABLE sucursales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    direccion VARCHAR(180),
    distrito_id INT NULL,
    estado TINYINT NOT NULL DEFAULT 1,
    FOREIGN KEY(empresa_id) REFERENCES empresas(id),
    FOREIGN KEY(distrito_id) REFERENCES distritos(id)
) ENGINE=InnoDB;

CREATE TABLE almacenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sucursal_id INT NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    tipo VARCHAR(40),
    estado TINYINT NOT NULL DEFAULT 1,
    FOREIGN KEY(sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB;

CREATE TABLE clientes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20),
    tipo_doc CHAR(2),
    nro_doc VARCHAR(15) NOT NULL UNIQUE,
    razon_social VARCHAR(150) NOT NULL,
    departamento_id INT NULL,
    provincia_id INT NULL,
    distrito_id INT NULL,
    FOREIGN KEY(departamento_id) REFERENCES departamentos(id),
    FOREIGN KEY(provincia_id) REFERENCES provincias(id),
    FOREIGN KEY(distrito_id) REFERENCES distritos(id)
) ENGINE=InnoDB;

CREATE TABLE vendedores (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombres VARCHAR(120) NOT NULL,
    apellidos VARCHAR(120),
    email VARCHAR(120),
    estado TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE categorias_producto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    descripcion VARCHAR(120)
) ENGINE=InnoDB;

CREATE TABLE marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    fabricante VARCHAR(80)
) ENGINE=InnoDB;

CREATE TABLE unidades_medida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(10) NOT NULL UNIQUE,
    nombre VARCHAR(40) NOT NULL,
    abreviatura VARCHAR(10),
    factor_base DECIMAL(12,4) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE productos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    categoria_id INT NULL,
    marca_id INT NULL,
    unidad_base_id INT NULL,
    estado TINYINT NOT NULL DEFAULT 1,
    FOREIGN KEY(categoria_id) REFERENCES categorias_producto(id),
    FOREIGN KEY(marca_id) REFERENCES marcas(id),
    FOREIGN KEY(unidad_base_id) REFERENCES unidades_medida(id)
) ENGINE=InnoDB;

CREATE TABLE tipos_documento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(4) NOT NULL UNIQUE,
    nombre VARCHAR(60) NOT NULL,
    sunat_code VARCHAR(4)
) ENGINE=InnoDB;

CREATE TABLE partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(80) NOT NULL,
    tipo VARCHAR(30)
) ENGINE=InnoDB;

CREATE TABLE documentos_cabecera (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento_id INT NOT NULL,
    numero VARCHAR(25) NOT NULL,
    fecha DATE NOT NULL,
    cliente_id BIGINT NOT NULL,
    vendedor_id BIGINT NOT NULL,
    sucursal_id INT NOT NULL,
    estado_registro VARCHAR(20) NOT NULL DEFAULT 'BORRADOR',
    created_by BIGINT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    UNIQUE KEY uq_doc(tipo_documento_id,numero),
    FOREIGN KEY(tipo_documento_id) REFERENCES tipos_documento(id),
    FOREIGN KEY(cliente_id) REFERENCES clientes(id),
    FOREIGN KEY(vendedor_id) REFERENCES vendedores(id),
    FOREIGN KEY(sucursal_id) REFERENCES sucursales(id),
    FOREIGN KEY(created_by) REFERENCES usuarios(id),
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    validated_at DATETIME NULL,
    validated_by BIGINT NULL,
    observed_at DATETIME NULL,
    observed_by BIGINT NULL,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT NULL,
    published_by BIGINT NULL,
    observation_reason TEXT NULL,
    cancellation_reason TEXT NULL,
    CONSTRAINT fk_documentos_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    CONSTRAINT fk_documentos_validated_by FOREIGN KEY (validated_by) REFERENCES usuarios(id),
    CONSTRAINT fk_documentos_observed_by FOREIGN KEY (observed_by) REFERENCES usuarios(id),
    CONSTRAINT fk_documentos_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES usuarios(id),
    CONSTRAINT fk_documentos_published_by FOREIGN KEY (published_by) REFERENCES usuarios(id),
    CONSTRAINT ck_documentos_estado CHECK (BINARY estado_registro IN ('BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO')),
    CONSTRAINT ck_documentos_version CHECK (version >= 1),
    CONSTRAINT ck_documentos_fecha CHECK (fecha >= '1000-01-01' AND DAYOFMONTH(fecha) >= 1),
    INDEX ix_documentos_estado_fecha (estado_registro,fecha,id),
    INDEX ix_documentos_ubicacion_fecha (sucursal_id,fecha,id)
) ENGINE=InnoDB;

CREATE TABLE documentos_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    documento_id BIGINT NOT NULL,
    producto_id BIGINT NOT NULL,
    unidad_id INT NOT NULL,
    cantidad DECIMAL(14,3) NOT NULL,
    valor_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY(documento_id) REFERENCES documentos_cabecera(id) ON DELETE CASCADE,
    FOREIGN KEY(producto_id) REFERENCES productos(id),
    FOREIGN KEY(unidad_id) REFERENCES unidades_medida(id),
    CONSTRAINT ck_documentos_cantidad CHECK (cantidad > 0),
    CONSTRAINT ck_documentos_valor CHECK (valor_unitario >= 0)
) ENGINE=InnoDB;

CREATE TABLE guias_cabecera (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(25) NOT NULL UNIQUE,
    fecha DATE NOT NULL,
    cliente_id BIGINT NOT NULL,
    vendedor_id BIGINT NOT NULL,
    sucursal_id INT NOT NULL,
    departamento_id INT NULL,
    provincia_id INT NULL,
    distrito_id INT NULL,
    estado_registro VARCHAR(20) NOT NULL DEFAULT 'BORRADOR',
    created_by BIGINT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    FOREIGN KEY(cliente_id) REFERENCES clientes(id),
    FOREIGN KEY(vendedor_id) REFERENCES vendedores(id),
    FOREIGN KEY(sucursal_id) REFERENCES sucursales(id),
    FOREIGN KEY(departamento_id) REFERENCES departamentos(id),
    FOREIGN KEY(provincia_id) REFERENCES provincias(id),
    FOREIGN KEY(distrito_id) REFERENCES distritos(id),
    FOREIGN KEY(created_by) REFERENCES usuarios(id),
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    validated_at DATETIME NULL,
    validated_by BIGINT NULL,
    observed_at DATETIME NULL,
    observed_by BIGINT NULL,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT NULL,
    published_by BIGINT NULL,
    observation_reason TEXT NULL,
    cancellation_reason TEXT NULL,
    CONSTRAINT fk_guias_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    CONSTRAINT fk_guias_validated_by FOREIGN KEY (validated_by) REFERENCES usuarios(id),
    CONSTRAINT fk_guias_observed_by FOREIGN KEY (observed_by) REFERENCES usuarios(id),
    CONSTRAINT fk_guias_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES usuarios(id),
    CONSTRAINT fk_guias_published_by FOREIGN KEY (published_by) REFERENCES usuarios(id),
    CONSTRAINT ck_guias_estado CHECK (BINARY estado_registro IN ('BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO')),
    CONSTRAINT ck_guias_version CHECK (version >= 1),
    CONSTRAINT ck_guias_fecha CHECK (fecha >= '1000-01-01' AND DAYOFMONTH(fecha) >= 1),
    INDEX ix_guias_estado_fecha (estado_registro,fecha,id),
    INDEX ix_guias_ubicacion_fecha (sucursal_id,fecha,id)
) ENGINE=InnoDB;

CREATE TABLE guias_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    guia_id BIGINT NOT NULL,
    producto_id BIGINT NOT NULL,
    unidad_id INT NOT NULL,
    cantidad DECIMAL(14,3) NOT NULL,
    FOREIGN KEY(guia_id) REFERENCES guias_cabecera(id) ON DELETE CASCADE,
    FOREIGN KEY(producto_id) REFERENCES productos(id),
    FOREIGN KEY(unidad_id) REFERENCES unidades_medida(id),
    CONSTRAINT ck_guias_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;

CREATE TABLE stock_cabecera (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    fecha_stock DATE NOT NULL,
    almacen_id INT NOT NULL,
    estado_registro VARCHAR(20) NOT NULL DEFAULT 'BORRADOR',
    created_by BIGINT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    FOREIGN KEY(almacen_id) REFERENCES almacenes(id),
    FOREIGN KEY(created_by) REFERENCES usuarios(id),
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    validated_at DATETIME NULL,
    validated_by BIGINT NULL,
    observed_at DATETIME NULL,
    observed_by BIGINT NULL,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT NULL,
    published_by BIGINT NULL,
    observation_reason TEXT NULL,
    cancellation_reason TEXT NULL,
    CONSTRAINT fk_stock_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    CONSTRAINT fk_stock_validated_by FOREIGN KEY (validated_by) REFERENCES usuarios(id),
    CONSTRAINT fk_stock_observed_by FOREIGN KEY (observed_by) REFERENCES usuarios(id),
    CONSTRAINT fk_stock_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES usuarios(id),
    CONSTRAINT fk_stock_published_by FOREIGN KEY (published_by) REFERENCES usuarios(id),
    CONSTRAINT ck_stock_estado CHECK (BINARY estado_registro IN ('BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO')),
    CONSTRAINT ck_stock_version CHECK (version >= 1),
    CONSTRAINT ck_stock_fecha CHECK (fecha_stock >= '1000-01-01' AND DAYOFMONTH(fecha_stock) >= 1),
    INDEX ix_stock_estado_fecha (estado_registro,fecha_stock,id),
    INDEX ix_stock_ubicacion_fecha (almacen_id,fecha_stock,id),
    idempotency_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CONSTRAINT ck_stock_idempotency CHECK (CHAR_LENGTH(idempotency_key) BETWEEN 1 AND 64),
    CONSTRAINT ck_stock_request_hash CHECK (CHAR_LENGTH(request_hash)=64 AND request_hash REGEXP '^[0-9a-f]+$'),
    UNIQUE KEY uq_stock_idempotency (idempotency_key),
    INDEX ix_stock_fecha_almacen (fecha_stock,almacen_id),
    UNIQUE KEY uq_stock_contexto (id,fecha_stock,almacen_id)
) ENGINE=InnoDB;

CREATE TABLE lotes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    producto_id BIGINT NOT NULL,
    codigo_lote VARCHAR(40) NOT NULL,
    fecha_vencimiento DATE NULL,
    estado TINYINT NOT NULL DEFAULT 1,
    UNIQUE KEY uq_lote(producto_id,codigo_lote),
    FOREIGN KEY(producto_id) REFERENCES productos(id),
    UNIQUE KEY uq_lote_id_producto (id,producto_id),
    CONSTRAINT ck_lotes_fecha CHECK (fecha_vencimiento IS NULL OR (fecha_vencimiento >= '1000-01-01' AND DAYOFMONTH(fecha_vencimiento) >= 1))
) ENGINE=InnoDB;

CREATE TABLE stock_detalle (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    stock_id BIGINT NOT NULL,
    lote_id BIGINT NULL,
    producto_id BIGINT NOT NULL,
    unidad_id INT NOT NULL,
    cantidad DECIMAL(14,3) NOT NULL,
    FOREIGN KEY(stock_id) REFERENCES stock_cabecera(id) ON DELETE CASCADE,
    FOREIGN KEY(lote_id) REFERENCES lotes(id),
    FOREIGN KEY(producto_id) REFERENCES productos(id),
    FOREIGN KEY(unidad_id) REFERENCES unidades_medida(id),
    CONSTRAINT ck_stock_cantidad CHECK (cantidad >= 0),
    lote_clave BIGINT GENERATED ALWAYS AS (IFNULL(lote_id,0)) STORED,
    CONSTRAINT ck_stock_lote CHECK (lote_id IS NULL OR lote_id > 0),
    CONSTRAINT fk_stock_lote_producto FOREIGN KEY (lote_id,producto_id) REFERENCES lotes(id,producto_id),
    fecha_stock DATE NOT NULL,
    almacen_id INT NOT NULL,
    CONSTRAINT fk_stock_detalle_contexto FOREIGN KEY (stock_id,fecha_stock,almacen_id)
        REFERENCES stock_cabecera(id,fecha_stock,almacen_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_stock_existencia (fecha_stock,almacen_id,producto_id,lote_clave,unidad_id)
) ENGINE=InnoDB;

CREATE TABLE homologacion_productos_bayer (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    producto_id BIGINT NOT NULL,
    material_id VARCHAR(40) NOT NULL,
    material_name VARCHAR(150),
    unidad_bayer_id INT NULL,
    UNIQUE KEY uq_hpb(partner_id,producto_id),
    FOREIGN KEY(partner_id) REFERENCES partners(id),
    FOREIGN KEY(producto_id) REFERENCES productos(id),
    FOREIGN KEY(unidad_bayer_id) REFERENCES unidades_medida(id),
    estado TINYINT NOT NULL DEFAULT 1,
    valid_from DATE NULL,
    valid_until DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    CONSTRAINT ck_hom_productos_estado CHECK (estado IN (0,1)),
    CONSTRAINT ck_hom_productos_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
    CONSTRAINT fk_hom_productos_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
    CONSTRAINT fk_hom_productos_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    INDEX ix_hom_productos_externo (partner_id,material_id,estado)
) ENGINE=InnoDB;

CREATE TABLE homologacion_clientes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    cliente_id BIGINT NOT NULL,
    customer_id VARCHAR(40),
    customer_name VARCHAR(150),
    UNIQUE KEY uq_hc(partner_id,cliente_id),
    FOREIGN KEY(partner_id) REFERENCES partners(id),
    FOREIGN KEY(cliente_id) REFERENCES clientes(id),
    estado TINYINT NOT NULL DEFAULT 1,
    valid_from DATE NULL,
    valid_until DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    CONSTRAINT ck_hom_clientes_estado CHECK (estado IN (0,1)),
    CONSTRAINT ck_hom_clientes_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
    CONSTRAINT fk_hom_clientes_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
    CONSTRAINT fk_hom_clientes_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    INDEX ix_hom_clientes_externo (partner_id,customer_id,estado)
) ENGINE=InnoDB;

CREATE TABLE homologacion_unidades (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    unidad_id INT NOT NULL,
    external_unit_code VARCHAR(20),
    external_unit_name VARCHAR(60),
    UNIQUE KEY uq_hu(partner_id,unidad_id),
    FOREIGN KEY(partner_id) REFERENCES partners(id),
    FOREIGN KEY(unidad_id) REFERENCES unidades_medida(id),
    estado TINYINT NOT NULL DEFAULT 1,
    valid_from DATE NULL,
    valid_until DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    CONSTRAINT ck_hom_unidades_estado CHECK (estado IN (0,1)),
    CONSTRAINT ck_hom_unidades_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
    CONSTRAINT fk_hom_unidades_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
    CONSTRAINT fk_hom_unidades_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    INDEX ix_hom_unidades_externo (partner_id,external_unit_code,estado)
) ENGINE=InnoDB;

CREATE TABLE homologacion_sucursales (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    sucursal_id INT NOT NULL,
    branch_id VARCHAR(20),
    branch_name VARCHAR(120),
    UNIQUE KEY uq_hs(partner_id,sucursal_id),
    FOREIGN KEY(partner_id) REFERENCES partners(id),
    FOREIGN KEY(sucursal_id) REFERENCES sucursales(id),
    estado TINYINT NOT NULL DEFAULT 1,
    valid_from DATE NULL,
    valid_until DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    CONSTRAINT ck_hom_sucursales_estado CHECK (estado IN (0,1)),
    CONSTRAINT ck_hom_sucursales_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
    CONSTRAINT fk_hom_sucursales_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
    CONSTRAINT fk_hom_sucursales_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    INDEX ix_hom_sucursales_externo (partner_id,branch_id,estado)
) ENGINE=InnoDB;

CREATE TABLE homologacion_almacenes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    almacen_id INT NOT NULL,
    warehouse_id VARCHAR(20),
    warehouse_name VARCHAR(120),
    UNIQUE KEY uq_ha(partner_id,almacen_id),
    FOREIGN KEY(partner_id) REFERENCES partners(id),
    FOREIGN KEY(almacen_id) REFERENCES almacenes(id),
    estado TINYINT NOT NULL DEFAULT 1,
    valid_from DATE NULL,
    valid_until DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    CONSTRAINT ck_hom_almacenes_estado CHECK (estado IN (0,1)),
    CONSTRAINT ck_hom_almacenes_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
    CONSTRAINT fk_hom_almacenes_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
    CONSTRAINT fk_hom_almacenes_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    INDEX ix_hom_almacenes_externo (partner_id,warehouse_id,estado)
) ENGINE=InnoDB;

CREATE TABLE homologacion_vendedores (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    partner_id INT NOT NULL,
    vendedor_id BIGINT NOT NULL,
    sales_id VARCHAR(20),
    sales_name VARCHAR(120),
    UNIQUE KEY uq_hv(partner_id,vendedor_id),
    FOREIGN KEY(partner_id) REFERENCES partners(id),
    FOREIGN KEY(vendedor_id) REFERENCES vendedores(id),
    estado TINYINT NOT NULL DEFAULT 1,
    valid_from DATE NULL,
    valid_until DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NULL,
    updated_at DATETIME NULL,
    updated_by BIGINT NULL,
    CONSTRAINT ck_hom_vendedores_estado CHECK (estado IN (0,1)),
    CONSTRAINT ck_hom_vendedores_vigencia CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
    CONSTRAINT fk_hom_vendedores_created FOREIGN KEY (created_by) REFERENCES usuarios(id),
    CONSTRAINT fk_hom_vendedores_updated FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    INDEX ix_hom_vendedores_externo (partner_id,sales_id,estado)
) ENGINE=InnoDB;

CREATE TABLE validaciones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    modulo VARCHAR(30) NOT NULL,
    registro_id BIGINT NOT NULL,
    usuario_id BIGINT NOT NULL,
    resultado VARCHAR(20) NOT NULL,
    observacion TEXT,
    validated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
    version INT UNSIGNED NOT NULL DEFAULT 1,
    correlation_id VARCHAR(64) NULL,
    CONSTRAINT ck_validaciones_version CHECK (version >= 1),
    INDEX ix_validaciones_registro (modulo,registro_id,version,validated_at)
) ENGINE=InnoDB;

CREATE TABLE publicaciones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    modulo VARCHAR(30) NOT NULL,
    fecha_publicacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id BIGINT NOT NULL,
    estado VARCHAR(20) NOT NULL,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
    cancellation_reason TEXT NULL,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT NULL,
    correlation_id VARCHAR(64) NULL,
    CONSTRAINT fk_publicaciones_cancelled FOREIGN KEY (cancelled_by) REFERENCES usuarios(id),
    INDEX ix_publicaciones_modulo_fecha (modulo,fecha_publicacion)
) ENGINE=InnoDB;

CREATE TABLE detalle_publicacion (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    publicacion_id BIGINT NOT NULL,
    registro_id BIGINT NOT NULL,
    dataset VARCHAR(30) NOT NULL,
    FOREIGN KEY(publicacion_id) REFERENCES publicaciones(id) ON DELETE CASCADE,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT ck_publicacion_version CHECK (version >= 1),
    UNIQUE KEY uq_publicacion_version (dataset,registro_id,version)
) ENGINE=InnoDB;

CREATE TABLE exportaciones (
    nombre_archivo VARCHAR(255) NULL,
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tipo_dataset VARCHAR(30) NOT NULL,
    formato VARCHAR(10) NOT NULL,
    filtro_json JSON NULL,
    usuario_id BIGINT NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
    record_count INT UNSIGNED NOT NULL DEFAULT 0,
    resultado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    duration_ms BIGINT UNSIGNED NULL,
    correlation_id VARCHAR(64) NULL,
    INDEX ix_exportaciones_dataset_fecha (tipo_dataset,generated_at)
) ENGINE=InnoDB;

CREATE TABLE auditoria_acciones (
    user_agent VARCHAR(255) NULL,
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT NOT NULL,
    modulo VARCHAR(30) NOT NULL,
    accion VARCHAR(30) NOT NULL,
    entidad_id BIGINT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
    ip VARCHAR(45) NULL,
    metadata_json JSON NULL,
    resultado VARCHAR(20) NULL,
    correlation_id VARCHAR(64) NULL,
    INDEX ix_auditoria_entidad (modulo,entidad_id,fecha_hora),
    INDEX ix_auditoria_correlation (correlation_id)
) ENGINE=InnoDB;

CREATE TABLE bitacora_acceso (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT NULL,
    ip VARCHAR(45),
    user_agent VARCHAR(255),
    accion VARCHAR(30),
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

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
