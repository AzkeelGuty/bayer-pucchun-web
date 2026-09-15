<?php
use App\Controllers\{AuthController,DashboardController,DocumentController,GuideController,StockController,BayerController,ExportController,ApiController};
use App\Middleware\{AuthMiddleware,RoleMiddleware};
use App\Policies\AccessPolicy;
use App\Exceptions\HttpException;

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

// The v1 capture forms require the Day 2 Service/normalized-master contract.
// Until connected, fail explicitly instead of calling removed Repository methods.
$pendingCapture = static function (): void {
    throw new HttpException(503, 'Captura en preparación: pendiente de conectar los formularios con Schema v2.');
};
$pendingWorkflow = static function (): void {
    throw new HttpException(503, 'Acción pendiente de integrar con validaciones y workflow.');
};
foreach (['documentos'=>DocumentController::class,'guias'=>GuideController::class,'stock'=>StockController::class] as $path=>$controller) {
    $router->get('/'.$path,[$controller,'index'],$internal);
    $router->get('/'.$path.'/nuevo',$pendingCapture,$capture);
    $router->post('/'.$path.'/guardar',$pendingCapture,$capture);
    $router->post('/'.$path.'/estado',$pendingWorkflow,$review);
}
$router->get('/bayer',[BayerController::class,'index'],$published);
$router->get('/bayer/datos',[BayerController::class,'data'],$published);
# Nueva ruta para exportaciones
$router->get('/exportaciones',[ExportController::class,'index'],$published);

$router->get('/guias/ver',[GuideController::class,'show'],$internal);
$router->get('/stock/ver',[StockController::class,'show'],$internal);
$router->get('/guias/nuevo',[GuideController::class,'create'],$capture);
$router->get('/stock/nuevo',[StockController::class,'create'],$capture);
$router->get('/guias/editar',[GuideController::class,'edit'],$capture);
$router->post('/guias/actualizar',$pendingCapture,$capture);
$router->get('/stock/editar',[StockController::class,'edit'],$capture);
$router->post('/stock/actualizar',$pendingCapture,$capture);

$router->get('/export',[ExportController::class,'export'],$published);
$router->get('/api/v1/bayer/sales',[ApiController::class,'sales']);
$router->get('/api/v1/bayer/shipments',[ApiController::class,'shipments']);
$router->get('/api/v1/bayer/inventory',[ApiController::class,'inventory']);
