<?php
namespace App\Controllers; use App\Services\{BayerDataService,DashboardService};
class BayerController {public function index():void{\require_role('ADMIN','GERENCIA','BAYER');$d=new DashboardService();\view('dashboard.bayer.index',['kpis'=>$d->kpis(true),'series'=>$d->salesByMonth(),'top'=>$d->topProducts()]);}public function data():void{\require_role('ADMIN','GERENCIA','BAYER');$type=(string)\input('type','documents');$rows=(new BayerDataService())->dataset($type);\view('dashboard.bayer.data',['rows'=>$rows,'type'=>$type]);}}
