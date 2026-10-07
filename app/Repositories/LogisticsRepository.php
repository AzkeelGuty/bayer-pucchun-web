<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\LogisticsRepositoryInterface;
use App\Contracts\LogisticsInventoryConfirmationProviderInterface;
use App\Exceptions\LogisticsPersistenceException;
use App\Support\LogisticsQuantity;
use DateTimeImmutable;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Physical logistics persistence. Never mutates Guides, stock or reservations.
 * All mutation commands replay before evaluating present versions/state. Services must
 * authorize their caller first, including when a command may return an earlier result.
 */
final class LogisticsRepository implements LogisticsRepositoryInterface
{
    private const PREPARATION_STATES = ['EN_PREPARACION', 'PREPARADA', 'CANCELADA'];
    private const DISPATCH_STATES = ['PENDIENTE', 'DESPACHADO', 'EN_TRANSITO', 'EN_RESOLUCION', 'ENTREGADO', 'CANCELADO', 'CERRADO_CON_INCIDENCIA'];
    private const INCIDENT_TYPES = ['FALTANTE', 'RECHAZO', 'DANO', 'PERDIDA_EN_INVESTIGACION', 'AUSENCIA_RECEPTOR', 'RETRASO', 'PROBLEMA_DOCUMENTAL'];
    private const REFERENCES = ['movimiento_salida_ref', 'motivo', 'resolucion_ref'];
    private PDO $pdo;
    private string $savepointPrefix;
    private int $savepointCounter = 0;
    private ?LogisticsInventoryConfirmationProviderInterface $inventory;

    public function __construct(?PDO $pdo = null, ?LogisticsInventoryConfirmationProviderInterface $inventory = null)
    {
        $this->pdo = $pdo ?? \db();
        $this->inventory = $inventory;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->savepointPrefix = 'logistics_' . bin2hex(random_bytes(6)) . '_';
    }

    /** An external owner keeps its transaction; nested failures roll back only our savepoint. */
    public function transaction(callable $operation): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        $savepoint = $this->savepointPrefix . ++$this->savepointCounter;
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT ' . $savepoint);
        }
        try {
            $result = $operation();
            if (!$this->pdo->inTransaction()) {
                throw new LogisticsPersistenceException('CONFLICT', 'El callback no debe cerrar la transacción compartida.');
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $failure) {
            if ($this->pdo->inTransaction()) {
                try {
                    if ($ownsTransaction) {
                        $this->pdo->rollBack();
                    } else {
                        $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                        $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                    }
                } catch (PDOException $cleanupFailure) {
                    // InnoDB may abort the whole caller transaction on a deadlock.
                    // A driver that still reports inTransaction then sees an absent
                    // savepoint. Preserve the original deadlock/retry information.
                    if ((int) ($cleanupFailure->errorInfo[1] ?? 0) !== 1305 || !$this->containsDriverCode($failure, 1213)) {
                        throw new LogisticsPersistenceException('CONFLICT', 'No se pudo restaurar la unidad de trabajo; el llamador debe revertir o reiniciar la transacción completa.', $failure);
                    }
                }
            }
            throw $failure;
        }
    }

    public function createPreparation(array $header, array $details, array $context): array
    {
        $header = $this->preparationHeader($header);
        $details = $this->preparationDetails($details);
        return $this->command('CREATE_PREPARATION', compact('header', 'details'), $context, function (array $ctx, int $operationId) use ($header, $details): array {
            $this->checkPreparationReferences($header, $details, $ctx['sucursal_id']);
            $id = $this->insert('logistica_preparaciones', $header + [
                'sucursal_id' => $ctx['sucursal_id'], 'estado' => 'EN_PREPARACION', 'version' => 1,
                'created_by' => $ctx['actor_id'], 'updated_by' => $ctx['actor_id'],
            ]);
            $this->insertPreparationDetails($id, $details);
            $this->event($ctx, $operationId, 'CREATE_PREPARATION', $id, null, null, null, 'EN_PREPARACION');
            return $this->headerResult($id, 'EN_PREPARACION', 1);
        });
    }

    public function updatePreparation(int $id, int $expectedVersion, array $header, array $details, array $context): array
    {
        $this->ids($id, $expectedVersion);
        $header = $this->preparationHeader($header);
        $details = $this->preparationDetails($details);
        return $this->command('UPDATE_PREPARATION', compact('id', 'expectedVersion', 'header', 'details'), $context, function (array $ctx, int $operationId) use ($id, $expectedVersion, $header, $details): array {
            $current = $this->locked('logistica_preparaciones', $id, $ctx['sucursal_id']);
            $this->version($current, $expectedVersion);
            $this->state($current, 'EN_PREPARACION');
            $this->noDispatch($id);
            $this->checkPreparationReferences($header, $details, $ctx['sucursal_id']);
            $this->execute('DELETE FROM logistica_preparacion_detalle WHERE preparacion_id=?', [$id]);
            $this->updateHeader('logistica_preparaciones', $id, $expectedVersion, $header, $ctx['actor_id']);
            $this->insertPreparationDetails($id, $details);
            $this->event($ctx, $operationId, 'UPDATE_PREPARATION', $id, null, null, 'EN_PREPARACION', 'EN_PREPARACION');
            return $this->headerResult($id, 'EN_PREPARACION', $expectedVersion + 1);
        });
    }

    public function transitionPreparation(int $id, int $expectedVersion, string $expectedState, string $newState, string $reason, array $context): array
    {
        $this->ids($id, $expectedVersion);
        $this->enum($expectedState, self::PREPARATION_STATES);
        $this->enum($newState, self::PREPARATION_STATES);
        $reason = $this->text($reason, 'reason', 65535, $newState !== 'CANCELADA');
        return $this->command('TRANSITION_PREPARATION', compact('id', 'expectedVersion', 'expectedState', 'newState', 'reason'), $context, function (array $ctx, int $operationId) use ($id, $expectedVersion, $expectedState, $newState, $reason): array {
            $current = $this->locked('logistica_preparaciones', $id, $ctx['sucursal_id']);
            $this->version($current, $expectedVersion);
            $this->state($current, $expectedState);
            if ($expectedState === $newState) {
                $this->fail('INVALID_STATE', 'La transición debe cambiar el estado.');
            }
            if ($newState === 'CANCELADA') {
                $this->cancellablePreparation($id);
            } else {
                $this->noDispatch($id);
            }
            $this->updateHeader('logistica_preparaciones', $id, $expectedVersion, ['estado' => $newState], $ctx['actor_id']);
            $this->event($ctx, $operationId, 'TRANSITION_PREPARATION', $id, null, null, $expectedState, $newState, $reason);
            return $this->headerResult($id, $newState, $expectedVersion + 1);
        });
    }

    public function createDispatch(int $preparationId, array $header, array $context): array
    {
        $this->ids($preparationId);
        $header = $this->dispatchHeader($header);
        return $this->command('CREATE_DISPATCH', compact('preparationId', 'header'), $context, function (array $ctx, int $operationId) use ($preparationId, $header): array {
            $preparation = $this->locked('logistica_preparaciones', $preparationId, $ctx['sucursal_id']);
            $this->state($preparation, 'PREPARADA');
            $this->noDispatch($preparationId);
            if ($header['guia_id'] !== null) {
                $this->guide($header['guia_id'], $header['guia_version'], $preparation, false);
                $this->guideAvailable($header['guia_id']);
            }
            $id = $this->insert('logistica_despachos', $header + [
                'preparacion_id' => $preparationId, 'sucursal_id' => $ctx['sucursal_id'],
                'estado' => 'PENDIENTE', 'version' => 1, 'created_by' => $ctx['actor_id'], 'updated_by' => $ctx['actor_id'],
            ]);
            $details = $this->execute('SELECT * FROM logistica_preparacion_detalle WHERE preparacion_id=? ORDER BY posicion LOCK IN SHARE MODE', [$preparationId])->fetchAll(PDO::FETCH_ASSOC);
            if ($details === []) {
                $this->fail('REFERENCE_CONFLICT', 'La preparación no tiene detalles.');
            }
            foreach ($details as $detail) {
                $this->insert('logistica_despacho_detalle', [
                    'despacho_id' => $id, 'preparacion_id' => $preparationId, 'preparacion_detalle_id' => (int) $detail['id'],
                    'producto_id' => (int) $detail['producto_id'], 'unidad_id' => (int) $detail['unidad_id'],
                    'lote_id' => $detail['lote_id'] === null ? null : (int) $detail['lote_id'],
                    'cantidad' => LogisticsQuantity::normalize($detail['cantidad']),
                ]);
            }
            $this->event($ctx, $operationId, 'CREATE_DISPATCH', $preparationId, $id, null, null, 'PENDIENTE');
            return $this->headerResult($id, 'PENDIENTE', 1);
        });
    }

    public function attachGuide(int $dispatchId, int $expectedVersion, int $guideId, int $guideVersion, array $context): array
    {
        $this->ids($dispatchId, $expectedVersion, $guideId, $guideVersion);
        return $this->command('ATTACH_GUIDE', compact('dispatchId', 'expectedVersion', 'guideId', 'guideVersion'), $context, function (array $ctx, int $operationId) use ($dispatchId, $expectedVersion, $guideId, $guideVersion): array {
            $dispatch = $this->locked('logistica_despachos', $dispatchId, $ctx['sucursal_id']);
            $this->version($dispatch, $expectedVersion);
            $this->state($dispatch, 'PENDIENTE');
            $preparation = $this->locked('logistica_preparaciones', (int) $dispatch['preparacion_id'], $ctx['sucursal_id'], false);
            $this->guide($guideId, $guideVersion, $preparation, false);
            $this->guideAvailable($guideId, $dispatchId);
            $this->updateHeader('logistica_despachos', $dispatchId, $expectedVersion, ['guia_id' => $guideId, 'guia_version' => $guideVersion, 'guia_snapshot' => null], $ctx['actor_id']);
            $this->event($ctx, $operationId, 'ATTACH_GUIDE', (int) $dispatch['preparacion_id'], $dispatchId, null, 'PENDIENTE', 'PENDIENTE');
            return $this->headerResult($dispatchId, 'PENDIENTE', $expectedVersion + 1);
        });
    }

    public function transitionDispatch(int $id, int $expectedVersion, string $expectedState, string $newState, array $references, array $context): array
    {
        $this->ids($id, $expectedVersion);
        $this->enum($expectedState, self::DISPATCH_STATES);
        $this->enum($newState, self::DISPATCH_STATES);
        $references = $this->dispatchReferences($references);
        return $this->command('TRANSITION_DISPATCH', compact('id', 'expectedVersion', 'expectedState', 'newState', 'references'), $context, function (array $ctx, int $operationId) use ($id, $expectedVersion, $expectedState, $newState, $references): array {
            $dispatch = $this->locked('logistica_despachos', $id, $ctx['sucursal_id']);
            $this->version($dispatch, $expectedVersion);
            $this->state($dispatch, $expectedState);
            if ($expectedState === $newState || in_array($expectedState, ['CANCELADO', 'CERRADO_CON_INCIDENCIA', 'ENTREGADO'], true)) {
                $this->fail('INVALID_STATE', 'El despacho no admite esa modificación; una anulación de entrega utiliza voidDelivery.');
            }
            $updates = ['estado' => $newState];
            if ($references['movimiento_salida_ref'] !== null && $dispatch['movimiento_salida_ref'] !== null
                && $references['movimiento_salida_ref'] !== $dispatch['movimiento_salida_ref']) {
                $this->fail('REFERENCE_CONFLICT', 'No se puede sustituir el movimiento de salida confirmado.');
            }
            if ($newState === 'DESPACHADO') {
                if ($expectedState !== 'PENDIENTE') {
                    $this->fail('INVALID_STATE', 'La salida requiere un despacho pendiente.');
                }
                if ($references['movimiento_salida_ref'] === null) {
                    $this->fail('REFERENCE_CONFLICT', 'La salida requiere un movimiento confirmado por Inventarios.');
                }
                if ($dispatch['guia_id'] === null) {
                    $this->fail('REFERENCE_CONFLICT', 'El despacho requiere una guía asociada.');
                }
                $preparation = $this->locked('logistica_preparaciones', (int) $dispatch['preparacion_id'], $ctx['sucursal_id'], false);
                $this->state($preparation, 'PREPARADA');
                $guide = $this->guide((int) $dispatch['guia_id'], (int) $dispatch['guia_version'], $preparation, true);
                $this->guideQuantities($id, $guide['details']);
                $updates['guia_snapshot'] = $this->json($guide);
                $updates['movimiento_salida_ref'] = $references['movimiento_salida_ref'];
            } elseif ($newState === 'CANCELADO') {
                if ($expectedState !== 'PENDIENTE' || $dispatch['movimiento_salida_ref'] !== null || $references['movimiento_salida_ref'] !== null || $references['motivo'] === null) {
                    $this->fail('INVALID_STATE', 'Solo se puede cancelar antes de la salida y con motivo.');
                }
            } elseif (in_array($newState, ['CERRADO_CON_INCIDENCIA', 'ENTREGADO', 'EN_RESOLUCION'], true)) {
                $this->fail('INVALID_STATE', 'El cierre y las excepciones se derivan de sus hechos; use los comandos específicos.');
            } elseif ($newState === 'EN_TRANSITO') {
                if ($expectedState !== 'DESPACHADO') {
                    $this->fail('INVALID_STATE', 'El tránsito requiere una salida registrada.');
                }
                $this->physicalDispatch($dispatch);
            } else {
                $this->fail('INVALID_STATE', 'No se puede devolver un despacho físico a pendiente.');
            }
            if ($newState !== 'DESPACHADO' && $references['movimiento_salida_ref'] !== null && $dispatch['movimiento_salida_ref'] === null) {
                $this->fail('REFERENCE_CONFLICT', 'Solo la confirmación de despacho registra una salida.');
            }
            if ($references['resolucion_ref'] !== null) {
                $this->fail('REFERENCE_CONFLICT', 'Una referencia textual no acredita una resolución de cantidades.');
            }
            $this->updateHeader('logistica_despachos', $id, $expectedVersion, $updates, $ctx['actor_id']);
            $this->event($ctx, $operationId, 'TRANSITION_DISPATCH', (int) $dispatch['preparacion_id'], $id, null, $expectedState, $newState, $references['motivo']);
            return $this->headerResult($id, $newState, $expectedVersion + 1);
        });
    }

    public function recordDelivery(int $dispatchId, int $expectedVersion, array $header, array $details, string $resultingState, array $context): array
    {
        $this->ids($dispatchId, $expectedVersion);
        $header = $this->deliveryHeader($header);
        $details = $this->deliveryDetails($details);
        $this->enum($resultingState, ['EN_TRANSITO', 'EN_RESOLUCION', 'ENTREGADO']);
        return $this->command('RECORD_DELIVERY', compact('dispatchId', 'expectedVersion', 'header', 'details', 'resultingState'), $context, function (array $ctx, int $operationId) use ($dispatchId, $expectedVersion, $header, $details, $resultingState): array {
            $dispatch = $this->locked('logistica_despachos', $dispatchId, $ctx['sucursal_id']);
            $this->version($dispatch, $expectedVersion);
            $this->openPhysicalDispatch($dispatch);
            $this->physicalDispatch($dispatch);
            $balances = $this->dispatchBalances($dispatchId);
            $cases = $this->lockedCases($dispatchId);
            $result = $this->attemptResult($header, $details);
            $header['resultado'] = $result;
            $preparation = $this->locked('logistica_preparaciones', (int) $dispatch['preparacion_id'], $ctx['sucursal_id'], false);
            if ($preparation['tipo_origen'] === 'TRASLADO' && array_filter($details, static fn(array $line): bool => $line['cantidad'] !== '0.000') !== []) {
                $this->fail('REFERENCE_CONFLICT', 'La aceptación de un traslado requiere el contrato de recepción confirmada de Inventarios; aún no está integrado.');
            }
            foreach ($details as $detail) {
                $lineId = $detail['despacho_detalle_id'];
                if (!isset($balances[$lineId])) {
                    $this->fail('REFERENCE_CONFLICT', 'La línea de entrega no pertenece al despacho.');
                }
                $accepted = LogisticsQuantity::toMillis($detail['cantidad'], true);
                $rejected = LogisticsQuantity::toMillis($detail['cantidad_rechazada'], true);
                if ($accepted + $rejected > $balances[$lineId]['pending']) {
                    $this->fail('QUANTITY_EXCEEDED', 'La cantidad presentada supera el saldo pendiente de la línea.');
                }
                $assignedAccepted = $this->allocationTotal($detail['aceptaciones_incidencias'], $lineId, $cases);
                $assignedRejected = $this->allocationTotal($detail['rechazos_incidencias'], $lineId, $cases);
                if ($assignedAccepted > $accepted || $assignedRejected > $rejected) {
                    $this->fail('QUANTITY_EXCEEDED', 'La asignación a incidencias supera lo aceptado o rechazado en ese detalle.');
                }
                $caseUsage = [];
                foreach ([...$detail['aceptaciones_incidencias'], ...$detail['rechazos_incidencias']] as $allocation) {
                    $caseId = $allocation['incidencia_id'];
                    $caseUsage[$caseId] = ($caseUsage[$caseId] ?? 0) + LogisticsQuantity::toMillis($allocation['cantidad']);
                    if ($caseUsage[$caseId] > $cases[$caseId]['remaining']) {
                        $this->fail('QUANTITY_EXCEEDED', 'Un reintento supera la cantidad pendiente de su incidencia.');
                    }
                }
                if ($accepted - $assignedAccepted + $rejected - $assignedRejected > $balances[$lineId]['free']) {
                    $this->fail('QUANTITY_EXCEEDED', 'Las cantidades sin incidencia identificada superan el pendiente libre.');
                }
            }
            $storedHeader = $header;
            unset($storedHeader['incidencias'], $storedHeader['tipo_fallo']);
            $id = $this->insert('logistica_entregas', $storedHeader + [
                'despacho_id' => $dispatchId, 'sucursal_id' => $ctx['sucursal_id'], 'anulada' => 0,
                'version' => 1, 'created_by' => $ctx['actor_id'],
            ]);
            $incidentIds = [];
            $resolutionIds = [];
            foreach ($details as $detail) {
                $deliveryLineId = $this->insert('logistica_entrega_detalle', [
                    'entrega_id' => $id, 'despacho_id' => $dispatchId,
                    'despacho_detalle_id' => $detail['despacho_detalle_id'], 'cantidad' => $detail['cantidad'],
                    'cantidad_rechazada' => $detail['cantidad_rechazada'],
                ]);
                foreach ($detail['aceptaciones_incidencias'] as $allocation) {
                    $caseId = $allocation['incidencia_id'];
                    $quantity = LogisticsQuantity::toMillis($allocation['cantidad']);
                    $resolutionId = $this->insertResolution($cases[$caseId], [
                        'tipo' => 'ACEPTACION_EN_REINTENTO', 'cantidad' => $allocation['cantidad'],
                        'entrega_detalle_id' => $deliveryLineId, 'motivo' => 'Aceptación confirmada en nuevo intento.',
                        'evidencia_json' => $this->json(['entrega_id' => $id, 'entrega_detalle_id' => $deliveryLineId]),
                        'fecha_operativa' => $header['fecha_operativa'],
                    ], $ctx);
                    $resolutionIds[] = $resolutionId;
                    $cases[$caseId]['remaining'] -= $quantity;
                    $this->touchCase($cases[$caseId], $ctx, $cases[$caseId]['remaining'] === 0);
                    $cases[$caseId]['version']++;
                    $this->caseEvent($ctx, $operationId, 'ACCEPT_REATTEMPT', $dispatch, $caseId, $resolutionId, $id, ['cantidad' => $allocation['cantidad']]);
                }
                $oldRejections = 0;
                foreach ($detail['rechazos_incidencias'] as $allocation) {
                    $caseId = $allocation['incidencia_id'];
                    $oldRejections += LogisticsQuantity::toMillis($allocation['cantidad']);
                    $mergedCauses = $this->causes([...json_decode($cases[$caseId]['causas_json'], true, 512, JSON_THROW_ON_ERROR), ...$detail['causas_rechazo']]);
                    $this->touchCase($cases[$caseId], $ctx, false, ['causas_json' => $this->json($mergedCauses)]);
                    $cases[$caseId]['version']++;
                    $this->caseEvent($ctx, $operationId, 'REJECT_REATTEMPT', $dispatch, $caseId, null, $id, [
                        'cantidad' => $allocation['cantidad'], 'causas' => $detail['causas_rechazo'], 'motivo' => $detail['motivo_rechazo'],
                    ]);
                }
                $newRejected = LogisticsQuantity::toMillis($detail['cantidad_rechazada'], true) - $oldRejections;
                if ($newRejected > 0) {
                    $incidentIds[] = $this->insertIncident($dispatch, [
                        'despacho_detalle_id' => $detail['despacho_detalle_id'], 'entrega_id' => $id,
                        'tipo' => 'RECHAZO', 'modo' => 'CUANTITATIVA', 'cantidad' => LogisticsQuantity::fromMillis($newRejected),
                        'causas_json' => $this->json($detail['causas_rechazo']), 'motivo' => $detail['motivo_rechazo'],
                        'evidencia_json' => $this->json(['entrega_id' => $id, 'entrega_detalle_id' => $deliveryLineId]),
                        'bloqueante' => 1, 'responsable_id' => $ctx['actor_id'],
                    ], $ctx, $operationId);
                }
                foreach ($detail['incidencias_informativas'] as $incident) {
                    $incident['entrega_id'] = $id;
                    $incident['responsable_id'] ??= $ctx['actor_id'];
                    $incidentIds[] = $this->insertIncident($dispatch, $incident, $ctx, $operationId);
                }
            }
            if ($result === 'FALLIDA') {
                $incidentIds[] = $this->insertIncident($dispatch, [
                    'despacho_detalle_id' => null, 'entrega_id' => $id, 'tipo' => $header['tipo_fallo'],
                    'modo' => 'INFORMATIVA', 'cantidad' => null, 'causas_json' => $this->json([$header['tipo_fallo']]),
                    'motivo' => $header['motivo_fallo'], 'evidencia_json' => $this->json(['contexto' => $header['observaciones']]),
                    'bloqueante' => $header['tipo_fallo'] === 'RETRASO' ? 0 : 1, 'responsable_id' => $ctx['actor_id'],
                ], $ctx, $operationId);
            }
            $acceptedBalances = $this->dispatchBalances($dispatchId);
            foreach ($header['incidencias'] as $incident) {
                $lineId = $incident['despacho_detalle_id'];
                if ($lineId !== null && !isset($acceptedBalances[$lineId])) {
                    $this->fail('REFERENCE_CONFLICT', 'El reclamo informativo corresponde a otra línea.');
                }
                if ($incident['cantidad'] !== null && LogisticsQuantity::toMillis($incident['cantidad']) > $acceptedBalances[$lineId]['delivered']) {
                    $this->fail('QUANTITY_EXCEEDED', 'El reclamo supera la cantidad aceptada vigente de esa línea.');
                }
                $incident['entrega_id'] = $id;
                $incident['responsable_id'] ??= $ctx['actor_id'];
                $incidentIds[] = $this->insertIncident($dispatch, $incident, $ctx, $operationId);
            }
            $balances = $this->dispatchBalances($dispatchId);
            $computedState = $this->operationalState($dispatchId, $balances);
            if ($resultingState !== $computedState) {
                $this->fail('INVALID_STATE', 'El estado solicitado no corresponde al balance y las incidencias pendientes.');
            }
            $updates = ['estado' => $computedState];
            if ($computedState === 'ENTREGADO') {
                $this->assertCloseable($dispatchId, $balances);
                $updates += ['cerrado_at' => $this->utcNow(), 'cerrado_by' => $ctx['actor_id']];
            }
            $this->updateHeader('logistica_despachos', $dispatchId, $expectedVersion, $updates, $ctx['actor_id']);
            $this->event($ctx, $operationId, 'RECORD_DELIVERY', (int) $dispatch['preparacion_id'], $dispatchId, $id, $dispatch['estado'], $computedState, null,
                ['resultado' => $result, 'balances' => $this->balanceSnapshot($balances), 'incidencias_ids' => $incidentIds, 'resoluciones_ids' => $resolutionIds]);
            return $this->deliveryResult($id, false, 1, $dispatchId, $computedState, $expectedVersion + 1) + [
                'resultado' => $result, 'incidencias_ids' => $incidentIds, 'reentrega_resoluciones_ids' => $resolutionIds,
            ];
        });
    }

    public function voidDelivery(int $deliveryId, int $expectedDeliveryVersion, int $expectedDispatchVersion, string $resultingState, string $reason, array $context): array
    {
        $this->ids($deliveryId, $expectedDeliveryVersion, $expectedDispatchVersion);
        $this->enum($resultingState, ['EN_TRANSITO', 'EN_RESOLUCION']);
        $reason = $this->text($reason, 'reason', 65535);
        return $this->command('VOID_DELIVERY', compact('deliveryId', 'expectedDeliveryVersion', 'expectedDispatchVersion', 'resultingState', 'reason'), $context, function (array $ctx, int $operationId) use ($deliveryId, $expectedDeliveryVersion, $expectedDispatchVersion, $resultingState, $reason): array {
            // The immutable parent locator needs a current shared read under caller RR.
            // Concurrent voids may contend on this shared child lock; report CONFLICT
            // and let the Service restart its full transaction after an InnoDB deadlock.
            $dispatchId = $this->execute('SELECT despacho_id FROM logistica_entregas WHERE id=? AND sucursal_id=? LOCK IN SHARE MODE', [$deliveryId, $ctx['sucursal_id']])->fetchColumn();
            if ($dispatchId === false) {
                $this->fail('NOT_FOUND', 'Entrega no encontrada en la sede.');
            }
            $dispatchId = (int) $dispatchId;
            $dispatch = $this->locked('logistica_despachos', $dispatchId, $ctx['sucursal_id']);
            $delivery = $this->locked('logistica_entregas', $deliveryId, $ctx['sucursal_id']);
            $this->version($dispatch, $expectedDispatchVersion);
            $this->version($delivery, $expectedDeliveryVersion);
            if ((int) $delivery['anulada'] !== 0 || !in_array($dispatch['estado'], ['EN_TRANSITO', 'EN_RESOLUCION'], true)) {
                $this->fail('INVALID_STATE', 'Solo una entrega vigente de un despacho abierto puede anularse; un cierre exige corrección explícita.');
            }
            if ($delivery['recepcion_ref'] !== null
                || $this->execute('SELECT id FROM logistica_incidencias WHERE entrega_id=? ORDER BY id LIMIT 1 LOCK IN SHARE MODE', [$deliveryId])->fetchColumn() !== false
                || $this->execute('SELECT r.id FROM logistica_incidencia_resoluciones r JOIN logistica_entrega_detalle ed ON ed.id=r.entrega_detalle_id WHERE ed.entrega_id=? ORDER BY r.id LIMIT 1 LOCK IN SHARE MODE', [$deliveryId])->fetchColumn() !== false) {
                $this->fail('REFERENCE_CONFLICT', 'El intento tiene incidencias, resoluciones o recepción externa dependientes; requiere corrección coordinada.');
            }
            $balances = $this->dispatchBalances($dispatchId, $deliveryId);
            if ($this->operationalState($dispatchId, $balances) !== $resultingState) {
                $this->fail('INVALID_STATE', 'El estado de la corrección no corresponde al balance e incidencias.');
            }
            $statement = $this->execute('UPDATE logistica_entregas SET anulada=1,version=version+1,anulada_by=?,anulada_at=UTC_TIMESTAMP(6),motivo_anulacion=? WHERE id=? AND sucursal_id=? AND version=? AND anulada=0', [$ctx['actor_id'], $reason, $deliveryId, $ctx['sucursal_id'], $expectedDeliveryVersion]);
            if ($statement->rowCount() !== 1) {
                $this->fail('CONFLICT', 'La entrega fue modificada por otra operación.');
            }
            $this->updateHeader('logistica_despachos', $dispatchId, $expectedDispatchVersion, ['estado' => $resultingState], $ctx['actor_id']);
            $this->event($ctx, $operationId, 'VOID_DELIVERY', (int) $dispatch['preparacion_id'], $dispatchId, $deliveryId, $dispatch['estado'], $resultingState, $reason);
            return $this->deliveryResult($deliveryId, true, $expectedDeliveryVersion + 1, $dispatchId, $resultingState, $expectedDispatchVersion + 1);
        });
    }

    public function findPreparation(int $id, int $branchId): ?array
    {
        return $this->findAggregate('logistica_preparaciones', 'logistica_preparacion_detalle', 'preparacion_id', $id, $branchId);
    }

    public function createIncident(int $dispatchId, int $expectedDispatchVersion, array $incident, array $context): array
    {
        $this->ids($dispatchId, $expectedDispatchVersion);
        $incident = $this->incidentInput($incident);
        return $this->command('CREATE_INCIDENT', compact('dispatchId', 'expectedDispatchVersion', 'incident'), $context,
            function (array $ctx, int $operationId) use ($dispatchId, $expectedDispatchVersion, $incident): array {
                $dispatch = $this->locked('logistica_despachos', $dispatchId, $ctx['sucursal_id']);
                $this->version($dispatch, $expectedDispatchVersion);
                $this->openPhysicalDispatch($dispatch);
                $balances = $this->dispatchBalances($dispatchId);
                $lineId = $incident['despacho_detalle_id'];
                if ($lineId !== null && !isset($balances[$lineId])) {
                    $this->fail('REFERENCE_CONFLICT', 'La incidencia corresponde a otra línea de despacho.');
                }
                if ($incident['modo'] === 'CUANTITATIVA' && LogisticsQuantity::toMillis($incident['cantidad']) > $balances[$lineId]['free']) {
                    $this->fail('QUANTITY_EXCEEDED', 'La incidencia supera el saldo pendiente libre; agregue causas al caso existente.');
                }
                if ($incident['modo'] === 'INFORMATIVA' && $incident['cantidad'] !== null
                    && LogisticsQuantity::toMillis($incident['cantidad']) > $balances[$lineId]['delivered']) {
                    $this->fail('QUANTITY_EXCEEDED', 'La cantidad informativa supera lo ya aceptado en esa línea.');
                }
                if ($incident['entrega_id'] !== null) {
                    $delivery = $this->locked('logistica_entregas', $incident['entrega_id'], $ctx['sucursal_id'], false);
                    if ((int) $delivery['despacho_id'] !== $dispatchId || (int) $delivery['anulada'] !== 0) {
                        $this->fail('REFERENCE_CONFLICT', 'El intento de origen no pertenece al despacho o está anulado.');
                    }
                }
                $incident['responsable_id'] ??= $ctx['actor_id'];
                $id = $this->insertIncident($dispatch, $incident, $ctx, $operationId);
                $state = $this->operationalState($dispatchId, $this->dispatchBalances($dispatchId), false);
                $this->updateHeader('logistica_despachos', $dispatchId, $expectedDispatchVersion, ['estado' => $state], $ctx['actor_id']);
                return $this->incidentResult($id, false, 1, $dispatchId, $state, $expectedDispatchVersion + 1);
            });
    }

    public function resolveIncident(int $incidentId, int $expectedIncidentVersion, int $expectedDispatchVersion, array $resolution, array $context): array
    {
        $this->ids($incidentId, $expectedIncidentVersion, $expectedDispatchVersion);
        $resolution = $this->resolutionInput($resolution);
        return $this->command('RESOLVE_INCIDENT', compact('incidentId', 'expectedIncidentVersion', 'expectedDispatchVersion', 'resolution'), $context,
            function (array $ctx, int $operationId) use ($incidentId, $expectedIncidentVersion, $expectedDispatchVersion, $resolution): array {
                $dispatchId = $this->execute('SELECT despacho_id FROM logistica_incidencias WHERE id=? AND sucursal_id=? LOCK IN SHARE MODE', [$incidentId, $ctx['sucursal_id']])->fetchColumn();
                if ($dispatchId === false) {
                    $this->fail('NOT_FOUND', 'Incidencia no encontrada en la sede.');
                }
                $dispatchId = (int) $dispatchId;
                $dispatch = $this->locked('logistica_despachos', $dispatchId, $ctx['sucursal_id']);
                $this->version($dispatch, $expectedDispatchVersion);
                $this->openPhysicalDispatch($dispatch);
                $cases = $this->lockedCases($dispatchId);
                $case = $cases[$incidentId];
                $this->version($case, $expectedIncidentVersion);
                if ($case['resuelta_at'] !== null) {
                    $this->fail('INVALID_STATE', 'La incidencia ya está resuelta.');
                }
                $balances = $this->dispatchBalances($dispatchId);
                if ($resolution['tipo'] === 'RESOLUCION_OPERATIVA') {
                    if ($case['modo'] !== 'INFORMATIVA') {
                        $this->fail('INVALID_STATE', 'Una solución operativa no resuelve mercadería pendiente.');
                    }
                    $resolved = true;
                } else {
                    if ($case['modo'] !== 'CUANTITATIVA' || $case['despacho_detalle_id'] === null) {
                        $this->fail('INVALID_STATE', 'El hecho físico requiere una incidencia cuantitativa.');
                    }
                    $quantity = LogisticsQuantity::toMillis($resolution['cantidad']);
                    if ($quantity > $case['remaining']) {
                        $this->fail('QUANTITY_EXCEEDED', 'La resolución supera el saldo de la incidencia.');
                    }
                    $line = $balances[(int) $case['despacho_detalle_id']]['line'];
                    $fact = $this->confirmedInventoryFact($resolution['confirmacion'], $resolution['tipo'], $resolution['cantidad'], $dispatch, $case, $line);
                    $resolution += [
                        'confirmacion_sistema' => $fact['sistema'], 'confirmacion_id' => $fact['id'],
                        'confirmacion_snapshot' => $this->json($fact), 'autorizacion_ref' => $fact['autorizacion_ref'] ?? null,
                    ];
                    $case['remaining'] -= $quantity;
                    $resolved = $case['remaining'] === 0;
                }
                unset($resolution['confirmacion']);
                $resolutionId = $this->insertResolution($case, $resolution, $ctx);
                $this->touchCase($case, $ctx, $resolved);
                $balances = $this->dispatchBalances($dispatchId);
                $state = $this->operationalState($dispatchId, $balances, false);
                $this->updateHeader('logistica_despachos', $dispatchId, $expectedDispatchVersion, ['estado' => $state], $ctx['actor_id']);
                $this->caseEvent($ctx, $operationId, 'RESOLVE_INCIDENT', $dispatch, $incidentId, $resolutionId, null,
                    ['tipo' => $resolution['tipo'], 'cantidad' => $resolution['cantidad'], 'balances' => $this->balanceSnapshot($balances)], $state);
                return $this->incidentResult($incidentId, $resolved, $expectedIncidentVersion + 1, $dispatchId, $state, $expectedDispatchVersion + 1)
                    + ['resolucion_id' => $resolutionId];
            });
    }

    public function closeDispatch(int $dispatchId, int $expectedVersion, string $expectedState, string $reason, array $context): array
    {
        $this->ids($dispatchId, $expectedVersion);
        $this->enum($expectedState, ['EN_TRANSITO', 'EN_RESOLUCION']);
        $reason = $this->text($reason, 'reason', 65535);
        return $this->command('CLOSE_DISPATCH', compact('dispatchId', 'expectedVersion', 'expectedState', 'reason'), $context,
            function (array $ctx, int $operationId) use ($dispatchId, $expectedVersion, $expectedState, $reason): array {
                $dispatch = $this->locked('logistica_despachos', $dispatchId, $ctx['sucursal_id']);
                $this->version($dispatch, $expectedVersion);
                $this->state($dispatch, $expectedState);
                $this->openPhysicalDispatch($dispatch);
                $balances = $this->dispatchBalances($dispatchId);
                $state = $this->assertCloseable($dispatchId, $balances);
                $this->updateHeader('logistica_despachos', $dispatchId, $expectedVersion,
                    ['estado' => $state, 'cerrado_at' => $this->utcNow(), 'cerrado_by' => $ctx['actor_id']], $ctx['actor_id']);
                $this->event($ctx, $operationId, 'CLOSE_DISPATCH', (int) $dispatch['preparacion_id'], $dispatchId, null,
                    $expectedState, $state, $reason, ['balances' => $this->balanceSnapshot($balances), 'incidencias_bloqueantes_pendientes' => 0]);
                return $this->headerResult($dispatchId, $state, $expectedVersion + 1);
            });
    }

    public function recordIncidentAction(int $incidentId, int $expectedIncidentVersion, int $expectedDispatchVersion, array $action, array $context): array
    {
        $this->ids($incidentId, $expectedIncidentVersion, $expectedDispatchVersion);
        $this->keys($action, ['tipo', 'causas', 'motivo', 'evidencia']);
        $type = $action['tipo'] ?? '';
        if (!is_string($type)) {
            $this->fail('INVALID_INPUT', 'Tipo de acción inválido.');
        }
        $this->enum($type, ['AGREGAR_CAUSAS', 'SOLICITAR_DEVOLUCION', 'REPROGRAMAR', 'INVESTIGAR']);
        $action = ['tipo' => $type, 'causas' => array_key_exists('causas', $action) ? $this->causes($action['causas']) : [],
            'motivo' => $this->text($action['motivo'] ?? null, 'motivo', 65535),
            'evidencia' => $this->evidence($action['evidencia'] ?? null)];
        if ($type === 'AGREGAR_CAUSAS' && $action['causas'] === []) {
            $this->fail('INVALID_INPUT', 'Agregar causas requiere al menos una causa.');
        }
        return $this->command('RECORD_INCIDENT_ACTION', compact('incidentId', 'expectedIncidentVersion', 'expectedDispatchVersion', 'action'), $context,
            function (array $ctx, int $operationId) use ($incidentId, $expectedIncidentVersion, $expectedDispatchVersion, $action): array {
                $dispatchId = $this->execute('SELECT despacho_id FROM logistica_incidencias WHERE id=? AND sucursal_id=? LOCK IN SHARE MODE', [$incidentId, $ctx['sucursal_id']])->fetchColumn();
                if ($dispatchId === false) {
                    $this->fail('NOT_FOUND', 'Incidencia no encontrada en la sede.');
                }
                $dispatch = $this->locked('logistica_despachos', (int) $dispatchId, $ctx['sucursal_id']);
                $this->version($dispatch, $expectedDispatchVersion);
                $this->openPhysicalDispatch($dispatch);
                $case = $this->locked('logistica_incidencias', $incidentId, $ctx['sucursal_id']);
                $this->version($case, $expectedIncidentVersion);
                if ($case['resuelta_at'] !== null) {
                    $this->fail('INVALID_STATE', 'La acción requiere una incidencia pendiente.');
                }
                $causes = $this->causes([...json_decode($case['causas_json'], true, 512, JSON_THROW_ON_ERROR), ...$action['causas']]);
                $this->updateHeader('logistica_incidencias', $incidentId, $expectedIncidentVersion, ['causas_json' => $this->json($causes), 'bloqueante' => $causes === ['RETRASO'] ? 0 : 1], $ctx['actor_id']);
                $state = $this->operationalState((int) $dispatchId, $this->dispatchBalances((int) $dispatchId), false);
                $this->updateHeader('logistica_despachos', (int) $dispatchId, $expectedDispatchVersion, ['estado' => $state], $ctx['actor_id']);
                $this->caseEvent($ctx, $operationId, 'RECORD_INCIDENT_ACTION', $dispatch, $incidentId, null, null, $action + ['causas_resultantes' => $causes], $state);
                return $this->incidentResult($incidentId, false, $expectedIncidentVersion + 1, (int) $dispatchId, $state, $expectedDispatchVersion + 1);
            });
    }

    public function findIncident(int $id, int $branchId): ?array
    {
        $this->ids($id, $branchId);
        return $this->transaction(function () use ($id, $branchId): ?array {
            $case = $this->readHeader('logistica_incidencias', $id, $branchId);
            if ($case === null) {
                return null;
            }
            $resolutions = $this->execute('SELECT * FROM logistica_incidencia_resoluciones WHERE incidencia_id=? AND sucursal_id=? ORDER BY id LOCK IN SHARE MODE', [$id, $branchId])->fetchAll(PDO::FETCH_ASSOC);
            $resolved = 0;
            foreach ($resolutions as &$resolution) {
                $resolution = $this->typedRow($resolution);
                $resolution['evidencia'] = json_decode($resolution['evidencia_json'], true, 512, JSON_THROW_ON_ERROR);
                $resolution['confirmacion'] = $resolution['confirmacion_snapshot'] === null ? null : json_decode($resolution['confirmacion_snapshot'], true, 512, JSON_THROW_ON_ERROR);
                if ($resolution['cantidad'] !== null) {
                    $resolved += LogisticsQuantity::toMillis($resolution['cantidad']);
                }
            }
            unset($resolution);
            $case = $this->typedRow($case);
            $case['causas'] = json_decode($case['causas_json'], true, 512, JSON_THROW_ON_ERROR);
            $case['evidencia'] = json_decode($case['evidencia_json'], true, 512, JSON_THROW_ON_ERROR);
            $case['resoluciones'] = $resolutions;
            $case['estado'] = $case['resuelta_at'] === null ? 'ABIERTA' : 'RESUELTA';
            $case['cantidad_resuelta'] = $case['modo'] === 'CUANTITATIVA' ? LogisticsQuantity::fromMillis($resolved) : null;
            $case['saldo'] = $case['modo'] === 'CUANTITATIVA' ? LogisticsQuantity::fromMillis(LogisticsQuantity::toMillis($case['cantidad']) - $resolved) : null;
            return $case;
        });
    }

    public function listIncidents(int $dispatchId, int $branchId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $this->ids($dispatchId, $branchId);
        $this->pagination($limit, $offset);
        $this->keys($filters, ['estado', 'tipo']);
        $where = 'despacho_id=? AND sucursal_id=?';
        $parameters = [$dispatchId, $branchId];
        if (isset($filters['estado'])) {
            if (!is_string($filters['estado'])) {
                $this->fail('INVALID_INPUT', 'Estado de filtro de incidencia inválido.');
            }
            $this->enum($filters['estado'], ['ABIERTA', 'RESUELTA']);
            $where .= $filters['estado'] === 'ABIERTA' ? ' AND resuelta_at IS NULL' : ' AND resuelta_at IS NOT NULL';
        }
        if (isset($filters['tipo'])) {
            if (!is_string($filters['tipo'])) {
                $this->fail('INVALID_INPUT', 'Tipo de filtro de incidencia inválido.');
            }
            $this->enum($filters['tipo'], self::INCIDENT_TYPES);
            $where .= ' AND tipo=?';
            $parameters[] = $filters['tipo'];
        }
        return $this->transaction(function () use ($where, $parameters, $branchId, $limit, $offset): array {
            $ids = $this->execute("SELECT id FROM logistica_incidencias WHERE $where ORDER BY id LIMIT $limit OFFSET $offset LOCK IN SHARE MODE", $parameters)->fetchAll(PDO::FETCH_COLUMN);
            return array_map(fn(mixed $id): array => $this->findIncident((int) $id, $branchId), $ids);
        });
    }

    public function findDispatch(int $id, int $branchId): ?array
    {
        return $this->findAggregate('logistica_despachos', 'logistica_despacho_detalle', 'despacho_id', $id, $branchId);
    }

    public function findDelivery(int $id, int $branchId): ?array
    {
        return $this->findAggregate('logistica_entregas', 'logistica_entrega_detalle', 'entrega_id', $id, $branchId);
    }

    public function listPreparations(int $branchId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->listing('logistica_preparaciones', $branchId, $filters, $limit, $offset,
            ['estado', 'fecha_desde', 'fecha_hasta', 'origen_ref', 'cliente_id', 'almacen_id'], self::PREPARATION_STATES, 'findPreparation');
    }

    public function listDispatches(int $branchId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->listing('logistica_despachos', $branchId, $filters, $limit, $offset,
            ['estado', 'fecha_desde', 'fecha_hasta', 'preparacion_id', 'guia_id'], self::DISPATCH_STATES, 'findDispatch');
    }

    public function listDeliveries(int $dispatchId, int $branchId, int $limit = 50, int $offset = 0): array
    {
        $this->ids($dispatchId, $branchId);
        $this->pagination($limit, $offset);
        return $this->transaction(function () use ($dispatchId, $branchId, $limit, $offset): array {
            if ($this->readHeader('logistica_despachos', $dispatchId, $branchId) === null) {
                return [];
            }
            $ids = $this->execute("SELECT id FROM logistica_entregas WHERE despacho_id=? AND sucursal_id=? ORDER BY id DESC LIMIT $limit OFFSET $offset LOCK IN SHARE MODE", [$dispatchId, $branchId])->fetchAll(PDO::FETCH_COLUMN);
            return array_map(fn(mixed $id): array => $this->findDelivery((int) $id, $branchId), $ids);
        });
    }

    public function history(string $aggregateType, int $id, int $branchId, int $limit = 100, int $offset = 0): array
    {
        $this->ids($id, $branchId);
        $this->pagination($limit, $offset);
        $columns = ['preparacion' => 'preparacion_id', 'despacho' => 'despacho_id', 'entrega' => 'entrega_id', 'incidencia' => 'incidencia_id'];
        if (!isset($columns[$aggregateType])) {
            $this->fail('INVALID_INPUT', 'Tipo de agregado no admitido.');
        }
        $column = $columns[$aggregateType];
        return $this->transaction(function () use ($column, $branchId, $id, $limit, $offset): array {
            $rows = $this->execute("SELECT * FROM logistica_historial WHERE sucursal_id=? AND $column=? ORDER BY id ASC LIMIT $limit OFFSET $offset LOCK IN SHARE MODE", [$branchId, $id])->fetchAll(PDO::FETCH_ASSOC);
            return array_map(fn(array $row): array => $this->typedRow($row), $rows);
        });
    }

    /** Command row serialization works across PHP workers/connections and caller transactions. */
    private function command(string $action, array $payload, array $context, callable $operation): array
    {
        $context = $this->context($context);
        $hash = hash('sha256', $this->json($this->canonical(['action' => $action, 'payload' => $payload, 'context' => $context])));
        return $this->transaction(function () use ($action, $payload, $context, $hash, $operation): array {
            try {
                $operationId = $this->insert('logistica_operaciones', [
                    'sucursal_id' => $context['sucursal_id'], 'clave' => $context['idempotency_key'],
                    'accion' => $action, 'actor_id' => $context['actor_id'], 'request_hash' => $hash,
                ]);
            } catch (PDOException $duplicate) {
                // Handle only the uniqueness conflict of this exact operation key. No other
                // INSERT is covered by this catch; unrelated duplicate/FK errors cannot replay.
                if ((int) ($duplicate->errorInfo[1] ?? 0) !== 1062) {
                    throw $this->constraintFailure($duplicate);
                }
                // Duplicate INSERT may already hold a shared index lock. Results are
                // immutable, so retain a current shared read instead of upgrading to
                // exclusive and deadlocking with another simultaneous replay.
                $stored = $this->execute('SELECT * FROM logistica_operaciones WHERE sucursal_id=? AND clave=? LOCK IN SHARE MODE', [$context['sucursal_id'], $context['idempotency_key']])->fetch(PDO::FETCH_ASSOC);
                if (!$stored) {
                    throw $duplicate;
                }
                if ($stored['accion'] !== $action || (int) $stored['actor_id'] !== $context['actor_id'] || !hash_equals($stored['request_hash'], $hash)) {
                    $this->fail('IDEMPOTENCY_CONFLICT', 'La clave ya corresponde a otro comando, actor o contenido.');
                }
                if ($stored['result_json'] === null) {
                    $this->fail('CONFLICT', 'La operación todavía no tiene un resultado confirmado.');
                }
                $result = json_decode($stored['result_json'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($result)) {
                    $this->fail('CONFLICT', 'El resultado persistido de la operación es inválido.');
                }
                return $result;
            }
            try {
                $result = $operation($context, $operationId);
                $this->execute('UPDATE logistica_operaciones SET result_json=? WHERE id=? AND result_json IS NULL', [$this->json($result), $operationId]);
                return $result;
            } catch (PDOException $constraint) {
                throw $this->constraintFailure($constraint);
            }
        });
    }

    private function constraintFailure(PDOException $failure): Throwable
    {
        $driverCode = (int) ($failure->errorInfo[1] ?? 0);
        if (in_array($driverCode, [1451, 1452, 3819, 4025], true)) {
            return new LogisticsPersistenceException('REFERENCE_CONFLICT', 'Una referencia o restricción de persistencia es inválida.', $failure);
        }
        if ($driverCode === 1062) {
            return new LogisticsPersistenceException('CONFLICT', 'La entidad o relación ya existe.', $failure);
        }
        if (in_array($driverCode, [1205, 1213], true)) {
            return new LogisticsPersistenceException('CONFLICT', 'La operación concurrió con otra transacción; el llamador debe reintentar su transacción completa.', $failure);
        }
        return $failure;
    }

    private function containsDriverCode(Throwable $failure, int $driverCode): bool
    {
        do {
            if ($failure instanceof PDOException && (int) ($failure->errorInfo[1] ?? 0) === $driverCode) {
                return true;
            }
            $failure = $failure->getPrevious();
        } while ($failure !== null);
        return false;
    }

    private function context(array $context): array
    {
        $this->keys($context, ['sucursal_id', 'actor_id', 'idempotency_key']);
        $key = $context['idempotency_key'] ?? null;
        if (!is_string($key) || !preg_match('/\A[\x21-\x7e]{1,128}\z/D', $key)) {
            $this->fail('INVALID_INPUT', 'idempotency_key debe tener de 1 a 128 caracteres ASCII visibles.');
        }
        return ['sucursal_id' => $this->id($context['sucursal_id'] ?? null), 'actor_id' => $this->id($context['actor_id'] ?? null), 'idempotency_key' => $key];
    }

    private function preparationHeader(array $header): array
    {
        $this->keys($header, ['almacen_id', 'tipo_origen', 'origen_ref', 'fecha', 'destino', 'cliente_id', 'almacen_destino_id', 'reserva_ref']);
        $type = $header['tipo_origen'] ?? null;
        if (!is_string($type)) {
            $this->fail('INVALID_INPUT', 'tipo_origen es obligatorio.');
        }
        $this->enum($type, ['VENTA', 'TRASLADO']);
        $result = [
            'almacen_id' => $this->id($header['almacen_id'] ?? null), 'tipo_origen' => $type,
            'origen_ref' => $this->text($header['origen_ref'] ?? null, 'origen_ref', 128),
            'fecha' => $this->date($header['fecha'] ?? null),
            'destino' => $this->text($header['destino'] ?? null, 'destino', 500),
            'cliente_id' => $this->nullableId($header['cliente_id'] ?? null),
            'almacen_destino_id' => $this->nullableId($header['almacen_destino_id'] ?? null),
            'reserva_ref' => $this->text($header['reserva_ref'] ?? null, 'reserva_ref', 128),
        ];
        if ($type === 'VENTA' && ($result['cliente_id'] === null || $result['almacen_destino_id'] !== null)) {
            $this->fail('INVALID_INPUT', 'VENTA requiere cliente y no un almacén destino.');
        }
        if ($type === 'TRASLADO' && ($result['almacen_destino_id'] === null || $result['almacen_destino_id'] === $result['almacen_id'])) {
            $this->fail('INVALID_INPUT', 'TRASLADO requiere un almacén destino diferente del origen.');
        }
        return $result;
    }

    private function preparationDetails(array $details): array
    {
        if ($details === [] || !array_is_list($details)) {
            $this->fail('INVALID_INPUT', 'Se requiere una lista no vacía de detalles.');
        }
        $normalized = [];
        $identities = [];
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                $this->fail('INVALID_INPUT', 'Detalle inválido.');
            }
            $this->keys($detail, ['origen_linea_ref', 'producto_id', 'unidad_id', 'lote_id', 'cantidad']);
            $row = [
                'origen_linea_ref' => $this->text($detail['origen_linea_ref'] ?? null, 'origen_linea_ref', 128),
                'producto_id' => $this->id($detail['producto_id'] ?? null), 'unidad_id' => $this->id($detail['unidad_id'] ?? null),
                'lote_id' => $this->nullableId($detail['lote_id'] ?? null), 'cantidad' => LogisticsQuantity::normalize($detail['cantidad'] ?? null),
            ];
            $identity = $this->json([$row['origen_linea_ref'], $row['producto_id'], $row['unidad_id'], $row['lote_id']]);
            if (isset($identities[$identity])) {
                $this->fail('INVALID_INPUT', 'La identidad de una línea se repite.');
            }
            $identities[$identity] = true;
            $normalized[$identity] = $row;
        }
        ksort($normalized, SORT_STRING);
        return array_values($normalized);
    }

    private function dispatchHeader(array $header): array
    {
        $this->keys($header, ['fecha', 'guia_id', 'guia_version']);
        $guideId = $this->nullableId($header['guia_id'] ?? null);
        $guideVersion = $this->nullableId($header['guia_version'] ?? null);
        if (($guideId === null) !== ($guideVersion === null)) {
            $this->fail('INVALID_INPUT', 'guia_id y guia_version deben enviarse juntos.');
        }
        return ['fecha' => $this->date($header['fecha'] ?? null), 'guia_id' => $guideId, 'guia_version' => $guideVersion];
    }

    private function dispatchReferences(array $references): array
    {
        $this->keys($references, self::REFERENCES);
        return [
            'movimiento_salida_ref' => $this->nullableText($references['movimiento_salida_ref'] ?? null, 'movimiento_salida_ref', 128),
            'motivo' => $this->nullableText($references['motivo'] ?? null, 'motivo', 65535),
            'resolucion_ref' => $this->nullableText($references['resolucion_ref'] ?? null, 'resolucion_ref', 128),
        ];
    }

    private function deliveryHeader(array $header): array
    {
        $this->keys($header, ['fecha', 'recibido_por', 'observaciones', 'recepcion_ref', 'resultado', 'motivo_fallo', 'tipo_fallo', 'fecha_operativa', 'incidencias']);
        $date = $this->date($header['fecha'] ?? null);
        $result = $header['resultado'] ?? null;
        if ($result !== null) {
            if (!is_string($result)) {
                $this->fail('INVALID_INPUT', 'Resultado del intento inválido.');
            }
            $this->enum($result, ['ACEPTADA', 'PARCIAL', 'RECHAZADA', 'FALLIDA']);
        }
        $failureType = $header['tipo_fallo'] ?? null;
        if ($failureType !== null) {
            if (!is_string($failureType)) {
                $this->fail('INVALID_INPUT', 'Tipo de fallo inválido.');
            }
            $this->enum($failureType, self::INCIDENT_TYPES);
        }
        $incidents = $header['incidencias'] ?? [];
        if (!is_array($incidents) || !array_is_list($incidents)) {
            $this->fail('INVALID_INPUT', 'Las incidencias del intento deben ser una lista.');
        }
        $normalizedIncidents = [];
        foreach ($incidents as $incident) {
            if (!is_array($incident)) {
                $this->fail('INVALID_INPUT', 'Incidencia del intento inválida.');
            }
            $incident = $this->incidentInput($incident);
            if ($incident['modo'] !== 'INFORMATIVA' || $incident['entrega_id'] !== null || $incident['cantidad'] !== null) {
                $this->fail('INVALID_INPUT', 'La cabecera admite incidencias cualitativas; las cantidades informativas se adjuntan al detalle aceptado.');
            }
            $normalizedIncidents[] = $incident;
        }
        return [
            'fecha' => $date, 'recibido_por' => $this->nullableText($header['recibido_por'] ?? null, 'recibido_por', 200),
            'observaciones' => $this->nullableText($header['observaciones'] ?? null, 'observaciones', 65535),
            'recepcion_ref' => $this->nullableText($header['recepcion_ref'] ?? null, 'recepcion_ref', 128),
            'resultado' => $result,
            'motivo_fallo' => $this->nullableText($header['motivo_fallo'] ?? null, 'motivo_fallo', 65535),
            'tipo_fallo' => $failureType,
            'fecha_operativa' => $this->dateTime($header['fecha_operativa'] ?? ($date . ' 00:00:00.000000')),
            'incidencias' => $normalizedIncidents,
        ];
    }

    private function deliveryDetails(array $details): array
    {
        if (!array_is_list($details)) {
            $this->fail('INVALID_INPUT', 'La entrega requiere una lista de líneas.');
        }
        $normalized = [];
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                $this->fail('INVALID_INPUT', 'Detalle de entrega inválido.');
            }
            $this->keys($detail, ['despacho_detalle_id', 'cantidad', 'cantidad_rechazada', 'aceptaciones_incidencias', 'rechazos_incidencias', 'motivo_rechazo', 'causas_rechazo', 'incidencias_informativas']);
            $id = $this->id($detail['despacho_detalle_id'] ?? null);
            if (isset($normalized[$id])) {
                $this->fail('INVALID_INPUT', 'La línea de despacho se repite en la entrega.');
            }
            $accepted = LogisticsQuantity::normalize($detail['cantidad'] ?? '0', true);
            $rejected = LogisticsQuantity::normalize($detail['cantidad_rechazada'] ?? '0', true);
            if ($accepted === '0.000' && $rejected === '0.000') {
                $this->fail('INVALID_INPUT', 'Una línea debe tener cantidad aceptada o rechazada positiva.');
            }
            $reason = $this->nullableText($detail['motivo_rechazo'] ?? null, 'motivo_rechazo', 65535);
            if ($rejected !== '0.000' && $reason === null) {
                $this->fail('INVALID_INPUT', 'El rechazo requiere un motivo.');
            }
            $informative = $detail['incidencias_informativas'] ?? [];
            if (!is_array($informative) || !array_is_list($informative)) {
                $this->fail('INVALID_INPUT', 'Los reclamos del detalle deben ser una lista.');
            }
            $normalizedInformative = [];
            foreach ($informative as $incident) {
                if (!is_array($incident)) {
                    $this->fail('INVALID_INPUT', 'Reclamo informativo inválido.');
                }
                $this->keys($incident, ['tipo', 'cantidad', 'causas', 'motivo', 'evidencia', 'responsable_id']);
                $incident = $this->incidentInput($incident + ['despacho_detalle_id' => $id, 'modo' => 'INFORMATIVA']);
                if ($incident['cantidad'] !== null && LogisticsQuantity::toMillis($incident['cantidad']) > LogisticsQuantity::toMillis($accepted, true)) {
                    $this->fail('QUANTITY_EXCEEDED', 'El reclamo informativo supera la cantidad aceptada en este detalle del intento.');
                }
                if ($accepted === '0.000') {
                    $this->fail('INVALID_INPUT', 'Un reclamo de mercadería aceptada requiere aceptación positiva en el detalle.');
                }
                $normalizedInformative[] = $incident;
            }
            $normalized[$id] = [
                'despacho_detalle_id' => $id, 'cantidad' => $accepted, 'cantidad_rechazada' => $rejected,
                'aceptaciones_incidencias' => $this->allocations($detail['aceptaciones_incidencias'] ?? []),
                'rechazos_incidencias' => $this->allocations($detail['rechazos_incidencias'] ?? []),
                'motivo_rechazo' => $reason, 'causas_rechazo' => $this->causes([...$this->causes(array_key_exists('causas_rechazo', $detail) ? $detail['causas_rechazo'] : ['RECHAZO']), 'RECHAZO']),
                'incidencias_informativas' => $normalizedInformative,
            ];
        }
        ksort($normalized, SORT_NUMERIC);
        return array_values($normalized);
    }

    private function allocations(mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input)) {
            $this->fail('INVALID_INPUT', 'Las asignaciones deben ser una lista.');
        }
        $result = [];
        foreach ($input as $allocation) {
            if (!is_array($allocation)) {
                $this->fail('INVALID_INPUT', 'Asignación inválida.');
            }
            $this->keys($allocation, ['incidencia_id', 'cantidad']);
            $id = $this->id($allocation['incidencia_id'] ?? null);
            if (isset($result[$id])) {
                $this->fail('INVALID_INPUT', 'La incidencia se repite en una asignación.');
            }
            $result[$id] = ['incidencia_id' => $id, 'cantidad' => LogisticsQuantity::normalize($allocation['cantidad'] ?? null)];
        }
        ksort($result, SORT_NUMERIC);
        return array_values($result);
    }

    private function causes(mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input) || $input === []) {
            $this->fail('INVALID_INPUT', 'Se requiere al menos una causa estructurada.');
        }
        foreach ($input as $cause) {
            if (!is_string($cause)) {
                $this->fail('INVALID_INPUT', 'Causa inválida.');
            }
            $this->enum($cause, self::INCIDENT_TYPES);
        }
        $input = array_values(array_unique($input));
        sort($input, SORT_STRING);
        return $input;
    }

    private function evidence(mixed $input): array
    {
        if (!is_array($input) || $input === []) {
            $this->fail('INVALID_INPUT', 'Se requiere evidencia estructurada no vacía.');
        }
        $this->json($input);
        return $this->canonical($input);
    }

    private function incidentInput(array $input): array
    {
        $this->keys($input, ['despacho_detalle_id', 'entrega_id', 'tipo', 'modo', 'cantidad', 'causas', 'motivo', 'evidencia', 'responsable_id']);
        $type = $input['tipo'] ?? '';
        $mode = $input['modo'] ?? '';
        if (!is_string($type) || !is_string($mode)) {
            $this->fail('INVALID_INPUT', 'Tipo y modo de incidencia inválidos.');
        }
        $this->enum($type, self::INCIDENT_TYPES);
        $this->enum($mode, ['CUANTITATIVA', 'INFORMATIVA']);
        $lineId = $this->nullableId($input['despacho_detalle_id'] ?? null);
        $quantity = null;
        if ($mode === 'CUANTITATIVA') {
            if ($lineId === null || !in_array($type, ['FALTANTE', 'RECHAZO', 'DANO', 'PERDIDA_EN_INVESTIGACION'], true)) {
                $this->fail('INVALID_INPUT', 'El caso cuantitativo requiere una línea y un problema de mercadería.');
            }
            $quantity = LogisticsQuantity::normalize($input['cantidad'] ?? null);
        } elseif (($input['cantidad'] ?? null) !== null) {
            if ($lineId === null) {
                $this->fail('INVALID_INPUT', 'Una cantidad afectada informativa requiere identificar su línea aceptada.');
            }
            $quantity = LogisticsQuantity::normalize($input['cantidad']);
        }
        $causes = array_key_exists('causas', $input) ? $this->causes($input['causas']) : [$type];
        $causes = $this->causes([...$causes, $type]);
        return [
            'despacho_detalle_id' => $lineId, 'entrega_id' => $this->nullableId($input['entrega_id'] ?? null),
            'tipo' => $type, 'modo' => $mode, 'cantidad' => $quantity,
            'causas_json' => $this->json($causes),
            'motivo' => $this->text($input['motivo'] ?? null, 'motivo', 65535),
            'evidencia_json' => $this->json($this->evidence($input['evidencia'] ?? null)),
            'responsable_id' => $this->nullableId($input['responsable_id'] ?? null),
            // Contract classification is server-owned. The client cannot force a nonblocking case.
            'bloqueante' => $causes === ['RETRASO'] ? 0 : 1,
        ];
    }

    private function resolutionInput(array $input): array
    {
        $this->keys($input, ['tipo', 'cantidad', 'motivo', 'evidencia', 'confirmacion', 'fecha_operativa']);
        $type = $input['tipo'] ?? '';
        if (!is_string($type)) {
            $this->fail('INVALID_INPUT', 'Tipo de resolución inválido.');
        }
        $this->enum($type, ['RETORNO_CONFIRMADO', 'PERDIDA_CONFIRMADA', 'DISPOSICION_FINAL_CONFIRMADA', 'RESOLUCION_OPERATIVA']);
        $reference = null;
        if ($type !== 'RESOLUCION_OPERATIVA') {
            $reference = $input['confirmacion'] ?? null;
            if (!is_array($reference)) {
                $this->fail('INVALID_INPUT', 'Se requiere la identidad granular de confirmación de Inventarios.');
            }
            $this->keys($reference, ['sistema', 'id']);
            $reference = ['sistema' => $this->asciiReference($reference['sistema'] ?? null, 'sistema', 60), 'id' => $this->asciiReference($reference['id'] ?? null, 'id', 128)];
        } elseif (($input['cantidad'] ?? null) !== null || ($input['confirmacion'] ?? null) !== null) {
            $this->fail('INVALID_INPUT', 'La resolución operativa no produce un destino final de cantidad.');
        }
        return [
            'tipo' => $type, 'cantidad' => $type === 'RESOLUCION_OPERATIVA' ? null : LogisticsQuantity::normalize($input['cantidad'] ?? null),
            'motivo' => $this->text($input['motivo'] ?? null, 'motivo', 65535),
            'evidencia_json' => $this->json($this->evidence($input['evidencia'] ?? null)),
            'confirmacion' => $reference, 'fecha_operativa' => isset($input['fecha_operativa']) ? $this->dateTime($input['fecha_operativa']) : null,
        ];
    }

    private function attemptResult(array $header, array $details): string
    {
        if ($details === []) {
            if ($header['resultado'] !== 'FALLIDA' || $header['motivo_fallo'] === null || $header['tipo_fallo'] === null || $header['observaciones'] === null || $header['recepcion_ref'] !== null) {
                $this->fail('INVALID_INPUT', 'FALLIDA sin líneas requiere tipo de fallo, motivo y contexto del intento, sin recepción de stock.');
            }
            return 'FALLIDA';
        }
        $accepted = false;
        $rejected = false;
        foreach ($details as $detail) {
            $accepted = $accepted || $detail['cantidad'] !== '0.000';
            $rejected = $rejected || $detail['cantidad_rechazada'] !== '0.000';
        }
        $result = $accepted ? ($rejected ? 'PARCIAL' : 'ACEPTADA') : 'RECHAZADA';
        if (($header['resultado'] !== null && $header['resultado'] !== $result) || $header['motivo_fallo'] !== null || $header['tipo_fallo'] !== null || ($accepted && $header['recibido_por'] === null)) {
            $this->fail('INVALID_INPUT', 'El resultado o el receptor no corresponde a los hechos del intento.');
        }
        return $result;
    }

    private function checkPreparationReferences(array $header, array $details, int $branchId): void
    {
        $warehouseBranch = $this->execute('SELECT sucursal_id FROM almacenes WHERE id=? LOCK IN SHARE MODE', [$header['almacen_id']])->fetchColumn();
        if ($warehouseBranch === false || (int) $warehouseBranch !== $branchId) {
            $this->fail('REFERENCE_CONFLICT', 'El almacén de origen no pertenece a la sede.');
        }
        if ($header['cliente_id'] !== null) {
            $this->reference('clientes', $header['cliente_id']);
        }
        if ($header['almacen_destino_id'] !== null) {
            $this->reference('almacenes', $header['almacen_destino_id']);
        }
        foreach ($details as $detail) {
            $this->reference('productos', $detail['producto_id']);
            $this->reference('unidades_medida', $detail['unidad_id']);
            if ($detail['lote_id'] !== null) {
                $product = $this->execute('SELECT producto_id FROM lotes WHERE id=? LOCK IN SHARE MODE', [$detail['lote_id']])->fetchColumn();
                if ($product === false || (int) $product !== $detail['producto_id']) {
                    $this->fail('REFERENCE_CONFLICT', 'El lote no pertenece al producto de la línea.');
                }
            }
        }
    }

    private function reference(string $table, int $id): void
    {
        if ($this->execute("SELECT id FROM $table WHERE id=? LOCK IN SHARE MODE", [$id])->fetchColumn() === false) {
            $this->fail('REFERENCE_CONFLICT', 'La referencia de catálogo no existe: ' . $table . '.');
        }
    }

    private function noDispatch(int $preparationId): void
    {
        if ($this->execute('SELECT id FROM logistica_despachos WHERE preparacion_id=? LOCK IN SHARE MODE', [$preparationId])->fetchColumn() !== false) {
            $this->fail('CONFLICT', 'La preparación ya tiene despacho y sus datos están congelados.');
        }
    }

    private function cancellablePreparation(int $preparationId): void
    {
        $dispatch = $this->execute('SELECT estado,movimiento_salida_ref FROM logistica_despachos WHERE preparacion_id=? LOCK IN SHARE MODE', [$preparationId])->fetch(PDO::FETCH_ASSOC);
        if ($dispatch && ($dispatch['estado'] !== 'CANCELADO' || $dispatch['movimiento_salida_ref'] !== null)) {
            $reason = $dispatch['estado'] === 'PENDIENTE' ? 'CONFLICT' : 'INVALID_STATE';
            $this->fail($reason, 'Antes de cancelar la preparación debe cancelarse su despacho pendiente; una salida física no puede cancelarse.');
        }
    }

    private function guideAvailable(int $guideId, ?int $dispatchId = null): void
    {
        $existing = $this->execute('SELECT id FROM logistica_despachos WHERE guia_id=? LOCK IN SHARE MODE', [$guideId])->fetchColumn();
        if ($existing !== false && (int) $existing !== $dispatchId) {
            $this->fail('REFERENCE_CONFLICT', 'La guía ya está asociada a otro despacho.');
        }
    }

    private function guide(int $guideId, int $guideVersion, array $preparation, bool $forDeparture): array
    {
        // Locks the existing document so its header/details cannot change during the snapshot.
        $header = $this->execute('SELECT * FROM guias_cabecera WHERE id=? FOR UPDATE', [$guideId])->fetch(PDO::FETCH_ASSOC);
        if (!$header || (int) $header['sucursal_id'] !== (int) $preparation['sucursal_id']) {
            $this->fail('REFERENCE_CONFLICT', 'La guía no pertenece a la sede del despacho.');
        }
        if ((int) $header['version'] !== $guideVersion) {
            $this->fail('CONFLICT', 'La versión de la guía cambió.');
        }
        if ($preparation['tipo_origen'] === 'VENTA' && (int) $header['cliente_id'] !== (int) $preparation['cliente_id']) {
            $this->fail('REFERENCE_CONFLICT', 'La guía corresponde a otro cliente.');
        }
        if ($header['estado_registro'] === 'ANULADO' || ($forDeparture && !in_array($header['estado_registro'], ['VALIDADO', 'PUBLICADO'], true))) {
            $this->fail('INVALID_STATE', 'La guía no está habilitada para esta operación.');
        }
        $details = $this->execute('SELECT * FROM guias_detalle WHERE guia_id=? ORDER BY id LOCK IN SHARE MODE', [$guideId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($details as &$detail) {
            $detail = $this->typedRow($detail);
            $detail['cantidad'] = LogisticsQuantity::normalize($detail['cantidad']);
        }
        unset($detail);
        return ['header' => $this->typedRow($header), 'details' => $details];
    }

    private function guideQuantities(int $dispatchId, array $guideDetails): void
    {
        $dispatchDetails = $this->execute('SELECT producto_id,unidad_id,cantidad FROM logistica_despacho_detalle WHERE despacho_id=? ORDER BY id LOCK IN SHARE MODE', [$dispatchId])->fetchAll(PDO::FETCH_ASSOC);
        $aggregate = function (array $details): array {
            $quantities = [];
            foreach ($details as $detail) {
                $key = (int) $detail['producto_id'] . ':' . (int) $detail['unidad_id'];
                $increment = LogisticsQuantity::toMillis($detail['cantidad']);
                $previous = $quantities[$key] ?? 0;
                if ($increment > PHP_INT_MAX - $previous) {
                    $this->fail('REFERENCE_CONFLICT', 'La cantidad documental acumulada excede el rango entero.');
                }
                $quantities[$key] = $previous + $increment;
            }
            ksort($quantities, SORT_STRING);
            return $quantities;
        };
        if ($dispatchDetails === [] || $aggregate($dispatchDetails) !== $aggregate($guideDetails)) {
            $this->fail('REFERENCE_CONFLICT', 'Productos, unidades y cantidades de guía y despacho no coinciden.');
        }
    }

    private function physicalDispatch(array $dispatch): void
    {
        if ($dispatch['movimiento_salida_ref'] === null || $dispatch['guia_id'] === null || $dispatch['guia_snapshot'] === null) {
            $this->fail('REFERENCE_CONFLICT', 'Falta el movimiento o el documento de salida confirmado.');
        }
    }

    /** Called only while the dispatch header is locked; deliveries serialize on that header. */
    private function dispatchBalances(int $dispatchId, ?int $excludedDeliveryId = null): array
    {
        // A caller may already have a REPEATABLE READ snapshot. These locking reads
        // see the current committed rows after acquiring the parent lock, never that
        // old snapshot; a normal SELECT SUM here could accept an excessive delivery.
        $rows = $this->execute('SELECT * FROM logistica_despacho_detalle WHERE despacho_id=? ORDER BY id LOCK IN SHARE MODE', [$dispatchId])->fetchAll(PDO::FETCH_ASSOC);
        $balances = [];
        foreach ($rows as $row) {
            $quantity = LogisticsQuantity::toMillis($row['cantidad']);
            $balances[(int) $row['id']] = ['quantity' => $quantity, 'delivered' => 0, 'returned' => 0, 'final' => 0, 'pending' => $quantity, 'assigned' => 0, 'free' => $quantity, 'line' => $row];
        }
        $sql = 'SELECT ed.despacho_detalle_id,ed.cantidad FROM logistica_entregas e
            JOIN logistica_entrega_detalle ed ON ed.entrega_id=e.id AND ed.despacho_id=e.despacho_id
            WHERE e.despacho_id=? AND e.anulada=0';
        $params = [$dispatchId];
        if ($excludedDeliveryId !== null) {
            $sql .= ' AND e.id<>?';
            $params[] = $excludedDeliveryId;
        }
        $sql .= ' ORDER BY e.id,ed.id LOCK IN SHARE MODE';
        foreach ($this->execute($sql, $params)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lineId = (int) $row['despacho_detalle_id'];
            if (!isset($balances[$lineId])) {
                $this->fail('REFERENCE_CONFLICT', 'Una entrega persistida refiere a otra línea de despacho.');
            }
            $delivered = LogisticsQuantity::toMillis($row['cantidad'], true);
            if ($delivered > $balances[$lineId]['pending']) {
                $this->fail('QUANTITY_EXCEEDED', 'Los datos persistidos exceden la cantidad despachada.');
            }
            $balances[$lineId]['delivered'] += $delivered;
            $balances[$lineId]['pending'] -= $delivered;
        }
        $resolutions = $this->execute("SELECT despacho_detalle_id,tipo,cantidad FROM logistica_incidencia_resoluciones WHERE despacho_id=? AND tipo IN ('RETORNO_CONFIRMADO','PERDIDA_CONFIRMADA','DISPOSICION_FINAL_CONFIRMADA') ORDER BY id LOCK IN SHARE MODE", [$dispatchId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($resolutions as $resolution) {
            $lineId = (int) $resolution['despacho_detalle_id'];
            $quantity = LogisticsQuantity::toMillis($resolution['cantidad']);
            if (!isset($balances[$lineId]) || $quantity > $balances[$lineId]['pending']) {
                $this->fail('QUANTITY_EXCEEDED', 'Los destinos finales persistidos exceden la cantidad pendiente.');
            }
            $field = $resolution['tipo'] === 'RETORNO_CONFIRMADO' ? 'returned' : 'final';
            $balances[$lineId][$field] += $quantity;
            $balances[$lineId]['pending'] -= $quantity;
        }
        foreach ($this->lockedCases($dispatchId, false) as $case) {
            if ($case['modo'] !== 'CUANTITATIVA') {
                continue;
            }
            $lineId = (int) $case['despacho_detalle_id'];
            if (!isset($balances[$lineId]) || $case['remaining'] > $balances[$lineId]['pending'] - $balances[$lineId]['assigned']) {
                $this->fail('QUANTITY_EXCEEDED', 'Las incidencias pendientes exceden el saldo físico de la línea.');
            }
            $balances[$lineId]['assigned'] += $case['remaining'];
        }
        foreach ($balances as &$balance) {
            $balance['free'] = $balance['pending'] - $balance['assigned'];
        }
        unset($balance);
        return $balances;
    }

    private function lockedCases(int $dispatchId, bool $exclusive = true): array
    {
        $lock = $exclusive ? 'FOR UPDATE' : 'LOCK IN SHARE MODE';
        $cases = $this->execute("SELECT * FROM logistica_incidencias WHERE despacho_id=? ORDER BY id $lock", [$dispatchId])->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($cases as $case) {
            $case['remaining'] = $case['modo'] === 'CUANTITATIVA' ? LogisticsQuantity::toMillis($case['cantidad']) : 0;
            $resolutions = $this->execute('SELECT r.tipo,r.cantidad,r.entrega_detalle_id,e.anulada FROM logistica_incidencia_resoluciones r LEFT JOIN logistica_entrega_detalle ed ON ed.id=r.entrega_detalle_id LEFT JOIN logistica_entregas e ON e.id=ed.entrega_id WHERE r.incidencia_id=? ORDER BY r.id LOCK IN SHARE MODE', [(int) $case['id']])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($resolutions as $resolution) {
                if ($resolution['tipo'] === 'ACEPTACION_EN_REINTENTO' && ($resolution['anulada'] === null || (int) $resolution['anulada'] !== 0)) {
                    $this->fail('REFERENCE_CONFLICT', 'Una resolución de aceptación carece de entrega vigente.');
                }
                if ($resolution['cantidad'] !== null) {
                    $quantity = LogisticsQuantity::toMillis($resolution['cantidad']);
                    if ($quantity > $case['remaining']) {
                        $this->fail('QUANTITY_EXCEEDED', 'Las resoluciones persistidas exceden la incidencia.');
                    }
                    $case['remaining'] -= $quantity;
                }
            }
            if ($case['modo'] === 'CUANTITATIVA' && (($case['remaining'] === 0) !== ($case['resuelta_at'] !== null))) {
                $this->fail('REFERENCE_CONFLICT', 'La marca de resolución no corresponde al saldo de la incidencia.');
            }
            $result[(int) $case['id']] = $case;
        }
        return $result;
    }

    private function allocationTotal(array $allocations, int $lineId, array $cases): int
    {
        $total = 0;
        foreach ($allocations as $allocation) {
            $case = $cases[$allocation['incidencia_id']] ?? null;
            if ($case === null || $case['modo'] !== 'CUANTITATIVA' || (int) $case['despacho_detalle_id'] !== $lineId || $case['resuelta_at'] !== null) {
                $this->fail('REFERENCE_CONFLICT', 'La asignación debe identificar un caso cuantitativo pendiente de esta línea.');
            }
            $quantity = LogisticsQuantity::toMillis($allocation['cantidad']);
            if ($quantity > $case['remaining']) {
                $this->fail('QUANTITY_EXCEEDED', 'La asignación supera el saldo del caso.');
            }
            $total += $quantity;
            if ($total > LogisticsQuantity::MAX_MILLIS) {
                $this->fail('QUANTITY_EXCEEDED', 'La asignación acumulada excede el rango permitido.');
            }
        }
        return $total;
    }

    private function openPhysicalDispatch(array $dispatch): void
    {
        if (!in_array($dispatch['estado'], ['EN_TRANSITO', 'EN_RESOLUCION'], true)) {
            $this->fail('INVALID_STATE', 'La operación requiere un despacho abierto en tránsito o resolución.');
        }
        $this->physicalDispatch($dispatch);
    }

    private function hasBlockingCases(int $dispatchId): bool
    {
        return $this->execute('SELECT id FROM logistica_incidencias WHERE despacho_id=? AND bloqueante=1 AND resuelta_at IS NULL ORDER BY id LIMIT 1 LOCK IN SHARE MODE', [$dispatchId])->fetchColumn() !== false;
    }

    private function operationalState(int $dispatchId, array $balances, bool $automaticNormalClosure = true): string
    {
        if ($balances === []) {
            $this->fail('REFERENCE_CONFLICT', 'El despacho no tiene líneas.');
        }
        if ($this->hasBlockingCases($dispatchId)) {
            return 'EN_RESOLUCION';
        }
        $complete = true;
        $allAccepted = true;
        foreach ($balances as $balance) {
            $complete = $complete && $balance['pending'] === 0;
            $allAccepted = $allAccepted && $balance['delivered'] === $balance['quantity'];
        }
        if (!$complete) {
            return 'EN_TRANSITO';
        }
        return $allAccepted && $automaticNormalClosure ? 'ENTREGADO' : 'EN_RESOLUCION';
    }

    private function assertCloseable(int $dispatchId, array $balances): string
    {
        if ($balances === [] || $this->hasBlockingCases($dispatchId)) {
            $this->fail('INVALID_STATE', 'El cierre requiere líneas y ninguna incidencia bloqueante pendiente.');
        }
        $exceptional = false;
        foreach ($balances as $balance) {
            if ($balance['pending'] !== 0 || $balance['assigned'] !== 0
                || $balance['quantity'] !== $balance['delivered'] + $balance['returned'] + $balance['final']) {
                $this->fail('INVALID_STATE', 'El cierre requiere un destino final confirmado para toda cantidad despachada.');
            }
            $exceptional = $exceptional || $balance['returned'] > 0 || $balance['final'] > 0;
        }
        return $exceptional ? 'CERRADO_CON_INCIDENCIA' : 'ENTREGADO';
    }

    private function balanceSnapshot(array $balances): array
    {
        $result = [];
        foreach ($balances as $id => $balance) {
            $result[] = ['despacho_detalle_id' => $id] + array_map(static fn(int $quantity): string => LogisticsQuantity::fromMillis($quantity),
                array_intersect_key($balance, array_flip(['quantity', 'delivered', 'returned', 'final', 'pending', 'assigned', 'free'])));
        }
        return $result;
    }

    private function confirmedInventoryFact(array $reference, string $type, string $quantity, array $dispatch, array $case, array $line): array
    {
        if ($this->inventory === null) {
            $this->fail('REFERENCE_CONFLICT', 'No hay un adaptador confiable de Inventarios para confirmar el destino de la mercadería.');
        }
        try {
            return $this->validateInventoryFact($reference, $type, $quantity, $dispatch, $case, $line);
        } catch (LogisticsPersistenceException $failure) {
            if ($failure->reasonCode() === 'INVALID_INPUT') {
                throw new LogisticsPersistenceException('REFERENCE_CONFLICT', 'Inventarios devolvió un hecho confirmado incompleto o inválido.', $failure);
            }
            throw $failure;
        }
    }

    private function validateInventoryFact(array $reference, string $type, string $quantity, array $dispatch, array $case, array $line): array
    {
        $fact = $this->inventory->confirmedFact($reference);
        if (($fact['sistema'] ?? null) !== $reference['sistema'] || ($fact['id'] ?? null) !== $reference['id']
            || ($fact['confirmado'] ?? null) !== true || ($fact['autorizado'] ?? null) !== true || ($fact['tipo'] ?? null) !== $type) {
            $this->fail('REFERENCE_CONFLICT', 'Inventarios no confirmó esa identidad y finalidad exactas.');
        }
        $identities = [
            'sucursal_id' => (int) $dispatch['sucursal_id'], 'despacho_id' => (int) $dispatch['id'],
            'incidencia_id' => (int) $case['id'], 'despacho_detalle_id' => (int) $line['id'],
            'producto_id' => (int) $line['producto_id'], 'unidad_id' => (int) $line['unidad_id'],
        ];
        foreach ($identities as $field => $value) {
            if (!array_key_exists($field, $fact) || $this->id($fact[$field]) !== $value) {
                $this->fail('REFERENCE_CONFLICT', 'La confirmación de Inventarios pertenece a otra sede, despacho, caso o mercadería.');
            }
        }
        if (!array_key_exists('lote_id', $fact) || $this->nullableId($fact['lote_id']) !== ($line['lote_id'] === null ? null : (int) $line['lote_id'])
            || LogisticsQuantity::normalize($fact['cantidad'] ?? null) !== $quantity) {
            $this->fail('REFERENCE_CONFLICT', 'El lote o la cantidad confirmada no corresponden a esta resolución indivisible.');
        }
        $return = $type === 'RETORNO_CONFIRMADO';
        $purpose = $return ? 'RETORNO' : ($type === 'PERDIDA_CONFIRMADA' ? 'PERDIDA' : 'DISPOSICION_FINAL');
        if (($fact['direccion'] ?? null) !== ($return ? 'ENTRADA' : 'SIN_NUEVO_EGRESO') || ($fact['finalidad'] ?? null) !== $purpose) {
            $this->fail('REFERENCE_CONFLICT', 'Una salida u otra recepción no acredita retorno, pérdida o disposición de este despacho.');
        }
        $warehouseId = $this->id($fact['almacen_id'] ?? null);
        $warehouseBranch = $this->execute('SELECT sucursal_id FROM almacenes WHERE id=? LOCK IN SHARE MODE', [$warehouseId])->fetchColumn();
        if ($warehouseBranch === false || (int) $warehouseBranch !== (int) $dispatch['sucursal_id']) {
            $this->fail('REFERENCE_CONFLICT', 'La confirmación no identifica un almacén de esta sede autorizado para el hecho.');
        }
        $fact['evidencia'] = $this->evidence($fact['evidencia'] ?? null);
        if (!$return) {
            $fact['autorizacion_ref'] = $this->text($fact['autorizacion_ref'] ?? null, 'autorizacion_ref', 128);
        } else {
            $fact['autorizacion_ref'] = $this->nullableText($fact['autorizacion_ref'] ?? null, 'autorizacion_ref', 128);
        }
        $fact['cantidad'] = $quantity;
        return $fact;
    }

    private function insertIncident(array $dispatch, array $incident, array $ctx, int $operationId): int
    {
        $id = $this->insert('logistica_incidencias', $incident + [
            'despacho_id' => (int) $dispatch['id'], 'sucursal_id' => $ctx['sucursal_id'], 'version' => 1,
            'created_by' => $ctx['actor_id'], 'updated_by' => $ctx['actor_id'],
        ]);
        $this->caseEvent($ctx, $operationId, 'CREATE_INCIDENT', $dispatch, $id, null, $incident['entrega_id'], [
            'tipo' => $incident['tipo'], 'modo' => $incident['modo'], 'cantidad' => $incident['cantidad'],
            'causas' => json_decode($incident['causas_json'], true, 512, JSON_THROW_ON_ERROR),
            'evidencia' => json_decode($incident['evidencia_json'], true, 512, JSON_THROW_ON_ERROR),
        ]);
        return $id;
    }

    private function insertResolution(array $case, array $resolution, array $ctx): int
    {
        $resolution['fecha_operativa'] ??= $this->utcNow();
        return $this->insert('logistica_incidencia_resoluciones', $resolution + [
            'incidencia_id' => (int) $case['id'], 'despacho_id' => (int) $case['despacho_id'], 'sucursal_id' => $ctx['sucursal_id'],
            'despacho_detalle_id' => $case['despacho_detalle_id'] === null ? null : (int) $case['despacho_detalle_id'],
            'created_by' => $ctx['actor_id'],
        ]);
    }

    private function touchCase(array $case, array $ctx, bool $resolved, array $extra = []): void
    {
        $changes = $extra + ['resuelta_at' => $resolved ? $this->utcNow() : null, 'resuelta_by' => $resolved ? $ctx['actor_id'] : null];
        $this->updateHeader('logistica_incidencias', (int) $case['id'], (int) $case['version'], $changes, $ctx['actor_id']);
    }

    private function caseEvent(array $ctx, int $operationId, string $action, array $dispatch, int $caseId, ?int $resolutionId, ?int $deliveryId, array $payload, ?string $state = null): void
    {
        $this->insert('logistica_historial', [
            'sucursal_id' => $ctx['sucursal_id'], 'preparacion_id' => (int) $dispatch['preparacion_id'],
            'despacho_id' => (int) $dispatch['id'], 'entrega_id' => $deliveryId, 'incidencia_id' => $caseId,
            'resolucion_id' => $resolutionId, 'operacion_id' => $operationId, 'accion' => $action,
            'estado_anterior' => $dispatch['estado'], 'estado_nuevo' => $state ?? $dispatch['estado'],
            'motivo' => $payload['motivo'] ?? null, 'actor_id' => $ctx['actor_id'], 'payload_json' => $this->json($payload),
        ]);
    }

    private function incidentResult(int $id, bool $resolved, int $version, int $dispatchId, string $dispatchState, int $dispatchVersion): array
    {
        return ['id' => $id, 'estado' => $resolved ? 'RESUELTA' : 'ABIERTA', 'version' => $version,
            'despacho_id' => $dispatchId, 'despacho_estado' => $dispatchState, 'despacho_version' => $dispatchVersion];
    }

    private function findAggregate(string $headerTable, string $detailTable, string $parentKey, int $id, int $branchId): ?array
    {
        $this->ids($id, $branchId);
        return $this->transaction(function () use ($headerTable, $detailTable, $parentKey, $id, $branchId): ?array {
            $header = $this->readHeader($headerTable, $id, $branchId);
            if ($header === null) {
                return null;
            }
            $details = $this->execute("SELECT * FROM $detailTable WHERE $parentKey=? ORDER BY id LOCK IN SHARE MODE", [$id])->fetchAll(PDO::FETCH_ASSOC);
            $balances = $headerTable === 'logistica_despachos' ? $this->dispatchBalances($id) : [];
            foreach ($details as &$detail) {
                $detail = $this->typedRow($detail);
                $detail['cantidad'] = LogisticsQuantity::normalize($detail['cantidad'], $headerTable === 'logistica_entregas');
                if (array_key_exists('cantidad_rechazada', $detail)) {
                    $detail['cantidad_rechazada'] = LogisticsQuantity::normalize($detail['cantidad_rechazada'], true);
                }
                if ($balances !== []) {
                    $balance = $balances[$detail['id']];
                    $detail['cantidad_entregada'] = LogisticsQuantity::fromMillis($balance['delivered']);
                    $detail['cantidad_pendiente'] = LogisticsQuantity::fromMillis($balance['pending']);
                    $detail['cantidad_retornada'] = LogisticsQuantity::fromMillis($balance['returned']);
                    $detail['cantidad_final'] = LogisticsQuantity::fromMillis($balance['final']);
                    $detail['cantidad_asignada'] = LogisticsQuantity::fromMillis($balance['assigned']);
                    $detail['cantidad_libre'] = LogisticsQuantity::fromMillis($balance['free']);
                }
            }
            unset($detail);
            $header = $this->typedRow($header);
            if (array_key_exists('guia_snapshot', $header) && $header['guia_snapshot'] !== null) {
                $header['guia_snapshot'] = json_decode($header['guia_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            }
            $header['details'] = $details;
            return $header;
        });
    }

    private function listing(string $table, int $branchId, array $filters, int $limit, int $offset, array $allowed, array $states, string $finder): array
    {
        $this->ids($branchId);
        $this->pagination($limit, $offset);
        $this->keys($filters, $allowed);
        $where = ['sucursal_id=?'];
        $params = [$branchId];
        foreach ($filters as $key => $value) {
            if ($key === 'estado') {
                if (!is_string($value)) {
                    $this->fail('INVALID_INPUT', 'Estado de filtro inválido.');
                }
                $this->enum($value, $states);
                $where[] = 'estado=?';
                $params[] = $value;
            } elseif ($key === 'fecha_desde' || $key === 'fecha_hasta') {
                $where[] = $key === 'fecha_desde' ? 'fecha>=?' : 'fecha<=?';
                $params[] = $this->date($value);
            } elseif ($key === 'origen_ref') {
                $where[] = 'origen_ref=?';
                $params[] = $this->text($value, $key, 128);
            } else {
                $where[] = $key . '=?';
                $params[] = $this->id($value);
            }
        }
        if (isset($filters['fecha_desde'], $filters['fecha_hasta']) && $filters['fecha_desde'] > $filters['fecha_hasta']) {
            $this->fail('INVALID_INPUT', 'El rango de fechas está invertido.');
        }
        $sql = 'SELECT id FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . " ORDER BY fecha DESC,id DESC LIMIT $limit OFFSET $offset LOCK IN SHARE MODE";
        return $this->transaction(function () use ($sql, $params, $finder, $branchId): array {
            $ids = $this->execute($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
            return array_map(fn(mixed $id): array => $this->{$finder}((int) $id, $branchId), $ids);
        });
    }

    private function locked(string $table, int $id, int $branchId, bool $exclusive = true): array
    {
        $lock = $exclusive ? 'FOR UPDATE' : 'LOCK IN SHARE MODE';
        $row = $this->execute("SELECT * FROM $table WHERE id=? AND sucursal_id=? $lock", [$id, $branchId])->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->fail('NOT_FOUND', 'Registro no encontrado en la sede.');
        }
        return $row;
    }

    private function readHeader(string $table, int $id, int $branchId): ?array
    {
        $row = $this->execute("SELECT * FROM $table WHERE id=? AND sucursal_id=? LOCK IN SHARE MODE", [$id, $branchId])->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function version(array $row, int $expectedVersion): void
    {
        if ((int) $row['version'] !== $expectedVersion) {
            $this->fail('CONFLICT', 'La versión cambió; vuelva a consultar el registro.');
        }
    }

    private function state(array $row, string $expectedState): void
    {
        if ($row['estado'] !== $expectedState) {
            $this->fail('INVALID_STATE', 'El estado vigente no corresponde al esperado.');
        }
    }

    private function updateHeader(string $table, int $id, int $expectedVersion, array $changes, int $actorId): void
    {
        $assignments = implode(',', array_map(static fn(string $column): string => $column . '=?', array_keys($changes)));
        $sql = "UPDATE $table SET $assignments,version=version+1,updated_by=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND version=?";
        $statement = $this->execute($sql, [...array_values($changes), $actorId, $id, $expectedVersion]);
        if ($statement->rowCount() !== 1) {
            $this->fail('CONFLICT', 'El agregado fue modificado por otra operación.');
        }
    }

    private function insertPreparationDetails(int $preparationId, array $details): void
    {
        foreach ($details as $position => $detail) {
            $this->insert('logistica_preparacion_detalle', $detail + ['preparacion_id' => $preparationId, 'posicion' => $position + 1]);
        }
    }

    private function event(array $context, int $operationId, string $action, ?int $preparationId, ?int $dispatchId, ?int $deliveryId, ?string $previousState, ?string $newState, ?string $reason = null, ?array $payload = null): void
    {
        $this->insert('logistica_historial', [
            'sucursal_id' => $context['sucursal_id'], 'preparacion_id' => $preparationId, 'despacho_id' => $dispatchId, 'entrega_id' => $deliveryId,
            'operacion_id' => $operationId, 'accion' => $action, 'estado_anterior' => $previousState, 'estado_nuevo' => $newState,
            'motivo' => $reason === '' ? null : $reason, 'actor_id' => $context['actor_id'],
            'payload_json' => $payload === null ? null : $this->json($payload),
        ]);
    }

    private function headerResult(int $id, string $state, int $version): array
    {
        return ['id' => $id, 'estado' => $state, 'version' => $version];
    }

    private function deliveryResult(int $id, bool $voided, int $version, int $dispatchId, string $dispatchState, int $dispatchVersion): array
    {
        return ['id' => $id, 'anulada' => $voided, 'version' => $version, 'despacho_id' => $dispatchId, 'despacho_estado' => $dispatchState, 'despacho_version' => $dispatchVersion];
    }

    private function insert(string $table, array $values): int
    {
        $columns = implode(',', array_keys($values));
        $marks = implode(',', array_fill(0, count($values), '?'));
        // Explicit UTC expressions keep new logistics rows consistent even when the
        // legacy application's PDO connection has not configured its session timezone.
        if (in_array($table, ['logistica_preparaciones', 'logistica_despachos', 'logistica_entregas', 'logistica_operaciones', 'logistica_historial', 'logistica_incidencias', 'logistica_incidencia_resoluciones'], true)) {
            $columns .= ',created_at';
            $marks .= ',UTC_TIMESTAMP(6)';
        }
        if (in_array($table, ['logistica_preparaciones', 'logistica_despachos', 'logistica_incidencias'], true)) {
            $columns .= ',updated_at';
            $marks .= ',UTC_TIMESTAMP(6)';
        }
        $this->execute("INSERT INTO $table ($columns) VALUES ($marks)", array_values($values));
        return (int) $this->pdo->lastInsertId();
    }

    private function execute(string $sql, array $values = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($values);
        return $statement;
    }

    private function typedRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value !== null && $key !== 'confirmacion_id' && ($key === 'id' || str_ends_with($key, '_id') || $key === 'version' || $key === 'guia_version' || $key === 'posicion' || str_ends_with($key, '_by'))) {
                $row[$key] = (int) $value;
            } elseif (in_array($key, ['anulada', 'bloqueante'], true)) {
                $row[$key] = (bool) $value;
            }
        }
        return $row;
    }

    private function ids(int ...$ids): void
    {
        foreach ($ids as $id) {
            $this->id($id);
        }
    }

    private function id(mixed $value): int
    {
        if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value)) {
            if (strlen($value) > strlen((string) PHP_INT_MAX) || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)) {
                $this->fail('INVALID_INPUT', 'ID fuera del rango entero.');
            }
            $value = (int) $value;
        }
        if (!is_int($value) || $value <= 0) {
            $this->fail('INVALID_INPUT', 'Los IDs y las versiones deben ser enteros positivos.');
        }
        return $value;
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null ? null : $this->id($value);
    }

    private function keys(array $data, array $allowed): void
    {
        if (array_diff(array_keys($data), $allowed) !== []) {
            $this->fail('INVALID_INPUT', 'El comando contiene campos no admitidos.');
        }
    }

    private function enum(string $value, array $allowed): void
    {
        if (!in_array($value, $allowed, true)) {
            $this->fail('INVALID_INPUT', 'Estado o tipo no admitido.');
        }
    }

    private function text(mixed $value, string $field, int $maximumBytes, bool $allowEmpty = false): string
    {
        if (!is_string($value) || !preg_match('//u', $value) || str_contains($value, "\0")) {
            $this->fail('INVALID_INPUT', 'Texto inválido: ' . $field . '.');
        }
        $value = trim($value);
        if ((!$allowEmpty && $value === '') || strlen($value) > $maximumBytes) {
            $this->fail('INVALID_INPUT', 'Texto obligatorio o demasiado largo: ' . $field . '.');
        }
        return $value;
    }

    private function nullableText(mixed $value, string $field, int $maximumBytes): ?string
    {
        return $value === null ? null : $this->text($value, $field, $maximumBytes);
    }

    private function asciiReference(mixed $value, string $field, int $maximumBytes): string
    {
        if (!is_string($value) || strlen($value) > $maximumBytes || !preg_match('/\A[\x21-\x7e]+\z/D', $value)) {
            $this->fail('INVALID_INPUT', 'La identidad de confirmación debe usar ASCII visible y conservarse exactamente: ' . $field . '.');
        }
        return $value;
    }

    private function date(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $value)) {
            $this->fail('INVALID_INPUT', 'Fecha inválida; use YYYY-MM-DD.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value || $value < '1000-01-01') {
            $this->fail('INVALID_INPUT', 'La fecha no existe o está fuera del rango DATE.');
        }
        return $value;
    }

    private function utcNow(): string
    {
        return (new DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    /** Operational DATETIME values are UTC, with a deterministic canonical microsecond form. */
    private function dateTime(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?\z/D', $value)) {
            $this->fail('INVALID_INPUT', 'Fecha operativa inválida; use YYYY-MM-DD HH:MM:SS.ffffff en UTC.');
        }
        $parts = explode('.', $value, 2);
        $canonical = $parts[0] . '.' . str_pad($parts[1] ?? '', 6, '0');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $canonical, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d H:i:s.u') !== $canonical || substr($canonical, 0, 10) < '1000-01-01') {
            $this->fail('INVALID_INPUT', 'La fecha operativa no existe o está fuera del rango DATETIME.');
        }
        return $canonical;
    }

    private function pagination(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 200 || $offset < 0) {
            $this->fail('INVALID_INPUT', 'Paginación inválida: limit entre 1 y 200 y offset no negativo.');
        }
    }

    private function canonical(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as &$entry) {
            if (is_array($entry)) {
                $entry = $this->canonical($entry);
            }
        }
        unset($entry);
        return $value;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function fail(string $reasonCode, string $message): never
    {
        throw new LogisticsPersistenceException($reasonCode, $message);
    }
}
