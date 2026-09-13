<?php
declare(strict_types=1);
namespace App\Exceptions;

final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Revise los campos enviados.');
    }
}
