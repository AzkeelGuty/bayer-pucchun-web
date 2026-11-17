# Implementación comercial Bayer–Pucchún (12/11/2026)

## Generación de guía desde comprobante
En Documentos > Ver (facturas FAC y boletas BOL vigentes), el botón **Generar guía automáticamente** copia cliente, vendedor, sucursal, productos, unidades y cantidades. Precompleta ubicación del cliente cuando esté completa; la guía queda en borrador. Un documento solo puede vincularse a una guía automática, y un nuevo clic abre la misma guía sin duplicarla.

El número INT-GD-... es **identificador interno** y el estado SUNAT se marca NO_EMITIDA. **No es una GRE SUNAT**, no se firma ni se transmite: la emisión legal exige integrar SEE-SOL o SEE del Contribuyente autorizado, completar datos de traslado y obtener confirmación. El estado interno PUBLICADO no significa aprobación SUNAT.

## Medios de pago
Documento: EFECTIVO, YAPE, PLIN, TRANSFERENCIA, TARJETA u OTRO, con referencia opcional. El medio de pago no cambia la naturaleza de factura/boleta ni genera una CPE SUNAT automáticamente.

## Homologaciones Bayer
Seis catálogos: Productos, Clientes, Unidades, Sucursales, Almacenes, Vendedores. ADMIN puede crear, editar, desactivar, buscar por partner o códigos, descargar plantilla XLSX e importar hasta 2000 filas de forma transaccional. Cada pareja partner + registro interno se actualiza sin duplicarse. Un Excel con filas inválidas no deja importaciones parciales.

## API de lectura
GET /api/v1/bayer/all, /sales, /shipments e /inventory mantienen su Bearer y los filtros actuales. Se agregan paymentMethod en ventas; sourceDocumentId y sunatStatus en envíos. Estos campos son informativos y **no representan aceptación electrónica**.

La API sigue siendo de lectura de datos publicados: el botón interno crea una guía con sesión y permisos. No existen nuevos endpoints API públicos de emisión ni CDR SUNAT.

## Despliegue y pruebas
1. Respaldar la base de datos.
2. Desplegar rama feature/guias-desde-documentos-homologaciones-20261112 e instalar migración database/migrations/009_guias_desde_documentos_y_pagos.sql después de 008. La migración añade columnas de guía-origen, estado SUNAT, medios de pago y referencia.
3. Verificar migración con:
   SELECT version FROM schema_migrations WHERE version=9;
   SHOW COLUMNS FROM guias_cabecera LIKE 'documento_origen_id';
   SHOW COLUMNS FROM documentos_cabecera LIKE 'medio_pago';
4. Probar creación de guía desde boleta/factura, repetición sin duplicado, estados anulados, registro Yape/efectivo, homologaciones manual y Excel, búsqueda y API publicada.
5. Ejecutar Quality checks (PHP 8.1, Node y MariaDB) y revisión de PR antes de fusionar.

## Emisión legal futura
Para automatizar guía de remisión electrónica SUNAT se deben completar datos reglamentarios del traslado (puntos de partida/llegada, transportista o vehículo/conductor según modalidad, motivo, etc.), tener serie fiscal y comunicación vía SEE autorizado, con constancia de recepción/aceptación. Las facturas/boletas electrónicas requieren integración CPE aparte. Mantener credenciales y certificados fuera del repositorio.

Referencias oficiales:
https://cpe.sunat.gob.pe/tipos_de_comprobantes/guias_de_remision
https://cpe.sunat.gob.pe/
