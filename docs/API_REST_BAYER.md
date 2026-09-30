# API REST Bayer — Integración automática

La API permite que el sistema interno de Bayer consulte directamente la información **PUBLICADA** de Pucchún, sin ingresar al portal ni descargar archivos manualmente.

## Objetivo

Flujo esperado:

```text
Sistema interno Bayer
        |
        | HTTPS + Bearer Token
        v
/api/v1/bayer/*
        |
        v
Pucchún Data Hub
        |
        v
Solo información PUBLICADA
```

Las exportaciones XLSX, JSON, TXT y PDF continúan disponibles para usuarios humanos. La API es el canal de integración sistema-a-sistema.

## Configuración

La API permanece deshabilitada por defecto. En el archivo `.env` del servidor:

```env
API_ENABLED=true
API_TOKEN=REEMPLAZAR_POR_UN_TOKEN_LARGO_ALEATORIO
```

Recomendaciones:

- usar HTTPS en producción;
- usar un token largo, aleatorio y exclusivo para la integración;
- no guardar el token en Git;
- rotar el token si se sospecha exposición;
- enviar el token únicamente en el encabezado `Authorization`.

## Autenticación

Todas las rutas requieren:

```http
Authorization: Bearer <API_TOKEN>
Accept: application/json
```

Si la credencial no es válida, la API responde `401 Unauthorized`.

## Endpoints

### Información de la API

```http
GET /api/v1/bayer
```

Devuelve versión, endpoints disponibles y filtros soportados.

### Toda la información publicada

```http
GET /api/v1/bayer/all
```

Devuelve en una sola llamada:

- `sales`: documentos/ventas publicados;
- `shipments`: guías de remisión publicadas;
- `inventory`: stock publicado.

Este endpoint no aplica el límite visual del portal; devuelve todos los registros que cumplan los filtros.

### Documentos / ventas

```http
GET /api/v1/bayer/sales
```

### Guías de remisión

```http
GET /api/v1/bayer/shipments
```

### Stock

```http
GET /api/v1/bayer/inventory
```

## Filtros opcionales

Los cuatro endpoints de datos aceptan:

| Parámetro | Descripción | Ejemplo |
| --- | --- | --- |
| `from` | Fecha inicial, inclusiva | `2026-09-01` |
| `to` | Fecha final, inclusiva | `2026-09-30` |
| `branch` | Código de sucursal | `PUC-CHI` |
| `q` | Búsqueda por documento, cliente o producto según dataset | `maiz` |

Ejemplo:

```http
GET /api/v1/bayer/all?from=2026-09-01&to=2026-09-30&branch=PUC-CHI
```

## Ejemplo con cURL

```bash
curl -H "Authorization: Bearer TU_TOKEN" \
     -H "Accept: application/json" \
     "https://tu-dominio.com/api/v1/bayer/all"
```

## Ejemplo en PowerShell

```powershell
$headers = @{
    Authorization = "Bearer TU_TOKEN"
    Accept = "application/json"
}

Invoke-RestMethod `
    -Uri "https://tu-dominio.com/api/v1/bayer/all" `
    -Headers $headers `
    -Method Get
```

## Formato de respuesta

Ejemplo simplificado de `GET /api/v1/bayer/sales`:

```json
{
  "meta": {
    "apiVersion": "v1",
    "requestId": "8b9d...",
    "dataset": "sales",
    "generatedAt": "2026-09-23T13:40:00-05:00",
    "dataStatus": "PUBLICADO",
    "recordCount": 2,
    "filters": {}
  },
  "data": [
    {
      "dealerId": "20609990001",
      "dealerName": "Pucchún Agro S.A.C.",
      "documentTypeId": "FAC",
      "documentType": "Factura",
      "documentNumber": "F001-000125",
      "documentDate": "2026-09-05",
      "salesId": "VEN-001",
      "salesName": "Luis Alberto Ramírez Soto",
      "branchId": "PUC-CHI",
      "branchName": "Sede Chincha",
      "customerId": "20609990011",
      "customerName": "Agrícola Valle Verde S.A.C.",
      "materialId": "PRD-001",
      "materialName": "Bioestimulante foliar 1 L",
      "measureUnit": "LIT",
      "quantity": 2,
      "quantityUnit": "NIU",
      "quantityMeaning": "PRODUCT_COUNT",
      "unitValue": "86.50",
      "province": "Chincha",
      "department": "Ica",
      "district": "Chincha"
    }
  ]
}
```

Cada fila representa una línea de producto publicada. Un mismo documento o guía puede aparecer en varias filas cuando contiene varios productos.

### Interpretación de cantidad y unidad

En la operación de Pucchún, `quantity` representa el **número de productos o presentaciones físicas**, no el peso o volumen neto contenido dentro de cada presentación.

Ejemplos:

| Producto | `measureUnit` | `quantity` | Interpretación |
| --- | --- | ---: | --- |
| Fertilizante soluble 5 kg | `KGM` | `2` | 2 presentaciones del producto de 5 kg |
| Adyuvante agrícola 500 ml | `LTR` | `3` | 3 presentaciones del producto de 500 ml |
| Semilla de maíz híbrido 20 kg | `KGM` | `6` | 6 presentaciones del producto de 20 kg |

Reglas de integración:

- `quantity` se entrega como número entero.
- `quantityUnit` se entrega como `NIU` para indicar conteo de unidades/presentaciones.
- `quantityMeaning` será `PRODUCT_COUNT` para los registros conformes a esta política.
- `measureUnit` conserva la unidad del catálogo o presentación del producto; **no debe interpretarse como la unidad matemática de `quantity`**.
- El contenido neto de una presentación (por ejemplo, 5 kg o 500 ml) actualmente forma parte del nombre/descripción del producto y no se expone como un campo numérico independiente.
- Si existiera un registro histórico fraccionario anterior a esta regla, la API no lo redondea ni lo trunca: lo marca con `quantityMeaning = LEGACY_FRACTIONAL_REVIEW_REQUIRED` para revisión de datos.


## Respuesta del endpoint completo

```json
{
  "meta": {
    "apiVersion": "v1",
    "dataset": "all",
    "dataStatus": "PUBLICADO",
    "recordCount": 8,
    "datasets": {
      "sales": 2,
      "shipments": 2,
      "inventory": 4
    }
  },
  "data": {
    "sales": [],
    "shipments": [],
    "inventory": []
  }
}
```

## Códigos HTTP

| Código | Significado |
| --- | --- |
| `200` | Consulta correcta |
| `401` | Token ausente o inválido |
| `422` | Filtro inválido |
| `500` | Error interno controlado |
| `503` | API deshabilitada o sin configuración válida |

Los errores también se devuelven en JSON y contienen un `requestId` para trazabilidad.

## Seguridad y trazabilidad

- La API nunca devuelve BORRADOR, VALIDADO, OBSERVADO o ANULADO; solo `PUBLICADO`.
- El token no se registra en logs ni auditoría.
- Cada llamada genera un `requestId`.
- El consumo queda registrado en `bitacora_acceso` con IP, agente de usuario y tipo de llamada.
- Las respuestas incluyen `Cache-Control: no-store`.
- Apache reenvía el encabezado `Authorization` a PHP mediante `public/.htaccess`.

## Automatización del lado de Bayer

Bayer puede ejecutar estas consultas desde un proceso programado en su ERP, middleware, ETL, Power Automate, servicio Windows, cron o cualquier cliente HTTP. No es necesario que un usuario abra el portal ni presione un botón de descarga.
