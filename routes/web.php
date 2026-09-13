<?php
use App\Controllers\{AuthController,DashboardController,DocumentController,GuideController,StockController,BayerController,ExportController,ApiController,BrandingController};
use App\Middleware\{AuthMiddleware,RoleMiddleware};
use App\Policies\AccessPolicy;

$router->before(static function (string $path): void {
    (new AuthMiddleware())->refresh();
    if (preg_match('~^/(documentos|guias|stock|dashboard|usuarios|roles|permisos|maestros|configuracion|auditoria)(/|$)~', $path)) {
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
    $router->post('/'.$path.'/estado',[$controller,'changeStatus'],$review);
}

$router->get('/configuracion/identidad',[BrandingController::class,'index'],[new RoleMiddleware(['ADMIN'])]);
$router->post('/configuracion/identidad',[BrandingController::class,'update'],[new RoleMiddleware(['ADMIN'])]);

$router->get('/bayer',[BayerController::class,'index'],$published);
$router->get('/bayer/datos',[BayerController::class,'data'],$published);
$router->get('/export',[ExportController::class,'export'],$published);
$router->get('/api/v1/bayer/sales',[ApiController::class,'sales']);
$router->get('/api/v1/bayer/shipments',[ApiController::class,'shipments']);
$router->get('/api/v1/bayer/inventory',[ApiController::class,'inventory']);
