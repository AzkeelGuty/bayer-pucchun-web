<?php
declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\HttpException;

final class WorkflowValidator
{
    public static function version(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value))
            || !preg_match('/^[1-9][0-9]*$/D', (string) $value)
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new HttpException(422, 'Debe enviar una versión válida del registro.');
        }
        return (int) $value;
    }
}
