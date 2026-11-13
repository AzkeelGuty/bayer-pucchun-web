<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
$checks=0;
function check_ui(bool $valid,string $name): void {
    global $checks;
    if(!$valid) throw new RuntimeException('Acciones del ERP: '.$name);
    ++$checks;
}
foreach(['documentos','guias','stock'] as $module){
    $source=(string)file_get_contents($root.'/app/Views/'.$module.'/index.php');
    check_ui(str_contains($source,'btn btn-sm btn-app-blue btn-with-icon') && str_contains($source,'>Ver</span>'),
        $module.' muestra Ver con azul sólido e icono');
    check_ui(str_contains($source,'btn btn-sm btn-app-amber btn-with-icon') && str_contains($source,'bi-pencil-square'),
        $module.' muestra Editar o Corregir con ámbar sólido');
}
$table=(string)file_get_contents($root.'/app/Views/backoffice/table.php');
check_ui(substr_count($table,'btn btn-sm btn-app-blue btn-with-icon')===2,'Editar azul en Catálogos y Homologaciones');
check_ui(substr_count($table,'btn btn-sm btn-app-red btn-with-icon')===2,'Eliminar rojo en Catálogos y Homologaciones');
check_ui(!str_contains($table,'>Desactivar</span>'),'Homologaciones no muestra Desactivar');
check_ui(str_contains($table,'data-master-delete-mode="deactivate"'),'Homologación conserva baja lógica');
$confirmation=(string)file_get_contents($root.'/public/assets/js/app.js');
check_ui(str_contains($confirmation,'El registro quedará inactivo y se conservará su historial.'),
    'Confirmación de homologación diferencia baja lógica de borrado físico');
$css=(string)file_get_contents($root.'/public/assets/css/app.css');
check_ui(str_contains($css,'color:#fff!important;opacity:1;filter:none;'),'Botones activos con texto blanco');
check_ui(str_contains($css,'.btn.btn-app-blue:hover:not(:disabled)'),'Azul activo cambia al pasar mouse');
check_ui(str_contains($css,'.btn.btn-app-amber:hover:not(:disabled)'),'Editar ámbar cambia al pasar mouse');
check_ui(str_contains($css,'.btn.btn-app-red:hover:not(:disabled)'),'Eliminar rojo cambia al pasar mouse');

$helpers=(string)file_get_contents($root.'/app/Helpers/functions.php');
check_ui(str_contains($helpers,'new App\\Services\\AuditService(db())') && str_contains($helpers,'AuthService::ip()'),
    'Todas las auditorías nuevas registran IP del servidor');
check_ui(str_contains($table,'No registrada'),'La vista no inventa direcciones de acciones históricas');
echo 'ERP ACTION COLORS/AUDIT OK ('.$checks.' checks)'.PHP_EOL;
