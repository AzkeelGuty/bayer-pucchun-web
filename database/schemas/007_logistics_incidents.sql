-- Migracion 007: evolucion de 006 para intentos, incidencias y resoluciones.
-- Requiere 006. Solo modifica tablas propias de Logistica; no altera stock/Guias.
-- Ejecutar con Logistica detenida y respaldo revisado. DDL no es transaccional.
-- Los cierres excepcionales antiguos requieren conciliacion manual: no se
-- inventan incidencias ni confirmaciones a partir de una referencia textual.
-- Las cantidades entre filas se protegen en el Repository bajo bloqueo del
-- despacho; CHECK/FK protegen formato, identidad y referencias, no sus sumas.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=1;

DELIMITER $$
DROP PROCEDURE IF EXISTS logistica_007_preflight$$
CREATE PROCEDURE logistica_007_preflight()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version=6)
       OR EXISTS (SELECT 1 FROM schema_migrations WHERE version=7) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='007 requiere 006 aplicada y 007 pendiente';
    END IF;
    IF EXISTS (SELECT 1 FROM logistica_despachos WHERE estado='CERRADO_CON_INCIDENCIA') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conciliar cierres excepcionales de 006 antes de 007';
    END IF;
    IF EXISTS (SELECT 1 FROM logistica_entregas e
               WHERE NOT EXISTS (SELECT 1 FROM logistica_entrega_detalle d WHERE d.entrega_id=e.id)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conciliar entregas antiguas sin lineas antes de 007';
    END IF;
    IF EXISTS (SELECT 1 FROM logistica_despacho_detalle d
               WHERE COALESCE((SELECT SUM(ed.cantidad) FROM logistica_entrega_detalle ed
                              JOIN logistica_entregas e ON e.id=ed.entrega_id
                              WHERE ed.despacho_detalle_id=d.id AND e.anulada=0),0)>d.cantidad) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conciliar cantidades sobreentregadas antes de 007';
    END IF;
    IF EXISTS (SELECT 1 FROM logistica_despachos h
               JOIN logistica_despacho_detalle d ON d.despacho_id=h.id
               WHERE h.estado='ENTREGADO' AND d.cantidad<>
                   COALESCE((SELECT SUM(ed.cantidad) FROM logistica_entrega_detalle ed
                             JOIN logistica_entregas e ON e.id=ed.entrega_id
                             WHERE ed.despacho_detalle_id=d.id AND e.anulada=0),0)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conciliar despachos ENTREGADO incompletos antes de 007';
    END IF;
END$$
CALL logistica_007_preflight()$$
DROP PROCEDURE logistica_007_preflight$$

-- MariaDB usa DROP CONSTRAINT y MySQL DROP CHECK.
DROP PROCEDURE IF EXISTS logistica_007_drop_check$$
CREATE PROCEDURE logistica_007_drop_check(IN table_name_arg VARCHAR(64),IN check_name_arg VARCHAR(64))
BEGIN
    SET @logistica_007_ddl=CONCAT('ALTER TABLE `',table_name_arg,'` DROP ',
        IF(LOCATE('MariaDB',VERSION())>0,'CONSTRAINT','CHECK'),' `',check_name_arg,'`');
    PREPARE logistica_007_stmt FROM @logistica_007_ddl;
    EXECUTE logistica_007_stmt;
    DEALLOCATE PREPARE logistica_007_stmt;
END$$
CALL logistica_007_drop_check('logistica_despachos','ck_log_despacho_estado')$$
CALL logistica_007_drop_check('logistica_entregas','ck_log_entrega_recibido')$$
CALL logistica_007_drop_check('logistica_entrega_detalle','ck_log_entrega_detalle_cantidad')$$
DROP PROCEDURE logistica_007_drop_check$$
DELIMITER ;

ALTER TABLE logistica_despachos
    ADD COLUMN cerrado_at DATETIME(6) NULL,
    ADD COLUMN cerrado_by BIGINT NULL,
    ADD CONSTRAINT fk_log_despacho_cerrado_by FOREIGN KEY (cerrado_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT ck_log_despacho_estado CHECK (BINARY estado IN
        ('PENDIENTE','DESPACHADO','EN_TRANSITO','EN_RESOLUCION','ENTREGADO','CANCELADO','CERRADO_CON_INCIDENCIA'));

UPDATE logistica_despachos SET cerrado_at=updated_at,cerrado_by=updated_by WHERE estado='ENTREGADO';

ALTER TABLE logistica_despachos
    ADD CONSTRAINT ck_log_despacho_cierre CHECK
        ((BINARY estado IN ('ENTREGADO','CERRADO_CON_INCIDENCIA') AND cerrado_at IS NOT NULL AND cerrado_by IS NOT NULL)
        OR (BINARY estado NOT IN ('ENTREGADO','CERRADO_CON_INCIDENCIA') AND cerrado_at IS NULL AND cerrado_by IS NULL));

ALTER TABLE logistica_entregas
    MODIFY COLUMN recibido_por VARCHAR(200) NULL,
    ADD COLUMN resultado VARCHAR(20) NOT NULL DEFAULT 'ACEPTADA',
    ADD COLUMN motivo_fallo TEXT NULL,
    ADD COLUMN fecha_operativa DATETIME(6) NULL,
    ADD UNIQUE KEY uq_log_entrega_despacho_sede (id,despacho_id,sucursal_id),
    ADD CONSTRAINT ck_log_entrega_resultado CHECK (BINARY resultado IN ('ACEPTADA','PARCIAL','RECHAZADA','FALLIDA')),
    ADD CONSTRAINT ck_log_entrega_recibido CHECK
        ((BINARY resultado IN ('ACEPTADA','PARCIAL') AND recibido_por IS NOT NULL AND CHAR_LENGTH(TRIM(recibido_por))>0)
        OR (BINARY resultado IN ('RECHAZADA','FALLIDA') AND (recibido_por IS NULL OR CHAR_LENGTH(TRIM(recibido_por))>0))),
    ADD CONSTRAINT ck_log_entrega_fallo CHECK
        ((BINARY resultado='FALLIDA' AND motivo_fallo IS NOT NULL AND CHAR_LENGTH(TRIM(motivo_fallo))>0)
        OR (BINARY resultado<>'FALLIDA' AND (motivo_fallo IS NULL OR CHAR_LENGTH(TRIM(motivo_fallo))>0)));

UPDATE logistica_entregas SET fecha_operativa=CAST(fecha AS DATETIME);

ALTER TABLE logistica_entregas
    MODIFY COLUMN fecha_operativa DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    ADD CONSTRAINT ck_log_entrega_operativa CHECK (fecha_operativa>='1000-01-01 00:00:00' AND DAYOFMONTH(fecha_operativa)>=1);

-- cantidad conserva el significado de cantidad ACEPTADA de 006.
ALTER TABLE logistica_entrega_detalle
    ADD COLUMN cantidad_rechazada DECIMAL(14,3) NOT NULL DEFAULT 0,
    ADD UNIQUE KEY uq_log_entrega_detalle_linea_padre (id,despacho_detalle_id,despacho_id),
    ADD CONSTRAINT ck_log_entrega_detalle_cantidad CHECK
        (cantidad>=0 AND cantidad_rechazada>=0 AND cantidad+cantidad_rechazada>0);

CREATE TABLE logistica_incidencias (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sucursal_id INT NOT NULL,
    despacho_id BIGINT NOT NULL,
    despacho_detalle_id BIGINT NULL,
    entrega_id BIGINT NULL,
    tipo VARCHAR(40) NOT NULL,
    modo VARCHAR(20) NOT NULL,
    causas_json LONGTEXT NOT NULL,
    cantidad DECIMAL(14,3) NULL,
    bloqueante TINYINT NOT NULL,
    motivo TEXT NOT NULL,
    evidencia_json LONGTEXT NOT NULL,
    responsable_id BIGINT NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    resuelta_at DATETIME(6) NULL,
    resuelta_by BIGINT NULL,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_log_incidencia_sede (id,sucursal_id),
    UNIQUE KEY uq_log_incidencia_despacho_sede (id,despacho_id,sucursal_id),
    UNIQUE KEY uq_log_incidencia_linea_padre (id,despacho_detalle_id,despacho_id,sucursal_id),
    INDEX ix_log_incidencia_despacho_pendiente (despacho_id,resuelta_at,id),
    INDEX ix_log_incidencia_sede_pendiente_fecha (sucursal_id,resuelta_at,created_at,id),
    CONSTRAINT fk_log_incidencia_despacho_sede FOREIGN KEY (despacho_id,sucursal_id)
        REFERENCES logistica_despachos(id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_incidencia_detalle_despacho FOREIGN KEY (despacho_detalle_id,despacho_id)
        REFERENCES logistica_despacho_detalle(id,despacho_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_incidencia_entrega_padre FOREIGN KEY (entrega_id,despacho_id,sucursal_id)
        REFERENCES logistica_entregas(id,despacho_id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_incidencia_responsable FOREIGN KEY (responsable_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_incidencia_resuelta FOREIGN KEY (resuelta_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_incidencia_created FOREIGN KEY (created_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_incidencia_updated FOREIGN KEY (updated_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_incidencia_tipo CHECK (BINARY tipo IN
        ('FALTANTE','RECHAZO','DANO','PERDIDA_EN_INVESTIGACION','AUSENCIA_RECEPTOR','RETRASO','PROBLEMA_DOCUMENTAL')),
    CONSTRAINT ck_log_incidencia_modo CHECK (BINARY modo IN ('CUANTITATIVA','INFORMATIVA')),
    CONSTRAINT ck_log_incidencia_cantidad CHECK
        ((BINARY modo='CUANTITATIVA' AND despacho_detalle_id IS NOT NULL AND cantidad IS NOT NULL AND cantidad>0)
        OR (BINARY modo='INFORMATIVA' AND (cantidad IS NULL OR (despacho_detalle_id IS NOT NULL AND cantidad>0)))),
    CONSTRAINT ck_log_incidencia_bloqueante CHECK (bloqueante IN (0,1)),
    CONSTRAINT ck_log_incidencia_motivo CHECK (CHAR_LENGTH(TRIM(motivo))>0),
    CONSTRAINT ck_log_incidencia_causas CHECK (JSON_VALID(causas_json)),
    CONSTRAINT ck_log_incidencia_evidencia CHECK (JSON_VALID(evidencia_json)),
    CONSTRAINT ck_log_incidencia_version CHECK (version>=1),
    CONSTRAINT ck_log_incidencia_resuelta CHECK
        ((resuelta_at IS NULL AND resuelta_by IS NULL) OR (resuelta_at IS NOT NULL AND resuelta_by IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hechos finales inmutables: el Repository no ofrece UPDATE/DELETE de resoluciones.
-- Una futura correccion requiere un comando compensatorio especifico.
CREATE TABLE logistica_incidencia_resoluciones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    incidencia_id BIGINT NOT NULL,
    sucursal_id INT NOT NULL,
    despacho_id BIGINT NOT NULL,
    despacho_detalle_id BIGINT NULL,
    tipo VARCHAR(40) NOT NULL,
    cantidad DECIMAL(14,3) NULL,
    entrega_detalle_id BIGINT NULL,
    confirmacion_sistema VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
    confirmacion_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
    confirmacion_snapshot LONGTEXT NULL,
    autorizacion_ref VARCHAR(128) NULL,
    motivo TEXT NOT NULL,
    evidencia_json LONGTEXT NOT NULL,
    fecha_operativa DATETIME(6) NOT NULL,
    created_by BIGINT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_log_resolucion_sede (id,sucursal_id),
    UNIQUE KEY uq_log_resolucion_incidencia_sede (id,incidencia_id,sucursal_id),
    UNIQUE KEY uq_log_resolucion_despacho_sede (id,despacho_id,sucursal_id),
    -- GLOBAL respecto de sede: una misma confirmacion granular no se reutiliza.
    UNIQUE KEY uq_log_resolucion_confirmacion (confirmacion_sistema,confirmacion_id),
    INDEX ix_log_resolucion_incidencia_fecha (incidencia_id,fecha_operativa,id),
    INDEX ix_log_resolucion_despacho_linea (despacho_id,despacho_detalle_id,id),
    CONSTRAINT fk_log_resolucion_incidencia_sede FOREIGN KEY (incidencia_id,despacho_id,sucursal_id)
        REFERENCES logistica_incidencias(id,despacho_id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_resolucion_incidencia_linea FOREIGN KEY (incidencia_id,despacho_detalle_id,despacho_id,sucursal_id)
        REFERENCES logistica_incidencias(id,despacho_detalle_id,despacho_id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_resolucion_entrega_linea FOREIGN KEY (entrega_detalle_id,despacho_detalle_id,despacho_id)
        REFERENCES logistica_entrega_detalle(id,despacho_detalle_id,despacho_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_log_resolucion_created FOREIGN KEY (created_by)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_log_resolucion_tipo CHECK (BINARY tipo IN
        ('ACEPTACION_EN_REINTENTO','RETORNO_CONFIRMADO','PERDIDA_CONFIRMADA','DISPOSICION_FINAL_CONFIRMADA','RESOLUCION_OPERATIVA')),
    CONSTRAINT ck_log_resolucion_cantidad CHECK
        ((BINARY tipo='RESOLUCION_OPERATIVA' AND cantidad IS NULL)
        OR (BINARY tipo<>'RESOLUCION_OPERATIVA' AND cantidad IS NOT NULL AND cantidad>0 AND despacho_detalle_id IS NOT NULL)),
    CONSTRAINT ck_log_resolucion_entrega CHECK
        ((BINARY tipo='ACEPTACION_EN_REINTENTO' AND entrega_detalle_id IS NOT NULL)
        OR (BINARY tipo<>'ACEPTACION_EN_REINTENTO' AND entrega_detalle_id IS NULL)),
    CONSTRAINT ck_log_resolucion_confirmacion CHECK
        ((BINARY tipo IN ('RETORNO_CONFIRMADO','PERDIDA_CONFIRMADA','DISPOSICION_FINAL_CONFIRMADA')
            AND confirmacion_sistema IS NOT NULL AND CHAR_LENGTH(TRIM(confirmacion_sistema))>0
            AND confirmacion_id IS NOT NULL AND CHAR_LENGTH(TRIM(confirmacion_id))>0
            AND confirmacion_snapshot IS NOT NULL AND JSON_VALID(confirmacion_snapshot))
        OR (BINARY tipo IN ('ACEPTACION_EN_REINTENTO','RESOLUCION_OPERATIVA')
            AND confirmacion_sistema IS NULL AND confirmacion_id IS NULL AND confirmacion_snapshot IS NULL)),
    CONSTRAINT ck_log_resolucion_autorizacion CHECK
        ((BINARY tipo IN ('PERDIDA_CONFIRMADA','DISPOSICION_FINAL_CONFIRMADA')
            AND autorizacion_ref IS NOT NULL AND CHAR_LENGTH(TRIM(autorizacion_ref))>0)
        OR (BINARY tipo NOT IN ('PERDIDA_CONFIRMADA','DISPOSICION_FINAL_CONFIRMADA')
            AND (autorizacion_ref IS NULL OR CHAR_LENGTH(TRIM(autorizacion_ref))>0))),
    CONSTRAINT ck_log_resolucion_motivo CHECK (CHAR_LENGTH(TRIM(motivo))>0),
    CONSTRAINT ck_log_resolucion_evidencia CHECK (JSON_VALID(evidencia_json)),
    CONSTRAINT ck_log_resolucion_fecha CHECK (fecha_operativa>='1000-01-01 00:00:00' AND DAYOFMONTH(fecha_operativa)>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE logistica_historial
    ADD COLUMN incidencia_id BIGINT NULL,
    ADD COLUMN resolucion_id BIGINT NULL,
    ADD COLUMN payload_json LONGTEXT NULL,
    ADD INDEX ix_log_historial_incidencia (sucursal_id,incidencia_id,id),
    ADD INDEX ix_log_historial_incidencia_padre (incidencia_id,despacho_id,sucursal_id),
    ADD INDEX ix_log_historial_resolucion_caso (resolucion_id,incidencia_id,sucursal_id),
    ADD CONSTRAINT fk_log_historial_incidencia_padre FOREIGN KEY (incidencia_id,despacho_id,sucursal_id)
        REFERENCES logistica_incidencias(id,despacho_id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT fk_log_historial_resolucion_caso FOREIGN KEY (resolucion_id,incidencia_id,sucursal_id)
        REFERENCES logistica_incidencia_resoluciones(id,incidencia_id,sucursal_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT ck_log_historial_incidencia_padre CHECK (incidencia_id IS NULL OR despacho_id IS NOT NULL),
    ADD CONSTRAINT ck_log_historial_resolucion_caso CHECK (resolucion_id IS NULL OR incidencia_id IS NOT NULL),
    ADD CONSTRAINT ck_log_historial_payload CHECK (payload_json IS NULL OR JSON_VALID(payload_json));

INSERT INTO schema_migrations(version,description)
VALUES (7,'Logistica: intentos, incidencias, resoluciones y cierre por balance');
