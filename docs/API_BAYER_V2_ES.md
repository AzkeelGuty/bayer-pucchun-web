# API Bayer v2 en español

La API v1 permanece compatible. Nueva entrega: Bearer obtenido en /api/v1/auth/login y GET /api/v2/bayer/todo, /facturas, /guias, /stock. Facturas incluye los tipos comerciales existentes (factura, boleta, notas). La respuesta usa metadatos y datos. Filtros desde, hasta, sucursal, busqueda y estado_documento (1 vigente, 0 anulado); también acepta nombres v1.

Aplicar database/migrations/019_integracion_bayer_espanol.sql después de 018. Completar Empresas (RUC y razón social reales), Vendedores (DNI, nombres y apellidos), Sucursales (nombre y ubicación), Almacenes (nombre y ubicación opcional). No se rellenan datos desconocidos con datos ficticios.

Facturas: 24 campos. Estado comercial numérico 1/0; precio_unitario es el valor registrado e importe_linea es cantidad por precio_unitario, sin agregar IGV adicional. Origen de despacho explícito o ubicación de sucursal; nunca ubicación del cliente.

Guías: 21 campos. Tipo de documento cliente DNI/RUC cuando corresponda. Origen de despacho separado del destino. EMISION_EXTERNA_DECLARADA expresa un registro del administrador, no un CDR validado. Número de documento de origen fiscal o nulo.

Stock: 11 campos. Sin columnas de sucursal, unidad o significado redundantes de cantidad. Almacén con ubicación propia o de la sucursal. Lote y vencimiento opcionales.

No se exportan números INT-* en v2. Facturas aceptadas en PRODUCCION toman serie y correlativo de la emisión; comprobantes y GRE emitidos fuera del ERP se registran en el detalle (ADMIN, publicado) con confirmación expresa. Documentos pendientes, BETA, rechazados o sin respuesta no se presentan como emitidos. Los identificadores internos se mantienen para la integridad de la base. Las series se validan por tipo. No se transforma un correlativo interno en fiscal. Emisión automática GRE y boletas aún no implementada: registrar los documentos efectivamente emitidos por otra vía. No se activa SUNAT en producción.
