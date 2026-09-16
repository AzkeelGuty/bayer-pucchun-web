# Base oficial de trabajo del equipo

## Rama compartida

La rama de integración validada para revisar el sistema completo es:

```text
integration/frontend-equipo
```

Esta rama consolida el frontend de Gianpiere, Michel y Aldhair sobre la misma base backend y Schema v2 usados por Alisson y Pedro.

## Base de datos

La estructura oficial es:

```text
database/schemas/002_schema_v2.sql
database/migrations/002_schema_v2.sql
```

La estructura es la misma que existe en las ramas backend de Alisson y Pedro. No se sustituyó por una base distinta.

Los seeders actuales añaden permisos, usuarios y datos de prueba compatibles:

```text
database/seeders/001_seed.sql
database/seeders/002_usuarios_prueba.sql
database/seeders/003_datos_ejemplo.sql
```

Los seeders 002 y 003 son solo para pruebas, no para producción.

## Regla Git

Cada programador debe trabajar únicamente en su rama `feature/*`. Para traer la base común:

```bash
git fetch origin
git switch <tu-rama>
git merge origin/develop
```

Una vez que la integración actual sea promovida a `develop`, `develop` será nuevamente la fuente común del equipo.

No copiar carpetas a mano, no descargar ZIP para reemplazar el proyecto y no hacer commits directos en `main`.
