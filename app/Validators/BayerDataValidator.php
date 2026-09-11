<?php
namespace App\Validators;
class BayerDataValidator {
    public function validate(array $data,string $dataset): array {
        $required = $dataset==='stock'
            ? ['dealerId','dealerName','stockDate','warehouseId','warehouseName','materialId','materialName','measureUnit','quantity']
            : ['dealerId','dealerName','documentNumber','documentDate','salesId','salesName','branchId','branchName','customerId','customerName','materialId','materialName','measureUnit','quantity','province','department','district'];
        if($dataset==='documents') array_splice($required,2,0,['documentTypeId','documentType']);
        $errors=[];
        foreach($required as $f) if(trim((string)($data[$f]??''))==='') $errors[$f]='Campo obligatorio';
        if(isset($data['dealerId']) && !preg_match('/^\d{11}$/',(string)$data['dealerId'])) $errors['dealerId']='El RUC debe tener 11 dígitos';
        if(isset($data['documentDate']) && $data['documentDate'] && !$this->dateOk($data['documentDate'])) $errors['documentDate']='Fecha inválida';
        if(isset($data['stockDate']) && $data['stockDate'] && !$this->dateOk($data['stockDate'])) $errors['stockDate']='Fecha inválida';
        if(isset($data['expirationDate']) && $data['expirationDate'] && !$this->dateOk($data['expirationDate'])) $errors['expirationDate']='Fecha inválida';
        if(isset($data['quantity']) && (!is_numeric($data['quantity']) || (float)$data['quantity']<0)) $errors['quantity']='Cantidad inválida';
        return $errors;
    }
    private function dateOk(string $v): bool { $d=\DateTime::createFromFormat('Y-m-d',$v); return $d && $d->format('Y-m-d')===$v; }
}
