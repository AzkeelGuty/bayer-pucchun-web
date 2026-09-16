# Día 2, paso 3: consultas del Data Hub

Base: plan de mejora, DH-01 y contrato Data Hub (dataset, from, to, sucursal, almacen, producto, page, per_page y orden permitido). DT-03 asigna consultas a repositorios y autorización/reglas a servicios.

Esta entrega prepara la consulta de datos internos PUBLICADOS. No conecta rutas, pantallas ni exportadores; no sustituye BayerDataService. No es todavía el dataset homologado para entrega a Bayer: MAP-01 se integra después y requiere comprobación de homologaciones. No se modificó el esquema ni se agregaron índices sin mediciones.

## Archivos y recorrido individual

1. `app/DTO/DataQuery.php`: recibe un array explícito, normaliza los parámetros y rechaza entradas inválidas. Es inmutable; no lee GET ni sesión. El servicio lo construye después de autorizar la consulta y fijar su alcance.
2. `app/Repositories/DataHub/DataHubRepository.php`: recibe ese objeto, elige SQL de una lista cerrada y aplica siempre PUBLICADO. Devuelve líneas de detalle con los nombres de campos que ya utilizaba BayerDataService. No crea ni cambia registros.
3. `tests/integration/data_hub_test.php`: crea una base temporal, carga Schema v2 y datos ficticios, prueba las consultas y elimina su base al finalizar. No lee .env ni modifica la base de aplicación.

## Contrato propuesto para Alisson

```php
// El servicio ya comprobó permiso y alcance; los filtros no conceden acceso.
$query = new App\DTO\DataQuery([
    'dataset' => 'sales',
    'from' => '2026-09-01',
    'to' => '2026-09-30',
    'sucursal' => 1,
    'producto' => 1,
    'page' => 1,
    'per_page' => 50,
    'sort' => 'date',
    'direction' => 'desc',
]);
$hub = new App\Repositories\DataHub\DataHubRepository($pdo);
$result = $hub->page($query);
$count = $hub->count($query);
```

- `dataset`: sales/documents, shipments/guides o inventory/stock. Se normaliza al primer nombre de cada pareja.
- `from`, `to`: fechas reales AAAA-MM-DD, límites inclusivos, año mínimo 1000; from no puede superar to. null o cadena vacía significa sin límite.
- `sucursal`, `producto`: identificadores positivos opcionales. `almacen`: identificador opcional solo para inventory/stock; en ventas o guías se rechaza porque esas cabeceras no registran almacén. No se inventa una relación para filtrarlas.
- `page`: entero positivo, predeterminado 1. `per_page`: 1 a 100, predeterminado 50. Se rechaza un desplazamiento que desborde el entero de PHP.
- `sort`: date, materialId, quantity; adicionalmente documentNumber para ventas/guías o warehouseId para stock. Predeterminado date. `direction`: asc/desc, sin distinguir mayúsculas, predeterminado desc. Los empates se ordenan por cabecera y detalle en la misma dirección.
- Los parámetros desconocidos se rechazan. No admite un filtro de estado que permita consultar borradores. Errores de entrada: InvalidArgumentException; errores de SQL: PDOException, que el servicio debe convertir en respuestas seguras.

`page()` devuelve `items`, `total`, `page`, `per_page`, `total_pages`. Cada item es una línea de detalle; total NO es el número de documentos o guías. Una página fuera de rango tiene items vacío y conserva el total. Sin coincidencias, total y total_pages son cero. La respuesta oculta las columnas auxiliares internas.

Página y total se calculan en una misma sentencia CTE para mantener coherencia de lectura. `count()` reutiliza los mismos filtros e ignora paginación/orden. Un count posterior puede reflejar publicaciones nuevas: no representa una congelación histórica de datos. La paginación por desplazamiento tampoco garantiza una exportación estable mientras se publican o anulan registros; esa integración requiere su estrategia de lectura.

Los códigos y nombres proceden de maestros internos actuales, igual que en el servicio anterior. Las homologaciones y la conservación de una versión histórica de datos maestros NO están resueltas por estas consultas. También se conserva el origen geográfico previo: cliente en ventas, cabecera en guías. El nombre de vendedor usa CONCAT_WS para no quedar completamente vacío cuando falta el apellido.

## Alcance y pendientes

- Alisson: aceptar contrato, integrar servicio/controladores, permisos y alcance obligatorio del usuario. Los filtros sucursal/almacen son filtros de consulta, no controles de seguridad. No conectar directamente un array HTTP sin revisión del servicio.
- Pedro: seed de muestra disponible en `database/seeders/002_sample_masters.sql`; homologaciones MAP-01 y revisión de índices/paginación del día 3. La integración de exportaciones debe reutilizar criterios y no exportar únicamente la primera página por accidente.
- Siguen intactos MasterDataService, BayerDataService y los controladores actuales. Los repositorios están organizados en Masters, Operations y DataHub. No se hizo commit/push.

## Verificación

Ejecutar `php tests/integration/data_hub_test.php` desde la raíz del proyecto. Comprueba publicación, los tres datasets, filtros combinados, intervalos inclusivos, alias, orden numérico, desempates, páginas vacías, conteos, entradas inválidas e independencia de GET. Usa el mismo entorno de pruebas MariaDB del día 1, con consultas preparadas reales y excepciones PDO.
