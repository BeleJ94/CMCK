<?php
// Dedicated supplier and barn for the cattle browser test; the transit tolerance of cattle is removed.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
$site=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");
$suffix=strtoupper(bin2hex(random_bytes(3)));
$db->prepare("INSERT INTO suppliers(name,status) VALUES(?,'active')")->execute(['Éleveur navigateur '.$suffix]);$supplier=(int)$db->lastInsertId();
$db->prepare("INSERT INTO livestock_facilities(site_id,code,name,facility_type,capacity_heads,status) VALUES(?,?,?,'housing',50,'active')")->execute([$site,'CATTLE-BROWSER-'.$suffix,'Étable navigateur '.$suffix]);$barn=(int)$db->lastInsertId();
$db->prepare("DELETE t FROM livestock_transit_tolerances t JOIN livestock_species s ON s.id=t.species_id WHERE s.code='BOVIN'")->execute();
echo json_encode(['site'=>$site,'supplier'=>$supplier,'supplierName'=>'Éleveur navigateur '.$suffix,'barn'=>$barn,'plate'=>'BOV-'.$suffix],JSON_UNESCAPED_UNICODE);
