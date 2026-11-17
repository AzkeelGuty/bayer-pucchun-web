# Rendimiento del ERP Bayer–Pucchún en cPanel

## Modificaciones del código

1. `asset_url()` ya no emplea `time()` cuando la raíz pública de cPanel está separada de la raíz privada del proyecto. Usa la fecha de modificación del archivo físico o un número de versión configurable estable. Esto permite reutilizar archivos CSS y JS en caché.
2. `public/.htaccess` añade expiraciones de caché para recursos estáticos si el módulo Apache `mod_expires` está habilitado. No cambia la caché del HTML ni de las respuestas de operaciones autenticadas.
3. Los archivos raster PNG, JPEG y WebP grandes se redimensionan en el navegador antes de enviarse a `/configuracion/identidad` solo si ello reduce al menos un 10 % del tamaño original. SVG, ICO y archivos menores no se alteran; si la conversión no está disponible se envía el original. Se conservan la validación de 2 MB del backend y los nombres únicos de archivos.
4. Se añadió una conexión anticipada al CDN de Bootstrap, sin cambiar el orden de carga de Chart.js o las gráficas.
5. Se incorporó `Server-Timing` opcional sin exponer credenciales ni datos privados.

## Activar las mediciones temporalmente

Añadir en el archivo `.env` **del servidor**:

```env
BP_PERFORMANCE_DIAGNOSTICS=1
APP_ASSET_VERSION=20261117-perf1
```

Si `BAYER_PUBLIC_ROOT` coincide con los activos reales, se utiliza su fecha de modificación. Si los archivos están en una carpeta distinta no localizable por PHP, cambiar `APP_ASSET_VERSION` al desplegar nuevas versiones de CSS o JS.

Después de actualizar los archivos de la rama `integration/frontend-equipo`, presionar F12 en el navegador > **Network/Red** > seleccionar una petición a `/guias`, `/dashboard`, `/lookups` o `/configuracion/identidad` > inspeccionar `Server-Timing` en las cabeceras de respuesta:

- `app;dur=...`: duración total de PHP en milisegundos.
- `session;dur=...`: duración de `session_start`, incluida la espera por bloqueo de sesión.
- `db-connect;dur=...`: duración del primer establecimiento de conexión PDO/MySQL.

Comparar con **Waiting (TTFB)** y **Content Download**, y filtrar por imágenes, CSS y JS para examinar tiempos de transferencia. Subir los logos con la pestaña Network abierta: observar especialmente la duración de `POST /configuracion/identidad` y los tamaños reales enviados.

Al finalizar, dejar `BP_PERFORMANCE_DIAGNOSTICS=0`.

## Si la demora persiste

- Si **session** es elevado: revisar llamadas AJAX simultáneas y el almacenamiento/bloqueo de sesiones PHP del hosting.
- Si **db-connect** es elevado: revisar `DB_HOST` y la proximidad de MySQL en cPanel. Verificar con el hosting el hostname correcto antes de cambiarlo.
- Si **app** es elevado con **session** y **db-connect** bajos: revisar las consultas de esa pantalla mediante Performance Schema / slow query log y los índices disponibles. La migración 006 incluye índices de autocompletados y búsqueda; no volver a ejecutar otras migraciones a ciegas.
- Si PHP es rápido pero la página se ve lenta: comprobar tamaño y caché de assets, CDN externo, transferencia y tiempos del navegador.
- En el selector de PHP de cPanel, comprobar versión compatible, OPcache y límites de recursos del plan compartido.

No hay una garantía de tiempos sin medición sobre el hosting real. Estos cambios preservan estructura de BD, roles, comprobantes, guías, stock, API y lógica tributaria.
