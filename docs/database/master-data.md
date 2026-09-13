# Día 2: consulta y persistencia del mantenimiento de maestros

Base documental: plan de mejora, tareas DB-02 y día 2; DT-03 separa persistencia y servicios. Esta primera entrega permite consultar maestros existentes. No completa todavía el mantenimiento administrado de catálogos ni el Data Hub del día 2.

## Contrato propuesto para Alisson y frontend

`new MasterDataRepository($pdo)` recibe la conexión; sin argumento utiliza `db()`.
El servicio debe comprobar permisos y alcance de acceso antes de llamar al repositorio.
No se crearon rutas ni se modificaron controladores. La aceptación del contrato con Alisson sigue pendiente.

- `search(catalog, query = '', filters = [], limit = 50, offset = 0)` devuelve una lista de filas para select/autocomplete. Siempre incluye `id` y `nombre`; incluye `codigo` cuando el catálogo dispone de él, más sus identificadores de relación. Lista vacía significa sin coincidencias.
- La búsqueda es por coincidencia parcial en código/nombre y, para clientes, también número de documento. `%`, `_` y `!` se buscan literalmente. Máximo 200 bytes de texto UTF-8, límite de 1 a 100 y desplazamiento no negativo. Orden estable por nombre e identificador. No devuelve un total ni es el contrato completo de listados CRUD.
- `find(catalog, id)` devuelve una fila o `null`. Incluye inactivos para poder mostrar referencias históricas; encontrar un maestro NO autoriza usarlo en una nueva operación.
- Errores de contrato producen `InvalidArgumentException`. Los errores de base conservan `PDOException`; el servicio debe convertirlos en respuestas seguras.

| Catálogo | Filtros admitidos |
|---|---|
| empresas | Ninguno |
| sucursales | empresa_id, distrito_id |
| almacenes | sucursal_id |
| clientes | departamento_id, provincia_id, distrito_id |
| vendedores | Ninguno |
| productos | categoria_id, marca_id, unidad_base_id |
| unidades_medida | Ninguno |
| lotes | producto_id |
| tipos_documento | Ninguno |
| departamentos | Ninguno |
| provincias | departamento_id |
| distritos | provincia_id |
| categorias_producto | Ninguno |
| marcas | Ninguno |

`search` devuelve solo estado=1 en las tablas que tienen ese campo. No se inventa un estado en clientes, unidades, tipos o geografía. Tampoco filtra por estado de padres o vencimiento: los servicios deben aplicar las reglas del caso de uso. En el selector de lotes, enviar producto_id para mostrar los del producto elegido. Los filtros por ubicación no sustituyen la autorización del usuario.

```php
$masters = new App\Repositories\Masters\MasterDataRepository($pdo);
$products = $masters->search('productos', 'M1');
$warehouses = $masters->search('almacenes', '', ['sucursal_id' => 1]);
$lots = $masters->search('lotes', '', ['producto_id' => 1]);
$existing = $masters->find('clientes', 1);
```

## Antes y ahora

El antiguo MasterDataService conserva sus métodos firstOrCreate y no se cambió. MasterDataRepository contiene únicamente SELECT y no lo llama. Los repositorios operativos del día 1 ya reciben identificadores y no crean maestros automáticamente. El paso 2 incorpora la persistencia del mantenimiento explícito con MasterDataMaintenanceRepository. Los servicios, permisos, pantallas y sustitución de usos antiguos del servicio siguen pendientes; el seed de muestra ya está disponible en `database/seeders/002_sample_masters.sql`; no conectar nuevas capturas a firstOrCreate.

## Paso 2: guardar maestros y auditar el cambio

DB-02 solicita separar el mantenimiento del ingreso operativo. DT-03 asigna persistencia a repositorios y reglas a servicios/validadores; DT-01 exige trazabilidad de cambios maestros. Esta entrega prepara la persistencia, no habilita todavía un mantenimiento administrado completo en la interfaz.

`new MasterDataMaintenanceRepository($pdo)` utiliza una conexión PDO configurada con excepciones y consultas preparadas reales, como el bootstrap de pruebas. Sin argumento usa `db()`.

- `create(catalog, data, actorId): int` inserta un maestro y devuelve su identificador. No reutiliza registros existentes: un duplicado sujeto a UNIQUE produce PDOException.
- `update(catalog, id, changes, actorId): void` modifica únicamente las columnas recibidas. Una fila inexistente produce RuntimeException. Enviar los mismos valores también registra la operación.
- Ambos admiten los mismos 14 catálogos del paso 1. Los campos son los nombres reales de columnas del esquema, no los alias de consulta: por ejemplo, empresas/clientes usan `razon_social`, vendedores `nombres`/`apellidos`, lotes `codigo_lote`.
- Las columnas admitidas están explícitas en FIELDS. No admite `id`, tablas ajenas ni cargas vacías. Valores: texto, enteros o null; decimales como texto. Si se envía `estado`, debe ser 0 o 1. Los campos omitidos en creación siguen los valores predeterminados o restricciones del esquema.
- No hay borrado físico. Se puede enviar estado=0 solo en catálogos que ya tienen esa columna.
- Cada cambio y su auditoría se guardan juntos. Si cualquiera falla, se deshacen ambos. Dentro de una transacción del servicio se utiliza un savepoint: el servicio conserva la decisión de confirmar o revertir.
- La auditoría guarda actor, catálogo, entidad, acción, fecha, resultado y nombres de columnas; no copia los valores personales. El actor debe existir por FK, pero esto NO comprueba sus permisos.

```php
// El servicio ya comprobó permisos y validó los datos antes de llegar aquí.
$maintenance = new App\Repositories\Masters\MasterDataMaintenanceRepository($pdo);
$productId = $maintenance->create('productos', [
    'codigo' => 'P-100',
    'nombre' => 'Producto de ejemplo',
    'unidad_base_id' => $existingUnitId,
], $authenticatedUserId);
$maintenance->update('productos', $productId, ['nombre' => 'Nombre corregido'], $authenticatedUserId);
```

### Conexión pendiente con Alisson

El servicio debe obtener el actor de la sesión autenticada, autorizar `masters.manage` y el alcance, validar campos requeridos/formato/longitud, relaciones geográficas, referencias activas y consecuencias de cambios sobre datos históricos (códigos, unidad base, producto del lote o vencimiento). Las FK no sustituyen esas reglas. No pasar directamente el cuerpo HTTP al repositorio. El servicio debe convertir errores de base en respuestas seguras y aplicar CSRF mediante la capa web correspondiente.

El bloqueo durante update serializa la escritura, pero no detecta formularios desactualizados: este contrato no añade versionado de maestros. La coordinación del contrato sigue pendiente. No conectar estas operaciones a una ruta hasta completar permisos y validaciones. No se modificaron el esquema, los controladores ni el servicio antiguo.

Ejecutar `php tests/integration/master_data_maintenance_test.php`. Usa una base temporal y comprueba los 14 catálogos, edición parcial, duplicados, FK, entradas no admitidas, auditoría atómica y transacciones exteriores. No carga datos en la base de la aplicación.

## Prueba

Ejecutar `php tests/integration/master_data_test.php` desde la raíz del proyecto.
Usa el entorno temporal del día 1: crea una base aleatoria, inserta datos ficticios y elimina su propia base al terminar. No lee .env ni modifica bayer_pucchun. Comprueba todos los catálogos, filtros, inactivos, búsqueda literal, paginación, entrada inválida y ausencia de escrituras del repositorio.
