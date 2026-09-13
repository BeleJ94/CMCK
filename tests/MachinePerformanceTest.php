<?php
require __DIR__.'/integration/TestBootstrap.php';$db=DagrilIntegrationTest::boot();$user=DagrilIntegrationTest::loginRole('administrateur','all');require dirname(__DIR__).'/app/models/Machine.php';$model=new Machine();
$db->beginTransaction();try{
$site=DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='MINO'");$silo=DagrilIntegrationTest::row('SELECT * FROM silos WHERE deleted_at IS NULL AND product_id IS NOT NULL LIMIT 1');
$id=$model->createMachine(['site_id'=>$site,'name'=>'Test performances '.bin2hex(random_bytes(4)),'machine_type'=>'main','capacity_kg_hour'=>'','status'=>'active']);
$insert=function($sql,$p)use($db){$q=$db->prepare($sql);$q->execute($p);return $db->lastInsertId();};
foreach([[100,80,'validated','pending','2026-09-05'],[900,450,'validated','validated','2026-09-06'],[500,400,'in_progress','pending','2026-09-07'],[999,999,'validated','cancelled','2026-09-08'],[100,100,'validated','pending','2025-01-01']] as$i=>$r){[$input,$output,$status,$feedState,$day]=$r;$fid=$insert('INSERT INTO machine_feeds(site_id,machine_id,silo_id,product_id,quantity_kg,authorized_quantity_kg,fed_at,status,created_by) VALUES(?,?,?,?,?,?,?,?,?)',[$site,$id,$silo['id'],$silo['product_id'],$input,$input,$day.' 09:00:00',$feedState,$user['id']]);$insert('INSERT INTO production_batches(site_id,machine_feed_id,product_id,batch_number,input_quantity_kg,actual_input_quantity_kg,output_quantity_kg,waste_quantity_kg,started_at,status,created_by) VALUES(?,?,?,?,?,?,?,0,?,?,?)',[$site,$fid,$silo['product_id'],'MP-'.bin2hex(random_bytes(5)),$input,$input,$output,$day.' 09:00:00',$status,$user['id']]);}
$row=array_column($model->allWithPerformance('2026-09-01','2026-09-30'),null,'id')[$id];
DagrilIntegrationTest::check($row['fed_quantity_kg']===1500.0,'Alimentations de la période sans opérations annulées');
DagrilIntegrationTest::check($row['output_quantity_kg']===530.0,'Production validée uniquement');
DagrilIntegrationTest::check(abs($row['yield_rate']-53)<.0001,'Rendement pondéré : 530 / 1000 = 53 %, et non moyenne des rendements');
DagrilIntegrationTest::check($row['open_count']===1&&$row['batches_count']===3,'Comptage distinct des opérations ouvertes et des lots de la période');
DagrilIntegrationTest::check($row['capacity_kg_hour']===null,'Capacité inconnue conservée à NULL');
$empty=array_column($model->allWithPerformance('2000-01-01','2000-01-31'),null,'id')[$id];DagrilIntegrationTest::check($empty['yield_rate']===null&&$empty['fed_quantity_kg']===0.0&&$empty['open_count']===1&&count($empty['open_operations'])===1&&$empty['open_quantity_kg']===500.0&&$empty['open_operations'][0]['quantity']==500,'Période vide sans faux rendement ; activité ouverte indépendante');
$wasteStock=DagrilIntegrationTest::row('SELECT id,site_id FROM waste_stocks WHERE deleted_at IS NULL LIMIT 1');
$wid=$model->createMachine(['site_id'=>$wasteStock['site_id'],'name'=>'Traitement test '.bin2hex(random_bytes(4)),'machine_type'=>'waste','capacity_kg_hour'=>'','status'=>'active']);
foreach([[100,70,'validated'],[50,40,'pending'],[999,999,'cancelled']] as$r)$insert('INSERT INTO waste_processings(site_id,waste_stock_id,machine_id,input_quantity_kg,output_quantity_kg,processed_at,status,created_by) VALUES(?,?,?,?,?,?,?,?)',[$wasteStock['site_id'],$wasteStock['id'],$wid,$r[0],$r[1],'2026-09-10 09:00:00',$r[2],$user['id']]);
$w=array_column($model->allWithPerformance('2026-09-01','2026-09-30'),null,'id')[$wid];DagrilIntegrationTest::check($w['fed_quantity_kg']===150.0&&$w['output_quantity_kg']===70.0&&$w['yield_rate']===70.0&&$w['open_count']===1,'Traitements déchets : quantités, validations, rendement et annulations');
Auth::selectSite((string)$silo['site_id']);if((int)$silo['site_id']!==(int)$site)DagrilIntegrationTest::check(!isset(array_column($model->allWithPerformance(),null,'id')[$id]),'Isolation du site appliquée aux machines');
}finally{if($db->inTransaction())$db->rollBack();}DagrilIntegrationTest::finish();
