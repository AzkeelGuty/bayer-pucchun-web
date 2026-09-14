# Base de datos — Schema v2 (Día 1 de Pedro)

Implementación de datos para revisión e integración en la rama
`feature/backend-datahub-db-pedro`.

**No desplegar estos repositorios con los controladores v1 sin adaptar su conexión.**
La adaptación queda pendiente para Alisson, según el alcance acordado: los controladores aún llaman
`create($_POST)` y `status(id, estado)`. La API nueva recibe cabecera,
lista de detalles e ID del actor; el método libre `status()` fue retirado.
Las llamadas antiguas de captura/cambio de estado no son compatibles. No se cambió
frontend, rutas, controladores ni Services. 

## Requisitos

- PHP 8.1 o posterior con PDO MySQL.
- InnoDB, utf8mb4 y modo SQL estricto (incluidos NO_ZERO_DATE y NO_ZERO_IN_DATE).
- Verificado con PHP 8.2.12 y MariaDB 10.4.32.
- MySQL requiere al menos 8.0.16 para CHECK efectivos; no se ejecutaron pruebas
  sobre MySQL en esta entrega. Validar en el motor exacto del hosting antes del despliegue.
- La actualización usa una rutina temporal: requiere CREATE ROUTINE, ALTER ROUTINE,
  EXECUTE y permisos de DDL/DML sobre la base objetivo.
- Ejecutar los scripts con un cliente que soporte DELIMITER, por ejemplo mysql.
  El servidor no recibe las instrucciones DELIMITER directamente.

## Instalación nueva

1. Crear una base **vacía**, con utf8mb4 y utf8mb4_unicode_ci.
2. Importar `database/schemas/002_schema_v2.sql`.
3. Importar `database/seeders/001_seed.sql` y cambiar inmediatamente
   la contraseña de bootstrap descrita en la documentación original.
4. Comprobar `SELECT * FROM schema_migrations;`: debe figurar versión 2.
5. Para probar los cinco roles, importar `database/seeders/002_usuarios_prueba.sql`.
6. Para poblar catálogos, homologaciones, documentos, guías, stock, validaciones, publicaciones, exportaciones y auditoría, importar `database/seeders/003_datos_ejemplo.sql`.
7. Conectar la aplicación y ejecutar las pruebas funcionales.


Ejemplo desde el cliente mysql, tras conectarse a la base vacía:

```sql
SOURCE database/schemas/002_schema_v2.sql;
SOURCE database/seeders/001_seed.sql;
SELECT * FROM schema_migrations;
```


## Actualización desde Schema v1


1. En phpMyAdmin se seleccionó la base `bayer_pucchun`.
2. Desde **Importar**, se ejecutó `database/migrations/002_schema_v2.sql` una sola vez.
3. phpMyAdmin confirmó la importación exitosa: **6 consultas ejecutadas**.
4. Se verificaron `schema_migrations.version = 2`, las **40 tablas** y la clave única completa de stock.

**Pendiente:** adaptar y conectar los controladores para Alisson y probar los formularios con la base v2. 

Ejemplo de respaldo, desde el directorio del repositorio y usando una ruta privada externa:

```text
mysqldump -h HOST -u USUARIO -p --single-transaction --routines --triggers --result-file=RUTA_PRIVADA/antes_v2.sql BASE
mysql -h HOST -u USUARIO -p BASE
```

Luego, dentro del cliente conectado:

```sql
SOURCE database/migrations/002_schema_v2.sql;
SELECT * FROM schema_migrations;
```

El script incluye preflight: detecta estados/fechas/cantidades inválidos, referencias
huérfanas, stock duplicado por fecha/almacén/producto/lote/unidad, lotes incompatibles/vencidos
y versiones de publicación ambiguas **antes del primer ALTER TABLE**. No borra ni
corrige datos de negocio silenciosamente. No usar la opción `--force`.

### Si la migración falla

- **Fallo en preflight:** no se alteraron tablas de negocio. Conciliar los registros
  indicados con el responsable y volver a ejecutar. Puede quedar la rutina temporal;
  el script la reemplaza al comenzar.
- **Fallo durante DDL:** MySQL/MariaDB hacen commit implícito por sentencia. La migración
  completa no tiene rollback transaccional. Mantener el sistema detenido, restaurar
  el respaldo v1 verificado y volver a intentar tras resolver la causa.
- **Segunda ejecución sobre v2:** se rechaza expresamente. Consultar el marcador
  `schema_migrations`; no ejecutar ALTERs manuales para omitir el rechazo.
- No hay script DOWN destructivo. La recuperación prevista es restaurar el respaldo
  y el código v1 correspondiente, antes de permitir nuevas escrituras.

La migración inicial permanece intacta. Los actores/fechas históricos desconocidos
quedan NULL; no se atribuyen operaciones pasadas al usuario que ejecuta la migración.

## Pruebas reproducibles

Desde la raíz del repositorio:

```text
php tests/integration/schema_v2_test.php
php tests/integration/repositories_test.php
php -d zend.assertions=1 -d assert.exception=1 tests/unit/validator_test.php
```

Las pruebas de integración **no leen .env**. Se conectan a 127.0.0.1:3306 con el usuario
local root sin contraseña de forma predeterminada. Para otro entorno, establecer
`TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_USER` y `TEST_DB_PASS`
en el entorno del proceso. No imprimir ni versionar la contraseña.

El usuario de pruebas necesita CREATE/DROP DATABASE. Cada ejecución crea nombres
aleatorios `bayer_test_<16 caracteres hexadecimales>`, carga solo fixtures
sintéticos y elimina exclusivamente sus propias bases en finally. Nunca reutiliza
la base de la aplicación. Una interrupción forzada podría dejar una base temporal;
verificar su nombre/propietario antes de eliminarla.

El test de repositorios necesita proc_open y PHP CLI para dos procesos concurrentes.
Las comprobaciones no dependen de que PHP tenga habilitado assert.
Si la prueba unitaria no puede escribir en el directorio de sesiones de PHP, ejecutarla
con `-d session.save_path=RUTA_TEMPORAL_ESCRIBIBLE`.

## Evidencia de esta entrega

Ejecución local del 11 de septiembre de 2026:

- Instalación limpia y migración v1 → v2 con comparación de las 40 tablas,
  conservación de datos y rechazo de casos inválidos.
- 86 comprobaciones del esquema, incluyendo cargas separadas válidas y duplicados entre cabeceras.
- 122 comprobaciones de repositorios, incluidos rollback, transacciones externas,
  edición por versión, consultas parametrizadas, lotes/vencimientos e idempotencia.
- Dos procesos independientes comprueban reintentos de Stock, duplicados de la clave
  completa con distintas claves de solicitud y cambios de estado concurrentes.
- Prueba unitaria original del validador y revisión de sintaxis PHP.

Consultar `docs/database/model.md` para contratos de métodos, decisiones de
unicidad y obligaciones de los Services. La revisión humana, el PR, merge y despliegue
del equipo definidos por DT-03/DT-05 quedan fuera de esta ejecución local.

## Corrección documental del 11 de septiembre

Se retiró la restricción de una cabecera por día y almacén. DT-02 §5.3 define la
clave completa fecha + almacén + producto + lote + unidad. El esquema permite cargas
separadas y aplica esa unicidad en los detalles mediante un índice único y una FK
compuesta que mantiene fecha/almacén coherentes con la cabecera. Véase el modelo
para la justificación de las columnas e índices. Se añadieron nombre_archivo y
user_agent conforme al plan de mejora p.5; los servicios deberán registrar sus valores.

Esta migración 002 sustituye únicamente la propuesta local v2 que no fue desplegada.
No debe reaplicarse sobre una base v2 ya instalada: ese caso requiere una migración
posterior específica. En esta tarea solo se utilizaron bases sintéticas en un servidor
MariaDB temporal ligado a 127.0.0.1:33317.

Las pruebas acreditan esquema y persistencia. El contrato está documentado para
revisión con Alisson, pero su aceptación e integración siguen pendientes. No se
declara cumplido el cierre de equipo del día 1 ni la Definition of Done completa.

## Archivos de esquema y migraciones

- `database/schemas/001_schema.sql`: estructura original v1, conservada sin cambios.
- `database/schemas/002_schema_v2.sql`: estructura completa v2 para una base nueva y vacía.
- `database/migrations/001_initial_schema.sql`: migración inicial histórica v1, intacta.
- `database/migrations/002_schema_v2.sql`: actualización de una base existente v1 a v2.

Los dos archivos llamados `002_schema_v2.sql` tienen propósitos distintos: el de
`schemas` crea la estructura completa; el de `migrations` transforma una base v1.
Para una instalación nueva v2, importar el de `schemas` y luego el seeder.
No ejecutar los esquemas v1 y v2 consecutivamente sobre la misma base.


## Datos funcionales para pruebas

Los seeders de prueba están separados de la estructura para que nunca se mezclen con un despliegue de producción.

Orden recomendado en una base de pruebas nueva:

```sql
SOURCE database/schemas/002_schema_v2.sql;
SOURCE database/seeders/001_seed.sql;
SOURCE database/seeders/002_usuarios_prueba.sql;
SOURCE database/seeders/003_datos_ejemplo.sql;
```

En phpMyAdmin, importar los mismos archivos en ese orden. El seeder `002_usuarios_prueba.sql` también normaliza las cuentas heredadas de versiones anteriores sin romper sus referencias históricas. El seeder `003_datos_ejemplo.sql` es idempotente para los registros principales y deja información en estados BORRADOR, VALIDADO, OBSERVADO y PUBLICADO para poder revisar cada flujo del sistema.

**No importar los seeders 002 y 003 en producción.** En producción se crean usuarios reales desde el módulo **Usuarios y accesos** y los datos operativos deben provenir de la operación real o de la integración autorizada.
