<?php
declare(strict_types=1);
namespace App\Services;

/** Configuración exclusiva del servidor. Ninguna credencial se expone en la interfaz. */
final class SunatReadinessService
{
    public function mode(): string
    {
        $mode=strtolower(trim((string)\env('SUNAT_MODE','beta')));
        return in_array($mode,['beta','produccion'],true) ? $mode : 'invalido';
    }

    public function certificatePath(): ?string
    {
        $raw=trim((string)\env('SUNAT_CERT_PEM_PATH',''));
        if($raw==='') return null;
        $path=realpath($raw);
        $folder=realpath(\base_path('storage/private/sunat'));
        if($path===false || $folder===false || !str_starts_with($path,$folder.DIRECTORY_SEPARATOR) ||
            !is_file($path) || !is_readable($path) || !preg_match('/\.pem$/i',$path)) return null;
        return $path;
    }

    /** @return list<string> */
    public function issues(): array
    {
        $problems=[];
        if(!filter_var(\env('SUNAT_ENABLED','false'),FILTER_VALIDATE_BOOL)) {
            $problems[]='Activar SUNAT_ENABLED=true solamente después de configurar el emisor.';
        }
        if($this->mode()==='invalido') $problems[]='SUNAT_MODE debe ser beta o produccion.';
        if($this->mode()==='produccion' &&
           !filter_var(\env('SUNAT_ALLOW_PRODUCTION','false'),FILTER_VALIDATE_BOOL))
            $problems[]='Para producción también se requiere SUNAT_ALLOW_PRODUCTION=true.';
        foreach(['SUNAT_RUC'=>'RUC','SUNAT_SOL_USER'=>'usuario SOL',
            'SUNAT_SOL_PASSWORD'=>'clave SOL','SUNAT_SERIE_FACTURA'=>'serie fiscal F***',
            'SUNAT_UBIGEO'=>'ubigeo','SUNAT_DEPARTAMENTO'=>'departamento',
            'SUNAT_PROVINCIA'=>'provincia','SUNAT_DISTRITO'=>'distrito',
            'SUNAT_DIRECCION'=>'dirección fiscal'] as $env=>$label) {
            if(trim((string)\env($env,''))==='') $problems[]='Configurar '.$label.' en '.$env.'.';
        }
        if(!preg_match('/^[0-9]{11}$/D',(string)\env('SUNAT_RUC','')))
            $problems[]='SUNAT_RUC debe contener 11 dígitos.';
        if(!preg_match('/^F[A-Z0-9]{3}$/D',strtoupper((string)\env('SUNAT_SERIE_FACTURA',''))))
            $problems[]='SUNAT_SERIE_FACTURA debe tener formato F001, F002, etc.';
        if(!preg_match('/^[0-9]{6}$/D',(string)\env('SUNAT_UBIGEO','')))
            $problems[]='SUNAT_UBIGEO debe tener 6 dígitos.';
        $start=filter_var(\env('SUNAT_CORRELATIVO_INICIAL','1'),FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>99999999]]);
        if($start===false) $problems[]='SUNAT_CORRELATIVO_INICIAL debe estar entre 1 y 99999999.';
        if((string)\env('SUNAT_IGV_PORCENTAJE','18')!=='18')
            $problems[]='El emisor inicial sólo admite operaciones gravadas con IGV del 18 %.';
        $path=$this->certificatePath();
        if($path===null) {
            $problems[]='Instalar certificado PEM válido en storage/private/sunat y configurar SUNAT_CERT_PEM_PATH.';
        } else {
            $content=(string)@file_get_contents($path);
            if(!str_contains($content,'BEGIN PRIVATE KEY') &&
               !str_contains($content,'BEGIN RSA PRIVATE KEY')) {
                $problems[]='El PEM no contiene una clave privada para firmar.';
            }
            if(!str_contains($content,'BEGIN CERTIFICATE'))
                $problems[]='El PEM no contiene el certificado público.';
        }
        if(!is_file(\base_path('vendor/autoload.php')) || !class_exists(\Greenter\See::class))
            $problems[]='Instalar Greenter con composer install (SOAP, DOM, OpenSSL, ZIP e Intl).';
        foreach(['soap','dom','openssl','zip','intl'] as $extension) {
            if(!extension_loaded($extension)) $problems[]='Habilitar extensión PHP '.$extension.'.';
        }
        return array_values(array_unique($problems));
    }

    public function isReady(): bool { return $this->issues()===[]; }

    /** Datos públicos de configuración; nunca contraseñas, claves ni PEM. */
    public function publicSummary(): array
    {
        return [
            'ambiente'=>$this->mode(),
            'ruc'=>(string)\env('SUNAT_RUC',''),
            'serie'=>(string)\env('SUNAT_SERIE_FACTURA',''),
            'certificado_instalado'=>$this->certificatePath()!==null,
            'habilitado'=>$this->isReady(),
            'issues'=>$this->issues(),
        ];
    }
}
