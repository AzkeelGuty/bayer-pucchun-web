<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DashboardService;
use App\Policies\AccessPolicy;

final class DashboardController
{
    public function index(): void
    {
        \require_role(...AccessPolicy::INTERNAL);
        if (\has_role('DIGITADOR') && !\has_role('ADMIN','SUPERVISOR','GERENCIA')) {
            \redirect('/documentos');
        }
        $s = new DashboardService();
        \view('dashboard.puchun.index', [
            'kpis'=>$s->kpis(),
            'series'=>$s->salesByMonth(),
            'top'=>$s->topProducts(),
            'statuses'=>$s->statusDistribution(),
            'recent'=>$s->recentActivity(),
        ]);
    }
}
