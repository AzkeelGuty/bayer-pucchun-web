<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use Throwable;

/** Staging privado e independiente del inventario Bayer; no toca otros archivos. */
final class BayerPriceImportPreviewService
{
    private const KEY='_bayer_prices_preview';
    private const TTL=1800;

    public function stage(array $file,int $userId): array
    {
        if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)
            throw new HttpException(422,'Seleccione el Excel o CSV de precios.');
        $size=(int)($file['size']??0);
        if($size<1||$size>12*1024*1024)
            throw new HttpException(422,'El archivo de precios no puede superar los 12 MB.');
        $name=basename(str_replace('\\','/',(string)($file['name']??'')));
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($ext,['xlsx','csv'],true))
            throw new HttpException(422,'Para precios se admite XLSX o CSV.');
        $source=(string)($file['tmp_name']??'');
        if(!$source||!is_uploaded_file($source))
            throw new HttpException(422,'Archivo subido inválido.');
        $this->discard();
        $folder=$this->folder();
        foreach(array_slice(glob($folder.'/*')?:[],0,1000) as $item){
            if(preg_match('/^[a-f0-9]{40}\.(xlsx|csv)$/D',basename($item))
               &&is_file($item)&&(int)filemtime($item)<time()-self::TTL)@unlink($item);
        }
        $token=bin2hex(random_bytes(20));
        $path=$folder.'/'.$token.'.'.$ext;
        if(!move_uploaded_file($source,$path))
            throw new HttpException(503,'No se pudo preparar la vista previa de precios.');
        @chmod($path,0600);
        try{
            $sheets=(new BayerPriceWorkbookReader())->inspectFile($path,$ext);
            if(!$sheets)throw new HttpException(422,'No hay hojas de precios identificadas.');
            $stage=['token'=>$token,'ext'=>$ext,'name'=>mb_substr($name,0,180),
                'user'=>$userId,'expires'=>time()+self::TTL];
            $_SESSION[self::KEY]=$stage;
            return $stage;
        }catch(Throwable $error){@unlink($path);throw $error;}
    }

    public function current(int $userId): ?array
    {
        $stage=$_SESSION[self::KEY]??null;
        if(!is_array($stage))return null;
        if((int)($stage['user']??0)!==$userId ||
           (int)($stage['expires']??0)<time() || !is_file($this->path($stage))){
            $this->discard();
            return null;
        }
        return $stage;
    }

    public function data(array $stage): array
    {
        return (new BayerPriceWorkbookReader())->inspectFile(
            $this->path($stage),$stage['ext']);
    }

    public function selectedRows(array $stage,string $name): array
    {
        return (new BayerPriceWorkbookReader())->readSelectedFile(
            $this->path($stage),$stage['ext'],$name);
    }

    public function discard(): void
    {
        $stage=$_SESSION[self::KEY]??null;
        unset($_SESSION[self::KEY]);
        if(!is_array($stage))return;
        try{$path=$this->path($stage);if(is_file($path))@unlink($path);}
        catch(HttpException $error){}
    }

    private function folder(): string
    {
        $path=dirname(__DIR__,2).'/storage/private/bayer_price_previews';
        if(!is_dir($path)&&!@mkdir($path,0700,true)&&!is_dir($path))
            throw new HttpException(503,'No se pudo crear la carpeta privada de precios.');
        if(!is_writable($path))throw new HttpException(503,'Carpeta privada sin escritura.');
        return $path;
    }

    private function path(array $stage): string
    {
        $token=(string)($stage['token']??'');$ext=(string)($stage['ext']??'');
        if(!preg_match('/^[0-9a-f]{40}$/D',$token) ||
           !in_array($ext,['xlsx','csv'],true))
            throw new HttpException(422,'Archivo de precios temporal inválido.');
        return $this->folder().'/'.$token.'.'.$ext;
    }
}
