<?php
use App\Controllers\{AuthController,DashboardController,DocumentController,GuideController,StockController,BayerController,ExportController,ApiController,BrandingController,BackofficeController,UserAdminController};
use App\Middleware\{AuthMiddleware,RoleMiddleware};
use App\Policies\AccessPolicy;

$router->before(static function (string $path): void {
    (new AuthMiddleware())->refresh();
    if (preg_match('~^/(documentos|guias|stock|dashboard|usuarios|roles|permisos|maestros|homologaciones|validacion|publicaciones|reportes|configuracion|auditoria|evolucion)(/|$)~', $path)) {
        require_role(...AccessPolicy::INTERNAL);
    }
    if (preg_match('~^/(usuarios|roles|permisos|configuracion)(/|$)~', $path)) {
        require_role('ADMIN');
    }
});
$internal = [new RoleMiddleware(AccessPolicy::INTERNAL)];
$capture = [new RoleMiddleware(AccessPolicy::CAPTURE)];
$review = [new RoleMiddleware(AccessPolicy::REVIEW)];
$published = [new RoleMiddleware(AccessPolicy::PUBLISHED)];

$router->get('/', fn()=> auth_user()?redirect(AccessPolicy::landing(auth_user())):redirect('/login'));
$router->get('/login',[AuthController::class,'showLogin']);
$router->post('/login',[AuthController::class,'login']);
$router->post('/logout',[AuthController::class,'logout'],[new AuthMiddleware()]);
$router->get('/dashboard',[DashboardController::class,'index'],$internal);

foreach (['documentos'=>DocumentController::class,'guias'=>GuideController::class,'stock'=>StockController::class] as $path=>$controller) {
    $module = ['documentos'=>'documents','guias'=>'guides','stock'=>'stock'][$path];
    $read = [...$internal, new \App\Middleware\PermissionMiddleware($module.'.read')];
    $write = [...$capture, new \App\Middleware\PermissionMiddleware($module.'.create')];
    $router->get('/'.$path,[$controller,'index'],$read);
    $router->get('/'.$path.'/ver',[$controller,'show'],$read);
    $router->get('/'.$path.'/maestros',[$controller,'masters'],$write);
    $router->get('/'.$path.'/nuevo',[$controller,'create'],$write);
    $router->post('/'.$path.'/guardar',[$controller,'store'],$write);
    $router->get('/'.$path.'/editar',[$controller,'edit'],$write);
    $router->post('/'.$path.'/actualizar',[$controller,'update'],$write);
    $router->post('/'.$path.'/eliminar',[$controller,'destroy'],$write);
    $router->post('/'.$path.'/estado',[$controller,'changeStatus'],$review);
}


$router->get('/maestros',[BackofficeController::class,'masters'],$internal);
$router->get('/homologaciones',[BackofficeController::class,'homologations'],[new RoleMiddleware(['ADMIN'])]);
$router->get('/validacion',[BackofficeController::class,'validation'],[new RoleMiddleware(['ADMIN','SUPERVISOR'])]);
$router->get('/publicaciones',[BackofficeController::class,'publications'],[new RoleMiddleware(['ADMIN','SUPERVISOR','GERENCIA'])]);
$router->get('/reportes',[BackofficeController::class,'reports'],[new RoleMiddleware(['ADMIN','SUPERVISOR','GERENCIA'])]);
$router->get('/auditoria',[BackofficeController::class,'audit'],[new RoleMiddleware(['ADMIN','SUPERVISOR','GERENCIA'])]);
$router->get('/seguridad',[BackofficeController::class,'security'],[new RoleMiddleware(['ADMIN'])]);
$router->post('/seguridad/usuarios/guardar',[UserAdminController::class,'store'],[new RoleMiddleware(['ADMIN'])]);
$router->post('/seguridad/usuarios/estado',[UserAdminController::class,'status'],[new RoleMiddleware(['ADMIN'])]);
$router->post('/seguridad/usuarios/rol',[UserAdminController::class,'role'],[new RoleMiddleware(['ADMIN'])]);
$router->get('/evolucion',[BackofficeController::class,'evolution'],[new RoleMiddleware(['ADMIN','SUPERVISOR','GERENCIA'])]);

$router->get('/configuracion/identidad',[BrandingController::class,'index'],[new RoleMiddleware(['ADMIN'])]);
$router->post('/configuracion/identidad',[BrandingController::class,'update'],[new RoleMiddleware(['ADMIN'])]);

$router->get('/bayer',[BayerController::class,'index'],$published);
$router->get('/bayer/datos',[BayerController::class,'data'],$published);
$router->get('/bayer/exportaciones',[BayerController::class,'exports'],$published);
$router->get('/bayer/descargas',[BayerController::class,'downloads'],$published);
$router->get('/export',[ExportController::class,'export'],$published);
$router->get('/export/count',[ExportController::class,'count'],$published);
$router->get('/exportaciones',[ExportController::class,'index'],$published);
$router->get('/api/v1/bayer/sales',[ApiController::class,'sales']);
$router->get('/api/v1/bayer/shipments',[ApiController::class,'shipments']);
$router->get('/api/v1/bayer/inventory',[ApiController::class,'inventory']);
