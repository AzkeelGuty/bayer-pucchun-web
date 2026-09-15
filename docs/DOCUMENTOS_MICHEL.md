# Documentos: entrega de Michel

Pantallas de listado, búsqueda, filtros, paginación, creación, detalle y edición de borradores con múltiples productos. Quitar retira una línea del borrador; siempre se exige al menos una. No se ofrece borrado físico del documento.

Se adapta exclusivamente Documentos al contrato header/details de los repositorios de Schema v2 presentes en develop. Se usan catálogos existentes, se mantienen errores y se comprueban propietario, rol y versión al editar. El repositorio conserva las transacciones de cabecera y detalles.

## Integración pendiente
Las acciones de validar, observar, devolver, publicar y anular se muestran deshabilitadas. Su endpoint conserva la protección pendiente de develop: no se importa WorkflowService de otra feature ni se llama directamente a métodos de publicación sin las validaciones del servicio aprobado. Guías y Stock no se modifican.

El adaptador carga catálogos completos (adecuado para el volumen local de demostración). Los registros históricos inactivos pueden mostrarse por ID. Antes de una entrega final, coordinar con backend sus servicios de captura, permisos atómicos, idempotencia y validación/publicación.

## Verificación
La prueba de integración core_security_test.php incluye creación con índices discontinuos, duplicados, quitar producto al editar, mínimo de una línea, conflicto de versión, propiedad, filtros y ausencia de borrado físico. Utiliza una base temporal aislada. Las pruebas de la copia Gianpiere no certifican el workflow de esta rama.
