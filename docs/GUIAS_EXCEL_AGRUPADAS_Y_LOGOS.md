# Exportación de guías de remisión y logotipos

La exportación XLSX de **Guías de remisión** utiliza un encabezado por número único de guía, seguido de sus líneas de productos y una única fila de total por guía.

Cada bloque incluye número de guía, fecha, documento e identificación del cliente, documento de venta de origen (si existe), vendedor, sucursal y destino. Esos datos no se repiten en cada producto. Las filas siguientes presentan código de producto, presentación, unidad y cantidad. Al final del bloque se genera **una única fila TOTAL DE GUÍA**, con número de productos distintos y la suma numérica de las cantidades. Cuando existen unidades diferentes, la celda de unidad indica **MIXTA** y el desglose conserva la unidad original de cada cantidad (ejemplo: **3 KGM + 3 LTR + 5 NIU = 11 de cantidad acumulada**). Este acumulado es un indicador de control, no una conversión física entre kilogramos, litros y unidades.

La columna **Estado SUNAT** no se incluye en el Excel de guías. Los datos originales y sus estados permanecen disponibles para los procesos que los necesiten, sin alterar los formatos JSON/TXT ni las operaciones del ERP.

La franja superior azul ahora cubre el ancho íntegro del reporte. Los logotipos Pucchún y Bayer se insertan en las dos últimas columnas del encabezado, dentro del cuadro, con tamaños y proporciones limitados. Esta corrección se aplica a **todos** los Excel que utilizan el exportador común: documentos, stock y guías.

## Verificación

- `php tests/unit/guide_excel_grouping_test.php`: agrupa clientes y números, preserva los productos y comprueba un único total de cantidad con desglose por unidad.
- `php tests/unit/guide_excel_xlsx_structure_test.php`: genera un archivo XLSX real, revisa su XML, comprueba que los logos están dentro de la franja institucional y verifica que no se incluya Estado SUNAT.
- `php tests/integration/stock_movements_reports_test.php`: regresión de reportes y saldos.
