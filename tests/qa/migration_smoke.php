<?php
declare(strict_types=1);
require dirname(__DIR__) . '/integration/bootstrap.php';
$db = new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/006_logistics_persistence.sql');
    $tables = $db->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'logistica_%'")->fetchAll(PDO::FETCH_COLUMN);
    ensure(count($tables) === 8, 'Eight logistics tables');
    $db->load('database/migrations/rollback/006_logistics_persistence_down.sql');
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'logistica_%'")->fetchColumn() === 0, 'Rollback removes only new tables');
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='guias_cabecera'")->fetchColumn() === 1, 'Existing Guides preserved');
    echo "Migration smoke: up/down OK, eight new tables, existing Guides preserved\n";
} finally { $db->close(); }
