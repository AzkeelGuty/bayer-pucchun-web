# Despliegue en hosting Linux compartido / cPanel
1. Crear subdominio, por ejemplo `bayer.midominio.com`.
2. Subir todo el proyecto fuera de `public_html` cuando el hosting lo permita.
3. Configurar el Document Root del subdominio apuntando a `bayer-pucchun-web/public`.
4. Crear BD y usuario MySQL/MariaDB en cPanel; asignar ALL PRIVILEGES a esa BD.
5. Importar `database/schemas/001_schema.sql` y luego `database/seeders/001_seed.sql` con phpMyAdmin.
6. Copiar `.env.example` a `.env`; completar APP_URL y credenciales DB.
7. Usar PHP 8.1 o superior. Extensiones recomendadas: pdo_mysql, mbstring, json, zip, openssl.
8. Permisos: carpetas 755, archivos 644; `storage/` debe ser escribible por PHP.
9. Forzar HTTPS desde cPanel/SSL.
10. Ingresar con `admin@local.test / Admin123*` y cambiar la credencial de bootstrap de inmediato.

## Si no puede cambiar Document Root
Copie el contenido de `public/` a `public_html/subdominio/` y ajuste en `index.php` las rutas `dirname(__DIR__)` hacia la ubicación real del proyecto privado. Evite exponer `.env`, `database/` y `storage/` al navegador.
