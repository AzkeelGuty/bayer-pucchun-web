<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\{HttpException,ValidationException};
use App\Policies\OperationalPolicy;
use App\Repositories\Operations\{DocumentRepository,GuideRepository,StockRepository};
use App\Repositories\Masters\MasterDataRepository;
use App\Validators\OperationalValidator;

final class OperationalService
{
    private DocumentRepository|GuideRepository|StockRepository $repository;
    private OperationalPolicy $policy;
    private OperationalValidator $validator;
    private MasterDataRepository $masters;

    public function __construct(private string $module, ?\PDO $pdo = null)
    {
        $pdo ??= \db();
        $this->repository = match($module) {
            'documents'=>new DocumentRepository($pdo), 'guides'=>new GuideRepository($pdo),
            'stock'=>new StockRepository($pdo), default=>throw new \InvalidArgumentException('Módulo inválido.')
        };
        $this->masters = new MasterDataRepository($pdo);
        $this->validator = new OperationalValidator($this->masters);
        $this->policy = new OperationalPolicy(new PermissionService($pdo));
    }

    public function listing(array $user, array $query): array
    {
        $this->policy->authorize($user,$this->module,'read');
        $filters = [];
        foreach (['fecha_desde','fecha_hasta'] as $field) {
            if (!isset($query[$field]) || $query[$field] === '') continue;
            if (!OperationalValidator::date($query[$field])) throw new ValidationException([$field=>'Fecha inválida.']);
            $filters[$field] = $query[$field];
        }
        if (isset($filters['fecha_desde'],$filters['fecha_hasta']) && $filters['fecha_desde'] > $filters['fecha_hasta']) throw new ValidationException(['fecha_hasta'=>'Intervalo invertido.']);
        if (isset($query['estado_registro']) && $query['estado_registro'] !== '') {
            if (!in_array($query['estado_registro'],['BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO'],true)) throw new ValidationException(['estado_registro'=>'Estado inválido.']);
            $filters['estado_registro'] = $query['estado_registro'];
        }
        $location = $this->module === 'stock' ? 'almacen_id' : 'sucursal_id';
        if (isset($query[$location]) && $query[$location] !== '') $filters[$location] = OperationalValidator::id($query[$location]);
        // Ignore browser-supplied created_by, even for users with multiple roles.
        if ($this->policy->ownOnly($user)) $filters['created_by'] = (int)$user['id'];
        $limit = OperationalValidator::id($query['limit'] ?? 300);
        $page = OperationalValidator::id($query['page'] ?? 1);
        if ($limit>300 || $page-1 > intdiv(PHP_INT_MAX,$limit)) throw new ValidationException(['page'=>'Paginación fuera de rango.']);
        return $this->repository->all($filters,$limit,($page-1)*$limit);
    }

    public function show(array $user, mixed $id): array
    {
        $this->policy->authorize($user,$this->module,'read');
        $record = $this->repository->find(OperationalValidator::id($id));
        if (!$record) throw new HttpException(404,'Carga no encontrada.');
        $this->policy->visible($user,$record['header']);
        // request_hash is a persistence detail, not part of the frontend contract.
        unset($record['header']['request_hash']);
        return $record;
    }

    public function captureContext(array $user): array
    {
        $this->policy->authorize($user,$this->module,'create');
        return ['module'=>$this->module,'state'=>'BORRADOR','max_details'=>200,
            'idempotency_key'=>$this->module==='stock' ? bin2hex(random_bytes(16)) : null];
    }

    public function masters(array $user, array $query): array
    {
        $this->policy->authorize($user,$this->module,'create');
        $catalog = $query['catalog'] ?? null; $q = $query['q'] ?? '';
        $catalogs = $this->module==='stock' ? ['almacenes','productos','unidades_medida','lotes'] : ['clientes','vendedores','sucursales','productos','unidades_medida','tipos_documento','departamentos','provincias','distritos'];
        if (!is_string($catalog) || !in_array($catalog,$catalogs,true) || !is_string($q)) throw new ValidationException(['catalog'=>'Consulta de catálogo inválida.']);
        $filters = $query['filters'] ?? [];
        if (!is_array($filters)) throw new ValidationException(['filters'=>'Filtros inválidos.']);
        try { return $this->masters->search($catalog,$q,$filters,OperationalValidator::id($query['limit'] ?? 50),$this->offset($query)); }
        catch (\InvalidArgumentException) { throw new ValidationException(['catalog'=>'Filtros o paginación de catálogo inválidos.']); }
    }

    private function offset(array $query): int
    {
        $page = OperationalValidator::id($query['page'] ?? 1); $limit = OperationalValidator::id($query['limit'] ?? 50);
        if ($limit>100 || $page-1 > intdiv(PHP_INT_MAX,$limit)) throw new ValidationException(['page'=>'Paginación inválida.']);
        return ($page-1)*$limit;
    }

    public function create(array $user, array $input): int
    {
        $this->policy->authorize($user,$this->module,'create');
        [$header,$details] = $this->validator->capture($this->module,$input);
        $actor = (int)$user['id'];
        if ($this->repository instanceof StockRepository) {
            $existing = $this->repository->findByIdempotencyKey($header['idempotency_key']);
            if ($existing && (int)$existing['header']['created_by'] !== $actor) throw new HttpException(409,'La clave de solicitud ya está en uso.');
        }
        try {
            $id = $this->repository->create($header,$details,$actor);
        } catch (\PDOException $error) {
            $code = (int)($error->errorInfo[1] ?? 0);
            if ($code === 1062) throw new HttpException(409,'Ya existe una carga con esos identificadores.');
            if ($code === 1452) throw new ValidationException(['references'=>'Una referencia ya no está disponible.']);
            throw $error;
        } catch (\InvalidArgumentException) {
            throw new ValidationException(['payload'=>'La carga no cumple el contrato v2.']);
        } catch (\RuntimeException $error) {
            if ($this->repository instanceof StockRepository && $error->getPrevious() instanceof \PDOException
                && (int)($error->getPrevious()->errorInfo[1] ?? 0) === 1062) throw new HttpException(409,'La clave de solicitud ya está en uso con otro contenido.');
            throw $error;
        }
        if ($this->repository instanceof StockRepository) {
            // Covers a concurrent request by another actor using the same key.
            $saved = $this->repository->find($id);
            if ((int)$saved['header']['created_by'] !== $actor) throw new HttpException(409,'La clave de solicitud ya está en uso.');
        }
        return $id;
    }

    public function editable(array $user, mixed $id): array
    {
        $this->policy->authorize($user,$this->module,'create');
        $record=$this->show($user,$id);
        if ($record['header']['estado_registro']!=='BORRADOR') throw new HttpException(409,'Solo se pueden modificar borradores.');
        return $record;
    }

    public function update(array $user, mixed $id, mixed $version, array $input): int
    {
        $record=$this->editable($user,$id);
        $version=OperationalValidator::id($version);
        if ((int)$record['header']['version']!==$version) throw new HttpException(409,'La carga cambió. Vuelva a abrirla antes de editar.');
        [$header,$details]=$this->validator->capture($this->module,$input,true);
        return $this->draftWrite(fn()=> $this->repository->updateDraft((int)$record['header']['id'],$version,$header,$details,(int)$user['id']));
    }

    public function delete(array $user, mixed $id, mixed $version): void
    {
        $record=$this->editable($user,$id);
        $version=OperationalValidator::id($version);
        $this->draftWrite(fn()=> $this->repository->deleteDraft((int)$record['header']['id'],$version,(int)$user['id']));
    }

    private function draftWrite(callable $operation): mixed
    {
        try { return $operation(); }
        catch (\PDOException $error) {
            $code=(int)($error->errorInfo[1] ?? 0);
            if (in_array($code,[1062,1451],true)) throw new HttpException(409,'La carga está duplicada o relacionada con otros registros.');
            if ($code===1452) throw new ValidationException(['references'=>'Una referencia ya no está disponible.']);
            throw $error;
        } catch (\InvalidArgumentException) {
            throw new ValidationException(['payload'=>'La carga no cumple el contrato v2 o intenta cambiar su identidad.']);
        } catch (\RuntimeException) {
            throw new HttpException(409,'La carga cambió. Actualice la página antes de continuar.');
        }
    }

    public function transition(array $user, mixed $id, mixed $version, mixed $target, mixed $reason): int
    {
        if (!is_string($target) || !is_string($reason)) throw new ValidationException(['status'=>'Estado o motivo inválido.']);
        $target=strtoupper(trim($target)); $reason=trim($reason);
        $this->policy->workflow($user,$target);
        if (!in_array($target,['BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO'],true)) throw new ValidationException(['status'=>'Estado inválido.']);
        if (in_array($target,['OBSERVADO','ANULADO'],true) && $reason==='') throw new ValidationException(['reason'=>'El motivo es obligatorio.']);
        $record=$this->show($user,$id);
        $version=OperationalValidator::id($version);
        return (new WorkflowService())->transition($this->repository,(int)$record['header']['id'],$version,$target,(int)$user['id'],$reason);
    }
}
