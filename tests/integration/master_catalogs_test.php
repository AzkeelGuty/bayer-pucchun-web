<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Exceptions\HttpException;
use App\Repositories\{DocumentRepository,MasterDataRepository};
use App\Services\{MasterCatalogService,SimpleXlsxTable};

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/004_catalogos_masivos_busqueda.sql');
    $pdo=$db->pdo;
    fixtures($pdo);

    $masters=new MasterDataRepository($pdo);
    $masters->updateClientLocation(1,1,1,1);
    $client=array_values(array_filter($masters->clientes(),static fn(array $r):bool=>(int)$r['id']===1))[0];
    ensure((int)$client['departamento_id']===1 && (int)$client['provincia_id']===1 && (int)$client['distrito_id']===1,'Client location persisted');
    rejects(fn()=>$masters->updateClientLocation(1,1,2,2),InvalidArgumentException::class);

    $service=new MasterCatalogService($pdo);
    $providerId=$service->save('proveedores',['codigo'=>'PX-01','nombre'=>'Proveedor X','estado'=>'1']);
    ensure($providerId>0,'Provider created');
    $service->save('proveedores',['codigo'=>'PX-01','nombre'=>'Proveedor X Editado','estado'=>'0'],$providerId);
    ensure($service->find('proveedores',$providerId)['nombre']==='Proveedor X Editado','Provider edited');
    $service->delete('proveedores',$providerId);
    rejects(fn()=>$service->find('proveedores',$providerId),HttpException::class);

    $productId=$service->save('productos',[
        'codigo'=>'PX-PROD','nombre'=>'Producto CRUD','tipo_art'=>'PRODUCTO',
        'unidad_base_id'=>'1','categoria_id'=>'','marca_id'=>'','estado'=>'1',
    ]);
    ensure($service->find('productos',$productId)['nombre']==='Producto CRUD','Product created');
    $service->save('productos',[
        'codigo'=>'PX-PROD','nombre'=>'Producto CRUD 2','tipo_art'=>'PRODUCTO',
        'unidad_base_id'=>'1','categoria_id'=>'','marca_id'=>'','estado'=>'0',
    ],$productId);
    ensure((int)$service->find('productos',$productId)['estado']===0,'Product edited');
    $service->delete('productos',$productId);
    rejects(fn()=>$service->find('productos',$productId),HttpException::class);

    $result=$service->importRows('vendedores',[
        ['codigo'=>'V100','nombres'=>'Ana','apellidos'=>'Pérez','email'=>'ana@example.test','estado'=>'1'],
        ['codigo'=>'V101','nombres'=>'Luis','apellidos'=>'Ramos','email'=>'','estado'=>'1'],
    ]);
    ensure($result['inserted']===2 && $result['updated']===0,'Bulk sellers inserted');
    $result=$service->importRows('vendedores',[
        ['codigo'=>'V100','nombres'=>'Ana María','apellidos'=>'Pérez','email'=>'ana@example.test','estado'=>'1'],
    ]);
    ensure($result['updated']===1,'Bulk import updates existing unique record');
    ensure((string)$pdo->query("SELECT nombres FROM vendedores WHERE codigo='V100'")->fetchColumn()==='Ana María','Bulk update persisted');

    $before=(int)$pdo->query("SELECT COUNT(*) FROM proveedores")->fetchColumn();
    rejects(fn()=>$service->importRows('proveedores',[
        ['codigo'=>'TX-1','nombre'=>'Temporal','estado'=>'1'],
        ['codigo'=>'','nombre'=>'Inválido','estado'=>'1'],
    ]),HttpException::class);
    ensure((int)$pdo->query("SELECT COUNT(*) FROM proveedores")->fetchColumn()===$before,'Failed bulk import rolls back all rows');

    $documents=new DocumentRepository($pdo);
    $docId=$documents->create(docHeader('MANUAL-900'),lines(),1);
    rejects(fn()=>$service->delete('productos',1),HttpException::class);
    ensure($documents->find($docId)!==null,'Protected master delete preserves referenced record');

    // Minimal XLSX with shared strings: validates the custom reader used by upload.
    $tmp=tempnam(sys_get_temp_dir(),'catalog-test-');
    $zip=new ZipArchive();
    $zip->open($tmp,ZipArchive::OVERWRITE);
    $zip->addFromString('xl/sharedStrings.xml','<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>codigo</t></si><si><t>nombre</t></si><si><t>estado</t></si><si><t>P-9</t></si><si><t>Proveedor 9</t></si><si><t>1</t></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row><row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2" t="s"><v>4</v></c><c r="C2" t="s"><v>5</v></c></row></sheetData></worksheet>');
    $zip->close();
    $rows=(new SimpleXlsxTable())->readFile($tmp);
    @unlink($tmp);
    ensure(count($rows)===1 && $rows[0]['codigo']==='P-9' && $rows[0]['nombre']==='Proveedor 9','XLSX reader maps header and row');

    echo 'Master catalogs: '.$GLOBALS['checks']." checks OK\n";
}finally{
    $db->close();
}
