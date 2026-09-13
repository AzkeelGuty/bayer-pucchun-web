# Contrato backend Día 2 — Alisson

Base: e5baa9b (Pedro). Repositories sin modificaciones, namespace App\Repositories\Operations.

## Alcance entregado

Captura BORRADOR, listado, consulta individual y consulta de catálogos existentes para Documentos, Guías y Stock. Controller común delgado -> OperationalService -> OperationalPolicy / OperationalValidator -> Repositories existentes. No se habilita ninguna transición de workflow ni edición; quedan para el Día 3 con permiso, estado BORRADOR y versión esperada. No hay nuevas rutas de publicación, API ERP o mantenimiento de maestros.

## Permisos y activación pendiente

Seed nuevo: database/seeders/003_day2_operational_permissions.sql. Probado en base temporal, NO aplicado automáticamente a la BD de la aplicación. No ejecuta cambios de esquema ni crea usuarios o roles. Requiere los roles existentes del seed 001. Es aditivo y reimportable; no revoca concesiones preexistentes.

| Operaciones | ADMIN | DIGITADOR | SUPERVISOR | GERENCIA | BAYER |
| --- | --- | --- | --- | --- | --- |
| documents.read / guides.read / stock.read | Equipo | Propias | Equipo | Equipo | Denegado |
| documents.create / guides.create / stock.create | Sí | Sí | No | No | No |

Las consultas de maestros para captura requieren el permiso create del módulo y un rol de captura. Los guardias por rol y los permisos persistidos se exigen conjuntamente. BAYER permanece externo incluso con roles adicionales. No hay privilegios implícitos para ADMIN. Antes de aplicar este seed aprobado, las nuevas operaciones internas devolverán 403 a usuarios sin concesiones; no es un fallo de login. El acceso al portal publicado conserva las reglas del Día 1.

## Rutas (prefijo /documentos, /guias o /stock)

| Método y sufijo | Contrato |
| --- | --- |
| GET prefijo | HTML actual; Accept: application/json devuelve data.items. |
| GET /ver?id=123 | JSON data.header y data.details; inexistente 404, carga ajena al digitador 403. |
| GET /nuevo | Con Accept: application/json devuelve módulo, BORRADOR, max_details, _csrf y clave de idempotencia para stock. La versión HTML conserva 503 mientras frontend conecta su formulario v2. |
| GET /maestros?catalog=productos&q=texto | JSON data.items de maestros; consulta de lectura, nunca crea datos. |
| POST /guardar | Recibe form-urlencoded con _csrf, header[...] y details[n][...]. Con Accept: application/json responde 201, data.id y Location; sin ese Accept redirige al listado existente. |
| POST /estado | Sigue en 503 para roles revisores; digitador y Bayer reciben 403. No modifica estados. |

El cuerpo de creación es **application/x-www-form-urlencoded**, no JSON crudo. JavaScript puede usar URLSearchParams. Accept controla la respuesta; no cambia el formato del cuerpo. Reutilizar la sesión autenticada y el _csrf obtenido de /nuevo. Un token ausente o inválido responde 419.

Ejemplo conceptual del cuerpo:

```text
_csrf=TOKEN
header[tipo_documento_id]=1
header[numero]=F001-123
header[fecha]=2026-09-13
header[cliente_id]=1
header[vendedor_id]=1
header[sucursal_id]=1
details[0][producto_id]=1
details[0][unidad_id]=1
details[0][cantidad]=2.500
details[0][valor_unitario]=10.00
```

Cabeceras:
- Documentos: tipo_documento_id, numero, fecha, cliente_id, vendedor_id, sucursal_id.
- Guías: numero, fecha, cliente_id, vendedor_id, sucursal_id; departamento_id, provincia_id y distrito_id pueden omitirse juntos en BORRADOR o enviarse completos y relacionados.
- Stock: fecha_stock, almacen_id, idempotency_key (ASCII, 1–64 caracteres; prefijo legacy-stock- reservado).

Detalles:
- Todos: producto_id, unidad_id, cantidad.
- Documentos: valor_unitario opcional, valor predeterminado cero, máximo dos decimales.
- Stock: lote_id opcional/null; si existe debe estar activo, pertenecer al producto y no estar vencido a la fecha del stock.
- Cantidad: decimal enviado como texto, máximo tres decimales y precisión del Repository (DECIMAL(14,3)); mayor que cero para Documentos/Guías, cero permitido para Stock.

De 1 a 200 líneas. Se aceptan índices numéricos dispersos y se compactan preservando el orden recibido. Frontend debe asignar índices únicos: si reutiliza un índice, PHP ya habrá sobrescrito esa fila antes de llegar al Validator. No enviar estado, actor, version, request_hash ni campos extra. El actor se obtiene de la sesión y el estado inicial lo fija el Repository. No se admiten los nombres planos del formulario v1.

La clave de stock debe conservarse al reintentar la misma solicitud. El mismo contenido/actor devuelve el mismo ID; otra carga con la clave utilizada responde 409. La unicidad de fecha/almacén/producto/lote/unidad de Pedro también puede causar 409 aunque la clave de solicitud sea distinta. Nunca se crea una actualización implícita.

## Consultas

Listado: fecha_desde, fecha_hasta, estado_registro, sucursal_id (documentos/guías) o almacen_id (stock), page >= 1, limit 1–300, predeterminados 1 y 300. No se inventa un total: all() no lo proporciona. created_by recibido del navegador se ignora; el alcance se fija con la sesión antes de paginar.

Maestros: catalog, q (hasta 200 bytes), filters[campo], page >= 1 y limit 1–100 (predeterminado 50). Se reutilizan las restricciones de MasterDataRepository. Stock ofrece almacenes, productos, unidades_medida y lotes. Documentos/Guías ofrecen clientes, vendedores, sucursales, productos, unidades_medida, tipos_documento, departamentos, provincias y distritos. Para lotes usar filters[producto_id], provincias filters[departamento_id], distritos filters[provincia_id].

search() excluye registros inactivos donde hay estado. find() incluye históricos: el Validator comprueba estado y relaciones antes de capturar, también empresa y sucursal del almacén. Las consultas de selección no garantizan que una referencia siga utilizable al guardar. Las FK y las comprobaciones adicionales de Pedro siguen vigentes. No se inventa homologación, conversión de unidades ni obligatoriedad de lotes que el esquema todavía no modela. La posible coordinación transaccional con cambios simultáneos de maestros queda pendiente con Pedro: su find() no bloquea esas filas.

## Respuestas de error

Con Accept: application/json: success=false, code y message. Un 422 de validación incluye errors con claves header.campo o details.índice.campo. 403 permisos, 404 inexistente, 409 duplicado/conflicto de idempotencia, 419 CSRF, 422 entrada inválida, 500 fallo interno sin SQL ni trazas. Los errores HTML mantienen el mensaje seguro general. El hash de persistencia de stock no se expone en la consulta individual.

## Coordinación y pendientes

- Michel: conectar formulario Documentos con header/details, seleccionar IDs existentes, mostrar errors y evitar índices repetidos. No se modificaron sus vistas ni se incorporó su rama.
- Aldhair: conectar Guías/Stock, conservar la clave de idempotencia por solicitud y respetar los detalles permitidos. No se modificaron formularios.
- Gianpiere: adaptar navegación y lectura de permisos cuando incorpore su layout; no se rediseñó ninguna vista. Los botones HTML antiguos de workflow siguen siendo presentación pendiente y el backend continúa devolviendo 503.
- Pedro: se consumen Operations y Masters existentes. No se cambiaron esquema, migraciones, Repositories, DataQuery ni DataHub. El mantenimiento de maestros no se conecta a la captura.
- Activación local: avisar y obtener autorización antes de ejecutar el seed en la BD de aplicación. Probar desde el navegador tras la integración frontend.
- Día 3: edición BORRADOR con control de versión, workflow, validación/publicación de negocio y auditoría transaccional correspondiente. Este bloque conserva created_by/created_at pero no incorpora una nueva auditoría de workflow.

## Pruebas

tests/integration/day2_operational_test.php: contrato de captura, permisos por rol, propias/ajenas, maestros activos e históricos, referencias, errores de entrada, duplicados, idempotencia, paginación y seed reimportable.

tests/integration/core_security_test.php: conserva las comprobaciones de seguridad existentes, carga el seed en su BD temporal y añade creación/lectura/catálogos HTTP, actor/estado, CSRF, permisos, denegación Bayer y workflow pendiente. Las comprobaciones del subconjunto Día 2 ya están incluidas en el total de esta suite; no sumarlas dos veces.
