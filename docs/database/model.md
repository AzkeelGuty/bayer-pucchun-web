# Modelo de datos y contrato de persistencia — Schema v2

Este documento describe `database/schemas/002_schema_v2.sql`, el esquema completo v2.
`database/schemas/001_schema.sql` conserva la estructura original v1.
La actualización desde una base v1 utiliza `database/migrations/002_schema_v2.sql`.

Fuentes funcionales: DT-01 a DT-05 de la carpeta BAYER, especialmente el contrato
de estados/validaciones DT-02 y las convenciones de arquitectura DT-03.
Los PDF/DOCX originales no se modificaron.

## Alcance y conexión pendiente

La capa de datos queda preparada para Services: maestros existentes, agregados
cabecera-detalle, consultas y escrituras atómicas. No implementa permisos, homologación
funcional, publicación completa, exportación ni adaptadores ERP.

La integración de los controladores actuales queda para Alisson por decisión de Pedro.
No llamar a estos repositorios directamente con POST. La antigua firma create(array)
y el método status(id, estado) dejaron de existir. Antes del despliegue, Alisson debe
normalizar entrada, resolver IDs, pasar el usuario autenticado, aplicar Policies/Validators,
manejar errores y agrupar persistencia de estado/historial/auditoría en la misma transacción.

## Relaciones y restricciones

- Maestros: empresas → sucursales → almacenes; departamentos → provincias → distritos;
  clientes, vendedores, productos, unidades, tipos de documento y partners.
- Documentos: documentos_cabecera → documentos_detalle (1:N).
  Unicidad histórica conservada: tipo_documento_id + numero.
- Guías: guias_cabecera → guias_detalle (1:N). numero conserva su unicidad global;
  debe contener la serie y correlativo, por ejemplo T001-000001.
- Stock: stock_cabecera → stock_detalle (1:N); cada lote pertenece a un producto.
  Una FK compuesta (lote_id, producto_id) → lotes(id, producto_id) impide mezclar ambos.
- Cantidades: documentos/guías > 0; stock >= 0. Valor unitario de documento >= 0.
  Cantidad DECIMAL(14,3); valor unitario DECIMAL(14,2). Los repositorios reciben cadenas
  decimales o enteros y rechazan floats, exponentes, redondeo implícito y desbordamientos.
- FK conservadas para maestros/actores; la migración revisa también referencias
  históricas importadas con FOREIGN_KEY_CHECKS=0.
- No se expone borrado de cabeceras operativas en los repositorios. La eliminación de
  detalles se usa únicamente para sustituir el contenido de un BORRADOR dentro de una
  transacción. La anulación conserva cabecera y detalles.

### Snapshot de Stock e idempotencia

La clave lógica sigue DT-02, sección 5.3: **fecha + almacén + producto + lote + unidad**.
Puede haber varias cabeceras para la misma fecha y almacén cuando sus detalles tengan
claves diferentes. No se obliga a consolidar todos los productos en una sola carga.
La fecha no distingue cortes horarios: repetir la clave completa sigue siendo duplicado.

`uq_stock_existencia` aplica esa unicidad en stock_detalle, incluso entre cabeceras y
solicitudes concurrentes. `lote_clave = IFNULL(lote_id, 0)` evita duplicados sin lote.
El repositorio obtiene fecha_stock y almacen_id de la cabecera al insertar los detalles;
el formulario no envía esos campos en cada línea. La FK compuesta
`(stock_id, fecha_stock, almacen_id)` exige que el contexto coincida con la cabecera.
Los dos campos repetidos permiten expresar la clave completa mediante una restricción
SQL; esa duplicación está controlada por la FK, no por confianza en el cliente.

El índice fecha/almacén de cabecera es de consulta, no único. El índice único
`(id, fecha_stock, almacen_id)` sirve de referencia a la FK y no prohíbe varias cargas.
La edición de borrador reemplaza detalles y cabecera dentro de la misma transacción;
si aparece un duplicado o una referencia inválida, se restauran datos y versión.
La migración copia el contexto desde cada cabecera y comprueba previamente duplicados
de la clave completa. Conserva cargas separadas válidas del mismo día y almacén.

Los productos que requieren lote deben verificarse en el Service/maestro correspondiente.

idempotency_key es ASCII, sensible a mayúsculas, de 1 a 64 caracteres admitidos
(letras, números, punto, guion, guion bajo, dos puntos; primer carácter alfanumérico).
Es globalmente única y el cliente/Service debe conservarla entre reintentos.

request_hash registra SHA-256 de fecha, almacén y detalles normalizados de la solicitud
inicial. El orden de líneas y ceros decimales redundantes no alteran el hash.

- Misma clave y mismo contenido inicial: devolver el ID existente sin reinsertar.
- Misma clave y contenido distinto: RuntimeException, sin sobrescribir.
- Otra clave de solicitud con una línea que repite fecha/almacén/producto/lote/unidad: PDOException por duplicado; se revierte toda la carga.
- Una edición de borrador conserva clave y hash de la solicitud original. Un reintento
  devuelve el ID incluso si su contenido fue corregido después; no deshace la corrección.
- La migración asigna legacy-stock-ID y un hash técnico a cargas antiguas, sin afirmar
  que se conoce su solicitud original. Esas claves permiten editar borradores migrados,
  pero el prefijo queda reservado y no se acepta para nuevas cargas.

El repositorio comprueba lote/producto y vencimiento >= fecha_stock bajo un bloqueo
compartido del lote. Un CHECK SQL no puede comparar filas de otras tablas: un cambio
posterior de fecha_vencimiento desde maestros debe volver a validar el stock afectado.
La publicación también debe volver a validar obligatoriedad, vigencia y homologación.

## Versiones, estados y trazabilidad

Las cabeceras usan exclusivamente BORRADOR, VALIDADO, PUBLICADO, OBSERVADO y ANULADO.
version comienza en 1 y aumenta en **cada escritura de contenido o estado**.
updated_at/updated_by registran el último cambio.

Cada operación dispone de validated_at/by, published_at/by, observed_at/by,
cancelled_at/by y los motivos observation_reason/cancellation_reason.
Los campos históricos desconocidos permanecen NULL durante la migración.

Las operaciones de estado tienen predicados SQL fijos de origen y versión esperada:

| Método | Origen requerido | Destino |
|---|---|---|
| markValidated(id, version, actorId) | BORRADOR | VALIDADO |
| markPublished(id, version, actorId) | VALIDADO | PUBLICADO |
| markObserved(id, version, actorId, reason) | VALIDADO | OBSERVADO |
| returnToDraft(id, version, actorId) | OBSERVADO | BORRADOR |
| markCancelled(id, version, actorId, reason) | PUBLICADO | ANULADO |

Todas devuelven la nueva versión. La edición solo opera en BORRADOR y exige una versión
vigente. No hay un método que acepte un estado arbitrario ni retorno PUBLICADO → BORRADOR.

Estos métodos son primitivas de persistencia, **no autorizan ni ejecutan VAL-001 a
VAL-007**. El Service debe comprobar permisos, reglas, aprobación, homologaciones y
detalles antes de invocarlos. Para evitar validar una versión y publicar otra, debe
bloquear/leer el agregado en su transacción, validar y escribir con la versión esperada.
La cabecera guarda la última observación/anulación; el historial completo corresponde
a las ejecuciones de validación, publicaciones y auditoría dentro de esa transacción.

## Contrato de los repositorios

Los tres admiten new Repository($pdo); sin argumento usan db(). Los tests inyectan PDO.
Configurar PDO con ERRMODE_EXCEPTION, FETCH_ASSOC y EMULATE_PREPARES=false.

| Método | Resultado |
|---|---|
| create(header, details, actorId) | ID de cabecera BORRADOR |
| updateDraft(id, expectedVersion, header, details, actorId) | Nueva versión; reemplaza todo el detalle |
| find(id) | ['header' => fila, 'details' => filas] o null |
| all(filters = [], limit = 300, offset = 0) | Resumen paginado con totales |
| DocumentRepository::findByNumber(typeId, number) | Agregado o null |
| GuideRepository::findByNumber(number) | Agregado o null |
| StockRepository::findByIdempotencyKey(key) | Agregado o null |

Filtros de all: estado_registro, fecha_desde, fecha_hasta y sucursal_id
(documentos/guías) o almacen_id (stock). Fechas inclusivas. Límite entre 1 y 300;
offset no negativo. Las claves de filtro son una lista cerrada y sus valores son
parámetros SQL. Los Services deben validar formatos y restringir el alcance del usuario:
all es una consulta interna, no el endpoint publicado para Bayer.

Cabeceras admitidas:

| Repositorio | Campos requeridos | Campos opcionales |
|---|---|---|
| Documentos | tipo_documento_id, numero, fecha, cliente_id, vendedor_id, sucursal_id | Ninguno |
| Guías | numero, fecha, cliente_id, vendedor_id, sucursal_id | departamento_id, provincia_id, distrito_id |
| Stock | fecha_stock, almacen_id, idempotency_key | Ninguno |

El ubigeo de guía se admite completo o completamente nulo en borradores.
Si se proporciona, debe corresponder distrito → provincia → departamento.
El Service exige el ubigeo obligatorio antes de validar/publicar.

Cada detalle requiere producto_id, unidad_id, cantidad.
Documento admite valor_unitario (predeterminado 0); Stock admite lote_id (predeterminado NULL).
No se aceptan campos adicionales ni estado, versiones, IDs de detalle, claves calculadas
o actores dentro del payload. actorId se pasa por separado y debe provenir de la sesión
autenticada que validó el Service, nunca de un campo libre del formulario.

Ejemplo para el Service, con maestros y usuario ya existentes:

```php
$documents = new App\Repositories\Operations\DocumentRepository($pdo);
$id = $documents->create(
    [
        'tipo_documento_id' => 1,
        'numero' => 'F001-000001',
        'fecha' => '2026-09-11',
        'cliente_id' => 1,
        'vendedor_id' => 1,
        'sucursal_id' => 1,
    ],
    [
        ['producto_id' => 1, 'unidad_id' => 1, 'cantidad' => '2.500', 'valor_unitario' => '10.00'],
        ['producto_id' => 2, 'unidad_id' => 2, 'cantidad' => '1.000', 'valor_unitario' => '5.00'],
    ],
    $authenticatedUserId
);
$record = $documents->find($id);
$newVersion = $documents->updateDraft(
    $id,
    (int) $record['header']['version'],
    $normalizedHeader,
    $normalizedDetails,
    $authenticatedUserId
);
```

### Transacciones y errores

create/updateDraft son atómicos. Si el repositorio inició la transacción, confirma
o revierte su operación. Si el Service ya abrió una, usa SAVEPOINT y nunca hace commit
del caso de uso completo. El Service debe confirmar o revertir su propia transacción.

find bloquea la cabecera en lectura durante la consulta de cabecera/detalles para
evitar mezclar versiones. Dentro de una transacción externa ese bloqueo permanece
hasta que termine la transacción del Service; mantenerlas cortas.

Los cambios de estado son UPDATEs atómicos que también participan en una transacción
externa si existe. El Service debe guardar los eventos de validación/publicación y
auditoría junto con el estado, usando el mismo PDO.

- InvalidArgumentException: contrato de entrada o relación lote/ubigeo inválidos.
- RuntimeException: versión/estado de origen desactualizado, registro inexistente,
  o conflicto entre clave idempotente y contenido.
- PDOException: error SQL original conservado; 1062 indica duplicado y 1452 referencia
  inexistente en MariaDB/MySQL. No exponer mensajes SQL ni datos de conexión al usuario.
- Ante deadlock/timeout, el Service debe reintentar su transacción completa de forma
  acotada; la idempotencia no sustituye la estrategia de reintentos del caso de uso.

## Validaciones, publicaciones y exportaciones

validaciones conserva una ejecución (módulo, registro, usuario, resultado, fecha),
y añade versión y correlation_id. validaciones_detalle enlaza cada hallazgo por FK:
codigo VAL-001…VAL-007, campo, severidad BLOQUEANTE/ADVERTENCIA, mensaje y resultado
OK/ERROR. El Service debe definir coherentemente si guarda la versión de entrada
evaluada o la versión resultante y usar esa convención en sus consultas; para esta
entrega se recomienda almacenar la **versión de entrada evaluada**, que no ha cambiado
durante la transacción.

publicaciones conserva usuario, fecha, módulo y resultado/estado de la operación;
añade motivo y actor/fecha de anulación y correlation_id. detalle_publicacion añade
version y unicidad dataset + registro_id + version. Guardar allí la **versión
resultante publicada** devuelta por markPublished. Para revertir/anular se conserva
el evento original: no eliminarlo para volver a publicar la misma versión.

registro_id en validaciones/detalle_publicacion es polimórfico (según módulo/dataset);
no tiene FK SQL hacia las tres posibles cabeceras. El Service debe verificar ese vínculo,
el dataset permitido y su versión al persistir los eventos. La migración no inventa
versiones distintas para publicaciones históricas duplicadas: exige conciliarlas.

exportaciones conserva filtro_json para filtros normalizados y añade record_count,
resultado (inicialmente PENDIENTE), duration_ms y correlation_id. El Service debe
normalizar los filtros antes de guardarlos. No se implementa el generador de exportación.

auditoria_acciones añade ip, user_agent resumido (máximo 255 caracteres), resultado, metadata_json y correlation_id.
No guardar contraseñas, cookies, tokens, payloads completos o secretos en metadata.
correlation_id permite asociar eventos del mismo caso de uso sin copiar información sensible.
bitacora_acceso añade FK al usuario cuando no es NULL.

## Homologaciones e índices

Las seis tablas homologacion_* añaden estado activo/inactivo, valid_from/valid_until,
created_at/by y updated_at/by. CHECK impide fin de vigencia anterior al inicio cuando
ambas fechas existen. Las claves internas de equivalencia v1 se conservan; esta versión
no permite varias filas históricas para el mismo par partner/maestro. La historia
de cambios se debe registrar en auditoría.

Índices principales:

| Índice/patrón | Consulta que apoya |
|---|---|
| Cabecera (estado_registro, fecha, id) | Datasets por estado y rango de fechas |
| Cabecera (sucursal_id/almacen_id, fecha, id) | Filtros operativos por ubicación y fecha |
| Cabecera Stock (fecha_stock, almacen_id) no único | Consultar las cargas por fecha y almacén |
| Cabecera Stock (id, fecha_stock, almacen_id) único | Referencia para mantener coherente el contexto del detalle |
| Stock idempotency_key único | Detección de reintentos |
| Detalle Stock (fecha_stock, almacen_id, producto_id, lote_clave, unidad_id) único | Evitar duplicados de la clave DT-02 entre todas las cargas |
| Homologación (partner_id, código externo, estado) | Resolución de equivalencias activas |
| Validación (modulo, registro_id, version, validated_at) | Historial de revisión de un registro |
| Publicación detalle (dataset, registro_id, version) único | Evitar republicar la misma versión |
| Exportación (tipo_dataset, generated_at) | Historial de descargas por dataset |
| Auditoría (modulo, entidad_id, fecha_hora), correlation_id | Trazabilidad por entidad/caso de uso |

BayerDataService sigue siendo el punto previsto para el dataset externo y deberá
consumir exclusivamente PUBLICADO. No se modificó durante el Día 1.

## Trazabilidad y entrega del día 1

Las decisiones de stock se basan en DT-02 §5.3, DT-03 §6 y el plan de mejora p.5.
La misma página pide `nombre_archivo` en exportaciones y `user_agent` resumido en
auditoria_acciones; ambos campos se añaden como NULL para conservar datos históricos
desconocidos. Registrar sus valores corresponde a los servicios de exportación/auditoría.
El nombre debe ser el del archivo entregado, no una ruta privada del servidor.

El contrato de repositorios descrito arriba es la propuesta de conexión para Alisson,
pendiente de su revisión e integración. La preparación local no equivale a aprobación
del contrato, PR integrado ni aplicación desplegable conforme a DT-05.


## Ajustes tras integrar develop con el día 2

Los repositorios operativos están en `App\Repositories\Operations`. Los tres controladores de listados y la prueba de seguridad usan esa ubicación. `all()` admite `created_by`: los controladores fijan ese filtro desde la sesión para un Digitador sin roles internos de supervisión/administración/gerencia. El parámetro HTTP no puede ampliar ese alcance. Los usuarios internos con roles de revisión mantienen la consulta del equipo; Bayer sigue sin acceso al backoffice.

La autorización de exportación comparte `AccessPolicy::PUBLISHED` entre ruta y controlador, incluyendo Supervisor. Esto no conecta todavía captura ni cambios de estado: las rutas correspondientes mantienen su respuesta 503 hasta integrar Services, validaciones y contratos nuevos. El CI incluye las tres suites de maestros y DataHub del día 2.
