<?php
declare(strict_types=1);

namespace App\Validators;

final class LoginValidator
{
    public function valid(mixed $email, mixed $password): bool
    {
        return is_string($email) && is_string($password)
            && strlen($email) <= 120 && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false
            && $password !== '' && strlen($password) <= 4096;
    }
}
