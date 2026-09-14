<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Policies\AccessPolicy;
use App\Repositories\{DocumentRepository,GuideRepository,StockRepository};

final class BackofficeController
{
    public function masters(): void
    {
        \require_role('ADMIN');
        $key=(string)($_GET['tab'] ?? 'clientes');
        $catalogs=[
            'clientes'=>['Clientes',"SELECT c.id,c.codigo,c.tipo_doc,c.nro_doc,c.razon_social,d.nombre distrito,p.nombre provincia,dp.nombre departamento FROM clientes c LEFT JOIN distritos d ON d.id=c.distrito_id LEFT JOIN provincias p ON p.id=c.provincia_id LEFT JOIN departamentos dp ON dp.id=c.departamento_id ORDER BY c.razon_social LIMIT 300"],
            'productos'=>['Productos',"SELECT pr.id,pr.codigo,pr.nombre,c.nombre categoria,m.nombre marca,u.codigo unidad,IF(pr.estado=1,'ACTIVO','INACTIVO') estado FROM productos pr LEFT JOIN categorias_producto c ON c.id=pr.categoria_id LEFT JOIN marcas m ON m.id=pr.marca_id LEFT JOIN unidades_medida u ON u.id=pr.unidad_base_id ORDER BY pr.nombre LIMIT 300"],
            'vendedores'=>['Vendedores',"SELECT id,codigo,TRIM(CONCAT(nombres,' ',COALESCE(apellidos,''))) vendedor,email,IF(estado=1,'ACTIVO','INACTIVO') estado FROM vendedores ORDER BY nombres LIMIT 300"],
            'sucursales'=>['Sucursales',"SELECT s.id,s.codigo,s.nombre,e.razon_social empresa,s.direccion,d.nombre distrito,IF(s.estado=1,'ACTIVO','INACTIVO') estado FROM sucursales s JOIN empresas e ON e.id=s.empresa_id LEFT JOIN distritos d ON d.id=s.distrito_id ORDER BY s.nombre LIMIT 300"],
            'almacenes'=>['Almacenes',"SELECT a.id,a.codigo,a.nombre,a.tipo,s.nombre sucursal,IF(a.estado=1,'ACTIVO','INACTIVO') estado FROM almacenes a JOIN sucursales s ON s.id=a.sucursal_id ORDER BY a.nombre LIMIT 300"],
            'unidades'=>['Unidades de medida',"SELECT id,codigo,nombre,abreviatura,factor_base FROM unidades_medida ORDER BY nombre LIMIT 300"],
            'empresas'=>['Empresas',"SELECT id,ruc,razon_social,nombre_comercial,IF(estado=1,'ACTIVO','INACTIVO') estado FROM empresas ORDER BY razon_social LIMIT 300"],
            'tipos'=>['Tipos de documento',"SELECT id,codigo,nombre,sunat_code FROM tipos_documento ORDER BY nombre LIMIT 300"],
            'ubigeo'=>['Ubigeo',"SELECT d.id,dp.nombre departamento,p.nombre provincia,d.nombre distrito FROM distritos d JOIN provincias p ON p.id=d.provincia_id JOIN departamentos dp ON dp.id=p.departamento_id ORDER BY dp.nombre,p.nombre,d.nombre LIMIT 300"],
        ];
        if(!isset($catalogs[$key])) throw new HttpException(404,'Catálogo no encontrado.');
        [$title,$sql]=$catalogs[$key];
        $rows=\db()->query($sql)->fetchAll();
        \view('backoffice.table',['section'=>'Catálogos maestros','title'=>$title,'rows'=>$rows,'tabs'=>array_map(fn($v)=>$v[0],$catalogs),'active'=>$key,'base'=>'/maestros']);
    }

    public function homologations(): void
    {
        \require_role('ADMIN');
        $key=(string)($_GET['tab'] ?? 'productos');
        $maps=[
            'productos'=>['Productos Bayer',"SELECT h.id,p.nombre partner,pr.codigo codigo_interno,pr.nombre producto,h.material_id codigo_bayer,h.material_name nombre_bayer,IF(h.estado=1,'ACTIVO','INACTIVO') estado,h.valid_from,h.valid_until FROM homologacion_productos_bayer h JOIN partners p ON p.id=h.partner_id JOIN productos pr ON pr.id=h.producto_id ORDER BY h.id DESC LIMIT 300"],
            'clientes'=>['Clientes Bayer',"SELECT h.id,p.nombre partner,c.nro_doc documento,c.razon_social cliente,h.customer_id codigo_bayer,h.customer_name nombre_bayer,IF(h.estado=1,'ACTIVO','INACTIVO') estado,h.valid_from,h.valid_until FROM homologacion_clientes h JOIN partners p ON p.id=h.partner_id JOIN clientes c ON c.id=h.cliente_id ORDER BY h.id DESC LIMIT 300"],
            'unidades'=>['Unidades Bayer',"SELECT h.id,p.nombre partner,u.codigo codigo_interno,u.nombre unidad,h.external_unit_code codigo_bayer,h.external_unit_name nombre_bayer,IF(h.estado=1,'ACTIVO','INACTIVO') estado,h.valid_from,h.valid_until FROM homologacion_unidades h JOIN partners p ON p.id=h.partner_id JOIN unidades_medida u ON u.id=h.unidad_id ORDER BY h.id DESC LIMIT 300"],
            'sucursales'=>['Sucursales Bayer',"SELECT h.id,p.nombre partner,s.codigo codigo_interno,s.nombre sucursal,h.branch_id codigo_bayer,h.branch_name nombre_bayer,IF(h.estado=1,'ACTIVO','INACTIVO') estado,h.valid_from,h.valid_until FROM homologacion_sucursales h JOIN partners p ON p.id=h.partner_id JOIN sucursales s ON s.id=h.sucursal_id ORDER BY h.id DESC LIMIT 300"],
            'almacenes'=>['Almacenes Bayer',"SELECT h.id,p.nombre partner,a.codigo codigo_interno,a.nombre almacen,h.warehouse_id codigo_bayer,h.warehouse_name nombre_bayer,IF(h.estado=1,'ACTIVO','INACTIVO') estado,h.valid_from,h.valid_until FROM homologacion_almacenes h JOIN partners p ON p.id=h.partner_id JOIN almacenes a ON a.id=h.almacen_id ORDER BY h.id DESC LIMIT 300"],
            'vendedores'=>['Vendedores Bayer',"SELECT h.id,p.nombre partner,v.codigo codigo_interno,TRIM(CONCAT(v.nombres,' ',COALESCE(v.apellidos,''))) vendedor,h.sales_id codigo_bayer,h.sales_name nombre_bayer,IF(h.estado=1,'ACTIVO','INACTIVO') estado,h.valid_from,h.valid_until FROM homologacion_vendedores h JOIN partners p ON p.id=h.partner_id JOIN vendedores v ON v.id=h.vendedor_id ORDER BY h.id DESC LIMIT 300"],
        ];
        if(!isset($maps[$key])) throw new HttpException(404,'Homologación no encontrada.');
        [$title,$sql]=$maps[$key];
        $rows=\db()->query($sql)->fetchAll();
        \view('backoffice.table',['section'=>'Homologaciones Bayer','title'=>$title,'rows'=>$rows,'tabs'=>array_map(fn($v)=>$v[0],$maps),'active'=>$key,'base'=>'/homologaciones']);
    }

    public function validation(): void
    {
        \require_role('ADMIN','SUPERVISOR');
        $docs=(new DocumentRepository())->all([],100,0);
        $guides=(new GuideRepository())->all([],100,0);
        $stock=(new StockRepository())->all([],100,0);
        $rows=[];
        foreach($docs as $r) if(in_array($r['estado_registro'],['BORRADOR','VALIDADO','OBSERVADO'],true)) $rows[]=['module'=>'documentos','dataset'=>'Documentos',...$r];
        foreach($guides as $r) if(in_array($r['estado_registro'],['BORRADOR','VALIDADO','OBSERVADO'],true)) $rows[]=['module'=>'guias','dataset'=>'Guías',...$r];
        foreach($stock as $r) if(in_array($r['estado_registro'],['BORRADOR','VALIDADO','OBSERVADO'],true)) $rows[]=['module'=>'stock','dataset'=>'Stock',...$r];
        \view('backoffice.validation',['rows'=>$rows]);
    }

    public function publications(): void
    {
        \require_role('ADMIN','SUPERVISOR','GERENCIA');
        $rows=\db()->query("SELECT p.id,p.modulo,p.fecha_publicacion,p.estado,u.nombre usuario,COUNT(dp.id) registros FROM publicaciones p JOIN usuarios u ON u.id=p.usuario_id LEFT JOIN detalle_publicacion dp ON dp.publicacion_id=p.id GROUP BY p.id ORDER BY p.fecha_publicacion DESC LIMIT 300")->fetchAll();
        \view('backoffice.table',['section'=>'Control','title'=>'Registro de publicaciones','rows'=>$rows,'tabs'=>[],'active'=>'','base'=>'/publicaciones']);
    }

    public function reports(): void
    {
        \require_role('ADMIN','SUPERVISOR','GERENCIA');
        $rows=\db()->query("SELECT e.id,e.tipo_dataset,e.formato,e.record_count,e.resultado,e.generated_at,u.nombre usuario FROM exportaciones e JOIN usuarios u ON u.id=e.usuario_id ORDER BY e.generated_at DESC LIMIT 200")->fetchAll();
        \view('backoffice.reports',['rows'=>$rows]);
    }

    public function audit(): void
    {
        \require_role('ADMIN','SUPERVISOR','GERENCIA');
        $rows=\db()->query("SELECT a.id,a.fecha_hora,u.nombre usuario,a.modulo,a.accion,a.entidad_id,a.resultado,a.ip FROM auditoria_acciones a JOIN usuarios u ON u.id=a.usuario_id ORDER BY a.fecha_hora DESC LIMIT 300")->fetchAll();
        \view('backoffice.table',['section'=>'Trazabilidad','title'=>'Auditoría de acciones','rows'=>$rows,'tabs'=>[],'active'=>'','base'=>'/auditoria']);
    }

    public function security(): void
    {
        \require_role('ADMIN');
        $users=\db()->query("SELECT u.id,u.nombre,u.email,IF(u.estado=1,'ACTIVO','INACTIVO') estado,GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', ') roles,u.created_at FROM usuarios u LEFT JOIN usuario_rol ur ON ur.usuario_id=u.id LEFT JOIN roles r ON r.id=ur.rol_id GROUP BY u.id ORDER BY u.nombre")->fetchAll();
        $roles=\db()->query("SELECT r.id,r.nombre,r.descripcion,IF(r.estado=1,'ACTIVO','INACTIVO') estado,COUNT(DISTINCT ur.usuario_id) usuarios,COUNT(DISTINCT rp.permiso_id) permisos FROM roles r LEFT JOIN usuario_rol ur ON ur.rol_id=r.id LEFT JOIN rol_permiso rp ON rp.rol_id=r.id GROUP BY r.id ORDER BY r.id")->fetchAll();
        $sessions=\db()->query("SELECT b.fecha_hora,u.nombre usuario,b.ip,b.user_agent,b.accion FROM bitacora_acceso b LEFT JOIN usuarios u ON u.id=b.usuario_id ORDER BY b.fecha_hora DESC LIMIT 150")->fetchAll();
        \view('backoffice.security',['users'=>$users,'roles'=>$roles,'sessions'=>$sessions]);
    }

    public function evolution(): void
    {
        \require_role('ADMIN','SUPERVISOR','GERENCIA');
        \view('backoffice.evolution',['apiEnabled'=>(bool)\config('app.api_enabled')]);
    }
}
