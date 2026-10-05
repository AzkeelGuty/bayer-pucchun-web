# Contrato de persistencia de Logística — Pedro / Alisson

Implementación sobre `integration/frontend-equipo` en `f38f4e8`, publicada en `feature/logistics-flow-pedro` por indicación de Pedro. Pedro confirmó el intercambio: él desarrolla persistencia y Alisson desarrolla funcionamiento, Services y Controllers. El nombre de la rama se conserva; su contenido actual es persistencia. La base concreta sigue provisional hasta validarla con G4, G5 e Inventarios. Este contrato no introduce otro CRUD de Guías ni stock/Kardex. La evolución 007 y [su contrato](logistics-incidents.md) prevalecen sobre los campos y métodos iniciales descritos aquí para intentos, incidencias, balances, cierre y corrección.

## Reglas del almacenamiento

Una preparación origina como máximo un despacho; un pedido externo puede generar varias preparaciones. Un despacho tiene como máximo una guía asociada y varias entregas parciales. Las líneas mantienen su identidad: entrega_detalle → despacho_detalle → preparacion_detalle. El Service de Ventas/Logística coordina las cantidades autorizadas del pedido; el Repository no crea un módulo de pedidos ni interpreta disponibilidad de stock.

Estados de preparación: EN_PREPARACION, PREPARADA, CANCELADA. Estados de despacho: PENDIENTE, DESPACHADO, EN_TRANSITO, EN_RESOLUCION, ENTREGADO, CANCELADO, CERRADO_CON_INCIDENCIA. Intento de entrega: resultado ACEPTADA/PARCIAL/RECHAZADA/FALLIDA, vigente o anulado. Services decide las acciones; persistencia exige estado/versión esperados, referencias consistentes y correspondencia de cantidades y casos. El cierre se comprueba mediante closeDispatch y resoluciones estructuradas; no mediante una referencia textual.

Cantidad: DECIMAL(14,3), entrada string decimal o entero, nunca float; máximo 99999999999.999. No redondear. La aritmética utiliza milésimas enteras (PHP 64 bits). Detalles de preparación/despacho positivos; detalle de intento admite aceptación cero si tiene rechazo positivo. FALLIDA admite lista vacía sin una línea ficticia. No duplicar identidades dentro del payload. Lote pertenece al producto; almacén pertenece a la sucursal. Reservas/salidas se coordinan por el Service; retornos/disposiciones requieren el proveedor confiable de confirmaciones definido en el contrato 007.

## Contexto y resultados

Todas las escrituras reciben `array $context`: `sucursal_id` (int), `actor_id` (int), `idempotency_key` (ASCII imprimible sin espacios ni controles, máximo 128). Misma sede y clave + mismo comando/datos/actor devuelve el resultado ORIGINAL, aunque el agregado cambió después. Distinto contenido produce conflicto. El hash se calcula después de normalizar cantidades y ordenar identidades de detalles, e incluye acción, entidad, versiones y contexto. La operación se identifica ANTES de comprobar estado o versión actuales. Un fallo no conserva una operación incompleta.

Lecturas devuelven una cabecera plana con clave `details` para sus líneas, o null si no existe en la sede indicada. Cantidades y saldos son strings con tres decimales. `findDispatch` incluye cantidades entregadas, retornadas, con disposición final, pendientes y asignadas a incidencias. Escrituras devuelven resultados inmutables y versiones; los métodos nuevos se describen en el contrato 007 y la interfaz PHP.

## API que debe implementar la interfaz LogisticsRepositoryInterface

Métodos públicos (todos en el Repository propio, no heredar las reglas documentales de OperationalRepository):

```php
transaction(callable $operation): mixed;
createPreparation(array $header, array $details, array $context): array;
updatePreparation(int $id, int $expectedVersion, array $header, array $details, array $context): array;
transitionPreparation(int $id, int $expectedVersion, string $expectedState, string $newState, string $reason, array $context): array;
createDispatch(int $preparationId, array $header, array $context): array;
attachGuide(int $dispatchId, int $expectedVersion, int $guideId, int $guideVersion, array $context): array;
transitionDispatch(int $id, int $expectedVersion, string $expectedState, string $newState, array $references, array $context): array;
recordDelivery(int $dispatchId, int $expectedVersion, array $header, array $details, string $resultingState, array $context): array;
voidDelivery(int $deliveryId, int $expectedDeliveryVersion, int $expectedDispatchVersion, string $resultingState, string $reason, array $context): array;
findPreparation(int $id, int $branchId): ?array;
findDispatch(int $id, int $branchId): ?array;
findDelivery(int $id, int $branchId): ?array;
listPreparations(int $branchId, array $filters = [], int $limit = 50, int $offset = 0): array;
listDispatches(int $branchId, array $filters = [], int $limit = 50, int $offset = 0): array;
listDeliveries(int $dispatchId, int $branchId, int $limit = 50, int $offset = 0): array;
history(string $aggregateType, int $id, int $branchId, int $limit = 100, int $offset = 0): array;
```

`header` de preparación: almacen_id, tipo_origen (VENTA/TRASLADO), origen_ref (máximo 128), fecha (YYYY-MM-DD), destino (máximo 500), cliente_id nullable, almacen_destino_id nullable, reserva_ref (máximo 128). Detalles: origen_linea_ref (máximo 128), producto_id, unidad_id, lote_id nullable, cantidad. Actualizar preparación reemplaza detalles solamente EN_PREPARACION y sin despacho; no cambia su sede. Services coordina cualquier ajuste de reserva.

`header` de despacho: fecha, guia_id nullable y guia_version nullable. Se crea PENDIENTE desde PREPARADA; copia exactamente los detalles de preparación y congela sus referencias. La reserva proviene de la preparación. `attachGuide` solamente PENDIENTE; la guía pertenece a la misma sucursal y cliente cuando el origen es VENTA, conserva su versión y no puede estar asociada a otro despacho. No crear ni editar Guías.

`references` en transición de despacho conserva movimiento_salida_ref, motivo y resolucion_ref por compatibilidad, pero 007 rechaza los estados finales mediante este método genérico. DESPACHADO exige referencia a una salida, guía asociada en VALIDADO/PUBLICADO con versión vigente, y captura snapshot documental. EN_TRANSITO no solicita una segunda salida. CANCELADO solamente antes de la salida. Los cierres se comprueban en closeDispatch; una referencia textual no sustituye cantidades y resoluciones. La creación de una preparación requiere reserva_ref que aporta el Service/adaptador, no una reserva ficticia generada por persistencia.

`header` de intento y detalles se amplían en [el contrato 007](logistics-incidents.md): resultado, fecha/hora, motivo de fallo, aceptación/rechazo y asignaciones de incidencias. El Repository admite intentos desde EN_TRANSITO/EN_RESOLUCION y verifica el estado esperado contra los hechos guardados. Anular exige versiones y motivo, conserva los datos y rechaza cierres, dependencias o recepciones externas que necesitan compensación. Las recepciones de traslado permanecen bloqueadas hasta integrar una confirmación efectiva del módulo responsable; una cadena recepcion_ref no habilita la aceptación.

`transaction` respeta una transacción externa con savepoints; el callback no recibe argumentos. Los Repositories e Inventarios deben usar el mismo PDO para la coordinación local. Nunca hacer commit/rollback de la transacción propiedad del llamador. Estado, líneas, evento y resultado de idempotencia se guardan en la misma unidad. `LogisticsPersistenceException` expone un código estable (INVALID_INPUT, NOT_FOUND, CONFLICT, IDEMPOTENCY_CONFLICT, INVALID_STATE, QUANTITY_EXCEEDED, REFERENCE_CONFLICT). Autorización de usuario/roles/sede corresponde al Service antes de cualquier escritura o replay.

Ante contención de InnoDB (1205/1213), se devuelve CONFLICT. Un deadlock puede hacer que el motor revierta la transacción completa, incluso si era del llamador; el Service debe reiniciar la unidad completa y no continuar con un savepoint inexistente. Las solicitudes distintas que compiten sobre la misma versión pueden dar un éxito y un conflicto. No se reintentan automáticamente operaciones externas desde persistencia.

Filtros de `listPreparations`: estado, fecha_desde, fecha_hasta, origen_ref, cliente_id, almacen_id. Filtros de `listDispatches`: estado, fecha_desde, fecha_hasta, preparacion_id, guia_id. Paginación de 1 a 200, offset no negativo. `history` acepta agregado preparacion, despacho o entrega (minúsculas exactas), y puede consultar eventos de entrega por todos sus padres. Campos desconocidos se rechazan; todos los valores se envían como parámetros SQL.

Los límites de texto de la API se expresan en bytes UTF-8; los IDs se normalizan a enteros positivos. No aceptar estados, auditoría o columnas arbitrarias dentro de cabeceras/detalles. Estos campos los controla el método de persistencia correspondiente.

## Estructura inicial de 006 y evolución 007

006 crea ocho tablas InnoDB; 007 añade incidencias y resoluciones y evoluciona columnas de intentos, historial y cierre: diez tablas en total. La lista siguiente describe 006 antes de la evolución; consultar la migración 007 y su contrato para la estructura vigente. IDs nuevos BIGINT signed; sucursal/almacén/unidad INT signed compatibles; actor/producto/cliente/lote/guía BIGINT signed. FKs RESTRICT, índices por sede/estado/fecha, sin alterar el stock ni las tablas de Guías. Metadatos DATETIME(6) en UTC sin cambiar la sesión del caller; fecha/hora operativa separada. Inserciones SQL ajenas al Repository requieren sesión UTC si usan defaults CURRENT_TIMESTAMP.

- logistica_preparaciones: id, sucursal_id, almacen_id, tipo_origen, origen_ref, fecha, destino, cliente_id nullable, almacen_destino_id nullable, reserva_ref, estado, version unsigned default 1, created_by, updated_by, created_at, updated_at. UNIQUE(id,sucursal_id).
- logistica_preparacion_detalle: id, preparacion_id, posicion, origen_linea_ref, producto_id, unidad_id, lote_id nullable, cantidad. UNIQUE(preparacion_id,posicion), UNIQUE(id,preparacion_id). Permitir que una línea externa se divida por lote; Repository rechaza identidades repetidas exactas. FK compuesta lote/producto cuando la base ya la permite.
- logistica_despachos: id, preparacion_id UNIQUE, sucursal_id, fecha, guia_id nullable UNIQUE, guia_version nullable, guia_snapshot LONGTEXT nullable JSON válido, movimiento_salida_ref nullable, resolucion_ref nullable, estado, version unsigned default 1, created_by, updated_by, created_at, updated_at. UNIQUE(id,preparacion_id), UNIQUE(id,sucursal_id); FK compuesta preparación/sucursal.
- logistica_despacho_detalle: id, despacho_id, preparacion_id, preparacion_detalle_id UNIQUE, producto_id, unidad_id, lote_id nullable, cantidad. UNIQUE(id,despacho_id); FKs compuestas (despacho_id,preparacion_id) y (preparacion_detalle_id,preparacion_id) hacia sus padres correctos.
- logistica_entregas: id, despacho_id, sucursal_id, fecha, recibido_por, observaciones nullable, recepcion_ref nullable, anulada default 0, version unsigned default 1, anulada_by nullable, anulada_at nullable, motivo_anulacion nullable, created_by, created_at. UNIQUE(id,despacho_id); FK compuesta despacho/sucursal.
- logistica_entrega_detalle: id, entrega_id, despacho_id, despacho_detalle_id, cantidad. UNIQUE(entrega_id,despacho_detalle_id); FKs compuestas hacia entrega/despacho y detalle/despacho.
- logistica_operaciones: id, sucursal_id, clave ASCII binaria VARCHAR(128), accion VARCHAR(60), actor_id, request_hash CHAR(64) ASCII binaria, result_json LONGTEXT nullable JSON válido, created_at. UNIQUE(sucursal_id,clave). Insertar resultado dentro de la misma transacción; una fila incompleta nunca se confirma.
- logistica_historial: id, sucursal_id, preparacion_id nullable, despacho_id nullable, entrega_id nullable, operacion_id, accion VARCHAR(60), estado_anterior nullable VARCHAR(40), estado_nuevo nullable VARCHAR(40), motivo nullable TEXT, actor_id, created_at. Al menos una referencia a agregado. Eventos de entrega pueden incluir preparación/despacho/entrega para consultas trazables. No exponer update/delete de historial.

CHECK para cantidades válidas, versiones positivas, banderas coherentes y pares guia_id/guia_version. El Repository verifica condiciones relacionales y sumas (almacén-sucursal, guía-sucursal, lote-producto, asignaciones, confirmaciones y cierre). No introduce triggers ni otra máquina de permisos. 006 y 007 tienen snapshots equivalentes y rollback separado; el rollback 007 bloquea la pérdida de hechos nuevos incompatibles.

## Verificación requerida

Usar TestDatabase aislado (no .env): migración up/down, FKs y cantidades, referencias inválidas, rollback de cabecera/líneas/historial/operación, transacción del llamador, idempotencia normal y simultánea, replay después de transiciones posteriores, conflicto de payload/versión, guía duplicada, entregas parciales y sobreentrega concurrente, anulación preservando historial, aislamiento de sede y exactitud decimal. Services/seguridad y conexión real a Inventarios son entregables de Alisson/equipos, no quedan implementados por este contrato.
