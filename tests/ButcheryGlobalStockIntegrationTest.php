<?php
// Boucherie par produit : réapprovisionnement du comptoir (DLC la plus proche, lots périmés exclus), retour au froid
// (DLC la plus lointaine d'abord), vente simple au comptoir par produit, refus et absence de choix manuel en transformation.
// Base jetable obligatoire : php scripts/setup_porcine_flow_test.php puis DAGRIL_DB_DATABASE=<base> php tests/ButcheryGlobalStockIntegrationTest.php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/PorcineFlowService.php';
$q=function($sql,$p=[])use($db){$s=$db->prepare($sql);$s->execute($p);return $s;};$v=function($sql,$p=[])use($q){return $q($sql,$p)->fetchColumn();};
$key=function($patch=[]){return array_replace(['operation_key'=>bin2hex(random_bytes(16))],$patch);};
$flow=new PorcineFlowService($db);$user=DagrilIntegrationTest::loginRole('administrateur');
$bouch=(int)$v("SELECT id FROM sites WHERE code='BOUCH'");Auth::selectSite((string)$bouch);
$q("INSERT INTO butchery_items(code,name,item_kind,unit,track_pieces,is_live,status) VALUES(?,?,'finished_product','kg',0,0,'active')",['TST-DLC-'.random_int(1000,9999),'Côtes test DLC']);$item=(int)$db->lastInsertId();
$q("INSERT INTO butchery_counters(site_id,code,name,status,created_by) VALUES(?,?,?,'active',?)",[$bouch,'TST-CPT-'.random_int(1000,9999),'Comptoir test DLC',$user['id']]);$counter=(int)$db->lastInsertId();
$lot=function($number,$expiry,$kg)use($q,$db,$bouch,$item){$q("INSERT INTO butchery_finished_lots(site_id,item_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?,?,?,?,0,1,'available')",[$bouch,$item,$number,date('Y-m-d',strtotime('-10 days')),$expiry,$kg]);return(int)$db->lastInsertId();};
// Lot périmé (exclu), lot à DLC proche, lot à DLC lointaine.
$expired=$lot('L-PERIME',date('Y-m-d',strtotime('-1 day')),50);$soon=$lot('L-PROCHE',date('Y-m-d',strtotime('+1 day')),10);$late=$lot('L-LOIN',date('Y-m-d',strtotime('+6 days')),20);
$counterKg=function($l)use($v,$counter){return(float)$v('SELECT COALESCE(SUM(quantity_kg),0) FROM butchery_counter_stocks WHERE counter_id=? AND finished_lot_id=?',[$counter,$l]);};

// Approvisionnement de 15 kg : 10 kg du lot à DLC proche puis 5 kg du suivant ; le lot périmé n'est jamais pris.
$flow->move($key(['direction'=>'supply','item_id'=>$item,'counter_id'=>$counter,'quantity_kg'=>'15']),$user);
DagrilIntegrationTest::check($counterKg($soon)===10.0&&$counterKg($late)===5.0&&$counterKg($expired)===0.0,'Approvisionnement : 10 kg DLC proche puis 5 kg, lot périmé exclu');
DagrilIntegrationTest::expectException(fn()=>$flow->move($key(['direction'=>'supply','item_id'=>$item,'counter_id'=>$counter,'quantity_kg'=>'16']),$user),'Approvisionnement au-delà du stock valide refusé (15 kg restants, lot périmé non compté)');
DagrilIntegrationTest::check($counterKg($late)===5.0,'Refus sans effet partiel');

// Vente simple de 12 kg : 10 kg du lot à DLC proche puis 2 kg du suivant, une vente par lot.
$sale=$flow->sell($key(['counter_id'=>$counter,'item_id'=>$item,'quantity_kg'=>'12','unit_price'=>'8','customer_name'=>'']),$user);
$sales=$q('SELECT finished_lot_id,quantity_kg FROM butchery_sales WHERE id>=? AND finished_lot_id IN (?,?) ORDER BY id',[$sale,$soon,$late])->fetchAll();
DagrilIntegrationTest::check(count($sales)===2&&(int)$sales[0]['finished_lot_id']===$soon&&(float)$sales[0]['quantity_kg']===10.0&&(int)$sales[1]['finished_lot_id']===$late&&(float)$sales[1]['quantity_kg']===2.0,'Vente : 10 kg DLC proche puis 2 kg, une ligne par lot');
DagrilIntegrationTest::check($counterKg($soon)===0.0&&$counterKg($late)===3.0&&(float)$v('SELECT quantity_kg FROM butchery_finished_lots WHERE id=?',[$soon])===0.0&&$v('SELECT status FROM butchery_finished_lots WHERE id=?',[$soon])==='depleted','Stocks comptoir et lot mis à jour, lot vendu épuisé');
DagrilIntegrationTest::expectException(fn()=>$flow->sell($key(['counter_id'=>$counter,'item_id'=>$item,'quantity_kg'=>'4','unit_price'=>'8']),$user),'Vente au-delà du stock du comptoir refusée');

// Retour au froid de 2 kg : le lot à DLC la plus lointaine repart en premier.
$flow->move($key(['direction'=>'supply','item_id'=>$item,'counter_id'=>$counter,'quantity_kg'=>'4']),$user);
$flow->move($key(['direction'=>'return_cold','item_id'=>$item,'counter_id'=>$counter,'quantity_kg'=>'2']),$user);
DagrilIntegrationTest::check($counterKg($late)===5.0,'Retour au froid : 2 kg repris sur le lot à DLC la plus lointaine');

// Lot explicite (ancien chemin) toujours accepté ; choix manuel en transformation refusé.
$flow->move($key(['direction'=>'return_cold','finished_lot_id'=>$late,'counter_id'=>$counter,'quantity_kg'=>'1']),$user);
DagrilIntegrationTest::check($counterKg($late)===4.0,'Mouvement sur lot explicite conservé');
$message='';try{$flow->transform($key(['source_item_id'=>$item,'lot_mode'=>'manual','source_lot_ids'=>[$late],'quantity_kg'=>'1','production_date'=>date('Y-m-d'),'outputs'=>[['item_id'=>$item,'counter_kg'=>'0','cold_kg'=>'1']]]),$user);}catch(RuntimeException $e){$message=$e->getMessage();}
DagrilIntegrationTest::check(strpos($message,'choix manuel')!==false,'Transformation : choix manuel des lots refusé');
DagrilIntegrationTest::finish();
