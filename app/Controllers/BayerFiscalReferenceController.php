<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Exceptions\HttpException;
use App\Services\BayerFiscalReferenceService;

final class BayerFiscalReferenceController
{
    public function save(): void
    {
        \require_role('ADMIN');
        $scope=(string)\input('modulo','');$id=(int)\input('id',0);
        if(!in_array($scope,['documentos','guias'],true)||$id<1)throw new HttpException(422,'Registro inválido.');
        $district=(int)\input('distrito_despacho_id',0);
        try{
            (new BayerFiscalReferenceService(\db()))->save($scope,$id,(string)\input('numero_fiscal',''),$district>0?$district:null,\input('confirmado')==='1',(int)\auth_user()['id'],true);
            \audit($scope,'referencia_fiscal_bayer',$id);
            \flash('success','Información fiscal y despacho actualizados.');
        }catch(HttpException $e){\flash('error',$e->getMessage());}
        \redirect('/'.$scope.'/ver?id='.$id);
    }
}
