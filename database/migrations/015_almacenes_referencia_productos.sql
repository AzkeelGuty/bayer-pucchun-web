-- 015: almacén de referencia por producto en ambos catálogos.
-- No cambia existencias físicas, históricos, ni códigos Bayer/Pucchún.
-- Reejecutable: CREATE TABLE IF NOT EXISTS.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bayer_producto_almacen (
  producto_id BIGINT NOT NULL PRIMARY KEY,
  almacen_id INT NOT NULL,
  CONSTRAINT fk_bayer_ref_producto FOREIGN KEY (producto_id)
    REFERENCES bayer_productos_catalogo(id) ON DELETE CASCADE,
  CONSTRAINT fk_bayer_ref_almacen FOREIGN KEY (almacen_id)
    REFERENCES almacenes(id),
  KEY ix_bayer_ref_almacen (almacen_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pucchun_producto_almacen (
  producto_id BIGINT NOT NULL PRIMARY KEY,
  almacen_id INT NOT NULL,
  CONSTRAINT fk_pucchun_ref_producto FOREIGN KEY (producto_id)
    REFERENCES productos(id) ON DELETE CASCADE,
  CONSTRAINT fk_pucchun_ref_almacen FOREIGN KEY (almacen_id)
    REFERENCES almacenes(id),
  KEY ix_pucchun_ref_almacen (almacen_id)
) ENGINE=InnoDB;
