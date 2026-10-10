<?php
declare(strict_types=1);

function req(bool $ok,string $message): void
{
    if(!$ok) throw new RuntimeException($message);
}

$root=dirname(__DIR__,2);
$read=static function(string $path)use($root):string{
    $content=file_get_contents($root.'/'.$path);
    if(!is_string($content)) throw new RuntimeException('No se pudo leer '.$path);
    return $content;
};

$docController=$read('app/Controllers/DocumentController.php');
$guideController=$read('app/Controllers/GuideController.php');
$docForm=$read('app/Views/documentos/form.php');
$guideForm=$read('app/Views/guias/form.php');
$docJs=$read('public/assets/js/documentos-captura.js');
$formsJs=$read('public/assets/js/forms.js');
$searchableJs=$read('public/assets/js/searchable-selects.js');
$stockForm=$read('app/Views/stock/form.php');
$masterForm=$read('app/Views/backoffice/master_form.php');
$guideIndex=$read('app/Views/guias/index.php');
$stockIndex=$read('app/Views/stock/index.php');
$routes=$read('routes/web.php');
$masterTable=$read('app/Views/backoffice/table.php');
$backofficeController=$read('app/Controllers/BackofficeController.php');
$bulk=$read('app/Views/components/bulk_workflow.php');
$exportPresentation=$read('app/Services/ExportPresentation.php');
$apiController=$read('app/Controllers/ApiController.php');
$apiEvolution=$read('app/Views/backoffice/evolution.php');
$appConfig=$read('config/app.php');
$lookupController=$read('app/Controllers/LookupController.php');
$masterRepo=$read('app/Repositories/MasterDataRepository.php');
$performanceMigration=$read('database/migrations/006_performance_indexes.sql');
$documentTypeMigration=$read('database/migrations/007_document_types.sql');
$documentScreen=$read('app/Services/DocumentScreenService.php');
$bayerDataService=$read('app/Services/BayerDataService.php');
req(!is_file($root.'/app/Services/OperationalNumberingService.php'),'El generador automático de correlativos debe estar eliminado.');

foreach([$docController,$guideController,$docForm,$guideForm,$docJs,$formsJs] as $content){
    req(!str_contains($content,'data-number-manual'),'No debe quedar el selector de numeración automática/manual.');
    req(!str_contains($content,'number_mode'),'No debe quedar number_mode en captura.');
    req(!str_contains($content,'nextDocumentNumber(') && !str_contains($content,'nextGuideNumber('),'Los formularios no deben generar correlativos.');
}
req(str_contains($docForm,'El sistema no genera correlativos.'),'Documento debe explicar la numeración manual.');
req(str_contains($guideForm,'El sistema no genera correlativos.'),'Guía debe explicar la numeración manual.');

req(str_contains($guideForm,'data-destination-save-client'),'La guía debe permitir guardar la ubicación del cliente.');
req(str_contains($formsJs,'cliente-ubicacion') || str_contains($guideForm,'/guias/cliente-ubicacion'),'Debe existir el endpoint de ubicación del cliente.');
req(str_contains($formsJs,'Ubicación vinculada') || str_contains($formsJs,'ubicación del cliente'),'Debe actualizarse la ubicación del cliente desde el formulario.');

foreach(['documentos','guias','stock'] as $module){
    req(str_contains($routes,"'/'.\$path.'/estado-masivo'") || str_contains($routes,"/$module/estado-masivo"),'Falta ruta masiva para '.$module);
    req(str_contains($bulk,"bulk_workflow_controls"),'Falta componente de workflow masivo.');
}
req(str_contains($bulk,'Validar todo') && str_contains($bulk,'Publicar todo'),'Faltan botones Validar todo/Publicar todo.');

foreach(['/maestros/nuevo','/maestros/guardar','/maestros/editar','/maestros/actualizar','/maestros/eliminar','/maestros/importar','/maestros/plantilla'] as $route){
    req(str_contains($routes,$route),'Falta ruta de catálogo: '.$route);
}
foreach(['Agregar uno','Importar Excel','Plantilla XLSX','data-master-delete'] as $needle){
    req(str_contains($masterTable,$needle),'Falta acción de catálogo: '.$needle);
}
req(str_contains($backofficeController,'$perPage=15;'),'Todos los catálogos maestros deben paginar a 15 filas.');
req(str_contains($backofficeController,"'pagination'=>["),'El controlador debe enviar metadatos de paginación.');
req(!str_contains($backofficeController,"LIMIT 300';"),'Catálogos maestros no deben volver a cargar 300 filas por clic.');
req(str_contains($masterTable,'catalog-pagination'),'La vista debe mostrar navegación paginada.');
req(str_contains($masterTable,"\$isMasterTable=(\$base??'')==='/maestros';"),'La paginación debe quedar aislada a Catálogos maestros.');
req(str_contains($masterTable,"\$searchQuery=\$isMasterTable ? trim((string)(\$q??'')) : '';"),'La vista compartida no debe asumir que q existe en Publicaciones/Auditoría.');
req(str_contains($masterTable,'50 registros por página') || str_contains($masterTable,'$perPage'),'La vista debe explicar la carga paginada.');
req(str_contains($guideForm,'Escribe inicio del departamento...') && str_contains($guideForm,'Escribe inicio de la provincia...') && str_contains($guideForm,'Escribe inicio del distrito...'),'Guías debe usar buscadores autocompletables por prefijo para el ubigeo.');
req(str_contains($guideForm,'Escribe código o inicio del vendedor...') && str_contains($guideForm,'Escribe código o inicio de la sucursal...'),'Guías debe permitir buscar vendedor y sucursal por prefijo.');
req(str_contains($stockForm,'Escribe código o inicio del almacén...') && str_contains($stockForm,'Escribe inicio del lote...'),'Stock debe usar autocompletado por prefijo para almacén y lote.');
req(str_contains($searchableJs,'function refresh(select)'),'Los selects buscables deben refrescar opciones dinámicas.');
req(str_contains($formsJs,'BP_SearchableSelects?.refresh?.(select)'),'Los cascados de ubigeo/lote deben sincronizar el buscador.');
req(str_contains($masterForm,'data-search-select'),'Los formularios maestros deben buscar relaciones sin scroll largo.');
req(str_contains($guideIndex,'Mostrando') && str_contains($stockIndex,'Mostrando'),'Guías y Stock deben mostrar rangos paginados.');
req(str_contains($backofficeController,'$perPage=15;'),'Backoffice debe limitar las tablas a 15 filas.');

req(str_contains($routes,"/lookups"),'Debe existir endpoint interno de búsquedas bajo demanda.');
req(str_contains($lookupController,"'productos'=>") && str_contains($lookupController,"'clientes'=>"),'Lookup remoto debe cubrir productos y clientes.');
req(str_contains($lookupController,"'provincias'=>") && str_contains($lookupController,"'distritos'=>"),'Lookup remoto debe cubrir ubigeo dependiente.');
req(str_contains($searchableJs,'remoteCache') && str_contains($searchableJs,'fetch('),'Autocompletado debe consultar bajo demanda y reutilizar resultados.');
req(str_contains($searchableJs,'160'),'Autocompletado remoto debe usar debounce para evitar consultas por cada tecla.');
req(str_contains($searchableJs,'limit: Math.max(5, Math.min(20'),'Autocompletado remoto debe limitar las coincidencias.');
req(str_contains($searchableJs,'prefix.startsWith(term)'),'Selects locales deben filtrar únicamente por inicio.');
req(str_contains($lookupController,'if($chars<=1) $limit=min($limit,8);'),'Una sola letra debe devolver un máximo pequeño de coincidencias.');
req(!str_contains($lookupController,"'%'.\$q.'%'"),'Lookups remotos no deben buscar substrings intermedios.');
req(!str_contains($backofficeController,"'%'.\$q.'%'"),'Catálogos maestros deben buscar por prefijo.');
req(!str_contains($bayerDataService,"'%'.\$q.'%'"),'Portal Bayer debe buscar por prefijo.');
req(!str_contains($guideForm,'window.BP_PRODUCTS') && !str_contains($guideForm,'window.BP_GEO'),'Guías no debe incrustar miles de productos/ubigeos en el HTML.');
req(!str_contains($stockForm,'window.BP_PRODUCTS') && !str_contains($stockForm,'window.BP_LOTES'),'Stock no debe incrustar catálogos completos en JavaScript.');
req(str_contains($docForm,"/lookups?type=productos") && str_contains($docForm,"/lookups?type=clientes"),'Documentos debe buscar productos y clientes en servidor.');
req(!str_contains($docForm,"/lookups?type=sucursales"),'Sucursal en Documentos debe ser lista directa, no autocompletado remoto.');
req(!str_contains($docForm,"'tipo_documento_id'=>['Buscar tipo de documento"),'Tipo de documento debe ser lista directa, no buscador.');
req(str_contains($documentScreen,"WHERE codigo IN ('FAC','BOL')"),'Documentos debe mostrar únicamente Factura y Boleta.');
req(str_contains($documentScreen,'$m->sucursales()'),'Documentos debe cargar las sucursales activas para el selector directo.');
req(str_contains($documentTypeMigration,"('FAC','Factura','01')") && str_contains($documentTypeMigration,"('BOL','Boleta','03')"),'Migración 007 debe asegurar Factura y Boleta.');
req(str_contains($documentTypeMigration,"VALUES (7,'Factura y Boleta"),'Migración 007 debe quedar registrada.');
req(str_contains($guideForm,"/lookups?type=productos") && str_contains($guideForm,"/lookups?type=clientes"),'Guías debe buscar productos y clientes en servidor.');
req(str_contains($stockForm,"/lookups?type=productos") && str_contains($stockForm,"/lookups?type=almacenes"),'Stock debe buscar productos y almacenes en servidor.');
req(str_contains($masterRepo,'productosByIds') && str_contains($masterRepo,'clientesByIds'),'Formularios deben poder cargar solamente maestros seleccionados.');
req(!str_contains($guideController,'->productos()'),'Guías no debe cargar todos los productos al abrir/guardar.');
req(!str_contains($read('app/Controllers/StockController.php'),'->productos()'),'Stock no debe cargar todos los productos al abrir/guardar.');
req(str_contains($docController,'catalogsFor('),'Documentos debe usar catálogos mínimos por selección.');
req(str_contains($masterForm,"/lookups?type=ubicaciones"),'Ubicación habitual de maestros debe buscarse bajo demanda.');
req(str_contains($performanceMigration,'ix_productos_estado_nombre') && str_contains($performanceMigration,'ix_documentos_cliente_fecha'),'Migración 006 debe crear índices de búsqueda e historial.');
req(str_contains($performanceMigration,"VALUES (6,'Indices de rendimiento"),'Migración 006 debe quedar registrada.');

req(str_contains($exportPresentation,"\\public_path(ltrim(\$relative, '/'))"),'PDF/XLSX deben resolver logos desde la raíz pública real de cPanel.');
req(!str_contains($exportPresentation,"base_path('public/'"),'Exportaciones no deben asumir que el Document Root es /public.');

req(str_contains($routes,"/api/v1/auth/login") && str_contains($routes,"/api/v1/auth/logout"),'Faltan endpoints de login/logout API.');
req(str_contains($routes,"postApi('/api/v1/auth/login"),'El login API debe aceptar POST sin CSRF de sesión web.');
req(str_contains($apiController,'ApiTokenService') && str_contains($apiController,'Primero inicie sesión'),'La API debe exigir token emitido tras login.');
req(!str_contains($appConfig,"'api_token' =>"),'No debe quedar token API fijo compartido en configuración.');
req(!str_contains($apiEvolution,'Token configurado:'),'El panel API no debe seguir mostrando el token fijo antiguo.');
req(!str_contains($apiEvolution,'API_TOKEN'),'El panel API no debe pedir API_TOKEN en .env.');
req(str_contains($apiEvolution,'Usuario + Bearer temporal'),'El panel API debe explicar la autenticación nueva.');
req(str_contains($apiEvolution,'/login'),'El panel API debe mostrar el endpoint de login.');

echo "Nuevos requisitos UI/rutas: OK\n";
