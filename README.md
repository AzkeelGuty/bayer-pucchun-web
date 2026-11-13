# Bayer - Pucchún Data Hub

Plataforma web para capturar, validar, publicar, analizar y entregar a Bayer los datasets de **Documentos, Guías de Remisión y Stock**.

## Estado actual

La integración técnica principal se trabaja en `develop`. La rama `main` se reserva para versiones estables.

El backend v2 incluye:
- autenticación y sesiones seguras;
- roles y estructura de permisos;
- repositorios normalizados;
- Schema v2 con cabecera/detalle;
- estados BORRADOR / VALIDADO / PUBLICADO / OBSERVADO / ANULADO;
- auditoría y bitácora de accesos;
- idempotencia y control de concurrencia en Stock;
- pruebas unitarias e integración.

**Pendiente funcional:** terminar la conexión de los formularios de captura y workflow con Schema v2 antes de considerar completa la versión funcional.


## Cálculos de Documentos y Guías

El ERP calcula por producto **cantidad × valor unitario** y muestra subtotal, IGV/IPM (18 %) y total en Documentos. Es obligatorio elegir si los valores unitarios incluyen IGV para evitar cobrarlo dos veces. Las Guías presentan cantidades agrupadas por unidad y valorización comercial **opcional**.

Esta mejora requiere ejecutar la migración **013** después de 012 y no modifica registros previos. Más detalles: [Resumen comercial de Documentos y Guías](docs/RESUMENES_COMERCIALES_DOCUMENTOS_GUIAS.md).

## Emisión electrónica SUNAT (módulo independiente)

El panel `/sunat`, exclusivo de `ADMIN`, permite revisar la habilitación del emisor y emitir facturas **gravadas al IGV** mediante Greenter (UBL 2.1 / firma XML / servicio SUNAT), tras configurar datos tributarios auténticos. Guarda XML y CDR en almacenamiento privado y separa la numeración fiscal del identificador interno `INT-...`.

- **Deshabilitado por defecto:** requiere `SUNAT_ENABLED=true`, certificado digital y credenciales SOL reales.
- **Producción requiere autorización adicional:** `SUNAT_ALLOW_PRODUCTION=true`.
- **No se han incluido certificados ni credenciales de ninguna empresa.** Sin ellos no es posible enviar un comprobante real.
- Esta primera modalidad no emite boletas, notas ni GRE; cada procedimiento requiere sus datos, validaciones y comunicaciones específicas.
- **Para bases existentes:** después de aplicar las migraciones 009, 010 y 011, aplicar `database/migrations/012_sunat_emision_cpe.sql`. Instalar dependencias con `composer install`.

Instalación, pruebas y alcance: **[docs/EMISION_SUNAT_INTEGRACION.md](docs/EMISION_SUNAT_INTEGRACION.md)**.

## Arquitectura

```text
public/index.php
      |
    routes
      |
 Controllers
      |
 Services / Policies / Validators
      |
 Repositories
      |
 MySQL / MariaDB
      |
Views / Exportaciones / API
```

## Requisitos

- PHP 8.1+
- MySQL 8.0.16+ o MariaDB compatible
- PDO MySQL
- mbstring
- JSON
- ZipArchive
- Apache con mod_rewrite o equivalente

## Instalación nueva con Schema v2

1. Copiar `.env.example` a `.env`.
2. Configurar `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` y `APP_URL`.
3. Crear una base vacía con `utf8mb4`.
4. Importar `database/schemas/002_schema_v2.sql`.
5. Importar `database/seeders/001_seed.sql`.
6. Verificar `SELECT * FROM schema_migrations;`.
7. Configurar el Document Root hacia `public/`.
8. Abrir `/login`.
9. Cambiar inmediatamente la credencial bootstrap antes de cualquier despliegue real.

Para actualizar una base v1 existente, revisar primero `database/README.md` y usar la migración `database/migrations/002_schema_v2.sql` con respaldo previo.

## API REST Bayer

La integración sistema-a-sistema está disponible en `/api/v1/bayer` y permanece deshabilitada por defecto mediante `API_ENABLED=false`.

Endpoints principales:

- `GET /api/v1/bayer/all`: devuelve en una sola llamada todos los documentos/ventas, guías y stock **PUBLICADOS**;
- `GET /api/v1/bayer/sales`;
- `GET /api/v1/bayer/shipments`;
- `GET /api/v1/bayer/inventory`.

La autenticación usa `Authorization: Bearer <API_TOKEN>`. El canal REST devuelve JSON, admite filtros opcionales de fecha/sucursal/búsqueda y registra el consumo en la bitácora de acceso. Las exportaciones XLSX/JSON/TXT/PDF se mantienen como alternativa manual.

En los datasets operativos, `quantity` representa un **conteo entero de productos/presentaciones**. El API identifica este criterio con `quantityUnit=NIU` y `quantityMeaning=PRODUCT_COUNT`; `measureUnit` se conserva como referencia de la unidad del catálogo/presentación.

Configuración y ejemplos de integración: `docs/API_REST_BAYER.md`.

En producción se debe usar HTTPS, un token largo y aleatorio, rotación de credenciales y límites de consumo adecuados al servidor.

## Trabajo en equipo

No descargar ZIP para trabajar normalmente y no subir carpetas manualmente desde la interfaz web.

Primera descarga:

```bash
git clone https://github.com/AzkeelGuty/bayer-pucchun-web.git
cd bayer-pucchun-web
git fetch origin
git switch develop
git pull origin develop
```

Consultar:
- `CONTRIBUTING.md`
- `docs/manuals/github-workflow.md`

## Seguridad

Nunca versionar:
- `.env`;
- contraseñas;
- tokens API reales;
- credenciales de cPanel;
- backups de producción;
- datos reales sensibles.

## Flujo de ramas

```text
feature/* -> Pull Request -> develop -> pruebas -> Pull Request -> main
```

## Nota de alcance

La solución actual es una plataforma de gestión de información / Data Hub preparada para una futura integración con ERP. La integración automática con ERP es evolución futura y no reemplaza el control de validación, publicación y auditoría.


### Migración de comprobantes
Ejecutar `database/migrations/008_estado_comprobante.sql` para agregar el estado comercial VIGENTE/ANULADO e incorporar Factura, Boleta, Nota de crédito y Nota de débito.
