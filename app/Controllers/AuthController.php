<?php
namespace App\Controllers; use App\Services\AuthService;
class AuthController {public function showLogin():void{if(\auth_user())\redirect('/dashboard');\view('auth.login');}public function login():void{$s=new AuthService();if($s->attempt(trim((string)\input('email')), (string)\input('password')))\redirect('/dashboard');\flash('error','Credenciales inválidas.');\redirect('/login');}public function logout():void{(new AuthService())->logout();\redirect('/login');}}
