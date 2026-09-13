<?php
namespace App\Validators;

class BayerDataValidator
{
    public function validate(array $data,string $dataset): array
    {
        $required = $dataset==='stock'
            ? ['dealerId','dealerName','stockDate','warehouseId','warehouseName','materialId','materialName','measureUnit','quantity']
            : ['dealerId','dealerName','documentNumber','documentDate','salesId','salesName','branchId','branchName','customerId','customerName','materialId','materialName','measureUnit','quantity','province','department','district'];

        if($dataset==='documents') array_splice($required,2,0,['documentTypeId','documentType']);

        $errors=[];
        foreach($required as $field){
            if(trim((string)($data[$field]??''))==='') $errors[$field]='Campo obligatorio';
        }

        if(isset($data['dealerId']) && !preg_match('/^\d{11}$/',(string)$data['dealerId'])) {
            $errors['dealerId']='El RUC debe tener 11 dígitos';
        }

        foreach(['documentDate','stockDate','expirationDate'] as $field){
            if(isset($data[$field]) && $data[$field] !== '' && !$this->dateOk((string)$data[$field])) {
                $errors[$field]='Fecha inválida';
            }
        }

        if(isset($data['quantity'])){
            $quantity=(string)$data['quantity'];
            $valid=preg_match('/^\d+(?:\.\d{1,3})?$/',$quantity)===1;
            if(!$valid || ($dataset==='stock' ? (float)$quantity<0 : (float)$quantity<=0)) {
                $errors['quantity']=$dataset==='stock' ? 'Cantidad inválida' : 'La cantidad debe ser mayor que cero';
            }
        }

        if(isset($data['valorUnitario']) && trim((string)$data['valorUnitario'])!==''){
            $value=(string)$data['valorUnitario'];
            if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$value) || (float)$value<0) {
                $errors['valorUnitario']='Valor unitario inválido';
            }
        }

        return $errors;
    }

    private function dateOk(string $value): bool
    {
        $date=\DateTime::createFromFormat('!Y-m-d',$value);
        return $date && $date->format('Y-m-d')===$value;
    }
}
