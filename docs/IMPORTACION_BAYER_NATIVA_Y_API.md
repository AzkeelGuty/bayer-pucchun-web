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

## Actualización de precios Bayer mediante XLSX o CSV

En **Productos Bayer → Importar / actualizar precios Excel**, subir el archivo original
con las siguientes columnas reconocibles:

- `codigo_bayer`: identificador único oficial de Bayer, tratado como texto.
- `nombre_bayer`: descripción de la presentación comercial (informativa).
- `moneda`: PEN (o columna opcional, por defecto PEN).
- `precio_sugerido_sin_igv`, `igv_porcentaje` y `precio_sugerido_con_igv`.
- `descuento_dealer_porcentaje`: opcional.
- `vigencia_desde`: fecha de la lista (`YYYY-MM-DD`).
- `vigencia_hasta`: fecha final opcional, se deja en blanco si el documento no la indica.
- `presentacion_referencia`: opcional, solo informativa; no es unidad de stock ni divisor de precio.

El programa **analiza** el XLSX o CSV sin escribir datos en la base de datos; identifica
las hojas de precios y muestra los primeros 20 registros y los códigos presentes o ausentes.
Después de seleccionar una hoja y marcar la confirmación, se actualizan únicamente sus códigos.
**Cancelar y descartar** elimina el archivo temporal y no ejecuta ninguna importación.

Cada lista se relaciona por **código Bayer + fecha inicial**. Volver a importar el
mismo archivo no duplica precios. Cuando cambia el precio en una fecha existente,
actualiza ese registro. Si la fecha es nueva, crea una nueva vigencia y conserva la
anterior como histórico. Los productos cuyos códigos no existan se reportan y la
carga entera se rechaza sin crear productos inventados. Los cambios quedan pendientes
de publicación en la API hasta revisión.

**Presentaciones:** 12X1L, 4X4L, 20X250ML, 1X20KG y 35X200GR son referencias
de envase/presentación del producto. El precio está asociado al código de esa
presentación: **no se multiplica ni divide por litros, mililitros, kilogramos o gramos**.
No se exige `unidad_codigo` para registrar precios. Los inventarios Bayer y Pucchún
no se modifican durante la actualización comercial.

El antiguo botón **Cargar precios Bayer 2026** y su confirmación nativa fueron
retirados del menú. La lista CSV interna antigua se conserva como referencia histórica
y compatibilidad de pruebas, pero no se activa automáticamente desde la interfaz.
El archivo de importación se genera a partir del PDF «Lista Precios Dealers Soles
02.11.26.pdf»: 99 filas con los importes originales, incluidos los IGV de 0 %.
La fecha de término queda opcional porque el PDF no especifica un final de vigencia.

## Estados, publicación y eliminación

- **Editar:** código, nombre, marca (un mismo código no se duplica).
- **Desactivar:** inactiva el producto en el catálogo Bayer sin borrar sus reportes.
- **Activar:** revierte la inactivación manual; no inventa cantidades.
- **Eliminar:** procedimiento separado y definitivo. Requiere escribir el código Bayer exacto; elimina los reportes y precios de ese código, **no** registros Pucchún.
- **Publicar API:** publicación explícita solo de datos revisados. La importación actualiza los datos sin publicarlos automáticamente.

La acción de eliminación utiliza POST con protección CSRF, no un enlace GET destructivo.

## API REST de los dos sistemas

Se conserva el login Bearer actual (`POST /api/v1/auth/login`), su expiración y las rutas anteriores.

| Ruta GET (Bearer) | Información |
|---|---|
| `/api/v1/pucchun/all` | Ventas, guías, stock publicado Pucchún |
| `/api/v1/pucchun/sales` | Ventas |
| `/api/v1/pucchun/shipments` | Guías |
| `/api/v1/pucchun/inventory` | Snapshots publicados |
| `/api/v1/pucchun/latest-stock` | Último snapshot publicado por almacén/producto/unidad |
| `/api/v1/bayer/products` | Productos Bayer publicados |
| `/api/v1/bayer/reported-inventory` | Histórico mensual publicado, con `stockMarker` |
| `/api/v1/bayer/prices` | Precios referenciales de productos publicados |
| `/api/v1/bayer/all` | Tres datasets históricos Pucchún **y** tres nuevos datasets Bayer |

El endpoint `/api/v1/bayer/all` conserva `sales`, `shipments` e `inventory` para no romper consumidores previos y agrega `bayerProducts`, `bayerInventory` y `bayerPrices`, con identificación de fuentes en `meta.sources`.

Una importación Bayer **no envía información a ningún servidor de Bayer**: se trata de una API de consulta protegida del ERP, no de una integración remota autenticada con plataformas del proveedor.

## Despliegue en cPanel

1. Respaldar toda la base de datos `userinno_bayer_pucchun` y los archivos del ERP.
2. Actualizar el proyecto en `/home/userinno/bayer-pucchun-web/` desde la rama `integration/frontend-equipo`. Conservar `.env`, carpetas privadas y archivos subidos.
3. Verificar en phpMyAdmin que estén registradas las migraciones previas, 009 a 013, antes de aplicar la nueva.
4. Ejecutar **`database/migrations/014_catalogo_bayer_nativo.sql`** sobre la base del hosting.
5. Confirmar que se crearon `bayer_productos_catalogo`, `bayer_inventario_periodos` y `bayer_precios_referencia`.
6. Actualizar navegador con Ctrl+F5. Entrar a **Homologaciones Bayer → Productos Bayer**, cargar el Excel original o la lista de precios.
7. Revisar los datos y publicarlos en la API mediante el botón de publicación correspondiente. Las existencias de Pucchún permanecen independientes.

**Importante:** el botón de carga de precios necesita que exista el archivo `database/data/bayer_lista_precios_dealer_2026_11.csv` en la carpeta del proyecto. Esta actualización sí necesita la migración **014**; no se deben ejecutar nuevamente SQL antiguos si ya se encuentran registrados.

## Pruebas

```bat
php tests\integration\bayer_native_catalog_test.php
php tests\unit\bayer_native_preview_test.php
php tests\integration\bayer_prices_excel_test.php
php tests\integration\api_auth_test.php
php tests\integration\homologations_web_test.php
```

Los ensayos crean y eliminan una base MySQL temporal independiente, sin cargar el `.env` de producción.
