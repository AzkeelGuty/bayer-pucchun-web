<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;
use PDO;

/** Asigna números reservados; jamás acredita una emisión. Comparte el contador de producción. */
final class AutomaticFiscalNumberService
{
    private ?bool $installed=null;
    public function __construct(private PDO $pdo) {}
    public function available(): bool
    {
        return $this->installed??=(bool)$this->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sunat_numeros_reservados'")->fetchColumn();
    }

    public static function series(string $type): string
    {
        [$key,$default]=match($type){
            '01'=>['SUNAT_SERIE_FACTURA','F001'],
            '03'=>['SUNAT_SERIE_BOLETA','B001'],
            '07'=>['SUNAT_SERIE_NOTA_CREDITO','FC01'],
            '08'=>['SUNAT_SERIE_NOTA_DEBITO','FD01'],
            '09'=>['SUNAT_SERIE_GUIA','T001'],
            default=>throw new HttpException(422,'Tipo de documento no numerable.'),
        };
        $serie=strtoupper(trim((string)\env($key,$default)));
        BayerFiscalReferenceService::number($type,$serie.'-1');
        return $serie;
    }

    public static function format(string $series,int $number): string
    {
        if($number<1||$number>99999999)throw new HttpException(409,'Se agotó el rango de correlativos de la serie.');
        return $series.'-'.str_pad((string)$number,8,'0',STR_PAD_LEFT);
    }

    /** Bloqueo común para reservas, emisiones y declaraciones externas de producción. */
    public function lockSeries(string $ruc,string $type,string $series,int $initial=1): int
    {
        if(!$this->pdo->inTransaction())throw new \LogicException('Se requiere una transacción activa.');
        $st=$this->pdo->prepare("INSERT IGNORE INTO sunat_series_fiscales(ruc_emisor,tipo_cpe,serie,ambiente,siguiente) VALUES(?,?,?,'PRODUCCION',?)");
        $st->execute([$ruc,$type,$series,max(1,$initial)]);
        $st=$this->pdo->prepare("SELECT siguiente FROM sunat_series_fiscales WHERE ruc_emisor=? AND tipo_cpe=? AND serie=? AND ambiente='PRODUCCION' FOR UPDATE");
        $st->execute([$ruc,$type,$series]);
        return max($initial,(int)$st->fetchColumn());
    }

    public function nextAvailable(string $ruc,string $type,string $series,int $initial=1,bool $lock=true): int
    {
        if($lock){
            $next=$this->lockSeries($ruc,$type,$series,$initial);
        }else{
            $st=$this->pdo->prepare("SELECT siguiente FROM sunat_series_fiscales WHERE ruc_emisor=? AND tipo_cpe=? AND serie=? AND ambiente='PRODUCCION'");
            $st->execute([$ruc,$type,$series]);$next=max($initial,(int)$st->fetchColumn());
        }
        $st=$this->pdo->prepare("SELECT COALESCE(MAX(correlativo),0) FROM sunat_emisiones WHERE ruc_emisor=? AND tipo_cpe=? AND serie=? AND ambiente='PRODUCCION'");
        $st->execute([$ruc,$type,$series]);$next=max($next,(int)$st->fetchColumn()+1);
        if((new BayerFiscalReferenceService($this->pdo))->available()){
            $st=$this->pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(numero_fiscal,6) AS UNSIGNED)),0) FROM bayer_referencias_fiscales WHERE ruc_emisor=? AND tipo_cpe=? AND numero_fiscal LIKE ?");
            $st->execute([$ruc,$type,$series.'-%']);$next=max($next,(int)$st->fetchColumn()+1);
        }
        if($this->available()){
            $st=$this->pdo->prepare("SELECT COALESCE(MAX(correlativo),0) FROM sunat_numeros_reservados WHERE ruc_emisor=? AND tipo_cpe=? AND serie=?");
            $st->execute([$ruc,$type,$series]);$next=max($next,(int)$st->fetchColumn()+1);
        }
        // También respeta números con esta serie que se registraron históricamente en la cabecera.
        $doc=$type!=='09';
        $sql="SELECT COALESCE(MAX(CAST(SUBSTRING(h.numero,6) AS UNSIGNED)),0) FROM ".($doc?'documentos_cabecera':'guias_cabecera')." h JOIN sucursales s ON s.id=h.sucursal_id JOIN empresas e ON e.id=s.empresa_id ".($doc?'JOIN tipos_documento t ON t.id=h.tipo_documento_id ':'')."WHERE e.ruc=? AND h.numero REGEXP ?";
        $args=[$ruc,'^'.$series.'-[0-9]{1,8}$'];
        if($doc){
            $code=match($type){'01'=>'FAC','03'=>'BOL','07'=>'NCR','08'=>'NDB'};
            $sql.=" AND t.codigo IN (?,?)";array_push($args,$type,$code);
        }
        $st=$this->pdo->prepare($sql);$st->execute($args);$next=max($next,(int)$st->fetchColumn()+1);
        self::format($series,$next);
        return $next;
    }

    private static function initial(string $type): int
    {
        $initial=(int)\env(match($type){'01'=>'SUNAT_CORRELATIVO_INICIAL','03'=>'SUNAT_CORRELATIVO_BOLETA','07'=>'SUNAT_CORRELATIVO_NOTA_CREDITO','08'=>'SUNAT_CORRELATIVO_NOTA_DEBITO','09'=>'SUNAT_CORRELATIVO_GUIA'},'1');
        return max(1,$initial);
    }

    /** Vista previa estrictamente de lectura. Si hay varios emisores, requiere sucursal. */
    public function preview(string $scope,int $typeId=0,int $branchId=0): string
    {
        if(!$this->available())return 'Instale la migración 020';
        if($scope==='documentos'){
            if($typeId<1)return 'Seleccione tipo de documento';
            $st=$this->pdo->prepare('SELECT codigo FROM tipos_documento WHERE id=?');
            $st->execute([$typeId]);$code=$st->fetchColumn();
            if($code===false)return 'Seleccione tipo de documento';
            $type=BayerFiscalReferenceService::type((string)$code);
        }elseif($scope==='guias'){$type='09';}
        else throw new \InvalidArgumentException('Módulo inválido.');
        $series=self::series($type);
        if($branchId>0){
            $st=$this->pdo->prepare('SELECT e.ruc FROM sucursales s JOIN empresas e ON e.id=s.empresa_id WHERE s.id=? AND s.estado=1');
            $st->execute([$branchId]);$ruc=(string)$st->fetchColumn();
        }else{
            $st=$this->pdo->query('SELECT DISTINCT e.ruc FROM sucursales s JOIN empresas e ON e.id=s.empresa_id WHERE s.estado=1 LIMIT 2');
            $rucs=$st->fetchAll(PDO::FETCH_COLUMN);
            if(count($rucs)>1)return 'Seleccione sucursal';
            $ruc=(string)($rucs[0]??'');
        }
        if(!preg_match('/^[0-9]{11}$/D',$ruc)||$ruc==='00000000000')return 'Configure RUC del emisor';
        return self::format($series,$this->nextAvailable($ruc,$type,$series,self::initial($type),false));
    }

    public function advance(string $ruc,string $type,string $series,int $number): void
    {
        $st=$this->pdo->prepare("UPDATE sunat_series_fiscales SET siguiente=GREATEST(siguiente,?) WHERE ruc_emisor=? AND tipo_cpe=? AND serie=? AND ambiente='PRODUCCION'");
        $st->execute([$number+1,$ruc,$type,$series]);
    }

    public function find(string $scope,int $id): ?array
    {
        if(!$this->available())return null;
        $key=match($scope){'documentos'=>'documento_id','guias'=>'guia_id',default=>throw new \InvalidArgumentException('Módulo inválido.')};
        $st=$this->pdo->prepare("SELECT * FROM sunat_numeros_reservados WHERE $key=?");$st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function reserve(string $scope,int $id): void
    {
        if(!$this->available())return; // Compatibilidad con instalaciones anteriores a 020.
        $doc=$scope==='documentos';$key=$doc?'documento_id':'guia_id';$table=$doc?'documentos_cabecera':'guias_cabecera';
        if(!$doc&&$scope!=='guias')throw new \InvalidArgumentException('Módulo inválido.');
        $st=$this->pdo->prepare("SELECT e.ruc,".($doc?'t.codigo tipo':"'09' tipo")." FROM $table h JOIN sucursales s ON s.id=h.sucursal_id JOIN empresas e ON e.id=s.empresa_id ".($doc?'JOIN tipos_documento t ON t.id=h.tipo_documento_id ':'')."WHERE h.id=? FOR UPDATE");
        $st->execute([$id]);$h=$st->fetch(PDO::FETCH_ASSOC);
        if(!$h)throw new HttpException(404,'Registro no encontrado.');
        $ruc=(string)$h['ruc'];
        $type=BayerFiscalReferenceService::type((string)$h['tipo']);
        if(!preg_match('/^[0-9]{11}$/D',$ruc)||$ruc==='00000000000')return; // No reservar para un emisor ficticio.
        $old=$this->find($scope,$id);
        if($old){
            if($old['ruc_emisor']!==$ruc||$old['tipo_cpe']!==$type)throw new HttpException(409,'No se puede cambiar el emisor o tipo de un número reservado.');
            return;
        }
        $serie=self::series($type);
        $initial=self::initial($type);
        $n=$this->nextAvailable($ruc,$type,$serie,max(1,$initial));
        $st=$this->pdo->prepare("INSERT INTO sunat_numeros_reservados($key,ruc_emisor,tipo_cpe,serie,correlativo) VALUES(?,?,?,?,?)");
        $st->execute([$id,$ruc,$type,$serie,$n]);$this->advance($ruc,$type,$serie,$n);
    }

    public function labels(string $scope,array $ids): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
        $real=(new BayerFiscalReferenceService($this->pdo))->displayNumbers($scope,$ids);
        $labels=[];
        foreach($ids as $id)$labels[$id]=BayerFiscalReferenceService::displayLabel($real[$id]??null,$id);
        if(!$ids||!$this->available())return $labels;
        $key=match($scope){'documentos'=>'documento_id','guias'=>'guia_id',default=>throw new \InvalidArgumentException('Módulo inválido.')};
        $st=$this->pdo->prepare("SELECT $key registro_id,serie,correlativo FROM sunat_numeros_reservados WHERE $key IN (".implode(',',array_fill(0,count($ids),'?')).")");
        $st->execute($ids);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row){
            $id=(int)$row['registro_id'];
            if(empty($real[$id]))$labels[$id]=self::format($row['serie'],(int)$row['correlativo']).' · Pendiente de emisión';
        }
        return $labels;
    }
}
