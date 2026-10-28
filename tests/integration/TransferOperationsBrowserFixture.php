<?php
// An approved transfer of three lots of the same product and format (82, 30 and 6 bags of 25 kg), for the shipment and receipt forms.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../../app/services/TransferService.php';
$admin=DagrilIntegrationTest::loginRole('administrateur');
$id=static fn($sql)=>(int)DagrilIntegrationTest::scalar($sql);
$mino=$id("SELECT id FROM sites WHERE code='MINO'");$deco=$id("SELECT id FROM sites WHERE code='DEP-DEC'");$flour=$id("SELECT id FROM products WHERE code='FARINE-MAIS'");$f25=$id("SELECT id FROM bag_formats WHERE name='Sac 25kg'");
$items=[];
foreach([[82,'-30 days'],[30,'-20 days'],[6,'-10 days']] as [$bags,$age]){
    $db->prepare("INSERT INTO finished_stocks(site_id,product_id,bag_format_id,quantity_bags,total_weight_kg,status,created_at) VALUES(?,?,?,?,?,'active',?)")->execute([$mino,$flour,$f25,$bags,$bags*25,date('Y-m-d H:i:s',strtotime($age))]);
    $items[]=['finished_stock_id'=>(int)$db->lastInsertId(),'quantity_bags'=>$bags];
}
$transfers=new TransferService($db);
$transfer=$transfers->create(['transfer_type'=>'inter_site','source_site_id'=>$mino,'destination_site_id'=>$deco,'notes'=>'Formulaires navigateur','items'=>$items],$admin);
$transfers->submit($transfer,$admin);$transfers->approve($transfer,$admin);
echo json_encode(['transfer'=>$transfer,'lots'=>array_column($items,'finished_stock_id')]);
