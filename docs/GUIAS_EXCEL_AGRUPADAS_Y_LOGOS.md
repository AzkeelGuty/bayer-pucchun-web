# Exportación de guías de remisión y logotipos

La exportación XLSX de **Guías de remisión** utiliza un encabezado por número único de guía, seguido de sus líneas de productos y una única fila de total por guía.

Cada bloque incluye número de guía, fecha, documento e identificación del cliente, documento de venta de origen (si existe), vendedor, sucursal y destino. Esos datos no se repiten en cada producto. Las filas siguientes presentan código de producto, presentación, unidad y cantidad. Al final del bloque se genera **una única fila TOTAL DE GUÍA**, con número de productos distintos y la suma numérica de las cantidades. Cuando existen unidades diferentes, la celda de unidad indica **MIXTA**, pero la columna DESTINO no muestra ningún desglose. Ejemplo de cantidad acumulada: **3 KGM + 3 LTR + 5 NIU = 11** como indicador numérico, sin equiparar unidades diferentes. El destino real continúa en la cabecera.

El Excel presenta **VALOR UNITARIO (S/)**, **IMPORTE (S/)**, **IGV (S/)** y **TOTAL (S/)**. Solo utiliza los importes registrados en guias_detalle.valor_unitario y la modalidad almacenada en guias_cabecera.precio_igv_modalidad. Respeta el cálculo comercial y redondeo por línea del ERP. Por ejemplo: 3 × 10 + 3 × 20 + 5 × 30 = S/ 240.00 de importe; IGV adicional de S/ 43.20, total comercial de S/ 283.20. El importe final de la guía se muestra en su única fila verde.

Cuando alguna línea no tiene precio, el importe total de esa guía permanece en blanco, sin utilizar precios ficticios. Los campos comerciales adicionales se consultan exclusivamente para XLSX; las salidas JSON/TXT/API conservan su estructura anterior.

La columna **Estado SUNAT** no se incluye en el Excel de guías. Los datos originales y sus estados permanecen disponibles para los procesos que los necesiten, sin alterar los formatos JSON/TXT ni las operaciones del ERP.

La franja superior azul ahora cubre el ancho íntegro del reporte. Los logotipos Pucchún y Bayer se insertan en las dos últimas columnas del encabezado, dentro del cuadro, con tamaños y proporciones limitados. Esta corrección se aplica a **todos** los Excel que utilizan el exportador común: documentos, stock y guías.

## Verificación

- `php tests/unit/guide_excel_grouping_test.php`: agrupa clientes y números, preserva productos y comprueba un único total de cantidad e importe comercial con IGV.
- `php tests/unit/guide_excel_xlsx_structure_test.php`: genera un archivo XLSX real, revisa su XML, verifica los importes y sus dos decimales, los logos en la franja institucional y la ausencia de Estado SUNAT y Desglose.
- `php tests/integration/stock_movements_reports_test.php`: regresión de reportes y saldos.
