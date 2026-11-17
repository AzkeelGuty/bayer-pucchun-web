# Exportación de guías de remisión y logotipos

La exportación XLSX de **Guías de remisión** utiliza un encabezado por número único de guía, seguido de sus líneas de productos y un subtotal por unidad de medida.

Cada bloque incluye número de guía, fecha, documento e identificación del cliente, documento de venta de origen (si existe), vendedor, sucursal y destino. Esos datos no se repiten en cada producto. Las filas siguientes presentan código de producto, presentación, unidad y cantidad. La última fila de cada unidad contiene el subtotal de esa unidad. Si una guía incluye litros y kilogramos, sus cantidades se totalizan por separado y no se suman indebidamente.

La columna **Estado SUNAT** no se incluye en el Excel de guías. Los datos originales y sus estados permanecen disponibles para los procesos que los necesiten, sin alterar los formatos JSON/TXT ni las operaciones del ERP.

La franja superior azul ahora cubre el ancho íntegro del reporte. Los logotipos Pucchún y Bayer se insertan en las dos últimas columnas del encabezado, dentro del cuadro, con tamaños y proporciones limitados. Esta corrección se aplica a **todos** los Excel que utilizan el exportador común: documentos, stock y guías.

## Verificación

- `php tests/unit/guide_excel_grouping_test.php`: agrupa clientes y números, preserva los productos y separa subtotales por unidad.
- `php tests/unit/guide_excel_xlsx_structure_test.php`: genera un archivo XLSX real, revisa su XML, comprueba que los logos están dentro de la franja institucional y verifica que no se incluya Estado SUNAT.
- `php tests/integration/stock_movements_reports_test.php`: regresión de reportes y saldos.
