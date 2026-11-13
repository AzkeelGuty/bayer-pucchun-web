<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use Throwable;

/**
 * Guarda temporalmente el archivo original FUERA del directorio público.
 * La sesión conserva solo un identificador aleatorio; nunca los datos enviados
 * por un campo oculto como fuente de importación.
 */
final class BayerImportPreviewService
{
    private const SESSION_KEY='_bayer_import_preview';
    private const TTL=1800;

    public function stage(array $file,?string $period,int $userId): array
    {
        if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)
            throw new HttpException(422,'Seleccione el archivo original XLSX o CSV.');
        $size=(int)($file['size']??0);
        if($size<1||$size>12*1024*1024)
            throw new HttpException(422,'El archivo debe tener un tamaño máximo de 12 MB.');
        $name=basename(str_replace('\\','/',(string)($file['name']??'')));
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($extension,['xlsx','csv'],true))
            throw new HttpException(422,'Formato no admitido. Seleccione XLSX o CSV.');
        $temp=(string)($file['tmp_name']??'');
        if($temp===''||!is_uploaded_file($temp))
            throw new HttpException(422,'La carga recibida no corresponde a un archivo válido.');
        $period=trim((string)$period);
        if($period!==''&&!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D',$period))
            throw new HttpException(422,'El período alternativo debe tener formato YYYY-MM.');

        $this->discard();
        $folder=$this->folder();
        $this->cleanExpired($folder);
        $token=bin2hex(random_bytes(20));
        $dest=$folder.'/'.$token.'.'.$extension;
        if(!move_uploaded_file($temp,$dest))
            throw new HttpException(503,'No se pudo guardar temporalmente el archivo para su revisión.');
        @chmod($dest,0600);
        try{
            $sheets=(new BayerWorkbookReader())->inspectFile($dest,$extension,$period);
            if(!$sheets)throw new HttpException(422,'No se encontraron pestañas con datos Bayer.');
            $stage=[
                'token'=>$token,'extension'=>$extension,'name'=>mb_substr($name,0,180),
                'period'=>$period,'user'=>$userId,'expires'=>time()+self::TTL
            ];
            $_SESSION[self::SESSION_KEY]=$stage;
            return $stage;
        }catch(Throwable $error){
            @unlink($dest);
            throw $error;
        }
    }

    public function current(int $userId): ?array
    {
        $stage=$_SESSION[self::SESSION_KEY]??null;
        if(!is_array($stage))return null;
        if((int)($stage['user']??0)!==$userId ||
           (int)($stage['expires']??0)<time() ||
           !is_file($this->path($stage))){
            $this->discard();
            return null;
        }
        return $stage;
    }

    public function data(array $stage): array
    {
        return (new BayerWorkbookReader())->inspectFile(
            $this->path($stage),(string)$stage['extension'],(string)$stage['period']
        );
    }

    public function selectedRows(array $stage,string $sheet): array
    {
        return (new BayerWorkbookReader())->readSelectedFile(
            $this->path($stage),(string)$stage['extension'],$sheet,(string)$stage['period']
        );
    }

    public function discard(): void
    {
        $stage=$_SESSION[self::SESSION_KEY]??null;
        unset($_SESSION[self::SESSION_KEY]);
        if(!is_array($stage))return;
        try{
            $path=$this->path($stage);
            if(is_file($path))@unlink($path);
        }catch(HttpException $ignored){}
    }

    private function folder(): string
    {
        $folder=dirname(__DIR__,2).'/storage/private/bayer_import_previews';
        if(!is_dir($folder) && !@mkdir($folder,0700,true) && !is_dir($folder))
            throw new HttpException(503,'No se pudo preparar la carpeta privada para la importación.');
        if(!is_writable($folder))
            throw new HttpException(503,'La carpeta privada de importación no tiene permisos de escritura.');
        return $folder;
    }

    private function path(array $stage): string
    {
        $token=(string)($stage['token']??'');
        $ext=(string)($stage['extension']??'');
        if(!preg_match('/^[0-9a-f]{40}$/D',$token) ||
           !in_array($ext,['xlsx','csv'],true))
            throw new HttpException(422,'La vista previa ha caducado o no es válida.');
        return $this->folder().'/'.$token.'.'.$ext;
    }

    private function cleanExpired(string $folder): void
    {
        $files=glob($folder.'/*')?:[];
        foreach(array_slice($files,0,1000) as $file){
            if(!preg_match('/^[0-9a-f]{40}\.(xlsx|csv)$/D',basename($file)))continue;
            if(is_file($file) && (int)filemtime($file)<time()-self::TTL)@unlink($file);
        }
    }
}
