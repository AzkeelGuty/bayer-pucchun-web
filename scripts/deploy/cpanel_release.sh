#!/usr/bin/env bash
# Despliegue seguro del frontend público del ERP Bayer-Pucchun a cPanel.
# Ejecutar únicamente desde Terminal/SSH del propio hosting.
set -Eeuo pipefail
umask 027
MODE="${1:---check}"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
PUBLIC_DIR="${BP_CPANEL_PUBLIC:-$HOME/public_html}"

fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
info(){ printf '%s\n' "$*"; }
usage(){ printf 'Uso: bash scripts/deploy/cpanel_release.sh [--check|--deploy]\n'; exit 2; }
[[ "$MODE" == "--check" || "$MODE" == "--deploy" ]] || usage
[[ -f "$APP_DIR/config/bootstrap.php" && -f "$APP_DIR/public/index.php" ]] || fail 'No se encuentra la raíz privada del proyecto.'
[[ -d "$PUBLIC_DIR" ]] || fail "No existe la carpeta pública: $PUBLIC_DIR"
PUBLIC_DIR="$(cd "$PUBLIC_DIR" && pwd -P)"
[[ "$PUBLIC_DIR" != "$APP_DIR" && "$PUBLIC_DIR" != "$APP_DIR/public" ]] || fail 'La carpeta pública debe ser distinta de la carpeta del código privado.'
[[ -f "$APP_DIR/public/assets/js/navigation.js" && -f "$APP_DIR/public/assets/js/soft-page-init.js" ]] || fail 'Faltan assets de la rama actual.'
[[ -f "$APP_DIR/.env" ]] || info 'AVISO: no se encontró .env en la raíz privada (se mantienen intactas las credenciales).'
[[ ! -f "$PUBLIC_DIR/.env" ]] || fail 'Archivo .env expuesto en public_html. Moverlo fuera de la carpeta pública antes de desplegar.'

check(){
    info '=== COMPROBACIÓN DE PUBLICACIÓN ==='
    info "Backend privado: $APP_DIR"
    info "Document Root:   $PUBLIC_DIR"
    if [[ -d "$APP_DIR/.git" ]] && command -v git >/dev/null 2>&1; then
        info "Rama local: $(git -C "$APP_DIR" branch --show-current)"
        info "Commit local: $(git -C "$APP_DIR" rev-parse --short HEAD)"
    else
        info 'AVISO: no se encontró un repositorio Git local; comprobar que el backend es la versión actual.'
    fi
    for f in index.php assets/js/navigation.js assets/js/soft-page-init.js assets/js/app.js assets/js/branding.js assets/css/app.css; do
        if [[ ! -f "$PUBLIC_DIR/$f" ]]; then
            info "FALTA:    public_html/$f"
        elif cmp -s "$APP_DIR/public/$f" "$PUBLIC_DIR/$f"; then
            info "CORRECTO: public_html/$f"
        else
            info "ANTIGUO:  public_html/$f (diferente del backend)"
        fi
    done
    local unmatched=0
    while IFS= read -r -d '' original; do
        relative="${original#"$APP_DIR/public/"}"
        if [[ ! -f "$PUBLIC_DIR/$relative" ]] || ! cmp -s "$original" "$PUBLIC_DIR/$relative"; then
            ((unmatched+=1))
        fi
    done < <(find "$APP_DIR/public/assets" -type f -print0)
    info "Assets faltantes o distintos: $unmatched"
    if [[ -f "$PUBLIC_DIR/.htaccess" ]]; then
        if grep -q 'RewriteRule.*index.php' "$PUBLIC_DIR/.htaccess"; then
            info 'Enrutamiento Apache: presente'
        else
            info 'AVISO: no se encontró el RewriteRule de index.php en .htaccess.'
        fi
    else
        info 'AVISO: falta .htaccess en public_html.'
    fi
    if [[ -f "$PUBLIC_DIR/index.php" ]] && grep -q 'X-BP-Release: bp-20261117-cpanel' "$PUBLIC_DIR/index.php"; then
        info 'Front controller optimizado: instalado'
    else
        info 'Front controller optimizado: NO instalado'
    fi
    info '=== FIN COMPROBACIÓN ==='
}

if [[ "$MODE" == "--check" ]]; then
    check
    exit 0
fi

# Rechazar despliegue desde una rama equivocada; nunca actualizar Git ni alterar .env.
if [[ -d "$APP_DIR/.git" ]] && command -v git >/dev/null 2>&1; then
    branch="$(git -C "$APP_DIR" branch --show-current)"
    [[ "$branch" == 'integration/frontend-equipo' ]] || fail "Rama '$branch': primero cambie a integration/frontend-equipo y actualice el backend."
fi
if ! grep -q 'X-BP-Release: bp-20261117-cpanel' "$APP_DIR/public/index.php"; then
    fail 'El backend no incluye el front controller de esta versión. Actualizar primero la rama privada.'
fi

backup_dir="$APP_DIR/storage/deploy-backups/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$backup_dir" "$PUBLIC_DIR/assets"
chmod 0700 "$APP_DIR/storage/deploy-backups" "$backup_dir" || true
for f in index.php .htaccess; do
    [[ ! -f "$PUBLIC_DIR/$f" ]] || cp -p "$PUBLIC_DIR/$f" "$backup_dir/$f"
done
info "Respaldo de archivos de arranque en $backup_dir"

# Sin --delete: no eliminar los logos subidos ni recursos ajenos administrados por cPanel.
if command -v rsync >/dev/null 2>&1; then
    rsync -rlt --chmod=D755,F644 "$APP_DIR/public/assets/" "$PUBLIC_DIR/assets/"
else
    (cd "$APP_DIR/public/assets" && tar -cf - .) | (cd "$PUBLIC_DIR/assets" && tar -xf -)
fi
# Algunos rsync no aplican --chmod sin --perms. Fijar permisos explícitamente.
find "$PUBLIC_DIR/assets" -type d -exec chmod 0755 {} +
find "$PUBLIC_DIR/assets" -type f -exec chmod 0644 {} +

# Sustitución atómica del front controller; no modificar .user.ini, php.ini ni uploads/.
new_index="$(mktemp "$PUBLIC_DIR/.index.php.deploy.XXXXXXXX")"
cp "$APP_DIR/public/index.php" "$new_index"
chmod 0644 "$new_index"
mv -f "$new_index" "$PUBLIC_DIR/index.php"

# Mantener intactas las directivas PHP personalizadas del proveedor de cPanel.
# Si no existe .htaccess se instala el del repositorio. De otro modo solo se
# añaden reglas de caché si faltan; jamás se reemplaza el archivo existente.
if [[ ! -f "$PUBLIC_DIR/.htaccess" ]]; then
    cp "$APP_DIR/public/.htaccess" "$PUBLIC_DIR/.htaccess"
    chmod 0644 "$PUBLIC_DIR/.htaccess"
elif ! grep -q 'ExpiresByType text/css' "$PUBLIC_DIR/.htaccess"; then
    cat >> "$PUBLIC_DIR/.htaccess" <<'HTACCESS'

# BEGIN BAYER-PUCCHUN STATIC-CACHE
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 7 days"
  ExpiresByType application/javascript "access plus 7 days"
  ExpiresByType text/javascript "access plus 7 days"
  ExpiresByType image/png "access plus 30 days"
  ExpiresByType image/jpeg "access plus 30 days"
  ExpiresByType image/webp "access plus 30 days"
  ExpiresByType image/svg+xml "access plus 30 days"
  ExpiresByType image/x-icon "access plus 30 days"
</IfModule>
# END BAYER-PUCCHUN STATIC-CACHE
HTACCESS
fi

if command -v php >/dev/null 2>&1; then
    php -l "$PUBLIC_DIR/index.php" || fail 'El front controller publicado no pasa php -l. Restaurar copia de seguridad.'
fi
check
info 'DESPLIEGUE PÚBLICO TERMINADO. No se modificaron base de datos, .env, storage/config ni uploads/.'
info 'Recargar el navegador con Ctrl+Shift+R. En Network verificar Response Headers: X-BP-Release.'
