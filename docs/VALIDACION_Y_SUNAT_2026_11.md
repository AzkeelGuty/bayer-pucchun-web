# Ajustes de aprobación y visualización — noviembre de 2026

## Corregido

- `Validar`, `Validar todo`, `Publicar` y `Publicar todo` se envían por POST normal. De esta manera se aplican los controles CSRF y se muestra el resultado o el error real, en vez de ocultarse por el navegador de páginas parciales.
- La navegación AJAX sincroniza los avisos de éxito. Cuando una petición POST recibe 403, 419 u otro error, informa del fallo en lugar de recargar la página y aparentar que el botón no respondió.
- Los roles ADMIN y SUPERVISOR reciben mediante la migración 011 los permisos operativos de flujo acordes con su función. ADMIN: `validation.review` y `publications.publish`; SUPERVISOR: `validation.review`. No se conceden permisos nuevos a DIGITADOR, GERENCIA ni BAYER.
- Se restablecen colores de botones menos saturados, manteniendo texto blanco, iconos, buen tamaño y estados hover/focus.
- En la captura de documentos y guías la ayuda de número interno se reduce a una indicación breve.

## Instalación local

Respalda tu base de datos y descarga `integration/frontend-equipo`. Aplica **la migración 011** si la instalación ya tiene 010:

```bat
cd /d C:\xampp\htdocs\bayer-pucchun-web
git fetch origin
git switch integration/frontend-equipo
git pull --ff-only origin integration/frontend-equipo
C:\xampp\mysql\bin\mysql.exe -u root -p NOMBRE_BASE < database\migrations\011_permisos_flujo_aprobacion.sql
php scripts\maintenance\healthcheck.php
php -S localhost:8000 -t public
```

Sustituye `NOMBRE_BASE` por el nombre de la base configurada en `.env`. Si un botón no está disponible, verifica que haya registros en estado BORRADOR para validar o VALIDADO para publicar.

## Emisión tributaria

`INT-FAC-...`, `INT-BOL-...` e `INT-GD-...` siguen siendo identificadores internos del ERP. No otorgan validez tributaria. Para emitir comprobantes y GRE ante SUNAT se debe integrar un sistema de emisión autorizado (SEE del Contribuyente u OSE/PSE habilitado, según corresponda), con series fiscales, certificado/firma cuando proceda, generación XML conforme especificaciones, envío y procesamiento de respuesta/CDR, así como resguardo de evidencias. No basta con el botón de validación interna.

Fuentes oficiales: https://cpe.sunat.gob.pe/sistema_emision/see_contribuyente y https://orientacion.sunat.gob.pe/guia-de-remision-electronica-modelo-general.
