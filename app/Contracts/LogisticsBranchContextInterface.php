<?php
declare(strict_types=1);

namespace App\Contracts;

use App\DTO\LogisticsBranchContext;

/** Resolves an authorized operational branch. Suggestions and resource ownership are not grants. */
interface LogisticsBranchContextInterface
{
    /**
     * Returns the sole authorized branch or the explicitly selected authorized branch.
     * No authorized branch and an unauthorized selection are FORBIDDEN; a missing selection
     * when several branches are authorized is INVALID_INPUT.
     */
    public function resolve(int $actorId, ?int $selectedBranchId = null): LogisticsBranchContext;
}
