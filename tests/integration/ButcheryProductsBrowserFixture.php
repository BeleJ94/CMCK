<?php
// Pig and beef carcasses in the cold room, so that the transformation form offers both materials.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
$site=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='BOUCH'");$suffix=strtoupper(bin2hex(random_bytes(3)));
$item=static fn($code)=>(int)DagrilIntegrationTest::scalar('SELECT id FROM butchery_items WHERE code=?',[$code]);
foreach(['MP-PORC'=>'PORC','MP-BOEUF'=>'BOEUF'] as $code=>$label)$db->prepare("INSERT INTO butchery_raw_lots(site_id,item_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?,?,?,50,0,2,'available')")->execute([$site,$item($code),'NAV-'.$label.'-'.$suffix,date('Y-m-d'),date('Y-m-d',strtotime('+4 days'))]);
echo json_encode(['porc'=>$item('MP-PORC'),'boeuf'=>$item('MP-BOEUF'),'cotelette'=>$item('PORC-COTELETTE'),'morceauxBoeuf'=>$item('MORCEAUX-BOEUF'),'abats'=>$item('ABATS'),'suffix'=>$suffix]);
