<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Policies\AccessPolicy;
use App\Repositories\GuideRepository;
use App\Repositories\MasterDataRepository;
use App\Validators\BayerDataValidator;

class GuideController
{
    private GuideRepository $r;

    public function __construct()
    {
        $this->r = new GuideRepository();
    }

    public function index(): void
    {
        \require_role(...AccessPolicy::INTERNAL);
        $filters = $this->filtersFromRequest();
        \view('guias.index', [
            'rows' => $this->r->all($filters),
            'filters' => $filters,
            'sucursales' => (new MasterDataRepository())->sucursales(),
        ]);
    }

    /** Read-only cabecera + detalle view; the workflow actions still POST to the pending /guias/estado endpoint. */
    public function show(): void
    {
        \require_role(...AccessPolicy::INTERNAL);
        $id = (int) \input('id');
        $record = $id > 0 ? $this->r->find($id) : null;
        if (!$record) {
            throw new HttpException(404, 'Guía no encontrada.');
        }
        $master = new MasterDataRepository();
        \view('guias.show', [
            'record' => $record,
            'clientes' => \index_by($master->clientes(), 'id'),
            'vendedores' => \index_by($master->vendedores(), 'id'),
            'sucursales' => \index_by($master->sucursales(), 'id'),
            'productos' => \index_by($master->productos(), 'id'),
            'unidades' => \index_by($master->unidades(), 'id'),
            'departamentos' => \index_by($master->departamentos(), 'id'),
            'provincias' => \index_by($master->provincias(), 'id'),
            'distritos' => \index_by($master->distritos(), 'id'),
        ]);
    }

    public function create(): void
    {
        \require_role('ADMIN', 'DIGITADOR', 'SUPERVISOR');
        $master = new MasterDataRepository();
        \view('guias.form', [
            'clientes' => $master->clientes(),
            'vendedores' => $master->vendedores(),
            'sucursales' => $master->sucursales(),
            'productos' => $master->productos(),
            'unidades' => $master->unidades(),
            'departamentos' => $master->departamentos(),
            'provincias' => $master->provincias(),
            'distritos' => $master->distritos(),
        ]);
    }

    /** Read-only: pre-fills the same form used by create(), only for a BORRADOR record. */
    public function edit(): void
    {
        \require_role('ADMIN', 'DIGITADOR', 'SUPERVISOR');
        $id = (int) \input('id');
        $record = $id > 0 ? $this->r->find($id) : null;
        if (!$record) {
            throw new HttpException(404, 'Guía no encontrada.');
        }
        if ($record['header']['estado_registro'] !== 'BORRADOR') {
            \flash('error', 'Solo se puede editar una guía en estado Borrador.');
            \redirect('/guias/ver?id=' . $id);
        }
        // field()/select() read initial values from $_SESSION['_old']; seeding it
        // here reuses that mechanism instead of a second value-resolution path.
        $_SESSION['_old'] = [
            'numero' => $record['header']['numero'],
            'fecha' => $record['header']['fecha'],
            'cliente_id' => $record['header']['cliente_id'],
            'vendedor_id' => $record['header']['vendedor_id'],
            'sucursal_id' => $record['header']['sucursal_id'],
            'departamento_id' => $record['header']['departamento_id'],
            'provincia_id' => $record['header']['provincia_id'],
            'distrito_id' => $record['header']['distrito_id'],
        ];
        $master = new MasterDataRepository();
        \view('guias.form', [
            'clientes' => $master->clientes(),
            'vendedores' => $master->vendedores(),
            'sucursales' => $master->sucursales(),
            'productos' => $master->productos(),
            'unidades' => $master->unidades(),
            'departamentos' => $master->departamentos(),
            'provincias' => $master->provincias(),
            'distritos' => $master->distritos(),
            'record' => $record,
        ]);
    }

    public function store(): void
    {
        \require_role('ADMIN', 'DIGITADOR', 'SUPERVISOR');
        $d = $_POST;
        $e = (new BayerDataValidator())->validate($d, 'guides');
        if ($e) {
            $_SESSION['_old'] = $d;
            $_SESSION['_errors'] = $e;
            \redirect('/guias/nuevo');
        }
        try {
            $id = $this->r->create($d);
            \audit('guias', 'crear', $id);
            \flash('success', 'Guía registrada en borrador.');
        } catch (\Throwable $x) {
            \log_event('guide_store_error', ['e' => $x->getMessage()]);
            \flash('error', 'No se pudo registrar la guía.');
        }
        \redirect('/guias');
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
        \audit('guias', 'estado_' . $s, $id);
        \redirect('/guias');
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
        $sucursal = (string) \input('sucursal_id', '');
        if (ctype_digit($sucursal) && $sucursal !== '0') {
            $filters['sucursal_id'] = (int) $sucursal;
        }
        return $filters;
    }
}