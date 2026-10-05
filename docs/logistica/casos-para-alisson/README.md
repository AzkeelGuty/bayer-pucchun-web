# Casos claros para revisar con Alisson

Entrega del 5 de octubre de 2026. Las láminas generales se conservan por separado en [material-visual](../material-visual/index.html). Este directorio contiene los casos concretos para Alisson.

## Cómo usar el paquete

1. Descomprimir el ZIP completo y abrir **index.html** en un navegador. Funciona sin Internet.
2. Empezar por [EXPLICACION_EN_5_MINUTOS.md](EXPLICACION_EN_5_MINUTOS.md) y las láminas 02, 06, 07 y 08: explican el mismo saldo rechazado y sus destinos posibles.
3. Consultar [GUIA_PARA_ALISSON.md](GUIA_PARA_ALISSON.md) para los métodos, entradas y quince escenarios.
4. Revisar [COORDINACION_Y_CONTRATO.md](COORDINACION_Y_CONTRATO.md) para acordar una sola implementación. Incluye un mensaje que Pedro puede copiar; no se envió a nadie.

Las imágenes se pueden compartir individualmente. Cada una tiene un caso y una nota de alcance sobre el contrato local. En la galería hay enlaces para abrirlas en tamaño completo o descargarlas.

## Contenido

- **10 PNG de 1536 × 1024:** completa/parcial, rechazo, dos causas sobre una cantidad, daño aceptado, ausencia del receptor, reintento parcial, devolución solicitada frente a retorno confirmado, retorno/pérdida, controles de cierre e idempotencia/concurrencia.
- **Guía técnica:** 15 casos, balances por línea, estados frente a resultados de intento, responsabilidades Services/Repository, tres entradas JSON y evidencia directa o derivada de las pruebas.
- **Coordinación:** ocho decisiones con G4, G5, Inventarios, Guías y responsables de funcionamiento, más lista de integración y borrador de mensaje.
- **referencias/:** copias del contrato, interfaz, Repository, migraciones y pruebas para revisar sin depender de rutas de mi equipo. Incluye el [logistics-persistence-pedro.md que pidió Alisson](referencias/docs/handoffs/logistics-persistence-pedro.md) y el [informe de pruebas](referencias/docs/handoffs/logistics-validation.md).
- **codigo/logistics-persistence-pedro.patch:** parche histórico de la entrega local contra `f38f4e8`, conservado para comparación offline. Sus textos reflejan el momento anterior a confirmar el intercambio; para responsabilidades y publicación actuales prevalecen esta guía y los archivos del repositorio.
- **PROMPTS.md:** instrucciones de generación y ajuste final de la lámina 10.
- **MANIFIESTO.json:** archivos PNG, dimensiones y huellas SHA-256.
- **fuentes/:** Source Serif 4 y Source Sans 3 autoalojadas con sus licencias OFL.

## Qué representa y qué falta acordar

Estas láminas describen una persistencia local basada en `schema_v2` de `integration/frontend-equipo`, base `f38f4e8`. El esquema exacto no tiene aprobación confirmada del ingeniero ni de G4/G5 como base definitiva. La integración remota observada avanzó a `d0819d4`; comparar cambios y referencias antes de integrar.

El modelo propio tiene diez tablas: seis del proceso, incidencias, resoluciones, operaciones técnicas e historial. No crea otro módulo de Guías ni un stock/Kardex paralelo. Services, permisos, rutas y adaptadores reales siguen fuera de esta entrega. Pedro confirmó el intercambio: **Pedro desarrolla persistencia; Alisson desarrolla Services, Controllers y funcionamiento**. La publicación solicitada corresponde a `feature/logistics-flow-pedro`.

El adaptador confiable de Inventarios es simulado en las pruebas. Un retorno o pérdida real necesita confirmación del módulo responsable. La aceptación de traslados sigue bloqueada hasta acordar recepción de destino. La guía explica además compatibilidad de decimales con Guías y restricciones de corrección.

El parche se preparó contra `f38f4e8`. **Sirve para comparar; no implica que pueda aplicarse sin revisión a la integración actual.** No importar `002_schema_v2.sql` sobre la base compartida existente. Revisar únicamente la integración acordada de migraciones aditivas 006 y 007 en QA.

## Diseño y revisión

Papel crema, tinta oscura y acento verde bosque; dibujo a tinta, líneas finas y titulares serif. Las tipografías exactas están autoalojadas en la galería; las imágenes generadas reproducen ese criterio visual, sin afirmar que su texto use un archivo tipográfico concreto.

Todas las láminas se inspeccionaron visualmente. La 10 se corrigió para evitar un ejemplo inventado de reserva y distinguir éxito/conflicto. En la 09 son dos escenarios independientes. En la 06 el reintento combinado 12/8 es derivado de las reglas; la guía distingue ese ejemplo de las pruebas exactas existentes.

Modo de generación: **herramienta integrada image_gen**, diez generaciones y una edición, sin CLI. Los PNG son estáticos. La galería sólo usa desplazamiento y transiciones suaves de enlaces; respeta `prefers-reduced-motion`.

Las referencias incluidas son copias de consulta. Para ejecutar las pruebas se necesita el repositorio completo y su entorno; este paquete no es una aplicación desplegable. El informe de ejecución anterior está en [logistics-validation.md](referencias/docs/handoffs/logistics-validation.md). Esta entrega visual no cambió ni volvió a ejecutar el código.
