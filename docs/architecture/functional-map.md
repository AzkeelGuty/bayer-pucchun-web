# Mapa funcional Bayer-Pucchún

Esta estructura refleja el alcance funcional definido para la plataforma privada Bayer-Pucchún.

## 1. Seguridad y acceso
- Inicio de sesión.
- Roles: ADMIN, DIGITADOR, SUPERVISOR, GERENCIA y BAYER.
- Control de sesiones y trazabilidad de accesos.
- Bayer no accede al CRUD interno.

## 2. Configuración base
- Catálogos maestros: empresas, sucursales, almacenes, clientes, vendedores, productos, unidades, tipos de documento y ubigeo.
- Homologaciones Bayer.
- Parámetros e identidad visual.

## 3. Captura interna
- Documentos.
- Guías de remisión.
- Stock.
- Solo perfiles internos autorizados capturan información.

## 4. Validación y publicación
Flujo principal:

BORRADOR -> VALIDADO -> PUBLICADO

Los registros observados regresan a borrador para corrección. Solo lo publicado se entrega a Bayer y alimenta la analítica externa.

## 5. Consulta y analítica
### Dashboard Pucchún
Uso interno. KPIs operativos, estados, tendencias y seguimiento.

### Portal Bayer
Consulta externa controlada. Dashboard comercial, vista previa, filtros y datos exclusivamente publicados.

## 6. Reportes y exportaciones
Formatos: XLSX, CSV, JSON, TXT y PDF. Toda exportación se registra para trazabilidad.

## 7. Trazabilidad y control
- Auditoría de acciones.
- Registro de publicaciones.
- Historial de exportaciones y accesos.

## 8. Evolución futura
La API REST y la integración con ERP reutilizan Services/Repositories y la misma fuente de datos. La web actual no depende de la API para funcionar.

## Roles

| Módulo | Admin | Digitador | Supervisor | Gerencia | Bayer |
|---|---|---|---|---|---|
| Dashboard Pucchún | Sí | Sí | Sí | Sí | No |
| Documentos / Guías / Stock | Completo | Captura | Consulta/revisión | Consulta | No |
| Validación / Publicación | Sí | No | Sí | Consulta | No |
| Catálogos / Homologaciones | Sí | No | No | No | No |
| Reportes / Exportaciones | Sí | No | Sí | Sí | Sí, publicados |
| Auditoría | Sí | No | Consulta | Consulta | No |
| Portal Bayer | Vista | No | Vista | Vista | Sí |
| Usuarios / roles | Sí | No | No | No | No |
| API futura | Configura | No | No | Consulta futura | Consumo futuro |

## Producción
- Linux/cPanel.
- HTTPS.
- APP_ENV=production.
- APP_DEBUG=false.
- Credenciales fuera del repositorio.
- Backups y logs.
- API deshabilitada hasta la etapa de integración.
