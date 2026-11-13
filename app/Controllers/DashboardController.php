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
        $s = new DashboardService();
        $digitadorOnly=\has_role('DIGITADOR') && !\has_role('ADMIN','SUPERVISOR','GERENCIA');
        $ownerId=$digitadorOnly ? (int)(\auth_user()['id']??0) : null;
        \view('dashboard.puchun.index', [
            'kpis'=>$s->kpis(false,$ownerId),
            'series'=>$s->salesByMonth($ownerId),
            'top'=>$s->topProducts($ownerId),
            'statuses'=>$s->statusDistribution($ownerId),
            'recent'=>$s->recentActivity($ownerId),
            'digitadorOnly'=>$digitadorOnly,
        ]);
    }
}
