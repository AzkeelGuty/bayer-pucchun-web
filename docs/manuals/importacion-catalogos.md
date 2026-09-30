# Importación de catálogos masivos

Esta carga corresponde al archivo entregado por el cliente con las hojas **PROVEEDORES**, **CLIENTES** y **productos**.

## 1. Ejecutar la migración

En una base Schema v2 existente ejecutar:

```text
database/migrations/004_catalogos_masivos_busqueda.sql
```

La migración crea el catálogo `proveedores` y agrega `tipo_art` a `productos`.

## 2. Archivos CSV

Crear `database/imports/` y colocar:

```text
proveedores.csv
clientes.csv
productos.csv
```

Los códigos deben mantenerse como texto para conservar ceros a la izquierda.

### proveedores.csv

```csv
codigo,nombre
20601417601,ACTIVA SEMILLAS EIRL
```

### clientes.csv

```csv
codigo,nombre
20603576919,INVERSIONES AGROECOLOGICO JK E.I.R.L.
40399451,GRANDA ACOSTA HECTOR AUGUSTO
```

Solo se aceptan documentos de 8 dígitos (DNI) o 11 dígitos (RUC). Los documentos incompletos deben corregirse con la fuente antes de importar; el sistema no inventa el dígito faltante.

### productos.csv

```csv
codigo,nombre,unidad,tipo_art
0000074661,12-12-17 PLUS BASE SOP X 50 KG,NIU,EXONERADO
```

Si un mismo código identifica dos productos distintos, ese código debe quedar fuera del archivo de importación hasta que el cliente confirme cuál es el correcto.

## 3. Ejecutar el importador

Desde Terminal de cPanel, ubicado en la raíz privada del proyecto:

```bash
php scripts/maintenance/import_catalogs.php
```

También puede indicarse otro directorio:

```bash
php scripts/maintenance/import_catalogs.php /home/USUARIO/importacion
```

El proceso usa transacción, actualiza registros ya existentes por su código/documento y conserva los datos geográficos existentes del cliente.

## 4. Verificación

En **Catálogos maestros** comprobar las pestañas Clientes, Productos y Proveedores. El buscador permite consultar por código, DNI/RUC o nombre aunque existan miles de registros.

En **Nuevo documento**, **Nueva guía** y **Nuevo stock**, Cliente y Producto usan búsqueda incremental. La selección continúa disparando los autocompletados ya existentes (vendedor, sucursal, ubicación, unidad y lote).
## Reemplazar los catálogos de demostración por los datos reales

Si la instalación todavía contiene los datos de ejemplo y se desea sustituir **Proveedores, Clientes y Productos** por los archivos reales, usar el modo de reemplazo:

```bash
php scripts/maintenance/import_catalogs.php --replace database/imports
```

Este modo limpia primero, dentro de la misma transacción, los registros demo que dependen de clientes/productos (documentos, guías, stock, lotes, homologaciones relacionadas, validaciones, publicaciones y exportaciones) y luego importa los catálogos nuevos. Si la importación falla, la transacción se revierte para no dejar una base parcialmente vacía.

No usar `DROP TABLE` ni eliminar manualmente solo `clientes` o `productos`, porque existen claves foráneas desde los módulos operativos.

El importador también admite CSV UTF-8 con BOM, habitual al exportar desde Excel.

