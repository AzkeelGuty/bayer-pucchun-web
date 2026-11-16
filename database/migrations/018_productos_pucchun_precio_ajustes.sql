-- 018 - Precio comercial interno y ajustes identificados del stock Pucchún.
-- Requiere 017. No modifica precios ni inventarios Bayer.
ALTER TABLE productos ADD COLUMN precio_venta DECIMAL(14,2) NULL AFTER unidad_base_id;
ALTER TABLE stock_movimientos
  MODIFY COLUMN documento_modulo ENUM('documentos','guias','maestros') NOT NULL,
  MODIFY COLUMN tipo ENUM('SALIDA','REVERSION','AJUSTE') NOT NULL;

CREATE TABLE stock_ajustes_producto (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  producto_id BIGINT NOT NULL,
  unidad_id INT NOT NULL,
  almacen_id INT NOT NULL,
  snapshot_id BIGINT NOT NULL,
  cantidad_anterior DECIMAL(14,3) NOT NULL,
  cantidad_nueva DECIMAL(14,3) NOT NULL,
  created_by BIGINT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_stock_ajustes_producto (producto_id,almacen_id,created_at),
  FOREIGN KEY(producto_id) REFERENCES productos(id),
  FOREIGN KEY(unidad_id) REFERENCES unidades_medida(id),
  FOREIGN KEY(almacen_id) REFERENCES almacenes(id),
  FOREIGN KEY(snapshot_id) REFERENCES stock_cabecera(id),
  FOREIGN KEY(created_by) REFERENCES usuarios(id),
  CONSTRAINT ck_ajuste_no_negativo CHECK (cantidad_nueva>=0)
) ENGINE=InnoDB;
