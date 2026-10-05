<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Stable persistence failures; Services translate these into their HTTP/domain responses. */
final class LogisticsPersistenceException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
