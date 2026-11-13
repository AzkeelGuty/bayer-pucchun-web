<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use PDO;
use RuntimeException;

/**
 * Numeración interna del ERP, SIN validez de comprobante/GRE emitido ante SUNAT.
 * preview* muestra una sugerencia no reservada; next* requiere transacción activa.
 * Cada ámbito usa un contador bloqueado con SELECT ... FOR UPDATE.
 */
final class InternalNumberingService
{
    public function __construct(private PDO $pdo) {}

    /** @return array{scope:string,prefix:string,table:string,typeId:int|null,digits:int} */
    private function documentDefinition(int $typeId): array
    {
        if ($typeId < 1) throw new HttpException(422,'Tipo de documento inválido.');
        $st=$this->pdo->prepare('SELECT codigo FROM tipos_documento WHERE id=?');
        $st->execute([$typeId]);
        $code=strtoupper((string)$st->fetchColumn());
        if(!in_array($code,['FAC','BOL','NCR','NDB'],true)){
            throw new HttpException(422,'La numeración interna requiere factura, boleta, nota de crédito o nota de débito.');
        }
        return [
            'scope'=>'DOC:'.$code,
            'prefix'=>'INT-'.$code.'-',
            'table'=>'documentos_cabecera',
            'typeId'=>$typeId,
            'digits'=>8,
        ];
    }

    /** @return array{scope:string,prefix:string,table:string,typeId:int|null,digits:int} */
    private function guideDefinition(): array
    {
        return ['scope'=>'GUIDE','prefix'=>'INT-GD-','table'=>'guias_cabecera','typeId'=>null,'digits'=>12];
    }

    public function previewDocument(int $typeId): string
    {
        return $this->preview($this->documentDefinition($typeId));
    }

    public function previewGuide(): string
    {
        return $this->preview($this->guideDefinition());
    }

    public function nextDocument(int $typeId): string
    {
        return $this->next($this->documentDefinition($typeId));
    }

    public function nextGuide(): string
    {
        return $this->next($this->guideDefinition());
    }

    private function usedMaximum(array $def): int
    {
        // También considera los identificadores creados antes de implantar el contador 010.
        $pattern='^'.$def['prefix'].'[0-9]+$';
        $length=strlen($def['prefix'])+1;
        if($def['typeId']!==null){
            $sql='SELECT COALESCE(MAX(CAST(SUBSTRING(numero, ?) AS UNSIGNED)),0)
                  FROM documentos_cabecera WHERE tipo_documento_id=? AND numero REGEXP ?';
            $args=[$length,$def['typeId'],$pattern];
        }else{
            $sql='SELECT COALESCE(MAX(CAST(SUBSTRING(numero, ?) AS UNSIGNED)),0)
                  FROM guias_cabecera WHERE numero REGEXP ?';
            $args=[$length,$pattern];
        }
        $st=$this->pdo->prepare($sql);
        $st->execute($args);
        return max(0,(int)$st->fetchColumn());
    }

    private function counter(string $scope): int
    {
        $st=$this->pdo->prepare('SELECT siguiente FROM numeraciones_internas WHERE ambito=?');
        $st->execute([$scope]);
        $value=$st->fetchColumn();
        return $value===false?1:max(1,(int)$value);
    }

    private function formatted(array $def,int $number): string
    {
        if($number<1 || $number>99999999 && $def['digits']===8 || $number>999999999999 && $def['digits']===12){
            throw new RuntimeException('Se agotó el rango del correlativo interno del ERP.');
        }
        return $def['prefix'].str_pad((string)$number,$def['digits'],'0',STR_PAD_LEFT);
    }

    private function preview(array $def): string
    {
        // Un preview es orientativo: el número se reserva recién al guardar.
        $n=max($this->counter($def['scope']),$this->usedMaximum($def)+1);
        return $this->formatted($def,$n);
    }

    private function next(array $def): string
    {
        if(!$this->pdo->inTransaction()){
            throw new RuntimeException('La numeración automática requiere una transacción activa.');
        }
        $st=$this->pdo->prepare('INSERT IGNORE INTO numeraciones_internas(ambito,siguiente) VALUES(?,1)');
        $st->execute([$def['scope']]);
        $st=$this->pdo->prepare('SELECT siguiente FROM numeraciones_internas WHERE ambito=? FOR UPDATE');
        $st->execute([$def['scope']]);
        $current=(int)$st->fetchColumn();
        $n=max(1,$current,$this->usedMaximum($def)+1);
        $number=$this->formatted($def,$n);
        $st=$this->pdo->prepare('UPDATE numeraciones_internas SET siguiente=? WHERE ambito=?');
        $st->execute([$n+1,$def['scope']]);
        return $number;
    }
}
