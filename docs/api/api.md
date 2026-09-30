# API REST Bayer

La API REST está implementada como capa opcional de integración sistema-a-sistema y solo expone información con estado **PUBLICADO**.

Configuración en `.env`:

```env
API_ENABLED=true
API_TOKEN=<token seguro>
```

Autenticación:

```http
Authorization: Bearer <API_TOKEN>
Accept: application/json
```

Endpoints:

- `GET /api/v1/bayer/all`
- `GET /api/v1/bayer/sales?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/v1/bayer/shipments?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/v1/bayer/inventory?from=YYYY-MM-DD&to=YYYY-MM-DD`

La cantidad se interpreta como **conteo entero de productos/presentaciones**. La API agrega `quantityUnit = NIU` y `quantityMeaning = PRODUCT_COUNT`. El campo `measureUnit` conserva la unidad del catálogo/presentación del producto y no debe multiplicarse automáticamente por `quantity`.

La especificación completa, filtros, ejemplos y códigos HTTP están en `docs/API_REST_BAYER.md`.

Para una integración corporativa de mayor escala puede evaluarse OAuth2 Client Credentials, rate limiting e IP allowlist.
