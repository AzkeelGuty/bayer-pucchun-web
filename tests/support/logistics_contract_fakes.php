<?php
declare(strict_types=1);

use App\Contracts\LogisticsBranchContextInterface;
use App\Contracts\LogisticsInventoryConfirmationProviderInterface;
use App\Contracts\LogisticsInventoryOperationsInterface;
use App\Contracts\LogisticsOriginProviderInterface;
use App\DTO\LogisticsAuthorizedOriginLine;
use App\DTO\LogisticsBranchContext;
use App\DTO\LogisticsInventoryCommand;
use App\DTO\LogisticsInventoryResult;
use App\DTO\LogisticsOriginLineQuery;
use App\Exceptions\LogisticsApplicationException;
use App\Exceptions\LogisticsErrorCode;
use App\Support\LogisticsQuantity;

/** Test-only branch fixture. It contains explicit grants and never reads application data. */
final class FakeLogisticsBranchContextProvider implements LogisticsBranchContextInterface
{
    public function __construct(private array $branchesByActor) {}

    public function resolve(int $actorId, ?int $selectedBranchId = null): LogisticsBranchContext
    {
        $branches = $this->branchesByActor[$actorId] ?? [];
        if ($branches === []) {
            throw new LogisticsApplicationException(LogisticsErrorCode::FORBIDDEN, 'Actor has no authorized branch.');
        }
        if ($selectedBranchId === null) {
            if (count($branches) > 1) {
                throw new LogisticsApplicationException(LogisticsErrorCode::INVALID_INPUT, 'Select an authorized branch.');
            }
            $selectedBranchId = $branches[0];
        }
        if (!in_array($selectedBranchId, $branches, true)) {
            throw new LogisticsApplicationException(LogisticsErrorCode::FORBIDDEN, 'Branch selection is not authorized.');
        }
        return new LogisticsBranchContext($actorId, $selectedBranchId);
    }
}

/** Test-only owner-module fixture. All source rows are explicitly supplied by the test. */
final class FakeLogisticsOriginProvider implements LogisticsOriginProviderInterface
{
    public array $rejectedReferences = [];

    public function __construct(private array $linesByReference) {}

    public function authorizedLine(LogisticsOriginLineQuery $query): LogisticsAuthorizedOriginLine
    {
        $line = $this->linesByReference[$query->originType . ':' . $query->originReference . ':' . $query->lineReference] ?? null;
        if (!$line instanceof LogisticsAuthorizedOriginLine) {
            throw new LogisticsApplicationException(LogisticsErrorCode::NOT_FOUND, 'Origin or line not found.');
        }
        if ($line->branchId !== $query->branchId) {
            throw new LogisticsApplicationException(LogisticsErrorCode::FORBIDDEN, 'Origin is outside the authorized branch.');
        }
        if (in_array($query->originType . ':' . $query->originReference . ':' . $query->lineReference, $this->rejectedReferences, true)) {
            throw new LogisticsApplicationException(LogisticsErrorCode::INVALID_STATE, 'Origin state does not allow preparation.');
        }
        if ($line->sourceWarehouseId !== $query->sourceWarehouseId
            || $line->destinationWarehouseId !== $query->destinationWarehouseId
            || $line->productId !== $query->productId || $line->unitId !== $query->unitId
            || $line->lotId !== $query->lotId) {
            throw new LogisticsApplicationException(LogisticsErrorCode::REFERENCE_CONFLICT, 'Requested line identity does not match the authorized origin.');
        }
        if (LogisticsQuantity::toMillis($query->requestedQuantity)
            > LogisticsQuantity::toMillis($line->availableQuantity, true)) {
            throw new LogisticsApplicationException(LogisticsErrorCode::QUANTITY_EXCEEDED, 'Requested quantity exceeds available origin balance.');
        }
        return $line;
    }
}

/** Test-only scripted provider. It never invents a confirmation; tests must set each outcome. */
final class FakeLogisticsInventoryOperations implements LogisticsInventoryOperationsInterface
{
    public array $calls = [];
    public bool $available = true;
    public array $outcomes = [];

    public function requestReservation(LogisticsInventoryCommand $command): LogisticsInventoryResult { return $this->run(__FUNCTION__, $command); }
    public function confirmReservation(LogisticsInventoryCommand $command): LogisticsInventoryResult { return $this->run(__FUNCTION__, $command); }
    public function releaseReservation(LogisticsInventoryCommand $command): LogisticsInventoryResult { return $this->run(__FUNCTION__, $command); }
    public function requestExit(LogisticsInventoryCommand $command): LogisticsInventoryResult { return $this->run(__FUNCTION__, $command); }
    public function confirmExit(LogisticsInventoryCommand $command): LogisticsInventoryResult { return $this->run(__FUNCTION__, $command); }

    private function run(string $operation, LogisticsInventoryCommand $command): LogisticsInventoryResult
    {
        if (!$this->available) {
            throw new LogisticsApplicationException(LogisticsErrorCode::DEPENDENCY_UNAVAILABLE, 'Inventory provider is unavailable.');
        }
        $this->calls[] = [$operation, $command];
        $result = $this->outcomes[$operation] ?? null;
        if (!$result instanceof LogisticsInventoryResult) {
            throw new LogisticsApplicationException(LogisticsErrorCode::DEPENDENCY_UNAVAILABLE, 'Test did not configure an Inventory outcome.');
        }
        return $result;
    }
}

/** Test-only fixture for the existing Pedro contract; never a production provider. */
final class FakeLogisticsInventoryConfirmationProvider implements LogisticsInventoryConfirmationProviderInterface
{
    public function __construct(private array $factsByReference) {}

    public function confirmedFact(array $reference): array
    {
        $key = ($reference['sistema'] ?? '') . ':' . ($reference['id'] ?? '');
        if (!isset($this->factsByReference[$key])) {
            throw new LogisticsApplicationException(LogisticsErrorCode::NOT_FOUND, 'Confirmed Inventory fact was not found.');
        }
        return $this->factsByReference[$key];
    }
}
