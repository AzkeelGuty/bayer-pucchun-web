<?php
declare(strict_types=1);
namespace App\Services;
use App\Exceptions\HttpException;
use PDO;
use PDOException;

final class BayerFiscalReferenceService
{
    public function __construct(private PDO $pdo) {}
    public function available(): bool
    {
        return (bool)$this->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bayer_referencias_fiscales'")->fetchColumn();
    }
    public static function type(string $code): string
    {
        return match(strtoupper($code)){
            'FAC','01'=>'01','BOL','03'=>'03','NCR','07'=>'07','NDB','08'=>'08','GRE','09'=>'09',
            default=>throw new HttpException(422,'Tipo fiscal no admitido.'),
        };
    }
    public static function number(string $type,string $number): string
    {
        $number=strtoupper(trim($number));
        if(!preg_match('/^([A-Z0-9]{4})-([0-9]{1,8})$/D',$number,$m) || (int)$m[2]<1)
            throw new HttpException(422,'Ingrese la serie y correlativo fiscal reales.');
        $pattern=match($type){
            '01'=>'/^(F[A-Z0-9]{3}|E001)$/D',
            '03'=>'/^(B[A-Z0-9]{3}|EB01)$/D',
            '07','08'=>'/^(F[A-Z0-9]{3}|B[A-Z0-9]{3}|E[A-Z0-9]{3})$/D',
            '09'=>'/^(T[A-Z0-9]{3}|EG[A-Z0-9]{2})$/D',
            default=>'/(?!) /',
        };
        if(!preg_match($pattern,$m[1]))throw new HttpException(422,'La serie no corresponde al tipo de documento electrónico.');
        return $m[1].'-'.str_pad((string)(int)$m[2],8,'0',STR_PAD_LEFT);
    }
    public function find(string $scope,int $id): ?array
    {
        $key=match($scope){'documentos'=>'documento_id','guias'=>'guia_id',default=>throw new HttpException(422,'Módulo inválido.')};
        $st=$this->pdo->prepare("SELECT r.*,CONCAT_WS(' / ',dp.nombre,p.nombre,d.nombre) ubicacion FROM bayer_referencias_fiscales r LEFT JOIN distritos d ON d.id=r.distrito_despacho_id LEFT JOIN provincias p ON p.id=d.provincia_id LEFT JOIN departamentos dp ON dp.id=p.departamento_id WHERE r.$key=?");
        $st->execute([$id]);return $st->fetch(PDO::FETCH_ASSOC)?:null;
    }
    /** Consulta en lote; no devuelve referencias internas como números fiscales. */
    public function displayNumbers(string $scope,array $ids): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
        if(!$ids||!$this->available())return [];
        $doc=$scope==='documentos';
        if(!$doc&&$scope!=='guias')throw new \InvalidArgumentException('Módulo inválido.');
        $table=$doc?'documentos_cabecera':'guias_cabecera';
        $key=$doc?'documento_id':'guia_id';
        $number=$doc?"CASE WHEN em.estado IN ('ACEPTADO','ACEPTADO_OBSERVADO') THEN CONCAT(em.serie,'-',LPAD(em.correlativo,8,'0')) WHEN em.id IS NULL THEN rf.numero_fiscal ELSE NULL END":'rf.numero_fiscal';
        $emission=$doc?" LEFT JOIN sunat_emisiones em ON em.documento_id=h.id AND em.ambiente='PRODUCCION' AND em.ruc_emisor=e.ruc":"";
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $st=$this->pdo->prepare("SELECT h.id,$number numero_fiscal FROM $table h JOIN sucursales s ON s.id=h.sucursal_id JOIN empresas e ON e.id=s.empresa_id LEFT JOIN bayer_referencias_fiscales rf ON rf.$key=h.id AND rf.ruc_emisor=e.ruc".$emission." WHERE h.id IN ($ph)");
        $st->execute($ids);return array_column($st->fetchAll(PDO::FETCH_ASSOC),'numero_fiscal','id');
    }

    public static function displayLabel(?string $number,int $id=0): string
    {
        return $number!==null&&$number!==''?$number:('Pendiente de número fiscal'.($id>0?' · #'.$id:''));
    }

    public function assertIdentity(string $scope,int $id): void
    {
        if(!$this->available())return;
        $doc=$scope==='documentos';
        if(!$doc&&$scope!=='guias')throw new \InvalidArgumentException('Módulo inválido.');
        $key=$doc?'documento_id':'guia_id';$table=$doc?'documentos_cabecera':'guias_cabecera';
        $sql="SELECT rf.ruc_emisor,rf.tipo_cpe,e.ruc,".($doc?'td.codigo tipo':"'09' tipo")." FROM $table h JOIN bayer_referencias_fiscales rf ON rf.$key=h.id AND rf.numero_fiscal IS NOT NULL JOIN sucursales s ON s.id=h.sucursal_id JOIN empresas e ON e.id=s.empresa_id ".($doc?'JOIN tipos_documento td ON td.id=h.tipo_documento_id ':'')."WHERE h.id=?";
        $st=$this->pdo->prepare($sql);$st->execute([$id]);$row=$st->fetch(PDO::FETCH_ASSOC);
        if($row&&($row['ruc_emisor']!==$row['ruc']||$row['tipo_cpe']!==self::type((string)$row['tipo'])))
            throw new HttpException(409,'No se puede cambiar el emisor o tipo de un registro con número fiscal asociado.');
    }

    public function save(string $scope,int $id,string $number,?int $district,bool $confirmed,int $actor,bool $allowDraft=false): void
    {
        if(!$this->available())throw new HttpException(409,'Ejecute la migración 019.');
        if($id<1||$actor<1)throw new HttpException(422,'Registro inválido.');
        $doc=$scope==='documentos';
        if(!$doc&&$scope!=='guias')throw new HttpException(422,'Módulo inválido.');
        $key=$doc?'documento_id':'guia_id';$table=$doc?'documentos_cabecera':'guias_cabecera';
        $ownsTransaction=!$this->pdo->inTransaction();
        if($ownsTransaction)$this->pdo->beginTransaction();
        try{
            $sql="SELECT h.id,h.estado_registro,e.ruc,".($doc?'t.codigo tipo':"'09' tipo")." FROM $table h JOIN sucursales s ON s.id=h.sucursal_id JOIN empresas e ON e.id=s.empresa_id ".($doc?'JOIN tipos_documento t ON t.id=h.tipo_documento_id ':'')."WHERE h.id=? FOR UPDATE";
            $st=$this->pdo->prepare($sql);$st->execute([$id]);$record=$st->fetch(PDO::FETCH_ASSOC);
            if(!$record)throw new HttpException(404,'Registro no encontrado.');
            $states=$allowDraft?['BORRADOR','OBSERVADO','PUBLICADO']:['PUBLICADO'];
            if(!in_array($record['estado_registro'],$states,true))throw new HttpException(409,'El estado del registro no permite asociar una referencia fiscal.');
            $type=self::type((string)$record['tipo']);$ruc=(string)$record['ruc'];
            if(!preg_match('/^[0-9]{11}$/D',$ruc)||$ruc==='00000000000')throw new HttpException(422,'Configure el RUC real del distribuidor.');
            $st=$this->pdo->prepare("SELECT * FROM bayer_referencias_fiscales WHERE $key=? FOR UPDATE");
            $st->execute([$id]);$old=$st->fetch(PDO::FETCH_ASSOC);
            $fiscal=$old['numero_fiscal']??null;
            if($old&&$fiscal!==null&&($old['ruc_emisor']!==$ruc||$old['tipo_cpe']!==$type))
                throw new HttpException(409,'La empresa o el tipo fiscal cambió; revise la referencia existente.');
            if(trim($number)!==''){
                if(!$confirmed)throw new HttpException(422,'Confirme que dispone del comprobante electrónico real.');
                $fiscal=self::number($type,$number);
                if($doc){
                    $st=$this->pdo->prepare("SELECT 1 FROM sunat_emisiones WHERE documento_id=? AND ambiente='PRODUCCION' LIMIT 1");
                    $st->execute([$id]);
                    if($st->fetchColumn())throw new HttpException(409,'Ya existe un intento en producción; revise su resultado.');
                }
                [$serie,$correlativo]=explode('-',$fiscal);
                $automatic=new AutomaticFiscalNumberService($this->pdo);
                if($automatic->available()){
                    $automatic->lockSeries($ruc,$type,$serie);
                    $st=$this->pdo->prepare("SELECT documento_id,guia_id FROM sunat_numeros_reservados WHERE ruc_emisor=? AND tipo_cpe=? AND serie=? AND correlativo=?");
                    $st->execute([$ruc,$type,$serie,(int)$correlativo]);
                    $reservation=$st->fetch(PDO::FETCH_ASSOC);
                    if($reservation&&(int)($reservation[$key]??0)!==$id)
                        throw new HttpException(409,'El número está reservado para otro registro.');
                    $automatic->advance($ruc,$type,$serie,(int)$correlativo);
                }
                $st=$this->pdo->prepare("SELECT 1 FROM sunat_emisiones WHERE ruc_emisor=? AND tipo_cpe=? AND serie=? AND correlativo=? AND ambiente='PRODUCCION' LIMIT 1");
                $st->execute([$ruc,$type,$serie,(int)$correlativo]);
                if($st->fetchColumn())throw new HttpException(409,'El número ya está registrado en emisiones SUNAT.');
            }
            if($district!==null){
                $st=$this->pdo->prepare('SELECT 1 FROM distritos WHERE id=?');$st->execute([$district]);
                if(!$st->fetchColumn())throw new HttpException(422,'Seleccione una ubicación de despacho válida.');
            }
            if($old){
                $st=$this->pdo->prepare("UPDATE bayer_referencias_fiscales SET ruc_emisor=?,tipo_cpe=?,numero_fiscal=?,distrito_despacho_id=?,registrado_por=? WHERE $key=?");
                $st->execute([$ruc,$type,$fiscal,$district,$actor,$id]);
            }else{
                $st=$this->pdo->prepare("INSERT INTO bayer_referencias_fiscales($key,ruc_emisor,tipo_cpe,numero_fiscal,distrito_despacho_id,registrado_por) VALUES(?,?,?,?,?,?)");
                $st->execute([$id,$ruc,$type,$fiscal,$district,$actor]);
            }
            if($ownsTransaction)$this->pdo->commit();
        }catch(\Throwable $e){
            if($ownsTransaction&&$this->pdo->inTransaction())$this->pdo->rollBack();
            if($e instanceof PDOException&&(int)($e->errorInfo[1]??0)===1062)throw new HttpException(409,'Número fiscal asociado a otro registro del emisor.');
            throw $e;
        }
    }
}
