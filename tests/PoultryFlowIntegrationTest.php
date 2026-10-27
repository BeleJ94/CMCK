<?php
/** Poultry flow: receptions and hatching, daily records, farm slaughter, carcass and live shipments, pieces at the butchery and the counter, on a disposable database with migration 044. */
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/PoultryFlowService.php';
require_once __DIR__.'/../app/services/OperationalAttentionService.php';
require_once __DIR__.'/../app/models/Livestock.php';
$poultry=new PoultryFlowService($db);$user=DagrilIntegrationTest::loginRole('administrateur');
$mut=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");$bouch=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='BOUCH'");Auth::selectSite((string)$mut);
function q($sql,$params=[]){global $db;$q=$db->prepare($sql);$q->execute($params);return $q;}
function k($patch=[]){return array_replace(['operation_key'=>bin2hex(random_bytes(16))],$patch);}
function ok($condition,$message){DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
function no($callback,$message,$expected){try{$callback();}catch(RuntimeException $e){ok(strpos($e->getMessage(),$expected)!==false,$message.' · « '.$e->getMessage().' »');return;}ok(false,$message);}
function lot($id){global $poultry;foreach($poultry->directory()['lots'] as $l)if((int)$l['id']===(int)$id)return $l;throw new RuntimeException('Lot absent');}
function heads($id){return (int)DagrilIntegrationTest::scalar('SELECT current_heads FROM livestock_batches WHERE id=?',[$id]);}
$poulet=(int)DagrilIntegrationTest::scalar("SELECT id FROM livestock_species WHERE code='POULET'");
q("INSERT INTO suppliers(name,status) VALUES(?,'active')",['Couvoir test '.bin2hex(random_bytes(3))]);$supplier=(int)$db->lastInsertId();
q("INSERT INTO livestock_facilities(site_id,code,name,facility_type,capacity_heads,status) VALUES(?,?,'Poulailler de validation','poultry_house',1000,'active')",[$mut,'POUL-'.strtoupper(bin2hex(random_bytes(3)))]);$house=(int)$db->lastInsertId();
ok($poultry->poultryAvailable(),'Migration 044 détectée : parcours volaille actif');

// Receptions from a supplier: broiler chicks and laying hens.
$base=['site_id'=>(string)$mut,'supplier_id'=>(string)$supplier,'facility_id'=>(string)$house,'received_at'=>date('Y-m-d H:i',strtotime('-50 days'))];
no(fn()=>$poultry->receivePoultry(k($base+['flock_type'=>'broiler','heads'=>'1001']),$user),'Capacité du poulailler respectée','Capacité du poulailler dépassée');
no(fn()=>$poultry->receivePoultry(k($base+['flock_type'=>'dinde','heads'=>'10']),$user),'Type de volaille contrôlé','chair ou pondeuses');
no(fn()=>$poultry->receivePoultry(k(array_replace($base,['flock_type'=>'broiler','heads'=>'10','supplier_id'=>'999999'])),$user),'Fournisseur inconnu refusé','fournisseur');
$broilers=$poultry->receivePoultry(k($base+['flock_type'=>'broiler','heads'=>'500','age_days'=>'1','delivery_reference'=>'BL-POUS-7']),$user);
$l=lot($broilers);ok($l['flock_type']==='broiler'&&$l['origin']==='supplier'&&(int)$l['current_heads']===500&&$l['age_now']===51&&$l['average_live_kg']===null,'Lot de chair : 500 poussins d’un jour, 51 jours aujourd’hui, poids facultatif');
ok(DagrilIntegrationTest::scalar('SELECT purpose FROM livestock_batches WHERE id=?',[$broilers])==='meat','Objectif chair du lot');
$layers=$poultry->receivePoultry(k($base+['flock_type'=>'layer','heads'=>'200','weighing_mode'=>'gross','gross_kg'=>'480','tare_kg'=>'80','tare_note'=>'Cages']),$user);
$l=lot($layers);ok($l['flock_type']==='layer'&&(float)$l['average_live_kg']===2.0&&$l['tare_note']==='Cages','Lot de pondeuses : 400 kg nets pour 200 poules, soit 2 kg par poule');
no(fn()=>(new Livestock())->createBatch(['site_id'=>$mut,'species_id'=>$poulet,'facility_id'=>$house,'purpose'=>'meat','started_at'=>date('Y-m-d'),'initial_heads'=>3],$user),'Parcours générique refusé pour les poulets','Élevage › Volailles');

// Hatching from the layers gives a broiler lot.
no(fn()=>$poultry->hatch(k(['mother_batch_id'=>(string)$broilers,'facility_id'=>(string)$house,'heads'=>'10','hatched_at'=>date('Y-m-d H:i')]),$user),'Éclosion rattachée à des pondeuses uniquement','pondeuses');
no(fn()=>$poultry->hatch(k(['mother_batch_id'=>(string)$layers,'facility_id'=>(string)$house,'heads'=>'10','hatched_at'=>date('Y-m-d H:i',strtotime('-60 days'))]),$user),'Éclosion avant l’arrivée des pondeuses refusée','précéder');
$chicks=$poultry->hatch(k(['mother_batch_id'=>(string)$layers,'facility_id'=>(string)$house,'heads'=>'120','hatched_at'=>date('Y-m-d H:i',strtotime('-2 days'))]),$user);
$l=lot($chicks);ok($l['flock_type']==='broiler'&&$l['origin']==='hatch'&&(int)$l['mother_batch_id']===$layers&&$l['age_now']===2,'Éclosion : 120 poussins de chair, rattachés aux pondeuses, âgés de 2 jours');
ok((int)lot($layers)['hatched_heads']===120&&DagrilIntegrationTest::scalar("SELECT movement_type FROM livestock_head_movements WHERE batch_id=? AND reference_type='poultry_lot_profiles'",[$chicks])==='birth','Pondeuses : 120 poussins éclos, mouvement de naissance');

// Daily records: eggs for layers, mortality for every lot.
no(fn()=>$poultry->record(k(['batch_id'=>(string)$broilers,'recorded_on'=>date('Y-m-d'),'eggs_count'=>'5']),$user),'Œufs refusés sur un lot de chair','pondeuses');
no(fn()=>$poultry->record(k(['batch_id'=>(string)$layers,'recorded_on'=>date('Y-m-d')]),$user),'Saisie vide refusée','Saisissez');
$poultry->record(k(['batch_id'=>(string)$layers,'recorded_on'=>date('Y-m-d'),'eggs_count'=>'150','mortality_heads'=>'2']),$user);
$poultry->record(k(['batch_id'=>(string)$layers,'recorded_on'=>date('Y-m-d'),'eggs_count'=>'30']),$user);
$l=lot($layers);ok((int)$l['eggs_today']===180&&(int)$l['current_heads']===198&&(int)$l['dead_today']===2,'Deux saisies du jour : 180 œufs, 2 poules mortes retirées du lot');
$poultry->record(k(['batch_id'=>(string)$broilers,'recorded_on'=>date('Y-m-d',strtotime('-1 day')),'mortality_heads'=>'5','notes'=>'Chaleur']),$user);
ok(heads($broilers)===495&&lot($broilers)['mortality_pct']===1.0,'Mortalité quotidienne des poulets : 5 sur 500, soit 1 %');
no(fn()=>$poultry->record(k(['batch_id'=>(string)$chicks,'recorded_on'=>date('Y-m-d'),'mortality_heads'=>'121']),$user),'Mortalité supérieure à l’effectif refusée','disponible');

// Farm slaughter of hens, with birds found dead at the slaughter.
$s=['slaughtered_at'=>date('Y-m-d H:i'),'expiry_date'=>date('Y-m-d',strtotime('+4 days'))];
$hens=$poultry->slaughter(k($s+['batch_id'=>(string)$layers,'heads'=>'10','dead_heads'=>'1','weighing_mode'=>'gross','gross_kg'=>'25','tare_kg'=>'1','tare_note'=>'Bacs']),$user);
$c=q('SELECT c.net_weight_kg,c.input_heads,d.dead_heads,i.code FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id JOIN butchery_items i ON i.id=d.item_id WHERE c.id=?',[$hens])->fetch();
ok((float)$c['net_weight_kg']===24.0&&(int)$c['dead_heads']===1&&$c['code']==='MP-POULE','Abattage de poules : 10 carcasses, 24 kg nets, 1 poule morte, article Carcasse de poule');
ok(lot($layers)['available_heads']===187,'Poules engagées dans l’abattage en attente : 187 disponibles');
ok((new OperationalAttentionService($db))->countFor('pending_poultry_slaughters')>=1,'Abattage de volailles en attente signalé dans le menu');
$henStock=$poultry->validateSlaughter($hens,$user);
ok(heads($layers)===187&&(float)DagrilIntegrationTest::scalar('SELECT quantity_kg FROM livestock_weight_stocks WHERE id=?',[$henStock])===24.0,'Validation : 11 poules sorties du lot, 24 kg de carcasses à la ferme');
ok((int)DagrilIntegrationTest::scalar("SELECT quantity_heads FROM livestock_head_movements WHERE batch_id=? AND movement_type='mortality' AND reference_type='poultry_slaughter_details'",[$layers])===1,'Mortalité à l’abattage tracée');
ok($poultry->validateSlaughter($hens,$user)===$henStock,'Double validation sans effet : même stock de carcasses');
no(fn()=>$poultry->cancelSlaughter($hens,k(['reason'=>'Test']),$user),'Abattage validé non annulable','en attente');

// Broiler slaughter, then carcass shipments counted in pieces.
$cut=$poultry->slaughter(k($s+['batch_id'=>(string)$broilers,'heads'=>'100','weighing_mode'=>'net','net_kg'=>'180']),$user);
$cancelled=$poultry->slaughter(k($s+['batch_id'=>(string)$broilers,'heads'=>'5','weighing_mode'=>'net','net_kg'=>'9']),$user);
$poultry->cancelSlaughter($cancelled,k(['reason'=>'Pesée à refaire']),$user);
$stock=$poultry->validateSlaughter($cut,$user);
ok(heads($broilers)===395&&lot($broilers)['average_carcass_kg']===1.8,'Poulets : 100 carcasses de 1,8 kg, abattage annulé sans effet');
$ship=['weight_stock_id'=>(string)$stock,'truck_plate'=>'VOL-01','driver_name'=>'Chauffeur test'];
no(fn()=>$poultry->shipment(k($ship+['quantity_kg'=>'70','quantity_pieces'=>'101']),$user),'Plus de carcasses que le stock refusé','100 pièce(s)');
no(fn()=>$poultry->shipment(k($ship+['quantity_kg'=>'180','quantity_pieces'=>'99']),$user),'Tout le poids sans toutes les carcasses refusé','100 pièce(s)');
$t1=$poultry->shipment(k($ship+['quantity_kg'=>'70','quantity_pieces'=>'40']),$user);
no(fn()=>$poultry->shipment(k($ship+['quantity_kg'=>'100','quantity_pieces'=>'60']),$user),'Les dernières carcasses emportent le poids restant','110,000 kg');
$t2=$poultry->shipment(k($ship+['quantity_kg'=>'110','quantity_pieces'=>'60']),$user);
foreach([$t1,$t2] as $t){$poultry->advanceShipment($t,'approve',$user);}
$poultry->advanceShipment($t1,'dispatch',$user);
ok(strpos((string)DagrilIntegrationTest::scalar('SELECT transfer_number FROM livestock_transfers WHERE id=?',[$t1]),'BTR-VOL-')===0&&(int)DagrilIntegrationTest::scalar('SELECT quantity_pieces FROM livestock_transfers WHERE id=?',[$t1])===40,'Envoi BTR-VOL de 40 carcasses');
ok(($poultry->shipmentBtr($t1)['flow']??'')==='poultry','Bon de transfert des carcasses de volaille');

// Live broilers: counted and weighed at the farm, out of the lot at dispatch.
$live=['heads'=>'50','weighing_mode'=>'gross','gross_kg'=>'120','tare_kg'=>'20','tare_note'=>'Cages','truck_plate'=>'VIF-01','driver_name'=>'Chauffeur vif'];
no(fn()=>$poultry->liveShipment(k($live+['batch_id'=>(string)$layers]),$user),'Poules refusées à l’envoi vivant','abattues à la ferme');
no(fn()=>$poultry->liveShipment(k(array_replace($live,['batch_id'=>(string)$broilers,'heads'=>'396'])),$user),'Envoi vivant limité à l’effectif disponible','395 poulet(s)');
$lt=$poultry->liveShipment(k($live+['batch_id'=>(string)$broilers]),$user);
ok(lot($broilers)['available_heads']===345&&heads($broilers)===395,'Envoi vivant en préparation : poulets engagés, encore dans le lot');
$poultry->advanceShipment($lt,'approve',$user);$poultry->advanceShipment($lt,'dispatch',$user);
ok(heads($broilers)===345&&DagrilIntegrationTest::scalar('SELECT status FROM livestock_transfers WHERE id=?',[$lt])==='in_transit','Expédition : 50 poulets vivants sortis du lot');
$btr=$poultry->shipmentBtr($lt);ok(($btr['flow']??'')==='poultry_live'&&(int)$btr['quantity_heads']===50&&(float)$btr['live_tare_kg']===20.0,'Bon de transfert des poulets vivants, têtes et tare des cages');

// Butchery: pieces counted at the arrival, live birds become a sellable lot.
Auth::selectSite((string)$bouch);
(new TransitWeighingService($db))->saveTolerance(['species_id'=>(string)$poulet,'tolerance_pct'=>'3','approver_role_id'=>''],$user,$bouch);
$queue=$poultry->butcheryDirectory()['incoming'];$flows=[];foreach($queue as $row)$flows[(int)$row['id']]=$row['flow'];
ok(($flows[$t1]??'')==='poultry'&&($flows[$lt]??'')==='poultry_live','Carcasses et poulets vivants attendus à la réception');
no(fn()=>$poultry->arrival(k(['transfer_id'=>$t1,'weighing_mode'=>'net','net_kg'=>'69.5']),$user),'Pièces reçues obligatoires','Comptez les carcasse(s)');
no(fn()=>$poultry->arrival(k(['transfer_id'=>$t1,'weighing_mode'=>'net','net_kg'=>'69.5','received_pieces'=>'41']),$user),'Plus de pièces que l’envoi refusé','vérifiez le comptage');
no(fn()=>$poultry->arrival(k(['transfer_id'=>$t1,'weighing_mode'=>'net','net_kg'=>'69.5','received_pieces'=>'39']),$user),'Pièce manquante sans motif refusée','manquant');
$r1=$poultry->arrival(k(['transfer_id'=>$t1,'weighing_mode'=>'net','net_kg'=>'69.5','received_pieces'=>'39','gap_reason'=>'Carcasse abîmée écartée']),$user);
ok((int)DagrilIntegrationTest::scalar('SELECT sent_pieces FROM butchery_receipts WHERE id=?',[$r1])===40&&(int)DagrilIntegrationTest::scalar('SELECT received_pieces FROM butchery_receipts WHERE id=?',[$r1])===39,'Arrivée : 39 carcasses reçues sur 40, motif conservé');
$poultry->receive(k(['receipt_id'=>$r1,'visual_conform'=>'1','documents_conform'=>'1']),$user);
$raw1=q('SELECT * FROM butchery_raw_lots WHERE receipt_id=?',[$r1])->fetch();
ok(strpos($raw1['lot_number'],'CARC-VOL-')===0&&(int)$raw1['quantity_pieces']===39&&(float)$raw1['quantity_kg']===69.5,'Chambre froide : 39 carcasses de poulet, 69,5 kg');
$r2=$poultry->arrival(k(['transfer_id'=>$lt,'weighing_mode'=>'net','net_kg'=>'97','received_pieces'=>'49','gap_reason'=>'Un poulet mort pendant le transport']),$user);
$poultry->receive(k(['receipt_id'=>$r2,'visual_conform'=>'1','documents_conform'=>'1']),$user);
$vif=q('SELECT l.*,i.code FROM butchery_finished_lots l JOIN butchery_items i ON i.id=l.item_id WHERE l.receipt_id=?',[$r2])->fetch();
ok($vif&&$vif['code']==='VOL-VIF'&&(int)$vif['quantity_pieces']===49&&(float)$vif['quantity_kg']===97.0&&strpos($vif['lot_number'],'VIF-VOL-')===0,'Poulets vivants : lot vendable de 49 poulets, 97 kg');
ok(DagrilIntegrationTest::scalar("SELECT movement_type FROM butchery_finished_movements WHERE finished_lot_id=?",[$vif['id']])==='receipt_in','Entrée du lot vivant tracée');

// Slaughter of live birds at the butchery: pieces leave the live lot, carcasses enter the cold room.
$bs=['live_lot_id'=>(string)$vif['id'],'slaughtered_at'=>date('Y-m-d H:i'),'expiry_date'=>date('Y-m-d',strtotime('+3 days'))];
no(fn()=>$poultry->butcherySlaughter(k($bs+['heads'=>'49','dead_heads'=>'1','weighing_mode'=>'net','net_kg'=>'70']),$user),'Plus de poulets que le lot vivant refusé','49 disponible');
$bslaughter=$poultry->butcherySlaughter(k($bs+['heads'=>'10','dead_heads'=>'1','weighing_mode'=>'gross','gross_kg'=>'16','tare_kg'=>'1','tare_note'=>'Bac']),$user);
$vif=q('SELECT * FROM butchery_finished_lots WHERE id=?',[$vif['id']])->fetch();
$bsRow=q('SELECT * FROM poultry_butchery_slaughters WHERE id=?',[$bslaughter])->fetch();
ok((int)$vif['quantity_pieces']===38&&(float)$bsRow['live_kg']===21.775&&(float)$vif['quantity_kg']===75.225,'Abattage en boucherie : 11 poulets (dont 1 mort) et 21,775 kg vifs sortis, 38 restent');
$raw2=q('SELECT * FROM butchery_raw_lots WHERE id=?',[$bsRow['raw_lot_id']])->fetch();
ok((int)$raw2['quantity_pieces']===10&&(float)$raw2['quantity_kg']===15.0&&(int)$raw2['livestock_batch_id']===$broilers,'10 carcasses de 15 kg au froid, rattachées au lot d’élevage');

// Counter supply by pieces: kilos follow the pieces.
$counter=(int)DagrilIntegrationTest::scalar("SELECT id FROM butchery_counters WHERE site_id=? AND status='active' ORDER BY id LIMIT 1",[$bouch]);
if(!$counter){$counter=$poultry->createCounter(k(['name'=>'Comptoir volaille']),$user);}
no(fn()=>$poultry->move(k(['finished_lot_id'=>$vif['id'],'counter_id'=>$counter,'direction'=>'supply','quantity_kg'=>'5']),$user),'Lot suivi en pièces : poids seul refusé','nombre de pièces');
$poultry->move(k(['finished_lot_id'=>$vif['id'],'counter_id'=>$counter,'direction'=>'supply','quantity_pieces'=>'6']),$user);
$cs=q('SELECT * FROM butchery_counter_stocks WHERE counter_id=? AND finished_lot_id=?',[$counter,$vif['id']])->fetch();
ok((int)$cs['quantity_pieces']===6&&(float)$cs['quantity_kg']===11.877,'Comptoir : 6 poulets vivants et leurs 11,877 kg');
$poultry->move(k(['finished_lot_id'=>$vif['id'],'counter_id'=>$counter,'direction'=>'return_cold','quantity_pieces'=>'1']),$user);
$cs=q('SELECT * FROM butchery_counter_stocks WHERE id=?',[$cs['id']])->fetch();ok((int)$cs['quantity_pieces']===5&&(float)$cs['quantity_kg']===9.898,'Retour en réserve d’un poulet vivant');

// Transformation of carcasses counted in pieces: whole birds and cuts.
$entier=(int)DagrilIntegrationTest::scalar("SELECT id FROM butchery_items WHERE code='VOL-ENTIER'");$morceaux=(int)DagrilIntegrationTest::scalar("SELECT id FROM butchery_items WHERE code='MORCEAUX-VOLAILLE'");$volaille=(int)DagrilIntegrationTest::scalar("SELECT id FROM butchery_items WHERE code='MP-VOLAILLE'");
$expiry=date('Y-m-d',strtotime('+3 days'));
$tf=['source_item_id'=>(string)$volaille,'lot_mode'=>'auto','production_date'=>date('Y-m-d'),'counter_id'=>(string)$counter];
no(fn()=>$poultry->transform(k($tf+['quantity_kg'=>'10','outputs'=>[['item_id'=>$morceaux,'quantity_kg'=>'5','expiry_date'=>$expiry]]]),$user),'Carcasses suivies en pièces : nombre de pièces obligatoire','pièces');
no(fn()=>$poultry->transform(k($tf+['quantity_pieces'=>'15','outputs'=>[['item_id'=>$entier,'quantity_kg'=>'10','expiry_date'=>$expiry]],'loss_confirmed'=>'1']),$user),'Produit entier sans nombre de pièces refusé','Poulet entier');
no(fn()=>$poultry->transform(k($tf+['quantity_pieces'=>'50','outputs'=>[['item_id'=>$morceaux,'quantity_kg'=>'5','expiry_date'=>$expiry]],'loss_confirmed'=>'1']),$user),'Plus de pièces que le stock refusé','Pièces disponibles insuffisantes');
// The butchery slaughter lot (earliest DLC) gives its 10 carcasses first, then 5 of the farm lot: 15 + 5 × 69,5 / 39 kg.
$order=$poultry->transform(k($tf+['quantity_pieces'=>'15','outputs'=>[['item_id'=>$entier,'quantity_kg'=>'16','quantity_pieces'=>'9','counter_pieces'=>'4','expiry_date'=>$expiry],['item_id'=>$morceaux,'quantity_kg'=>'7.5','counter_kg'=>'2','expiry_date'=>$expiry]],'loss_confirmed'=>'1']),$user);
$o=q('SELECT * FROM butchery_orders WHERE id=?',[$order])->fetch();
ok((float)$o['actual_input_kg']===23.91&&(float)$o['output_kg']===23.5,'Transformation : 15 carcasses sorties, soit 23,910 kg ; 23,5 kg de produits');
ok((int)DagrilIntegrationTest::scalar('SELECT reserved_pieces FROM butchery_raw_lots WHERE id=?',[$raw2['id']])===10&&(int)DagrilIntegrationTest::scalar('SELECT reserved_pieces FROM butchery_raw_lots WHERE id=?',[$raw1['id']])===5,'Pièces réservées par DLC la plus proche : 10 puis 5');
ok((float)DagrilIntegrationTest::scalar('SELECT counter_quantity_kg FROM butchery_output_allocations a JOIN butchery_order_outputs o ON o.id=a.output_id WHERE o.order_id=? AND o.item_id=?',[$order,$entier])===7.111,'Comptoir : 4 poulets entiers sur 9, soit 7,111 kg');
$poultry->validateTransform($order,k(),$user);
ok((int)DagrilIntegrationTest::scalar('SELECT quantity_pieces FROM butchery_raw_lots WHERE id=?',[$raw1['id']])===34&&DagrilIntegrationTest::scalar('SELECT status FROM butchery_raw_lots WHERE id=?',[$raw2['id']])==='depleted','Validation : lot de la boucherie épuisé, 34 carcasses restent au lot de la ferme');
$wholeLot=q('SELECT l.* FROM butchery_finished_lots l JOIN butchery_order_outputs o ON o.id=l.order_output_id WHERE o.order_id=? AND l.item_id=?',[$order,$entier])->fetch();
ok((int)$wholeLot['quantity_pieces']===9&&(int)DagrilIntegrationTest::scalar('SELECT quantity_pieces FROM butchery_counter_stocks WHERE finished_lot_id=?',[$wholeLot['id']])===4,'Poulets entiers : 9 pièces, dont 4 au comptoir');

// Counter prices by the piece or by the kilo, as set for each article.
no(fn()=>$poultry->posPrice(k(['item_id'=>$morceaux,'currency'=>'USD','price'=>'5','price_unit'=>'piece']),$user),'Vente à la pièce refusée pour un article au poids','uniquement au kilo');
$poultry->posPrice(k(['item_id'=>$entier,'currency'=>'USD','price'=>'7.50','price_unit'=>'piece']),$user);
$poultry->posPrice(k(['item_id'=>(string)$vif['item_id'],'currency'=>'USD','price'=>'6','price_unit'=>'piece']),$user);
$poultry->posPrice(k(['item_id'=>$morceaux,'currency'=>'USD','price'=>'5','price_unit'=>'kg']),$user);
$catalog=[];foreach($poultry->posDirectory()['posCatalog'] as $row)if((int)$row['counter_id']===$counter)$catalog[(int)$row['item_id']]=$row;
ok(($catalog[$entier]['unit']??'')==='piece'&&(int)$catalog[$entier]['quantity_pieces']===4&&(int)$catalog[(int)$vif['item_id']]['quantity_pieces']===5,'Caisse : poulets entiers et vivants vendus à la pièce, pièces disponibles');
$sale=['counter_id'=>(string)$counter,'currency'=>'USD','payment_method'=>'cash','received_amount'=>'40.00','customer_name'=>'Client volaille'];
no(fn()=>$poultry->posCheckout(k($sale+['lines'=>[['item_id'=>$entier,'quantity_kg'=>'2','unit_price'=>'7.50']]]),$user),'Article à la pièce sans nombre de pièces refusé','Comptez les pièces');
no(fn()=>$poultry->posCheckout(k($sale+['lines'=>[['item_id'=>$entier,'quantity_pieces'=>'5','unit_price'=>'7.50']]]),$user),'Plus de pièces qu’au comptoir refusé','insuffisant');
$ticket=$poultry->posCheckout(k($sale+['lines'=>[['item_id'=>$entier,'quantity_pieces'=>'2','unit_price'=>'7.50'],['item_id'=>(string)$vif['item_id'],'quantity_pieces'=>'2','unit_price'=>'6.00'],['item_id'=>$morceaux,'quantity_kg'=>'1.250','unit_price'=>'5.00']]]),$user);
$receipt=$poultry->posTicket($ticket);$lines=[];foreach($receipt['lines'] as $line)$lines[(int)$line['item_id']]=$line;
ok((float)$receipt['total']===33.25&&$lines[$entier]['price_unit']==='piece'&&(int)$lines[$entier]['quantity_pieces']===2&&(float)$lines[$entier]['quantity_kg']===3.555,'Ticket : 2 × 7,50 + 2 × 6,00 + 1,250 kg × 5,00 = 33,25 ; kilos des poulets entiers tracés');
ok((int)DagrilIntegrationTest::scalar('SELECT quantity_pieces FROM butchery_counter_stocks WHERE finished_lot_id=?',[$wholeLot['id']])===2&&(int)DagrilIntegrationTest::scalar('SELECT quantity_pieces FROM butchery_finished_lots WHERE id=?',[$vif['id']])===36,'Stocks : 2 poulets entiers au comptoir, 36 poulets vivants au total');
// Switching an article to the kilo: the cashier weighs and counts.
$poultry->posPrice(k(['item_id'=>$entier,'currency'=>'USD','price'=>'6.00','price_unit'=>'kg']),$user);
ok(DagrilIntegrationTest::scalar('SELECT unit FROM butchery_items WHERE id=?',[$entier])==='kg','Poulet entier désormais vendu au kilo');
no(fn()=>$poultry->posCheckout(k($sale+['lines'=>[['item_id'=>$entier,'quantity_kg'=>'1.900','unit_price'=>'6.00']]]),$user),'Article suivi en pièces vendu au kilo : pièces aussi comptées','Comptez les pièces');
$byKg=$poultry->posCheckout(k($sale+['lines'=>[['item_id'=>$entier,'quantity_kg'=>'1.900','quantity_pieces'=>'1','unit_price'=>'6.00']]]),$user);
ok((float)$poultry->posTicket($byKg)['total']===11.4&&(int)DagrilIntegrationTest::scalar('SELECT quantity_pieces FROM butchery_counter_stocks WHERE finished_lot_id=?',[$wholeLot['id']])===1,'Poulet entier pesé 1,900 kg × 6,00 = 11,40 ; une pièce sortie');
$poultry->posCancel($ticket,k(['reason'=>'Erreur de caisse','not_delivered'=>'1']),$user);
ok((int)DagrilIntegrationTest::scalar('SELECT quantity_pieces FROM butchery_counter_stocks WHERE finished_lot_id=?',[$wholeLot['id']])===3&&(int)DagrilIntegrationTest::scalar('SELECT quantity_pieces FROM butchery_finished_lots WHERE id=?',[$vif['id']])===38,'Annulation du ticket : pièces rétablies au comptoir et au lot');
$poultry->posPrice(k(['item_id'=>$entier,'currency'=>'USD','price'=>'7.50','price_unit'=>'piece']),$user);

// Weighing report and remaining shipment.
$report=(new TransitWeighingService($db))->report($bouch,['from'=>date('Y-m-01',strtotime('-1 day')),'to'=>date('Y-m-d'),'species'=>(string)$poulet]);
ok(count(array_filter($report['rows'],static fn($row)=>in_array((int)$row['id'],[$r1,$r2],true)))===2,'Rapport des écarts : réceptions de volailles incluses');
Auth::selectSite((string)$mut);
$poultry->cancelShipment($t2,k(['reason'=>'Camion indisponible']),$user);
ok((float)DagrilIntegrationTest::scalar('SELECT reserved_kg FROM livestock_weight_stocks WHERE id=?',[$stock])===0.0,'Envoi approuvé annulé : réservation libérée');
DagrilIntegrationTest::finish();
