<?php
declare(strict_types=1);
require dirname(__DIR__) . '/integration/bootstrap.php';

$db = new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/006_logistics_persistence.sql');
    $db->load('database/migrations/007_logistics_incidents.sql');
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'logistica_%'")->fetchColumn() === 10, '007 makes ten logistics tables');
    $db->load('database/migrations/rollback/007_logistics_incidents_down.sql');
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'logistica_%'")->fetchColumn() === 8, '007 rollback retains eight 006 tables');
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=6')->fetchColumn() === 1, '006 version retained');
    $db->load('database/migrations/rollback/006_logistics_persistence_down.sql');
    $db->load('database/schemas/006_logistics_persistence.sql');
    fixtures($db->pdo);
    $db->pdo->exec("INSERT INTO logistica_preparaciones(id,sucursal_id,almacen_id,tipo_origen,origen_ref,fecha,destino,cliente_id,reserva_ref,estado,created_by,updated_by) VALUES(1,1,1,'VENTA','sale-1','2026-10-05','Fixture',1,'reservation-1','PREPARADA',1,1)");
    $db->pdo->exec("INSERT INTO logistica_preparacion_detalle(id,preparacion_id,posicion,origen_linea_ref,producto_id,unidad_id,lote_id,cantidad) VALUES(1,1,1,'sale-line-1',1,1,1,100)");
    $db->pdo->exec("INSERT INTO logistica_despachos(id,preparacion_id,sucursal_id,fecha,estado,created_by,updated_by) VALUES(1,1,1,'2026-10-05','ENTREGADO',1,1)");
    $db->pdo->exec('INSERT INTO logistica_despacho_detalle(id,despacho_id,preparacion_id,preparacion_detalle_id,producto_id,unidad_id,lote_id,cantidad) VALUES(1,1,1,1,1,1,1,100)');
    $db->pdo->exec("INSERT INTO logistica_entregas(id,despacho_id,sucursal_id,fecha,recibido_por,created_by) VALUES(1,1,1,'2026-10-05','Fixture',1)");
    $db->pdo->exec('INSERT INTO logistica_entrega_detalle(id,entrega_id,despacho_id,despacho_detalle_id,cantidad) VALUES(1,1,1,1,100)');
    $db->load('database/schemas/007_logistics_incidents.sql');
    ensure($db->pdo->query('SELECT resultado FROM logistica_entregas WHERE id=1')->fetchColumn() === 'ACEPTADA', '006 accepted fact preserved');
    ensure($db->pdo->query('SELECT cantidad FROM logistica_entrega_detalle WHERE id=1')->fetchColumn() === '100.000', '006 quantities preserved');
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM logistica_despachos WHERE cerrado_at IS NOT NULL AND cerrado_by=1')->fetchColumn() === 1, 'Legacy normal closure audit backfilled');
    $db->load('database/migrations/rollback/007_logistics_incidents_down.sql');
    ensure($db->pdo->query('SELECT cantidad FROM logistica_entrega_detalle WHERE id=1')->fetchColumn() === '100.000', 'Safe down preserves legacy facts');
    $db->load('database/migrations/007_logistics_incidents.sql');
    $db->pdo->exec("INSERT INTO logistica_incidencias(id,sucursal_id,despacho_id,despacho_detalle_id,tipo,modo,causas_json,cantidad,bloqueante,motivo,evidencia_json,responsable_id,created_by,updated_by) VALUES(1,1,1,1,'DANO','INFORMATIVA','[\"DANO\"]',10,1,'Fixture','{}',1,1,1)");
    rejects(fn() => $db->load('database/migrations/rollback/007_logistics_incidents_down.sql'), PDOException::class, 1644);
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM logistica_incidencias')->fetchColumn() === 1, 'Blocked down preserves incidence');
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=7')->fetchColumn() === 1, 'Blocked down preserves version');
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='logistica_entregas' AND column_name='resultado'")->fetchColumn() === 1, 'Blocked down did not alter old tables');
    echo '007 migrations: ' . $GLOBALS['checks'] . " checks passed\n";
} finally { $db->close(); }
