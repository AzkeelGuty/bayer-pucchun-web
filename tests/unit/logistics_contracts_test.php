<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        require dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});
require dirname(__DIR__) . '/support/logistics_contract_fakes.php';

use App\DTO\LogisticsAuthorizedOriginLine;
use App\DTO\LogisticsInventoryCommand;
use App\DTO\LogisticsInventoryResult;
use App\DTO\LogisticsConfirmedFact;
use App\DTO\LogisticsOperationContext;
use App\DTO\LogisticsOriginLineQuery;
use App\Exceptions\LogisticsApplicationException;
use App\Exceptions\LogisticsErrorCode;
use App\Support\LogisticsQuantity;

$checks = 0;
function contractCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    ++$GLOBALS['checks'];
}
function contractRejects(callable $operation, LogisticsErrorCode $expected): void
{
    try { $operation(); }
    catch (LogisticsApplicationException $error) {
        contractCheck($error->errorCode === $expected, 'Expected ' . $expected->value . ', got ' . $error->reasonCode());
        return;
    }
    throw new RuntimeException('Expected application failure ' . $expected->value);
}
function contractRejectsInput(callable $operation): void
{
    try { $operation(); }
    catch (InvalidArgumentException) { contractCheck(true, 'Malformed DTO input is rejected.'); return; }
    throw new RuntimeException('Expected invalid DTO input to be rejected.');
}
function originLine(string $type = 'VENTA', string $ref = 'sale-1', string $line = 'line-1', array $overrides = []): LogisticsAuthorizedOriginLine
{
    $data = array_replace(['originType' => $type, 'originReference' => $ref, 'lineReference' => $line,
        'branchId' => 1, 'sourceWarehouseId' => 10, 'destinationWarehouseId' => $type === 'TRASLADO' ? 11 : null,
        'productId' => 20, 'unitId' => 30, 'lotId' => 40, 'authorizedQuantity' => '10.000',
        'usedQuantity' => '2.000', 'availableQuantity' => '8.000', 'originState' => 'VALIDADO'], $overrides);
    return new LogisticsAuthorizedOriginLine(...$data);
}

$branches = new FakeLogisticsBranchContextProvider([1 => [7], 2 => [7, 8], 3 => []]);
contractCheck($branches->resolve(1)->branchId === 7, 'Single authorized branch is inferred.');
contractRejects(fn() => $branches->resolve(2), LogisticsErrorCode::INVALID_INPUT);
contractCheck($branches->resolve(2, 8)->branchId === 8, 'Explicit authorized branch is selected.');
contractRejects(fn() => $branches->resolve(2, 9), LogisticsErrorCode::FORBIDDEN);
contractRejects(fn() => $branches->resolve(3), LogisticsErrorCode::FORBIDDEN);

$sale = originLine('VENTA', 'sale-1', 'line-1', ['clientId' => 501]);
$transfer = originLine('TRASLADO', 'transfer-1', 'line-1', ['clientId' => null]);
$origins = new FakeLogisticsOriginProvider([
    'VENTA:sale-1:line-1' => $sale,
    'TRASLADO:transfer-1:line-1' => $transfer,
]);
$saleQuery = new LogisticsOriginLineQuery('VENTA', 'sale-1', 'line-1', 1, 10, null, 20, 30, 40, '8');
contractCheck($origins->authorizedLine($saleQuery) === $sale, 'Authorized sale line returns owner-provided line and balance.');
contractCheck($saleQuery->requestedQuantity === '8.000', 'Decimal query is normalized exactly.');
contractCheck(!property_exists($saleQuery, 'clientId') && $origins->authorizedLine($saleQuery)->clientId === 501,
    'Venta client identity comes only from the authorized origin result, not the query/request.');
$otherSale = originLine('VENTA', 'sale-2', 'line-1', ['clientId' => 502]);
$salesByReference = new FakeLogisticsOriginProvider(['VENTA:sale-1:line-1' => $sale, 'VENTA:sale-2:line-1' => $otherSale]);
contractCheck($salesByReference->authorizedLine(new LogisticsOriginLineQuery('VENTA', 'sale-2', 'line-1', 1, 10, null, 20, 30, 40, '1'))->clientId === 502,
    'Provider returns the client belonging to the selected authorized sale origin.');
contractRejects(fn() => $origins->authorizedLine(new LogisticsOriginLineQuery('VENTA', 'missing', 'line-1', 1, 10, null, 20, 30, 40, '1')),
    LogisticsErrorCode::NOT_FOUND);
contractRejects(fn() => $origins->authorizedLine(new LogisticsOriginLineQuery('VENTA', 'sale-1', 'line-1', 1, 10, null, 20, 30, 40, '8.001')),
    LogisticsErrorCode::QUANTITY_EXCEEDED);
contractRejects(fn() => $origins->authorizedLine(new LogisticsOriginLineQuery('VENTA', 'sale-1', 'line-1', 1, 10, null, 99, 30, 40, '1')),
    LogisticsErrorCode::REFERENCE_CONFLICT);
contractRejects(fn() => $origins->authorizedLine(new LogisticsOriginLineQuery('VENTA', 'sale-1', 'line-1', 2, 10, null, 20, 30, 40, '1')),
    LogisticsErrorCode::FORBIDDEN);
contractCheck($origins->authorizedLine(new LogisticsOriginLineQuery('TRASLADO', 'transfer-1', 'line-1', 1, 10, 11, 20, 30, 40, '8')) === $transfer,
    'Existing transfer validates both warehouses.');
contractCheck($transfer->clientId === null, 'Transfer origin does not invent a client identity.');
contractRejectsInput(fn() => originLine('VENTA', 'sale-no-client', 'line-1', ['clientId' => null]));
contractRejectsInput(fn() => originLine('TRASLADO', 'transfer-invalid', 'line-1', ['clientId' => 501]));
contractRejectsInput(fn() => new LogisticsOriginLineQuery('TRASLADO', 'transfer-1', 'line-1', 1, 10, 10, 20, 30, 40, '1'));
contractRejects(fn() => $origins->authorizedLine(new LogisticsOriginLineQuery('TRASLADO', 'missing', 'line-1', 1, 10, 11, 20, 30, 40, '1')),
    LogisticsErrorCode::NOT_FOUND);
contractRejects(fn() => $origins->authorizedLine(new LogisticsOriginLineQuery('VENTA', 'sale-1', 'line-1', 1, 10, null, 20, 30, 41, '1')),
    LogisticsErrorCode::REFERENCE_CONFLICT);
$draftOrigins = new FakeLogisticsOriginProvider(['VENTA:sale-draft:line-1' => originLine('VENTA', 'sale-draft', 'line-1', ['originState' => 'BORRADOR', 'clientId' => 501])]);
$draftOrigins->rejectedReferences[] = 'VENTA:sale-draft:line-1';
contractRejects(fn() => $draftOrigins->authorizedLine(new LogisticsOriginLineQuery('VENTA', 'sale-draft', 'line-1', 1, 10, null, 20, 30, 40, '1')),
    LogisticsErrorCode::INVALID_STATE);

$inventoryCommand = new LogisticsInventoryCommand(5, 1, 10, null, 20, 30, 40, '1.125', 'VENTA', 'sale-1', 'line-1', 9, null, 'reserve-1');
$inventory = new FakeLogisticsInventoryOperations();
$inventory->outcomes = [
    'requestReservation' => new LogisticsInventoryResult(LogisticsInventoryResult::RESERVATION_ACCEPTED, 'reservation-op', 'reserve-ref'),
    'confirmReservation' => new LogisticsInventoryResult(LogisticsInventoryResult::RESERVATION_CONFIRMED, 'reservation-op', 'reserve-ref'),
    'releaseReservation' => new LogisticsInventoryResult(LogisticsInventoryResult::RELEASED, 'release-op', 'reserve-ref'),
    'requestExit' => new LogisticsInventoryResult(LogisticsInventoryResult::EXIT_REQUESTED, 'exit-op'),
    'confirmExit' => new LogisticsInventoryResult(LogisticsInventoryResult::EXIT_CONFIRMED, 'exit-op', null, 'movement-ref'),
];
contractCheck($inventory->requestReservation($inventoryCommand)->outcome === LogisticsInventoryResult::RESERVATION_ACCEPTED, 'Reservation request is accepted only as configured.');
contractCheck($inventory->confirmReservation($inventoryCommand)->outcome === LogisticsInventoryResult::RESERVATION_CONFIRMED, 'Reservation confirmation is explicit.');
contractCheck($inventory->releaseReservation($inventoryCommand)->outcome === LogisticsInventoryResult::RELEASED, 'Reservation release outcome is explicit.');
contractCheck($inventory->requestExit($inventoryCommand)->outcome === LogisticsInventoryResult::EXIT_REQUESTED, 'Exit request does not imply confirmation.');
contractCheck($inventory->confirmExit($inventoryCommand)->movementReference === 'movement-ref', 'Exit confirmation carries confirmed movement reference.');
$inventory->outcomes['requestReservation'] = new LogisticsInventoryResult(LogisticsInventoryResult::RESERVATION_REJECTED);
contractCheck($inventory->requestReservation($inventoryCommand)->outcome === LogisticsInventoryResult::RESERVATION_REJECTED, 'Reservation rejection is represented.');
$inventory->outcomes['requestExit'] = new LogisticsInventoryResult(LogisticsInventoryResult::INSUFFICIENT_STOCK);
contractCheck($inventory->requestExit($inventoryCommand)->outcome === LogisticsInventoryResult::INSUFFICIENT_STOCK, 'Insufficient stock is distinct.');
$inventory->outcomes['requestExit'] = new LogisticsInventoryResult(LogisticsInventoryResult::CONFLICT);
contractCheck($inventory->requestExit($inventoryCommand)->outcome === LogisticsInventoryResult::CONFLICT, 'Inventory conflict is distinct.');
$inventory->outcomes['confirmReservation'] = new LogisticsInventoryResult(LogisticsInventoryResult::RESERVATION_NOT_FOUND);
contractCheck($inventory->confirmReservation($inventoryCommand)->outcome === LogisticsInventoryResult::RESERVATION_NOT_FOUND, 'Missing reservation is distinct.');
$inventory->outcomes['confirmExit'] = new LogisticsInventoryResult(LogisticsInventoryResult::OPERATION_NOT_FOUND);
contractCheck($inventory->confirmExit($inventoryCommand)->outcome === LogisticsInventoryResult::OPERATION_NOT_FOUND, 'Missing operation is distinct.');
contractRejectsInput(fn() => new LogisticsInventoryResult(LogisticsInventoryResult::EXIT_CONFIRMED, 'exit-op', null, null));
$inventory->available = false;
contractRejects(fn() => $inventory->requestExit($inventoryCommand), LogisticsErrorCode::DEPENDENCY_UNAVAILABLE);
contractCheck($inventoryCommand->quantity === '1.125', 'Inventory command preserves exact decimal quantity.');
contractCheck(count($inventory->calls) === 10, 'Fake records requests without modifying stock or auto-confirming.');

$reference = ['sistema' => 'INV', 'id' => 'fact-1'];
$scope = ['tipo' => 'RETORNO_CONFIRMADO', 'direccion' => 'ENTRADA', 'finalidad' => 'RETORNO',
    'sucursal_id' => 1, 'despacho_id' => 9, 'incidencia_id' => 91, 'despacho_detalle_id' => 90,
    'almacen_id' => 10, 'producto_id' => 20, 'unidad_id' => 30, 'lote_id' => 40, 'cantidad' => '2.500'];
$fact = ['sistema' => 'INV', 'id' => 'fact-1', 'confirmado' => true, 'autorizado' => true,
    'tipo' => 'RETORNO_CONFIRMADO', 'direccion' => 'ENTRADA', 'finalidad' => 'RETORNO',
    'sucursal_id' => '1', 'despacho_id' => '9', 'incidencia_id' => '91', 'despacho_detalle_id' => '90',
    'almacen_id' => 10, 'producto_id' => 20, 'unidad_id' => 30, 'lote_id' => 40,
    'cantidad' => '2.5', 'evidencia' => ['acta' => 'R-1']];
$factProvider = new FakeLogisticsInventoryConfirmationProvider(['INV:fact-1' => $fact]);
$confirmed = LogisticsConfirmedFact::fromProvider($factProvider->confirmedFact($reference), $scope);
contractCheck($confirmed->data['cantidad'] === '2.500', 'Confirmed fact is normalized with exact decimal precision.');
$unconfirmed = $fact; $unconfirmed['confirmado'] = false;
contractRejects(fn() => LogisticsConfirmedFact::fromProvider($unconfirmed, $scope), LogisticsErrorCode::REFERENCE_CONFLICT);
$unauthorized = $fact; $unauthorized['autorizado'] = false;
contractRejects(fn() => LogisticsConfirmedFact::fromProvider($unauthorized, $scope), LogisticsErrorCode::REFERENCE_CONFLICT);
$wrongScope = $fact; $wrongScope['despacho_id'] = 99;
contractRejects(fn() => LogisticsConfirmedFact::fromProvider($wrongScope, $scope), LogisticsErrorCode::REFERENCE_CONFLICT);
$wrongPurpose = $fact; $wrongPurpose['finalidad'] = 'PERDIDA';
contractRejects(fn() => LogisticsConfirmedFact::fromProvider($wrongPurpose, $scope), LogisticsErrorCode::REFERENCE_CONFLICT);
$unauthorizedLoss = $fact; $unauthorizedLoss['tipo'] = 'PERDIDA_CONFIRMADA';
$unauthorizedLoss['direccion'] = 'SIN_NUEVO_EGRESO'; $unauthorizedLoss['finalidad'] = 'PERDIDA';
$unauthorizedLoss['autorizacion_ref'] = null;
contractRejects(fn() => LogisticsConfirmedFact::fromProvider($unauthorizedLoss, []), LogisticsErrorCode::REFERENCE_CONFLICT);
contractRejects(fn() => $factProvider->confirmedFact(['sistema' => 'INV', 'id' => 'missing']), LogisticsErrorCode::NOT_FOUND);

foreach ([LogisticsErrorCode::NOT_FOUND, LogisticsErrorCode::FORBIDDEN, LogisticsErrorCode::CONFLICT,
    LogisticsErrorCode::INVALID_INPUT, LogisticsErrorCode::UNAUTHENTICATED, LogisticsErrorCode::IDEMPOTENCY_CONFLICT,
    LogisticsErrorCode::INVALID_STATE, LogisticsErrorCode::QUANTITY_EXCEEDED, LogisticsErrorCode::REFERENCE_CONFLICT,
    LogisticsErrorCode::DEPENDENCY_UNAVAILABLE,
    LogisticsErrorCode::DEPENDENCY_REJECTED] as $code) {
    $error = new LogisticsApplicationException($code, 'contract test');
    contractCheck($error->reasonCode() === $code->value, 'Stable application error code: ' . $code->value);
}
$operationContext = new LogisticsOperationContext(5, 1, 'request-123', 'correlation-1');
contractCheck($operationContext->toRepositoryContext() === ['sucursal_id' => 1, 'actor_id' => 5, 'idempotency_key' => 'request-123'],
    'Repository context uses Pedro field names and excludes a duplicate request hash.');
contractCheck(LogisticsQuantity::normalize('0.001') === '0.001', 'Minimum fractional precision remains exact.');

echo 'Logistics contracts: ' . $checks . " checks OK\n";
