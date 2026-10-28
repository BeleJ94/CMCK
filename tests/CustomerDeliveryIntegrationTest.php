<?php
/** Commercial distribution, lot 3 (049): deliveries of client orders from the office or the mill, settlement, cancellation and closure. */
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
// Same clock as the application and the database (dates of delivery are checked against the order validation).
date_default_timezone_set(config('app.timezone','UTC'));
require_once __DIR__.'/../vendor/autoload.php';require_once __DIR__.'/../app/services/PdfService.php';require_once __DIR__.'/../app/core/Controller.php';require_once __DIR__.'/../app/controllers/DistributionController.php';require_once __DIR__.'/../app/controllers/CustomerDeliveryController.php';
require_once __DIR__.'/../app/services/CustomerService.php';require_once __DIR__.'/../app/services/CustomerDeliveryService.php';require_once __DIR__.'/../app/services/TransferService.php';require_once __DIR__.'/../app/models/Distribution.php';
function q($sql,$params=[]){global $db;$s=$db->prepare($sql);$s->execute($params);return $s;}
function v($sql,$params=[]){return q($sql,$params)->fetchColumn();}
function ok($condition,$message){DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
function no($callback,$message,$expected){try{$callback();}catch(RuntimeException $e){ok(strpos($e->getMessage(),$expected)!==false,$message.' · « '.$e->getMessage().' »');return;}ok(false,$message);}
function lot($site,$product,$format,$bags,$weight,$age){global $db;q("INSERT INTO finished_stocks(site_id,product_id,bag_format_id,packaging_id,quantity_bags,total_weight_kg,status,created_at) VALUES(?,?,?,NULL,?,?,'active',?)",[$site,$product,$format,$bags,$bags*$weight,date('Y-m-d H:i:s',strtotime($age))]);return (int)$db->lastInsertId();}
function stock($id){return q('SELECT quantity_bags,reserved_bags,status FROM finished_stocks WHERE id=?',[$id])->fetch();}
function queued($id){global $deliveries;foreach($deliveries->directory()['orders'] as $o)if((int)$o['id']===(int)$id)return $o;return null;}
function line($order,$format){return q('SELECT * FROM customer_order_lines WHERE order_id=? AND bag_format_id=?',[$order,$format])->fetch();}
$mino=(int)v("SELECT id FROM sites WHERE code='MINO'");$deco=(int)v("SELECT id FROM sites WHERE code='DEP-DEC'");
$flour=(int)v("SELECT id FROM products WHERE code='FARINE-MAIS'");$f50=(int)v("SELECT id FROM bag_formats WHERE code='EMB-F50'");$f10=(int)v("SELECT id FROM bag_formats WHERE code='EMB-F10'");
$admin=DagrilIntegrationTest::loginRole('administrateur');
$settings=new SalesSettingsService($db);$settings->setRate(['rate'=>'2800'],$admin);
$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$f50,'price_company'=>'35','price_counter'=>'38'],$admin);$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$f10,'price_company'=>'8'],$admin);
$customers=new CustomerService($db);
$client=$customers->create(['customer_type'=>'company','name'=>'Grossiste Kinshasa','phone'=>'0820000001','billing_currency'=>'USD'],$admin);
$cdfClient=$customers->create(['customer_type'=>'company','name'=>'Boutique Limete','phone'=>'0820000002','billing_currency'=>'CDF'],$admin);
$mill50=lot($mino,$flour,$f50,100,50,'-10 days');$mill10=lot($mino,$flour,$f10,20,10,'-5 days');
$orders=new CustomerOrderService($db);$deliveries=new CustomerDeliveryService($db);$transfers=new TransferService($db);
ok($deliveries->ready(),'Migration 049 détectée : livraisons clients actives');

// Order of 60 × 50 kg and 10 × 10 kg; 40 bags of 50 kg sent to DECO, the rest stays reserved at the mill.
$order=$orders->save(['customer_id'=>(string)$client,'destination_site_id'=>(string)$deco,'lines'=>[['product_key'=>$flour.'-'.$f50,'quantity_bags'=>'60'],['product_key'=>$flour.'-'.$f10,'quantity_bags'=>'10']]],$admin);
$orders->validate($order,DagrilIntegrationTest::loginRole('direction'));$admin=DagrilIntegrationTest::loginRole('administrateur');
$l50=(int)line($order,$f50)['id'];$l10=(int)line($order,$f10)['id'];
$orders->prepareTransfer($order,['bags'=>[$l50=>'40',$l10=>'0']],$admin);$transfer=(int)v('SELECT id FROM stock_transfers WHERE customer_order_id=?',[$order]);
$transfers->approve($transfer,DagrilIntegrationTest::loginRole('direction'));$admin=DagrilIntegrationTest::loginRole('administrateur');
$item=(int)v('SELECT id FROM stock_transfer_items WHERE transfer_id=?',[$transfer]);$shipment=$transfers->ship($transfer,[$item=>40],$admin,'CAM-01');
$shipItem=(int)v('SELECT id FROM transfer_shipment_items WHERE shipment_id=?',[$shipment]);$transfers->receive($shipment,bin2hex(random_bytes(8)),[$shipItem=>['accepted_bags'=>40,'rejected_bags'=>0]],$admin,null);$transfers->close($transfer,$admin);
$office=(int)v("SELECT finished_stock_id FROM customer_order_allocations WHERE stage='office' AND order_line_id=?",[$l50]);
$o=queued($order);
ok($o&&$o['ready_bags']===70&&$o['options']['office-'.$deco]['lines'][$l50]===40&&$o['options']['source-'.$mino]['lines'][$l50]===20&&$o['options']['source-'.$mino]['lines'][$l10]===10&&array_key_first($o['options'])==='office-'.$deco,'À livrer : 40 sacs au bureau DECO (proposé en premier), 20 + 10 directement à la minoterie');

// Refusals, all rolled back.
$at=date('Y-m-d\TH:i');$base=['order_id'=>(string)$order,'source'=>'office-'.$deco,'delivery_mode'=>'pickup','receiver_name'=>'Jean Mavungu','delivered_at'=>$at,'bags'=>[$l50=>'10']];
no(fn()=>$deliveries->deliver(array_replace($base,['source'=>'']),$admin),'Provenance obligatoire','d’où partent les sacs');
no(fn()=>$deliveries->deliver(array_replace($base,['delivery_mode'=>'company_truck']),$admin),'Camion de l’entreprise : véhicule obligatoire','Véhicule : champ obligatoire');
no(fn()=>$deliveries->deliver(array_replace($base,['receiver_name'=>' ']),$admin),'Réceptionnaire obligatoire','Réceptionnaire : champ obligatoire');
no(fn()=>$deliveries->deliver(array_replace($base,['delivered_at'=>date('Y-m-d\TH:i',strtotime('+1 day'))]),$admin),'Livraison future refusée','ne peut pas être future');
no(fn()=>$deliveries->deliver(array_replace($base,['bags'=>[$l50=>'41']]),$admin),'Plus que le prêt au bureau refusé','seulement 40 sac(s) prêt(s)');
no(fn()=>$deliveries->deliver(array_replace($base,['source'=>'source-'.$mino,'bags'=>[$l50=>'61']]),$admin),'Plus que le reste de la commande refusé','il reste 60 sac(s) à livrer');
no(fn()=>$deliveries->deliver(array_replace($base,['bags'=>[$l50=>'0']]),$admin),'Livraison vide refusée','nombre de sacs à livrer');
no(fn()=>$deliveries->deliver(array_replace($base,['bags'=>[$l10=>'1']]),$admin),'Ligne sans sac prêt au bureau refusée','seulement 0 sac(s) prêt(s)');
ok((int)v('SELECT COUNT(*) FROM customer_deliveries')===0&&(int)v('SELECT delivered_bags FROM customer_order_lines WHERE id=?',[$l50])===0&&(int)stock($office)['quantity_bags']===40,'Refus : rien d’enregistré, stock du bureau intact');

// Partial delivery from the office: the client's reserved bags leave the stock.
$result=$deliveries->deliver(array_replace($base,['bags'=>[$l50=>'30'],'receiver_phone'=>'0820000009']),$admin);
$first=(int)$result['id'];$s=stock($office);
ok($result['number']==='BL-'.date('Y').'-0001'&&strpos($result['message'],'30 sac(s) remis à Grossiste Kinshasa')!==false&&strpos($result['message'],'soldée')===false,'Livraison partielle BL-'.date('Y').'-0001 depuis DECO · « '.$result['message'].' »');
ok((int)$s['quantity_bags']===10&&(int)$s['reserved_bags']===10&&(int)v("SELECT quantity_bags FROM customer_order_allocations WHERE stage='office' AND order_line_id=?",[$l50])===10,'Bureau : 30 sacs sortis du stock et de la réservation du client, 10 restent réservés');
ok((int)v('SELECT delivered_bags FROM customer_order_lines WHERE id=?',[$l50])===30&&v('SELECT status FROM customer_orders WHERE id=?',[$order])==='validated','Commande toujours en cours : 30 sacs livrés sur 60');
ok((float)v("SELECT SUM(quantity_kg) FROM stock_movements WHERE customer_delivery_id=? AND movement_type='out'",[$first])===1500.0&&(int)v("SELECT SUM(quantity_bags) FROM customer_order_movements WHERE order_id=? AND movement_type='delivered'",[$order])===30,'Mouvement de stock (1 500 kg) et journal de la commande « Livré au client »');
ok((int)v("SELECT COUNT(*) FROM documents d JOIN document_types t ON t.id=d.document_type_id WHERE t.code='BL' AND d.entity_type='customer_deliveries' AND d.entity_id=?",[$first])===1,'Bon de livraison BL enregistré au registre des documents');

// Direct delivery from the mill by company truck, both lines at once.
no(fn()=>$deliveries->deliver(array_replace($base,['source'=>'source-'.$mino,'delivery_mode'=>'company_truck','vehicle_reference'=>'CAM-02','bags'=>[$l50=>'20']]),$admin),'Camion de l’entreprise : chauffeur obligatoire','Chauffeur : champ obligatoire');
$result=$deliveries->deliver(array_replace($base,['source'=>'source-'.$mino,'delivery_mode'=>'company_truck','vehicle_reference'=>'CAM-02','driver_name'=>'Paul','bags'=>[$l50=>'20',$l10=>'10']]),$admin);
$s=stock($mill50);
ok((int)$s['quantity_bags']===40&&(int)$s['reserved_bags']===0&&(int)stock($mill10)['quantity_bags']===10&&(int)stock($mill10)['reserved_bags']===0,'Livraison directe depuis la minoterie : 20 sacs de 50 kg et 10 de 10 kg sortis, plus rien de réservé');
ok((int)line($order,$f10)['delivered_bags']===10&&queued($order)['ready_bags']===10,'Reste 10 sacs prêts au bureau pour la commande · « '.$result['message'].' »');

// The last bags settle the order; the empty office lot is closed.
$result=$deliveries->deliver(array_replace($base,['bags'=>[$l50=>'10']]),$admin);$last=(int)$result['id'];
ok(strpos($result['message'],'est soldée')!==false&&v('SELECT status FROM customer_orders WHERE id=?',[$order])==='closed'&&v('SELECT close_reason FROM customer_orders WHERE id=?',[$order])===CustomerDeliveryService::FULLY_DELIVERED&&queued($order)===null,'Dernière livraison : commande soldée et retirée des commandes à livrer');
ok((int)stock($office)['quantity_bags']===0&&stock($office)['status']==='inactive','Lot du bureau vide : désactivé');

// Cancelling the last delivery reopens the order with its bags reserved again.
no(fn()=>$deliveries->cancel($last,['reason'=>'Erreur'],DagrilIntegrationTest::loginRole('agent-distribution')),'Annulation refusée à l’agent','Permission refusee');
$direction=DagrilIntegrationTest::loginRole('direction');
no(fn()=>$deliveries->cancel($last,['reason'=>''],$direction),'Motif obligatoire','motif d’annulation');
$deliveries->cancel($last,['reason'=>'Livraison refusée par le client'],$direction);$s=stock($office);
ok(v('SELECT status FROM customer_deliveries WHERE id=?',[$last])==='cancelled'&&v('SELECT status FROM customer_orders WHERE id=?',[$order])==='validated'&&(int)line($order,$f50)['delivered_bags']===50,'Annulation : livraison annulée, commande rouverte, 50 sacs livrés sur 60');
ok((int)$s['quantity_bags']===10&&(int)$s['reserved_bags']===10&&$s['status']==='active'&&queued($order)['options']['office-'.$deco]['lines'][$l50]===10,'Les 10 sacs reviennent au bureau, de nouveau réservés à la commande');
ok(v("SELECT d.status FROM documents d WHERE d.entity_type='customer_deliveries' AND d.entity_id=?",[$last])==='cancelled'&&(int)v("SELECT COUNT(*) FROM customer_order_movements WHERE order_id=? AND movement_type='delivery_cancelled'",[$order])===1,'Bon de livraison annulé et journal « Livraison annulée »');
no(fn()=>$deliveries->cancel($last,['reason'=>'Encore'],$direction),'Double annulation refusée','déjà annulée');

// An order with deliveries is closed, not cancelled: the undelivered rest is released.
no(fn()=>$orders->cancel($order,['reason'=>'Test'],$direction),'Annulation d’une commande déjà livrée refusée','clôturez la commande');
no(fn()=>$orders->close($order,['reason'=>''],$direction),'Motif de clôture obligatoire','motif de clôture');
$orders->close($order,['reason'=>'Le client renonce au reste'],$direction);
ok(v('SELECT status FROM customer_orders WHERE id=?',[$order])==='closed'&&(int)stock($office)['reserved_bags']===0&&(int)v("SELECT SUM(a.quantity_bags) FROM customer_order_allocations a JOIN customer_order_lines l ON l.id=a.order_line_id WHERE l.order_id=?",[$order])===0,'Clôture : 10 sacs du bureau libérés pour le comptoir, plus aucune réservation');
no(fn()=>$deliveries->cancel($first,['reason'=>'Trop tard'],$direction),'Livraison d’une commande clôturée non annulable','est clôturée');
no(fn()=>$orders->close($order,['reason'=>'Encore'],$direction),'Double clôture refusée','Seule une commande en cours');

// Client billed in CDF: delivery note in USD and CDF at the order's rate.
$admin=DagrilIntegrationTest::loginRole('administrateur');
$cdf=$orders->save(['customer_id'=>(string)$cdfClient,'destination_site_id'=>(string)$deco,'lines'=>[['product_key'=>$flour.'-'.$f10,'quantity_bags'=>'5']]],$admin);
$orders->validate($cdf,DagrilIntegrationTest::loginRole('direction'));$admin=DagrilIntegrationTest::loginRole('administrateur');
$lCdf=(int)line($cdf,$f10)['id'];$cdfData=['order_id'=>(string)$cdf,'source'=>'source-'.$mino,'delivery_mode'=>'pickup','receiver_name'=>'Mamie Limete','delivered_at'=>$at,'bags'=>[$lCdf=>'2']];
$customers->setStatus($cdfClient,['status'=>'blocked','reason'=>'Impayés'],$admin);
no(fn()=>$deliveries->deliver($cdfData,$admin),'Client bloqué : livraison refusée','est bloqué : Impayés');
$customers->setStatus($cdfClient,['status'=>'active'],$admin);
$cdfDelivery=$deliveries->deliver(['order_id'=>(string)$cdf,'source'=>'source-'.$mino,'delivery_mode'=>'pickup','receiver_name'=>'Mamie Limete','delivered_at'=>$at,'bags'=>[$lCdf=>'2']],$admin);
$html=(static function($delivery){ob_start();require __DIR__.'/../app/views/distributions/delivery_print.php';return ob_get_clean();})($deliveries->find($cdfDelivery['id']));
ok(strpos($html,'Bon de livraison client')!==false&&strpos($html,$cdfDelivery['number'])!==false&&strpos($html,'Mamie Limete')!==false&&strpos($html,'44 800 CDF')!==false&&strpos($html,'Warning:')===false,'Bon de livraison : client, réceptionnaire, 2 × 8 USD = 44 800 CDF au taux de la commande');
ob_start();(new CustomerDeliveryController())->print($cdfDelivery['id']);$pdf=ob_get_clean();
ok(strncmp($pdf,'%PDF',4)===0,'Le bon s’affiche directement en PDF : l’aperçu, l’impression et le téléchargement sont le même document');

// Exit without an order to a registered client: the client gives its name to the exit note.
$_SERVER['HTTP_X_REQUESTED_WITH']='XMLHttpRequest';$_SERVER['REQUEST_METHOD']='POST';
$free10=lot($mino,$flour,$f10,8,10,'-1 day');
$_POST=['_token'=>csrf_token(),'customer_id'=>(string)$client,'recipient_name'=>'','finished_stock_id'=>(string)$free10,'quantity_bags'=>'3','distributed_at'=>$at,'transporter'=>'','exit_voucher'=>''];
ob_start();(new DistributionController())->store();$json=json_decode(ob_get_clean(),true);
$exit=q('SELECT * FROM distributions ORDER BY id DESC LIMIT 1')->fetch();
ok(!empty($json['success'])&&(int)$exit['customer_id']===$client&&$exit['recipient_name']==='Grossiste Kinshasa'&&(int)stock($free10)['quantity_bags']===5,'Sortie sans commande à un client enregistré : nom du client repris, 3 sacs sortis');
ok(strpos($json['redirect'],'/distributions')!==false&&substr(rtrim($json['redirect'],'/'),-13)==='distributions','Après la sortie, retour au hub des sorties');
$_POST['customer_id']='999999';ob_start();(new DistributionController())->store();$json=json_decode(ob_get_clean(),true);
ok(empty($json['success'])&&strpos($json['message'],'Client introuvable')!==false,'Client inconnu refusé');
unset($_SERVER['HTTP_X_REQUESTED_WITH']);$_POST=[];$_SERVER['REQUEST_METHOD']='GET';

// Hub page.
$page=function(){http_response_code(200);ob_start();(new DistributionController())->index();return ob_get_clean();};
flash('exit_done');flash('success');flash('delivery_done',(string)$cdfDelivery['id']);$html=$page();
ok(strpos($html,'Commandes à livrer')!==false&&strpos($html,'id="deliver-'.$cdf.'"')!==false&&strpos($html,'name="source" value="source-'.$mino.'"')!==false&&strpos($html,'Boutique Limete')!==false,'Hub : commande CDF à livrer avec son formulaire de livraison');
ok(strpos($html,'BL/DEP-DEC/'.date('Y').'/000001')!==false&&strpos($html,'data-type="delivery"')!==false&&strpos($html,'data-type="direct"')!==false&&strpos($html,'id="delivery-cancel-'.$first.'"')===false,'Historique unique BL et BS ; livraison d’une commande clôturée non annulable');
ok(strpos($html,'distributions/deliveries/'.$cdfDelivery['id'].'/print"')!==false&&strpos($html,'Voir le bon de livraison BL/')!==false&&strpos($html,'id="note-preview"')!==false&&strpos($html,'Warning:')===false&&strpos($html,'Fatal error')===false,'Bandeau après livraison : bon en aperçu ; rendu sans erreur PHP');
DagrilIntegrationTest::loginRole('agent-distribution');$html=$page();
ok(strpos($html,'id="delivery-cancel-')===false,'Agent : annulation des livraisons non proposée');
DagrilIntegrationTest::finish();
