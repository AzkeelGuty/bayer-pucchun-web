#!/usr/bin/env bash
set -Eeuo pipefail
test_dir="$(mktemp -d)"
trap 'rm -rf "$test_dir"' EXIT
mkdir -p "$test_dir/project/scripts/deploy" "$test_dir/project/config" "$test_dir/project/public/assets/js" "$test_dir/project/storage" "$test_dir/www/assets/js" "$test_dir/www/uploads/branding"
cp scripts/deploy/cpanel_release.sh "$test_dir/project/scripts/deploy/"
printf '%s\n' '<?php // Bootstrap ficticio' > "$test_dir/project/config/bootstrap.php"
printf '%s\n' '<?php header("X-BP-Release: bp-20261117-cpanel");' > "$test_dir/project/public/index.php"
printf 'nuevo js\n' > "$test_dir/project/public/assets/js/navigation.js"
printf 'nuevo soft\n' > "$test_dir/project/public/assets/js/soft-page-init.js"
printf 'logo existente' > "$test_dir/www/uploads/branding/logo.png"
printf 'js anterior' > "$test_dir/www/assets/js/navigation.js"
printf 'php anterior' > "$test_dir/www/index.php"
printf '# cPanel PHP handler\nAddHandler application/x-httpd-ea-php82 .php\nRewriteRule ^ index.php [QSA,L]\n' > "$test_dir/www/.htaccess"

BP_CPANEL_PUBLIC="$test_dir/www" bash "$test_dir/project/scripts/deploy/cpanel_release.sh" --check >/dev/null
BP_CPANEL_PUBLIC="$test_dir/www" bash "$test_dir/project/scripts/deploy/cpanel_release.sh" --deploy >/dev/null
BP_CPANEL_PUBLIC="$test_dir/www" bash "$test_dir/project/scripts/deploy/cpanel_release.sh" --deploy >/dev/null

cmp "$test_dir/project/public/index.php" "$test_dir/www/index.php"
cmp "$test_dir/project/public/assets/js/navigation.js" "$test_dir/www/assets/js/navigation.js"
grep -q 'php82' "$test_dir/www/.htaccess"
[[ "$(grep -c 'BEGIN BAYER-PUCCHUN STATIC-CACHE' "$test_dir/www/.htaccess")" == 1 ]]
[[ "$(cat "$test_dir/www/uploads/branding/logo.png")" == 'logo existente' ]]
[[ -f "$test_dir/project/storage/deploy-backups/$(date +%Y%m%d-%H%M%S)/index.php" ]]
printf 'CPANEL DEPLOY CHECK OK\n'
