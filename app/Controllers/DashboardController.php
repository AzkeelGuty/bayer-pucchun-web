<?php
namespace App\Controllers; use App\Services\DashboardService;
class DashboardController {public function index():void{\require_auth();$s=new DashboardService();\view('dashboard.puchun.index',['kpis'=>$s->kpis(),'series'=>$s->salesByMonth(),'top'=>$s->topProducts()]);}}
