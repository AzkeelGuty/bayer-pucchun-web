<?php
namespace App\Controllers;

use App\Services\DashboardService;
use App\Policies\AccessPolicy;

class DashboardController
{
    public function index(): void
    {
        \require_role(...AccessPolicy::INTERNAL);
        $s = new DashboardService();
        // The unrestricted internal KPI set is not a limited digitador dashboard.
        if (\has_role('DIGITADOR') && !\has_role('ADMIN','SUPERVISOR','GERENCIA')) {
            \redirect('/documentos');
        }
        \view('dashboard.puchun.index', ['kpis'=>$s->kpis(), 'series'=>$s->salesByMonth(), 'top'=>$s->topProducts()]);
    }
}
