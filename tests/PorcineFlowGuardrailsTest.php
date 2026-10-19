<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Disposable test database required.');
require_once __DIR__.'/integration/TestBootstrap.php';$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/PorcineFlowService.php';require_once __DIR__.'/../app/models/Butchery.php';
$fx=json_decode(file_get_contents(getenv('DAGRIL_FLOW_FIXTURE')),true);$flow=new PorcineFlowService($db);$pig=new PigService($db);$admin=DagrilIntegrationTest::loginRole('administrateur');
function sql($s,$p=[]){global $db;$q=$db->prepare($s);$q->execute($p);return $q;}
function payload($x=[]){return ['operation_key'=>bin2hex(random_bytes(16))]+$x;}
function check($c,$m){DagrilIntegrationTest::check($c,$m);if(!$c)throw new RuntimeException($m);}
function denied($f,$m){DagrilIntegrationTest::expectException($f,$m);}
Auth::selectSite((string)$fx['butchery']);
$before=(int)sql('SELECT COUNT(*) FROM butchery_transfers')->fetchColumn();
denied(fn()=>(new Butchery())->createTransfer(['finished_lot_id'=>$fx['lot1'],'quantity_kg'=>$flow->coldAvailable($fx['lot1'])+0.001],$admin),'Ancien transfert ne peut pas prelever dans un comptoir');
check((int)sql('SELECT COUNT(*) FROM butchery_transfers')->fetchColumn()===$before,'Refus de transfert sans dossier partiel');
$remaining=(float)sql('SELECT quantity_kg FROM butchery_counter_stocks WHERE id=?',[$fx['counterStock']])->fetchColumn();
denied(fn()=>$flow->sell(payload(['counter_stock_id'=>$fx['counterStock'],'quantity_kg'=>1,'unit_price'=>'-5']),$admin),'Prix negatif refuse');
check((float)sql('SELECT quantity_kg FROM butchery_counter_stocks WHERE id=?',[$fx['counterStock']])->fetchColumn()===$remaining,'Prix invalide ne modifie aucun stock');
// A normal user can have both create and validate rights, but cannot self-validate.
sql("INSERT INTO roles(name,slug,status) VALUES('Operateur test','flow-operator','active')");$role=(int)$db->lastInsertId();
sql("INSERT INTO users(role_id,name,email,password,status) VALUES(?,'Operateur test','flow-operator@example.test','unused','active')",[$role]);$uid=(int)$db->lastInsertId();
sql("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component IN('butchery','livestock')",[$role]);
sql("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')",[$uid,$role,$fx['butchery']]);
sql("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')",[$uid,$fx['butchery']]);
$operator=sql('SELECT u.*,r.slug role_slug,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?',[$uid])->fetch();Auth::login($operator);Auth::selectSite((string)$fx['butchery']);$operator=Auth::user();
$order=$flow->transform(payload(['raw_lot_id'=>$fx['raw'],'quantity_kg'=>'1','production_date'=>date('Y-m-d'),'outputs'=>[['item_id'=>$fx['item1'],'cold_kg'=>'0.9','counter_kg'=>'0','expiry_date'=>$fx['expiry']]]]),$operator);
denied(fn()=>$flow->validateTransform($order,payload(),$operator),'Separation des taches respectee pour un utilisateur non administrateur');
denied(fn()=>$flow->slaughter(payload(['batch_id'=>$fx['adult'],'heads'=>1,'weight_kg'=>1,'slaughtered_at'=>date('Y-m-d H:i:s'),'expiry_date'=>$fx['expiry']]),$operator),'Acces a une ferme non attribuee refuse');
Auth::login($admin);Auth::selectSite((string)$fx['butchery']);$flow->cancelTransform($order,payload(['reason'=>'Fin du test']),$admin);
check((float)sql('SELECT reserved_kg FROM butchery_raw_lots WHERE id=?',[$fx['raw']])->fetchColumn()===0.0,'Annulation libere la reservation de l operateur');
Auth::selectSite((string)$fx['farm']);
$batch=$pig->register(['site_id'=>$fx['farm'],'facility_id'=>$fx['facility'],'sex'=>'male','born_on'=>date('Y-m-d',strtotime('-5 months')),'heads'=>1,'submission_key'=>bin2hex(random_bytes(16))],$admin);
$slaughter=$flow->slaughter(payload(['batch_id'=>$batch,'heads'=>1,'weight_kg'=>10,'slaughtered_at'=>date('Y-m-d H:i:s'),'expiry_date'=>$fx['expiry']]),$admin);$stock=$flow->validateSlaughter($slaughter,$admin);
check(sql('SELECT status FROM livestock_batches WHERE id=?',[$batch])->fetchColumn()==='completed','Abattage du dernier porc cloture le lot vivant');
$shipment=$flow->shipment(payload(['weight_stock_id'=>$stock,'quantity_kg'=>10,'truck_plate'=>'CLOSED','driver_name'=>'Test']),$admin);check($shipment>0,'Les carcasses restent expediees quand le lot vivant est termine');
$flow->cancelShipment($shipment,payload(['reason'=>'Fin du test']),$admin);
$pending=$flow->slaughter(payload(['batch_id'=>$fx['adult'],'heads'=>1,'weight_kg'=>5,'slaughtered_at'=>date('Y-m-d H:i:s'),'expiry_date'=>$fx['expiry']]),$admin);$heads=(int)sql('SELECT current_heads FROM livestock_batches WHERE id=?',[$fx['adult']])->fetchColumn();$flow->cancelSlaughter($pending,payload(['reason'=>'Erreur avant validation']),$admin);check((int)sql('SELECT current_heads FROM livestock_batches WHERE id=?',[$fx['adult']])->fetchColumn()===$heads,'Annulation d abattage en attente conserve les animaux');
check((int)sql('SELECT COUNT(*) FROM butchery_raw_lots WHERE quantity_kg<reserved_kg OR reserved_kg<0')->fetchColumn()===0,'Invariants des stocks matiere respectes');
check((int)sql('SELECT COUNT(*) FROM butchery_finished_lots l WHERE l.quantity_kg<l.reserved_kg+(SELECT COALESCE(SUM(quantity_kg),0) FROM butchery_counter_stocks WHERE finished_lot_id=l.id)')->fetchColumn()===0,'Conservation froid + comptoirs + reservations respectee');
DagrilIntegrationTest::finish();
