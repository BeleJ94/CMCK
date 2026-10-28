<?php
// A validated order (30 × 50 kg, 10 × 10 kg) for a dedicated client: 20 bags of 50 kg already at DECO, the rest reserved at the mill.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();date_default_timezone_set(config('app.timezone','UTC'));
require_once __DIR__.'/../../app/services/CustomerService.php';require_once __DIR__.'/../../app/services/CustomerOrderService.php';require_once __DIR__.'/../../app/services/TransferService.php';
$admin=DagrilIntegrationTest::loginRole('administrateur');$settings=new SalesSettingsService($db);
$id=static fn($sql,$params=[])=>(int)DagrilIntegrationTest::scalar($sql,$params);
$flour=$id("SELECT id FROM products WHERE code='FARINE-MAIS'");$f50=$id("SELECT id FROM bag_formats WHERE code='EMB-F50'");$f10=$id("SELECT id FROM bag_formats WHERE code='EMB-F10'");
$mino=$id("SELECT id FROM sites WHERE code='MINO'");$deco=$id("SELECT id FROM sites WHERE code='DEP-DEC'");
if($settings->currentRate()===null)$settings->setRate(['rate'=>'2800'],$admin);
foreach([[$f50,'35'],[$f10,'8']] as [$format,$price]){try{$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$format,'price_company'=>$price],$admin);}catch(RuntimeException $e){/* already in force */}}
$suffix=strtoupper(bin2hex(random_bytes(3)));$name='Client livraison '.$suffix;
$client=(new CustomerService($db))->create(['customer_type'=>'company','name'=>$name,'phone'=>'0830000000','billing_currency'=>'USD'],$admin);
$lot=$db->prepare("INSERT INTO finished_stocks(site_id,product_id,bag_format_id,quantity_bags,total_weight_kg,status,created_at) VALUES(?,?,?,?,?,'active',?)");
$lot->execute([$mino,$flour,$f50,30,1500,date('Y-m-d H:i:s',strtotime('-30 years'))]);$lot->execute([$mino,$flour,$f10,10,100,date('Y-m-d H:i:s',strtotime('-30 years'))]);
$orders=new CustomerOrderService($db);
$order=$orders->save(['customer_id'=>(string)$client,'destination_site_id'=>(string)$deco,'lines'=>[['product_key'=>$flour.'-'.$f50,'quantity_bags'=>'30'],['product_key'=>$flour.'-'.$f10,'quantity_bags'=>'10']]],$admin);
$orders->validate($order,DagrilIntegrationTest::loginRole('direction'));$admin=DagrilIntegrationTest::loginRole('administrateur');
$l50=$id('SELECT id FROM customer_order_lines WHERE order_id=? AND bag_format_id=?',[$order,$f50]);$l10=$id('SELECT id FROM customer_order_lines WHERE order_id=? AND bag_format_id=?',[$order,$f10]);
$orders->prepareTransfer($order,['bags'=>[$l50=>'20',$l10=>'0']],$admin);$transfer=$id('SELECT id FROM stock_transfers WHERE customer_order_id=?',[$order]);
$transfers=new TransferService($db);$transfers->approve($transfer,DagrilIntegrationTest::loginRole('direction'));$admin=DagrilIntegrationTest::loginRole('administrateur');
$item=$id('SELECT id FROM stock_transfer_items WHERE transfer_id=?',[$transfer]);$shipment=$transfers->ship($transfer,[$item=>20],$admin,'CAM-01');
$shipItem=$id('SELECT id FROM transfer_shipment_items WHERE shipment_id=?',[$shipment]);$transfers->receive($shipment,bin2hex(random_bytes(8)),[$shipItem=>['accepted_bags'=>20,'rejected_bags'=>0]],$admin,null);$transfers->close($transfer,$admin);
echo json_encode(['order'=>$order,'orderNumber'=>(string)DagrilIntegrationTest::scalar('SELECT order_number FROM customer_orders WHERE id=?',[$order]),'client'=>$client,'clientName'=>$name,'l50'=>$l50,'l10'=>$l10,'deco'=>$deco,'mino'=>$mino],JSON_UNESCAPED_UNICODE);
