# Flujo GitHub del equipo Bayer Pucchún

## Objetivo

Trabajar cinco programadores sobre el mismo repositorio sin sobrescribir código, conservando trazabilidad, revisión y una rama estable.

## Flujo oficial

```text
feature/*  -> Pull Request -> develop -> pruebas -> Pull Request -> main
```

### Frontend
- `feature/frontend-gianpiere`
- `feature/frontend-michel`
- `feature/frontend-aldhair`

### Backend
- `feature/backend-core-security-alisson`
- `feature/backend-datahub-db-pedro`

Las ramas históricas pueden conservarse como referencia, pero los trabajos nuevos deben partir de `develop`.

## Descarga inicial

```bash
git clone https://github.com/AzkeelGuty/bayer-pucchun-web.git
cd bayer-pucchun-web
git fetch origin
git switch develop
git pull origin develop
```

## Descargar actualizaciones

```bash
git fetch origin
git switch develop
git pull origin develop
```

Para actualizar tu rama:

```bash
git switch feature/tu-rama
git merge develop
```

## Subir actualizaciones

```bash
git status
git add .
git commit -m "tipo(modulo): descripcion"
git push
```

## No usar como flujo normal

- Download ZIP
- Upload files desde GitHub web
- copiar y pegar carpetas entre compañeros
- trabajar todos sobre `main`
- reemplazar carpetas completas sin revisar diffs

## Solución de conflictos

Cuando `git merge develop` indique conflictos:

1. Abrir los archivos marcados.
2. Elegir o combinar correctamente ambos cambios.
3. Quitar los marcadores `<<<<<<<`, `=======`, `>>>>>>>`.
4. Ejecutar pruebas.
5. Guardar:

```bash
git add .
git commit -m "merge: resuelve conflictos con develop"
git push
```

## Antes de un Pull Request

```bash
git status
php -l archivo.php
```

Y ejecutar las pruebas disponibles del proyecto.

## Producción

`main` representa la entrega estable. Nunca desplegar directamente una rama `feature/*` en producción.
