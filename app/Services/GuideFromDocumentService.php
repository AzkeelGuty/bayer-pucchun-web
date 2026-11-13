<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\Policies\OperationalOwnershipPolicy;
use App\Repositories\{DocumentRepository,GuideRepository};
use PDO;
use PDOException;
use Throwable;

/**
 * Crea una guía interna desde un comprobante SIN emitir una GRE ante SUNAT.
 * El número INT-GD-... se reserva desde el contador interno del ERP, nunca es una serie fiscal.
 * La restricción UNIQUE(documento_origen_id) evita guías duplicadas.
 */
final class GuideFromDocumentService
{
    public function __construct(private ?PDO $pdo=null)
    {
        $this->pdo ??= \db();
    }

    /** @return array{guide_id:int,created:bool} */
    public function generate(int $documentId,int $actorId): array
    {
        if($documentId<1 || $actorId<1) throw new HttpException(422,'Documento o usuario inválido.');
        $pdo=$this->pdo;
        $owns=!$pdo->inTransaction();
        if(!$owns) throw new \LogicException('La generación debe ejecutarse fuera de una transacción externa.');
        $pdo->beginTransaction();
        try{
            $document=(new DocumentRepository($pdo))->find($documentId);
            if($document===null) throw new HttpException(404,'El documento no existe.');
            $h=$document['header'];
            OperationalOwnershipPolicy::require($h);
            if(!in_array((string)$h['estado_registro'],['BORRADOR','VALIDADO','PUBLICADO'],true)
                || (string)($h['estado_comprobante']??'VIGENTE')!=='VIGENTE'){
                throw new HttpException(409,'Solo se genera guía desde un documento vigente y sin observaciones.');
            }

            $stmt=$pdo->prepare('SELECT codigo FROM tipos_documento WHERE id=?');
            $stmt->execute([(int)$h['tipo_documento_id']]);
            if(!in_array((string)$stmt->fetchColumn(),['FAC','BOL'],true)){
                throw new HttpException(422,'La guía automática requiere una factura o boleta de venta.');
            }

            $stmt=$pdo->prepare('SELECT id FROM guias_cabecera WHERE documento_origen_id=? LIMIT 1');
            $stmt->execute([$documentId]);
            $found=$stmt->fetchColumn();
            if($found!==false){
                $pdo->commit();
                return ['guide_id'=>(int)$found,'created'=>false];
            }

            $details=[];
            foreach($document['details'] as $line){
                $details[]=[
                    'producto_id'=>(int)$line['producto_id'],
                    'unidad_id'=>(int)$line['unidad_id'],
                    'cantidad'=>(string)$line['cantidad'],
                    'valor_unitario'=>isset($line['valor_unitario'])?(string)$line['valor_unitario']:null,
                ];
            }
            if(!$details) throw new HttpException(422,'El documento no contiene productos para trasladar.');

            $stmt=$pdo->prepare('SELECT departamento_id,provincia_id,distrito_id FROM clientes WHERE id=?');
            $stmt->execute([(int)$h['cliente_id']]);
            $geo=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
            $complete=isset($geo['departamento_id'],$geo['provincia_id'],$geo['distrito_id']);
            $header=[
                'fecha'=>date('Y-m-d'),
                'cliente_id'=>(int)$h['cliente_id'],
                'vendedor_id'=>(int)$h['vendedor_id'],
                'sucursal_id'=>(int)$h['sucursal_id'],
                'precio_igv_modalidad'=>$h['precio_igv_modalidad']??null,
                'departamento_id'=>$complete?(int)$geo['departamento_id']:null,
                'provincia_id'=>$complete?(int)$geo['provincia_id']:null,
                'distrito_id'=>$complete?(int)$geo['distrito_id']:null,
            ];
            $id=(new GuideRepository($pdo))->createAutoNumbered($header,$details,$actorId);
            $stmt=$pdo->prepare("UPDATE guias_cabecera SET documento_origen_id=?,estado_sunat='NO_EMITIDA' WHERE id=?");
            $stmt->execute([$documentId,$id]);
            $pdo->commit();
            return ['guide_id'=>$id,'created'=>true];
        }catch(Throwable $error){
            if($pdo->inTransaction()) $pdo->rollBack();
            // Otra petición concurrente pudo haber creado la guía; devolverla sin duplicar.
            if($error instanceof PDOException && (string)$error->getCode()==='23000'){
                $stmt=$pdo->prepare('SELECT id FROM guias_cabecera WHERE documento_origen_id=? LIMIT 1');
                $stmt->execute([$documentId]);
                $existing=$stmt->fetchColumn();
                if($existing!==false) return ['guide_id'=>(int)$existing,'created'=>false];
            }
            throw $error;
        }
    }
}
