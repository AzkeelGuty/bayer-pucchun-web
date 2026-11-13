# Cálculo comercial en Documentos y Guías

## Alcance

Los formularios de **Nuevo documento, Editar documento, Nueva guía y Editar guía** calculan los importes sin recurrir a calculadoras externas. La misma lógica se ejecuta en el servidor al guardar y vuelve a utilizarse al consultar un registro. Los cálculos no dependen de valores ocultos enviados por el navegador.

### Documentos (facturas, boletas y otros)

| Producto | Cantidad | Valor unitario | Importe |
|---|---:|---:|---:|
| A | 4 | S/ 750.00 | S/ 3,000.00 |
| B | 5 | S/ 125.00 | S/ 625.00 |

**Si los valores son sin IGV:** subtotal **S/ 3,625.00**, IGV/IPM (18 %) **S/ 652.50**, total **S/ 4,277.50**.

**Si los valores ya incluyen IGV:** importe comercial **S/ 3,625.00**, base imponible **S/ 3,072.03**, IGV/IPM incluido **S/ 552.97**. El sistema no duplica el impuesto.

El formulario exige seleccionar **«El valor unitario incluye IGV: Sí / No»**. Al añadir, eliminar o editar filas, el resumen se actualiza automáticamente y el servidor calcula nuevamente los importes en céntimos enteros. Se desagrega el impuesto por línea, redondeando a 2 decimales y sumando los resultados.

### Guías de remisión

La guía presenta el listado de productos, **cantidad por producto** y **totales agrupados por unidad de medida**. Por ejemplo, 4 kilogramos y 5 litros se muestran por separado; nunca se suman falsamente como «9 unidades iguales».

La **valorización comercial es opcional**: una guía creada solo para traslado no requiere precio ni subtotal. Si se introduce un precio en cualquier producto, se deben completar todos los valores y elegir si incluyen IGV. Entonces se muestran los importes, subtotal, IGV y **valor comercial referencial**. No constituye ni reemplaza un comprobante electrónico de venta.

Cuando se genera una nueva guía desde un documento, se copian **cliente, vendedor, sucursal, productos, cantidades, valores unitarios y modalidad del IGV**. Los registros anteriores se conservan; el sistema no inventa precios para guías históricas.

### Compatibilidad y persistencia

La migración **013_calculos_totales_documentos_guias.sql** incorpora:

- `documentos_cabecera.precio_igv_modalidad`
- `guias_cabecera.precio_igv_modalidad`
- `guias_detalle.valor_unitario`

Todas las columnas nuevas son opcionales y no alteran ni borran registros antiguos. En documentos antiguos sin modalidad IGV, se muestra el monto registrado y se indica **IGV sin desagregar**, sin presumir que el precio incluye o excluye impuesto. En guías anteriores sin valores unitarios se muestran solo las cantidades. Al editar un documento antiguo, se solicita definir la modalidad antes de guardar.

**Importante:** el importe de cada fila y los totales se calculan desde las cantidades y valores unitarios almacenados, no se almacenan sumas redundantes susceptibles de quedar desactualizadas. La modalidad se guarda para reproducir el cálculo.

## Actualización local XAMPP (después de migración 012)

```bat
cd /d C:\xampp\htdocs\bayer-pucchun-web
git fetch origin
git switch integration/frontend-equipo
git pull --ff-only origin integration/frontend-equipo
```

Haga antes un respaldo de la base identificada en `.env`:

```bat
C:\xampp\mysql\bin\mysqldump.exe -u root -p --single-transaction NOMBRE_BASE > "%USERPROFILE%\Desktop\respaldo_bayer_pre013.sql"
C:\xampp\mysql\bin\mysql.exe -u root -p NOMBRE_BASE < database\migrations\013_calculos_totales_documentos_guias.sql
php scripts\maintenance\healthcheck.php
```

Sustituya `NOMBRE_BASE` por el nombre real de la base. La migración puede volver a ejecutarse sin duplicar columnas.

Después reinicie el servidor PHP y actualice el navegador con **Ctrl + F5**.

## Pruebas automatizadas

```bat
node tests\unit\commercial_totals_test.js
php tests\integration\commercial_totals_test.php
```

Las pruebas PHP requieren MariaDB configurado para el entorno de ensayo. La base temporal se crea y elimina automáticamente. Se comprueban el caso de S/ 3,625.00, IGV incluido/no incluido, redondeos, documentos editados, guías con/sin precios, agrupación de cantidades y registros históricos.

**Fuentes tributarias**:
- https://emprender.sunat.gob.pe/principales-impuestos/impuesto-general-las-ventas-igv/impuesto-general-las-ventas
- https://orientacion.sunat.gob.pe/guia-de-remision-electronica-modelo-general
