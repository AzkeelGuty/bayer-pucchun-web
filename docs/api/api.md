# API REST futura
Está implementada como capa opcional y deshabilitada por defecto.

`.env`:
`API_ENABLED=true`
`API_TOKEN=<token seguro>`

Endpoints:
- `GET /api/v1/bayer/sales?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/v1/bayer/shipments?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/v1/bayer/inventory?from=YYYY-MM-DD&to=YYYY-MM-DD`

Header: `Authorization: Bearer <API_TOKEN>`.

Para producción corporativa futura se recomienda migrar a OAuth2 Client Credentials, rate limiting e IP allowlist.
