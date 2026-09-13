<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Policies\AccessPolicy;
use App\Services\AuthService;
use App\Validators\LoginValidator;

class AuthController
{
    public function showLogin(): void
    {
        if (\auth_user()) {
            \redirect(AccessPolicy::landing(\auth_user()));
        }
        \view('auth.login');
    }

    public function login(): void
    {
        $email = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;
        if (!(new LoginValidator())->valid($email, $password)) {
            throw new \App\Exceptions\HttpException(422, 'Ingrese un correo y una contraseña válidos.');
        }
        if ((new AuthService())->attempt(trim($email), $password)) {
            \redirect(AccessPolicy::landing(\auth_user()));
        }
        \flash('error', 'No se pudo iniciar sesión. Verifique sus credenciales o intente más tarde.');
        \redirect('/login');
    }

    public function logout(): void
    {
        (new AuthService())->logout();
        \redirect('/login');
    }
}
