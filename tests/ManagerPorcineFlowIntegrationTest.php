<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/integration/TestBootstrap.php';$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/PorcineFlowService.php';
$fixture=json_decode(file_get_contents(getenv('DAGRIL_FLOW_FIXTURE')),true,512,JSON_THROW_ON_ERROR);
if($fixture['database']!==getenv('DAGRIL_DB_DATABASE'))throw new RuntimeException('Fixture incompatible.');
$db->exec(file_get_contents(__DIR__.'/../database/038_add_butchery_manager_role.sql'));$db->exec(file_get_contents(__DIR__.'/../database/039_add_farm_manager_roles.sql'));
function pq($sql,$params=[]){global$db;$q=$db->prepare($sql);$q->execute($params);return$q;}
function pk($patch=[]){return array_merge(['operation_key'=>bin2hex(random_bytes(16))],$patch);}
function pc($ok,$message){DagrilIntegrationTest::check($ok,$message);if(!$ok)throw new RuntimeException($message);}
function managerAccount($slug,$site){global$db;$role=(int)pq('SELECT id FROM roles WHERE slug=?',[$slug])->fetchColumn();pq("INSERT INTO users(role_id,name,email,password,status) VALUES(NULL,?,?,'unused','active')",[$slug,$slug.'-'.bin2hex(random_bytes(4)).'@example.test']);$id=(int)$db->lastInsertId();pq("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')",[$id,$site]);pq("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')",[$id,$role,$site]);return$id;}
function managerLogin($id,$site){Auth::login(pq('SELECT * FROM users WHERE id=?',[$id])->fetch());Auth::selectSite((string)$site);return Auth::user();}
$farm=(int)$fixture['farm'];$bouch=(int)$fixture['butchery'];$farmerId=managerAccount('responsable-elevage',$farm);$butcherId=managerAccount('responsable-boucherie',$bouch);
$farmer=managerLogin($farmerId,$farm);$flow=new PorcineFlowService($db);$pig=new PigService($db);
pc(Auth::canSelfValidate($farmerId,'livestock',$farm),'Responsable élevage autorisé à valider sa propre opération sur sa ferme');
$batch=$pig->register(['site_id'=>$farm,'facility_id'=>$fixture['facility'],'sex'=>'male','born_on'=>date('Y-m-d',strtotime('-5 months')),'heads'=>'2','submission_key'=>bin2hex(random_bytes(16))],$farmer);
$slaughter=$flow->slaughter(pk(['batch_id'=>$batch,'heads'=>'2','weight_kg'=>'20','slaughtered_at'=>date('Y-m-d H:i:s'),'expiry_date'=>$fixture['expiry']]),$farmer);
$stock=$flow->validateSlaughter($slaughter,$farmer);$r=pq('SELECT created_by,validated_by,status FROM livestock_conversions WHERE id=?',[$slaughter])->fetch();
pc((int)$r['created_by']===$farmerId&&(int)$r['validated_by']===$farmerId&&$r['status']==='validated','Abattage saisi et validé par le même responsable élevage');
$transfer=$flow->shipment(pk(['weight_stock_id'=>$stock,'quantity_kg'=>'20','truck_plate'=>'MANAGER-TEST','driver_name'=>'Responsable élevage test']),$farmer);$flow->advanceShipment($transfer,'approve',$farmer);$flow->advanceShipment($transfer,'dispatch',$farmer);
$r=pq('SELECT created_by,approved_by,status FROM livestock_transfers WHERE id=?',[$transfer])->fetch();pc((int)$r['created_by']===$farmerId&&(int)$r['approved_by']===$farmerId&&$r['status']==='in_transit','Envoi créé, approuvé et expédié par le même responsable élevage');
pc(!Auth::canSelfValidate($farmerId,'butchery',$bouch),'Le responsable élevage ne reçoit aucun accès implicite à la boucherie');
$butcher=managerLogin($butcherId,$bouch);pc(Auth::canSelfValidate($butcherId,'butchery',$bouch),'Responsable boucherie autorisé à valider sa propre opération');
$receipt=$flow->arrival(pk(['transfer_id'=>$transfer,'weighing_mode'=>'net','net_kg'=>'20']),$butcher);$flow->receive(pk(['receipt_id'=>$receipt,'visual_conform'=>'1','documents_conform'=>'1','temperature_c'=>'4']),$butcher);
$r=pq('SELECT created_by,validated_by,status FROM butchery_receipts WHERE id=?',[$receipt])->fetch();pc((int)$r['created_by']===$butcherId&&(int)$r['validated_by']===$butcherId&&$r['status']==='accepted','Arrivée et contrôle sanitaire effectués par le même responsable boucherie');
$raw=pq('SELECT * FROM butchery_raw_lots WHERE receipt_id=?',[$receipt])->fetch();
$order=$flow->transform(pk(['source_item_id'=>$raw['item_id'],'lot_mode'=>'manual','source_lot_ids'=>[(string)$raw['id']],'quantity_kg'=>'20','production_date'=>date('Y-m-d'),'counter_id'=>$fixture['counter'],'outputs'=>[['item_id'=>$fixture['item1'],'quantity_kg'=>'20','counter_kg'=>'5','expiry_date'=>$fixture['expiry']]]]),$butcher);
$flow->validateTransform($order,pk(),$butcher);$r=pq('SELECT created_by,validated_by,status FROM butchery_orders WHERE id=?',[$order])->fetch();pc((int)$r['created_by']===$butcherId&&(int)$r['validated_by']===$butcherId&&$r['status']==='validated','Transformation saisie et validée par le même responsable boucherie');
$counterStock=pq('SELECT cs.* FROM butchery_counter_stocks cs JOIN butchery_finished_lots l ON l.id=cs.finished_lot_id JOIN butchery_order_outputs o ON o.id=l.order_output_id WHERE o.order_id=?',[$order])->fetch();
$sale=$flow->sell(pk(['counter_stock_id'=>$counterStock['id'],'quantity_kg'=>'2','unit_price'=>'10','customer_name'=>'Vente responsable']),$butcher);
pc($sale>0&&(float)pq('SELECT quantity_kg FROM butchery_counter_stocks WHERE id=?',[$counterStock['id']])->fetchColumn()===3.0,'Vente enregistrée directement par le responsable boucherie');
pc(!Auth::canSelfValidate($butcherId,'livestock',$farm),'Le responsable boucherie ne reçoit aucun accès implicite à la ferme');
pc((float)pq('SELECT quantity_kg FROM butchery_raw_lots WHERE id=?',[$raw['id']])->fetchColumn()===0.0,'Les stocks sont consommés une seule fois sur tout le parcours');
DagrilIntegrationTest::finish();
