<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;

final class BrandingController
{
    private const TEXT_LIMITS = [
        'system_name'=>80,
        'system_subtitle'=>100,
        'partner_name'=>80,
        'partner_subtitle'=>100,
        'internal_title'=>100,
        'portal_title'=>100,
        'login_kicker'=>80,
        'login_title'=>120,
        'login_message'=>220,
        'footer_text'=>140,
    ];

    private const APPEARANCE_OPTIONS = [
        'sidebar_theme'=>['dark','light'],
        'ui_density'=>['comfortable','compact'],
        'corner_style'=>['rounded','balanced','square'],
        'shadow_style'=>['soft','minimal','none'],
        'sidebar_size'=>['normal','compact'],
        'topbar_style'=>['glass','solid'],
    ];

    private const ASSETS = ['logo_primary','logo_partner','favicon'];

    public function index(): void
    {
        \require_role('ADMIN');
        \view('configuracion.identidad', ['branding' => \branding()]);
    }

    public function update(): void
    {
        \require_role('ADMIN');

        $current = \branding();
        $next = $current;

        foreach (self::TEXT_LIMITS as $key=>$limit) {
            $value=trim((string)($_POST[$key] ?? $current[$key] ?? ''));
            if($value==='' || mb_strlen($value)>$limit){
                throw new HttpException(422, 'Revise los textos de identidad: hay un campo vacío o demasiado largo.');
            }
            $next[$key]=$value;
        }

        foreach (['primary_color','accent_color','sidebar_color','background_color'] as $key) {
            $value=strtoupper(trim((string)($_POST[$key] ?? $current[$key] ?? '')));
            if(!preg_match('/^#[0-9A-F]{6}$/',$value)){
                throw new HttpException(422, 'Uno de los colores de identidad no es válido.');
            }
            $next[$key]=$value;
        }

        foreach (self::APPEARANCE_OPTIONS as $key=>$allowed) {
            $value=strtolower(trim((string)($_POST[$key] ?? $current[$key] ?? $allowed[0])));
            if(!in_array($value,$allowed,true)){
                throw new HttpException(422, 'Una opción de apariencia no es válida.');
            }
            $next[$key]=$value;
        }

        $newUploads=[];
        $deleteAfterSave=[];

        foreach (self::ASSETS as $asset) {
            $old=is_string($current[$asset] ?? null) ? (string)$current[$asset] : null;

            if($this->hasUpload($asset)){
                $uploaded=$this->storeUpload($asset);
                $next[$asset]=$uploaded;
                $newUploads[]=$uploaded;
                if($old && $old!==$uploaded) $deleteAfterSave[]=$old;
                continue;
            }

            if(!empty($_POST['remove_'.$asset])){
                $next[$asset]=null;
                if($old) $deleteAfterSave[]=$old;
            }
        }

        $dir = \base_path('storage/config');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->deleteAssets($newUploads);
            throw new HttpException(500, 'No se pudo preparar la carpeta de configuración.');
        }

        $payload = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false || @file_put_contents($dir . '/branding.json', $payload . PHP_EOL, LOCK_EX) === false) {
            $this->deleteAssets($newUploads);
            throw new HttpException(500, 'No se pudo guardar la identidad visual.');
        }

        // Recién después de guardar la configuración eliminamos los archivos reemplazados.
        // Así un upload inválido o un fallo al escribir branding.json nunca borra el activo actual.
        $this->deleteAssets(array_values(array_unique($deleteAfterSave)));

        \audit('configuracion', 'actualizar_identidad');
        \flash('success', 'Identidad visual y apariencia actualizadas correctamente.');
        \redirect('/configuracion/identidad');
    }

    private function hasUpload(string $field): bool
    {
        $file=$_FILES[$field] ?? null;
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function storeUpload(string $field): string
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new HttpException(422, 'No se recibió el archivo de identidad.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'No se pudo cargar uno de los archivos de identidad.');
        }
        if (($file['size'] ?? 0) < 1 || (int) $file['size'] > 2 * 1024 * 1024) {
            throw new HttpException(422, 'Cada archivo de identidad debe pesar como máximo 2 MB.');
        }

        $tmp=(string)($file['tmp_name'] ?? '');
        if(!is_uploaded_file($tmp)){
            throw new HttpException(422, 'Archivo de identidad inválido.');
        }

        $originalExtension=strtolower(pathinfo((string)($file['name'] ?? ''),PATHINFO_EXTENSION));
        $extension=null;
        $sanitizedSvg=null;

        if($originalExtension==='svg'){
            $sanitizedSvg=$this->sanitizeSvg($tmp);
            $extension='svg';
        }elseif($field==='favicon' && $originalExtension==='ico' && $this->isIco($tmp)){
            $extension='ico';
        }else{
            $finfo=new \finfo(FILEINFO_MIME_TYPE);
            $mime=$finfo->file($tmp);
            $extensions=[
                'image/png'=>'png',
                'image/jpeg'=>'jpg',
                'image/webp'=>'webp',
                'image/x-icon'=>'ico',
                'image/vnd.microsoft.icon'=>'ico',
            ];
            if(!isset($extensions[$mime]) || ($extensions[$mime]==='ico' && $field!=='favicon')){
                throw new HttpException(422, $field==='favicon'
                    ? 'Formato no permitido. Use SVG, PNG, JPG, WEBP o ICO.'
                    : 'Formato no permitido. Use SVG, PNG, JPG o WEBP.');
            }
            $extension=$extensions[$mime];
        }

        $dir=\base_path('public/uploads/branding');
        if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)){
            throw new HttpException(500, 'No se pudo preparar la carpeta de identidad visual.');
        }

        $filename=$field.'-'.bin2hex(random_bytes(8)).'.'.$extension;
        $target=$dir.DIRECTORY_SEPARATOR.$filename;

        $saved=$sanitizedSvg!==null
            ? @file_put_contents($target,$sanitizedSvg,LOCK_EX)!==false
            : move_uploaded_file($tmp,$target);

        if(!$saved){
            throw new HttpException(500, 'No se pudo guardar el archivo de identidad.');
        }

        return 'uploads/branding/'.$filename;
    }

    private function isIco(string $tmp): bool
    {
        $head=@file_get_contents($tmp,false,null,0,4);
        return is_string($head) && ($head==="\x00\x00\x01\x00" || $head==="\x00\x00\x02\x00");
    }

    private function sanitizeSvg(string $tmp): string
    {
        $raw=@file_get_contents($tmp);
        if(!is_string($raw) || trim($raw)===''){
            throw new HttpException(422,'SVG vacío o inválido.');
        }
        if(stripos($raw,'<!DOCTYPE')!==false || stripos($raw,'<!ENTITY')!==false){
            throw new HttpException(422,'El SVG contiene declaraciones no permitidas.');
        }

        $dom=new \DOMDocument();
        $previous=libxml_use_internal_errors(true);
        $loaded=$dom->loadXML($raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if(!$loaded || !$dom->documentElement || strtolower($dom->documentElement->localName)!=='svg'){
            throw new HttpException(422,'El archivo SVG no es válido.');
        }

        $forbidden=['script','foreignobject','iframe','object','embed','audio','video','style'];
        $remove=[];
        foreach($dom->getElementsByTagName('*') as $node){
            if(in_array(strtolower($node->localName),$forbidden,true)){
                $remove[]=$node;
                continue;
            }

            $attrs=[];
            foreach($node->attributes ?? [] as $attr) $attrs[]=$attr;
            foreach($attrs as $attr){
                $name=strtolower($attr->name);
                $value=trim((string)$attr->value);

                if(str_starts_with($name,'on')){
                    $node->removeAttributeNode($attr);
                    continue;
                }

                if(in_array($name,['href','xlink:href','src'],true) && $value!=='' && !str_starts_with($value,'#')){
                    $node->removeAttributeNode($attr);
                    continue;
                }

                if($name==='style' && preg_match('/url\s*\(|expression\s*\(|javascript\s*:|@import/i',$value)){
                    $node->removeAttributeNode($attr);
                }
            }
        }

        foreach($remove as $node){
            $node->parentNode?->removeChild($node);
        }

        $safe=$dom->saveXML($dom->documentElement);
        if(!is_string($safe) || trim($safe)===''){
            throw new HttpException(422,'No se pudo procesar el SVG.');
        }

        return $safe;
    }

    private function deleteAssets(array $paths): void
    {
        foreach($paths as $relative){
            if(!is_string($relative) || $relative==='') continue;
            $relative=ltrim($relative,'/');
            if(!str_starts_with($relative,'uploads/branding/')) continue;
            $absolute=\base_path('public/'.$relative);
            if(is_file($absolute)) @unlink($absolute);
        }
    }
}
