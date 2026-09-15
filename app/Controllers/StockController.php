<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Policies\AccessPolicy;
use App\Repositories\MasterDataRepository;
use App\Repositories\StockRepository;
use App\Validators\BayerDataValidator;

class StockController
{
    private StockRepository $r;

    public function __construct()
    {
        $this->r = new StockRepository();
    }

    public function index(): void
    {
        \require_role(...AccessPolicy::INTERNAL);
        $filters = $this->filtersFromRequest();
        \view('stock.index', [
            'rows' => $this->r->all($filters),
            'filters' => $filters,
            'almacenes' => (new MasterDataRepository())->almacenes(),
        ]);
    }

    /** Read-only cabecera + detalle view; the workflow actions still POST to the pending /stock/estado endpoint. */
    public function show(): void
    {
        \require_role(...AccessPolicy::INTERNAL);
        $id = (int) \input('id');
        $record = $id > 0 ? $this->r->find($id) : null;
        if (!$record) {
            throw new HttpException(404, 'Registro de stock no encontrado.');
        }
        $master = new MasterDataRepository();
        \view('stock.show', [
            'record' => $record,
            'almacenes' => \index_by($master->almacenes(), 'id'),
            'productos' => \index_by($master->productos(), 'id'),
            'unidades' => \index_by($master->unidades(), 'id'),
            'lotes' => \index_by($master->lotes(), 'id'),
        ]);
    }

    public function create(): void
    {
        \require_role('ADMIN', 'DIGITADOR', 'SUPERVISOR');
        $master = new MasterDataRepository();
        \view('stock.form', [
            'almacenes' => $master->almacenes(),
            'productos' => $master->productos(),
            'unidades' => $master->unidades(),
            'lotes' => $master->lotes(),
        ]);
    }

    /** Read-only: pre-fills the same form used by create(), only for a BORRADOR record. */
    public function edit(): void
    {
        \require_role('ADMIN', 'DIGITADOR', 'SUPERVISOR');
        $id = (int) \input('id');
        $record = $id > 0 ? $this->r->find($id) : null;
        if (!$record) {
            throw new HttpException(404, 'Registro de stock no encontrado.');
        }
        if ($record['header']['estado_registro'] !== 'BORRADOR') {
            \flash('error', 'Solo se puede editar un stock en estado Borrador.');
            \redirect('/stock/ver?id=' . $id);
        }
        $_SESSION['_old'] = [
            'fecha_stock' => $record['header']['fecha_stock'],
            'almacen_id' => $record['header']['almacen_id'],
            'idempotency_key' => $record['header']['idempotency_key'],
        ];
        $master = new MasterDataRepository();
        \view('stock.form', [
            'almacenes' => $master->almacenes(),
            'productos' => $master->productos(),
            'unidades' => $master->unidades(),
            'lotes' => $master->lotes(),
            'record' => $record,
        ]);
    }

    public function store(): void
    {
        \require_role('ADMIN', 'DIGITADOR', 'SUPERVISOR');
        $d = $_POST;
        $e = (new BayerDataValidator())->validate($d, 'stock');
        if ($e) {
            $_SESSION['_old'] = $d;
            $_SESSION['_errors'] = $e;
            \redirect('/stock/nuevo');
        }
        try {
            $id = $this->r->create($d);
            \audit('stock', 'crear', $id);
            \flash('success', 'Stock registrado en borrador.');
        } catch (\Throwable $x) {
            \log_event('stock_store_error', ['e' => $x->getMessage()]);
            \flash('error', 'No se pudo registrar el stock.');
        }
        \redirect('/stock');
    }

    public function changeStatus(): void
    {
        \require_role('ADMIN', 'SUPERVISOR');
        $id = (int) \input('id');
        $s = (string) \input('status');
        if (!in_array($s, ['BORRADOR', 'VALIDADO', 'PUBLICADO'], true)) {
            $s = 'BORRADOR';
        }
        $this->r->status($id, $s);
        \audit('stock', 'estado_' . $s, $id);
        \redirect('/stock');
    }

    private function filtersFromRequest(): array
    {
        $filters = [];
        $estado = (string) \input('estado_registro', '');
        if (in_array($estado, ['BORRADOR', 'VALIDADO', 'PUBLICADO', 'OBSERVADO', 'ANULADO'], true)) {
            $filters['estado_registro'] = $estado;
        }
        foreach (['fecha_desde', 'fecha_hasta'] as $key) {
            $value = (string) \input($key, '');
            if ($value !== '' && \DateTimeImmutable::createFromFormat('!Y-m-d', $value) !== false) {
                $filters[$key] = $value;
            }
        }
        $almacen = (string) \input('almacen_id', '');
        if (ctype_digit($almacen) && $almacen !== '0') {
            $filters['almacen_id'] = (int) $almacen;
        }
        return $filters;
    }
}