# Plantillas en español por apartado y productos Pucchún

## Actualización de base de datos

Ejecute primero las migraciones hasta la 017 y después una sola vez:

\`database/migrations/018_productos_pucchun_precio_ajustes.sql\`

La 018 incorpora un precio comercial opcional a \`productos\` y movimientos
auditables de ajustes (\`AJUSTE\`) sin sobrescribir inventarios físicos
publicados ni cantidades informadas por Bayer.

## Catálogos maestros Pucchún

Cada apartado descarga su propia plantilla XLSX (dos hojas: **Carga** e
**Instrucciones**) y muestra sus propias columnas en español. Los
apartados incluyen clientes, productos, proveedores, vendedores, sucursales,
almacenes, unidades, empresas, tipos de documento y ubigeo.

Las cabeceras y filas corresponden al apartado que el usuario eligió.
El sistema reconoce exactamente esas columnas y sus nombres técnicos
históricos, sin exigir que el usuario copie los datos a otra estructura.
La importación actualiza por el identificador único del catálogo:
código de producto, documento de cliente, RUC de empresa, etc.

**Productos Pucchún**: en el editor y el XLSX existe **Precio de venta
(S/)**, opcional, conservando el precio anterior cuando una carga no lo
incluye; y **Stock disponible (unidades)**. Cuando el producto no tiene
precio o inventario, la celda queda vacía.

Los ajustes de cantidad requieren:
- Migraciones 015, 017 y 018 aplicadas.
- Unidad base válida, un almacén asignado y un inventario físico
  previamente PUBLICADO en el almacén seleccionado.
- Cantidad entera no negativa. Se registra la diferencia como AJUSTE,
  con usuario, producto, almacén, fecha y referencia al snapshot.
- La edición de un precio no altera inventarios y la omisión del
  stock en el Excel no implica ponerlo a cero.
- Las salidas de ventas y las anulaciones siguen calculándose sobre
  el mismo kardex. Un ajuste repetido sin cambio no duplica movimientos.

**Importante:** un producto sin conteo físico inicial debe registrar
primero su inventario en **Stock → Nuevo inventario Pucchún**. Esto evita
crear existencias ficticias o sustituir el saldo de otros productos.

## Homologaciones Bayer

Bayer se determina por el apartado que el administrador está utilizando:
no es necesario ingresar \`partner_codigo\` ni períodos de vigencia en
el Excel. Se mantienen los datos necesarios para vincular un registro
Pucchún con su equivalente externo en Bayer, pero los encabezados se
presentan según su naturaleza: **Número de documento Pucchún** para
clientes, **Código de unidad Pucchún** para unidades, etc.

Los catálogos de Bayer y Pucchún siguen independientes. **Productos
Bayer** conserva como identificador obligatorio el **CÓDIGO BAYER**
oficial, porque permite identificar y actualizar correctamente las
filas del reporte original; el sistema no inventa ese código. Las
cabeceras comerciales y los precios e IGV se muestran en español.

Las plantillas anteriores con claves técnicas siguen siendo compatibles,
pero los nuevos archivos no las exigen al usuario.

## Verificación

Las pruebas \`tests/unit/catalog_templates_spanish_test.php\` y
\`tests/integration/stock_movements_reports_test.php\` verifican,
respectivamente, cabeceras por apartado y ajustes físicos con saldos
contabilizados.
