# Guía para el equipo
- Rutas: `routes/web.php`.
- Controladores: `app/Controllers`.
- Reglas de negocio: `app/Services`.
- Persistencia: `app/Repositories`.
- Validación de diccionario Bayer: `app/Validators/BayerDataValidator.php`.
- Vistas: `app/Views`.
- Consultas planas para Bayer: `app/Services/BayerDataService.php`.
- Exportadores: `SimpleXlsxExporter`, `SimplePdfExporter` y `ExportController`.
- SQL: `database/schemas` y `database/migrations`.
- Estilos: `public/assets/css/app.css`.
- Gráficos: `public/assets/js/app.js` con Chart.js CDN.

## Para agregar campos
1. Crear migración SQL.
2. Actualizar repositorio.
3. Actualizar formulario y validador.
4. Si Bayer lo requiere, actualizar `BayerDataService`.
5. Validar exportaciones.
