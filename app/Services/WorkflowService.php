<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\Repositories\OperationalRepository;
use RuntimeException;
use Throwable;

final class WorkflowService
{
    public function transition(
        OperationalRepository $repository,
        int $id,
        int $version,
        string $target,
        int $actorId,
        ?string $reason = null
    ): int {
        $record = $repository->find($id);
        if (!$record) throw new HttpException(404, 'El registro ya no existe.');

        $from = (string) ($record['header']['estado_registro'] ?? '');
        $module=$this->module($repository);
        $pdo=\db();
        $owns=!$pdo->inTransaction();
        if($owns) $pdo->beginTransaction();

        try {
            $newVersion = match ($from . '>' . $target) {
                'BORRADOR>VALIDADO' => $repository->markValidated($id, $version, $actorId),
                'VALIDADO>PUBLICADO' => $repository->markPublished($id, $version, $actorId),
                'VALIDADO>OBSERVADO' => $repository->markObserved($id, $version, $actorId, (string) $reason),
                'OBSERVADO>BORRADOR' => $repository->returnToDraft($id, $version, $actorId),
                'PUBLICADO>ANULADO' => $repository->markCancelled($id, $version, $actorId, (string) $reason),
                default => throw new HttpException(422, "Transición no permitida: $from → $target."),
            };

            if($target==='VALIDADO' || $target==='OBSERVADO'){
                $st=$pdo->prepare('INSERT INTO validaciones(modulo,registro_id,usuario_id,resultado,observacion,validated_at,version) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,?)');
                $st->execute([$module,$id,$actorId,$target,$reason ?: null,$newVersion]);
            }

            if($target==='PUBLICADO'){
                $st=$pdo->prepare("INSERT INTO publicaciones(modulo,usuario_id,estado,fecha_publicacion) VALUES(?,?,'PUBLICADO',CURRENT_TIMESTAMP)");
                $st->execute([$module,$actorId]);
                $publicationId=(int)$pdo->lastInsertId();
                $st=$pdo->prepare('INSERT INTO detalle_publicacion(publicacion_id,registro_id,dataset,version) VALUES(?,?,?,?)');
                $st->execute([$publicationId,$id,$module,$newVersion]);
            }

            if($target==='ANULADO'){
                $st=$pdo->prepare("INSERT INTO publicaciones(modulo,usuario_id,estado,fecha_publicacion,cancellation_reason,cancelled_at,cancelled_by) VALUES(?,?,'ANULADO',CURRENT_TIMESTAMP,?,CURRENT_TIMESTAMP,?)");
                $st->execute([$module,$actorId,$reason,$actorId]);
            }

            if($owns) $pdo->commit();
            return $newVersion;
        } catch (HttpException $error) {
            if($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        } catch (RuntimeException $error) {
            if($owns && $pdo->inTransaction()) $pdo->rollBack();
            // Compatibility with persistState(): only its explicit concurrency failure is a 409.
            // PDOException and unrelated runtime failures must reach the sanitized 500 handler.
            if (get_class($error) === RuntimeException::class
                && $error->getMessage() === 'Registro inexistente, estado de origen distinto o version desactualizada.') {
                throw new HttpException(409, 'El registro cambió mientras lo revisaba. Actualice la página e intente nuevamente.');
            }
            throw $error;
        } catch (\InvalidArgumentException $error) {
            if($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw new HttpException(422, $error->getMessage());
        } catch (Throwable $error) {
            if($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    private function module(OperationalRepository $repository): string
    {
        $class=$repository::class;
        return match(true){
            str_contains($class,'Document')=>'documentos',
            str_contains($class,'Guide')=>'guias',
            str_contains($class,'Stock')=>'stock',
            default=>'operacion',
        };
    }
}
