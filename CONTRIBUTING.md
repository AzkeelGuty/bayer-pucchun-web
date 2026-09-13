# Guía de colaboración - Bayer Pucchún

Este repositorio trabaja con un flujo simple de Git para evitar sobrescrituras y mantener una versión estable.

## Ramas principales

- `main`: versión estable y presentable.
- `develop`: integración del trabajo aprobado.
- `feature/*`: trabajo individual o por módulo.
- `fix/*`: correcciones puntuales.
- `chore/*`: documentación, CI y mantenimiento.

## Regla obligatoria

No trabajar directamente sobre `main` ni subir archivos manualmente desde la web de GitHub para cambios normales del proyecto.

## Primera descarga del proyecto

```bash
git clone https://github.com/AzkeelGuty/bayer-pucchun-web.git
cd bayer-pucchun-web
git fetch origin
git switch develop
git pull origin develop
```

## Crear una rama de trabajo

```bash
git switch develop
git pull origin develop
git switch -c feature/nombre-del-modulo
git push -u origin feature/nombre-del-modulo
```

## Antes de empezar a programar cada día

```bash
git status
git switch develop
git pull origin develop
git switch feature/nombre-del-modulo
git merge develop
```

Resolver conflictos antes de continuar.

## Guardar y subir avances

```bash
git status
git add .
git commit -m "feat(modulo): descripcion breve"
git push
```

Ejemplos:

```text
feat(frontend): implementa sidebar responsive
feat(documentos): agrega formulario cabecera detalle
fix(stock): corrige validacion de lote
docs(github): actualiza flujo de trabajo
```

## Pull Request

1. Subir la rama con `git push`.
2. Crear PR hacia `develop`.
3. Revisar archivos cambiados.
4. Esperar CI en verde.
5. Corregir observaciones si existen.
6. Fusionar el PR.
7. Solo cuando una versión esté estable, crear PR de `develop` hacia `main`.

## Archivos que nunca deben subirse

- `.env`
- contraseñas de MySQL/cPanel
- tokens API reales
- llaves privadas
- backups de producción
- datos personales o empresariales reales
- archivos temporales o logs

## Si ya descargaste un ZIP

No sigas trabajando desde esa copia. Verifica:

```bash
git status
```

Si aparece `fatal: not a git repository`, clona el repositorio con `git clone`.

## Cómo traer actualizaciones de otros compañeros

Los cambios NO llegan automáticamente a tu computadora.

```bash
git fetch origin
git switch develop
git pull origin develop
```

Luego actualiza tu rama:

```bash
git switch feature/tu-rama
git merge develop
```

## Qué hacer si tienes cambios sin guardar

Antes de cambiar de rama:

```bash
git status
git add .
git commit -m "wip: guarda avance local"
```

O, solo temporalmente:

```bash
git stash
git switch develop
git pull origin develop
git switch feature/tu-rama
git stash pop
```

## Regla de seguridad

Nunca compartir por WhatsApp, capturas o commits un `.env` real. Cada integrante mantiene su propio `.env` local basado en `.env.example`.
