# Cuaderno visual de Logística

Material de explicación del módulo de persistencia de Logística del Grupo 7. Contiene seis imágenes estáticas generadas con la herramienta integrada y una galería local, `index.html`, con explicaciones y guiones breves de exposición. Los diagramas son conceptuales y no están a escala; no representan pantallas ya implementadas.

## Uso

Abrir `index.html` en un navegador. La galería funciona sin conexión una vez reunidos los archivos de esta carpeta. Cada lámina tiene enlaces para abrir y descargar el PNG, y un bloque accesible de explicación con guion. Las imágenes pueden insertarse directamente en una presentación o documento. Para compartir la galería, conservar la carpeta `fuentes` junto al HTML y las imágenes.

La interfaz está en español. No utiliza JavaScript, bibliotecas, rastreo ni recursos remotos. Las transiciones de enlaces y desplegables duran 120 ms y se desactivan con `prefers-reduced-motion`; no hay animaciones automáticas. Se incluye diseño adaptable y estilos de impresión. Para imprimir con navegadores antiguos, abrir los bloques de explicación antes de imprimir.

| Archivo | Utilidad |
| --- | --- |
| `01_flujo_logistico.png` | Preparación, despacho, tránsito, intentos de entrega y Guía vinculada. |
| `02_responsabilidades.png` | Frontera entre Servicios y controladores, persistencia, Inventarios y Guías. |
| `03_caso_100_unidades.png` | 80 aceptadas, 20 rechazadas, 12 retornadas y 8 pérdidas confirmadas. |
| `04_intento_fallido.png` | Ausencia de receptor sin cantidades ficticias ni alteración del balance. |
| `05_seguridad_de_datos.png` | Idempotencia, versiones, transacciones y concurrencia. |
| `06_mapa_de_persistencia.png` | Diez tablas y alcance real de la entrega. |

## Fuentes de contenido

Contenido contrastado con los archivos de desarrollo del repositorio. Se incluyen copias de consulta en referencias/ para conservarlas al compartir el paquete; para integrar, usar los contratos y código vigentes enlazados en el [índice de Logística](../README.md):

- [Contrato vigente de incidencias, intentos, balances y cierre](referencias/docs/contracts/logistics-incidents.md).
- [Contrato común de persistencia](referencias/docs/contracts/logistics-persistence.md), subordinado al contrato vigente en los puntos que evolucionó la migración 007.
- [Registro de validación](referencias/docs/handoffs/logistics-validation.md).
- [Documento de entrega e integración](referencias/docs/handoffs/logistics-persistence-pedro.md).
- [Migración inicial 006](referencias/database/migrations/006_logistics_persistence.sql) y [evolución 007](referencias/database/migrations/007_logistics_incidents.sql).

El registro del 5 de octubre de 2026 informa **545 comprobaciones de Logística correctas**: 42 de cantidades, 239 de persistencia y 264 de incidencias e intentos. También se ejecutaron correctamente 27 suites originales. Estos resultados verifican los casos probados en el entorno local de QA; **no son una garantía de ausencia de errores ni de preparación para producción**. El workflow remoto de CI no se ejecutó en esa entrega.

El caso de 100 unidades utiliza confirmaciones simuladas exclusivamente en pruebas. La integración real de Inventarios, Servicios y controladores, permisos y coordinación de reservas y salidas está pendiente. También requiere revisión Guías con cantidades fraccionarias. La aceptación de traslados permanece bloqueada hasta integrar la recepción confirmada en el almacén destino.

Pedro confirmó que intercambiaron sus responsabilidades: **Pedro desarrolla persistencia; Alisson desarrolla Services, Controllers y funcionamiento del módulo**. La publicación solicitada corresponde a `feature/logistics-flow-pedro`; el nombre histórico de esa rama se conserva aunque ahora Pedro trabaje en persistencia.

## Reglas para explicar las imágenes

- La Guía es un documento vinculado y versionado, no un estado del proceso ni un CRUD nuevo.
- Cada intento de entrega conserva su resultado. `FALLIDA` no es un estado del despacho y puede registrarse sin líneas, con causa, motivo y contexto; no cambia aceptadas, retornadas, finales o pendientes.
- Una cantidad pendiente por sí sola no implica `EN_RESOLUCION`. Sin asunto bloqueante el despacho puede seguir `EN_TRANSITO`; con un bloqueo pendiente se mantiene en resolución.
- Rechazar, investigar, reprogramar o solicitar devolución no confirma un retorno, una pérdida ni una aceptación.
- Por línea exacta de despacho: despachado = aceptado + retorno confirmado + disposición/pérdida confirmada + pendiente. No sumar de nuevo una resolución de aceptación posterior a lo ya aceptado.
- Las mismas 20 unidades dañadas y rechazadas son una asignación con varias causas, no 40 unidades.
- En el caso ilustrado, el cierre final es `CERRADO_CON_INCIDENCIA`: 80 aceptadas + 12 retornadas + 8 pérdidas = 100, sin saldo ni bloqueos pendientes. `ENTREGADO` exige aceptación completa sin bloqueos.
- `logistica_operaciones` controla idempotencia técnica; `logistica_incidencias` registra problemas operativos. No se reemplazan entre sí.
- Logística no implementa stock paralelo ni Kardex. Las confirmaciones físicas y movimientos reales corresponden a Inventarios.

## Tipografía local

La **galería HTML** utiliza titulares en **Source Serif 4** y cuerpo en **Source Sans 3**, pesos 400 y 600. Los cuatro archivos WOFF2 se descargaron sin modificaciones desde los repositorios oficiales de Adobe, rama `release`, el 5 de octubre de 2026. Las rutas se contrastaron con el CSS oficial de cada familia. Las fuentes se sirven desde `fuentes/`, sin solicitudes a Google Fonts u otro servicio durante el uso de la galería.

La tipografía de los PNG forma parte de cada imagen generada: se solicitó un estilo serif para titulares y sans humanista para cuerpo. No se certifica que el generador haya utilizado estas familias exactas dentro de las imágenes; las fuentes reales autoalojadas se aplican a los textos de la galería.

- [Repositorio oficial Source Serif](https://github.com/adobe-fonts/source-serif/tree/release) y [CSS oficial](https://raw.githubusercontent.com/adobe-fonts/source-serif/release/source-serif-text.css).
- [Repositorio oficial Source Sans](https://github.com/adobe-fonts/source-sans/tree/release) y [CSS oficial](https://raw.githubusercontent.com/adobe-fonts/source-sans/release/source-sans-3.css).
- [Licencia local Source Serif 4](fuentes/SourceSerif4-LICENSE.md) y [original oficial](https://raw.githubusercontent.com/adobe-fonts/source-serif/release/LICENSE.md).
- [Licencia local Source Sans 3](fuentes/SourceSans3-LICENSE.md) y [original oficial](https://raw.githubusercontent.com/adobe-fonts/source-sans/release/LICENSE.md).

Las licencias SIL Open Font License 1.1 se conservan completas junto a las fuentes. El inglés de los archivos de licencia se preserva por fidelidad al original; no forma parte de los textos de la interfaz.

Paleta de la galería: papel `#F3EFE4`, tinta `#282821` y único acento verde desaturado `#405A47`. Sin degradados, sombras, transparencias decorativas ni tarjetas con bordes redondeados.
