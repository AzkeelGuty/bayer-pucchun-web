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

        $theme=strtolower(trim((string)($_POST['sidebar_theme'] ?? 'dark')));
        if(!in_array($theme,['dark','light'],true)){
            throw new HttpException(422, 'Tema del menú lateral inválido.');
        }
        $next['sidebar_theme']=$theme;

        foreach (['logo_primary','logo_partner','favicon'] as $asset) {
            if (!empty($_POST['remove_'.$asset])) $this->removeAsset($next, $asset);
            $next[$asset] = $this->storeUpload($asset, $next[$asset] ?? null);
        }

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
        }else{
            $finfo=new \finfo(FILEINFO_MIME_TYPE);
            $mime=$finfo->file($tmp);
            $extensions=[
                'image/png'=>'png',
                'image/jpeg'=>'jpg',
                'image/webp'=>'webp',
            ];
            if(!isset($extensions[$mime])){
                throw new HttpException(422, 'Formato no permitido. Use SVG, PNG, JPG o WEBP.');
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

        if($current){
            $old=\base_path('public/'.ltrim($current,'/'));
            if(is_file($old)) @unlink($old);
        }

        return 'uploads/branding/'.$filename;
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

    private function removeAsset(array &$branding, string $key): void
    {
        $relative=$branding[$key] ?? null;
        if(is_string($relative) && $relative!==''){
            $absolute=\base_path('public/'.ltrim($relative,'/'));
            if(is_file($absolute)) @unlink($absolute);
        }
        $branding[$key]=null;
    }
}
