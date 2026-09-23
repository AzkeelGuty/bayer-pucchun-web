<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Genera números operativos sugeridos a partir de las series usadas por el sistema.
 * La restricción UNIQUE de cada cabecera sigue siendo la última barrera contra duplicados.
 */
final class OperationalNumberingService
{
    public function nextDocumentNumber(int $typeId): string
    {
        $st=\db()->prepare('SELECT codigo FROM tipos_documento WHERE id=? LIMIT 1');
        $st->execute([$typeId]);
        $code=strtoupper(trim((string)$st->fetchColumn()));
        if($code==='') return '';

        $prefix=match($code){
            'FAC'=>'F001',
            'BOL'=>'B001',
            'PED'=>'P001',
            default=>substr(preg_replace('/[^A-Z0-9]/','',$code) ?: 'D',0,1).'001',
        };

        return $this->nextForSeries('documentos_cabecera',$prefix,$typeId);
    }

    public function documentSuggestions(array $types): array
    {
        $result=[];
        foreach($types as $type){
            $id=(int)($type['id']??0);
            if($id>0) $result[$id]=$this->nextDocumentNumber($id);
        }
        return $result;
    }

    public function nextGuideNumber(): string
    {
        return $this->nextForSeries('guias_cabecera','T001',null);
    }

    private function nextForSeries(string $table,string $prefix,?int $typeId): string
    {
        $sql='SELECT numero FROM '.$table.' WHERE numero LIKE ?';
        $params=[$prefix.'-%'];
        if($typeId!==null){
            $sql.=' AND tipo_documento_id=?';
            $params[]=$typeId;
        }

        $st=\db()->prepare($sql);
        $st->execute($params);
        $max=0;
        $pattern='/^'.preg_quote($prefix,'/').'-(\d{1,12})$/';
        foreach($st->fetchAll(\PDO::FETCH_COLUMN) as $number){
            if(preg_match($pattern,(string)$number,$m)) $max=max($max,(int)$m[1]);
        }

        return $prefix.'-'.str_pad((string)($max+1),6,'0',STR_PAD_LEFT);
    }
}
