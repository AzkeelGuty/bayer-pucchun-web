<?php
declare(strict_types=1);

namespace App\Exceptions;

/** Stable application-level failures; Controllers may map these to HTTP later. */
enum LogisticsErrorCode: string
{
    case INVALID_INPUT = 'INVALID_INPUT';
    case UNAUTHENTICATED = 'UNAUTHENTICATED';
    case FORBIDDEN = 'FORBIDDEN';
    case NOT_FOUND = 'NOT_FOUND';
    case CONFLICT = 'CONFLICT';
    case IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
    case INVALID_STATE = 'INVALID_STATE';
    case QUANTITY_EXCEEDED = 'QUANTITY_EXCEEDED';
    case REFERENCE_CONFLICT = 'REFERENCE_CONFLICT';
    case DEPENDENCY_UNAVAILABLE = 'DEPENDENCY_UNAVAILABLE';
    case DEPENDENCY_REJECTED = 'DEPENDENCY_REJECTED';
}
