<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$db = new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql');
    $pdo = $db->pdo;

    foreach (['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'] as $role) {
        $st = $pdo->prepare('INSERT INTO roles(nombre,descripcion) VALUES(?,?)');
        $st->execute([$role, $role]);
    }

    // Simula una instalación v2 anterior a la incorporación de permisos granulares.
    $pdo->exec("INSERT INTO permisos(codigo,nombre) VALUES
        ('operations.view','Consultar operación'),
        ('operations.capture','Capturar operación'),
        ('validation.review','Validar información'),
        ('publications.publish','Publicar información')");

    $db->load('database/migrations/003_operational_permissions.sql');

    $expected = [
        'ADMIN' => ['documents.read','documents.create','guides.read','guides.create','stock.read','stock.create'],
        'DIGITADOR' => ['documents.read','documents.create','guides.read','guides.create','stock.read','stock.create'],
        'SUPERVISOR' => ['documents.read','guides.read','stock.read'],
        'GERENCIA' => ['documents.read','guides.read','stock.read'],
        'BAYER' => [],
    ];

    $st = $pdo->prepare(
        'SELECT p.codigo
         FROM permisos p
         JOIN rol_permiso rp ON rp.permiso_id=p.id
         JOIN roles r ON r.id=rp.rol_id
         WHERE r.nombre=?
         ORDER BY p.codigo'
    );

    foreach ($expected as $role => $permissions) {
        sort($permissions);
        $st->execute([$role]);
        $actual = $st->fetchAll(PDO::FETCH_COLUMN);
        sort($actual);
        ensure($actual === $permissions, "$role receives expected operational grants");
    }

    ensure(
        (int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version=3")->fetchColumn() === 1,
        'Migration 003 is registered'
    );

    // La migración debe ser segura al repetirse.
    $db->load('database/migrations/003_operational_permissions.sql');
    ensure(
        (int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version=3")->fetchColumn() === 1,
        'Migration 003 remains idempotent'
    );

    echo 'Operational permissions migration: '.$GLOBALS['checks']." checks OK\n";
} finally {
    $db->close();
}
