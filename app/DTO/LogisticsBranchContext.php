<?php
declare(strict_types=1);

namespace App\DTO;

/** Authorized execution location, resolved by LogisticsBranchContextInterface. */
final readonly class LogisticsBranchContext
{
    public function __construct(public int $actorId, public int $branchId)
    {
        if ($actorId < 1 || $branchId < 1) {
            throw new \InvalidArgumentException('Actor and authorized branch IDs must be positive.');
        }
    }
}
