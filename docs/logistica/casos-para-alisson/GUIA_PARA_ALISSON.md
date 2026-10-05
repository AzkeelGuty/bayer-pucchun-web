# Logística: guía de casos para conectar funcionamiento y persistencia

**Lectura de trabajo, 5 de octubre de 2026.** Esta guía explica lo implementado en persistencia y lo que Alisson construirá en funcionamiento. Pedro confirmó que intercambiaron sus responsabilidades: **Pedro desarrolla persistencia; Alisson desarrolla Services, Controllers, reglas, validaciones, permisos, rutas y transiciones**. La entrega se publica en `feature/logistics-flow-pedro` por indicación de Pedro.

**Base provisional:** la implementación usa `schema_v2` de `integration/frontend-equipo`, commit `f38f4e8`, base indicada en los mensajes de Alisson. No existe aquí confirmación de que el ingeniero, G4 o G5 aprobaran ese esquema concreto como definitivo del ERP. La rama remota de integración avanzó a `d0819d4`; la integración necesita comparación y validación del equipo. No importar el esquema 002 sobre una base compartida existente.

## Lo que conviene entender primero

La preparación organiza las líneas autorizadas; el despacho fija qué salió; la Guía ya existente documenta esa salida; cada intento registra lo sucedido al presentar la mercadería. Una incidencia registra el problema y una resolución registra cómo se atendió. **Rechazar, pedir devolución y recibir en almacén son tres hechos diferentes.**

El estado del despacho y el resultado de un intento son diferentes:

| Concepto | Ejemplo | Qué significa |
| --- | --- | --- |
| Estado del despacho | `EN_TRANSITO` | El envío aún tiene cantidades por atender y ningún caso bloqueante abierto. |
| Estado del despacho | `EN_RESOLUCION` | Existe un caso bloqueante o un cierre que debe verificarse explícitamente. |
| Resultado de un intento | `ACEPTADA` | En ese intento hubo aceptación y ningún rechazo; puede haber saldo para otra visita. |
| Resultado de un intento | `PARCIAL` | Ese intento combinó aceptación y rechazo. |
| Resultado de un intento | `RECHAZADA` | Se presentó una cantidad y toda fue rechazada. |
| Resultado de un intento | `FALLIDA` | No hubo entrega de cantidades; requiere tipo de fallo, motivo y contexto. |

Por ejemplo: despachar 100 y entregar 60 sin rechazo produce un intento `ACEPTADA`, pero el despacho sigue `EN_TRANSITO` con 40 pendientes. No indicar `PARCIAL` en ese payload: el Repository rechaza un resultado que contradiga sus cantidades.

## Cómo leer las cantidades

Cada balance corresponde a **una línea exacta del despacho**, con producto, unidad y lote. No sumar unidades incompatibles entre productos ni usar el total general para ocultar una línea pendiente.

```text
Q = A + R + F + P
B <= P
Libre = P - B
```

| Símbolo | Nombre para explicar | Fuente |
| --- | --- | --- |
| Q | Despachado | Cantidad fijada en el detalle del despacho. |
| A | Aceptado | Detalles de intentos vigentes; una resolución de reaceptación no lo suma otra vez. |
| R | Retornado confirmado | Resoluciones con un hecho granular de entrada confirmado por Inventarios. |
| F | Pérdida o disposición confirmada | Resoluciones con autorización y confirmación confiable; no producen un segundo egreso. |
| P | Pendiente de destino final | Lo que aún no está aceptado, retornado o dispuesto con confirmación. |
| B | Pendiente asignado a incidencias | Parte de P ya vinculada a casos cuantitativos abiertos. |
| Libre | Pendiente sin caso asignado | Lo que se puede aceptar o asignar sin identificar una incidencia previa. |

Los reportes informativos no asignan B. Un daño sobre 20 unidades ya aceptadas puede bloquear el cierre sin cambiar A ni P. Una observación libre no sustituye una cantidad, una causa ni una confirmación.

## Matriz de 15 casos: qué ocurre y qué queda registrado

En los casos 1–10 se parte de una línea de venta de 100 unidades, con Guía válida, salida y estado físico habilitado. Los casos son independientes salvo cuando se indica una continuación. Los saldos se muestran como `A / R / F / P / B`; Q continúa siendo 100.

| Caso | Antes | Evento | Registro que debe quedar | Saldo después | Estado del despacho |
| --- | --- | --- | --- | --- | --- |
| 01. Entrega normal | A=0, P=100; sin bloqueos. | El cliente acepta las 100. | Un intento vigente con receptor y un detalle de 100; cierre con actor, fecha e historial. | `100 / 0 / 0 / 0 / 0` | `ENTREGADO`, si todas las líneas están completas y no hay casos bloqueantes. |
| 02. Entrega por partes | A=0, P=100; sin incidencias previas. | Se presentan y aceptan 60; las otras 40 siguen pendientes, sin rechazo. | Un intento `ACEPTADA` por 60. No inventar una incidencia por las 40 ni registrar rechazo que no ocurrió. | `60 / 0 / 0 / 40 / 0` | `EN_TRANSITO`. Una visita posterior por 40 puede completar la entrega. |
| 03. Rechazo parcial | A=0, P=100. | Se presentan 100: acepta 80 y rechaza 20. | Intento `PARCIAL`, detalle 80/20 e incidencia cuantitativa de 20, creada en la misma transacción. | `80 / 0 / 0 / 20 / 20` | `EN_RESOLUCION`. Rechazo total 0/100 es la variante `RECHAZADA`, P=100 y B=100. |
| 04. Daño y rechazo de las mismas 20 | Continúa el caso 03 o ambas causas se conocen al registrar el intento. | Se comprueba que las 20 rechazadas estaban dañadas. | El mismo caso conserva dos causas: `RECHAZO` y `DANO`. Si se descubre después, una acción añade la causa y su evidencia. | Sigue `80 / 0 / 0 / 20 / 20` | `EN_RESOLUCION`. No abrir otra asignación de 20 que convierta el problema en 40. |
| 05. Aceptación con reclamo | A=0, P=100. | Acepta 100, pero reporta daño en 20 aceptadas. | Intento con aceptación 100 e incidencia `INFORMATIVA` de 20 afectadas dentro del mismo comando. | `100 / 0 / 0 / 0 / 0` | `EN_RESOLUCION`, pese a P=0. Resolver el reclamo y luego cerrar permite `ENTREGADO`. |
| 06. Nadie recibe | A=0, P=100. | Visita fallida por ausencia del receptor. | Intento `FALLIDA`, `tipo_fallo=AUSENCIA_RECEPTOR`, motivo y contexto; receptor nulo y detalles vacíos. Caso informativo de la visita. | `0 / 0 / 0 / 100 / 0` | `EN_RESOLUCION` mientras el caso bloquea. Reprogramar no entrega cantidades ni permite cerrar. |
| 07. Fallo documental | A=0, P=100. | No se recibe por documento faltante. | Intento `FALLIDA`, `tipo_fallo=PROBLEMA_DOCUMENTAL`, motivo y contexto; sin líneas. La incidencia conserva la causa documental. | `0 / 0 / 0 / 100 / 0` | `EN_RESOLUCION`. No etiquetar automáticamente como ausencia del receptor. |
| 08. Reintento sobre las 20 rechazadas | Continúa el caso 03: A=80, P=B=20. | En otro intento acepta 12 y rechaza otra vez las 8 restantes. | Nueva entrega con `aceptaciones_incidencias` 12 y `rechazos_incidencias` 8 apuntando al mismo caso. La aceptación crea su resolución vinculada. | `92 / 0 / 0 / 8 / 8` | `EN_RESOLUCION`. Aceptar luego esas 8 desde el caso deja A=100, P=B=0 y permite `ENTREGADO` sin otros bloqueos. |
| 09. Retorno y pérdida | Continúa el caso 03: A=80, P=B=20. | Inventarios confirma retorno de 12 y pérdida autorizada de 8. | Dos resoluciones parciales distintas, con identidades granulares únicas y snapshots confirmados. | `80 / 12 / 8 / 0 / 0` | Permanece abierto para cierre explícito. `closeDispatch` lo cierra en `CERRADO_CON_INCIDENCIA`. |
| 10. Devolución solicitada | Continúa el caso 03: A=80, P=B=20. | Se acuerda devolver las 20, pero aún no entran al almacén. | Acción `SOLICITAR_DEVOLUCION`, motivo y evidencia; ninguna resolución física ficticia. | Sigue `80 / 0 / 0 / 20 / 20` | `EN_RESOLUCION`. Pedir devolución no aumenta R ni el stock. |
| 11. Otra línea pendiente | Línea 1 ya completa; línea 2 aún pendiente. | Se solicita cierre porque la primera línea terminó. | La solicitud se rechaza; no se conserva un cierre incompleto. Los saldos de ambas líneas siguen disponibles. | Línea 1 P=0; línea 2 P>0. | Sigue abierto: `EN_TRANSITO` si no hay bloqueos, o `EN_RESOLUCION` si los hay. |
| 12. Documento pendiente con P=0 | A=100; caso documental informativo bloqueante abierto. | Se solicita cierre antes de resolver el documento. | Rechazo del cierre. Tras verificar el documento, resolución operativa con evidencia y cierre explícito. | `100 / 0 / 0 / 0 / 0` en ambas etapas. | Antes `EN_RESOLUCION`; después de resolver y cerrar, `ENTREGADO`. |
| 13. Solicitud repetida | Ya se confirmó un comando. | Llega la misma clave con el mismo comando, actor y datos originales. | Se devuelve el resultado original; no se repiten líneas, resoluciones ni historial. Datos distintos con la misma clave generan conflicto. | Sin cambio. | Sin nueva transición. Una repetición puede mostrar la versión histórica de su resultado. |
| 14. Dos operaciones simultáneas | A=80, P=B=20. | Dos usuarios intentan retornar y reaceptar las mismas 20 a la vez. | Un comando gana y otro obtiene `CONFLICT`; una sola resolución consume las 20. | Gana retorno: `80 / 20 / 0 / 0 / 0`; gana aceptación: `100 / 0 / 0 / 0 / 0`. | Retorno exige cierre posterior; aceptación puede completar en `ENTREGADO`. |
| 15. Corrección restringida | A=60, P=40 en despacho abierto, sin dependencias ni recepción externa; o un despacho ya cerrado. | Se detecta digitación errónea. | Abierto y elegible: anulación con motivo conserva el intento y excluye su aceptación del balance; corrección es otro intento. Cerrado/dependiente: se rechaza la anulación ordinaria. | Abierto al anular los 60: `0 / 0 / 0 / 100 / 0`; cerrado: sin cambio. | Abierto sigue operativo. Cerrado no se reabre; compensación/devolución posterior es otro proceso todavía fuera del alcance. |

**Matices de los casos 06–07:** `recordIncidentAction` con `REPROGRAMAR` conserva una gestión pendiente. `resolveIncident` con `RESOLUCION_OPERATIVA` puede cerrar ese caso informativo cuando hay evidencia suficiente; si quedan cantidades por atender y ningún bloqueo, el despacho pasa a `EN_TRANSITO`. Ninguna de esas acciones altera A/R/F/P ni prueba una nueva visita.

## Matriz de implementación y evidencia

S = bloque de funcionamiento (Services). R = Repository. Las copias de las pruebas citadas están en `referencias/tests/integration`. “Directa” significa que localicé el escenario o sus aserciones en el archivo; “derivado” significa ejemplo construido con las reglas vigentes, sin afirmar que esos números exactos se ejecutaron. La ejecución local histórica está documentada en `docs/handoffs/logistics-validation.md`: 545 comprobaciones de Logística. Esta guía no vuelve a ejecutar las suites ni certifica la futura integración.

| Caso | Responsabilidad S | Método y protección R | Evidencia localizada |
| --- | --- | --- | --- |
| 01 | Autorizar entrega/receptor, obtener líneas y preparar estado esperado. | `recordDelivery`: aceptación, balance de todas las líneas, bloqueo y cierre normal atómicos. | `logistics_incidents_test.php:338` prueba completar 100 en reintentos; `logistics_persistence_test.php:250` prueba cierre normal con todas las líneas. Entrega única 100 es ejemplo derivado de esas reglas. |
| 02 | Informar que quedan 40 por atender; no confundir parcialidad del envío con resultado `PARCIAL`. | `recordDelivery` con estado esperado `EN_TRANSITO`; `findDispatch` devuelve saldos. | `logistics_persistence_test.php:238` prueba aceptación parcial sin rechazo con otros valores y P exacto. Ejemplo 60/40 derivado. |
| 03 | Capturar aceptación, rechazo y motivo real; no anunciar retorno. | `recordDelivery` crea intento y caso cuantitativo juntos. | Directa: `logistics_incidents_test.php:174` (80/20), `:269` (rechazo total). |
| 04 | Identificar que daño/rechazo son la misma mercadería; recoger evidencia. | `recordDelivery` con causas del rechazo; después `recordIncidentAction` con `AGREGAR_CAUSAS`. | Directa: `logistics_incidents_test.php:186` un caso dos causas; `:243` añadir causa sin duplicar cantidad. |
| 05 | Capturar el reclamo al registrar aceptación; atenderlo antes de solicitar cierre. | `recordDelivery` con `incidencias_informativas`; `resolveIncident` operativo; `closeDispatch`. | Directa: `logistics_incidents_test.php:361`, cantidad afectada 20; cierre prematuro bloqueado. |
| 06 | Exigir causa/contexto de visita, permitir ausencia de receptor y líneas vacías; coordinar agenda. | `recordDelivery` con `FALLIDA`; `recordIncidentAction` o `resolveIncident` operativo según el hecho. | Directa: `logistics_incidents_test.php:280`; rechaza falta de motivo/tipo y cierre aunque se haya reprogramado. |
| 07 | Elegir la causa documental real, sin inferir ausencia. | `recordDelivery` preserva `tipo_fallo` en el caso y no inventa cantidades. | Directa: `logistics_incidents_test.php:302`; sin líneas ni causa de ausencia inventada. |
| 08 | Referir el caso existente para lo aceptado y lo rechazado; no generar un caso nuevo para la misma cantidad. | `recordDelivery` comprueba saldo del caso, suma de asignaciones y aceptación/rechazo del intento. | `logistics_incidents_test.php:317` prueba rechazo repetido de 20, luego aceptación de 12 y luego 8. El combinado 12 aceptadas/8 rechazadas en un mismo reintento es derivado del PHP, no una prueba exacta existente. |
| 09 | Coordinar Inventarios y aprobación de pérdida; pedir cierre sólo tras conciliación. | `resolveIncident` recupera confirmaciones confiables; `closeDispatch` verifica cantidades y casos. | Directa: `logistics_incidents_test.php:204`, retorno 12 + pérdida 8 y cierre excepcional. Adaptador usado sólo de pruebas. |
| 10 | Registrar la gestión, coordinar recepción y esperar el hecho confirmado. | `recordIncidentAction` no crea R/F ni resoluciones. | Directa: `logistics_incidents_test.php:243`, solicitud sin cambio de balance ni resolución. Escenario 80/20 es derivado; prueba usa A=0/P=100/B=20. |
| 11 | Mostrar pendientes por línea y evitar ofrecer un cierre que no corresponde. | `closeDispatch` verifica P=0 en cada línea; `recordDelivery` rechaza expectativa final prematura. | Directa: `logistics_persistence_test.php:245` rechaza `ENTREGADO` incompleto y `:250` completa todas las líneas. Bloqueo específico de `closeDispatch` por segunda línea es derivado de su implementación. |
| 12 | Verificar documento y guardar evidencia; P=0 no habilita botón de cierre por sí solo. | `createIncident`, `recordDelivery`, `resolveIncident` operativo y `closeDispatch`. | Directa: `logistics_incidents_test.php:390`, problema documental sin cantidad, aceptación 100 y cierre protegido. |
| 13 | Autorizar también el replay, conservar el payload/clave original ante fallo de conexión. | Cada escritura usa `logistica_operaciones`; hash, resultado original y transacción. Confirmación externa también tiene unicidad propia. | Directa: `logistics_incidents_test.php:211` replay y `:229` referencia externa duplicada con otra clave; `logistics_persistence_test.php:365` comandos repetidos concurrentes. |
| 14 | Refrescar estado y resolver el conflicto; no repetir a ciegas un efecto externo. | `resolveIncident`/`recordDelivery`: bloqueo del despacho, versiones y consumo único. | Directa: `logistics_incidents_test.php:473`, workers independientes, un éxito/un conflicto y balance 100. |
| 15 | Autorizar motivo/corrección y verificar elegibilidad; diseñar compensación externa aparte cuando corresponda. | `voidDelivery` preserva datos y comprueba estado, versiones y dependencias; `recordDelivery` registra reemplazo. | Directa: `logistics_persistence_test.php:257` corrección abierta de 6/10; `logistics_incidents_test.php:336` dependencia bloqueada y `:227` cerrado bloqueado. Ejemplo abierto 60/100 derivado. |

## Tres entradas que evitan confusiones

Son fragmentos orientativos de arrays para el Service, no rutas HTTP implementadas. Los IDs son ficticios. El Service obtiene actor y sede de la sesión autorizada, y los relaciona con el recurso real. Debe usar versiones leídas y una clave por comando; no copiar el contexto de seguridad enviado por el cliente.

**80 aceptadas / 20 rechazadas por daño:**

```json
{
  "despacho_detalle_id": 51,
  "cantidad": "80.000",
  "cantidad_rechazada": "20.000",
  "motivo_rechazo": "Envases dañados",
  "causas_rechazo": ["RECHAZO", "DANO"]
}
```

Pasar a `recordDelivery` con cabecera de fecha/receptor y estado esperado `EN_RESOLUCION`. El caso cuantitativo lo crea el Repository; no llamar además a `createIncident` por esas mismas 20.

**Fallo sin receptor ni cantidades:**

```json
{
  "fecha": "2026-10-05",
  "resultado": "FALLIDA",
  "tipo_fallo": "AUSENCIA_RECEPTOR",
  "recibido_por": null,
  "motivo_fallo": "No había una persona autorizada para recibir",
  "observaciones": "Visita registrada por el transportista"
}
```

Los detalles son `[]`. Pasar a `recordDelivery` con estado esperado `EN_RESOLUCION`. Para fallo documental usar `tipo_fallo=PROBLEMA_DOCUMENTAL` y su motivo real. No enviar `recepcion_ref` ni una línea cero para representar una visita fallida.

**Reintento con 12 aceptadas y las mismas 8 rechazadas:**

```json
{
  "despacho_detalle_id": 51,
  "cantidad": "12.000",
  "cantidad_rechazada": "8.000",
  "aceptaciones_incidencias": [{"incidencia_id": 7, "cantidad": "12.000"}],
  "rechazos_incidencias": [{"incidencia_id": 7, "cantidad": "8.000"}],
  "motivo_rechazo": "El cliente mantiene el rechazo de esas ocho unidades"
}
```

La suma sobre el caso 7 es 20 y no supera su saldo inicial. El nuevo intento es `PARCIAL`; A aumenta a 92, P/B bajan a 8. La resolución de 12 se crea vinculada a esa aceptación. No invocar `resolveIncident` con `ACEPTACION_EN_REINTENTO`: ese tipo sólo se crea dentro de `recordDelivery`.

## Mapa mínimo de datos para trabajar

| Fuente | Dato necesario | Quién lo conserva o valida |
| --- | --- | --- |
| Plataforma G4 | Actor autenticado, permisos, sedes autorizadas y PDO/transacción común. | Service autoriza; Repository limita referencias/lecturas a sede y registra actor. |
| Maestros G5 e Inventarios G7 | G5: identidades de producto, unidad y cliente. Inventarios: lotes y su relación con el producto. | Cada módulo define sus datos; Logística los referencia y conserva identidad por línea. |
| Ventas o sistema de origen | `origen_ref`, `origen_linea_ref`, cantidad autorizada, cliente y destino. | Service coordina el origen; preparación guarda referencias, sin crear otro módulo de pedidos. |
| Inventarios | Reserva y salida reales; confirmación granular de retorno/pérdida/disposición. | Service coordina reserva/salida. Adaptador confiable consulta el hecho; Repository valida identidad/cantidad y guarda snapshot. |
| Guías existente | ID, versión, sede, cliente, estado y contenido compatible con despacho. | `attachGuide` asocia; al despachar se captura snapshot. El CRUD de Guías sigue siendo el existente. |
| Operación logística | Líneas, aceptación/rechazo, receptor real, causa, evidencia y fecha/hora. | Service captura/valida propósito; Repository aplica integridad y persiste de forma atómica. |

Las diez tablas propias implementadas son:

1. `logistica_preparaciones` y `logistica_preparacion_detalle`: qué se prepara.
2. `logistica_despachos` y `logistica_despacho_detalle`: qué sale y con qué Guía.
3. `logistica_entregas` y `logistica_entrega_detalle`: intentos y cantidades aceptadas/rechazadas.
4. `logistica_incidencias`: caso y cantidad afectada, cuando corresponde.
5. `logistica_incidencia_resoluciones`: hechos que atienden el caso.
6. `logistica_operaciones`: deduplicación técnica de comandos.
7. `logistica_historial`: eventos trazables con operación, actor y referencias.

No equivalen literalmente a los nombres `*_cabecera` propuestos por Alisson. Hay que comparar esquemas antes de integrar; no crear dos conjuntos de tablas para representar el mismo flujo. Una incidencia explica qué problema ocurrió; una operación técnica evita repetir un comando. No se sustituyen entre sí.

## Recorrido recomendado para implementar el bloque de funcionamiento

1. **Cerrar el contrato y la base.** Revisar esta guía, el contrato 007 y la interfaz con Pedro, responsable de persistencia. Comparar la propuesta inicial de Alisson y elegir un modelo común y un único mecanismo de idempotencia. Coordinar el commit de integración actualizado; el reparto confirmado es Pedro en persistencia y Alisson en funcionamiento.
2. **Autorizar antes de leer o escribir.** Resolver usuario/sede desde sesión y comprobar permiso sobre la acción y recurso. El Repository recibe contexto confiable; no valida roles por sí solo ni convierte un `actor_id` enviado por el navegador en permiso.
3. **Preparar con datos del origen e Inventarios.** Obtener cantidad autorizada, identidad de líneas y reserva real; llamar `createPreparation`. Ajustes usan `updatePreparation` sólo mientras está editable. `transitionPreparation` marca `PREPARADA` cuando la preparación corresponde.
4. **Crear y documentar el despacho.** `createDispatch` copia lo preparado. Asociar una Guía existente con `attachGuide`, obtener salida real y usar `transitionDispatch` para `DESPACHADO` y después `EN_TRANSITO`. Inventarios es el único responsable de stock/Kardex; no descontar de nuevo por tránsito, aceptación o pérdida.
5. **Registrar intentos completos.** Elegir `recordDelivery`; enviar las cantidades y causas reales juntas, incluida una incidencia informativa si se reporta al aceptar. `resultingState` es una expectativa que el Repository contrasta con los hechos, no una forma de forzar el estado.
6. **Atender el caso correcto.** Gestiones usan `recordIncidentAction`; retornos/pérdidas/disposición usan `resolveIncident` con confirmaciones confiables; reaceptación vuelve a `recordDelivery` indicando el caso. Mostrar saldo desde `findDispatch`, `findIncident`, `listIncidents` e historial; no recalcular saldos con floats en el navegador.
7. **Cerrar cuando todas las líneas y casos lo permiten.** Cierre normal de aceptación total puede ser automático. Cierre excepcional o posterior a resolver un reclamo utiliza `closeDispatch`; no `transitionDispatch` a un estado final.
8. **Manejar errores sin efectos dobles.** `CONFLICT`: refrescar versiones y presentar el cambio al usuario. Fallo de conexión: repetir mismo comando con clave y payload originales tras autorizar. `IDEMPOTENCY_CONFLICT`: no cambiar payload debajo de esa clave. `QUANTITY_EXCEEDED`: corregir cantidades/asignaciones. `REFERENCE_CONFLICT`: revisar identidades y fuente confiable. Si un deadlock revierte la transacción del llamador, reiniciar la unidad completa; no continuar un savepoint perdido.
9. **Verificar seguridad e integración.** El bloque de funcionamiento debe añadir pruebas de permisos/sede, contratos reales y transacciones de reserva/salida. Las pruebas locales de persistencia no cubren esos Services todavía inexistentes.

## Límites que debemos comunicar al integrar

- El proveedor de Inventarios de pruebas es sólo un fixture. Falta el adaptador real; la resolución externa se rechaza sin él. Una referencia escrita por el cliente no demuestra una entrada ni una autorización.
- La aceptación de traslados está bloqueada hasta integrar recepción confirmada en almacén destino. No presentar traslado como flujo productivo terminado.
- Logística admite tres decimales; el CRUD de Guías revisado valida enteros. Acordar esa compatibilidad con el módulo responsable antes de habilitar fracciones.
- El Service debe coordinar reserva/salida con Inventarios. En un monolito se recomienda PDO/transacción común; una comunicación remota exige un diseño adicional para recuperar fallos. No se implementó una saga remota aquí.
- Correcciones con dependencias, confirmaciones externas o cierres requieren compensación explícita todavía fuera del contrato. No borrar resoluciones ni reabrir cierres para simularla.
- La política actual bloquea los casos salvo el retraso informativo con causas exclusivamente `RETRASO`. Cambiarla exige acuerdo y actualización de reglas/pruebas; no aceptar un campo cliente `bloqueante=false` para evitar la protección.

## Referencias ejecutables

- [Contrato vigente 007](referencias/docs/contracts/logistics-incidents.md).
- [Contrato base 006, subordinado a 007](referencias/docs/contracts/logistics-persistence.md).
- [Interfaz de persistencia](referencias/app/Contracts/LogisticsRepositoryInterface.php).
- [Repository implementado](referencias/app/Repositories/LogisticsRepository.php).
- [Proveedor confiable de Inventarios](referencias/app/Contracts/LogisticsInventoryConfirmationProviderInterface.php).
- [Pruebas de incidencias](referencias/tests/integration/logistics_incidents_test.php).
- [Pruebas de persistencia](referencias/tests/integration/logistics_persistence_test.php).
- [Informe de ejecución local](referencias/docs/handoffs/logistics-validation.md).
