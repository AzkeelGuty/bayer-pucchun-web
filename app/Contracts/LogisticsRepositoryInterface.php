<?php
declare(strict_types=1);

namespace App\Contracts;

/**
 * Services own authorization, allowed state transitions and external inventory orchestration.
 * Context: sucursal_id, actor_id, idempotency_key. The same normalized command replays its
 * original result. Reads return a flat header with details; quantities use three decimals.
 * Header mutation result: id, estado, version. Delivery result: id, anulada, version,
 * despacho_id, despacho_estado, despacho_version. No Guide/Stock CRUD is exposed.
 */
interface LogisticsRepositoryInterface
{
    public function transaction(callable $operation): mixed;
    public function createPreparation(array $header, array $details, array $context): array;
    public function updatePreparation(int $id, int $expectedVersion, array $header, array $details, array $context): array;
    public function transitionPreparation(int $id, int $expectedVersion, string $expectedState, string $newState, string $reason, array $context): array;
    public function createDispatch(int $preparationId, array $header, array $context): array;
    public function attachGuide(int $dispatchId, int $expectedVersion, int $guideId, int $guideVersion, array $context): array;
    public function transitionDispatch(int $id, int $expectedVersion, string $expectedState, string $newState, array $references, array $context): array;
    public function recordDelivery(int $dispatchId, int $expectedVersion, array $header, array $details, string $resultingState, array $context): array;
    public function voidDelivery(int $deliveryId, int $expectedDeliveryVersion, int $expectedDispatchVersion, string $resultingState, string $reason, array $context): array;
    public function createIncident(int $dispatchId, int $expectedDispatchVersion, array $incident, array $context): array;
    public function resolveIncident(int $incidentId, int $expectedIncidentVersion, int $expectedDispatchVersion, array $resolution, array $context): array;
    public function recordIncidentAction(int $incidentId, int $expectedIncidentVersion, int $expectedDispatchVersion, array $action, array $context): array;
    public function closeDispatch(int $dispatchId, int $expectedVersion, string $expectedState, string $reason, array $context): array;
    public function findPreparation(int $id, int $branchId): ?array;
    public function findDispatch(int $id, int $branchId): ?array;
    public function findDelivery(int $id, int $branchId): ?array;
    public function findIncident(int $id, int $branchId): ?array;
    public function listIncidents(int $dispatchId, int $branchId, array $filters = [], int $limit = 50, int $offset = 0): array;
    public function listPreparations(int $branchId, array $filters = [], int $limit = 50, int $offset = 0): array;
    public function listDispatches(int $branchId, array $filters = [], int $limit = 50, int $offset = 0): array;
    public function listDeliveries(int $dispatchId, int $branchId, int $limit = 50, int $offset = 0): array;
    public function history(string $aggregateType, int $id, int $branchId, int $limit = 100, int $offset = 0): array;
}
