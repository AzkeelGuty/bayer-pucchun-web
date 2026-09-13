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

// Workflow remains disabled until the Day 3 implementation.
$pendingWorkflow = static function (): void {
    throw new HttpException(503, 'Acción pendiente de integrar con validaciones y workflow.');
};
foreach (['documentos'=>DocumentController::class,'guias'=>GuideController::class,'stock'=>StockController::class] as $path=>$controller) {
    $module = ['documentos'=>'documents','guias'=>'guides','stock'=>'stock'][$path];
    $read = [...$internal, new \App\Middleware\PermissionMiddleware($module.'.read')];
    $write = [...$capture, new \App\Middleware\PermissionMiddleware($module.'.create')];
    $router->get('/'.$path,[$controller,'index'],$read);
    $router->get('/'.$path.'/ver',[$controller,'show'],$read);
    $router->get('/'.$path.'/maestros',[$controller,'masters'],$write);
    $router->get('/'.$path.'/nuevo',[$controller,'create'],$write);
    $router->post('/'.$path.'/guardar',[$controller,'store'],$write);
    $router->post('/'.$path.'/estado',$pendingWorkflow,$review);
}
$router->get('/bayer',[BayerController::class,'index'],$published);
$router->get('/bayer/datos',[BayerController::class,'data'],$published);
$router->get('/export',[ExportController::class,'export'],$published);
$router->get('/api/v1/bayer/sales',[ApiController::class,'sales']);
$router->get('/api/v1/bayer/shipments',[ApiController::class,'shipments']);
$router->get('/api/v1/bayer/inventory',[ApiController::class,'inventory']);
