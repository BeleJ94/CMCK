<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/PorcineFlowService.php';
require_once __DIR__.'/../app/models/Butchery.php';
require_once __DIR__.'/../app/core/Controller.php';
require_once __DIR__.'/../app/controllers/PorcineFlowController.php';
$flow=new PorcineFlowService($db);$pig=new PigService($db);$user=DagrilIntegrationTest::loginRole('administrateur');
$mut=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");$bouch=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='BOUCH'");Auth::selectSite((string)$mut);
function q($sql,$params=[]){global $db;$q=$db->prepare($sql);$q->execute($params);return $q;}
function keyData($patch=[]){return array_replace(['operation_key'=>bin2hex(random_bytes(16))],$patch);}
function ok($condition,$message){DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
function no($callback,$message){DagrilIntegrationTest::expectException($callback,$message);}
function snapshot(){return [q('SELECT COALESCE(SUM(quantity_kg),0) FROM butchery_raw_lots')->fetchColumn(),q('SELECT COALESCE(SUM(reserved_kg),0) FROM butchery_raw_lots')->fetchColumn(),q('SELECT COALESCE(SUM(quantity_kg),0) FROM butchery_finished_lots')->fetchColumn(),q('SELECT COALESCE(SUM(quantity_kg),0) FROM butchery_counter_stocks')->fetchColumn()];}
q("INSERT INTO livestock_facilities(site_id,code,name,facility_type,capacity_heads,status) VALUES(?,'FLOW-TEST','Porcherie de validation','housing',500,'active')",[$mut]);$facility=(int)$db->lastInsertId();
$adult=$pig->register(['site_id'=>$mut,'facility_id'=>$facility,'sex'=>'female','born_on'=>date('Y-m-d',strtotime('-5 months')),'heads'=>'20','submission_key'=>bin2hex(random_bytes(16))],$user);
$young=$pig->register(['site_id'=>$mut,'facility_id'=>$facility,'sex'=>'male','born_on'=>date('Y-m-d'),'heads'=>'3','submission_key'=>bin2hex(random_bytes(16))],$user);
$expiry=date('Y-m-d',strtotime('+4 days'));
$data=keyData(['batch_id'=>$adult,'heads'=>'10','weight_kg'=>'200','slaughtered_at'=>date('Y-m-d H:i:s'),'expiry_date'=>$expiry]);
no(fn()=>$flow->slaughter(array_replace($data,['batch_id'=>$young]),$user),'Abattage d’un groupe A refusé');
$conversion=$flow->slaughter($data,$user);ok($flow->slaughter($data,$user)===$conversion,'Abattage idempotent');
no(fn()=>$flow->slaughter(keyData(array_replace($data,['operation_key'=>bin2hex(random_bytes(16)),'heads'=>11])),$user),'Effectif déjà engagé pris en compte');
$stock=$flow->validateSlaughter($conversion,$user);ok((int)q('SELECT current_heads FROM livestock_batches WHERE id=?',[$adult])->fetchColumn()===10,'Validation retire exactement les porcs abattus');ok((float)q('SELECT quantity_kg FROM livestock_weight_stocks WHERE id=?',[$stock])->fetchColumn()===200.0,'Stock de carcasses pesées créé');
no(fn()=>$flow->validateSlaughter($conversion,$user),'Double validation d’abattage refusée');
$send=keyData(['weight_stock_id'=>$stock,'quantity_kg'=>'120','truck_plate'=>'FLOW-01','driver_name'=>'Chauffeur test']);$t1=$flow->shipment($send,$user);ok($flow->shipment($send,$user)===$t1,'Envoi idempotent');
no(fn()=>$flow->shipment(keyData(['weight_stock_id'=>$stock,'quantity_kg'=>'81','truck_plate'=>'FLOW-02','driver_name'=>'Test']),$user),'Brouillons d’envoi empêchent le surengagement');
$t2=$flow->shipment(keyData(['weight_stock_id'=>$stock,'quantity_kg'=>'80','truck_plate'=>'FLOW-02','driver_name'=>'Test']),$user);ok($t2!==$t1,'Plusieurs envois partiels pour le même lot');
$flow->advanceShipment($t1,'approve',$user);$flow->advanceShipment($t2,'approve',$user);ok((float)q('SELECT reserved_kg FROM livestock_weight_stocks WHERE id=?',[$stock])->fetchColumn()===200.0,'Réservation cumulée correcte');
$flow->advanceShipment($t1,'dispatch',$user);ok((float)q('SELECT quantity_kg FROM livestock_weight_stocks WHERE id=?',[$stock])->fetchColumn()===80.0,'Expédition partielle diminue le stock ferme');
$flow->cancelShipment($t2,keyData(['reason'=>'Report de l’envoi']),$user);ok((float)q('SELECT reserved_kg FROM livestock_weight_stocks WHERE id=?',[$stock])->fetchColumn()===0.0,'Annulation d’envoi libère la réservation');
no(fn()=>$flow->advanceShipment($t2,'dispatch',$user),'Envoi annulé non expédiable');
Auth::selectSite((string)$bouch);
no(fn()=>$flow->shipment($send,$user),'Écriture ferme depuis le mauvais contexte refusée');
$receipt=$flow->arrival(keyData(['transfer_id'=>$t1]),$user);ok((int)q('SELECT COUNT(*) FROM butchery_raw_lots')->fetchColumn()===0,'Arrivée en attente : aucun stock vendable créé');
no(fn()=>$flow->receive(keyData(['receipt_id'=>$receipt,'visual_conform'=>'1']),$user),'Contrôle documentaire obligatoire');
$receiveData=keyData(['receipt_id'=>$receipt,'visual_conform'=>'1','documents_conform'=>'1','temperature_c'=>'4']);$flow->receive($receiveData,$user);ok($flow->receive($receiveData,$user)===$receipt,'Contrôle de réception idempotent');
$raw=(int)q('SELECT id FROM butchery_raw_lots WHERE receipt_id=?',[$receipt])->fetchColumn();ok((float)q('SELECT quantity_kg FROM butchery_raw_lots WHERE id=?',[$raw])->fetchColumn()===120.0,'Réception entrée en chambre froide');ok(q('SELECT expiry_date FROM butchery_raw_lots WHERE id=?',[$raw])->fetchColumn()===$expiry,'DLC d’origine conservée');
no(fn()=>$flow->receive(keyData(['receipt_id'=>$receipt,'visual_conform'=>'1','documents_conform'=>'1']),$user),'Seconde réception refusée');
$counter=(int)q("SELECT id FROM butchery_counters WHERE code='PRINCIPAL'")->fetchColumn();$item1=(int)q("SELECT id FROM butchery_items WHERE code='PORC-COTELETTE'")->fetchColumn();$item2=(int)q("SELECT id FROM butchery_items WHERE code='PORC-MAKOSO'")->fetchColumn();
$transform=keyData(['raw_lot_id'=>$raw,'quantity_kg'=>'100','production_date'=>date('Y-m-d'),'counter_id'=>$counter,'outputs'=>[['item_id'=>$item1,'cold_kg'=>'30','counter_kg'=>'20','expiry_date'=>$expiry],['item_id'=>$item2,'cold_kg'=>'25','counter_kg'=>'15','expiry_date'=>$expiry]]]);
$before=snapshot();no(fn()=>$flow->transform(array_replace($transform,['quantity_kg'=>'50']),$user),'Poids des produits supérieur à la matière refusé');ok(snapshot()===$before,'Refus de transformation sans effet partiel');
$order=$flow->transform($transform,$user);ok($flow->transform($transform,$user)===$order,'Transformation idempotente');ok((float)q('SELECT reserved_kg FROM butchery_raw_lots WHERE id=?',[$raw])->fetchColumn()===100.0,'Matière réservée dès la soumission');
no(fn()=>$flow->transform(array_replace($transform,keyData(['quantity_kg'=>'100'])),$user),'Deuxième consommation du même stock réservée refusée');
$validate=keyData();$flow->validateTransform($order,$validate,$user);ok($flow->validateTransform($order,$validate,$user)===$order,'Validation de transformation idempotente');ok((float)q('SELECT quantity_kg FROM butchery_raw_lots WHERE id=?',[$raw])->fetchColumn()===20.0&&(float)q('SELECT reserved_kg FROM butchery_raw_lots WHERE id=?',[$raw])->fetchColumn()===0.0,'Validation consomme une partie et libère la réservation');
$lots=q('SELECT l.* FROM butchery_finished_lots l JOIN butchery_order_outputs o ON o.id=l.order_output_id WHERE o.order_id=? ORDER BY o.id',[$order])->fetchAll();$lot1=(int)$lots[0]['id'];$lot2=(int)$lots[1]['id'];
ok(count($lots)===2&&array_sum(array_column($lots,'quantity_kg'))==90,'Plusieurs produits obtenus, pertes distinctes');ok($flow->coldAvailable($lot1)===30.0&&$flow->coldAvailable($lot2)===25.0,'Répartition initiale froid/comptoir exacte');
$counterStock=(int)q('SELECT id FROM butchery_counter_stocks WHERE finished_lot_id=?',[$lot1])->fetchColumn();$saleData=keyData(['counter_stock_id'=>$counterStock,'quantity_kg'=>'5','unit_price'=>'10','customer_name'=>'Client test']);$sale=$flow->sell($saleData,$user);ok($flow->sell($saleData,$user)===$sale,'Vente idempotente');ok((float)q('SELECT quantity_kg FROM butchery_counter_stocks WHERE id=?',[$counterStock])->fetchColumn()===15.0,'Vente déduite du seul comptoir');ok($flow->coldAvailable($lot1)===30.0,'Vente ne modifie pas la quantité en chambre froide');
$before=snapshot();no(fn()=>$flow->sell(array_replace($saleData,keyData(['quantity_kg'=>'16'])),$user),'Survente du comptoir refusée malgré du stock au froid');ok(snapshot()===$before,'Refus de vente sans écriture partielle');
$move=keyData(['finished_lot_id'=>$lot1,'counter_id'=>$counter,'direction'=>'supply','quantity_kg'=>'10']);$flow->move($move,$user);$flow->move($move,$user);ok($flow->coldAvailable($lot1)===20.0&&(float)q('SELECT quantity_kg FROM butchery_counter_stocks WHERE id=?',[$counterStock])->fetchColumn()===25.0,'Réapprovisionnement sans double mouvement');
$flow->move(keyData(['finished_lot_id'=>$lot1,'counter_id'=>$counter,'direction'=>'return_cold','quantity_kg'=>'5']),$user);ok($flow->coldAvailable($lot1)===25.0&&(float)q('SELECT quantity_kg FROM butchery_counter_stocks WHERE id=?',[$counterStock])->fetchColumn()===20.0,'Retour du comptoir au froid conserve le total');
no(fn()=>(new Butchery())->sell(['lot_type'=>'finished','lot_id'=>$lot1,'quantity_kg'=>1,'unit_price'=>2],$user),'Ancienne route de vente ne contourne pas le comptoir');
$cancelData=keyData(['raw_lot_id'=>$raw,'quantity_kg'=>'10','production_date'=>date('Y-m-d'),'outputs'=>[['item_id'=>$item1,'cold_kg'=>'9','counter_kg'=>'0','expiry_date'=>$expiry]]]);$cancelOrder=$flow->transform($cancelData,$user);$flow->cancelTransform($cancelOrder,keyData(['reason'=>'Erreur de saisie']),$user);ok((float)q('SELECT reserved_kg FROM butchery_raw_lots WHERE id=?',[$raw])->fetchColumn()===0.0,'Annulation de transformation libère la matière');
$secondCounter=$flow->createCounter(keyData(['name'=>'Comptoir secondaire']),$user);$flow->move(keyData(['finished_lot_id'=>$lot2,'counter_id'=>$secondCounter,'direction'=>'supply','quantity_kg'=>'3']),$user);ok((float)q('SELECT quantity_kg FROM butchery_counter_stocks WHERE counter_id=? AND finished_lot_id=?',[$secondCounter,$lot2])->fetchColumn()===3.0,'Plusieurs comptoirs avec stocks séparés');
q('UPDATE butchery_finished_lots SET expiry_date=? WHERE id=?',[date('Y-m-d',strtotime('-1 day')),$lot1]);no(fn()=>$flow->sell(array_replace($saleData,keyData()),$user),'Vente après DLC refusée');q('UPDATE butchery_finished_lots SET expiry_date=? WHERE id=?',[$expiry,$lot1]);
foreach(['receipts','coldRoom','production','counters'] as $method){ob_start();(new PorcineFlowController())->$method();$html=ob_get_clean();ok(strpos($html,'data-flow-directory')!==false&&strpos($html,'Warning:')===false,'Écran boucherie '.$method.' rendu sans avertissement');}
Auth::selectSite((string)$mut);foreach(['slaughters','shipments'] as $method){ob_start();(new PorcineFlowController())->$method();$html=ob_get_clean();ok(strpos($html,'data-flow-directory')!==false&&strpos($html,'Warning:')===false,'Écran ferme '.$method.' rendu sans avertissement');}
$fixture=['database'=>getenv('DAGRIL_DB_DATABASE'),'farm'=>$mut,'butchery'=>$bouch,'facility'=>$facility,'adult'=>$adult,'expiry'=>$expiry,'counter'=>$counter,'item1'=>$item1,'item2'=>$item2,'raw'=>$raw,'lot1'=>$lot1,'lot2'=>$lot2,'counterStock'=>$counterStock];
if(getenv('DAGRIL_FLOW_FIXTURE'))file_put_contents(getenv('DAGRIL_FLOW_FIXTURE'),json_encode($fixture));
DagrilIntegrationTest::finish();
