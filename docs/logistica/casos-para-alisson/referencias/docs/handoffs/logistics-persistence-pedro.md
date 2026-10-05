# Entrega de persistencia de Logística — Pedro

Rama de publicación: `feature/logistics-flow-pedro`, por indicación de Pedro. El trabajo se inició desde `integration/frontend-equipo` en `f38f4e8`. Pedro confirmó que intercambiaron sus responsabilidades: él desarrolla persistencia y Alisson desarrolla Services, Controllers y funcionamiento. El nombre histórico de la rama se conserva aunque ahora Pedro entregue persistencia. Las ramas de Alisson y la rama conjunta no se modificaron.

## Qué utilizará Alisson

Inyectar `App\Contracts\LogisticsRepositoryInterface` en sus Services e implementar con `App\Repositories\LogisticsRepository`. El constructor admite PDO compartido y el proveedor confiable `LogisticsInventoryConfirmationProviderInterface` para retornos/disposiciones. Sin proveedor real, esos hechos se rechazan; no habilitar un mock en producción. Leer `docs/contracts/logistics-incidents.md` para el contrato vigente y `docs/database/logistics-persistence.md` para las relaciones.

Ejemplo de creación, una vez que el Service haya autorizado la acción y obtenido una reserva de Inventarios:

```php
$pdo = db();
$repository = new \App\Repositories\LogisticsRepository($pdo);

$context = [
    'sucursal_id' => $sedeAutorizada,
    'actor_id' => $usuarioAutenticado,
    'idempotency_key' => $claveEstableDeLaSolicitud,
];

$preparacion = $repository->createPreparation([
    'almacen_id' => $almacenAutorizado,
    'tipo_origen' => 'VENTA',
    'origen_ref' => $pedidoAutorizado,
    'fecha' => $fechaOperativa,
    'destino' => $direccionConfirmada,
    'cliente_id' => $clienteId,
    'almacen_destino_id' => null,
    'reserva_ref' => $reservaConfirmada,
], [[
    'origen_linea_ref' => $lineaAutorizada,
    'producto_id' => $productoId,
    'unidad_id' => $unidadId,
    'lote_id' => $loteId,
    'cantidad' => '10.000',
]], $context);
```

Son variables del Service, no valores inventados por persistencia. El Service coordina la asignación del pedido y la reserva. Para hacer atómicas operaciones locales, debe usar la misma conexión PDO de Inventarios y llamar a `transaction` o abrir una transacción común. Un adaptador de Inventarios también debe reconocer la clave de operación para que un reintento no vuelva a reservar o descontar antes del replay del Repository. Una llamada remota no queda cubierta por el rollback local.

Las acciones posteriores reciben la versión devuelta por la acción anterior y una clave propia. Para reintentar la misma acción se conserva la clave y el payload ORIGINAL, incluida la versión original; la respuesta guardada puede ser anterior a la versión actual del agregado. Consultar de nuevo el agregado si la interfaz necesita mostrar su estado presente.

Un CONFLICT por versión exige volver a consultar. Si el conflicto se debe a un deadlock, InnoDB puede haber revertido la transacción completa; el Service debe iniciar de nuevo toda la unidad, incluidas sus llamadas locales coordinadas. Las anulaciones distintas concurrentes pueden provocar esa contención y no deben producir dos anulaciones ni dos compensaciones. Los reintentos de Inventarios también deben proteger la identidad del movimiento, además de la solicitud.

Las lecturas exigen la sede: `findDispatch($id, $sedeAutorizada)`. Devuelven cabecera plana + `details`, no el envoltorio `header/details` de los Repositories documentales antiguos. Los saldos `cantidad_entregada` y `cantidad_pendiente` son cadenas decimales exactas.

Alisson debe validar permisos antes de toda escritura o replay, controlar las transiciones del negocio, comprobar los documentos y coordinar Inventarios/Ventas. No se añadieron Controllers, rutas, un nuevo CRUD de Guías ni movimientos de stock propios.

## Ejecutar pruebas

Requisitos: PHP 8.1+ de 64 bits, extensión pdo_mysql y servidor MySQL/MariaDB de pruebas. El arnés no lee `.env`, crea una base `bayer_test_<hex>` y la elimina al finalizar. El usuario del servidor de pruebas debe poder crear y eliminar esas bases.

```powershell
$env:TEST_DB_HOST = '127.0.0.1'
$env:TEST_DB_PORT = '43316' # Puerto del servidor aislado usado en esta revisión.
$env:TEST_DB_USER = 'root'
$env:TEST_DB_PASS = ''
php tests/unit/logistics_quantity_test.php
php tests/integration/logistics_persistence_test.php
php tests/integration/logistics_incidents_test.php
```

El puerto y las credenciales son del entorno de pruebas propio; no reutilizar los de producción. CI usa MariaDB 10.11 y ejecuta las tres suites junto a las verificaciones existentes. Los workers comprueban repeticiones, competencia de entregas y retorno contra reentrega de las mismas cantidades.

Para los tests originales que usan ZIP en XAMPP: `php -d extension=zip ...`. Para `validator_test.php`, usar una ruta de sesiones escribible, por ejemplo `php -d session.save_path=storage/sessions -d zend.assertions=1 -d assert.exception=1 tests/unit/validator_test.php`. Dos comparaciones de rutas de pruebas de branding se normalizaron para Windows/Linux; no se modificó el comportamiento de branding.

## Integración en el equipo

Comparar este contrato con el código de Alisson y los responsables de Inventarios/Ventas. Su rama remota consultada sigue en f38f4e8; integración/frontend-equipo avanzó a d0819d4. Este trabajo local permanece aislado y no modifica sus ramas. Consolidar una sola persistencia y su contrato; integrar Services, aplicar 006 y 007 en QA conforme a las versiones existentes y ejecutar el flujo/permisos. Reservas y salidas aún requieren integración real del Service; la persistencia no acredita un movimiento por recibir una cadena no vacía.

La evolución 007 sustituye el cierre por una referencia textual por comprobaciones estructuradas de cantidades e incidencias. Los intentos fallidos pueden existir sin líneas y las resoluciones permiten retornos parciales, pérdidas y reentregas. Leer también el contrato de evolución antes de integrar: `docs/contracts/logistics-incidents.md`. La guía actual tiene cantidades enteras; los despachos fraccionarios requieren coordinación con el módulo documental.

La [verificación ejecutada](logistics-validation.md) distingue los resultados anteriores de la validación de la evolución. No interpretar los 269 checks iniciales como prueba de incidencias que entonces no existían.
