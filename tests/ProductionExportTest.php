<?php
require_once __DIR__.'/../app/services/ProductionExportService.php';
$s=new ProductionExportService();
$base=['status'=>'in_progress','machine_code'=>'ROOF-1','machine_name'=>'Machine Élite','silo_id'=>1,'silo_name'=>'Silo A','batch_number'=>'LOT-1','bss_number'=>'BSS-1','started_at'=>'2026-09-13 09:00:00','ended_at'=>null,'actual_input_quantity_kg'=>100,'output_quantity_kg'=>0,'waste_quantity_kg'=>0];
$validated=array_replace($base,['status'=>'validated','ended_at'=>'2026-09-14 10:00:00','output_quantity_kg'=>80,'waste_quantity_kg'=>19]);
$f=array_fill_keys(['search','state','machine','silo','from','to','date-kind'],'');
$check=function($ok,$message){if(!$ok)throw new RuntimeException($message);echo 'OK : '.$message.PHP_EOL;};
$check(count($s->filtered([$base,$validated],array_replace($f,['state'=>'todo'])))===1,'Étapes normalisées');
$check(count($s->filtered([$base,$validated],array_replace($f,['date-kind'=>'end','from'=>'2026-09-14','to'=>'2026-09-14'])))===1,'Dates inclusives et absence de fin exclue');
$check(count($s->filtered([$base],array_replace($f,['search'=>'elite','machine'=>'ROOF-1','silo'=>'1'])))===1,'Recherche accentuée et filtres combinés');
$check($s->filtered([$base],array_replace($f,['from'=>'2026-09-15','to'=>'2026-09-13']))===[],'Période inversée sans résultat');
$check($s->rows([$base])[0][7]==='—','Résultats non saisis sans faux zéro');
$t=$s->totals([$base,$validated]);$check($t['flour']==80&&$t['validated']===1&&$t['loaded']==200,'Totaux de résultats limités aux lots validés');
