<?php
/** Cattle flow: supplier reception, farm slaughter, double weighing and butchery reception, on a disposable database with migration 043. */
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/CattleFlowService.php';
require_once __DIR__.'/../app/services/OperationalAttentionService.php';
require_once __DIR__.'/../app/models/Livestock.php';
$cattle=new CattleFlowService($db);$user=DagrilIntegrationTest::loginRole('administrateur');
$mut=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");$bouch=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='BOUCH'");Auth::selectSite((string)$mut);
function q($sql,$params=[]){global $db;$q=$db->prepare($sql);$q->execute($params);return $q;}
function k($patch=[]){return array_replace(['operation_key'=>bin2hex(random_bytes(16))],$patch);}
function ok($condition,$message){DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
function no($callback,$message,$expected){try{$callback();}catch(RuntimeException $e){ok(strpos($e->getMessage(),$expected)!==false,$message.' · « '.$e->getMessage().' »');return;}ok(false,$message);}
function lot($id){global $cattle;foreach($cattle->directory()['lots'] as $l)if((int)$l['id']===(int)$id)return $l;throw new RuntimeException('Lot absent');}
$bovin=(int)DagrilIntegrationTest::scalar("SELECT id FROM livestock_species WHERE code='BOVIN'");
q("INSERT INTO suppliers(name,status) VALUES(?,'active')",['Éleveur test '.bin2hex(random_bytes(3))]);$supplier=(int)$db->lastInsertId();
q("INSERT INTO livestock_facilities(site_id,code,name,facility_type,capacity_heads,status) VALUES(?,?,'Étable de validation','housing',30,'active')",[$mut,'ETB-'.strtoupper(bin2hex(random_bytes(3)))]);$barn=(int)$db->lastInsertId();
ok($cattle->cattleAvailable(),'Migration 043 détectée : parcours bovin actif');

// Reception from a supplier.
$base=['site_id'=>(string)$mut,'supplier_id'=>(string)$supplier,'facility_id'=>(string)$barn,'received_at'=>date('Y-m-d H:i',strtotime('-40 days'))];
no(fn()=>$cattle->receiveCattle(k($base+['heads'=>'31']),$user),'Capacité du bâtiment respectée','Capacité du bâtiment dépassée');
no(fn()=>$cattle->receiveCattle(k(array_replace($base,['heads'=>'2','supplier_id'=>'999999'])),$user),'Fournisseur inconnu refusé','fournisseur');
no(fn()=>$cattle->receiveCattle(k(array_replace($base,['heads'=>'2','received_at'=>date('Y-m-d H:i',strtotime('+2 days'))])),$user),'Arrivée future refusée','future');
$lot=$cattle->receiveCattle(k($base+['heads'=>'10','delivery_reference'=>'BL-2026-118','weighing_mode'=>'gross','gross_kg'=>'16500','tare_kg'=>'12500','tare_note'=>'Camion','notes'=>'Animaux en bon état']),$user);
$r=q('SELECT * FROM cattle_receipts WHERE batch_id=?',[$lot])->fetch();
ok((int)$r['supplier_id']===$supplier&&(int)$r['heads']===10&&(float)$r['live_weight_kg']===4000.0&&$r['tare_note']==='Camion'&&$r['delivery_reference']==='BL-2026-118','Réception : fournisseur, bon de livraison, poids vif brut − tare (4 000 kg nets)');
$l=lot($lot);ok((int)$l['current_heads']===10&&(float)$l['average_live_kg']===400.0&&$l['supplier_name']!==null,'Lot créé : 10 bovins de 400 kg en moyenne');
ok((int)DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM livestock_head_movements WHERE batch_id=? AND movement_type='stocking' AND reference_type='cattle_receipts'",[$lot])===1,'Mouvement d’effectif de réception');
$light=$cattle->receiveCattle(k($base+['heads'=>'2']),$user);
ok(lot($light)['average_live_kg']===null&&q('SELECT live_weight_kg FROM cattle_receipts WHERE batch_id=?',[$light])->fetchColumn()===null,'Poids vif facultatif à la réception');
no(fn()=>(new Livestock())->createBatch(['site_id'=>$mut,'species_id'=>$bovin,'facility_id'=>$barn,'purpose'=>'meat','started_at'=>date('Y-m-d'),'initial_heads'=>3,'initial_average_weight_kg'=>300],$user),'Parcours générique refusé pour les bovins','Élevage › Bœufs');

// Farm slaughter.
$s=['batch_id'=>(string)$lot,'slaughtered_at'=>date('Y-m-d H:i'),'expiry_date'=>date('Y-m-d',strtotime('+5 days'))];
no(fn()=>$cattle->slaughter(k($s+['heads'=>'11','weighing_mode'=>'net','net_kg'=>'100']),$user),'Plus de bovins que l’effectif refusé','disponible');
no(fn()=>$cattle->slaughter(k(array_replace($s,['heads'=>'1','weighing_mode'=>'net','net_kg'=>'100','slaughtered_at'=>date('Y-m-d H:i',strtotime('-60 days'))])),$user),'Abattage avant l’arrivée refusé','précéder');
no(fn()=>$cattle->slaughter(k(array_replace($s,['heads'=>'1','weighing_mode'=>'net','net_kg'=>'100','expiry_date'=>date('Y-m-d',strtotime('-1 day'))])),$user),'DLC dépassée refusée','DLC');
$c1=$cattle->slaughter(k($s+['heads'=>'4','weighing_mode'=>'gross','gross_kg'=>'900','tare_kg'=>'20','tare_note'=>'Crochets']),$user);
$c=q('SELECT c.net_weight_kg,c.tare_note,i.code FROM livestock_conversions c JOIN cattle_slaughter_details d ON d.conversion_id=c.id JOIN butchery_items i ON i.id=d.item_id WHERE c.id=?',[$c1])->fetch();
ok((float)$c['net_weight_kg']===880.0&&$c['tare_note']==='Crochets'&&$c['code']==='MP-BOEUF','Abattage pesé brut − tare, article Carcasse de bœuf');
ok(lot($lot)['available_heads']===6,'Bovins en attente de validation déduits des disponibles');
no(fn()=>$cattle->slaughter(k($s+['heads'=>'7','weighing_mode'=>'net','net_kg'=>'100']),$user),'Abattages en attente pris en compte','6 bovin(s) disponible(s)');
ok((new OperationalAttentionService($db))->countFor('pending_cattle_slaughters')>=1,'Abattage en attente signalé dans le menu');
$stock=$cattle->validateSlaughter($c1,$user);
ok((int)lot($lot)['current_heads']===6&&(float)DagrilIntegrationTest::scalar('SELECT quantity_kg FROM livestock_weight_stocks WHERE id=?',[$stock])===880.0,'Validation : 4 bovins sortis, 880 kg de carcasses à la ferme');
ok(lot($lot)['carcass_yield_pct']===55.0,'Rendement carcasse : 880 kg pour 4 × 400 kg vifs = 55 %');
$c2=$cattle->slaughter(k($s+['heads'=>'1','weighing_mode'=>'net','net_kg'=>'210']),$user);
$cattle->cancelSlaughter($c2,k(['reason'=>'Pesée à refaire']),$user);
ok(DagrilIntegrationTest::scalar('SELECT status FROM livestock_conversions WHERE id=?',[$c2])==='cancelled'&&lot($lot)['available_heads']===6,'Abattage annulé, bovins de nouveau disponibles');

// Shipment and double weighing at the butchery.
$t=$cattle->shipment(k(['weight_stock_id'=>$stock,'quantity_kg'=>'880','truck_plate'=>'BOV-01','driver_name'=>'Chauffeur test']),$user);
$cattle->advanceShipment($t,'approve',$user);$cattle->advanceShipment($t,'dispatch',$user);
ok(strpos((string)DagrilIntegrationTest::scalar('SELECT transfer_number FROM livestock_transfers WHERE id=?',[$t]),'BTR-BOEUF-')===0,'Envoi numéroté BTR-BOEUF');
ok(($cattle->shipmentBtr($t)['flow']??'')==='cattle','Bon de transfert identifié comme carcasses de bœuf');
Auth::selectSite((string)$bouch);
$weighing=new TransitWeighingService($db);$weighing->saveTolerance(['species_id'=>(string)$bovin,'tolerance_pct'=>'2','approver_role_id'=>''],$user,$bouch);
$incoming=array_values(array_filter($cattle->butcheryDirectory()['incoming'],static fn($row)=>(int)$row['id']===$t));
ok(count($incoming)===1&&$incoming[0]['flow']==='cattle'&&(float)$incoming[0]['tolerance_pct']===2.0,'Carcasses de bœuf attendues à la réception, tolérance de 2 %');
no(fn()=>$cattle->arrival(k(['transfer_id'=>$t,'weighing_mode'=>'net','net_kg'=>'850']),$user),'Écart de 3,4 % hors tolérance sans motif refusé','précisez le motif');
$receipt=$cattle->arrival(k(['transfer_id'=>$t,'weighing_mode'=>'net','net_kg'=>'868']),$user);
$r=q('SELECT receipt_number,sent_weight_kg,received_weight_kg,weight_gap_kg FROM butchery_receipts WHERE id=?',[$receipt])->fetch();
ok(strpos($r['receipt_number'],'REC-BOEUF-')===0&&(float)$r['sent_weight_kg']===880.0&&(float)$r['received_weight_kg']===868.0&&(float)$r['weight_gap_kg']===12.0,'Pesée d’arrivée : 868 kg reçus, perte de 12 kg (1,36 %) dans la tolérance');
$cattle->receive(k(['receipt_id'=>$receipt,'visual_conform'=>'1','documents_conform'=>'1','temperature_c'=>'3']),$user);
$raw=q('SELECT l.lot_number,l.quantity_kg,i.code FROM butchery_raw_lots l JOIN butchery_items i ON i.id=l.item_id WHERE l.receipt_id=?',[$receipt])->fetch();
ok((float)$raw['quantity_kg']===868.0&&$raw['code']==='MP-BOEUF'&&strpos($raw['lot_number'],'CARC-BOEUF-')===0,'Chambre froide : lot de carcasses de bœuf au poids reçu (868 kg)');
ok(DagrilIntegrationTest::scalar('SELECT status FROM livestock_transfers WHERE id=?',[$t])==='received','Envoi reçu à la boucherie');
$report=$weighing->report($bouch,['from'=>date('Y-m-01',strtotime('-1 day')),'to'=>date('Y-m-d'),'species'=>(string)$bovin]);
ok(count(array_filter($report['rows'],static fn($row)=>(int)$row['id']===$receipt))===1&&$report['bySpecies'][0]['species']!=='','Rapport des écarts : réception bovine incluse');
DagrilIntegrationTest::finish();
