# Reportes consolidados y movimientos de inventario (migración 017)

## 1. Despliegue

1. Actualice la rama `integration/frontend-equipo` tras integrar el PR.
2. Antes de operar, ejecute una vez en la base `bayer_pucchun` el archivo
   `database/migrations/017_movimientos_stock_publicados.sql`.
3. Verifique que exista la tabla `stock_movimientos`. La aplicación avisa en
   las páginas Stock y Reportes si falta esta migración.
4. Registre y publique inventario físico inicial por almacén en el módulo Stock,
   con la unidad correcta para cada producto.
5. Asigne a los productos su almacén de referencia en el catálogo Pucchún
   cuando exista más de uno por sucursal.

## 2. Descuento automático y trazabilidad

- Al guardar un documento solo se crea un BORRADOR: **no** descuenta.
- Al pasar de VALIDADO a PUBLICADO se realiza en una misma transacción
  la publicación y el registro de las salidas del producto Pucchún.
- El sistema bloquea los productos durante el descuento, comprueba la
  existencia física y rechaza cantidades que superarían el saldo.
- Los movimientos se guardan en `stock_movimientos` con documento o guía
  de origen, línea de detalle, almacén, unidad, cantidad y usuario.
- La guía generada desde un documento que ya descontó no vuelve a
  descontar ese inventario. Si una guía vinculada salió primero,
  la posterior publicación del documento tampoco duplica la salida.
- Al anular un documento/guía publicado, o anular el comprobante comercial,
  se registra una reversión idempotente de la salida correspondiente.
- No se sobrescriben los snapshots históricos ni se mezclan códigos
  Bayer con los códigos internos Pucchún.
- Publicar un nuevo inventario físico crea un **nuevo snapshot** de
  referencia: los movimientos anteriores permanecen en la auditoría y
  dejan de alterar el saldo del nuevo conteo.
- Si se registra un movimiento de salida con unidad distinta a la
  usada en el inventario, el saldo no se transforma entre LT, ML, G o KG:
  será necesario corregir la unidad correspondiente.

## 3. Reportes y exportaciones

**Stock:** exportación conjunta (separada por origen) de todos los productos
del catálogo Bayer y Pucchún, con código, presentación, almacén, unidad,
cantidad, fecha/período y estado. Incluye productos sin inventario
informado, que se distinguen claramente de cantidad cero.

El panel permite filtrar por origen, almacén y estado, con contadores
de productos totales, disponibles, stock bajo, cero, riesgo y sin dato.
El reporte presenta el último snapshot publicado Pucchún actualizado
con las salidas y reversiones de `stock_movimientos`; para Bayer
presenta el reporte mensual del canal sin reducirlo por ventas Pucchún.

**Documentos:** exporta los detalles por cliente, tipo/documento, vendedor,
sucursal, fecha, producto, unidad, cantidad, precio unitario y valor
de cada línea, además de la ubicación disponible de cliente.

**Guías:** exporta cliente e identificación, documento de origen cuando
corresponda, número y fecha de guía, estado SUNAT, vendedor, sucursal,
producto, unidad, cantidad y destino geográfico registrado.

Los formatos XLSX, PDF, TXT y JSON reutilizan estas columnas de
control. La exportación registra usuario, fecha, filtros y cantidad
de filas. No se cambió la paleta institucional.

## 4. Logotipos Excel

Ambos logotipos se insertan dentro de las dos últimas columnas del
encabezado de la hoja, respetando sus proporciones. La ubicación
se calcula respecto del ancho real del reporte; no se insertan
columnas de imagen adicionales fuera de la tabla.

## 5. Validación

Ejecute la suite automatizada o la prueba concreta:

```cmd
php tests/integration/stock_movements_reports_test.php
```

Requiere las mismas variables de conexión a MySQL utilizadas por
los demás tests de integración, y ejecuta sobre una base temporal.
