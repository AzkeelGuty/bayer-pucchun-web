<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;

/**
 * Fotos privadas de usuarios: PNG, JPG, WEBP; sin SVG ni archivos ejecutables.
 * Los bytes nunca se guardan dentro de public/ ni en el repositorio.
 */
final class UserPhotoService
{
    public static function folder(): string
    {
        return \base_path('storage/private/user_avatars');
    }

    public static function validateUpload(array $file): bool
    {
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error===UPLOAD_ERR_NO_FILE)return false;
        if($error!==UPLOAD_ERR_OK)
            throw new HttpException(422,'No se pudo cargar la foto. Verifique el archivo.');
        if((int)($file['size']??0)<1||(int)$file['size']>2*1024*1024)
            throw new HttpException(422,'La foto debe pesar como máximo 2 MB.');
        $tmp=(string)($file['tmp_name']??'');
        if($tmp===''||!is_uploaded_file($tmp))
            throw new HttpException(422,'La foto cargada no es válida.');
        $info=@getimagesize($tmp);
        if(!is_array($info) || ($info[0]??0)<32 || ($info[1]??0)<32 ||
            ($info[0]??0)>4000 || ($info[1]??0)>4000)
            throw new HttpException(422,'La imagen debe medir entre 32 y 4000 píxeles por lado.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!isset($allowed[$mime]) || ($info['mime']??'')!==$mime)
            throw new HttpException(422,'Formato de foto no permitido. Use JPG, PNG o WEBP.');
        return true;
    }

    /** Recibe solo archivos ya validados y devuelve un nombre aleatorio. */
    public static function store(array $file): ?string
    {
        if(!self::validateUpload($file))return null;
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extension=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
        $dir=self::folder();
        if(!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir))
            throw new HttpException(503,'No se pudo preparar almacenamiento privado de fotos.');
        $name=bin2hex(random_bytes(16)).'.'.$extension;
        if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))
            throw new HttpException(503,'No fue posible guardar la foto.');
        @chmod($dir.'/'.$name,0600);
        return $name;
    }

    public static function delete(?string $name): void
    {
        if(!self::validName($name))return;
        @unlink(self::folder().'/'.$name);
    }

    private static function validName(?string $name): bool
    {
        return is_string($name) &&
            preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D',$name)===1;
    }

    public static function show(?string $name): void
    {
        if(!self::validName($name))
            throw new HttpException(404,'Foto no encontrada.');
        $file=self::folder().'/'.$name;
        if(!is_file($file))
            throw new HttpException(404,'Foto no encontrada.');
        $mime=match(strtolower(pathinfo($file,PATHINFO_EXTENSION))){
            'png'=>'image/png','webp'=>'image/webp',default=>'image/jpeg',
        };
        header('Content-Type: '.$mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=60');
        header('Content-Length: '.(string)filesize($file));
        readfile($file);
    }
}
