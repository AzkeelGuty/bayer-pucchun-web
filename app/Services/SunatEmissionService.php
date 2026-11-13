<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;
use PDO;
use Throwable;

/**
 * Proceso explícito, auditable y de un solo intento: una falla de red no se reenvía
 * sin consultar antes a SUNAT (podría haber llegado y generado un CDR).
 */
final class SunatEmissionService
{
    public function __construct(private PDO $pdo,private ?SunatInvoiceGateway $gateway=null)
    {
        $this->gateway??=new SunatInvoiceGateway();
    }

    public function findForDocument(int $id,?string $ambiente=null): ?array
    {
        if($ambiente!==null && !in_array($ambiente,['BETA','PRODUCCION'],true))
            throw new HttpException(422,'Ambiente tributario inválido.');
        if($ambiente!==null){
            $st=$this->pdo->prepare('SELECT * FROM sunat_emisiones WHERE documento_id=? AND ambiente=? LIMIT 1');
            $st->execute([$id,$ambiente]);
        } else {
            $st=$this->pdo->prepare('SELECT * FROM sunat_emisiones WHERE documento_id=? ORDER BY id DESC LIMIT 1');
            $st->execute([$id]);
        }
        $row=$st->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }

    public function recent(int $limit=50): array
    {
        $limit=max(1,min(100,$limit));
        return $this->pdo->query(
            'SELECT e.*,d.numero AS referencia_interna,d.fecha AS fecha_documento
             FROM sunat_emisiones e JOIN documentos_cabecera d ON d.id=e.documento_id
             ORDER BY e.id DESC LIMIT '.$limit
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function emitInvoice(int $documentId,int $userId,string $priceMode,bool $allTaxable): array
    {
        if(!$allTaxable) throw new HttpException(422,'Confirma que TODOS los productos están gravados con IGV 18 %.');
        if($documentId<1||$userId<1)throw new HttpException(422,'Datos de emisión incorrectos.');
        $ready=new SunatReadinessService();
        if(!$ready->isReady())
            throw new HttpException(503,'La emisión electrónica está deshabilitada o faltan datos de SUNAT.');
        $document=(new SunatInvoiceBuilder($this->pdo))->snapshot($documentId);
        $totals=SunatInvoiceBuilder::totals($document['lineas'],$priceMode);
        $ruc=(string)$document['ruc_emisor'];
        $serie=strtoupper((string)\env('SUNAT_SERIE_FACTURA',''));
        $ambiente=$ready->mode()==='beta'?'BETA':'PRODUCCION';
        $initial=(int)\env('SUNAT_CORRELATIVO_INICIAL','1');
        $id=0;$folio=0;

        $this->pdo->beginTransaction();
        try {
            $st=$this->pdo->prepare('SELECT id,estado_registro,estado_comprobante FROM documentos_cabecera WHERE id=? FOR UPDATE');
            $st->execute([$documentId]);$header=$st->fetch(PDO::FETCH_ASSOC);
            if(!$header||$header['estado_registro']!=='PUBLICADO'||$header['estado_comprobante']!=='VIGENTE')
                throw new HttpException(409,'El documento ya no está publicado y vigente.');
            if($this->findForDocument($documentId,$ambiente))
                throw new HttpException(409,'El documento ya tiene un intento SUNAT registrado. Consulte su estado antes de repetir.');

            $st=$this->pdo->prepare(
                'INSERT IGNORE INTO sunat_series_fiscales(ruc_emisor,tipo_cpe,serie,ambiente,siguiente) VALUES(?,?,?,?,?)'
            );
            $st->execute([$ruc,'01',$serie,$ambiente,$initial]);
            $st=$this->pdo->prepare(
                'SELECT siguiente FROM sunat_series_fiscales
                 WHERE ruc_emisor=? AND tipo_cpe=? AND serie=? AND ambiente=? FOR UPDATE'
            );
            $st->execute([$ruc,'01',$serie,$ambiente]);
            $folio=(int)$st->fetchColumn();
            SunatInvoiceBuilder::documentName($ruc,$serie,$folio);
            $st=$this->pdo->prepare(
                'UPDATE sunat_series_fiscales SET siguiente=? WHERE ruc_emisor=? AND tipo_cpe=? AND serie=? AND ambiente=?'
            );
            $st->execute([$folio+1,$ruc,'01',$serie,$ambiente]);
            $st=$this->pdo->prepare(
                'INSERT INTO sunat_emisiones(documento_id,usuario_id,ruc_emisor,tipo_cpe,serie,correlativo,ambiente,estado)
                 VALUES(?,?,?,?,?,?,?,?)'
            );
            $st->execute([$documentId,$userId,$ruc,'01',$serie,$folio,$ambiente,'EN_PROCESO']);
            $id=(int)$this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch(Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }

        $outcome=[];
        try {
            $outcome=$this->gateway->send($document,$totals,$serie,$folio);
            $xml=$outcome['xml']??'';
            $cdr=$outcome['cdr']??null;
            $paths=$this->writeEvidence($id,$xml,is_string($cdr)?$cdr:null);
            $hasCdr=is_string($cdr)&&strlen($cdr)>4&&str_starts_with($cdr,'PK');
            $code=$outcome['codigo']??null;
            if(!$hasCdr) {
                $state='SIN_RESPUESTA';
            } elseif((string)$code==='0') {
                $state=trim((string)($outcome['observaciones']??''))===''?'ACEPTADO':'ACEPTADO_OBSERVADO';
            } elseif(ctype_digit((string)$code)&&(int)$code>=2000&&(int)$code<=3999) {
                $state='RECHAZADO';
            } else {
                $state='SIN_RESPUESTA';
            }
            $st=$this->pdo->prepare(
                'UPDATE sunat_emisiones SET estado=?,codigo_cdr=?,mensaje_cdr=?,observaciones=?,
                   xml_sha256=?,xml_archivo=?,cdr_archivo=?,fecha_envio=NOW(),fecha_respuesta=?
                 WHERE id=? AND estado=?'
            );
            $st->execute([
                $state,is_scalar($code)?(string)$code:null,
                mb_substr((string)($outcome['mensaje']??'Sin constancia'),0,1000),
                mb_substr((string)($outcome['observaciones']??''),0,2000),
                $paths['hash'],$paths['xml'],$paths['cdr'],
                $hasCdr?date('Y-m-d H:i:s'):null,$id,'EN_PROCESO',
            ]);
        } catch(Throwable $e) {
            // Una respuesta perdida NO implica rechazo: no enviar una segunda vez.
            \log_event('sunat_envio_incierto',[
                'documento_id'=>$documentId,'emision_id'=>$id,'tipo'=>get_class($e),
            ]);
            $st=$this->pdo->prepare(
                "UPDATE sunat_emisiones SET estado='SIN_RESPUESTA',
                 mensaje_cdr='No se confirmó el resultado de recepción. Verificar en SUNAT antes de cualquier nuevo envío.',
                 fecha_envio=NOW() WHERE id=? AND estado='EN_PROCESO'"
            );
            $st->execute([$id]);
        }
        return $this->findForDocument($documentId,$ambiente)
            ?? throw new \RuntimeException('No se pudo recuperar el estado de emisión.');
    }

    /** @return array{hash:?string,xml:?string,cdr:?string} */
    private function writeEvidence(int $id,string $xml,?string $cdr): array
    {
        $folder=\base_path('storage/private/sunat/emisiones');
        if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder))
            throw new \RuntimeException('No se pudo crear el directorio privado SUNAT.');
        $result=['hash'=>null,'xml'=>null,'cdr'=>null];
        foreach(['xml'=>$xml,'cdr'=>$cdr] as $format=>$bytes) {
            if(!is_string($bytes)||$bytes==='')continue;
            $name=$id.'.'.$format.($format==='cdr'?'.zip':'');
            $path=$folder.DIRECTORY_SEPARATOR.$name;
            if(file_put_contents($path,$bytes,LOCK_EX)===false)
                throw new \RuntimeException('No se pudo guardar el archivo SUNAT.');
            @chmod($path,0600);
            $result[$format]='storage/private/sunat/emisiones/'.$name;
            if($format==='xml')$result['hash']=hash('sha256',$bytes);
        }
        return $result;
    }

    public function evidenceFile(int $emissionId,string $type): array
    {
        if(!in_array($type,['xml','cdr'],true)||$emissionId<1)
            throw new HttpException(422,'Archivo solicitado no admitido.');
        $st=$this->pdo->prepare('SELECT * FROM sunat_emisiones WHERE id=?');
        $st->execute([$emissionId]);$row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new HttpException(404,'Emisión no encontrada.');
        $relative=$row[$type.'_archivo']??'';
        $expected='storage/private/sunat/emisiones/'.$emissionId.'.'.$type.($type==='cdr'?'.zip':'');
        if($relative!==$expected)throw new HttpException(404,'El archivo todavía no está disponible.');
        $file=\base_path($expected);
        if(!is_file($file)||!is_readable($file))
            throw new HttpException(404,'No se encontró el archivo SUNAT.');
        $download=SunatInvoiceBuilder::documentName((string)$row['ruc_emisor'],
            (string)$row['serie'],(int)$row['correlativo']);
        return ['path'=>$file,'filename'=>$download.($type==='xml'?'.xml':'.cdr.zip'),
            'content_type'=>$type==='xml'?'application/xml':'application/zip'];
    }
}
