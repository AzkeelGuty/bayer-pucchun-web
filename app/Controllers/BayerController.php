<?php
namespace App\Controllers;

use App\Services\{BayerDataService,DashboardService};
use App\Policies\AccessPolicy;
use App\Exceptions\HttpException;

class BayerController
{
    public function index(): void
    {
        \require_role(...AccessPolicy::PUBLISHED);
        $d = new DashboardService();
        $kpis = $d->kpis(true);
        // Master totals are internal. Published master counts await Pedro's Data Hub.
        unset($kpis['clientes'], $kpis['productos']);
        \view('dashboard.bayer.index', ['kpis'=>$kpis, 'series'=>$d->salesByMonth(), 'top'=>$d->topProducts()]);
    }

    public function data(): void
    {
        \require_role(...AccessPolicy::PUBLISHED);
        $type = $_GET['type'] ?? 'documents';
        if (!is_string($type) || !in_array($type, ['documents','guides','stock'], true)) {
            throw new HttpException(422, 'Dataset inválido.');
        }
        $dates = [];
        foreach (['from','to'] as $key) {
            $value = $_GET[$key] ?? '';
            if (!is_string($value)) throw new HttpException(422, 'Fecha inválida.');
            if ($value !== '') {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) throw new HttpException(422, 'Fecha inválida.');
                $dates[$key] = $value;
            }
        }
        if (isset($dates['from'],$dates['to']) && $dates['from'] > $dates['to']) {
            throw new HttpException(422, 'El rango de fechas es inválido.');
        }
        $rows = (new BayerDataService())->dataset($type);
        \view('dashboard.bayer.data', ['rows'=>$rows,'type'=>$type]);
    }
}
