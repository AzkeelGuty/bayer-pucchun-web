# Acuerdos para integrar Logística sin duplicar trabajo

Material para revisar entre Pedro, Alisson y los responsables de los módulos. No es una instrucción del ingeniero ni confirma acuerdos ajenos. Los casos están explicados en [GUIA_PARA_ALISSON.md](GUIA_PARA_ALISSON.md).

**Situación:** persistencia local basada en `schema_v2` de `integration/frontend-equipo` (`f38f4e8`), con migraciones 006 y 007. Esa estructura es provisional hasta validar los contratos de G4/G5/Inventarios y la integración actual. El esquema concreto no queda aprobado por el ingeniero sólo porque se reutilice el repositorio Bayer/Pucchún. La rama remota de integración avanzó a `d0819d4`; revisar cambios antes de consolidar.

## Ocho decisiones que necesitamos cerrar

| Decisión | Responsable con quien coordinar | Propuesta concreta | Evidencia para darla por cerrada |
| --- | --- | --- | --- |
| 1. Base común y plan de migración | G4 y equipo de integración; G5 para referencias maestras. | Comparar el esquema real actual con `schema_v2`. Acordar tipos de IDs, nombres, sedes, almacenes y migraciones ya aplicadas. Logística se adapta a la base compartida validada. | Rama/commit común y revisión de FKs/IDs; ejecución 006→007 sobre copia de QA sin importar 002 sobre datos existentes. |
| 2. Autorización y sede | G4 y bloque de funcionamiento. | Usuario/actor y sede vienen de sesión confiable. Definir permisos para preparar, despachar, registrar intento, gestionar/resolver caso, cerrar y corregir. Autorizar también reintentos de una clave existente. | Matriz de roles/permisos acordada y pruebas de acceso denegado, sede ajena y replay sin permiso vigente. Los nombres exactos de permisos aún no se han definido. |
| 3. Una persistencia y reparto | Pedro: persistencia; Alisson: funcionamiento; equipo de integración. | Comparar la propuesta inicial de Alisson con estas diez tablas. Elegir un solo esquema y API. `logistica_operaciones` controla comandos; las incidencias controlan problemas. No activar dos deduplicadores sobre el mismo comando. | Pedro confirmó el intercambio de responsabilidades. Falta cerrar el contrato técnico y el commit común de integración; esta entrega se publica en `feature/logistics-flow-pedro`. |
| 4. Producto, unidad, lote y origen | G5 para maestros; Inventarios G7 para lotes y relación lote/producto; Ventas y Compras cuando exista un origen relacionado. | Referenciar identidades reales y cantidades autorizadas; acordar `origen_ref`/`origen_linea_ref`, lote opcional y equivalencia de unidades. No sumar cantidades de unidades distintas ni crear otro maestro. | Ejemplo compartido de pedido con líneas/lotes/unidades y reglas de autorización de cantidades entre preparaciones. No inventar endpoints de Compras. |
| 5. Reserva y salida | Inventarios: Estefany y Aldair; bloque de funcionamiento. | Inventarios reserva y confirma salida. Services coordina los comandos y Logística conserva referencias. Compartir PDO/transacción si ambos trabajan en el mismo monolito. No hacer otro stock/Kardex. | Contrato de reserva/salida/cancelación, ejemplo de fallo y recuperación y prueba integrada que descuenta una sola vez. Si son servicios remotos, acordar recuperación explícita: no hay saga remota implementada. |
| 6. Retorno, pérdida y disposición | Inventarios y responsables de aprobación. | Adaptador `confirmedFact` consulta hechos granulares realmente confirmados y autorizados. Retorno es entrada; pérdida/disposición no repite el egreso original. ID único por aplicación/línea/caso, no sólo por cabecera documental. | Adaptador real y ejemplos de confirmación de 12 retornadas/8 perdidas; rechazo de referencia falsa, no autorizada, duplicada o de otra sede/lote. Acordar compensación de una confirmación aplicada. |
| 7. Guías y recepción de traslado | Responsable del CRUD de Guías, Inventarios y Logística. | Reutilizar Guías por ID/versión y snapshot; validar sede/cliente/producto/unidad/cantidad. Resolver enteros de Guías frente a tres decimales de Logística. Mantener bloqueada aceptación de traslado hasta tener recepción confiable. | Guía existente compatible para despacho y contrato de recepción en destino. No construir un segundo CRUD ni anunciar traslado operativo sin esa integración. |
| 8. Cierre, incidencias y correcciones | Bloque de funcionamiento; Inventarios/Ventas para compensación. | Cerrar sólo con P=0 en todas las líneas y sin casos bloqueantes. Reclamo aceptado es informativo; solicitud de devolución no es retorno. Anular únicamente intentos abiertos elegibles. | Casos normal, rechazo, ausencia, documentación y retorno/pérdida probados en QA. Acordar qué proceso nuevo atenderá devolución comercial posterior y correcciones con dependencias. |

## Lista para la reunión y la integración

- [ ] Comparar tablas/nombres y reglas del modelo de Alisson con la implementación, sin integrar dos variantes del mismo proceso.
- [x] Reparto confirmado por Pedro: Pedro desarrolla persistencia y Alisson desarrolla Services, Controllers y funcionamiento.
- [ ] Elegir rama/commit de integración actualizado; conservar una referencia de la entrega `f38f4e8` para comparar.
- [ ] Revisar sedes, almacenes, usuarios y maestros con G4/G5; registrar cualquier adaptación de FKs/tipos.
- [ ] Mantener un mecanismo de idempotencia por comando y una identidad granular única por confirmación externa.
- [ ] Acordar reserva/salida/retorno y pérdida con Inventarios, incluido fallo a mitad de la coordinación.
- [ ] Revisar compatibilidad de Guías; confirmar recepción de traslado antes de habilitar su aceptación.
- [ ] Definir permisos, obtención de actor/sede, formato de errores y conversión de hora local a UTC en Services.
- [ ] Ejecutar suites de persistencia y añadir pruebas de funcionamiento, acceso e integración con adaptadores reales.
- [ ] Probar en QA aislado: 100 aceptadas; 60/40 pendientes; 80/20 rechazadas; daño aceptado; FALLIDA sin líneas; reintento; retorno/pérdida; cierre documental; conflicto; corrección.
- [ ] Revisar actualización 006→007 con datos previos y backup del equipo. No forzar rollback eliminando incidencias ni confirmaciones.
- [ ] Documentar limitaciones pendientes y criterios de salida a producción; las pruebas locales no certifican el ERP completo.

## Borrador que Pedro puede copiar

> Ali, te comparto la guía y los casos de la persistencia local para compararlos con tu propuesta. La base se tomó de la rama que mencionaste, pero todavía debemos validar ese esquema con G4/G5 y la integración actual. El modelo usa diez tablas: proceso, incidencias, resoluciones, historial y un control técnico único de idempotencia; no crea otro stock ni otro CRUD de Guías.
>
> Lo más importante es distinguir rechazo, solicitud de devolución y retorno confirmado. Por ejemplo, 100 despachadas con 80 aceptadas y 20 rechazadas quedan con 20 pendientes; sólo una aceptación posterior, retorno confirmado o disposición autorizada las concilia. También cubre intento FALLIDA sin líneas y reclamos con toda la cantidad aceptada.
>
> Como acordamos el cambio, yo desarrollo persistencia y tú Services, Controllers y funcionamiento. Subo mi entrega a `feature/logistics-flow-pedro`. Propongo revisar el contrato y elegir una sola persistencia antes de integrar con la rama conjunta. Adjunto los casos para conectar ambos bloques. Falta el adaptador real de Inventarios y las pruebas de Services/permisos/integración.

Este texto es un borrador para revisión de Pedro. **No se ha enviado a Alisson ni a otro responsable.**
