<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        require dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

use App\Exceptions\LogisticsPersistenceException;
use App\Support\LogisticsQuantity;

$checks = 0;
function quantityCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    ++$GLOBALS['checks'];
}
function quantityRejects(callable $operation): void
{
    try { $operation(); }
    catch (LogisticsPersistenceException $error) {
        quantityCheck($error->reasonCode() === 'INVALID_INPUT', 'Invalid quantity has stable INVALID_INPUT code');
        return;
    }
    throw new RuntimeException('Invalid quantity was accepted');
}

foreach ([['1', '1.000', 1000], [1, '1.000', 1000], ['0002.5', '2.500', 2500],
    ['0.001', '0.001', 1], ['99999999999.999', '99999999999.999', 99999999999999]] as [$input, $expected, $millis]) {
    quantityCheck(LogisticsQuantity::normalize($input) === $expected, 'Canonical decimal preserves exact quantity');
    quantityCheck(LogisticsQuantity::toMillis($input) === $millis, 'Exact integer conversion');
    quantityCheck(LogisticsQuantity::fromMillis($millis) === $expected, 'Exact integer round trip');
}
quantityCheck(LogisticsQuantity::normalize('0', true) === '0.000', 'Zero allowed for a computed balance');
quantityCheck(LogisticsQuantity::toMillis(0, true) === 0, 'Zero balance converts exactly');
quantityCheck(LogisticsQuantity::fromMillis(0) === '0.000', 'Empty balance formats as decimal');
quantityCheck(LogisticsQuantity::fromMillis(LogisticsQuantity::toMillis('0.100') + LogisticsQuantity::toMillis('0.200')) === '0.300', 'Fractional totals do not use binary floating point');
quantityCheck(LogisticsQuantity::fromMillis(LogisticsQuantity::toMillis('2.500') - LogisticsQuantity::toMillis('2.499')) === '0.001', 'One-thousandth pending balance is preserved');
foreach ([0, '0.000', -1, '-0.001', 0.1, 2.5, true, false, null, [], '', ' 1', '1 ', '+1', '1e3', '1,5', '.5', '1.', '1.0001', '100000000000.000'] as $input) {
    quantityRejects(fn() => LogisticsQuantity::normalize($input));
}
quantityRejects(fn() => LogisticsQuantity::fromMillis(-1));
quantityRejects(fn() => LogisticsQuantity::fromMillis(100000000000000));
echo 'Logistics quantities: ' . $checks . " checks OK\n";
