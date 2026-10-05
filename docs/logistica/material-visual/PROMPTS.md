# Instrucciones de generación y revisión

Herramienta: generador de imágenes integrado de Codex. Se crearon seis láminas y se corrigieron puntos concretos tras la revisión visual. La carpeta conserva únicamente las versiones finales seleccionadas; los originales de generación permanecen en el directorio predeterminado de Codex.

Las láminas son PNG estáticos. Las fuentes reales autoalojadas pertenecen a la galería HTML; en los PNG la tipografía forma parte de la imagen generada.

## Especificaciones finales

### 1. El recorrido de Logística

Archivo: 01_flujo_logistico.png

```text
Use case: infographic-diagram.
Asset type: lámina educativa para exponer un módulo de ERP, imagen final horizontal de alta resolución, proporción 3:2.
Diseño editorial elegante, sobrio y artesanal, composición única y deliberada como una página de un manual técnico impreso. Fondo papel crema uniforme #F3EFE4 con fibra muy sutil, tinta oscura #282821 y UN solo acento verde bosque desaturado #405A47. No otros colores. Títulos serif estilo Source Serif 4 / Newsreader; cuerpo sans humanista estilo Source Sans 3, muy legible, jerarquía precisa y letras grandes. Amplio espacio blanco, márgenes generosos, reglas muy finas. Pequeños trazos originales a pluma, planos y discretos, solo si ayudan. La información domina.
TODO el texto en español, escrito exactamente según la lista, con acentos correctos. No añadir texto, cifras, leyendas, logotipos ni marcas de agua. No cortar texto. Evitar texto diminuto.
Prohibido: degradados, morado, azul neón, glassmorphism, sombras exageradas, emojis, iconos prediseñados, estética de plantilla, cuadrícula de tarjetas iguales, esquinas muy redondeadas, renders 3D, decoraciones superfluas. No interfaces ficticias ni botones de aplicación.
Primary request: Explicar el recorrido conceptual, distinguiendo documento, intento y estado. No aparentar una aplicación ya integrada.
Composition: gran título arriba a la izquierda. Un recorrido horizontal amplio y continuo de cuatro hitos con flechas de tinta, sin cajas ni tarjetas. Una nota verde se conecta solo a Despacho, como documento sujeto con un fino trazo. Debajo, tres ramas desde Entrega: aceptación completa, saldo pendiente sin bloqueo e incidencia bloqueante pendiente. Las condiciones son distintas.
Text (verbatim):
"ERP COMERCIAL · GRUPO 7"
"El recorrido de Logística"
"Preparación"
"Organizar cantidades y destino."
"Despacho"
"Vincular salida y guía."
"En tránsito"
"Conservar el seguimiento."
"Entrega"
"Registrar cada intento."
"Guía existente"
"Documento asociado; no es un estado."
"Todo aceptado, sin bloqueos"
"ENTREGADO"
"Pendiente, sin incidencia bloqueante"
"EN TRÁNSITO"
"Incidencia bloqueante pendiente"
"EN RESOLUCIÓN"
"Persistencia implementada · Funcionamiento e integración pendientes"
Constraints: Los rótulos EN TRÁNSITO y EN RESOLUCIÓN son español de lectura; el código real usa EN_TRANSITO y EN_RESOLUCION. Un saldo pendiente sin bloqueo permanece en tránsito. No poner Guía como quinto estado. No implicar que un rechazo ya es retorno ni que la persistencia mueve stock. Flechas no conectan EN_RESOLUCIÓN a ENTREGADO sin condición.
```

### 2. Qué resuelve cada parte

Archivo: 02_responsabilidades.png

```text
Use case: infographic-diagram.
Asset type: lámina educativa para exponer un módulo de ERP, imagen final horizontal de alta resolución, proporción 3:2.
Diseño editorial elegante, sobrio y artesanal, composición única y deliberada como una página de un manual técnico impreso. Fondo papel crema uniforme #F3EFE4 con fibra muy sutil, tinta oscura #282821 y UN solo acento verde bosque desaturado #405A47. No otros colores. Títulos serif estilo Source Serif 4 / Newsreader; cuerpo sans humanista estilo Source Sans 3, muy legible, jerarquía precisa y letras grandes. Amplio espacio blanco, márgenes generosos, reglas muy finas. Pequeños trazos originales a pluma, planos y discretos, solo si ayudan. La información domina.
TODO el texto en español, escrito exactamente según la lista, con acentos correctos. No añadir texto, cifras, leyendas, logotipos ni marcas de agua. No cortar texto. Evitar texto diminuto.
Prohibido: degradados, morado, azul neón, glassmorphism, sombras exageradas, emojis, iconos prediseñados, estética de plantilla, cuadrícula de tarjetas iguales, esquinas muy redondeadas, renders 3D, decoraciones superfluas. No interfaces ficticias ni botones de aplicación.
Primary request: Explicar la separación de responsabilidades ya confirmada por Pedro: él desarrolla persistencia y Alisson desarrolla funcionamiento.
Composition: página editorial asimétrica en tres bandas de alturas distintas, sin tarjetas. Izquierda encabezados de gran serif; derecha listas cortas alineadas, separadas con reglas finas. Un único trazo vertical verde relaciona las bandas. Pie con nota sobria.
Text (verbatim):
"ERP COMERCIAL · GRUPO 7"
"Qué resuelve cada parte"
"Persistencia preparada"
"Tablas y migraciones"
"Repositorios y relaciones"
"Integridad de cantidades"
"Concurrencia, idempotencia e historial"
"Funcionamiento por integrar"
"Servicios y controladores"
"Reglas, permisos y rutas"
"Coordinación entre módulos"
"Módulos existentes"
"Inventarios: reservas, salidas y retornos"
"Guías: documento del despacho"
"Ventas: origen de la solicitud"
"Reparto confirmado"
"Pedro: persistencia · Alisson: funcionamiento"
"Pedro y Alisson intercambiaron sus responsabilidades."
Constraints: distinguir claramente preparada vs por integrar. No afirmar que Servicios o stock ya fueron implementados aquí. No decir que la guía reemplaza una salida de Inventarios.
```

### 3. Cien unidades, ningún saldo perdido

Archivo: 03_caso_100_unidades.png

```text
Use case: infographic-diagram.
Asset type: lámina educativa para exponer un módulo de ERP, imagen final horizontal de alta resolución, proporción 3:2.
Diseño editorial elegante, sobrio y artesanal, composición única y deliberada como una página de un manual técnico impreso. Fondo papel crema uniforme #F3EFE4 con fibra muy sutil, tinta oscura #282821 y UN solo acento verde bosque desaturado #405A47. No otros colores. Títulos serif estilo Source Serif 4 / Newsreader; cuerpo sans humanista estilo Source Sans 3, muy legible, jerarquía precisa y letras grandes. Amplio espacio blanco, márgenes generosos, reglas muy finas. Pequeños trazos originales a pluma, planos y discretos, solo si ayudan. La información domina.
TODO el texto en español, escrito exactamente según la lista, con acentos correctos. No añadir texto, cifras, leyendas, logotipos ni marcas de agua. No cortar texto. Evitar texto diminuto.
Prohibido: degradados, morado, azul neón, glassmorphism, sombras exageradas, emojis, iconos prediseñados, estética de plantilla, cuadrícula de tarjetas iguales, esquinas muy redondeadas, renders 3D, decoraciones superfluas. No interfaces ficticias ni botones de aplicación.
Primary request: Infografía exacta del ejemplo probado de un mismo producto/lote/unidad, 100 despachadas, 80 aceptadas, 20 rechazadas, después 12 retornadas y 8 pérdida confirmada. Rechazo es pendiente, no devolución automática.
Composition: narrativa de izquierda a derecha con dos momentos. Mostrar 100 como franja horizontal dividida proporcionalmente 80/20, verde tenue para aceptadas, crema con rayado fino para pendientes. Del segmento 20 salen dos ramas finales anchas proporcionadas 12/8. Evitar que la franja 100 se sume de nuevo o que rechazo y daño multipliquen cantidades. Una gran ecuación inferior y sello tipográfico plano del cierre, sin recuadros redondeados.
Text (verbatim):
"ERP COMERCIAL · GRUPO 7"
"Cien unidades, ningún saldo perdido"
"100 despachadas"
"Primer intento"
"80 aceptadas"
"20 rechazadas"
"Siguen pendientes; no son un retorno."
"Resolución posterior"
"12 retornadas"
"Confirmación de Inventarios"
"8 perdidas"
"Confirmación y autorización"
"100 = 80 + 12 + 8 + 0 pendientes"
"CERRADO CON INCIDENCIA"
"Solo cuando todas las líneas estén conciliadas y no existan incidencias bloqueantes pendientes."
"Cada cantidad se contabiliza una sola vez."
Constraints: el cierre final es CERRADO_CON_INCIDENCIA, nunca ENTREGADO. Las 8 son pérdida confirmada, no descuento nuevo de stock. Las 12 retornadas son hechos confirmados, no solicitudes. Los porcentajes/proporciones 80%,20%,12%,8% se expresan mediante tamaños solamente, no añadir porcentajes escritos.
Additional required footer text (verbatim): "Caso probado con confirmaciones simuladas de Inventarios."
```

### 4. Una visita fallida también deja evidencia

Archivo: 04_intento_fallido.png

```text
Use case: infographic-diagram.
Asset type: lámina educativa para exponer un módulo de ERP, imagen final horizontal de alta resolución, proporción 3:2.
Diseño editorial elegante, sobrio y artesanal, composición única y deliberada como una página de un manual técnico impreso. Fondo papel crema uniforme #F3EFE4 con fibra muy sutil, tinta oscura #282821 y UN solo acento verde bosque desaturado #405A47. No otros colores. Títulos serif estilo Source Serif 4 / Newsreader; cuerpo sans humanista estilo Source Sans 3, muy legible, jerarquía precisa y letras grandes. Amplio espacio blanco, márgenes generosos, reglas muy finas. Pequeños trazos originales a pluma, planos y discretos, solo si ayudan. La información domina.
TODO el texto en español, escrito exactamente según la lista, con acentos correctos. No añadir texto, cifras, leyendas, logotipos ni marcas de agua. No cortar texto. Evitar texto diminuto.
Prohibido: degradados, morado, azul neón, glassmorphism, sombras exageradas, emojis, iconos prediseñados, estética de plantilla, cuadrícula de tarjetas iguales, esquinas muy redondeadas, renders 3D, decoraciones superfluas. No interfaces ficticias ni botones de aplicación.
Primary request: Explicar un intento fallido por ausencia de receptor, con cero aceptado y ningún detalle de cantidades. Es un resultado de intento, no una falsa entrega ni nuevo estado de despacho.
Composition: izquierda dibujo de pluma muy sobrio de puerta cerrada y documento de visita, sin personajes, espacio generoso. Derecha secuencia vertical abierta con tres momentos y reglas finas. Abajo una frase breve fuerte con acento verde. Ninguna cuadrícula de tarjetas.
Text (verbatim):
"ERP COMERCIAL · GRUPO 7"
"Una visita fallida también deja evidencia"
"1 · Registrar el intento"
"Resultado: FALLIDA"
"Motivo: ausencia del receptor"
"Sin líneas de cantidades"
"2 · Abrir la incidencia"
"Conservar motivo, evidencia y responsable."
"3 · Reprogramar"
"Registrar la acción; la mercadería sigue pendiente."
"Reprogramar no equivale a entregar."
"La aceptación posterior se registra en un nuevo intento."
"Los saldos no cambian por una visita fallida."
Constraints: FALLIDA corresponde al intento. No poner despacho FALLIDO, ENTREGADO o saldo cero. No crear una línea con cantidad cero. No dibujar devolución ni movimientos de Inventarios. El caso de ausencia es un ejemplo explícito, no causa inferida para cualquier fallo.
```

### 5. Reintentar sin duplicar

Archivo: 05_seguridad_de_datos.png

```text
Use case: infographic-diagram.
Asset type: lámina educativa para exponer un módulo de ERP, imagen final horizontal de alta resolución, proporción 3:2.
Diseño editorial elegante, sobrio y artesanal, composición única y deliberada como una página de un manual técnico impreso. Fondo papel crema uniforme #F3EFE4 con fibra muy sutil, tinta oscura #282821 y UN solo acento verde bosque desaturado #405A47. No otros colores. Títulos serif estilo Source Serif 4 / Newsreader; cuerpo sans humanista estilo Source Sans 3, muy legible, jerarquía precisa y letras grandes. Amplio espacio blanco, márgenes generosos, reglas muy finas. Pequeños trazos originales a pluma, planos y discretos, solo si ayudan. La información domina.
TODO el texto en español, escrito exactamente según la lista, con acentos correctos. No añadir texto, cifras, leyendas, logotipos ni marcas de agua. No cortar texto. Evitar texto diminuto.
Prohibido: degradados, morado, azul neón, glassmorphism, sombras exageradas, emojis, iconos prediseñados, estética de plantilla, cuadrícula de tarjetas iguales, esquinas muy redondeadas, renders 3D, decoraciones superfluas. No interfaces ficticias ni botones de aplicación.
Primary request: Explicar idempotencia, concurrencia y evidencia externa como tres protecciones distintas. Precisión: misma clave y mismo contenido devuelve respuesta original; otra misma clave con contenido diferente se rechaza. Retorno contra reentrega de la misma cantidad: uno gana otro entra en conflicto y debe reconsultar.
Composition: página editorial con tres diagramas abiertos diferentes, de arriba abajo. Primero dos flechas de solicitudes iguales convergen en un solo hecho y respuesta. Segundo dos acciones compiten por una misma cantidad, una confirmada y una señal de conflicto tipográfica. Tercero documento de Inventarios conectado con referencia única a resolución. No cajas repetitivas, iconos emoji ni UI. Numeración discreta 01/02/03 en verde.
Text (verbatim):
"ERP COMERCIAL · GRUPO 7"
"Reintentar sin duplicar"
"01 · Misma solicitud"
"Misma clave + mismo contenido"
"Una operación; respuesta original."
"Clave reutilizada con otro contenido: se rechaza."
"02 · Misma cantidad, dos acciones"
"Retornar o reentregar"
"Un éxito y un conflicto."
"Consultar de nuevo antes de decidir."
"03 · Confirmación externa"
"Retorno o pérdida: evidencia confiable y referencia única."
"Una confirmación no se utiliza dos veces."
"Sin adaptador real de Inventarios, esas resoluciones se rechazan."
Constraints: nunca representar un reintento como evento nuevo ni prometer exactamente una operación global en sistemas remotos. Las tres protecciones son locales a la persistencia implementada; no confundir idempotency_key con referencia de confirmación de Inventarios. No afirmar que el adaptador real ya existe.
```

### 6. Diez tablas, una sola persistencia

Archivo: 06_mapa_de_persistencia.png

```text
Use case: infographic-diagram.
Asset type: lámina educativa para exponer un módulo de ERP, imagen final horizontal de alta resolución, proporción 3:2.
Diseño editorial elegante, sobrio y artesanal, composición única y deliberada como una página de un manual técnico impreso. Fondo papel crema uniforme #F3EFE4 con fibra muy sutil, tinta oscura #282821 y UN solo acento verde bosque desaturado #405A47. No otros colores. Títulos serif estilo Source Serif 4 / Newsreader; cuerpo sans humanista estilo Source Sans 3, muy legible, jerarquía precisa y letras grandes. Amplio espacio blanco, márgenes generosos, reglas muy finas. Pequeños trazos originales a pluma, planos y discretos, solo si ayudan. La información domina.
TODO el texto en español, escrito exactamente según la lista, con acentos correctos. No añadir texto, cifras, leyendas, logotipos ni marcas de agua. No cortar texto. Evitar texto diminuto.
Prohibido: degradados, morado, azul neón, glassmorphism, sombras exageradas, emojis, iconos prediseñados, estética de plantilla, cuadrícula de tarjetas iguales, esquinas muy redondeadas, renders 3D, decoraciones superfluas. No interfaces ficticias ni botones de aplicación.
Primary request: Mapa lógico de las diez tablas propias implementadas, agrupadas por función, y estado real de la entrega. No es un diagrama de FK exhaustivo.
Composition: esquema asimétrico con núcleo del proceso en una columna amplia: Preparación enlaza Despacho y este enlaza Intentos de entrega. A la derecha, rama de incidencias seguida de resoluciones. Abajo, dos bandas largas y finas transversales para Operaciones e Historial. Pares cabecera/detalle visibles en subtítulos, ninguna cuadrícula. Flechas simples conceptuales. Total destacado en serif.
Text (verbatim):
"ERP COMERCIAL · GRUPO 7"
"Diez tablas, una sola persistencia"
"Preparación"
"Cabecera + detalle · 2 tablas"
"Despacho"
"Cabecera + detalle · 2 tablas"
"Intentos de entrega"
"Cabecera + detalle · 2 tablas"
"Incidencias · 1 tabla"
"Resoluciones · 1 tabla"
"Operaciones · 1 tabla"
"Evita repetir comandos."
"Historial · 1 tabla"
"Conserva eventos y evidencia."
"6 del proceso + 2 de incidencias + 2 de control = 10"
"Guías e Inventarios se reutilizan; no se duplican."
"545 comprobaciones de Logística superadas"
"Entrega local · Integración pendiente"
Constraints: 2+2+2+1+1+1+1=10. Operaciones e incidencias son distintas y no se reemplazan. Cabecera+detalle es agrupación lógica, los nombres físicos reales no aparecen. No dibujar stock nuevo, nuevo CRUD de Guías ni módulo de compras propio. 545 son comprobaciones de pruebas, no garantía de ausencia de errores ni pruebas de integración en producción.
Constraints additional: Incidencias se vincula solo a Despacho y opcionalmente a Intentos de entrega; no conectar Preparación directamente con Incidencias.
Precisión final de relaciones: Incidencias se conecta al Despacho y opcionalmente al Intento de entrega, nunca directamente a Preparación.
```

## Ediciones de revisión

### 01_flujo_logistico

```text
Edita SOLO la ramificación inferior desde Entrega de esta lámina editorial. Conserva exactamente toda la mitad superior, el flujo, los textos, la guía, colores, textura, tipos y dimensiones. Hay una precisión de negocio indispensable: cantidad pendiente SIN incidencia bloqueante NO implica EN_RESOLUCION.
Sustituye las dos ramas inferiores por TRES salidas de texto claras, sin dibujos de cajas ni círculos de check/exclamación, con tres finas flechas desde Entrega y espacio suficiente:
1. "Todo aceptado, sin bloqueos" → "ENTREGADO".
2. "Pendiente, sin incidencia bloqueante" → "EN TRÁNSITO".
3. "Incidencia bloqueante pendiente" → "EN RESOLUCIÓN".
Estos tres rótulos son español de lectura; no poner guiones bajos. Mantén el pie exacto "Persistencia implementada · Funcionamiento e integración pendientes". Ninguna condición debe mezclarse con otra. Ajusta solo el espacio inferior necesario para que quede legible, elegante, sin plantilla, con letra grande y márgenes.
```

### 02_responsabilidades

```text
Edición tipográfica puntual de esta lámina. Sustituye únicamente "Services y Controllers" por "Servicios y controladores", en el mismo lugar y con el mismo tipo, tamaño y tinta. Conserva TODO lo demás: composición, ilustraciones, cada otro texto, colores, textura, dimensiones y posición. Todo el texto debe ser español. No añadir nada.
```

### 05_seguridad_de_datos

```text
Edición puntual de esta lámina editorial. En el segundo diagrama, debajo de las cajas del centro, sustituye únicamente el rótulo "Inventario misma cantidad" por "Saldo pendiente del despacho" en dos líneas legibles. Conserva toda la composición y TODOS los demás textos/ilustraciones/colores. Esa cantidad la protege la persistencia del despacho; el stock real de Inventarios no se implementó aquí. No cambiar ni añadir nada más.
```

### 06_mapa_de_persistencia

```text
Edita SOLO el conector verde del lado derecho del núcleo del proceso en esta lámina. La incidencia pertenece al Despacho y puede vincularse al Intento de entrega, NO a Preparación. Elimina el extremo superior del corchete/flecha que sale de Preparación. Haz que ese corchete empiece a la altura del centro del bloque Despacho y termine a la altura del centro del bloque Intentos de entrega; desde él una fina flecha va a Incidencias. Conserva la flecha Preparación → Despacho → Intentos, la flecha Incidencias → Resoluciones y TODOS los textos/ilustraciones/colores/márgenes/dimensiones. No alterar nada más. Es un mapa lógico; no implica incidencias de preparación.
```

### 03_caso_100_unidades

```text
Edición muy concreta del diagrama central. Conserva TODO el texto, ecuación, colores, textura, ilustraciones, flechas y composición de esta lámina. Ajusta únicamente el divisor vertical de la franja "80 aceptadas / 20 rechazadas": la sección verde debe ocupar exactamente CUATRO QUINTOS (80 por ciento) de la anchura total interior, y la sección rayada exactamente UN QUINTO (20 por ciento). Reubica los dos textos dentro de sus secciones si hace falta para legibilidad. No añadir porcentajes ni ningún texto. Los dos recuadros de 12/8 a la derecha son etiquetas de hechos, no una escala; no los cambies.
```

## Actualización final: reparto confirmado

Fecha: 5 de octubre de 2026. Modo: edición mediante la herramienta integrada image_gen; objetivo local: `02_responsabilidades.png`. Las versiones originales previas se conservan fuera de esta copia de publicación.

```text
Use case: text-localization. Input image 1 is the edit target. Correct ONLY the bottom footer to reflect the role swap confirmed by the user.
Replace "Intercambio solicitado" with EXACT text "Reparto confirmado".
Keep "Pedro: persistencia · Alisson: funcionamiento" unchanged.
Replace "Este reparto debe quedar acordado entre ambos." with EXACT text "Pedro y Alisson intercambiaron sus responsabilidades."
All other elements must remain unchanged: title, all bullet points, exact three-section structure, pen-and-ink drawings, cream paper, muted forest green as the only accent, dark ink, serif headings, humanist sans body, thin lines, spacing, 1536x1024 composition.
Do not add anything else or change technical claims. No new colours, no gradients, no decorative shadows, no emojis. Legible Spanish with accents correct.
```
