<?php
require dirname(__DIR__) . '/app/helpers/functions.php';
require dirname(__DIR__) . '/app/core/Database.php';
require dirname(__DIR__) . '/app/core/Model.php';
require dirname(__DIR__) . '/app/core/Auth.php';
require dirname(__DIR__) . '/app/models/Pelletization.php';

Auth::start();
ob_start();
$_SERVER['HTTP_USER_AGENT'] = 'PelletizationTest';
$db = Database::getInstance()->connection();
$failures = [];
$ids = [];
function ptq($db, $sql, array $params = []) { $q=$db->prepare($sql); $q->execute($params); return $q; }
function ptok($condition, $message) { global $failures; echo ($condition ? 'OK  - ' : 'FAIL - ') . $message . "\n"; if (!$condition) $failures[]=$message; }
function ptdocs($db, $entity, $id) { $docs=ptq($db,'SELECT id FROM documents WHERE entity_type=? AND entity_id=?',[$entity,$id])->fetchAll(PDO::FETCH_COLUMN); foreach($docs as $doc){$instances=ptq($db,'SELECT id FROM workflow_instances WHERE document_id=?',[$doc])->fetchAll(PDO::FETCH_COLUMN);foreach($instances as $instance){foreach(['workflow_notifications','workflow_level_approvals','workflow_transitions'] as $table)ptq($db,"DELETE FROM $table WHERE workflow_instance_id=?",[$instance]);ptq($db,'DELETE FROM workflow_instances WHERE id=?',[$instance]);}ptq($db,'DELETE FROM document_status_history WHERE document_id=?',[$doc]);ptq($db,'DELETE FROM documents WHERE id=?',[$doc]);}}

try {
    $users=ptq($db,"SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='active' AND u.deleted_at IS NULL AND r.slug IN('administrateur','direction') ORDER BY FIELD(r.slug,'direction','administrateur'),u.id LIMIT 2")->fetchAll();
    if(count($users)<2) throw new RuntimeException('Deux utilisateurs administrateur/direction sont requis.');
    $creator=$users[0]; $validator=$users[1];
    $pell=(int)ptq($db,"SELECT id FROM sites WHERE code='PELL'")->fetchColumn();
    $wasteType=(int)ptq($db,"SELECT id FROM waste_types ORDER BY id LIMIT 1")->fetchColumn();
    $wasteProduct=(int)ptq($db,'SELECT product_id FROM waste_types WHERE id=?',[$wasteType])->fetchColumn();
    $additive=(int)ptq($db,"SELECT id FROM pellet_additives WHERE code='LIANT'")->fetchColumn();
    ptq($db,"INSERT INTO waste_stocks(site_id,product_id,waste_type_id,quantity_kg,quality_grade,average_unit_cost,available_at,storage_status,status) VALUES(?,?,?,?,?, ?,NOW(),'available','active')",[$pell,$wasteProduct,$wasteType,1000,'test',0.5]);
    $ids['waste']=(int)$db->lastInsertId();
    ptq($db,"INSERT INTO waste_stock_movements(waste_stock_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reference_type,reference_id,movement_at,created_by) VALUES(?,'production_in',1000,0,1000,'test',?,NOW(),?)",[$ids['waste'],$ids['waste'],$creator['id']]);
    Auth::login($creator); Auth::selectSite((string)$pell); $creator=Auth::user(); $model=new Pelletization();
    $purchase=$model->buyAdditive(['additive_id'=>$additive,'quantity_kg'=>100,'unit_cost'=>2],$creator); $ids['purchase']=$purchase;
    try{$model->receiveAdditive($purchase,$creator);$blocked=false;}catch(Exception $e){$blocked=true;} ptok($blocked,'Séparation créateur/réceptionnaire des additifs');
    Auth::login($validator); Auth::selectSite((string)$pell); $validator=Auth::user(); $before=(float)ptq($db,'SELECT COALESCE(quantity_kg,0) FROM pellet_additive_stocks WHERE site_id=? AND additive_id=?',[$pell,$additive])->fetchColumn(); $model->receiveAdditive($purchase,$validator);
    $recipe=$model->createRecipeVersion(['code'=>'TST-PELL-'.random_int(10000,99999),'name'=>'Recette test','waste_type_id'=>$wasteType,'waste_percent'=>90,'additive_id'=>$additive,'additive_percent'=>10,'notes'=>'Test'],$validator); $ids['recipe_version']=$recipe; $ids['recipe']=(int)ptq($db,'SELECT recipe_id FROM pellet_recipe_versions WHERE id=?',[$recipe])->fetchColumn();
    Auth::login($creator); Auth::selectSite((string)$pell); $creator=Auth::user(); $order=$model->createOrder(['recipe_version_id'=>$recipe,'waste_stock_id'=>$ids['waste'],'additive_id'=>$additive,'theoretical_input_kg'=>100],$creator); $ids['order']=$order;
    try{ptq($db,'UPDATE pellet_recipe_wastes SET percentage=80 WHERE recipe_version_id=?',[$recipe]);$immutable=false;}catch(Exception $e){$immutable=true;} ptok($immutable,'Une recette utilisée est immuable');
    $model->submitResults($order,['waste_actual_kg'=>90,'additive_actual_kg'=>10,'output_quantity_kg'=>95],$creator);
    Auth::login($validator); Auth::selectSite((string)$pell); $validator=Auth::user(); $model->validateOrder($order,$validator);
    $orderRow=ptq($db,'SELECT * FROM pellet_orders WHERE id=?',[$order])->fetch(); ptok($orderRow['status']==='validated' && (float)$orderRow['loss_quantity_kg']===5.0 && (float)$orderRow['yield_percent']===95.0,'Rendement, pertes et validation atomique calculés');
    ptok((int)ptq($db,"SELECT COUNT(*) FROM waste_stock_movements WHERE waste_stock_id=? AND movement_type='pellet_consumption'",[$ids['waste']])->fetchColumn()===1,'Consommation du lot de déchets tracée une seule fois');
    try{$model->validateOrder($order,$validator);$double=false;}catch(Exception $e){$double=true;} ptok($double,'Double validation et double consommation refusées');
    ptok((int)ptq($db,"SELECT COUNT(*) FROM pellet_stock_movements psm JOIN pellet_stocks ps ON ps.id=psm.pellet_stock_id WHERE ps.pellet_order_id=? AND psm.movement_type='production_in'",[$order])->fetchColumn()===1,'Stock pellets créé avec un mouvement traçable');
    $item=ptq($db,"SELECT * FROM empty_packaging_items WHERE code='EMB-A30'")->fetch();
    $empty=ptq($db,'SELECT * FROM empty_packaging_stocks WHERE site_id=? AND packaging_item_id=?',[$pell,$item['id']])->fetch();
    $ids['empty_existed']=(bool)$empty;
    if(!$empty){ptq($db,'INSERT INTO empty_packaging_stocks(site_id,packaging_item_id,physical_quantity,operational_quantity) VALUES(?,?,10,10)',[$pell,$item['id']]);$empty=ptq($db,'SELECT * FROM empty_packaging_stocks WHERE id=?',[$db->lastInsertId()])->fetch();}
    $ids['empty_stock']=(int)$empty['id']; $ids['empty_physical']=(int)$empty['physical_quantity']; $ids['empty_operational']=(int)$empty['operational_quantity'];
    ptq($db,'UPDATE empty_packaging_stocks SET physical_quantity=physical_quantity+2,operational_quantity=operational_quantity+2 WHERE id=?',[$empty['id']]);
    $finished=$model->package($order,['packaging_item_id'=>$item['id'],'bags_count'=>2],$validator); $ids['finished']=$finished;
    ptok((float)ptq($db,'SELECT total_weight_kg FROM finished_stocks WHERE id=?',[$finished])->fetchColumn()===60.0,'Conditionnement 30 kg crée le stock fini transférable');
    ptok((int)ptq($db,"SELECT COUNT(*) FROM empty_packaging_movements WHERE stock_id=? AND reference_type='pellet_packaging'",[$empty['id']])->fetchColumn()===1,'Conditionnement consomme les sacs vides dans la même transaction');
    $ids['additive_stock']=(int)ptq($db,'SELECT id FROM pellet_additive_stocks WHERE site_id=? AND additive_id=?',[$pell,$additive])->fetchColumn(); $ids['additive_before']=$before;
} catch(Exception $e) { echo 'ERREUR - '.$e->getMessage()."\n"; $failures[]=$e->getMessage(); }
finally {
    if(!empty($ids['finished'])){$pack=(int)ptq($db,'SELECT id FROM pellet_packaging WHERE finished_stock_id=?',[$ids['finished']])->fetchColumn();if($pack){ptq($db,"DELETE FROM empty_packaging_movements WHERE reference_type='pellet_packaging' AND reference_id=?",[$pack]);ptq($db,"DELETE FROM pellet_stock_movements WHERE reference_type='pellet_packaging' AND reference_id=?",[$pack]);ptq($db,'DELETE FROM pellet_packaging WHERE id=?',[$pack]);}ptq($db,'DELETE FROM finished_stocks WHERE id=?',[$ids['finished']]);}
    if(!empty($ids['order'])){ptdocs($db,'pellet_orders',$ids['order']);$pellets=ptq($db,'SELECT id FROM pellet_stocks WHERE pellet_order_id=?',[$ids['order']])->fetchAll(PDO::FETCH_COLUMN);foreach($pellets as $stock){ptq($db,'DELETE FROM pellet_stock_movements WHERE pellet_stock_id=?',[$stock]);}ptq($db,'DELETE FROM pellet_stocks WHERE pellet_order_id=?',[$ids['order']]);ptq($db,"DELETE FROM pellet_additive_movements WHERE reference_type='pellet_orders' AND reference_id=?",[$ids['order']]);ptq($db,"DELETE FROM waste_stock_movements WHERE reference_type='pellet_orders' AND reference_id=?",[$ids['order']]);ptq($db,'DELETE FROM pellet_order_additive_consumptions WHERE pellet_order_id=?',[$ids['order']]);ptq($db,'DELETE FROM pellet_order_waste_consumptions WHERE pellet_order_id=?',[$ids['order']]);ptq($db,'DELETE FROM pellet_orders WHERE id=?',[$ids['order']]);}
    if(!empty($ids['recipe_version'])){ptq($db,'DELETE FROM pellet_recipe_additives WHERE recipe_version_id=?',[$ids['recipe_version']]);ptq($db,'DELETE FROM pellet_recipe_wastes WHERE recipe_version_id=?',[$ids['recipe_version']]);ptq($db,'DELETE FROM pellet_recipe_versions WHERE id=?',[$ids['recipe_version']]);ptq($db,'DELETE FROM pellet_recipes WHERE id=?',[$ids['recipe']]);}
    if(!empty($ids['purchase'])){ptq($db,"DELETE FROM pellet_additive_movements WHERE reference_type='pellet_additive_purchases' AND reference_id=?",[$ids['purchase']]);ptq($db,'DELETE FROM pellet_additive_purchases WHERE id=?',[$ids['purchase']]);}
    if(!empty($ids['additive_stock']))ptq($db,'UPDATE pellet_additive_stocks SET quantity_kg=? WHERE id=?',[$ids['additive_before'],$ids['additive_stock']]);
    if(!empty($ids['empty_stock'])){if(!empty($ids['empty_existed']))ptq($db,'UPDATE empty_packaging_stocks SET physical_quantity=?,operational_quantity=? WHERE id=?',[$ids['empty_physical'],$ids['empty_operational'],$ids['empty_stock']]);else ptq($db,'DELETE FROM empty_packaging_stocks WHERE id=?',[$ids['empty_stock']]);}
    if(!empty($ids['waste'])){ptq($db,'DELETE FROM waste_stock_movements WHERE waste_stock_id=?',[$ids['waste']]);ptq($db,'DELETE FROM waste_stocks WHERE id=?',[$ids['waste']]);}
    ptq($db,"DELETE FROM activity_logs WHERE user_agent='PelletizationTest'");
}
ob_end_flush();
if($failures) exit(1); echo "\nPelletisation conforme.\n";
