-- 017 - Kardex de salidas y reversiones sobre el último inventario físico publicado.
-- Aplicar después de 016. No modifica ni elimina inventarios anteriores.
CREATE TABLE IF NOT EXISTS stock_movimientos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    snapshot_id BIGINT NOT NULL,
    almacen_id INT NOT NULL,
    producto_id BIGINT NOT NULL,
    unidad_id INT NOT NULL,
    documento_modulo ENUM('documentos','guias') NOT NULL,
    documento_id BIGINT NOT NULL,
    detalle_id BIGINT NOT NULL,
    tipo ENUM('SALIDA','REVERSION') NOT NULL,
    delta DECIMAL(14,3) NOT NULL,
    usuario_id BIGINT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_movimiento_origen (documento_modulo,documento_id,detalle_id,tipo),
    INDEX ix_movimientos_saldo (snapshot_id,producto_id,unidad_id,almacen_id),
    INDEX ix_movimientos_origen (documento_modulo,documento_id,tipo),
    CONSTRAINT fk_stock_mov_snapshot FOREIGN KEY(snapshot_id) REFERENCES stock_cabecera(id),
    CONSTRAINT fk_stock_mov_almacen FOREIGN KEY(almacen_id) REFERENCES almacenes(id),
    CONSTRAINT fk_stock_mov_producto FOREIGN KEY(producto_id) REFERENCES productos(id),
    CONSTRAINT fk_stock_mov_unidad FOREIGN KEY(unidad_id) REFERENCES unidades_medida(id),
    CONSTRAINT fk_stock_mov_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
    CONSTRAINT ck_stock_mov_delta CHECK (delta<>0)
) ENGINE=InnoDB;
