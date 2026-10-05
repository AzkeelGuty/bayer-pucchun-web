<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\{BayerDataService,DashboardService};
use App\Policies\AccessPolicy;
use App\Exceptions\HttpException;

final class BayerController
{
    public function index(): void
    {
        \require_role(...AccessPolicy::PUBLISHED);
        $d = new DashboardService();
        $kpis = $d->kpis(true);
        unset($kpis['clientes'], $kpis['productos']);
        \view('dashboard.bayer.index', [
            'kpis'=>$kpis,
            'series'=>$d->salesByMonth(),
            'top'=>$d->topProducts(),
            'distribution'=>$d->publishedDistribution(),
            'lastUpdate'=>$d->lastPublishedAt(),
        ]);
    }

    public function data(): void
    {
        \require_role(...AccessPolicy::PUBLISHED);
        $type = $this->type();
        $filters = $this->filters();
        $service = new BayerDataService();
        $perPage=15;
        $total=$service->countDataset($type,$filters);
        $pages=max(1,(int)ceil($total/$perPage));
        $page=min($pages,max(1,(int)($_GET['page']??1)));
        $rows=$service->dataset($type,$filters,$perPage,($page-1)*$perPage);
        \view('dashboard.bayer.data', [
            'rows'=>$rows,
            'type'=>$type,
            'filters'=>$filters,
            'branches'=>$service->branches(),
            'total'=>$total,
            'page'=>$page,
            'pages'=>$pages,
            'perPage'=>$perPage,
        ]);
    }

    public function exports(): void
    {
        \require_role(...AccessPolicy::PUBLISHED);
        \view('dashboard.bayer.exports');
    }

    public function downloads(): void
    {
        \require_role(...AccessPolicy::PUBLISHED);
        $user=(int)\auth_user()['id'];
        $perPage=15;
        $count=\db()->prepare('SELECT COUNT(*) FROM exportaciones WHERE usuario_id=?');
        $count->execute([$user]);
        $total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));
        $page=min($pages,max(1,(int)($_GET['page']??1)));
        $offset=($page-1)*$perPage;
        $st=\db()->prepare("SELECT id,tipo_dataset,formato,record_count,resultado,generated_at,nombre_archivo FROM exportaciones WHERE usuario_id=? ORDER BY generated_at DESC,id DESC LIMIT ".$perPage." OFFSET ".$offset);
        $st->execute([$user]);
        \view('dashboard.bayer.downloads',[
            'rows'=>$st->fetchAll(),
            'total'=>$total,'page'=>$page,'pages'=>$pages,'perPage'=>$perPage,
        ]);
    }

    private function type(): string
    {
        $type = $_GET['type'] ?? 'documents';
        if (!is_string($type) || !in_array($type, ['documents','guides','stock'], true)) {
            throw new HttpException(422, 'Dataset inválido.');
        }
        return $type;
    }

    private function filters(): array
    {
        $out=[];
        foreach(['from','to'] as $key){
            $value=$_GET[$key]??'';
            if(!is_string($value)) throw new HttpException(422,'Fecha inválida.');
            $value=trim($value);
            if($value!==''){
                $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
                if(!$date || $date->format('Y-m-d')!==$value) throw new HttpException(422,'Fecha inválida.');
                $out[$key]=$value;
            }
        }
        if(isset($out['from'],$out['to']) && $out['from']>$out['to']) throw new HttpException(422,'El rango de fechas es inválido.');

        foreach(['branch','q'] as $key){
            $value=$_GET[$key]??'';
            if(!is_string($value)) throw new HttpException(422,'Filtro inválido.');
            $value=trim($value);
            if($value!==''){
                if(mb_strlen($value)>100) throw new HttpException(422,'Filtro demasiado largo.');
                $out[$key]=$value;
            }
        }
        return $out;
    }
}
