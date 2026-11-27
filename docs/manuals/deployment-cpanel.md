# Despliegue de avance en hosting Linux / cPanel

Esta guía corresponde a la versión actual del proyecto Bayer–Pucchún sobre **Schema v2**.

## Recomendación para el avance

Para una demostración o revisión del avance, desplegar la rama:

```text
integration/frontend-equipo
```

No usar `main` mientras el PR de promoción siga pendiente. La rama de integración contiene las mejoras más recientes del frontend y backend y debe probarse antes de promoverla a `develop/main`.

## Requisitos

- PHP 8.1 o superior.
- MySQL 8.0.16+ o MariaDB compatible.
- Extensiones PHP: pdo_mysql, mbstring, json, zip, openssl.
- Apache con mod_rewrite.
- HTTPS habilitado.
- Document Root apuntando a la carpeta `public/`.

## Estructura recomendada

Mantener el proyecto fuera de la carpeta pública cuando cPanel lo permita:

```text
/home/USUARIO/bayer-pucchun-web/
    app/
    config/
    database/
    docs/
    public/
    routes/
    storage/
    .env
```

Configurar el dominio o subdominio para que su Document Root sea:

```text
/home/USUARIO/bayer-pucchun-web/public
```

De esta forma `.env`, `database/`, `storage/` y el código fuente no quedan expuestos directamente.

## Base de datos nueva para demo

Crear una base MySQL/MariaDB y un usuario desde cPanel, asignando ALL PRIVILEGES a esa base.

Importar en este orden:

```text
database/schemas/002_schema_v2.sql
database/seeders/001_seed.sql
database/seeders/002_usuarios_prueba.sql       (solo demo/pruebas)
database/seeders/003_datos_ejemplo.sql         (solo demo/pruebas)
```

No importar Schema 001 en una instalación nueva.

Los seeders 002 y 003 no deben utilizarse en producción real.

## Base Schema v2 ya existente

Si la base ya fue creada previamente con Schema v2, no borrar ni volver a importar el esquema. Ejecutar la migración compatible:

```text
database/migrations/003_operational_permissions.sql
database/migrations/004_catalogos_masivos_busqueda.sql
```

La migración 003 agrega/asigna los permisos persistidos requeridos por Documentos, Guías y Stock. La migración 004 agrega el catálogo de proveedores y el campo `tipo_art` de productos para la carga masiva, sin reemplazar los registros existentes.

## Archivo .env

Copiar `.env.example` a `.env` y configurar:

```dotenv
APP_NAME="Bayer-Pucchun Data Hub"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO-O-SUBDOMINIO
APP_TIMEZONE=America/Lima

DB_HOST=localhost
DB_PORT=3306
DB_NAME=NOMBRE_BD_CPANEL
DB_USER=USUARIO_BD_CPANEL
DB_PASS=CLAVE_BD

SESSION_NAME=bayer_pucchun_session
API_ENABLED=false
API_TOKEN=TOKEN_LARGO_ALEATORIO
```

No subir `.env` a GitHub ni compartir credenciales en documentación.

## Permisos del servidor

- Carpetas: 755.
- Archivos: 644.
- `storage/`: debe ser escribible por PHP.
- Mantener `APP_DEBUG=false`.
- Activar SSL/HTTPS antes de compartir el enlace.

## Comprobación después del despliegue

Probar como mínimo:

- Login.
- ADMIN: Documentos, Guías, Stock, usuarios, validación/publicación.
- DIGITADOR: captura y edición de sus borradores.
- SUPERVISOR: revisión, validación y publicación sin captura.
- GERENCIA: consulta, dashboard y reportes sin edición.
- BAYER: solo Portal Bayer e información PUBLICADA.
- Exportaciones XLSX, JSON, TXT y PDF.
- Cierre de sesión.
- Error 403 para accesos no autorizados.

## Si el hosting no permite cambiar Document Root

Como último recurso, colocar solamente el contenido de `public/` en el directorio público y mantener el resto del proyecto fuera de él.

La versión actual de `public/index.php` detecta automáticamente el proyecto privado cuando está en `/home/USUARIO/bayer-pucchun-web`. También puede definirse la variable de entorno `BAYER_APP_ROOT` si se usa otro nombre o ruta.

**Importante al actualizar:** reemplazar también `public_html/assets/` con el contenido actual de `public/assets/`. Los enlaces CSS/JS usan `filemtime` para cambiar automáticamente la versión y evitar que el navegador conserve archivos antiguos.

No dejar `.env`, `database/`, `storage/`, backups SQL ni credenciales dentro de una URL pública.

## Seguridad de credenciales

Las credenciales de cPanel, correo, MySQL y tokens son secretos. Si una contraseña fue compartida en una captura o chat, rotarla desde cPanel después de completar el acceso y antes de considerar el sitio listo para terceros.


## Carga masiva de catálogos

Después de ejecutar la migración 004, seguir `docs/manuals/importacion-catalogos.md`. La carga valida DNI/RUC, conserva códigos con ceros a la izquierda y evita insertar códigos conflictivos sin revisión.


## Verificación de cantidades después del despliegue

La captura nueva usa **cantidades enteras de productos o presentaciones**. Documentos y guías requieren cantidades mayores que cero; stock admite cero.

Después de actualizar los archivos en cPanel, ejecutar desde la raíz privada del proyecto:

```bash
php scripts/maintenance/audit_quantities.php
```

El comando es de solo lectura. Si responde `OK`, no existen cantidades fraccionarias históricas. Si lista registros, deben revisarse con la fuente original; el sistema no redondea ni modifica automáticamente datos anteriores.


### Migración de comprobantes
Ejecutar `database/migrations/008_estado_comprobante.sql` para agregar el estado comercial VIGENTE/ANULADO e incorporar Factura, Boleta, Nota de crédito y Nota de débito.


## Solución definitiva para cPanel con raíz pública separada (noviembre de 2026)

Cuando el servidor presenta \`/home/userinno/bayer-pucchun-web/\` y \`/home/userinno/public_html/\` como carpetas separadas, **ambas partes del software deben actualizarse juntas**. Un \`git pull\` solo en el directorio privado no copia los JS, CSS y el front controller a \`public_html\`. El \`public/index.php\` publicado identifica la raíz privada mediante \`BAYER_APP_ROOT\` o, por defecto, el directorio hermano \`bayer-pucchun-web\`; una carpeta distinta como \`bayer-pucchun-web-676\` no será utilizada automáticamente.

### Publicación desde la Terminal de cPanel

En el servidor que utiliza el directorio \`/home/userinno\`, ejecutar **una línea a la vez**:

\`\`\`bash
cd /home/userinno/bayer-pucchun-web
git fetch origin integration/frontend-equipo
git switch integration/frontend-equipo
git pull --ff-only origin integration/frontend-equipo
bash scripts/deploy/cpanel_release.sh --check
bash scripts/deploy/cpanel_release.sh --deploy
\`\`\`

Si Git presenta modificaciones locales o conflictos, detenerse y conservarlas. No utilizar \`git reset --hard\` ni borrar el proyecto. Si el directorio no contiene \`.git\`, actualizar previamente la copia privada desde el ZIP oficial de la rama de integración, conservando el \`.env\`, \`storage/\` y los datos existentes.

El script de despliegue:

- Compara los archivos actuales con la versión privada antes de copiarlos.
- Realiza una copia de seguridad de \`index.php\` y \`.htaccess\` en \`storage/deploy-backups\`.
- Sincroniza todos los CSS y JS de \`public/assets/\` hacia \`public_html/assets/\` sin borrar archivos.
- Instala el nuevo \`public/index.php\` en \`public_html/index.php\`.
- Conserva el \`.htaccess\` de cPanel y sus manejadores PHP; añade caducidad de caché solo si faltan reglas.
- No modifica \`public_html/uploads\`, \`.env\`, \`storage/config\`, \`.user.ini\`, \`php.ini\` ni la base de datos.

### Alternativa desde Administrador de archivos

Después de actualizar el código privado, copiar \`bayer-pucchun-web/public/index.php\` sobre \`public_html/index.php\` y el contenido de \`bayer-pucchun-web/public/assets/\` sobre \`public_html/assets/\`, sobrescribiendo CSS/JS. Conservar los logotipos de \`public_html/uploads/branding/\` y el \`.htaccess\` actual de cPanel. No subir \`app/\`, \`database/\`, \`config/\`, \`.env\` ni \`storage/\` al directorio público.

### Confirmar en el navegador

Entrar a \`https://innovasoft.site/maestros\`, abrir F12 > Network > seleccionar \`maestros\` > Headers > Response Headers. Debe existir:

\`\`\`http
X-BP-Release: bp-20261117-cpanel
\`\`\`

Si falta, el front controller público está desactualizado o se está sirviendo desde otra ruta. Una vez confirmado, activar temporalmente \`BP_PERFORMANCE_DIAGNOSTICS=1\` en el \`.env\` privado y examinar \`Server-Timing\` para diferenciar latencia PHP, sesión y conexión MySQL. Desactivar posteriormente. Si PHP responde rápido pero la navegación sigue lenta, revisar Network > Timing, los recursos externos y las limitaciones CPU/I/O de cPanel, además del estado de OPcache. No interpretar \`Cache-Control: no-store\` en respuestas HTML autenticadas como un error: evita guardar páginas privadas en caché.

No es necesario volver a ejecutar migraciones SQL para corregir la sincronización pública.
