# Comprobaciones adicionales de Logística

Estos scripts publican las verificaciones auxiliares de la entrega local. Usan el arnés de `tests/integration/bootstrap.php`: no cargan `.env` y crean/eliminan exclusivamente bases `bayer_test_<hex>`.

Ejecutarlos en el mismo entorno aislado que las pruebas de integración, con PHP/PDO MySQL y `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_USER` y `TEST_DB_PASS` del servidor de pruebas:

```sh
php tests/qa/migration_smoke.php
php tests/qa/migration_007_smoke.php
php tests/qa/migration_007_constraints_smoke.php
php tests/qa/repository_007_normalizers_smoke.php
```

- `migration_smoke.php`: alta/baja 006 y conservación del módulo de Guías.
- `migration_007_smoke.php`: actualización 006→007, estructura y reversión compatible.
- `migration_007_constraints_smoke.php`: integridad de relaciones, confirmaciones únicas y reversión bloqueada cuando perdería datos.
- `repository_007_normalizers_smoke.php`: normalización de identidad externa y payload estable.

Son pruebas de persistencia; no demuestran permisos, Services ni contratos productivos de Inventarios. Los fixtures y las referencias externas son ficticios. Las rutas originales específicas de mi computadora se reemplazaron por rutas relativas para ejecutarlos en CI u otro equipo.
