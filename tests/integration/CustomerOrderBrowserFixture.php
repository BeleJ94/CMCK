<?php
// Rate, company prices, a dedicated client and 100 bags of flour at the mill, for the client orders browser test.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../../app/services/CustomerService.php';require_once __DIR__.'/../../app/services/SalesSettingsService.php';
$admin=DagrilIntegrationTest::loginRole('administrateur');$settings=new SalesSettingsService($db);
$id=static fn($sql)=>(int)DagrilIntegrationTest::scalar($sql);
$flour=$id("SELECT id FROM products WHERE code='FARINE-MAIS'");$f50=$id("SELECT id FROM bag_formats WHERE code='EMB-F50'");$f10=$id("SELECT id FROM bag_formats WHERE code='EMB-F10'");$mino=$id("SELECT id FROM sites WHERE code='MINO'");
if($settings->currentRate()===null)$settings->setRate(['rate'=>'2800'],$admin);
foreach([[$f50,'35'],[$f10,'8']] as [$format,$price]){try{$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$format,'price_company'=>$price],$admin);}catch(RuntimeException $e){/* already in force */}}
$suffix=strtoupper(bin2hex(random_bytes(3)));
$client=(new CustomerService($db))->create(['customer_type'=>'company','name'=>'Client navigateur '.$suffix,'phone'=>'0820000000','billing_currency'=>'USD'],$admin);
$db->prepare("INSERT INTO finished_stocks(site_id,product_id,bag_format_id,quantity_bags,total_weight_kg,status,created_at) VALUES(?,?,?,100,5000,'active',?)")->execute([$mino,$flour,$f50,date('Y-m-d H:i:s',strtotime('-20 years'))]);
echo json_encode(['client'=>$client,'clientName'=>'Client navigateur '.$suffix,'f50'=>$flour.'-'.$f50,'f10'=>$flour.'-'.$f10],JSON_UNESCAPED_UNICODE);
