<?php
declare(strict_types=1);
namespace App\Services;

/** Document screen adapter for Schema v2; does not create master records. */
final class DocumentScreenService
{
    public function catalogs(): array
    {
        $queries = [
            'tipo_documento_id'=>'SELECT id,codigo,CONCAT(codigo," · ",nombre) label FROM tipos_documento ORDER BY codigo',
            'cliente_id'=>'SELECT c.id,CONCAT(c.nro_doc," · ",c.razon_social) label,
                COALESCE(
                    (SELECT h.vendedor_id FROM documentos_cabecera h WHERE h.cliente_id=c.id ORDER BY h.fecha DESC,h.id DESC LIMIT 1),
                    (SELECT g.vendedor_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1)
                ) vendedor_sugerido_id,
                COALESCE(
                    (SELECT h.sucursal_id FROM documentos_cabecera h WHERE h.cliente_id=c.id ORDER BY h.fecha DESC,h.id DESC LIMIT 1),
                    (SELECT g.sucursal_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1)
                ) sucursal_sugerida_id
                FROM clientes c ORDER BY c.razon_social',
            'vendedor_id'=>'SELECT id,CONCAT(codigo," · ",nombres," ",COALESCE(apellidos,"")) label FROM vendedores WHERE estado=1 ORDER BY nombres',
            'sucursal_id'=>'SELECT id,CONCAT(codigo," · ",nombre) label FROM sucursales WHERE estado=1 ORDER BY nombre',
            'producto_id'=>'SELECT id,CONCAT(codigo," · ",nombre) label,unidad_base_id FROM productos WHERE estado=1 ORDER BY nombre',
            'unidad_id'=>'SELECT id,CONCAT(codigo," · ",nombre) label FROM unidades_medida ORDER BY nombre',
        ];
        $result=[];
        foreach($queries as $key=>$sql) $result[$key]=\db()->query($sql)->fetchAll();

        $numbering=new OperationalNumberingService();
        foreach($result['tipo_documento_id'] as &$type){
            $type['next_number']=$numbering->nextDocumentNumber((int)$type['id']);
        }
        unset($type);

        return $result;
    }

    public function listing(array $query): array
    {
        $q=is_string($query['q']??null)?trim($query['q']):'';
        $state=is_string($query['state']??null)?$query['state']:'';
        if(!in_array($state,['BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO'],true)) $state='';
        $where=['1=1']; $params=[];
        if(\has_role('DIGITADOR') && !\has_role('ADMIN','SUPERVISOR','GERENCIA')) { $where[]='h.created_by=?'; $params[]=(int)\auth_user()['id']; }
        if($q!=='') { $where[]='(h.numero LIKE ? OR c.razon_social LIKE ? OR v.nombres LIKE ? OR s.nombre LIKE ?)'; for($i=0;$i<4;$i++) $params[]='%'.$q.'%'; }
        if($state!=='') { $where[]='h.estado_registro=?'; $params[]=$state; }
        $joins=' FROM documentos_cabecera h JOIN clientes c ON c.id=h.cliente_id JOIN vendedores v ON v.id=h.vendedor_id JOIN sucursales s ON s.id=h.sucursal_id WHERE '.implode(' AND ',$where);
        $st=\db()->prepare('SELECT COUNT(*)'.$joins); $st->execute($params); $total=(int)$st->fetchColumn();
        $pages=max(1,(int)ceil($total/10));
        $page=min($pages,max(1,(int)(is_scalar($query['page']??1)?($query['page']??1):1)));
        $st=\db()->prepare('SELECT h.*,c.razon_social cliente,v.nombres vendedor,s.nombre sucursal,(SELECT SUM(d.cantidad) FROM documentos_detalle d WHERE d.documento_id=h.id) cantidad'.$joins.' ORDER BY h.fecha DESC,h.id DESC LIMIT 10 OFFSET '.(($page-1)*10));
        $st->execute($params);
        return ['rows'=>$st->fetchAll(),'q'=>$q,'state'=>$state,'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    public function validate(array $header,array $details,array $catalogs): array
    {
        $errors=[];
        foreach(['tipo_documento_id','cliente_id','vendedor_id','sucursal_id'] as $key) {
            if(!is_scalar($header[$key]??null) || !in_array((string)$header[$key],array_map('strval',array_column($catalogs[$key],'id')),true)) $errors['header.'.$key]='VAL-003: selecciona un registro vigente del catálogo.';
        }
        $number=$header['numero']??'';
        if(!is_string($number)||trim($number)===''||strlen(trim($number))>25) $errors['header.numero']='VAL-001: ingresa un número de hasta 25 bytes.';
        $date=$header['fecha']??''; $parsed=is_string($date)?\DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!$parsed||$parsed->format('Y-m-d')!==$date||$date<'1000-01-01') $errors['header.fecha']='VAL-002: ingresa una fecha válida.';
        if(count($details)<1||count($details)>200) $errors['details']='VAL-007: agrega entre 1 y 200 productos.';
        foreach($details as $i=>$line) {
            if(!is_array($line)) { $errors['details']='VAL-002: formato de productos inválido.'; continue; }
            foreach(['producto_id','unidad_id'] as $key) {
                if(!is_scalar($line[$key]??null)||!in_array((string)$line[$key],array_map('strval',array_column($catalogs[$key],'id')),true)) $errors["details.$i.$key"]='VAL-003: selecciona una opción vigente.';
            }
            foreach(['cantidad'=>3,'valor_unitario'=>2] as $key=>$precision) {
                $value=$line[$key]??($key==='valor_unitario'?'0':'');
                if(!is_scalar($value)||!preg_match('/^\d{1,'.(14-$precision).'}(\.\d{1,'.$precision.'})?$/D',(string)$value)||($key==='cantidad'&&(float)$value<=0)) $errors["details.$i.$key"]='VAL-002: usa un decimal válido'.($key==='cantidad'?' mayor que cero.':'.');
            }
        }
        return $errors;
    }
}
