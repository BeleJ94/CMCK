<?php
// Dedicated pond with one parcel for the fish browser test; the transit tolerance of the species is removed.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../../app/services/FishFlowService.php';
$user=DagrilIntegrationTest::loginRole('administrateur');
$site=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");Auth::selectSite((string)$site);
$code='FISH-BROWSER-'.strtoupper(bin2hex(random_bytes(3)));$name='Étang navigateur '.substr($code,-6);
$db->prepare("INSERT INTO livestock_facilities(site_id,code,name,facility_type,area_m2,status) VALUES(?,?,?,'pond',500,'active')")->execute([$site,$code,$name]);$pond=(int)$db->lastInsertId();
$parcel=(new FishFlowService($db))->createParcel(['operation_key'=>bin2hex(random_bytes(16)),'facility_id'=>$pond,'name'=>'','area_m2'=>'500'],$user);
$species=(int)DagrilIntegrationTest::scalar("SELECT id FROM livestock_species WHERE code='TILAPIA'");
$db->prepare('DELETE FROM livestock_transit_tolerances WHERE species_id=?')->execute([$species]);
echo json_encode(['pond'=>$pond,'pondName'=>$name,'parcel'=>$parcel],JSON_UNESCAPED_UNICODE);
