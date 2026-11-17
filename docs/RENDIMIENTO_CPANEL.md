# Rendimiento del ERP Bayer–Pucchún en cPanel

## Modificaciones del código

1. `asset_url()` ya no emplea `time()` cuando la raíz pública de cPanel está separada de la raíz privada del proyecto. Usa la fecha de modificación del archivo físico o un número de versión configurable estable. Esto permite reutilizar archivos CSS y JS en caché.
2. `public/.htaccess` añade expiraciones de caché para recursos estáticos si el módulo Apache `mod_expires` está habilitado. No cambia la caché del HTML ni de las respuestas de operaciones autenticadas.
3. Los archivos raster PNG, JPEG y WebP grandes se redimensionan en el navegador antes de enviarse a `/configuracion/identidad` solo si ello reduce al menos un 10 % del tamaño original. SVG, ICO y archivos menores no se alteran; si la conversión no está disponible se envía el original. Se conservan la validación de 2 MB del backend y los nombres únicos de archivos.
4. Se añadió una conexión anticipada al CDN de Bootstrap. Chart.js dejó de cargarse globalmente: `soft-page-init.js` solo lo descarga cuando aparece un panel con gráficos; el panel funciona tanto tras una carga completa como con navegación interna.
5. Se incorporó `Server-Timing` opcional sin exponer credenciales ni datos privados.
6. En cada solicitud autenticada, `AuthService::user()` obtiene datos del usuario y roles activos mediante **una consulta** en vez de dos. No mantiene una caché de roles: la revocación de acceso continúa siendo inmediata.
7. `DashboardService` calcula los indicadores generales en una consulta y preagrupa por producto y por estado antes de unir resultados. El registro de actividad consulta un máximo de ocho filas por origen, sin cargar todos los registros históricos al combinar las tres fuentes.
8. `UnifiedStockService` reutiliza los conteos de filas del resumen para paginar y evita ejecutar nuevamente el consolidado completo; `overview()` devuelve únicamente los indicadores. Reportes utiliza `overview()` en vez de cargar también una lista paginada de stock.
9. Las consultas automáticas duplicadas de `app.js` fueron retiradas. `soft-page-init.js` administra los refrescos cuando son necesarios, y `navigation.js` controla la búsqueda incremental. El historial de exportaciones es manual, con actualización después de una descarga; las tablas secundarias limitan su consulta periódica a un minuto.
10. Se añadieron pruebas de regresión de rendimiento a ambos flujos CI (`PHP CI` y `Quality checks`) para evitar que regresen los comportamientos anteriores.

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

## Recorrido de verificación por módulos

Realizar una navegación normal por: `/dashboard`, `/documentos`, `/guias`, `/stock`, `/maestros`, `/homologaciones`, `/reportes`, `/validacion`, `/seguridad`, `/bayer` y `/configuracion/identidad`.

Para cada pantalla registrar `Waiting (TTFB)`, `app`, `session` y `db-connect`, observando los recursos del mismo recorrido. Los formularios de documentos/guías/stock deben consultar productos y clientes con `/lookups` mientras el usuario escribe, sin descargar sus catálogos completos por cada pulsación. Los cambios automáticos de estado o búsquedas no deben generar dos solicitudes idénticas.

Para el panel y stock, comparar con las consultas de `DashboardService` y `UnifiedStockService`; si el tiempo `app` sigue alto, capturar planes `EXPLAIN` de las consultas específicas y revisar índices reales antes de crear otros.

### Verificar que phpMyAdmin usa la base correcta

```sql
SELECT DATABASE() AS base_actual;
SELECT TABLE_NAME, TABLE_ROWS AS registros_estimados,
       ROUND((DATA_LENGTH + INDEX_LENGTH)/1048576,2) AS tamano_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'
ORDER BY DATA_LENGTH + INDEX_LENGTH DESC LIMIT 20;
```

Una respuesta de cero registros o columnas a la consulta de tamaño no confirma lentitud. Verificar la selección de base antes de interpretarla.

### Verificar la versión desplegada

Si `Server-Timing` no aparece después de activar `BP_PERFORMANCE_DIAGNOSTICS=1`, comprobar que se ha publicado el archivo `public/index.php` actualizado, que el .env efectivo llega a PHP y que se está usando la rama `integration/frontend-equipo`. Actualizar también los JS del directorio `public/assets` (en `public_html` si corresponde). Desactivar `Disable cache` en Chrome después del primer despliegue y hacer una recarga forzada una vez para renovar recursos versionados.

## Si la demora persiste

- Si **session** es elevado: revisar llamadas AJAX simultáneas y el almacenamiento/bloqueo de sesiones PHP del hosting.
- Si **db-connect** es elevado: revisar `DB_HOST` y la proximidad de MySQL en cPanel. Verificar con el hosting el hostname correcto antes de cambiarlo.
- Si **app** es elevado con **session** y **db-connect** bajos: revisar las consultas de esa pantalla mediante Performance Schema / slow query log y los índices disponibles. La migración 006 incluye índices de autocompletados y búsqueda; no volver a ejecutar otras migraciones a ciegas.
- Si PHP es rápido pero la página se ve lenta: comprobar tamaño y caché de assets, CDN externo, transferencia y tiempos del navegador.
- En el selector de PHP de cPanel, comprobar versión compatible, OPcache y límites de recursos del plan compartido.

No hay una garantía de tiempos sin medición sobre el hosting real. Estos cambios preservan estructura de BD, roles, comprobantes, guías, stock, API y lógica tributaria.
