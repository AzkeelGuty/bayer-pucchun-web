<?php
declare(strict_types=1);

namespace App\DTO;

/** Explicit provider outcome; a reference is useful only with a confirmed outcome. */
final readonly class LogisticsInventoryResult
{
    public const RESERVATION_ACCEPTED = 'RESERVATION_ACCEPTED';
    public const RESERVATION_REJECTED = 'RESERVATION_REJECTED';
    public const RESERVATION_CONFIRMED = 'RESERVATION_CONFIRMED';
    public const RESERVATION_NOT_FOUND = 'RESERVATION_NOT_FOUND';
    public const RELEASED = 'RELEASED';
    public const EXIT_REQUESTED = 'EXIT_REQUESTED';
    public const EXIT_CONFIRMED = 'EXIT_CONFIRMED';
    public const INSUFFICIENT_STOCK = 'INSUFFICIENT_STOCK';
    public const CONFLICT = 'CONFLICT';
    public const OPERATION_NOT_FOUND = 'OPERATION_NOT_FOUND';

    private const OUTCOMES = [self::RESERVATION_ACCEPTED, self::RESERVATION_REJECTED,
        self::RESERVATION_CONFIRMED, self::RESERVATION_NOT_FOUND, self::RELEASED,
        self::EXIT_REQUESTED, self::EXIT_CONFIRMED, self::INSUFFICIENT_STOCK,
        self::CONFLICT, self::OPERATION_NOT_FOUND];

    public function __construct(
        public string $outcome,
        public ?string $operationReference = null,
        public ?string $reservationReference = null,
        public ?string $movementReference = null,
        public ?string $message = null,
    ) {
        if (!in_array($outcome, self::OUTCOMES, true)) {
            throw new \InvalidArgumentException('Unknown Inventory operation outcome.');
        }
        if (in_array($outcome, [self::RESERVATION_CONFIRMED, self::EXIT_CONFIRMED], true)
            && ($operationReference === null || $operationReference === '')) {
            throw new \InvalidArgumentException('A confirmed Inventory fact requires its operation reference.');
        }
        if ($outcome === self::RESERVATION_CONFIRMED && ($reservationReference === null || $reservationReference === '')
            || $outcome === self::EXIT_CONFIRMED && ($movementReference === null || $movementReference === '')) {
            throw new \InvalidArgumentException('Confirmed reservation/exit must include its confirmed reference.');
        }
    }
}
