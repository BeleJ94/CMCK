<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();require_once __DIR__.'/../app/services/PorcineFlowService.php';
$fixture=json_decode(file_get_contents(getenv('DAGRIL_FLOW_FIXTURE')),true,512,JSON_THROW_ON_ERROR);
if($fixture['database']!==getenv('DAGRIL_DB_DATABASE'))throw new RuntimeException('Fixture incompatible.');
$user=DagrilIntegrationTest::loginRole('administrateur');Auth::selectSite((string)$fixture['butchery']);$flow=new PorcineFlowService($db);
function sq($sql,$p=[]){global$db;$q=$db->prepare($sql);$q->execute($p);return$q;}
function ck($condition,$label){DagrilIntegrationTest::check($condition,$label);if(!$condition)throw new RuntimeException($label);}
function fresh($patch=[]){return array_merge(['operation_key'=>bin2hex(random_bytes(16))],$patch);}
function snap(){return[sq('SELECT SUM(quantity_kg),SUM(reserved_kg) FROM butchery_raw_lots')->fetch(PDO::FETCH_NUM),sq('SELECT COUNT(*) FROM butchery_orders')->fetchColumn(),sq('SELECT COUNT(*) FROM butchery_order_consumptions')->fetchColumn()];}
$code='AUTO-'.strtoupper(bin2hex(random_bytes(4)));
sq("INSERT INTO butchery_items(code,name,item_kind,unit,status) VALUES(?,?,'raw_material','kg','active')",[$code,'Carcasse · test automatique']);$item=(int)$db->lastInsertId();
sq("INSERT INTO butchery_recipes(code,name,status,created_by) VALUES(?,?,'active',?)",[$code,'Découpe automatique test',$user['id']]);$recipe=(int)$db->lastInsertId();
sq("INSERT INTO butchery_recipe_versions(recipe_id,version_number,status,effective_from,created_by) VALUES(?,1,'active',?,?)",[$recipe,date('Y-m-d'),$user['id']]);$version=(int)$db->lastInsertId();
sq("INSERT INTO butchery_recipe_components(recipe_version_id,item_id,component_type,percentage) VALUES(?,?,'raw_material',100)",[$version,$item]);
function lot($suffix,$qty,$reserved,$days,$cost=2,$state='available',$site=null,$production=null,$deleted=null){global$db,$fixture,$item,$code;sq('INSERT INTO butchery_raw_lots(site_id,item_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status,deleted_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$site??$fixture['butchery'],$item,$code.'-'.$suffix,$production??date('Y-m-d'),date('Y-m-d',strtotime(($days>=0?'+':'').$days.' days')),$qty,$reserved,$cost,$state,$deleted]);return(int)$db->lastInsertId();}
$late=lot('TARD',35,5,5,3);$early=lot('TOT',20,0,2);
$excluded=[lot('EXPIRE',100,0,-1),lot('QUARANTAINE',100,0,1,2,'quarantine'),lot('SUPPRIME',100,0,1,2,'available',null,null,date('Y-m-d H:i:s')),lot('AUTRE-SITE',100,0,1,2,'available',$fixture['farm']),lot('FUTUR',100,0,8,2,'available',null,date('Y-m-d',strtotime('+1 day')))];
$expiry=date('Y-m-d',strtotime('+2 days'));
$data=fresh(['source_item_id'=>(string)$item,'quantity_kg'=>'50','production_date'=>date('Y-m-d'),'outputs'=>[['item_id'=>$fixture['item1'],'quantity_kg'=>'20','expiry_date'=>$expiry],['item_id'=>$fixture['item2'],'quantity_kg'=>'30','expiry_date'=>$expiry]]]);
$before=snap();DagrilIntegrationTest::expectException(fn()=>$flow->transform(array_replace($data,fresh(['quantity_kg'=>'51','loss_confirmed'=>'1'])),$user),'Stock insuffisant : aucun lot non utilisable ne complète le prélèvement');ck(snap()===$before,'Refus sans réservation ou ordre partiel');
$order=$flow->transform($data,$user);ck($flow->transform($data,$user)===$order,'Soumission automatique idempotente après réservation');
$cons=sq('SELECT raw_lot_id,quantity_kg FROM butchery_order_consumptions WHERE order_id=? ORDER BY id',[$order])->fetchAll();ck(count($cons)===2&&(int)$cons[0]['raw_lot_id']===$early&&(float)$cons[0]['quantity_kg']===20.0&&(int)$cons[1]['raw_lot_id']===$late&&(float)$cons[1]['quantity_kg']===30.0,'DLC proche prélevée en premier, complément sur le second lot');
ck((float)sq('SELECT reserved_kg FROM butchery_raw_lots WHERE id=?',[$late])->fetchColumn()===35.0,'Autres réservations conservées');
ck((float)sq('SELECT quantity_kg FROM butchery_raw_lots WHERE id=?',[$early])->fetchColumn()===20.0,'Avant validation : stock physique inchangé');
ck((int)sq('SELECT COUNT(*) FROM butchery_finished_lots l JOIN butchery_order_outputs o ON o.id=l.order_output_id WHERE o.order_id=?',[$order])->fetchColumn()===0,'Avant validation : aucun produit vendable créé');
$before=snap();DagrilIntegrationTest::expectException(fn()=>$flow->transform(array_replace($data,fresh(['quantity_kg'=>'1','outputs'=>[['item_id'=>$fixture['item1'],'quantity_kg'=>'1','expiry_date'=>$expiry]]])),$user),'Une seconde transformation ne réutilise pas les lots déjà réservés');ck(snap()===$before,'Surengagement multi-lots refusé sans effet partiel');
sq("UPDATE butchery_raw_lots SET status='quarantine' WHERE id=?",[$early]);$before=snap();DagrilIntegrationTest::expectException(fn()=>$flow->validateTransform($order,fresh(),$user),'Mise en quarantaine après réservation : validation refusée');ck(snap()===$before,'Refus de validation atomique');sq("UPDATE butchery_raw_lots SET status='available' WHERE id=?",[$early]);
$flow->validateTransform($order,fresh(),$user);
ck((float)sq('SELECT quantity_kg FROM butchery_raw_lots WHERE id=?',[$early])->fetchColumn()===0.0&&(float)sq('SELECT quantity_kg FROM butchery_raw_lots WHERE id=?',[$late])->fetchColumn()===5.0,'Validation consomme exactement 20 + 30 kg');
ck((float)sq('SELECT reserved_kg FROM butchery_raw_lots WHERE id=?',[$late])->fetchColumn()===5.0,'Validation libère uniquement sa propre réservation');
ck((float)sq('SELECT total_cost FROM butchery_orders WHERE id=?',[$order])->fetchColumn()===130.0,'Coût pondéré des deux lots conservé');
ck((float)sq('SELECT SUM(l.quantity_kg) FROM butchery_finished_lots l JOIN butchery_order_outputs o ON o.id=l.order_output_id WHERE o.order_id=?',[$order])->fetchColumn()===50.0,'Les 50 kg obtenus sont stockés entièrement au froid par défaut');
ck((int)sq('SELECT COUNT(*) FROM butchery_raw_movements WHERE reference_type=\'butchery_orders\' AND reference_id=?',[$order])->fetchColumn()===2,'Deux mouvements traçables jusqu’à chaque lot source');
foreach($excluded as$id)ck((float)sq('SELECT reserved_kg FROM butchery_raw_lots WHERE id=?',[$id])->fetchColumn()===0.0,'Lot exclu non réservé #'.$id);
$a=lot('MANUEL-A',10,0,1);$b=lot('MANUEL-B',10,0,3);
$manual=array_replace($data,fresh(['quantity_kg'=>'5','lot_mode'=>'manual','source_lot_ids'=>[(string)$b],'outputs'=>[['item_id'=>$fixture['item1'],'quantity_kg'=>'4','expiry_date'=>$expiry]],'loss_confirmed'=>'1']));
$unconfirmed=$manual;unset($unconfirmed['loss_confirmed']);DagrilIntegrationTest::expectException(fn()=>$flow->transform($unconfirmed,$user),'Écart non confirmé refusé côté serveur');
$m=$flow->transform($manual,$user);ck((int)sq('SELECT raw_lot_id FROM butchery_order_consumptions WHERE order_id=?',[$m])->fetchColumn()===$b,'Choix manuel respecté même si une autre DLC est plus proche');
$flow->cancelTransform($m,fresh(['reason'=>'Test annulation']),$user);ck((float)sq('SELECT reserved_kg FROM butchery_raw_lots WHERE id=?',[$b])->fetchColumn()===0.0,'Annulation libère les réservations');
DagrilIntegrationTest::expectException(fn()=>$flow->transform(array_replace($manual,fresh(['source_lot_ids'=>[]])),$user),'Choix manuel vide refusé');
DagrilIntegrationTest::expectException(fn()=>$flow->transform(array_replace($manual,fresh(['source_lot_ids'=>[$fixture['raw']]])),$user),'Lot d’un autre produit refusé');
DagrilIntegrationTest::expectException(fn()=>$flow->transform(array_replace($manual,fresh(['source_lot_ids'=>[$excluded[3]]])),$user),'Lot d’un autre site refusé');
DagrilIntegrationTest::expectException(fn()=>$flow->transform(array_replace($manual,fresh(['outputs'=>[['item_id'=>$fixture['item1'],'quantity_kg'=>'4','counter_kg'=>'5','expiry_date'=>$expiry]]])),$user),'Répartition au comptoir supérieure au produit refusée');
$split=array_replace($data,fresh(['quantity_kg'=>'5','counter_id'=>(string)$fixture['counter'],'outputs'=>[['item_id'=>$fixture['item1'],'quantity_kg'=>'5','counter_kg'=>'2','expiry_date'=>$expiry]]]));$s=$flow->transform($split,$user);$flow->validateTransform($s,fresh(),$user);
$finished=(int)sq('SELECT l.id FROM butchery_finished_lots l JOIN butchery_order_outputs o ON o.id=l.order_output_id WHERE o.order_id=?',[$s])->fetchColumn();ck($flow->coldAvailable($finished)===3.0,'Répartition : 3 kg au froid');ck((float)sq('SELECT quantity_kg FROM butchery_counter_stocks WHERE finished_lot_id=?',[$finished])->fetchColumn()===2.0,'Répartition : 2 kg au comptoir');
if(getenv('DAGRIL_TRANSFORM_FIXTURE'))file_put_contents(getenv('DAGRIL_TRANSFORM_FIXTURE'),json_encode(['database'=>getenv('DAGRIL_DB_DATABASE'),'sourceItem'=>$item,'manualA'=>$a,'manualB'=>$b,'item1'=>$fixture['item1'],'item2'=>$fixture['item2'],'expiry'=>$expiry,'counter'=>$fixture['counter']]));
DagrilIntegrationTest::finish();
