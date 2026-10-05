<?php
declare(strict_types=1);
require dirname(__DIR__) . '/integration/bootstrap.php';

$reflection = new ReflectionClass(App\Repositories\LogisticsRepository::class);
$repository = $reflection->newInstanceWithoutConstructor();
$typedRow = $reflection->getMethod('typedRow');
$row = $typedRow->invoke($repository, ['id'=>'1','incidencia_id'=>'2','confirmacion_id'=>'return-line-AbC-01']);
ensure($row['id']===1 && $row['incidencia_id']===2, 'Numeric relational IDs remain typed');
ensure($row['confirmacion_id']==='return-line-AbC-01', 'External granular identity remains a string');
$resolutionInput = $reflection->getMethod('resolutionInput');
$payload = ['tipo'=>'RETORNO_CONFIRMADO','cantidad'=>'12','motivo'=>'Fixture','evidencia'=>['archivo'=>'fixture'],'confirmacion'=>['sistema'=>'INVENTARIOS','id'=>'return-line-AbC-01']];
$first=$resolutionInput->invoke($repository,$payload);
usleep(1000);
$second=$resolutionInput->invoke($repository,$payload);
ensure($first===$second, 'Omitting operational time keeps normalized idempotency payload stable');
echo '007 normalizers: '.$GLOBALS['checks']." checks passed\n";
