<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Domain/contract failure, deliberately independent of HTTP and persistence exceptions. */
final class LogisticsApplicationException extends RuntimeException
{
    public function __construct(
        public readonly LogisticsErrorCode $errorCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function reasonCode(): string
    {
        return $this->errorCode->value;
    }
}
