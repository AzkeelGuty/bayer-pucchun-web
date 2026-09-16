# Reconciliación P0 de Alisson con develop

Fecha: 2026-09-16. Rama: `feature/backend-core-security-alisson`.
HEAD previo: `b649741506529adbf4d80cdd47641de27387017e`.
Base del merge: `53a191cde7fdee6888053ed53bfc915b5fdd234a`.
El fetch solicitado durante la reconciliación confirmó que develop seguía en esa base.
Este documento no implica commit, push ni aprobación del PR #5.

## Resolución de los siete conflictos

| Archivo | Aporte de Alisson | Aporte de develop | Resolución |
|---|---|---|---|
| DocumentController.php | Controller delgado y OperationalService | Formulario, detalle, edición, eliminación y workflow | Mantener el wrapper; conectar todas las acciones en OperationalController/OperationalService a las vistas integradas. No recuperar llamadas a repositories antiguos. |
| GuideController.php | Validación v2, permisos y alcance propio | Formularios planos `detalle`, catálogos y acciones HTML | Mismo límite de Service; adaptar el payload sin coerción ni descarte de campos desconocidos. |
| StockController.php | Captura v2 e idempotencia | Formularios y edición de borradores | Mismo límite de Service; conservar identidad inmutable en Repository y validar versión explícita. |
| ExportController.php | Restricción por AccessPolicy | XLSX/JSON/TXT/PDF con presentación y metadata | Preservar exportador integrado y aplicar la constante PUBLISHED. CSV continúa rechazado. |
| layouts/header.php | Landing por rol y logout CSRF | Shell, sidebar, branding y UX | Preservar header integrado: sidebar ya separa Bayer y roles internos; mantiene landing y CSRF. |
| routes/web.php | Middleware por rol y permiso granular; maestros y JSON | Rutas de edición/eliminación/workflow, backoffice y portal | Unión explícita, sin duplicar `/ver` ni restaurar 503. Conectar `/export/count`, utilizado por exports.js, y `/exportaciones`. |
| core_security_test.php | Pruebas de seguridad y contrato v2 | Captura inválida responde 422 | Conservar 422. Solicitar JSON explícito para detalles; probar el workflow actual y rechazo de CSV, sin exigir el antiguo 503. |

Los tres wrappers operativos quedan idénticos a HEAD; sus responsabilidades HTML se reúnen en OperationalController, no se descartan.

## Contratos resultantes

- GET de listados, nuevo, detalle y edición: HTML integrado por defecto. `Accept: application/json` conserva el contrato de datos/contexto.
- POST guardar/actualizar: documentos usa `header` + `details`; guías/stock aceptan tanto ese contrato como los formularios planos existentes con `detalle`.
- Campos desconocidos, estado y actor enviados dentro de la captura siguen rechazados con 422.
- Actor obtenido de sesión; digitador conserva `created_by` en listados y comprobación de propiedad en detalle, edición y eliminación.
- Crear/editar/eliminar exige ADMIN o DIGITADOR y permiso persistido `<module>.create`; lectura exige `<module>.read`. Edición/eliminación también comprueban acceso al registro.
- Edición y eliminación solo de BORRADOR y con `version` positiva enviada por el cliente. Conflicto de versión/estado: 409.
- `/estado` exige ADMIN/SUPERVISOR, permiso persistido `validation.review` o `publications.publish` según destino, permiso de lectura y versión positiva explícita.
- Workflow conserva BORRADOR→VALIDADO, VALIDADO→PUBLICADO, VALIDADO→OBSERVADO, OBSERVADO→BORRADOR y PUBLICADO→ANULADO. Observación/anulación requieren motivo. No se permite BORRADOR→PUBLICADO.
- Guías/Stock envían `version` en todos los formularios de workflow; ya no existe fallback a la versión actual de la BD.
- Bayer no accede al CRUD, aunque tenga además un rol interno. Datos de salida continúan limitados a PUBLICADO.
- Errores de validación: 422, CSRF: 419, permisos: 403, concurrencia: 409. Errores PDO inesperados permanecen 500 seguros; no se convierten artificialmente en 409.
- Un stock migrado puede conservar su clave `legacy-stock-` al editar; sigue prohibida en nuevas capturas y el Repository impide cambiar la identidad del borrador.

## Base preservada y límites

PermissionService, SessionService, AuthService, middleware, Router, AccessPolicy y configuración de seguridad de Alisson permanecen sin cambios respecto a HEAD. Se conservan OperationalValidator y OperationalPolicy, ampliados únicamente para conectar las acciones integradas.

Se conservan las vistas/estilos/scripts integrados, Portal Bayer y los cuatro exportadores. Los cambios visuales adicionales se limitan a versiones ocultas, conservación de valores y errores 422 en Guías/Stock, enlace de edición coherente con los roles y texto de workflow actualizado.

Se conservan Operations, Masters y DataHub de Pedro. `deleteDraft()` llegó por combinación automática desde develop al Repository Operations; no se añadió SQL nuevo. BackofficeController y WorkflowService se adaptaron al namespace Operations.

Schema v2, migraciones y pruebas originales de Pedro no se modificaron. Se conservan los seeders de ambas ramas: los permisos granulares actuales son idempotentes. No se ejecutaron seeders o migraciones en la BD de aplicación. Los permisos de workflow se cargaron únicamente como fixtures de prueba temporal; su existencia en la BD local de aplicación no fue verificada ni alterada.

Los siete commits exclusivos anteriores siguen representados: seis commits de Pedro (`85846bd`, `b409f3b`, `30da5ef`, `6eae8c6`, `6a12337`, `e5baa9b`) y el Día 2 de Alisson (`b649741`). No se eliminaron sus Repositories organizados, maestros, Data Hub, fixtures, CI ni validación operativa.

Quedan obsoletos frente a develop el 503 de formularios/workflow, el detalle exclusivamente JSON sin negociación, el header anterior y CSV. El contrato histórico de `docs/contracts/day2-backend.md` debe leerse con esta actualización.

## Deudas pendientes para P1, coordinadas con Pedro

1. Auditoría atómica: `audit()` conserva el comportamiento integrado posterior a la operación y captura sus propios fallos. No se garantiza atomicidad entre transición y auditoría. Pedro debe proporcionar persistencia transaccional; Alisson coordinarla desde el Service.
2. WorkflowService conserva SQL preexistente de validaciones/publicaciones; no se agregó SQL. Extraerlo hacia persistencia de Pedro y utilizar una misma conexión/transacción también en llamadas con PDO inyectado. No rediseñado durante P0.
3. DocumentScreenService conserva consultas de presentación preexistentes; su `validate()` anterior queda sin uso en captura. BackofficeController, ExportController y otros componentes integrados conservan SQL existente. Coordinar su traslado a Repositories sin romper la UX.
4. Portal/exportadores siguen usando BayerDataService integrado. Conectar Data Hub como fuente lógica única es trabajo posterior conjunto; no se reemplazaron consultas en P0.
5. Revisar validación previa a publicación, homologaciones y pruebas de concurrencia del workflow según contratos acordados; esta reconciliación no implementa esa evolución.
6. Coordinar con frontend las comprobaciones visuales por permiso específico y los catálogos históricos/inactivos. El backend rechaza capturas con maestros inactivos; no se rediseñaron los selectores.
7. ZIP debe estar habilitado en el PHP que atiende XLSX. En XAMPP estaba deshabilitado; se habilitó solo para los procesos de pruebas. No se editó php.ini ni se reinició/configuró Apache.

## Verificación

Se reutiliza el harness con bases `bayer_test_<aleatorio>`, servidor HTTP y sesiones temporales. La BD de aplicación no es destino de pruebas.

La suite HTTP incluye `reconciliation_http_cases.php`: formularios HTML y JSON, 403/409/419/422, propiedad, versiones, borradores, workflow, motivos, revocación de permiso y rollback de estado/versión cuando falla la persistencia de validaciones. También comprueba XLSX/JSON/TXT/PDF, rechazo de CSV y ausencia de warnings PHP en las solicitudes.

La ejecución HTTP ampliada pasó 322 comprobaciones: 152 anteriores y 170 de reconciliación. Las 56 del subconjunto Día 2 están incluidas, no se suman otra vez.

Las suites de CI se ejecutan junto con `day2_operational_test.php`. La verificación final y el estado exacto de Git se entregan con el informe de cierre, antes de cualquier commit o push.

Resultado final: 10 suites, 1.021 comprobaciones, 0 fallos; 95 archivos PHP sin errores de sintaxis. Desglose: validator 2, core unitario 31, CSRF 14, Schema v2 86, Repositories 122, core HTTP 322, maestros 77, mantenimiento 73, Data Hub 79 y operaciones Día 2 215.

La primera prueba ampliada falló en XLSX por ZIP deshabilitado en el PHP local. La repetición y la pasada final usaron la extensión instalada mediante configuración temporal del proceso; no se relajó la prueba ni se cambió el exportador. Dos saltos de línea Markdown con espacios finales heredados de develop en database/README.md se expresaron con barra inversa para conservar su presentación y limpiar la comprobación de whitespace.
