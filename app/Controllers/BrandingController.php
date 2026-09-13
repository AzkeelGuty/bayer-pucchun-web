<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;

final class BrandingController
{
    public function index(): void
    {
        \require_role('ADMIN');
        \view('configuracion.identidad', ['branding' => \branding()]);
    }

    public function update(): void
    {
        \require_role('ADMIN');

        $current = \branding();
        $systemName = trim((string) ($_POST['system_name'] ?? 'Pucchún'));
        $partnerName = trim((string) ($_POST['partner_name'] ?? 'Bayer'));
        $primary = strtoupper(trim((string) ($_POST['primary_color'] ?? '#075B9F')));
        $accent = strtoupper(trim((string) ($_POST['accent_color'] ?? '#168C5B')));

        if ($systemName === '' || mb_strlen($systemName) > 80) {
            throw new HttpException(422, 'El nombre principal es obligatorio y debe tener máximo 80 caracteres.');
        }
        if ($partnerName === '' || mb_strlen($partnerName) > 80) {
            throw new HttpException(422, 'El nombre del aliado es obligatorio y debe tener máximo 80 caracteres.');
        }
        foreach ([$primary, $accent] as $color) {
            if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
                throw new HttpException(422, 'Color de identidad inválido.');
            }
        }

        $next = [
            'system_name' => $systemName,
            'partner_name' => $partnerName,
            'primary_color' => $primary,
            'accent_color' => $accent,
            'logo_primary' => $current['logo_primary'] ?? null,
            'logo_partner' => $current['logo_partner'] ?? null,
        ];

        if (!empty($_POST['remove_logo_primary'])) $this->removeLogo($next, 'logo_primary');
        if (!empty($_POST['remove_logo_partner'])) $this->removeLogo($next, 'logo_partner');

        $next['logo_primary'] = $this->storeUpload('logo_primary', $next['logo_primary']);
        $next['logo_partner'] = $this->storeUpload('logo_partner', $next['logo_partner']);

        $dir = \base_path('storage/config');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'No se pudo preparar la carpeta de configuración.');
        }
        $payload = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false || @file_put_contents($dir . '/branding.json', $payload . PHP_EOL, LOCK_EX) === false) {
            throw new HttpException(500, 'No se pudo guardar la identidad visual.');
        }

        \audit('configuracion', 'actualizar_identidad');
        \flash('success', 'Identidad visual actualizada correctamente.');
        \redirect('/configuracion/identidad');
    }

    private function storeUpload(string $field, ?string $current): ?string
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $current;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'No se pudo cargar uno de los logotipos.');
        }
        if (($file['size'] ?? 0) < 1 || (int) $file['size'] > 2 * 1024 * 1024) {
            throw new HttpException(422, 'Cada logotipo debe pesar como máximo 2 MB.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new HttpException(422, 'Archivo de logotipo inválido.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            throw new HttpException(422, 'Formato no permitido. Use PNG, JPG o WEBP.');
        }

        $dir = \base_path('public/uploads/branding');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'No se pudo preparar la carpeta de logotipos.');
        }
        $filename = $field . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $target = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmp, $target)) {
            throw new HttpException(500, 'No se pudo guardar el logotipo.');
        }

        if ($current) {
            $old = \base_path('public/' . ltrim($current, '/'));
            if (is_file($old)) @unlink($old);
        }
        return 'uploads/branding/' . $filename;
    }

    private function removeLogo(array &$branding, string $key): void
    {
        $relative = $branding[$key] ?? null;
        if (is_string($relative) && $relative !== '') {
            $absolute = \base_path('public/' . ltrim($relative, '/'));
            if (is_file($absolute)) @unlink($absolute);
        }
        $branding[$key] = null;
    }
}
