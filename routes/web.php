<?php
use App\Controllers\{AuthController,DashboardController,DocumentController,GuideController,StockController,BayerController,ExportController,ApiController,BrandingController,BackofficeController,UserAdminController,MasterDataController};
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
    $router->get('/'.$path,[$controller,'index'],$internal);
    $router->get('/'.$path.'/nuevo',[$controller,'create'],$capture);
    $router->post('/'.$path.'/guardar',[$controller,'store'],$capture);
    $router->get('/'.$path.'/editar',[$controller,'edit'],$capture);
    $router->post('/'.$path.'/actualizar',[$controller,'update'],$capture);
    $router->post('/'.$path.'/eliminar',[$controller,'destroy'],$capture);
    $router->post('/'.$path.'/estado',[$controller,'changeStatus'],$review);
    $router->post('/'.$path.'/estado-masivo',[$controller,'bulkStatus'],$review);
}

$router->get('/documentos/ver',[DocumentController::class,'show'],$internal);
$router->get('/guias/ver',[GuideController::class,'show'],$internal);
$router->post('/guias/cliente-ubicacion',[GuideController::class,'saveClientLocation'],$capture);
$router->get('/stock/ver',[StockController::class,'show'],$internal);

$router->get('/maestros',[BackofficeController::class,'masters'],$internal);
$router->get('/maestros/nuevo',[MasterDataController::class,'create'],[new RoleMiddleware(['ADMIN'])]);
$router->post('/maestros/guardar',[MasterDataController::class,'store'],[new RoleMiddleware(['ADMIN'])]);
$router->get('/maestros/editar',[MasterDataController::class,'edit'],[new RoleMiddleware(['ADMIN'])]);
$router->post('/maestros/actualizar',[MasterDataController::class,'update'],[new RoleMiddleware(['ADMIN'])]);
$router->post('/maestros/eliminar',[MasterDataController::class,'destroy'],[new RoleMiddleware(['ADMIN'])]);
$router->get('/maestros/importar',[MasterDataController::class,'importPage'],[new RoleMiddleware(['ADMIN'])]);
$router->post('/maestros/importar',[MasterDataController::class,'import'],[new RoleMiddleware(['ADMIN'])]);
$router->get('/maestros/plantilla',[MasterDataController::class,'template'],[new RoleMiddleware(['ADMIN'])]);
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
$router->postApi('/api/v1/auth/login',[ApiController::class,'login']);
$router->postApi('/api/v1/auth/logout',[ApiController::class,'logout']);
$router->get('/api/v1/bayer',[ApiController::class,'info']);
$router->get('/api/v1/bayer/all',[ApiController::class,'all']);
$router->get('/api/v1/bayer/sales',[ApiController::class,'sales']);
$router->get('/api/v1/bayer/shipments',[ApiController::class,'shipments']);
$router->get('/api/v1/bayer/inventory',[ApiController::class,'inventory']);
