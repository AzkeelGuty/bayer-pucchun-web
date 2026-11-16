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

## Consulta de stock consolidada

En **Stock** se presenta una tabla de **15 productos por página**, combinando
visualización de productos Bayer y Pucchún sin combinar sus cantidades.
La fuente aparece en cada fila. Se incluyen filtros de búsqueda por código o
producto, origen y situación; y un umbral configurable de stock bajo.

- **Bajo**: cantidad positiva menor o igual al umbral configurado (10 por defecto).
- **En cero**: cantidad reportada igual a 0.
- **Negativo**: solo cuando existan saldos negativos en datos históricos.
- **Sin reporte**: ausencia de datos; nunca se reemplaza por cero.

Las existencias Pucchún proceden de los últimos snapshots PUBLICADOS por almacén
y producto; el inventario Bayer procede de su último mes reportado.
El listado de movimientos de Stock mantiene su propia paginación de 15 y
el procedimiento de captura y revisión.

Los selectores de consulta aplican el filtro al elegir una opción, sin exigir
un clic adicional en Buscar. Esto no afecta los formularios de registro o edición.

## Estados y eliminación

- **Editar:** cambia el nombre o marca del código Bayer seleccionado.
- **Desactivar:** retira el producto del catálogo activo de la API y conserva su histórico.
- **Activar:** vuelve a mostrar el código activo y su información guardada.
- **Eliminar:** requiere confirmar con el código exacto; borra sus referencias Bayer,
  nunca el inventario físico de Pucchún.

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
6. Actualizar navegador con Ctrl+F5. Entrar a **Homologaciones Bayer → Productos Bayer → Importar Excel original** para subir inventarios y precios.
7. Verificar el resultado en Productos Bayer y la API autenticada. Los productos activos ya están disponibles sin publicarlos manualmente.

**Importante:** el módulo utiliza la migración 014 existente. No se requiere nueva migración ni ejecutar nuevamente SQL antiguos.

## Pruebas

```bat
php tests\integration\bayer_native_catalog_test.php
php tests\unit\bayer_native_preview_test.php
php tests\integration\bayer_prices_excel_test.php
php tests\integration\bayer_unified_stock_api_test.php
php tests\integration\api_auth_test.php
php tests\integration\homologations_web_test.php
```

Los ensayos crean y eliminan una base MySQL temporal independiente, sin cargar el `.env` de producción.
