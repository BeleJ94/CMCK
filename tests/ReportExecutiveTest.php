<?php
require __DIR__.'/../app/helpers/functions.php';require __DIR__.'/../app/core/Model.php';require __DIR__.'/../app/models/ReportModel.php';
class ReportExecutiveFixture extends ReportModel {
 public function __construct(){}
 public function dailyReception(array $f){return [['status'=>'validated','poids_net'=>100],['status'=>'cancelled','poids_net'=>900]];}
 public function production(array $f){return [['status'=>'validated','input_quantity_kg'=>100,'output_quantity_kg'=>80,'waste_quantity_kg'=>20],['status'=>'draft','input_quantity_kg'=>500,'output_quantity_kg'=>500,'waste_quantity_kg'=>0]];}
 public function waste(array $f){return [['status'=>'validated','input_quantity_kg'=>10],['status'=>'cancelled','input_quantity_kg'=>20]];}
 public function packaging(array $f){return [['status'=>'validated','total_weight_kg'=>70],['status'=>'cancelled','total_weight_kg'=>500]];}
 public function distribution(array $f){return [['status'=>'validated','total_weight_kg'=>90],['status'=>'cancelled','total_weight_kg'=>500]];}
}
$summary=(new ReportExecutiveFixture())->globalSummary([]);
foreach(['received_kg'=>100,'produced_kg'=>80,'average_yield'=>80,'distributed_kg'=>90,'net_finished_flow_kg'=>-20,'waste_processed_kg'=>10] as $key=>$expected)if($summary[$key]!=$expected)throw new RuntimeException($key);
$filters=['start_date'=>'2026-09-01','end_date'=>'2026-09-07','supplier_id'=>'','machine_id'=>''];$previousFilters=['start_date'=>'2026-08-25','end_date'=>'2026-08-31'];$previousSummary=$summary;$periodDays=7;$directionRows=[];$references=['suppliers'=>[],'machines'=>[]];
set_error_handler(static function($n,$m,$f,$l){throw new ErrorException($m,0,$n,$f,$l);});
foreach(['','pdf','excel'] as $exportMode){ob_start();require __DIR__.'/../app/views/reports/index.php';$html=ob_get_clean();if(strpos($html,'Les sorties dépassent')===false)throw new RuntimeException('Missing executive insight');if($exportMode&&strpos($html,'<form')!==false)throw new RuntimeException('Export contains filters');}
restore_error_handler();echo "OK : volumes validés, rendement pondéré, flux net, synthèse et exports.\n";
