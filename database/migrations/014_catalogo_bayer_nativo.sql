-- 014: catálogo Bayer propio, inventarios por período y lista de precios independiente.
-- No altera, renombra ni borra productos, stock ni homologaciones existentes de Pucchún.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bayer_productos_catalogo (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    codigo_bayer VARCHAR(40) NOT NULL,
    nombre_bayer VARCHAR(200) NOT NULL,
    marca VARCHAR(100) NULL,
    stock_reportado BIGINT NULL,
    stock_periodo CHAR(7) NULL,
    stock_estado ENUM('SIN_DATO','SIN_STOCK','CON_STOCK','DESACTIVADO') NOT NULL DEFAULT 'SIN_DATO',
    estado_catalogo ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    publicado TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bayer_codigo (codigo_bayer),
    KEY ix_bayer_nombre (nombre_bayer),
    KEY ix_bayer_estado (stock_estado,publicado),
    CONSTRAINT ck_bayer_stock_nonnegative CHECK (stock_reportado IS NULL OR stock_reportado >= 0),
    CONSTRAINT ck_bayer_publicado CHECK (publicado IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bayer_inventario_periodos (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    producto_id BIGINT NOT NULL,
    periodo CHAR(7) NOT NULL,
    cantidad BIGINT NULL,
    marcador ENUM('CANTIDAD','VACIO','GUION') NOT NULL,
    fuente VARCHAR(180) NULL,
    registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bayer_producto_periodo (producto_id,periodo),
    KEY ix_bayer_periodo (periodo),
    CONSTRAINT fk_bayer_inventario_producto FOREIGN KEY (producto_id)
        REFERENCES bayer_productos_catalogo(id) ON DELETE CASCADE,
    CONSTRAINT ck_bayer_inventario_cantidad CHECK (
        (marcador='CANTIDAD' AND cantidad IS NOT NULL AND cantidad>=0) OR
        (marcador IN ('VACIO','GUION') AND cantidad IS NULL)
    )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bayer_precios_referencia (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    producto_id BIGINT NOT NULL,
    vigencia_desde DATE NOT NULL,
    vigencia_hasta DATE NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'PEN',
    sugerido_sin_igv DECIMAL(14,2) NOT NULL,
    igv_porcentaje DECIMAL(5,2) NOT NULL,
    sugerido_con_igv DECIMAL(14,2) NOT NULL,
    descuento_dealer DECIMAL(5,2) NULL,
    fuente VARCHAR(180) NULL,
    registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bayer_precio_periodo (producto_id,vigencia_desde),
    CONSTRAINT fk_bayer_precio_producto FOREIGN KEY (producto_id)
        REFERENCES bayer_productos_catalogo(id) ON DELETE CASCADE,
    CONSTRAINT ck_bayer_precio_nonnegative CHECK (sugerido_sin_igv>=0 AND sugerido_con_igv>=0),
    CONSTRAINT ck_bayer_precio_rates CHECK (igv_porcentaje>=0 AND igv_porcentaje<=100)
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (14,'Catálogo Bayer independiente con stock mensual y precios de referencia');
