# API REST Bayer–Pucchún v2

La integración utiliza exclusivamente rutas `/api/v2/`. Consultar el [contrato de datos en español](API_BAYER_V2_ES.md) para los 56 campos, filtros y criterios de entrega.

| Método | Ruta | Función |
|---|---|---|
| POST | `/api/v2/auth/login` | Autenticación y token temporal |
| POST | `/api/v2/auth/logout` | Revocación del token |
| GET | `/api/v2/bayer` | Información de integración |
| GET | `/api/v2/bayer/todo` | Consulta consolidada |
| GET | `/api/v2/bayer/facturas` | Facturas y documentos |
| GET | `/api/v2/bayer/guias` | Guías |
| GET | `/api/v2/bayer/stock` | Stock |

El inicio de sesión conserva `auth.access_token`; enviar `Authorization: Bearer <token>` en las consultas. Los conjuntos principales devuelven `metadatos` y `datos`. Los filtros son `desde`, `hasta`, `sucursal`, `busqueda` y `estado_documento` (1 vigente, 0 anulado). Las respuestas de autenticación e información conservan `meta` y `auth` o `data`, con `apiVersion: v2`.
