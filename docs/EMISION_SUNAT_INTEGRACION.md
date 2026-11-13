# Integración SUNAT en Bayer–Pucchún Data Hub

## Función incorporada

Se agregó el menú **Administración → Emisión SUNAT** para usuarios `ADMIN`. El módulo separa el flujo **BORRADOR → VALIDADO → PUBLICADO** del envío tributario. Conserva los identificadores internos `INT-FAC-...` y reserva una **serie y correlativo fiscal diferentes**.

El emisor inicial admite **facturas electrónicas tipo 01 con líneas gravadas al IGV del 18 %, moneda PEN, venta al contado, sin descuentos ni detracciones**, usando Greenter UBL 2.1, firma digital y el servicio SOAP `billService` de SUNAT. Es necesario confirmar explícitamente la afectación tributaria de todos los productos y si el **valor unitario incluye o no el IGV** antes de cada envío. El sistema valida el RUC de empresa/cliente, las unidades de medida disponibles y que el documento interno esté PUBLICADO y VIGENTE.

**Otras operaciones no se envían todavía desde este módulo:** boletas (su resumen diario puede requerir procedimiento diferenciado), notas de crédito/débito (documentos vinculados y motivos), casos exonerados/inafectos, productos con ISC/ICBPER, detracciones, exportaciones y **GRE** (requiere motivos, transportista, direcciones, vehículo, ubigeos y REST/OAuth2). Se seguirá usando la emisión oficial SEE-SOL o proveedor autorizado para estas operaciones hasta integrar sus flujos específicos.

La aplicación **nunca** denomina aceptado un comprobante sólo por haberlo guardado o publicado internamente. El resultado se deriva de la **CDR real**, y la aceptación en `BETA` sólo es una prueba sin validez tributaria.

## Instalación en Windows/XAMPP

1. Respaldar la base MySQL que usa el `.env` y actualizar el código de la rama `integration/frontend-equipo`:

```bat
cd /d C:\xampp\htdocs\bayer-pucchun-web
git fetch origin
git switch integration/frontend-equipo
git pull --ff-only origin integration/frontend-equipo
```

2. Instalar dependencias de emisión usando Composer y habilitar en `php.ini` las extensiones `soap`, `dom`, `openssl`, `zip`, `intl` y `mbstring`:

```bat
composer install
php -m
```

3. Aplicar la **migración 012** *después de la 011* (usar la base configurada en `.env`; se recomienda primero `mysqldump`):

```bat
C:\xampp\mysql\bin\mysql.exe -u root -p NOMBRE_BASE < database\migrations\012_sunat_emision_cpe.sql
php scripts\maintenance\healthcheck.php
```

La migración es reejecutable y no reemplaza ni borra documentos ni guías antiguas. Crea los contadores `sunat_series_fiscales` separados por **ambiente** y la trazabilidad `sunat_emisiones`.

4. En SUNAT, comprobar que la empresa sea emisor electrónico (RUC activo/habido), disponer de usuario/clave SOL autorizado para emisión y registrar el certificado digital correspondiente. Los datos y certificados **reales** del emisor no deben incluirse en Git.

5. Crear manualmente la carpeta privada `storage/private/sunat/` (fuera de `public_html`), guardar allí un **certificado PEM con clave privada y cadena pública**, con acceso restringido al usuario de PHP. Si el certificado es PFX/P12, convertirlo de forma segura a PEM. No colocar archivos PEM ni claves SOL en una carpeta pública o el código fuente.

6. En el archivo privado `.env`:

```dotenv
SUNAT_ENABLED=false
SUNAT_MODE=beta
SUNAT_ALLOW_PRODUCTION=false
SUNAT_RUC=<RUC_REAL_DEL_EMISOR>
SUNAT_SOL_USER=<USUARIO_SOL_AUTORIZADO>
SUNAT_SOL_PASSWORD=<CLAVE_SOL_REAL>
SUNAT_CERT_PEM_PATH=<RUTA_ABSOLUTA_ARCHIVO_PEM_PRIVADO>
SUNAT_SERIE_FACTURA=<SERIE_FISCAL_F___>
SUNAT_CORRELATIVO_INICIAL=<SIGUIENTE_CORRELATIVO_LIBRE_REAL>
SUNAT_UBIGEO=<UBIGEO_6_DIGITOS>
SUNAT_DEPARTAMENTO=<DEPARTAMENTO_REAL>
SUNAT_PROVINCIA=<PROVINCIA_REAL>
SUNAT_DISTRITO=<DISTRITO_REAL>
SUNAT_DIRECCION=<DIRECCION_FISCAL_REAL>
SUNAT_NOMBRE_COMERCIAL=<NOMBRE_COMERCIAL_REAL>
SUNAT_IGV_PORCENTAJE=18
```

Estos son **nombres de variables** y marcadores explicativos, no credenciales utilizables. La serie y el siguiente correlativo deben coordinarse con los emitidos realmente por la empresa; el sistema **no** los recupera automáticamente de SUNAT.

7. Tras configurar una cuenta de pruebas autorizada y el certificado, cambiar `SUNAT_ENABLED=true` con `SUNAT_MODE=beta`, reiniciar PHP y verificar en el panel que la configuración aparezca completa. Ejecutar pruebas únicamente con datos del ambiente beta.

8. Para operar en producción, confirmar el procedimiento tributario, certificado, serie/folio y la verificación de documentos, establecer `SUNAT_MODE=produccion`, `SUNAT_ALLOW_PRODUCTION=true` y confirmar manualmente cada envío escribiendo `EMITIR`. Mantener el acceso a `.env` y al PEM estrictamente restringido.

## Uso del panel

1. Crear una **factura** del ERP con cliente RUC y precios documentados.
2. Validarla y publicarla **internamente**.
3. Ir a **Administración → Emisión SUNAT**.
4. Confirmar si cada `valor_unitario` **incluye IGV** o si es **sin IGV**, y marcar que todas las líneas son gravadas al 18 %. Si hay líneas exoneradas, detracciones o tributos especiales **no usar la modalidad inicial**.
5. Presionar **Emitir factura**. El ERP reserva la numeración fiscal en una transacción, genera UBL 2.1, firma con certificado real y envía a SUNAT. Guarda XML firmado y CDR en `storage/private/sunat/emisiones`. Los archivos se descargan exclusivamente desde una sesión de administrador.
6. Revisar `ACEPTADO`, `ACEPTADO_OBSERVADO`, `RECHAZADO` o `SIN_RESPUESTA` y el código del CDR.

**Un envío SIN_RESPUESTA nunca debe reenviarse automáticamente:** primero se debe consultar el estado/constancia ante SUNAT por su serie y correlativo. El ERP bloquea segundos intentos sobre el mismo documento interno; la conciliación de respuestas inciertas se realiza con el procedimiento oficial.

## Aspectos fiscales y operativos

- `INT-FAC-00000001` es identificador interno, no es `F001-1`. El CDR sólo valida el segundo.
- La validación interna del ERP no confiere validez tributaria.
- CDR recibida de BETA no tiene efectos de producción.
- Si una factura es tributariamente aceptada, su anulación o modificación requiere el procedimiento SUNAT pertinente, no sólo cambiar el estado interno a ANULADO.
- La pasarela no realiza una consulta de recuperación automática de CDR ni el envío de resumen diario de boletas.
- Los datos del cliente, monto, unidad, precios y serie deben reflejar fielmente la operación real.
- Nunca subir `.env`, PEM/PFX, XML privados o CDR a GitHub.

## Servicios y documentación técnica oficiales

- [SUNAT – Sistema de Emisión del Contribuyente](https://cpe.sunat.gob.pe/sistema_emision/see_contribuyente)
- [SUNAT – Servicios web disponibles](https://orientacion.sunat.gob.pe/guias-manuales-y-servicios-web)
- [SUNAT – Guías y manuales / UBL 2.1 / Reglas de validación 2026](https://cpe.sunat.gob.pe/guias-y-manuales)
- [SUNAT – Manual de Servicios GRE](https://cpe.sunat.gob.pe/sites/default/files/inline-files/Manual_Servicios_GRE.pdf)
- [Greenter – Guía oficial de inicio](https://greenter.dev/starter/)
- [Greenter – Producción y servicio GRE](https://greenter.dev/production/)
