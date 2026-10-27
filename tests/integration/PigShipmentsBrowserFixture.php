<?php
// A validated pig slaughter with 150 kg of carcasses at the farm, for the shipments page browser test.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../../app/services/PorcineFlowService.php';
$user=DagrilIntegrationTest::loginRole('administrateur');
$site=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");Auth::selectSite((string)$site);
$suffix=strtoupper(bin2hex(random_bytes(3)));$key=static fn()=>bin2hex(random_bytes(16));
$db->prepare("INSERT INTO livestock_facilities(site_id,code,name,facility_type,capacity_heads,status) VALUES(?,?,?,'housing',100,'active')")->execute([$site,'PIG-SHIP-'.$suffix,'Porcherie envois '.$suffix]);$facility=(int)$db->lastInsertId();
$batch=(new PigService($db))->register(['site_id'=>$site,'facility_id'=>$facility,'sex'=>'female','born_on'=>date('Y-m-d',strtotime('-5 months')),'heads'=>'12','submission_key'=>$key()],$user);
$flow=new PorcineFlowService($db);
$conversion=$flow->slaughter(['operation_key'=>$key(),'batch_id'=>$batch,'heads'=>'6','weight_kg'=>'150','slaughtered_at'=>date('Y-m-d H:i:s'),'expiry_date'=>date('Y-m-d',strtotime('+4 days'))],$user);
$stock=$flow->validateSlaughter($conversion,$user);
echo json_encode(['stock'=>$stock,'facility'=>'Porcherie envois '.$suffix,'plate'=>'ENV-'.$suffix,'conversion'=>DagrilIntegrationTest::scalar('SELECT conversion_number FROM livestock_conversions WHERE id=?',[$conversion])],JSON_UNESCAPED_UNICODE);
