<?php
declare(strict_types=1);

namespace App\DTO;

/** Trusted command context. The repository computes and stores the sole idempotency hash. */
final readonly class LogisticsOperationContext
{
    public function __construct(
        public int $actorId,
        public int $branchId,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {
        if ($actorId < 1 || $branchId < 1) {
            throw new \InvalidArgumentException('Actor and authorized branch IDs must be positive.');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128
            || preg_match('/[^\x21-\x7E]/', $idempotencyKey) === 1) {
            throw new \InvalidArgumentException('Idempotency key must be printable ASCII without spaces (1-128 bytes).');
        }
    }

    /** Context shape required by LogisticsRepositoryInterface. */
    public function toRepositoryContext(): array
    {
        return ['sucursal_id' => $this->branchId, 'actor_id' => $this->actorId,
            'idempotency_key' => $this->idempotencyKey];
    }
}
