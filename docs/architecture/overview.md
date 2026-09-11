# Arquitectura
Aplicación PHP 8.1+ modular para hosting Linux compartido. Flujo: `public/index.php -> routes -> Controllers -> Services/Repositories -> MySQL/MariaDB -> Views/Exportadores/API`.

## Dominios
- Backoffice Puchún: captura de Documentos, Guías y Stock.
- Workflow: BORRADOR -> VALIDADO -> PUBLICADO.
- Portal Bayer: dashboard, vista previa y exportaciones; nunca acceso al CRUD.
- API REST: incluida pero deshabilitada por defecto con `API_ENABLED=false`.
- Auditoría: registra acciones relevantes.

## Regla arquitectónica
Las salidas Bayer consumen exclusivamente registros `PUBLICADO`. La futura integración con ERP debe sustituir la fuente de captura, no la capa de salida.
