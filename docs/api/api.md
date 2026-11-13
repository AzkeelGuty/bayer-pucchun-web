# API REST Bayer

La API REST expone únicamente información con estado **PUBLICADO** y requiere autenticación de usuario.

## Activación

```env
API_ENABLED=true
API_TOKEN_TTL=28800
```

En instalaciones existentes ejecutar primero:

```text
database/migrations/005_api_tokens.sql
database/migrations/008_estado_comprobante.sql
```

## Flujo de autenticación

1. Enviar email y contraseña a `POST /api/v1/auth/login`.
2. El sistema valida que la cuenta esté activa y tenga rol **ADMIN, SUPERVISOR, GERENCIA o BAYER**.
3. La API devuelve un Bearer token temporal.
4. Enviar ese token en `Authorization: Bearer <token>` para consultar los endpoints.
5. `POST /api/v1/auth/logout` revoca el token.

No se usa un `API_TOKEN` fijo en el archivo `.env`.

## Endpoints

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/bayer`
- `GET /api/v1/bayer/all`
- `GET /api/v1/bayer/sales?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/v1/bayer/shipments?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/v1/bayer/inventory?from=YYYY-MM-DD&to=YYYY-MM-DD`

La cantidad se interpreta como **conteo entero de productos/presentaciones**. La especificación completa, filtros, ejemplos y códigos HTTP están en `docs/API_REST_BAYER.md`.


## Estado del comprobante

Los documentos publicados incluyen `documentStatus` con valores `VIGENTE` o `ANULADO`. Este campo es independiente del estado del flujo interno del ERP.

Filtro opcional para documentos/ventas:

```text
GET /api/v1/bayer/sales?document_status=ANULADO
```

La migración `008_estado_comprobante.sql` también incorpora Factura (01), Boleta (03), Nota de crédito (07) y Nota de débito (08).
