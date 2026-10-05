# Guion para explicarlo en cinco minutos

Material para Pedro y Alisson. Describe la persistencia local; la integración y la base común del ERP siguen por validar.

## Primero: entender los cuatro registros

**Preparación** organiza qué se va a atender. **Despacho** fija qué salió. La **Guía existente** documenta la salida; no es otro estado ni otro CRUD. **Entrega** registra cada intento, incluyendo intentos fallidos. Las incidencias conservan problemas y las resoluciones conservan cómo se atendieron.

El estado del despacho no es el resultado del intento. Una visita puede ser FALLIDA y dejar el despacho EN_RESOLUCION. Una aceptación de 60 sin rechazo produce un intento ACEPTADA y mantiene el despacho EN_TRANSITO con 40 pendientes.

## Después: seguir las mismas 100 unidades

Mostrar las láminas **02 → 06 → 07 → 08** como rutas alternativas, no como una sola secuencia obligatoria.

> Salieron 100. El cliente acepta 80 y rechaza 20. Esas 20 siguen pendientes: aún no han regresado al almacén.

Si un nuevo intento acepta 12 y rechaza las otras 8, quedan **92 aceptadas y 8 pendientes**. Se usa la misma incidencia, sin duplicar mercadería.

Si se solicita devolver las 20, sigue habiendo **80 aceptadas y 20 pendientes**. La solicitud conserva la gestión; no prueba una entrada física.

Si Inventarios confirma retorno de 12 y pérdida autorizada de 8, quedan **80 aceptadas + 12 retornadas + 8 pérdidas confirmadas = 100**. Se comprueba cada línea y luego se solicita cierre en CERRADO_CON_INCIDENCIA. Estas confirmaciones se simulan en las pruebas locales; falta conectarlas al módulo real.

## Dos confusiones que las imágenes resuelven

En la lámina **03**, daño y rechazo de las mismas 20 son dos causas sobre una cantidad, no 40 unidades.

En la lámina **04**, el cliente acepta las 100, pero reclama daño en 20 que ya están dentro de esas 100. El pendiente es cero; el reclamo puede bloquear el cierre. Hay que atenderlo con evidencia y después comprobar el cierre.

Mostrar la lámina **05** para ausencia del receptor: se conserva una visita FALLIDA sin líneas ficticias. Reprogramar otra visita no acepta, devuelve ni pierde cantidades.

## Las reglas para permitir cierre

La lámina **09** presenta dos ejemplos independientes. En uno falta otra línea; en otro toda la mercadería está aceptada pero falta resolver un documento. Por eso se necesitan dos controles:

1. Pendiente cero en **cada línea**, con su producto, unidad y lote.
2. Ningún caso bloqueante abierto.

Resolver un caso no significa necesariamente cerrar el despacho. Después de una resolución excepcional o de un reclamo con saldo cero se usa closeDispatch. La aceptación completa normal puede cerrar dentro de recordDelivery si no hay bloqueos.

## Quién hace qué y cómo evitar dobles efectos

Alisson desarrolla el bloque de **funcionamiento**: autoriza al usuario, valida propósito, captura datos y coordina Inventarios. Pedro desarrolla **persistencia**: el Repository guarda cada operación de forma íntegra, controla cantidades, versiones, repeticiones e historial. Pedro confirmó que intercambiaron sus responsabilidades; la publicación solicitada es `feature/logistics-flow-pedro`.

La lámina **10** muestra dos protecciones diferentes: una solicitud idéntica devuelve la respuesta original, y dos comandos concurrentes no pueden consumir las mismas 20. “Retornar” exige confirmación de Inventarios; “reentregar” exige aceptación real del cliente. Programar no consume saldo.

Inventarios conserva stock/Kardex; Logística conserva el proceso y sus referencias. La persistencia local no garantiza por sí sola la coordinación física con otros módulos.

## Terminar con decisiones concretas

Abrir **COORDINACION_Y_CONTRATO.md**: elegir una sola persistencia, aplicar el reparto confirmado, validar la base con G4/G5/Inventarios y acordar reserva/salida, confirmaciones, Guías y permisos.

Abrir **GUIA_PARA_ALISSON.md** cuando se necesiten nombres de métodos, entradas JSON, pruebas o límites. Los contratos y archivos vigentes del repositorio permiten comparar lo implementado antes de integrar.
