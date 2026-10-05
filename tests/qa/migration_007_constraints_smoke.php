<?php
declare(strict_types=1);
require dirname(__DIR__) . '/integration/bootstrap.php';

$db = new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/006_logistics_persistence.sql');
    fixtures($db->pdo);
    $pdo = $db->pdo;
    $pdo->exec("INSERT INTO logistica_preparaciones(id,sucursal_id,almacen_id,tipo_origen,origen_ref,fecha,destino,cliente_id,reserva_ref,estado,created_by,updated_by) VALUES(1,1,1,'VENTA','sale-1','2026-10-05','Fixture',1,'reservation-1','PREPARADA',1,1)");
    $pdo->exec("INSERT INTO logistica_preparacion_detalle(id,preparacion_id,posicion,origen_linea_ref,producto_id,unidad_id,lote_id,cantidad) VALUES(1,1,1,'sale-line-1',1,1,1,100),(2,1,2,'sale-line-2',2,2,2,100)");
    $pdo->exec("INSERT INTO logistica_despachos(id,preparacion_id,sucursal_id,fecha,estado,created_by,updated_by) VALUES(1,1,1,'2026-10-05','EN_TRANSITO',1,1)");
    $pdo->exec('INSERT INTO logistica_despacho_detalle(id,despacho_id,preparacion_id,preparacion_detalle_id,producto_id,unidad_id,lote_id,cantidad) VALUES(1,1,1,1,1,1,1,100),(2,1,1,2,2,2,2,100)');
    $pdo->exec("INSERT INTO logistica_entregas(id,despacho_id,sucursal_id,fecha,recibido_por,created_by) VALUES(1,1,1,'2026-10-05','Fixture',1)");
    $pdo->exec('INSERT INTO logistica_entrega_detalle(id,entrega_id,despacho_id,despacho_detalle_id,cantidad) VALUES(1,1,1,1,80)');
    $pdo->exec("UPDATE logistica_despachos SET estado='ENTREGADO' WHERE id=1");
    rejects(fn() => $db->load('database/migrations/007_logistics_incidents.sql'), PDOException::class, 1644);
    ensure((int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='logistica_entregas' AND column_name='resultado'")->fetchColumn() === 0, 'Unsafe legacy closure guard runs before altering logistics tables');
    $pdo->exec("UPDATE logistica_despachos SET estado='CERRADO_CON_INCIDENCIA' WHERE id=1");
    rejects(fn() => $db->load('database/migrations/007_logistics_incidents.sql'), PDOException::class, 1644);
    $pdo->exec("UPDATE logistica_despachos SET estado='EN_TRANSITO' WHERE id=1");
    $db->load('database/migrations/007_logistics_incidents.sql');
    $pdo->exec("INSERT INTO logistica_incidencias(id,sucursal_id,despacho_id,despacho_detalle_id,tipo,modo,causas_json,cantidad,bloqueante,motivo,evidencia_json,responsable_id,created_by,updated_by) VALUES(1,1,1,1,'RECHAZO','CUANTITATIVA','[\"RECHAZO\"]',20,1,'Fixture','{\"foto\":\"fixture\"}',1,1,1),(2,1,1,2,'RECHAZO','CUANTITATIVA','[\"RECHAZO\"]',20,1,'Fixture','{\"foto\":\"fixture\"}',1,1,1)");
    $resolutionSql="INSERT INTO logistica_incidencia_resoluciones(incidencia_id,sucursal_id,despacho_id,despacho_detalle_id,tipo,cantidad,confirmacion_sistema,confirmacion_id,confirmacion_snapshot,motivo,evidencia_json,fecha_operativa,created_by) VALUES(?,1,1,?,'RETORNO_CONFIRMADO',12,'INVENTARIOS',?,'{\"fixture\":true}','Fixture','{\"foto\":\"fixture\"}','2026-10-05 12:00:00',1)";
    $insert = $pdo->prepare($resolutionSql);
    rejects(fn() => $insert->execute([1,2,'wrong-line']), PDOException::class, 1452);
    $insert->execute([1,1,'return-line-1']);
    rejects(fn() => $insert->execute([2,2,'return-line-1']), PDOException::class, 1062);
    ensure((int)$pdo->query('SELECT COUNT(*) FROM logistica_incidencia_resoluciones')->fetchColumn() === 1, 'One external granular confirmation persisted once');
    rejects(fn() => $pdo->exec("INSERT INTO logistica_incidencia_resoluciones(incidencia_id,sucursal_id,despacho_id,despacho_detalle_id,tipo,cantidad,entrega_detalle_id,motivo,evidencia_json,fecha_operativa,created_by) VALUES(2,1,1,2,'ACEPTACION_EN_REINTENTO',1,1,'Fixture','{}','2026-10-05 12:00:00',1)"), PDOException::class, 1452);
    rejects(fn() => $pdo->exec("UPDATE logistica_incidencias SET modo='CUANTITATIVA',cantidad=NULL WHERE id=1"), PDOException::class);
    rejects(fn() => $pdo->exec("UPDATE logistica_entrega_detalle SET cantidad=0,cantidad_rechazada=0 WHERE id=1"), PDOException::class);
    rejects(fn() => $pdo->exec("UPDATE logistica_despachos SET estado='ENTREGADO' WHERE id=1"), PDOException::class);
    $pdo->exec("INSERT INTO logistica_entregas(id,despacho_id,sucursal_id,fecha,recibido_por,resultado,motivo_fallo,fecha_operativa,created_by) VALUES(2,1,1,'2026-10-05',NULL,'FALLIDA','No receptor','2026-10-05 12:00:00',1)");
    ensure((int)$pdo->query('SELECT COUNT(*) FROM logistica_entrega_detalle WHERE entrega_id=2')->fetchColumn() === 0, 'FALLIDA valid without a fake zero quantity detail');
    rejects(fn() => $db->load('database/migrations/rollback/007_logistics_incidents_down.sql'), PDOException::class, 1644);
    ensure((int)$pdo->query('SELECT COUNT(*) FROM logistica_entregas WHERE id=2')->fetchColumn() === 1, 'Blocked down retains failed attempt');
    echo '007 constraints: ' . $GLOBALS['checks'] . " checks passed\n";
} finally { $db->close(); }
