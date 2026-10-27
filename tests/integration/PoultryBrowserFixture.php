<?php
// Dedicated supplier, poultry house and laying lot for the poultry browser test; poultry transit tolerance set to 5 %.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../../app/services/PoultryFlowService.php';
$user=DagrilIntegrationTest::loginRole('administrateur');
$site=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");Auth::selectSite((string)$site);
$suffix=strtoupper(bin2hex(random_bytes(3)));
$db->prepare("INSERT INTO suppliers(name,status) VALUES(?,'active')")->execute(['Couvoir navigateur '.$suffix]);$supplier=(int)$db->lastInsertId();
$db->prepare("INSERT INTO livestock_facilities(site_id,code,name,facility_type,capacity_heads,status) VALUES(?,?,?,'poultry_house',1000,'active')")->execute([$site,'POUL-BROWSER-'.$suffix,'Poulailler navigateur '.$suffix]);$house=(int)$db->lastInsertId();
$poultry=new PoultryFlowService($db);
$layers=$poultry->receivePoultry(['operation_key'=>bin2hex(random_bytes(16)),'site_id'=>(string)$site,'flock_type'=>'layer','supplier_id'=>(string)$supplier,'facility_id'=>(string)$house,'received_at'=>date('Y-m-d H:i',strtotime('-90 days')),'heads'=>'100'],$user);
$species=(int)DagrilIntegrationTest::scalar("SELECT id FROM livestock_species WHERE code='POULET'");
(new TransitWeighingService($db))->saveTolerance(['species_id'=>(string)$species,'tolerance_pct'=>'5','approver_role_id'=>''],$user,(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='BOUCH'"));
echo json_encode(['site'=>$site,'supplier'=>$supplier,'supplierName'=>'Couvoir navigateur '.$suffix,'house'=>$house,'houseName'=>'Poulailler navigateur '.$suffix,'layers'=>$layers,'plate'=>'VOL-'.$suffix,'livePlate'=>'VIF-'.$suffix],JSON_UNESCAPED_UNICODE);
