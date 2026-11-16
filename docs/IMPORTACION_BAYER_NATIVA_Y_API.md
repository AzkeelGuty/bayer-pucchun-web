# Productos Bayer, inventario por mes y API Pucchún/Bayer

## Nuevo diseño

**Productos Bayer** se mantiene dentro del menú **Homologaciones Bayer** y conserva las seis pestañas de la administración, pero ahora su tabla de productos es **independiente** del catálogo **Pucchún**. Los otros cinco apartados de homologación conservan su comportamiento existente. No se elimina ni modifica la tabla histórica `homologacion_productos_bayer`.

- **Código Bayer:** único identificador real que figura en la columna `CODIGO` del proveedor. No se genera otro código manual ni automático.
- **Producto / presentación** y **marca**: datos originales de Bayer.
- **Stock Bayer**: cantidad del reporte mensual, por producto y período; no es el stock de almacén Pucchún.
- **Stock Pucchún**: columna visual vacía en Productos Bayer. Solo el módulo **Stock** y los snapshots de almacenes publicados gestionan existencias Pucchún.
- **Estado**: derivado de la cantidad reportada, no es una bandera fija `1`.

| Celda del proveedor | Valor conservado | Estado visual | Cantidad en API |
|---|---|---|---|
| `274` | 274 | Con stock | 274 |
| `0` | 0 | Sin stock | 0 |
| celda vacía | `NULL` | Sin dato | null |
| `-` / `—` | `NULL` | Desactivado | null y `stockMarker=GUION` |

Los meses se conservan en `bayer_inventario_periodos`. La tabla muestra el último período por código Bayer; importar un mes anterior no debe reemplazar el último período. Los reportes mantienen trazabilidad mensual y **no se suman meses diferentes**.

## Importar XLSX o CSV original del proveedor

**Homologaciones Bayer → Productos Bayer → Importar Excel original**.

Se admite el archivo XLSX o CSV original del reporte «REPORTE DE INVENTARIOS CANAL - CLIENTE» sin reorganizarlo. El sistema reconoce hasta 100 hojas y busca automáticamente encabezados como:

- `CODIGO`
- `PRODUCTO / PRESENTACION`
- `MARCA` (opcional)
- `CANTIDAD / UNIDADES` (si existe)
- `Year` y `Month`, o nombres mensuales de pestañas como `SET_26`, `OCT_26`, etc.

No importa que el encabezado esté en la fila 13 o más abajo. Si el archivo no trae período, el administrador puede indicarlo en el selector **Mes del inventario**. Si hay stock y no es posible conocer el período, la importación lo rechaza en lugar de inventar una fecha.

### Vista previa por pestaña y confirmación

1. Subir el Excel original **XLSX**, con todas sus hojas mensuales (por ejemplo, SET_26, OCT_26, NOV_26), o el **CSV** original.
2. Pulsar **Analizar archivo**. No se modifica ninguna tabla ni producto durante el análisis.
3. Elegir una pestaña reconocida en el selector. La pantalla muestra el período, el número de productos, los estados y las primeras 20 filas.
4. Marcar la confirmación y pulsar **Importar pestaña seleccionada**. Solo esa hoja se registra. Las demás no se importan.
5. Para incorporar otro mes, subir nuevamente el original y seleccionar la otra pestaña.

El CSV usa una sola fuente llamada «Archivo CSV» y admite separadores coma, punto y coma o tabulador, incluidos archivos con BOM UTF-8. El archivo temporal se conserva fuera del directorio público durante un máximo de **30 minutos** y puede descartarse con el botón correspondiente.

El máximo es **12 MB por archivo** y **10 000 productos por hoja**. Los archivos con varias pestañas pueden superar los 10 000 productos-período en total, siempre que cada pestaña esté dentro de su límite.

Los períodos se determinan prioritariamente con columnas Year y Month; cuando no existen, a partir del nombre de la pestaña; y solo como alternativa con el mes ingresado. La vista previa indica cantidades inválidas, meses ausentes y duplicados antes de permitir la confirmación. Los datos Pucchún permanecen independientes.
Se mantiene disponible una **plantilla alternativa simple de cinco columnas**: `codigo_bayer`, `nombre_bayer`, `marca`, `cantidad_unidades`, `periodo`. Ya no solicita `partner_codigo`, `codigo_interno`, `valid_from`, `valid_until` ni `unidad_codigo` para Productos Bayer.

**Nota:** si el proveedor almacenó un código originalmente con ceros iniciales como número en Excel, Excel pudo haberlos descartado antes de subirlo; el sistema no puede recuperarlos sin la fuente original. Los códigos deben permanecer como texto cuando existan ceros significativos.

## Encabezados alternativos de reportes Bayer y productos Pucchún

La importación no depende del orden de columnas ni exige encabezados en la
primera fila. Los reportes mensuales originales Bayer pueden llevar títulos,
datos de empresa, país, año y mes antes de la tabla. El sistema localiza una
fila que incluya el código y el producto, incluso cuando las columnas comiencen
en I, J, K y L. Los encabezados grises **CÓDIGO**, **PRODUCTO /
PRESENTACIÓN**, **MARCA** y **CANTIDAD / UNIDADES** se reconocen tal cual,
con mayúsculas, tildes, barras, espacios y otras separaciones.

La detección utiliza un diccionario de equivalencias exactas normalizadas
(`app/Services/ProductExcelHeaderAliases.php`). Algunos ejemplos:

| Campo | Otras cabeceras admitidas |
| --- | --- |
| Código Bayer | CÓDIGO, COD. PRODUCTO, SKU, CODIGO SAP, MATERIAL ID, PRODUCT CODE |
| Presentación | PRODUCTO / PRESENTACIÓN, NOMBRE COMERCIAL, DESCRIPCION PRODUCTO, PRODUCT NAME |
| Marca | MARCA, BRAND, MARCA PRODUCTO |
| Cantidad | CANTIDAD / UNIDADES, STOCK DISPONIBLE, EXISTENCIAS, SALDO UNIDADES, QTY |
| Año / mes | YEAR, AÑO REPORTE, MONTH, MES INVENTARIO |
| Período | PERÍODO STOCK, YEAR MONTH |
| Almacén | ALMACÉN, ALMACENAMIENTO, NOMBRE DEL ALMACÉN, WAREHOUSE |
| Almacén / sucursal (códigos) | CÓDIGO DE ALMACÉN, COD. ALMACEN, CÓDIGO SUCURSAL |
| Precio sin IGV | PRECIO NETO, PRECIO SUGERIDO SIN IGV, PRECIO LISTA SIN IGV |
| IGV | IGV %, PORCENTAJE DE IGV, TASA DE IGV, VAT RATE |
| Precio con IGV | PRECIO SUGERIDO CON IGV, PRECIO FINAL CON IGV, PRECIO BRUTO |
| Descuento | DESCUENTO DEALER %, DSCTO %, DESCUENTO DISTRIBUIDOR |
| Vigencia | VIGENCIA DESDE, FECHA INICIO, VIGENCIA HASTA, FECHA FIN |

En Pucchún (catálogo de Productos), también se admiten encabezados
como **CÓDIGO PRODUCTO**, **NOMBRE DEL PRODUCTO**, **TIPO DE ARTÍCULO**,
**UNIDAD DE MEDIDA**, **CATEGORÍA PRODUCTO**, **MARCA**, **ESTADO PRODUCTO**
y los campos opcionales de almacén. Esta ampliación no cambia los
encabezados exigidos por los otros catálogos maestros.

La equivalencia se aplica al **nombre completo de la columna**, nunca
por prefijos ambiguos. **Country Code, Region Code y Channel Code** son
datos auxiliares y no se confunden con el código Bayer. Si una hoja incluye
dos columnas que representan el mismo campo, la importación detiene esa
hoja para corregir las cabeceras duplicadas.

### Excel de prueba con estructura de reporte mensual

El libro `Ejemplo_Bayer_Reportes_Cabeceras_Grises_Multipestanas.xlsx` contiene
las hojas `SET_26`, `OCT_26`, `PRECIOS_DEMO`, `INSTRUCCIONES`
y `CABECERAS ADMITIDAS`. Su encabezado gris reproduce las columnas
comerciales habituales del reporte de canal, con datos **DEMO**;
no contiene cantidades oficiales ni debe importarse en producción sin
revisarlo previamente. El asistente reconoce cada hoja por sus cabeceras
y permite seleccionar cuál registrar.

## Reconocimiento de almacenes al importar Excel

Desde **Productos Bayer → Importar Excel original** o **Catálogos maestros
→ Productos Pucchún → Importar Excel**, el sistema detecta el almacén
mediante las columnas `almacen_nombre`, `almacen_codigo` y
`sucursal_codigo`, o mediante información superior a la tabla como
**Almacén: Almacén Principal Chincha** y **Sucursal código: SUC-01**.
Reconoce las variantes de cabecera **ALMACÉN**, **Almacenamiento**,
**Nombre almacén**, **Código almacén** y sus equivalentes normalizados.

La validación compara **todo el nombre o todo el código**, no una
coincidencia parcial. Ignora diferencias irrelevantes de mayúsculas,
tildes y espacios. Si coincide con uno existente activo, reutiliza
su registro. Si es nuevo, lo crea con un código propio (o el que
indica el archivo) en la misma transacción que los productos, y
asocia la referencia al catálogo correspondiente.

Para almacenes nuevos, el archivo debe indicar la sucursal mediante
`sucursal_codigo` si hay más de una sucursal activa. Si solo existe
una, se utiliza esa sucursal. Los nombres muy parecidos a uno existente
se detienen para revisión y no se duplican automáticamente. Si
hay nombres ambiguos entre sucursales, también se solicita su código.

En Bayer, la previsualización de cada hoja muestra los almacenes
existentes, los que se crearán y los errores que impiden importar.
En Pucchún, los cambios y las posibles altas se confirman juntos:
un error revierte toda la importación.

**Importante:** asignar un almacén de referencia no incrementa
ni traslada stock físico Pucchún. El stock del almacén se registra
en el módulo de inventarios físicos y sigue su propio flujo.

### Plantillas Excel diferenciadas

La plantilla Bayer (`plantilla_productos_bayer.xlsx`) contiene
`codigo_bayer`, `nombre_bayer`, marca, cantidades y período,
almacén y sucursal opcionales, así como precios, IGV, descuento y
fechas comerciales opcionales. Admite hojas de inventario, listas de
precios y reportes con múltiples pestañas.

La plantilla Pucchún (`plantilla_productos_pucchun.xlsx`) contiene
el código interno Pucchún, nombre, `unidad_codigo`, categoría,
marca, estado y almacén/sucursal opcionales. No mezcla precios del
proveedor Bayer ni registra existencias físicas del almacén.

Ambas plantillas incluyen **Carga** con encabezados destacados y
**Instrucciones** con descripción, obligatoriedad y ejemplos.
Los encabezados se identifican aunque aparezcan después de un
título o cabecera. Los archivos originales Bayer también se aceptan
sin copiarlos a estas plantillas.

## Un único Excel para productos, inventario y precios

Desde **Homologaciones Bayer → Productos Bayer → Importar Excel original**, subir
`XLSX` o `CSV`. Se reconocen automáticamente las pestañas del proveedor por su
cabecera: inventarios mensuales, listas comerciales o pestañas mixtas.
El administrador selecciona una pestaña y revisa **hasta 20 filas** antes de importar.
El botón **Importar hoja seleccionada** confirma el registro; se eliminó la casilla
de verificación adicional. **Cancelar y descartar** no modifica datos.

Los campos de precios detectados son `codigo_bayer`, `nombre_bayer`,
`moneda`, `precio_sugerido_sin_igv`, `igv_porcentaje`,
`precio_sugerido_con_igv`, `descuento_dealer_porcentaje`,
`vigencia_desde` y `vigencia_hasta`.

La columna IGV es opcional cuando existen precios neto y final para deducir su
porcentaje. Si se informa la tasa con un solo importe se calcula el otro; si no
hay información suficiente no se inventan datos tributarios. Cuando el archivo
no trae fecha inicial, se puede indicar **Fecha precios** antes de analizar.
Los productos sin columnas de precios mantienen intactos sus importes.

**Código Bayer + fecha inicial** identifica cada precio. En la misma fecha
se actualiza el valor, en una fecha nueva se añade un registro histórico.
Cada presentación comercial (12X1L, 4X4L, 20X250ML o 1X20KG) conserva su código
y precio independiente; no se exige unidad de medida ni conversión.

En pestañas mixtas, el catálogo, inventario y precios Bayer se registran en una
misma transacción: cualquier inconsistencia rechaza todos los cambios de esa hoja.
Un archivo de precios sin inventario solo actualiza códigos Bayer existentes;
para códigos nuevos debe importarse la hoja de catálogo o inventario.

## Publicación automática en API de Bayer

Los **productos activos Bayer** y sus registros mensuales y precios se consultan
en la API autenticada tan pronto se guardan. No es necesario pulsar **Publicar API**
ni **Publicar todos en API**: esos controles se retiraron de la pantalla.
Los productos desactivados no se incluyen en el catálogo activo de la API.
La información continúa protegida por autenticación Bearer.

El stock Pucchún sigue su propio procedimiento de validación y publicación de
snapshots físicos, con independencia total del proveedor Bayer.

## Stock consolidado por almacén y productos individuales

La pantalla **Stock** presenta una sola tabla que combina el catálogo de productos
Bayer y Pucchún, manteniendo identificada cada fuente. Se retiró la segunda tabla
de movimientos al pie de la página. Se conservan el formulario **Nuevo inventario
Pucchún** y las funciones de captura y validación en las pantallas correspondientes.

### Contadores de productos

- **Productos individuales**: cantidad de códigos únicos entre los dos catálogos.
- **Productos Bayer** y **Productos Pucchún**: códigos únicos por catálogo.
- Cada producto puede aparecer en más de un almacén, pero se cuenta solo una
  vez en los totales individuales. Las filas de la tabla pueden ser mayores
  que el total de productos únicos.

### Cinco indicadores visuales

**Disponible (verde)**, **Stock bajo (ámbar)**, **En cero (rojo)**,
**Negativo / desactivado (rojo oscuro)** y **Sin reporte (azul grisáceo)**.
Los productos desactivados Bayer se incluyen en el mismo indicador de
riesgo que las cantidades negativas; no existe una tarjeta independiente
de desactivados. No se modifica ni se borra el estado original del catálogo.

Las tarjetas usan iconos y permiten filtrar el listado. Con un producto en
varios almacenes puede haber más de un estado para ese código; el contador
de cada estado identifica productos distintos en esa situación. En todos
los casos, una cantidad vacía o no informada se mantiene separada de cero.

### Almacén en la consulta y en el formulario de edición

El filtro de **Almacén** utiliza una lista desplegable de almacenes activos.
La tabla identifica en qué almacén se encuentra cada registro. Al seleccionar
un almacén, las alertas y los totales se recalculan únicamente con los productos
y cantidades aplicables a ese lugar.

En **Productos Bayer → Editar**, puede indicarse un almacén de referencia,
y en **Catálogos maestros → Productos → Editar**, también. Es un campo opcional;
si se deja vacío el producto aparecerá sin almacén asignado cuando no existan
datos de inventario físicos.

Para **Pucchún**, las existencias reales proceden de los últimos inventarios
PUBLICADOS por almacén. El almacén elegido en el catálogo es una referencia
administrativa de ubicación y solo se utiliza cuando no hay snapshot físico.
No crea, mueve, suma ni descuenta stock. Un producto puede aparecer en varios
almacenes físicos reales aunque tenga un solo almacén de referencia.
Para **Bayer**, el almacén es una referencia del inventario informado por el
proveedor, sin modificar existencias Pucchún.

Los filtros de origen, situación y almacén se aplican automáticamente al escoger
una opción. Se mantienen el buscador, el umbral de stock bajo configurable y la
paginación de **15 filas por página**.

### Nueva migración 015

Antes de utilizar las nuevas asignaciones o la vista Stock, ejecutar una sola
vez **`database/migrations/015_almacenes_referencia_productos.sql`** sobre
la base de datos correcta. Crea las relaciones
`bayer_producto_almacen` y `pucchun_producto_almacen` con integridad referencial.
No elimina registros existentes ni modifica inventarios anteriores.
Es reejecutable mediante `CREATE TABLE IF NOT EXISTS`.

## Estados y eliminación

- **Editar:** cambia el nombre o marca del código Bayer seleccionado.
- **Desactivar:** retira el producto del catálogo activo de la API y conserva su histórico.
- **Activar:** vuelve a mostrar el código activo y su información guardada.
- **Eliminar:** requiere confirmar con el código exacto; borra sus referencias Bayer,
  nunca el inventario físico de Pucchún.

## Edición individual de productos, precios e inventario Bayer

En **Productos Bayer → Editar**, el formulario mantiene el código oficial
`codigo_bayer` bloqueado durante la edición para conservar su relación
con los precios e inventarios de esa presentación. Se pueden modificar el
nombre, la marca, la lista de precios y, si se selecciona explícitamente,
la cantidad informada para un mes.

La sección **Precio sugerido Bayer** permite registrar o modificar
`precio_sin_igv`, `precio_igv`, `precio_con_igv`,
`precio_descuento`, `precio_vigencia_desde` y
`precio_vigencia_hasta`. Moneda: PEN, por presentación comercial.
El formulario muestra el historial y botones **Editar** por vigencia.
Editar la misma fecha actualiza el precio de ese código; registrar una
fecha distinta conserva el precio anterior. Si no hay importes, el guardado
de datos del producto no crea ni elimina precios.

La sección **Inventario mensual informado por Bayer** ofrece
**Sin cambios**, **Registrar cantidad (incluido 0)**,
**Registrar mes sin dato** y **Desactivar en este mes (—)**.
La opción predeterminada es Sin cambios; solo al elegir una acción se
modifica el histórico del mes. Registrar meses anteriores no reemplaza
la cantidad del mes más reciente. Todas estas operaciones son independientes
del stock físico Pucchún.

Producto, precio e inventario opcional se guardan en una sola transacción:
un dato inválido impide confirmar cambios parciales. El historial y los
precios de productos activos se consultan por la API autenticada.

## Botones de retorno y navegación

Se unificaron los botones **Volver al listado**, **Volver al catálogo**
y **Volver al panel** en vistas de edición, importación, detalle
y configuración. Se muestran a la **izquierda, antes del título**,
con flecha hacia atrás y fondo azul institucional.
Los botones Cancelar de los formularios se mantienen independientes.

## API REST de los dos sistemas

Se conserva el login Bearer actual (`POST /api/v1/auth/login`), su expiración y las rutas anteriores.

| Ruta GET (Bearer) | Información |
|---|---|
| `/api/v1/pucchun/all` | Ventas, guías, stock publicado Pucchún |
| `/api/v1/pucchun/sales` | Ventas |
| `/api/v1/pucchun/shipments` | Guías |
| `/api/v1/pucchun/inventory` | Snapshots publicados |
| `/api/v1/pucchun/latest-stock` | Último snapshot publicado por almacén/producto/unidad |
| `/api/v1/bayer/products` | Productos Bayer activos (automático) |
| `/api/v1/bayer/reported-inventory` | Histórico mensual Bayer activo, con `stockMarker` |
| `/api/v1/bayer/prices` | Precios referenciales de productos Bayer activos |
| `/api/v1/bayer/all` | Tres datasets históricos Pucchún **y** tres nuevos datasets Bayer |

El endpoint `/api/v1/bayer/all` conserva `sales`, `shipments` e `inventory` para no romper consumidores previos y agrega `bayerProducts`, `bayerInventory` y `bayerPrices`, con identificación de fuentes en `meta.sources`.

Una importación Bayer **no envía información a ningún servidor de Bayer**: se trata de una API de consulta protegida del ERP, no de una integración remota autenticada con plataformas del proveedor.

## Despliegue en cPanel

1. Respaldar toda la base de datos `userinno_bayer_pucchun` y los archivos del ERP.
2. Actualizar el proyecto en `/home/userinno/bayer-pucchun-web/` desde la rama `integration/frontend-equipo`. Conservar `.env`, carpetas privadas y archivos subidos.
3. Verificar en phpMyAdmin que estén registradas las migraciones previas, 009 a 013, antes de aplicar la nueva.
4. Ejecutar **`database/migrations/014_catalogo_bayer_nativo.sql`** sobre la base del hosting.
5. Confirmar que se crearon `bayer_productos_catalogo`, `bayer_inventario_periodos` y `bayer_precios_referencia`.
6. Aplicar **`database/migrations/015_almacenes_referencia_productos.sql`** para habilitar almacenes de referencia en ambos catálogos.
7. Actualizar navegador con Ctrl+F5. Entrar a **Homologaciones Bayer → Productos Bayer → Importar Excel original** para subir inventarios y precios.
8. Verificar el resultado en Productos Bayer y la API autenticada. Los productos activos ya están disponibles sin publicarlos manualmente.

**Importante:** si la migración 014 ya fue aplicada, no debe repetirse. Para esta mejora se requiere únicamente aplicar la migración 015, sin recrear las tablas anteriores.

## Pruebas

```bat
php tests\integration\bayer_native_catalog_test.php
php tests\unit\bayer_native_preview_test.php
php tests\unit\product_excel_header_aliases_test.php
php tests\integration\bayer_prices_excel_test.php
php tests\integration\bayer_unified_stock_api_test.php
php tests\integration\stock_warehouse_filters_test.php
php tests\integration\excel_warehouse_autocreate_test.php
php tests\integration\bayer_product_editor_navigation_test.php
php tests\integration\api_auth_test.php
php tests\integration\homologations_web_test.php
```

Los ensayos crean y eliminan una base MySQL temporal independiente, sin cargar el `.env` de producción.
