<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Auditoría persistente: almacena la dirección registrada por el servidor.
 * No reconstruye IPs históricas ni utiliza cabeceras HTTP no verificadas.
 */
final class AuditService
{
    public function __construct(private PDO $pdo) {}

    public function record(int $userId,string $module,string $action,?int $entityId,?string $ip,?string $userAgent): void
    {
        $agent=$userAgent===null?null:substr((string)preg_replace('/[^\x20-\x7E]/','?', $userAgent),0,255);
        $validIp=$ip!==null && filter_var($ip,FILTER_VALIDATE_IP)!==false?$ip:null;
        $st=$this->pdo->prepare(
            'INSERT INTO auditoria_acciones
             (usuario_id,modulo,accion,entidad_id,fecha_hora,ip,user_agent,resultado)
             VALUES(?,?,?,?,NOW(),?,?,?)'
        );
        $st->execute([$userId,$module,$action,$entityId,$validIp,$agent,'COMPLETADO']);
    }
}
