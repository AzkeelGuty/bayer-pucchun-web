<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Helpers/functions.php';

function perf_assert(bool $yes,string $reason): void {
    if(!$yes) throw new RuntimeException($reason);
}
$root=dirname(__DIR__,2);
$helpers=(string)file_get_contents($root.'/app/Helpers/functions.php');
$index=(string)file_get_contents($root.'/public/index.php');
$js=(string)file_get_contents($root.'/public/assets/js/branding.js');
$navigation=(string)file_get_contents($root.'/public/assets/js/navigation.js');
$apache=(string)file_get_contents($root.'/public/.htaccess');
perf_assert(!str_contains($helpers,'(string)time();'),'No debe invalidar recursos estáticos cada segundo');
perf_assert(str_contains($helpers,"config('app.asset_version','1')"),'Falta fallback estable para versión de activos');
perf_assert(str_contains($index,'Server-Timing:'),'Falta medición optativa de latencia de PHP');
perf_assert(str_contains($js,'window.BP_Branding={optimizeUploads}'),'Falta preparación de logos grandes');
perf_assert(str_contains($navigation,'await window.BP_Branding.optimizeUploads(data)'),
    'La navegación debe optimizar logos antes de enviar FormData');
perf_assert(str_contains($apache,'ExpiresByType image/png'),'Faltan cabeceras de caché condicionales');
$original=$_ENV['BAYER_PUBLIC_ROOT']??null;
$originalProcess=getenv('BAYER_PUBLIC_ROOT');
try {
    $_ENV['BAYER_PUBLIC_ROOT']=$root.'/storage/temp/public-root-no-existe';
    putenv('BAYER_PUBLIC_ROOT='.$_ENV['BAYER_PUBLIC_ROOT']);
    $url=asset_url('assets/js/branding.js');
    perf_assert($url===asset_url('assets/js/branding.js'),'URL de activo inestable');
    perf_assert(str_contains($url,'?v='),'El activo debe incluir versión');
} finally {
    if($original===null) unset($_ENV['BAYER_PUBLIC_ROOT']);
    else $_ENV['BAYER_PUBLIC_ROOT']=$original;
    if($originalProcess===false) putenv('BAYER_PUBLIC_ROOT');
    else putenv('BAYER_PUBLIC_ROOT='.$originalProcess);
}
$reports=(string)file_get_contents($root.'/app/Views/backoffice/reports.php');
$live=(string)file_get_contents($root.'/public/assets/js/soft-page-init.js');
$app=(string)file_get_contents($root.'/public/assets/js/app.js');
$tables=(string)file_get_contents($root.'/app/Views/backoffice/table.php');
perf_assert(str_contains($reports,'data-live-refresh="0" data-live-refresh-key="export-history"'),
    'El historial no debe disparar solicitudes periódicas cada 3 segundos');
perf_assert(str_contains($reports,'data-live-refresh-now="export-history"'),
    'El historial debe permitir actualizar voluntariamente');
perf_assert(str_contains($live,'initLiveRefresh(document.querySelector('),
    'La actualización única debe inicializarse en la carga de página');
perf_assert(!str_contains($app,'const liveElements =') && !str_contains($app,'const runMasterSearch ='),
    'App.js no debe duplicar temporizadores ni consultas incrementales');
perf_assert(str_contains($tables,'data-live-refresh="60000"'),
    'Las tablas secundarias deben refrescar a un intervalo razonable');
$dashboard=(string)file_get_contents($root.'/app/Services/DashboardService.php');
$auth=(string)file_get_contents($root.'/app/Services/AuthService.php');
$stock=(string)file_get_contents($root.'/app/Services/UnifiedStockService.php');
$header=(string)file_get_contents($root.'/app/Views/layouts/header.php');
$reportsController=(string)file_get_contents($root.'/app/Controllers/BackofficeController.php');
perf_assert(!str_contains($header,'<script src="https://cdn.jsdelivr.net/npm/chart.js'),
    'Chart.js no debe bloquear todos los módulos');
perf_assert(str_contains($live,'const ensureCharts = () =>') &&
    str_contains($live,'ensureCharts().then('),
    'Los gráficos deben cargar únicamente en paneles');
perf_assert(str_contains($navigation,'window.Chart.getChart(canvas)') &&
    str_contains($navigation,'chart.destroy()'),
    'Los gráficos deben liberar su memoria al cambiar de pantalla');
perf_assert(str_contains($auth,'JOIN usuario_rol ur ON ur.usuario_id=u.id') &&
    str_contains($auth,'r.nombre AS rol_activo'),
    'El usuario y sus roles se obtienen en una consulta');
perf_assert(str_contains($dashboard,'GROUP BY dd.producto_id') &&
    str_contains($dashboard,'GROUP BY gd.producto_id') &&
    str_contains($dashboard,'GROUP BY sd.producto_id'),
    'El dashboard debe preagrupar resultados antes del UNION');
perf_assert(str_contains($stock,'private function calculateSummary(') &&
    str_contains($stock,'public function overview(') &&
    !str_contains($stock, '$count=$this->pdo->prepare('),
    'Stock no debe ejecutar un segundo consolidado para el conteo');
perf_assert(str_contains($reportsController,'$stockReport->overview()'),
    'Reportes solo debe obtener resumen, no paginación completa de stock');
echo "RENDIMIENTO CPANEL OK\n";
