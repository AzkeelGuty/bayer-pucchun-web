# Numeración interna automática y botones operativos — 12/11/2026

## Resumen

- Documentos: la interfaz muestra un **número interno estimado** al seleccionar FAC, BOL, NCR o NDB. Al guardar, el servidor **reserva el identificador definitivo** dentro de una transacción; el valor recibido desde el navegador no se utiliza como fuente de verdad.
- Guías: las guías registradas manualmente en el formulario y las generadas a partir de un documento obtienen referencias internas **INT-GD-** automáticamente.
- El contador de cada tipo es independiente y conserva los números históricos ya registrados. El mecanismo utiliza `numeraciones_internas`, `INSERT IGNORE` y `SELECT ... FOR UPDATE` para evitar numeración duplicada con guardados concurrentes.
- Homologaciones Bayer: botones de agregar, importar, plantilla, editar y desactivar con iconos de Bootstrap y la paleta de Catálogos maestros; **Desactivar** aparece en rojo, no amarillo.
- Flujo de aprobación: validar azul, publicar verde, observar ámbar oscuro, anular rojo; texto blanco, iconos y estados hover/focus visibles en Guías, Stock y Documentos.

## Identificadores internos (NO emisión SUNAT)

| Operación | Ejemplo |
|---|---|
| Factura interna | `INT-FAC-00000001` |
| Boleta interna | `INT-BOL-00000001` |
| Nota de crédito interna | `INT-NCR-00000001` |
| Nota de débito interna | `INT-NDB-00000001` |
| Guía interna | `INT-GD-000000000001` |

**Importante:** estos identificadores son exclusivamente de control del ERP. **No** son números de comprobantes emitidos electrónicamente, no sustituyen las series autorizadas o gestionadas por el emisor, ni equivalen a una Guía de Remisión Electrónica emitida ante SUNAT. El ERP no genera XML firmado ni obtiene CDR en esta mejora. Los documentos históricos mantienen su numeración original.

## Actualización de una base de datos existente

1. Respaldar la base de datos local antes de alterar el esquema.
2. Confirmar que la migración 009 ya está aplicada.
3. Ejecutar **una vez** `database/migrations/010_numeracion_interna_automatica.sql` sobre la misma base configurada en el `.env`. La migración es reejecutable y no borra documentos, guías ni clientes.
4. Descargar `integration/frontend-equipo` y reiniciar el servidor PHP.

Ejemplo desde CMD en Windows/XAMPP (sustituir `BASE_LOCAL` por el nombre configurado):

```bat
cd /d C:\xampp\htdocs\bayer-pucchun-web
C:\xampp\mysql\bin\mysql.exe -u root -p BASE_LOCAL < database\migrations\010_numeracion_interna_automatica.sql
php scripts\maintenance\healthcheck.php
php -S localhost:8000 -t public
```

## Pruebas

```bat
php tests\integration\internal_numbering_test.php
php tests\integration\commerce_workflows_test.php
```

Estas pruebas crean bases **temporales sintéticas** y las eliminan al finalizar. Nunca las ejecute contra el nombre de la base real. La prueba de numeración cubre separación por tipo, continuidad histórica, guía automática, rollback sin consumir número y persistencia entre conexiones distintas.

## Referencias tributarias

- SUNAT: [Sistema de Emisión del Contribuyente](https://cpe.sunat.gob.pe/sistema_emision/see_contribuyente).
- SUNAT: [Guía de Remisión Electrónica — Modelo general](https://orientacion.sunat.gob.pe/guia-de-remision-electronica-modelo-general).
