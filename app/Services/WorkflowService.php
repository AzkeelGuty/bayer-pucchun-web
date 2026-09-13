<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\Repositories\OperationalRepository;
use RuntimeException;

final class WorkflowService
{
    public function transition(
        OperationalRepository $repository,
        int $id,
        int $version,
        string $target,
        int $actorId,
        ?string $reason = null
    ): int {
        $record = $repository->find($id);
        if (!$record) throw new HttpException(404, 'El registro ya no existe.');

        $from = (string) ($record['header']['estado_registro'] ?? '');
        try {
            return match ($from . '>' . $target) {
                'BORRADOR>VALIDADO' => $repository->markValidated($id, $version, $actorId),
                'VALIDADO>PUBLICADO' => $repository->markPublished($id, $version, $actorId),
                'VALIDADO>OBSERVADO' => $repository->markObserved($id, $version, $actorId, (string) $reason),
                'OBSERVADO>BORRADOR' => $repository->returnToDraft($id, $version, $actorId),
                'PUBLICADO>ANULADO' => $repository->markCancelled($id, $version, $actorId, (string) $reason),
                default => throw new HttpException(422, "Transición no permitida: $from → $target."),
            };
        } catch (RuntimeException $error) {
            throw new HttpException(409, 'El registro cambió mientras lo revisaba. Actualice la página e intente nuevamente.');
        } catch (\InvalidArgumentException $error) {
            throw new HttpException(422, $error->getMessage());
        }
    }
}
