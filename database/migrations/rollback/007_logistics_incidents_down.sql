-- Rollback 007: vuelve al esquema 006 SOLO si no elimina hechos nuevos.
-- Logistica debe estar detenida. No borrar incidencias/resoluciones para forzar
-- este rollback: respaldar, conciliar y elegir una evolucion si ya existen.
-- El preflight aborta ANTES de cualquier ALTER/DROP de tablas de Logistica.
-- DDL no es transaccional; un fallo posterior exige revisar el estado parcial.
SET FOREIGN_KEY_CHECKS=1;

DELIMITER $$
DROP PROCEDURE IF EXISTS logistica_007_down_preflight$$
CREATE PROCEDURE logistica_007_down_preflight()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version=7) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Rollback 007 requiere 007 aplicada';
    END IF;
    IF EXISTS (SELECT 1 FROM logistica_incidencias)
       OR EXISTS (SELECT 1 FROM logistica_incidencia_resoluciones)
       OR EXISTS (SELECT 1 FROM logistica_historial
                  WHERE incidencia_id IS NOT NULL OR resolucion_id IS NOT NULL OR payload_json IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Rollback 007 bloqueado: hay incidencias, resoluciones o historial nuevo';
    END IF;
    IF EXISTS (SELECT 1 FROM logistica_entregas e
               WHERE e.resultado<>'ACEPTADA' OR e.motivo_fallo IS NOT NULL
                  OR e.recibido_por IS NULL OR CHAR_LENGTH(TRIM(e.recibido_por))=0
                  OR e.fecha_operativa<>CAST(e.fecha AS DATETIME)
                  OR NOT EXISTS (SELECT 1 FROM logistica_entrega_detalle d WHERE d.entrega_id=e.id))
       OR EXISTS (SELECT 1 FROM logistica_entrega_detalle WHERE cantidad<=0 OR cantidad_rechazada<>0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Rollback 007 bloqueado: hay intentos o cantidades incompatibles con 006';
    END IF;
    IF EXISTS (SELECT 1 FROM logistica_despachos WHERE estado='EN_RESOLUCION') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Rollback 007 bloqueado: hay despachos EN_RESOLUCION';
    END IF;
END$$
CALL logistica_007_down_preflight()$$
DROP PROCEDURE logistica_007_down_preflight$$

DROP PROCEDURE IF EXISTS logistica_007_down_drop_check$$
CREATE PROCEDURE logistica_007_down_drop_check(IN table_name_arg VARCHAR(64),IN check_name_arg VARCHAR(64))
BEGIN
    SET @logistica_007_down_ddl=CONCAT('ALTER TABLE `',table_name_arg,'` DROP ',
        IF(LOCATE('MariaDB',VERSION())>0,'CONSTRAINT','CHECK'),' `',check_name_arg,'`');
    PREPARE logistica_007_down_stmt FROM @logistica_007_down_ddl;
    EXECUTE logistica_007_down_stmt;
    DEALLOCATE PREPARE logistica_007_down_stmt;
END$$
CALL logistica_007_down_drop_check('logistica_historial','ck_log_historial_incidencia_padre')$$
CALL logistica_007_down_drop_check('logistica_historial','ck_log_historial_resolucion_caso')$$
CALL logistica_007_down_drop_check('logistica_historial','ck_log_historial_payload')$$
CALL logistica_007_down_drop_check('logistica_entrega_detalle','ck_log_entrega_detalle_cantidad')$$
CALL logistica_007_down_drop_check('logistica_entregas','ck_log_entrega_resultado')$$
CALL logistica_007_down_drop_check('logistica_entregas','ck_log_entrega_recibido')$$
CALL logistica_007_down_drop_check('logistica_entregas','ck_log_entrega_fallo')$$
CALL logistica_007_down_drop_check('logistica_entregas','ck_log_entrega_operativa')$$
CALL logistica_007_down_drop_check('logistica_despachos','ck_log_despacho_cierre')$$
CALL logistica_007_down_drop_check('logistica_despachos','ck_log_despacho_estado')$$
DROP PROCEDURE logistica_007_down_drop_check$$
DELIMITER ;

ALTER TABLE logistica_historial
    DROP FOREIGN KEY fk_log_historial_resolucion_caso,
    DROP FOREIGN KEY fk_log_historial_incidencia_padre,
    DROP INDEX ix_log_historial_incidencia,
    DROP INDEX ix_log_historial_incidencia_padre,
    DROP INDEX ix_log_historial_resolucion_caso,
    DROP COLUMN resolucion_id,
    DROP COLUMN incidencia_id,
    DROP COLUMN payload_json;

DROP TABLE logistica_incidencia_resoluciones;
DROP TABLE logistica_incidencias;

ALTER TABLE logistica_entrega_detalle
    DROP INDEX uq_log_entrega_detalle_linea_padre,
    DROP COLUMN cantidad_rechazada,
    ADD CONSTRAINT ck_log_entrega_detalle_cantidad CHECK (cantidad>0);

ALTER TABLE logistica_entregas
    DROP INDEX uq_log_entrega_despacho_sede,
    DROP COLUMN resultado,
    DROP COLUMN motivo_fallo,
    DROP COLUMN fecha_operativa,
    MODIFY COLUMN recibido_por VARCHAR(200) NOT NULL,
    ADD CONSTRAINT ck_log_entrega_recibido CHECK (CHAR_LENGTH(TRIM(recibido_por))>0);

ALTER TABLE logistica_despachos
    DROP FOREIGN KEY fk_log_despacho_cerrado_by,
    DROP COLUMN cerrado_at,
    DROP COLUMN cerrado_by,
    ADD CONSTRAINT ck_log_despacho_estado CHECK (BINARY estado IN
        ('PENDIENTE','DESPACHADO','EN_TRANSITO','ENTREGADO','CANCELADO','CERRADO_CON_INCIDENCIA'));

DELETE FROM schema_migrations WHERE version=7;
