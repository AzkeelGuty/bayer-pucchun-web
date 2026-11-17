<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;
use PDO;

/**
 * Almacén de referencia de productos. Este vínculo administrativo
 * NO crea ni mueve existencias del inventario físico Pucchún.
 */
final class ProductWarehouseService
{
    public function __construct(private ?PDO $pdo=null){$this->pdo??=\db();}

    public function options(): array
    {
        return $this->pdo->query("SELECT id,codigo,nombre,
             CONCAT(codigo,' · ',nombre) label
             FROM almacenes WHERE estado=1 ORDER BY nombre,codigo")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(string $source,int $productId): ?int
    {
        $table=$this->table($source);
        if($productId<1)return null;
        $s=$this->pdo->prepare("SELECT almacen_id FROM ".$table." WHERE producto_id=?");
        $s->execute([$productId]);
        $id=$s->fetchColumn();
        return $id===false?null:(int)$id;
    }

    /** Usa la transacción exterior, si ya existe, para no confirmar datos parciales. */
    public function assign(string $source,int $productId,mixed $value): void
    {
        $table=$this->table($source);
        if($productId<1)throw new HttpException(422,'Producto inválido para asignar almacén.');
        $raw=trim((string)($value??''));
        if($raw===''){
            $d=$this->pdo->prepare('DELETE FROM '.$table.' WHERE producto_id=?');
            $d->execute([$productId]);
            return;
        }
        if(!ctype_digit($raw)||(int)$raw<1)
            throw new HttpException(422,'Seleccione un almacén válido.');
        $id=(int)$raw;
        $check=$this->pdo->prepare('SELECT id FROM almacenes WHERE id=? AND estado=1');
        $check->execute([$id]);
        if($check->fetchColumn()===false)
            throw new HttpException(422,'El almacén indicado no existe o está inactivo.');
        $up=$this->pdo->prepare('INSERT INTO '.$table.' (producto_id,almacen_id)
            VALUES (?,?) ON DUPLICATE KEY UPDATE almacen_id=VALUES(almacen_id)');
        $up->execute([$productId,$id]);
    }

    private function table(string $source): string
    {
        return match($source){
            'BAYER'=>'bayer_producto_almacen',
            'PUCCHUN'=>'pucchun_producto_almacen',
            default=>throw new HttpException(422,'Catálogo de almacén no reconocido.'),
        };
    }
}
