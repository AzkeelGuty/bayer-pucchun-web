# Verificación de la persistencia de Logística

Fecha: 5 de octubre de 2026. Base: `f38f4e87e09f9311324e5b25384708b58841ca99`. Rama de publicación: `feature/logistics-flow-pedro`. La validación inicial se hizo en la rama local histórica de persistencia.

## Verificación antes de publicar

Se repitieron correctamente **34 suites**: las 27 originales, las 3 de Logística y 4 scripts auxiliares de QA. Se mantienen **545 comprobaciones de Logística** (42 + 239 + 264), y los scripts 007 verifican otras 46 comprobaciones más el smoke inicial 006. Se revisó la sintaxis de **132 archivos PHP** y la identidad de ambos snapshots con sus migraciones. Las pruebas usaron el servidor temporal aislado de localhost:43316 y el arnés de bases `bayer_test_<hex>`.

Los cuatro scripts auxiliares se publican en [tests/qa](../../tests/qa/README.md) con rutas relativas y ejecución desde CI. El resultado remoto de GitHub Actions debe consultarse por separado; estos resultados corresponden a la ejecución local previa al push.

Entorno ejecutado: Windows, PHP 8.2.12 de 64 bits, MariaDB 10.4.32 en servidor temporal dedicado de localhost, puerto 43316. Todas las pruebas de integración utilizan bases `bayer_test_<hex>` creadas y eliminadas por el arnés, sin leer `.env`. CI está configurado para PHP 8.2 y MariaDB 10.11; ese workflow remoto no se ejecutó en esta entrega local.

## Resultado vigente: evolución 007

La evolución fue implementada y verificada el 5 de octubre de 2026 en el mismo servidor temporal aislado. El registro 006 posterior conserva resultados históricos, no certifica por sí solo estos casos nuevos.

- Cantidades: **42 checks correctos**.
- Persistencia actualizada con 006 + 007: **239 checks correctos**.
- Incidencias e intentos: **264 checks correctos**.
- Total de Logística: **545 comprobaciones correctas**.
- **27 suites originales** ejecutadas y correctas.
- **128 archivos PHP** revisados; los cambios finales de app pasaron lint.
- **46 comprobaciones adicionales** de migración/restricciones/normalizadores en scripts de QA, fuera de CI y del código de aplicación.

Salida final de las suites:

```text
Logistics quantities: 42 checks OK
Logistics persistence: 239 checks OK (migration, rollback, isolation and concurrent workers)
Logistics incidents integration: 264 checks passed
```

Verificado: cien despachadas con ochenta aceptadas y veinte rechazadas; retorno confirmado de doce y pérdida autorizada de ocho; rechazo total; intento FALLIDA sin líneas con tipo obligatorio; fallo documental sin inventar ausencia; aceptación posterior y rechazo repetido sin reasignación; múltiples causas sobre una cantidad; límites de resolución de varios casos desde una sola recepción; daño aceptado con veinte afectadas registrado dentro de la misma entrega; problema documental pendiente con aceptación completa; acciones de devolución/investigación/reprogramación sin destinos finales ficticios; unicidad de confirmación externa incluso con otra clave; identidad/sede/unidad/lote/dirección y evidencia verificadas; ausencia de proveedor; rollback tardío; cierre protegido; corrección ordinaria bloqueada ante dependencias.

Con workers independientes, retorno y reentrega simultáneos de las mismas veinte producen un éxito y un CONFLICT, una sola resolución y un balance exacto de cien. Se conserva también la concurrencia y los savepoints de la suite anterior. Las bases temporales se eliminan al terminar.

Migración 007 y snapshot idénticos. Upgrade/downgrade compatible conserva registros previos; datos nuevos incompatibles bloquean rollback antes de alterar tablas. La reversión no borra incidencias para forzar compatibilidad.

Los scripts adicionales estuvieron originalmente en `../qa_mariadb_logistics`: migration_007_smoke.php (21), migration_007_constraints_smoke.php (22) y repository_007_normalizers_smoke.php (3). Sus versiones portables están ahora en `tests/qa/`, junto al smoke inicial 006. No forman parte de producción.

El proveedor de Inventarios usado por las pruebas vive exclusivamente en tests. Falta el adaptador real, los Services/Controllers/permisos y la integración de reservas/salidas. La aceptación de traslados se rechaza hasta integrar su recepción confirmada. Guías todavía requiere revisar su compatibilidad con cantidades fraccionarias. Estos resultados certifican persistencia y sus casos probados, no un ERP listo para producción. La integración remota avanzó a d0819d4 y necesita la validación del equipo al consolidar ramas.

## Registro histórico: entrega inicial 006

- `tests/unit/logistics_quantity_test.php`: **42 comprobaciones correctas**.
- `tests/integration/logistics_persistence_test.php`: **227 comprobaciones correctas**.
- Total nuevo: **269 comprobaciones correctas**.
- Las **27 suites originales** se ejecutaron; cuatro requirieron corregir el entorno o la portabilidad y después pasaron.
- Sintaxis PHP: **126 archivos correctos**.
- Migración 006 y snapshot equivalentes, reversión aislada correcta y `git diff --check` sin errores.

Salida de la última ejecución de las suites nuevas:

```text
Logistics quantities: 42 checks OK
Logistics persistence: 227 checks OK (migration, rollback, isolation and concurrent workers)
```

## Casos comprobados

Modelo de ocho tablas, claves foráneas y cantidades positivas; precisión decimal y rechazo de floats/rangos inválidos; sede y referencias correctas; edición y congelación de preparación; guía única, versión y snapshot, sumas por producto/unidad; entregas parciales, saldos, sobreentrega y anulación conservando datos; cancelación coordinada antes de salida; historial e idempotencia con resultado original; versión obsoleta; fallo tardío de historial sin cabeceras o líneas parciales; savepoints y transacciones del llamador; lecturas actuales dentro de un snapshot antiguo; UTC independiente de la zona horaria del caller.

Los workers independientes verifican solicitudes repetidas simultáneas, tres repeticiones de una clave ya confirmada, entregas concurrentes y dos anulaciones distintas sobre la misma entrega. En las anulaciones hay exactamente un éxito y un conflicto, con un solo evento/resultado, versiones incrementadas una vez y cantidades correctas.

## Ajustes de verificación del proyecto original

XAMPP tiene ZIP deshabilitado en el CLI por defecto: la prueba de catálogos se repitió con `-d extension=zip`. El test de validaciones se repitió con `-d session.save_path=storage/sessions`. Dos pruebas de branding comparaban rutas con separadores diferentes en Windows; se normalizó únicamente esa comparación, sin modificar la lógica de la aplicación.

## Alcance verificado

Esta entrega valida la persistencia y el contrato del Repository. Controllers, Services, permisos/rutas y conexión a módulos reales son el trabajo de Alisson y los otros responsables. Las referencias de Inventarios de las pruebas son fixtures; no representan movimientos reales de stock. Guías fraccionarias se preparan por SQL solo en la base temporal, porque el CRUD vigente aún exige cantidades enteras.

Los proyectos originales `frontend-equipo` y `C:\xampp\htdocs\bayer-pucchun-web` conservan sus ramas y cambios previos. Las migraciones no se aplicaron a sus bases. El servidor temporal se detiene al terminar la revisión.
