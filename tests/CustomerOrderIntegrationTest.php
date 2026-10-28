<?php
/** Commercial distribution, lot 2 (048): client orders, booking at the mill, assigned transfer and client stock at the main office. */
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/core/Controller.php';require_once __DIR__.'/../app/controllers/CustomerOrderController.php';
require_once __DIR__.'/../app/services/CustomerService.php';require_once __DIR__.'/../app/services/CancellationService.php';require_once __DIR__.'/../app/models/Distribution.php';
function q($sql,$params=[]){global $db;$s=$db->prepare($sql);$s->execute($params);return $s;}
function v($sql,$params=[]){return q($sql,$params)->fetchColumn();}
function ok($condition,$message){DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
function no($callback,$message,$expected){try{$callback();}catch(RuntimeException $e){ok(strpos($e->getMessage(),$expected)!==false,$message.' · « '.$e->getMessage().' »');return;}ok(false,$message);}
function line($order,$format){global $orders;foreach($orders->directory()['orders'] as $o)if((int)$o['id']===(int)$order)foreach($o['lines'] as $l)if((int)$l['bag_format_id']===(int)$format)return $l;throw new RuntimeException('Ligne absente');}
function order($id){global $orders;foreach($orders->directory()['orders'] as $o)if((int)$o['id']===(int)$id)return $o;throw new RuntimeException('Commande absente');}
function free($stock){return (int)v('SELECT quantity_bags-reserved_bags FROM finished_stocks WHERE id=?',[$stock]);}
function lot($site,$product,$format,$bags,$weight,$age){global $db;q("INSERT INTO finished_stocks(site_id,product_id,bag_format_id,packaging_id,quantity_bags,total_weight_kg,status,created_at) VALUES(?,?,?,NULL,?,?,'active',?)",[$site,$product,$format,$bags,$bags*$weight,date('Y-m-d H:i:s',strtotime($age))]);return (int)$db->lastInsertId();}
$mino=(int)v("SELECT id FROM sites WHERE code='MINO'");$deco=(int)v("SELECT id FROM sites WHERE code='DEP-DEC'");
$flour=(int)v("SELECT id FROM products WHERE code='FARINE-MAIS'");$f50=(int)v("SELECT id FROM bag_formats WHERE code='EMB-F50'");$f10=(int)v("SELECT id FROM bag_formats WHERE code='EMB-F10'");$a30=(int)v("SELECT id FROM bag_formats WHERE code='EMB-A30'");$feed=(int)v("SELECT id FROM products WHERE code='ALIMENT-BETAIL'");
$admin=DagrilIntegrationTest::loginRole('administrateur');
$settings=new SalesSettingsService($db);$settings->setRate(['rate'=>'2800'],$admin);
$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$f50,'price_company'=>'35','price_counter'=>'38'],$admin);$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$f10,'price_company'=>'8'],$admin);
$customers=new CustomerService($db);
$client=$customers->create(['customer_type'=>'company','name'=>'Grossiste Matadi','phone'=>'0810000001','billing_currency'=>'USD'],$admin);
$cdfClient=$customers->create(['customer_type'=>'company','name'=>'Boutique Ngaliema','phone'=>'0810000002','billing_currency'=>'CDF'],$admin);
$blocked=$customers->create(['customer_type'=>'company','name'=>'Client bloqué','phone'=>'0810000003','billing_currency'=>'USD'],$admin);$customers->setStatus($blocked,['status'=>'blocked','reason'=>'Impayés'],$admin);
$old=lot($mino,$flour,$f50,30,50,'-10 days');$recent=lot($mino,$flour,$f50,50,50,'-2 days');
$orders=new CustomerOrderService($db);
ok($orders->ready(),'Migration 048 détectée : commandes clients actives');

// Draft order by the distribution agent: frozen prices, one line per product and format.
$agent=DagrilIntegrationTest::loginRole('agent-distribution');
$base=['customer_id'=>(string)$client,'destination_site_id'=>(string)$deco,'lines'=>[['product_key'=>$flour.'-'.$f50,'quantity_bags'=>'100'],['product_key'=>$flour.'-'.$f10,'quantity_bags'=>'20']]];
no(fn()=>$orders->save(['lines'=>[['product_key'=>$flour.'-'.$f50,'quantity_bags'=>'5'],['product_key'=>$flour.'-'.$f50,'quantity_bags'=>'5']]]+$base,$agent),'Même produit et format sur deux lignes refusé','regroupez les quantités');
no(fn()=>$orders->save(['lines'=>[['product_key'=>$feed.'-'.$a30,'quantity_bags'=>'5']]]+$base,$agent),'Format sans tarif entreprises refusé','Fixez d’abord le tarif entreprises');
no(fn()=>$orders->save(array_replace($base,['customer_id'=>(string)$blocked]),$agent),'Client bloqué refusé','est bloqué : Impayés');
no(fn()=>$orders->save(array_replace($base,['destination_site_id'=>(string)$mino]),$agent),'Destination hors bureau principal refusée','dépôt du bureau principal');
no(fn()=>$orders->save(array_replace($base,['lines'=>[]]),$agent),'Commande sans ligne refusée','au moins une ligne');
$first=$orders->save($base,$agent);$o=order($first);
ok($o['order_number']==='BC-'.date('Y').'-0001'&&$o['status']==='draft'&&(float)$o['amount_usd']===3660.0&&$o['currency']==='USD','Brouillon BC-'.date('Y').'-0001 : 100 × 35 + 20 × 8 = 3 660 USD');
ok(free($old)===30&&free($recent)===50,'Un brouillon ne réserve aucun sac');
$cdf=$orders->save(['customer_id'=>(string)$cdfClient,'destination_site_id'=>(string)$deco,'lines'=>[['product_key'=>$flour.'-'.$f10,'quantity_bags'=>'10']]],$agent);
ok((float)order($cdf)['exchange_rate']===2800.0&&order($cdf)['currency']==='CDF','Client facturé en CDF : taux du jour figé sur la commande (2 800)');

// Validation by another user: FIFO booking at the mill, the rest « à produire ».
no(fn()=>$orders->validate($first,$agent),'Validation refusée à l’agent','Permission refusee');
$direction=DagrilIntegrationTest::loginRole('direction');
$message=$orders->validate($first,$direction);
ok(strpos($message,'80 sac(s) réservé(s)')!==false&&strpos($message,'40 à produire')!==false,'Validation : 80 sacs réservés, 40 à produire · « '.$message.' »');
ok(free($old)===0&&free($recent)===0&&(int)v('SELECT reserved_bags FROM finished_stocks WHERE id=?',[$old])===30,'Les lots les plus anciens réservés en premier, plus rien de libre à la minoterie');
ok(!array_filter((new Distribution())->availableStocks(),static fn($s)=>in_array((int)$s['id'],[$old,$recent],true)),'Sortie directe : les sacs réservés au client ne sont plus proposés');
no(fn()=>$orders->validate($first,$direction),'Double validation refusée','déjà validée');
$l50=line($first,$f50);$l10=line($first,$f10);
ok((int)$l50['source_bags']===80&&(int)$l50['remaining_bags']===20&&(int)$l10['remaining_bags']===20,'Ligne 50 kg : 80 réservés, 20 à produire ; ligne 10 kg : 20 à produire');

// New packaging: pending bags booked automatically, oldest order first.
$new50=lot($mino,$flour,$f50,30,50,'now');$new10=lot($mino,$flour,$f10,25,10,'now');
ok(CustomerOrderService::allocatePending($db,$mino,$flour,$f50,$admin)===20&&CustomerOrderService::allocatePending($db,$mino,$flour,$f10,$admin)===20,'Nouvel emballage : 20 sacs de 50 kg et 20 de 10 kg réservés automatiquement');
ok(free($new50)===10&&free($new10)===5&&(int)line($first,$f50)['remaining_bags']===0,'Le surplus de l’emballage reste libre ; plus rien à produire');
$admin=DagrilIntegrationTest::loginRole('administrateur');
no(fn()=>$orders->reserveAvailable($first,$admin),'Réservation du disponible inutile','déjà réservés');

// Assigned transfer to the main office.
no(fn()=>$orders->prepareTransfer($first,['bags'=>[$l50['id']=>'101']],$admin),'Envoi supérieur à la réservation refusé','100 sac(s) réservé(s) disponible(s)');
$message=$orders->prepareTransfer($first,['bags'=>[$l50['id']=>'60',$l10['id']=>'0'],'notes'=>'Camion 1'],$admin);
$transfer=(int)v('SELECT id FROM stock_transfers WHERE customer_order_id=? ORDER BY id DESC LIMIT 1',[$first]);
$items=q('SELECT * FROM stock_transfer_items WHERE transfer_id=? ORDER BY id',[$transfer])->fetchAll();
ok(v('SELECT status FROM stock_transfers WHERE id=?',[$transfer])==='submitted'&&count($items)===2&&(int)$items[0]['source_finished_stock_id']===$old&&(int)$items[0]['requested_bags']===30&&(int)$items[1]['requested_bags']===30&&(int)$items[0]['customer_order_line_id']===(int)$l50['id'],'Envoi soumis : 60 sacs pris sur les lots les plus anciens, rattachés à la ligne · « '.$message.' »');
ok((int)line($first,$f50)['planned_bags']===60&&order($first)['plannable_bags']===60,'60 sacs en préparation d’envoi, 60 encore disponibles pour un autre envoi');
no(fn()=>$orders->prepareTransfer($first,['bags'=>[$l50['id']=>'41']],$admin),'Sacs déjà prévus dans un envoi non comptés deux fois','40 sac(s) réservé(s) disponible(s)');

// Approval: the order's reservation becomes the transfer's, without changing the lots.
$transfers=new TransferService($db);$direction=DagrilIntegrationTest::loginRole('direction');
$transfers->approve($transfer,$direction);
$l=line($first,$f50);
ok((int)$l['source_bags']===40&&(int)$l['awaiting_bags']===60&&(int)$l['remaining_bags']===0&&(int)v('SELECT reserved_bags FROM finished_stocks WHERE id=?',[$old])===30,'Approbation : 60 sacs confiés à l’envoi, réservation des lots inchangée');
$admin=DagrilIntegrationTest::loginRole('administrateur');
$shipment=$transfers->ship($transfer,[$items[0]['id']=>30,$items[1]['id']=>20],$admin,'CAM-01');
ok((int)line($first,$f50)['transit_bags']===50&&(int)line($first,$f50)['awaiting_bags']===10,'Expédition partielle : 50 en route, 10 attendent le camion');

// Reception at the office: accepted bags reserved for the client, rejected bags back to the remainder.
$shipItems=q('SELECT * FROM transfer_shipment_items WHERE shipment_id=? ORDER BY id',[$shipment])->fetchAll();
$transfers->receive($shipment,bin2hex(random_bytes(8)),[$shipItems[0]['id']=>['accepted_bags'=>30,'rejected_bags'=>0],$shipItems[1]['id']=>['accepted_bags'=>15,'rejected_bags'=>5,'quality_notes'=>'Sacs déchirés']],$admin,null);
$office=(int)v("SELECT finished_stock_id FROM customer_order_allocations WHERE stage='office' AND order_line_id=?",[$l50['id']]);
$l=line($first,$f50);
ok((int)$l['office_bags']===45&&(int)$l['transit_bags']===0&&(int)$l['remaining_bags']===5&&(int)v('SELECT site_id FROM finished_stocks WHERE id=?',[$office])===$deco,'Réception à DECO : 45 sacs au bureau, 5 refusés à réserver de nouveau');
ok(free($office)===0&&(int)v('SELECT reserved_bags FROM finished_stocks WHERE id=?',[$office])===45,'Au bureau, les 45 sacs sont réservés au client : rien de libre pour le comptoir');
$return=(int)v('SELECT id FROM transfer_returns WHERE transfer_id=?',[$transfer]);
$transfers->dispatchReturn($return,$admin);$transfers->receiveReturn($return,$admin);
ok((int)line($first,$f50)['remaining_bags']===0&&(int)line($first,$f50)['source_bags']===45,'Retour des 5 sacs refusés à la minoterie : réservés de nouveau automatiquement pour la commande');
no(fn()=>$orders->cancel($first,['reason'=>'Test'],$direction),'Annulation refusée pendant un envoi en cours','est en cours');

// Closure: the 10 bags never shipped go back to the order's reservation.
$transfers->close($transfer,$admin);
$l=line($first,$f50);
ok((int)$l['source_bags']===55&&(int)$l['awaiting_bags']===0&&(int)$l['office_bags']===45&&(int)$l['remaining_bags']===0,'Clôture : 10 sacs non expédiés réservés de nouveau pour la commande');

// An approved transfer cancelled through the cancellations module also returns its bags to the order.
q("INSERT IGNORE INTO cancellation_policies(entity_type,label,component,allowed_statuses,requires_approval_after_validation,authorized_action,stock_effects,blocking_dependencies,document_types,status) VALUES('transfer','Transfert inter-sites','transfers','draft,submitted,approved,reserved',1,'read','Libération des réservations','Toute expédition ou réception','BTR,BTI,BR,BS','active')");
$orders->prepareTransfer($first,['bags'=>[$l50['id']=>'20']],$admin);$second=(int)v('SELECT id FROM stock_transfers WHERE customer_order_id=? ORDER BY id DESC LIMIT 1',[$first]);
$transfers->approve($second,DagrilIntegrationTest::loginRole('direction'));$admin=DagrilIntegrationTest::loginRole('administrateur');
$cancellations=new CancellationService($db);$request=$cancellations->request('transfer',$second,'Camion en panne',$admin);$cancellations->approve($request,DagrilIntegrationTest::loginRole('direction'));
ok(v('SELECT status FROM stock_transfers WHERE id=?',[$second])==='cancelled'&&(int)line($first,$f50)['source_bags']===55&&(int)line($first,$f50)['awaiting_bags']===0,'Envoi approuvé puis annulé : ses 20 sacs reviennent à la réservation de la commande');
ok((int)v("SELECT COUNT(*) FROM customer_order_movements WHERE order_id=? AND movement_type='transfer_back'",[$first])===2,'Journal : retours en réservation après clôture et après annulation');

// Cancelling the order frees every reservation, at the mill and at the office.
$direction=DagrilIntegrationTest::loginRole('direction');
no(fn()=>$orders->cancel($first,['reason'=>''],$direction),'Motif obligatoire','motif d’annulation');
$reservedMill=(int)v('SELECT SUM(reserved_bags) FROM finished_stocks WHERE site_id=?',[$mino]);
$orders->cancel($first,['reason'=>'Client parti'],$direction);
ok(order($first)['status']==='cancelled'&&(int)v('SELECT SUM(reserved_bags) FROM finished_stocks WHERE site_id=?',[$mino])===$reservedMill-75&&free($office)===45,'Annulation : 75 sacs libérés à la minoterie, 45 sacs libres au bureau pour le comptoir');
ok((int)v("SELECT SUM(a.quantity_bags) FROM customer_order_allocations a JOIN customer_order_lines l ON l.id=a.order_line_id WHERE l.order_id=?",[$first])===0,'Plus aucune réservation pour la commande annulée');

// Pages.
$page=function(){http_response_code(200);ob_start();(new CustomerOrderController())->index();return ob_get_clean();};
$admin=DagrilIntegrationTest::loginRole('administrateur');$html=$page();
ok(strpos($html,'BC-'.date('Y').'-0001')!==false&&strpos($html,'id="order-new"')!==false&&strpos($html,'Annulée')!==false&&strpos($html,'Warning:')===false&&strpos($html,'Fatal error')===false,'Page des commandes : historique, nouvelle commande, rendu sans erreur PHP');
DagrilIntegrationTest::loginRole('agent-distribution');$html=$page();
ok(strpos($html,'order-cancel-')===false&&strpos($html,'/validate"')===false,'Agent : ni validation ni annulation proposées');
$menu=[];foreach(Auth::menuGroups() as $group)foreach($group['items'] as $entry)$menu[]=$entry['path'];
ok(in_array('sales/orders',$menu,true),'Menu de l’agent : Commandes clients');
DagrilIntegrationTest::finish();
