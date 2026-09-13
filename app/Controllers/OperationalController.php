<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\OperationalService;

abstract class OperationalController
{
    protected const MODULE = '';
    protected const PATH = '';

    private function service(): OperationalService { return new OperationalService(static::MODULE); }
    private function actor(): array { \require_auth(); return \auth_user(); }
    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }

    public function index(): void
    {
        $rows = $this->service()->listing($this->actor(),$_GET);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) $this->json(['items'=>$rows]);
        else \view(static::PATH.'.index',['rows'=>$rows]);
    }

    public function create(): void
    {
        $context = $this->service()->captureContext($this->actor());
        if (!str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            throw new HttpException(503,'Formulario pendiente de conectar al contrato v2 del backend.');
        }
        $context['_csrf'] = \csrf_token();
        $this->json($context);
    }

    public function store(): void
    {
        // Form-encoded header[...] + details[n][...], including the existing CSRF token.
        $id = $this->service()->create($this->actor(),$_POST);
        $link = \url('/'.static::PATH.'/ver?id='.$id);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Location: '.$link);
            $this->json(['id'=>$id],201);
        } else \redirect('/'.static::PATH);
    }

    public function show(): void { $this->json($this->service()->show($this->actor(),$_GET['id'] ?? null)); }
    public function masters(): void { $this->json(['items'=>$this->service()->masters($this->actor(),$_GET)]); }
}
