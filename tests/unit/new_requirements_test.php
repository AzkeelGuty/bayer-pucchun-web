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
$routes=$read('routes/web.php');
$masterTable=$read('app/Views/backoffice/table.php');
$bulk=$read('app/Views/components/bulk_workflow.php');
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

echo "Nuevos requisitos UI/rutas: OK\n";
