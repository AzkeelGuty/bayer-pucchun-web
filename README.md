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

## API

La API REST está deshabilitada por defecto mediante `API_ENABLED=false`. La versión actual es de transición y no debe exponerse como API pública de producción sin completar hardening, tokens por cliente/scopes, rate limiting y auditoría de consumo.

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
