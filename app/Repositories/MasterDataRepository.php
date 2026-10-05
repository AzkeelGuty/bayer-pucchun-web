<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Read-only lookups used to populate dropdowns in capture forms and filters. */
class MasterDataRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    /** Includes ubigeo and last known operational seller/branch for assisted capture. */
    public function clientes(): array
    {
        return $this->all('SELECT c.id,c.codigo,c.nro_doc,c.razon_social,CONCAT(c.nro_doc," · ",c.razon_social) label,c.departamento_id,c.provincia_id,c.distrito_id,
            COALESCE(
                (SELECT g.vendedor_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1),
                (SELECT d.vendedor_id FROM documentos_cabecera d WHERE d.cliente_id=c.id ORDER BY d.fecha DESC,d.id DESC LIMIT 1)
            ) vendedor_sugerido_id,
            COALESCE(
                (SELECT g.sucursal_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1),
                (SELECT d.sucursal_id FROM documentos_cabecera d WHERE d.cliente_id=c.id ORDER BY d.fecha DESC,d.id DESC LIMIT 1)
            ) sucursal_sugerida_id
            FROM clientes c ORDER BY c.razon_social');
    }

    public function vendedores(): array
    {
        return $this->all("SELECT id, codigo, TRIM(CONCAT(nombres, ' ', apellidos)) nombre FROM vendedores WHERE estado = 1 ORDER BY nombre");
    }

    public function sucursales(): array
    {
        return $this->all('SELECT id, codigo, nombre FROM sucursales WHERE estado = 1 ORDER BY nombre');
    }

    public function almacenes(): array
    {
        return $this->all('SELECT id, codigo, nombre FROM almacenes WHERE estado = 1 ORDER BY nombre');
    }

    public function productos(): array
    {
        return $this->all('SELECT id, codigo, nombre, CONCAT(codigo," · ",nombre) label, unidad_base_id FROM productos WHERE estado = 1 ORDER BY nombre');
    }

    public function unidades(): array
    {
        return $this->all('SELECT id, codigo, nombre FROM unidades_medida ORDER BY nombre');
    }

    /** Every lote with its producto_id, so the form can filter them client-side per line. */
    public function lotes(): array
    {
        return $this->all("SELECT id, producto_id, codigo_lote, DATE_FORMAT(fecha_vencimiento, '%Y-%m-%d') fecha_vencimiento FROM lotes WHERE estado = 1 ORDER BY codigo_lote");
    }

    public function departamentos(): array
    {
        return $this->all('SELECT id, nombre FROM departamentos ORDER BY nombre');
    }

    /** Every provincia with its departamento_id, filtered client-side per selected departamento. */
    public function provincias(): array
    {
        return $this->all('SELECT id, departamento_id, nombre FROM provincias ORDER BY nombre');
    }

    /** Every distrito with its provincia_id, filtered client-side per selected provincia. */
    public function distritos(): array
    {
        return $this->all('SELECT id, provincia_id, nombre FROM distritos ORDER BY nombre');
    }

    public function clientesByIds(array $ids): array
    {
        $ids=$this->ids($ids);
        if(!$ids) return [];
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $sql='SELECT c.id,c.codigo,c.nro_doc,c.razon_social,CONCAT(c.nro_doc," · ",c.razon_social) label,c.departamento_id,c.provincia_id,c.distrito_id,
            dp.nombre departamento,p.nombre provincia,d.nombre distrito,
            COALESCE(
                (SELECT g.vendedor_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1),
                (SELECT x.vendedor_id FROM documentos_cabecera x WHERE x.cliente_id=c.id ORDER BY x.fecha DESC,x.id DESC LIMIT 1)
            ) vendedor_sugerido_id,
            COALESCE(
                (SELECT g.sucursal_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1),
                (SELECT x.sucursal_id FROM documentos_cabecera x WHERE x.cliente_id=c.id ORDER BY x.fecha DESC,x.id DESC LIMIT 1)
            ) sucursal_sugerida_id
            FROM clientes c
            LEFT JOIN departamentos dp ON dp.id=c.departamento_id
            LEFT JOIN provincias p ON p.id=c.provincia_id
            LEFT JOIN distritos d ON d.id=c.distrito_id
            WHERE c.id IN ('.$ph.') ORDER BY c.razon_social';
        $st=$this->pdo->prepare($sql); $st->execute($ids); return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clienteById(int $id): ?array
    {
        return $id>0 ? ($this->clientesByIds([$id])[0]??null) : null;
    }

    public function vendedoresByIds(array $ids): array
    {
        return $this->byIds('SELECT id,codigo,TRIM(CONCAT(nombres," ",COALESCE(apellidos,""))) nombre,CONCAT(codigo," · ",TRIM(CONCAT(nombres," ",COALESCE(apellidos,"")))) label FROM vendedores WHERE estado=1 AND id IN (%s) ORDER BY nombre',$ids);
    }

    public function sucursalesByIds(array $ids): array
    {
        return $this->byIds('SELECT id,codigo,nombre,CONCAT(codigo," · ",nombre) label FROM sucursales WHERE estado=1 AND id IN (%s) ORDER BY nombre',$ids);
    }

    public function almacenesByIds(array $ids): array
    {
        return $this->byIds('SELECT id,codigo,nombre,CONCAT(codigo," · ",nombre) label FROM almacenes WHERE estado=1 AND id IN (%s) ORDER BY nombre',$ids);
    }

    public function productosByIds(array $ids): array
    {
        return $this->byIds('SELECT p.id,p.codigo,p.nombre,CONCAT(p.codigo," · ",p.nombre) label,p.unidad_base_id,CONCAT(u.codigo," · ",u.nombre) unidad_label FROM productos p LEFT JOIN unidades_medida u ON u.id=p.unidad_base_id WHERE p.estado=1 AND p.id IN (%s) ORDER BY p.nombre',$ids);
    }

    public function lotesByIds(array $ids): array
    {
        return $this->byIds("SELECT id,producto_id,codigo_lote,DATE_FORMAT(fecha_vencimiento,'%Y-%m-%d') fecha_vencimiento,CONCAT(codigo_lote,IF(fecha_vencimiento IS NULL,'',CONCAT(' · Vence ',DATE_FORMAT(fecha_vencimiento,'%d/%m/%Y')))) label FROM lotes WHERE estado=1 AND id IN (%s) ORDER BY codigo_lote",$ids);
    }

    public function provinciasByIds(array $ids): array
    {
        return $this->byIds('SELECT id,departamento_id,nombre FROM provincias WHERE id IN (%s) ORDER BY nombre',$ids);
    }

    public function distritosByIds(array $ids): array
    {
        return $this->byIds('SELECT id,provincia_id,nombre FROM distritos WHERE id IN (%s) ORDER BY nombre',$ids);
    }

    private function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
    }

    private function byIds(string $sql,array $ids): array
    {
        $ids=$this->ids($ids);
        if(!$ids) return [];
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $st=$this->pdo->prepare(sprintf($sql,$ph));
        $st->execute($ids);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateClientLocation(int $clientId,int $departmentId,int $provinceId,int $districtId): void
    {
        foreach([$clientId,$departmentId,$provinceId,$districtId] as $id){
            if($id<1) throw new \InvalidArgumentException('Identificador de ubicación inválido.');
        }

        $st=$this->pdo->prepare(
            'SELECT d.id FROM distritos d
             JOIN provincias p ON p.id=d.provincia_id
             WHERE d.id=? AND p.id=? AND p.departamento_id=?'
        );
        $st->execute([$districtId,$provinceId,$departmentId]);
        if($st->fetchColumn()===false){
            throw new \InvalidArgumentException('Departamento, provincia y distrito no corresponden entre sí.');
        }

        $st=$this->pdo->prepare(
            'UPDATE clientes SET departamento_id=?,provincia_id=?,distrito_id=? WHERE id=?'
        );
        $st->execute([$departmentId,$provinceId,$districtId,$clientId]);
        if($st->rowCount()===0){
            $check=$this->pdo->prepare('SELECT id FROM clientes WHERE id=?');
            $check->execute([$clientId]);
            if($check->fetchColumn()===false) throw new \RuntimeException('Cliente no encontrado.');
        }
    }

    private function all(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}