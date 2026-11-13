<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

/** Adaptador real de SUNAT. Nunca simula un CDR ni genera un estado ACEPTADO localmente. */
final class SunatInvoiceGateway
{
    /**
     * @return array{xml:string,cdr:?string,codigo:?string,mensaje:string,observaciones:string}
     */
    public function send(array $document,array $totals,string $serie,int $correlativo): array
    {
        $ready=new SunatReadinessService();
        if(!$ready->isReady()) throw new RuntimeException('Configurar SUNAT antes de emitir.');
        $invoice=SunatInvoiceBuilder::createGreenterInvoice($document,$totals,$serie,$correlativo);
        $see=new \Greenter\See();
        $content=file_get_contents($ready->certificatePath());
        if($content===false) throw new RuntimeException('No se puede abrir el certificado digital.');
        $see->setCertificate($content);
        $see->setService($ready->mode()==='beta'
            ? \Greenter\Ws\Services\SunatEndpoints::FE_BETA
            : \Greenter\Ws\Services\SunatEndpoints::FE_PRODUCCION);
        $see->setClaveSOL((string)\env('SUNAT_RUC',''),(string)\env('SUNAT_SOL_USER',''),
            (string)\env('SUNAT_SOL_PASSWORD',''));

        $result=$see->send($invoice);
        $xml=(string)($see->getFactory()->getLastXml()??'');
        if($xml==='')throw new RuntimeException('No se produjo un XML firmado.');
        if(!$result->isSuccess()){
            $error=$result->getError();
            return [
                'xml'=>$xml,'cdr'=>null,'codigo'=>null,
                'mensaje'=>'El servicio no confirmó la recepción. Código: '.(string)($error?->getCode()??'sin código'),
                'observaciones'=>'',
            ];
        }
        $cdrZip=$result->getCdrZip();
        $cdr=$result->getCdrResponse();
        if(!is_string($cdrZip)||$cdrZip===''||!$cdr){
            return ['xml'=>$xml,'cdr'=>null,'codigo'=>null,
                'mensaje'=>'No se recibió constancia CDR verificable.','observaciones'=>''];
        }
        $notes=method_exists($cdr,'getNotes')?$cdr->getNotes():[];
        return [
            'xml'=>$xml,'cdr'=>$cdrZip,'codigo'=>(string)$cdr->getCode(),
            'mensaje'=>(string)$cdr->getDescription(),
            'observaciones'=>is_array($notes)?implode(' | ',array_map('strval',$notes)):'',
        ];
    }
}
