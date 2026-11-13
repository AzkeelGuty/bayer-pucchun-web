# Importación de homologaciones Bayer: productos sin código interno registrado

## Problema resuelto

El Excel de **Homologaciones Bayer → Productos Bayer** puede contener referencias de producto que aún no están en **Catálogos maestros → Productos**. Por ejemplo, la fila 2 con `codigo_interno=86216353` y `nombre_bayer=ABACUS EC18 4X4L BOT PE`.

Anteriormente la importación se interrumpía indicando que el código no existía. El comportamiento ahora permite optar por **registrar los productos faltantes** durante la carga, sin modificar la lógica de las otras cinco homologaciones.

## Cómo usarlo

1. Ir a **Homologaciones Bayer → Productos Bayer → Importar Excel**.
2. Descargar la plantilla del sistema y utilizar sus ocho encabezados sin cambiarlos:
   `partner_codigo`, `codigo_interno`, `codigo_bayer`, `nombre_bayer`, `estado`, `valid_from`, `valid_until`, `unidad_codigo`.
3. Mantener `codigo_interno` como **texto**, en especial si empieza con cero. El partner `BAYER` debe estar registrado.
4. Si hay códigos internos nuevos, marcar **Registrar automáticamente productos con código interno faltante**.
5. Importar. El sistema crea las homologaciones y, solo si marcaste la opción, crea los productos maestros faltantes **INACTIVOS** con el código interno y la denominación incluida en la hoja. No se sustituyen ni modifican los productos existentes.
6. Ir a **Catálogos maestros → Productos** para asignar unidad base real de Pucchún, revisar la descripción/categoría/marca y **activar** los productos cuando estén completos.

La columna `unidad_codigo` vincula la unidad Bayer con una unidad ya registrada, pero **no determina automáticamente la unidad base de Pucchún**. Puede dejarse vacía. El resto de columnas de vigencia también son opcionales.

La columna `codigo_bayer` preserva exactamente el identificador externo introducido, sin generar códigos ficticios. Si se utiliza `TEST-BAY-0003`, queda como dato de ensayo y se debe reemplazar por el identificador autorizado antes de un intercambio real con Bayer.

## Garantías

- La opción de registro está **desmarcada por defecto**.
- Solo existe para **Productos Bayer**; clientes, vendedores, sucursales, unidades y almacenes mantienen el procedimiento anterior.
- El proceso es **transaccional**: ante un error de cualquiera de las filas, se revierten tanto los productos nuevos como las homologaciones de esa importación.
- Si el mismo Excel se importa otra vez, las homologaciones se actualizan por partner + producto sin crear duplicados.
- Un producto nuevo no pasa automáticamente a ventas ni puede seleccionarse como activo hasta que sea validado y activado en catálogo maestro.
- No requiere migración SQL adicional.

## Archivos que se deben actualizar en cPanel

Dentro de `/home/userinno/bayer-pucchun-web/`:

- `app/Services/HomologationService.php`
- `app/Controllers/HomologationController.php`
- `app/Views/backoffice/homologation_import.php`

Tras actualizar, reiniciar PHP o restablecer OPcache si aplica y refrescar la página. No se necesita modificar `public_html/assets` ni importar nuevos SQL para esta corrección.

## Pruebas

La prueba `tests/integration/homologation_missing_products_test.php` utiliza MariaDB aislado y un XLSX con ejemplos de código numérico, código con cero inicial, producto inexistente, unidad opcional, reimportación y reversión transaccional. Se ejecuta automáticamente en **PHP CI** y **Quality checks**.
