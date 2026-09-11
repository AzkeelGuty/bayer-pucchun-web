# Bayer–Pucchún Data Hub
MVP funcional de la plataforma web para capturar, validar, publicar, analizar y entregar a Bayer los tres datasets definidos en el Excel oficial: **Documentos, Guía de Remisión y Stock**.

## Incluye
- Login y RBAC: ADMIN, DIGITADOR, SUPERVISOR, GERENCIA, BAYER.
- CRUD de captura para Puchún: documentos, guías y stock.
- Estados BORRADOR / VALIDADO / PUBLICADO.
- Portal Bayer sin acceso al CRUD.
- Dashboard Puchún y Dashboard Bayer.
- Vista previa tabular de datos publicados.
- Exportación XLSX, CSV, JSON, TXT y PDF.
- Auditoría básica.
- API REST opcional, deshabilitada por defecto.
- SQL completo y seed inicial.
- Documentación de arquitectura, despliegue, seguridad y modificación.

## Requisitos
PHP 8.1+, MySQL/MariaDB, PDO MySQL, mbstring, JSON, ZipArchive (para XLSX), Apache con mod_rewrite o equivalente.

## Instalación rápida
1. Copie `.env.example` a `.env`.
2. Configure DB y APP_URL.
3. Importe `database/schemas/001_schema.sql`.
4. Importe `database/seeders/001_seed.sql`.
5. Configure el Document Root hacia `public/`.
6. Abra `/login`.

Credencial bootstrap: `admin@local.test` / `Admin123*`. **Cambiar antes de producción.**

## Nota de alcance
El proyecto es una base funcional y extensible orientada a una primera entrega rápida en hosting compartido. Antes de Go-Live se debe completar UAT, revisión de seguridad, datos maestros definitivos, homologaciones Bayer, carga masiva si aplica, hardening y respaldo.
