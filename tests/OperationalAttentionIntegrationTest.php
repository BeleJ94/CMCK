<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Disposable UI database required.');
require_once __DIR__.'/integration/TestBootstrap.php';$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/PigService.php';require_once __DIR__.'/../app/services/PorcineFlowService.php';require_once __DIR__.'/../app/services/OperationalAttentionService.php';
function q($sql,$params=[]){global $db;$q=$db->prepare($sql);$q->execute($params);return $q;}
function check($value,$message){DagrilIntegrationTest::check($value,$message);if(!$value)throw new RuntimeException($message);}
$fx=json_decode(file_get_contents(getenv('DAGRIL_FLOW_FIXTURE')),true);
$admin=DagrilIntegrationTest::loginRole('administrateur');Auth::selectSite('all');$dik=(int)q("SELECT id FROM sites WHERE code='FARM-DIK'")->fetchColumn();
q("INSERT INTO livestock_facilities(site_id,code,name,facility_type,capacity_heads,status) VALUES(?,'UI-ATTN-DIK','Porcherie DIKAPA','housing',100,'active')",[$dik]);$facilityDik=(int)$db->lastInsertId();
$pigs=new PigService($db);
foreach([[$dik,$facilityDik],[$fx['farm'],$fx['facility']]] as [$site,$facility]){
 $id=$pigs->register(['site_id'=>$site,'facility_id'=>$facility,'sex'=>'male','born_on'=>date('Y-m-d',strtotime('-2 months -5 days')),'heads'=>2,'submission_key'=>bin2hex(random_bytes(16))],$admin);
 q("UPDATE livestock_pig_profiles SET confirmed_group='A' WHERE batch_id=?",[$id]);
}
(new PorcineFlowService($db))->slaughter(['operation_key'=>bin2hex(random_bytes(16)),'batch_id'=>$fx['adult'],'heads'=>1,'weight_kg'=>5,'slaughtered_at'=>date('Y-m-d H:i:s'),'expiry_date'=>$fx['expiry']],$admin);
q('UPDATE butchery_finished_lots SET expiry_date=? WHERE id=?',[date('Y-m-d',strtotime('-1 day')),$fx['lot2']]);
$service=new OperationalAttentionService($db);$items=$service->items();$byKey=array_column($items,null,'key');
check(($byKey['pending_pig_groups']['count']??0)===2,'Admin sees the two genuine pending group transitions');
check(($byKey['pending_pig_slaughters']['count']??0)===1,'Pending farm slaughter is actionable');
check(($byKey['expired_cold_lots']['count']??0)===1,'Expired cold-room stock is flagged');
check(($byKey['expired_counter_lots']['count']??0)===2,'Expired stock remains scoped to each counter');
check(($byKey['pending_pig_groups']['path']??'')==='livestock/pigs?filter=pending','Notification links carry their business filter');
check($service->countFor('pending_pig_groups')===($byKey['pending_pig_groups']['count']??-1),'Sidebar and bell share one source');
q("INSERT INTO roles(name,slug,status) VALUES('Responsable elevage UI','ui-elevage-test','active')");$role=(int)$db->lastInsertId();
q("INSERT INTO users(name,email,password,status) VALUES('Equipe elevage UI','ui.elevage@example.test','unused','active')");$userId=(int)$db->lastInsertId();
q("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component='livestock' AND action IN('read','create','update','validate')",[$role]);
q("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')",[$userId,$dik]);
q("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')",[$userId,$role,$dik]);
Auth::login(q('SELECT * FROM users WHERE id=?',[$userId])->fetch());$restricted=(new OperationalAttentionService($db))->items();$byKey=array_column($restricted,null,'key');
check(count($restricted)===1&&($byKey['pending_pig_groups']['count']??0)===1,'Restricted user only sees their own farm actions');
check(!array_filter($restricted,static fn($item)=>strpos($item['path'],'butchery/')===0),'No butcher notification leaks to the livestock role');
check(Auth::homePathFor(Auth::user())==='livestock','Custom livestock role keeps an authorized landing page');
DagrilIntegrationTest::finish();
