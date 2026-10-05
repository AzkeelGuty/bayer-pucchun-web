# Contrato de evolución: intentos, incidencias y resoluciones

La migración 007 evoluciona 006 y prevalece sobre el contrato inicial para entregas, balances, anulación y cierre. La interfaz PHP y sus normalizadores son la referencia ejecutable. No se implementan Controllers, Services, permisos, stock/Kardex ni otro CRUD de Guías.

## Modelo y responsabilidad

Diez tablas propias: las ocho de 006 más `logistica_incidencias` y `logistica_incidencia_resoluciones`. El registro `logistica_operaciones` es el único mecanismo de idempotencia por comando; el historial conserva eventos y sus referencias a casos/resoluciones. No activar en paralelo un segundo deduplicador de creación en cabeceras.

Services autoriza al actor y la sede antes de cualquier comando o replay, decide las acciones permitidas y coordina Inventarios/Ventas. Persistencia comprueba versiones, identidad, cantidades, referencias, balance y cierre, bajo bloqueo del despacho. Las restricciones de suma entre filas se verifican en el Repository, no por un CHECK escalar.

Pedro confirmó el intercambio de responsabilidades: él desarrolla esta persistencia y Alisson desarrolla Services, Controllers y funcionamiento. La entrega se publica en `feature/logistics-flow-pedro`; el contrato técnico y la base compartida siguen sujetos a validación del equipo antes de integrar.

## Balances

Por detalle exacto de despacho (producto, lote y unidad):

```text
Q = A + R + F + P
0 <= B <= P
pendiente_libre = P - B
```

Q es despachado; A es aceptación vigente; R es retorno confirmado por Inventarios; F es pérdida/disposición confirmada; P es pendiente. B es la cantidad aún asignada a incidencias cuantitativas. Informativas, retrasos y problemas documentales no se suman al balance. Daño y rechazo de una misma cantidad son causas del mismo caso cuantitativo, sin otra asignación.

Una nueva aceptación sin origen de incidencia consume pendiente libre. Una aceptación de cantidad asignada identifica su caso; la entrega y su resolución se guardan juntas. Por detalle aceptado, la suma de sus resoluciones de reentrega no supera esa aceptación; cada caso aporta como máximo su saldo. El resto aceptado debe caber en el saldo libre anterior al comando. Una resolución de reentrega no incrementa A otra vez.

## Constructor e integración de Inventarios

```php
new LogisticsRepository(?PDO $pdo = null,
    ?LogisticsInventoryConfirmationProviderInterface $inventory = null);
```

El proveedor es un adaptador confiable del módulo de Inventarios. `confirmedFact(['sistema' => ..., 'id' => ...])` consulta un hecho confirmado granular; no debe copiar un JSON de la solicitud ni devolver una aprobación ficticia. No se incluye un adaptador real de Inventarios ni un mock de producción. Sin proveedor, las resoluciones que necesitan confirmación externa se rechazan.

La respuesta exige la misma identidad solicitada, `confirmado=true`, `autorizado=true`, tipo/dirección/finalidad, sede/almacén, despacho/incidencia/línea, producto/unidad/lote, cantidad exacta, evidencia y autorización cuando corresponde a pérdida/disposición. El proveedor debe comprobar el efecto y el permiso en el módulo responsable. Una salida no sirve como confirmación de retorno. Las cantidades y referencias deben coincidir con el caso y no consumir dos veces la misma confirmación.

Cada par sistema/id identifica una aplicación granular indivisible, con unicidad global respecto a las sedes. Un documento con varias líneas o casos exige referencias de detalle/aplicación separadas. No reutilizar su cabecera como identidad de todos los retornos. Confirmaciones aplicadas son inmutables; revertirlas requiere una compensación coordinada.

La salida y la reserva del contrato inicial siguen siendo referencias que el Service debe obtener de Inventarios y coordinar atómicamente. Este proveedor no reemplaza los casos de uso reales de reserva/salida. Ninguna entrega ni pérdida genera un segundo descuento del almacén origen.

## Intentos de entrega

`recordDelivery` mantiene su firma. `resultingState` admite EN_TRANSITO, EN_RESOLUCION y ENTREGADO; es una expectativa que el Repository valida, no una autorización para saltarse los datos.

Cabecera de intento: fecha, fecha_operativa opcional, recibido_por nullable, observaciones, recepcion_ref, resultado opcional, motivo_fallo y tipo_fallo cuando FALLIDA. Puede incluir incidencias cualitativas sin cantidad para registrarlas atómicamente. fecha_operativa explícita se expresa en UTC con formato YYYY-MM-DD HH:MM:SS.ffffff; el Service convierte la hora local. Cuando no se especifica hora, se conserva la fecha declarada a medianoche para compatibilidad; no representa una hora de visita conocida.

El Repository deriva ACEPTADA/PARCIAL/RECHAZADA de cantidades. FALLIDA se solicita explícitamente, exige motivo, contexto y tipo_fallo estructurado y admite detalles vacíos; no inventar líneas con cantidad cero. No inferir AUSENCIA_RECEPTOR si el intento falló por documentación u otra causa. El receptor es obligatorio si existe aceptación.

Detalles:

- despacho_detalle_id;
- cantidad: aceptación, compatible con el contrato inicial;
- cantidad_rechazada: rechazo, cero por defecto;
- aceptaciones_incidencias: asignaciones `{incidencia_id, cantidad}` del origen de lo aceptado;
- rechazos_incidencias: cantidades presentadas y rechazadas otra vez desde casos existentes;
- motivo_rechazo y causas_rechazo: evidencia/códigos del rechazo nuevo.
- incidencias_informativas: reportes sobre cantidades aceptadas en ese mismo intento, con tipo, cantidad afectada, causas, motivo, evidencia y responsable_id opcional. Modo, línea, intento y condición bloqueante se asignan en el servidor.

Cada línea tiene aceptación/rechazo no negativos y al menos una cantidad positiva. No repetir línea. Asignaciones de casos deben pertenecer a esa línea y respetar su saldo; la cantidad no asignada debe caber en saldo libre. El rechazo nuevo abre automáticamente su caso cuantitativo en la misma transacción. Rechazar otra vez una cantidad ya asignada añade evidencia al caso sin asignarla otra vez.

Un intento FALLIDA puede abrir una incidencia informativa bloqueante por ausencia. Resolver la reprogramación no consume P ni da por entregada mercadería. Los rechazos parciales y los reintentos se conservan como intentos diferentes.

Una aceptación completa con veinte unidades dañadas se registra con su incidencia informativa dentro del mismo comando, antes de calcular el cierre. No cerrar primero para intentar añadir el reclamo después, ni reemplazar su cantidad estructurada por una observación. El reporte no supera lo aceptado en ese detalle y no asigna B.

## Casos de incidencia y resolución

```php
createIncident(int $dispatchId, int $expectedDispatchVersion,
    array $incident, array $context): array;
resolveIncident(int $incidentId, int $expectedIncidentVersion,
    int $expectedDispatchVersion, array $resolution, array $context): array;
findIncident(int $id, int $branchId): ?array;
listIncidents(int $dispatchId, int $branchId,
    array $filters = [], int $limit = 50, int $offset = 0): array;
closeDispatch(int $dispatchId, int $expectedVersion,
    string $expectedState, string $reason, array $context): array;
recordIncidentAction(int $incidentId, int $expectedIncidentVersion,
    int $expectedDispatchVersion, array $action, array $context): array;
```

Incidencia: modo CUANTITATIVA/INFORMATIVA; tipo FALTANTE, RECHAZO, DANO, PERDIDA_EN_INVESTIGACION, AUSENCIA_RECEPTOR, RETRASO o PROBLEMA_DOCUMENTAL; detalle/entrega cuando corresponda; cantidad, causas, motivo, evidencia y responsable_id opcional (por defecto actor). Una cuantitativa es bloqueante y asigna pendiente libre; una informativa no asigna saldo. Las operativas sin cantidad se representan como informativas. La clasificación acordada en este contrato deja sin bloqueo solamente el retraso informativo con causas exclusivamente RETRASO; otros casos bloquean. No aceptar un booleano de cliente para evitar el cierre protegido. Services valida la clasificación antes de llamar; modificar esta política exige cambiar el contrato y sus pruebas.

Resoluciones cuantitativas externas: RETORNO_CONFIRMADO, PERDIDA_CONFIRMADA y DISPOSICION_FINAL_CONFIRMADA. Entrada con tipo, cantidad, motivo/evidencia, fecha_operativa y `confirmacion={sistema,id}`. Datos confirmados se recuperan del proveedor, se verifican y se conservan como snapshot. Las pérdidas/disposiciones requieren aprobación/evidencia trazables.

ACEPTACION_EN_REINTENTO se crea exclusivamente dentro de `recordDelivery`, vinculada al detalle vigente de aceptación. No aceptar una resolución aislada que introduzca cualquier ID de entrega. RESOLUCION_OPERATIVA resuelve un caso informativo con motivo/evidencia sin alterar A/R/F.

Un caso cuantitativo continúa abierto mientras tenga saldo. Solicitar devolución, investigar o reprogramar son acciones, no resoluciones finales. Las resoluciones se añaden; no se exponen actualización/borrado. Las consultas devuelven cantidades exactas y las resoluciones necesarias para reconstruir el saldo.

recordIncidentAction conserva acciones pendientes y nuevas causas del mismo caso, sin reasignar ni resolver sus cantidades: AGREGAR_CAUSAS, SOLICITAR_DEVOLUCION, REPROGRAMAR e INVESTIGAR. Entrada: tipo, motivo, evidencia y causas cuando se agregan. Exige versiones del caso/despacho. Agregar una causa bloqueante a un retraso actualiza su condición y el estado operativo. No habilita cierre ni aumenta stock. Las acciones se consultan en history('incidencia', id, sede), decodificando payload_json; findIncident devuelve causas/evidencia y resoluciones. El historial completo del despacho conserva también esos eventos.

## Cierre y corrección

Estados adicionales: EN_RESOLUCION mientras exista un asunto bloqueante en tratamiento; CERRADO_CON_INCIDENCIA para cierre con retorno o disposición. ENTREGADO indica aceptación completa sin bloqueos y registra cerrado_at/by. Un intento normal que completa todas las líneas puede finalizar automáticamente en ENTREGADO; el cierre excepcional siempre pasa por `closeDispatch`.

`closeDispatch` exige salida/guía confirmadas, P=0 en cada línea y ningún caso bloqueante pendiente. Deriva ENTREGADO si todo fue aceptado o CERRADO_CON_INCIDENCIA si existe retorno/disposición. Conserva evidencia de balances y actor en historial. `transitionDispatch` rechaza cambios genéricos a estados finales: una referencia textual no demuestra resolución.

Anulación ordinaria limitada a intentos de venta abiertos sin incidencias/resoluciones dependientes ni recepción externa que requiera compensación. Un despacho final no se reabre con `voidDelivery`. Los casos con dependencias requieren otro proceso explícito de corrección/compensación, todavía fuera de este contrato. Una devolución comercial posterior a entrega cerrada es un flujo nuevo vinculado, no una anulación del pasado.

La aceptación de traslados está bloqueada hasta definir e integrar recepción confirmada en almacén destino. Su preparación y referencias se conservan, pero no presentar ese recorrido como operativo en producción.

## Migración y verificación

Aplicar 006 y después 007 en QA con el procedimiento del equipo. Si 006 ya existe, aplicar solamente 007. Snapshot 007 es equivalente al paso de migración, no un segundo paso que deba ejecutarse otra vez. 007 comprueba datos previos y rechaza cierres excepcionales sin conciliación; no fabrica incidencias a partir de observaciones. El rollback 007 se niega a perder hechos nuevos incompatibles con 006. DDL no es transaccional: usar el procedimiento de respaldo/mantenimiento del equipo.

Todas las pruebas utilizan bases temporales generadas sin .env. Ejecutar suites de cantidades, persistencia e incidencias. El informe de validación registra las comprobaciones realmente ejecutadas y las integraciones que aún necesita completar el equipo.
