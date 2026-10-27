<?php
/** Fish farming by pond parcel and farm-to-butchery double weighing, on a disposable database with migration 042. */
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/FishFlowService.php';
require_once __DIR__.'/../app/services/OperationalAttentionService.php';
require_once __DIR__.'/../app/models/Livestock.php';
$fish=new FishFlowService($db);$user=DagrilIntegrationTest::loginRole('administrateur');
$mut=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");$bouch=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='BOUCH'");Auth::selectSite((string)$mut);
function q($sql,$params=[]){global $db;$q=$db->prepare($sql);$q->execute($params);return $q;}
function k($patch=[]){return array_replace(['operation_key'=>bin2hex(random_bytes(16))],$patch);}
function ok($condition,$message){DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
function no($callback,$message,$expected){try{$callback();}catch(RuntimeException $e){ok(strpos($e->getMessage(),$expected)!==false,$message.' · « '.$e->getMessage().' »');return;}ok(false,$message);}
function lot($id){global $fish;foreach($fish->directory()['lots'] as $l)if((int)$l['id']===(int)$id)return $l;throw new RuntimeException('Lot absent');}
function ship($stock,$kg,$plate){global $fish,$user,$mut;Auth::selectSite((string)$mut);$t=$fish->shipment(k(['weight_stock_id'=>$stock,'quantity_kg'=>$kg,'truck_plate'=>$plate,'driver_name'=>'Chauffeur test']),$user);$fish->advanceShipment($t,'approve',$user);$fish->advanceShipment($t,'dispatch',$user);return $t;}
$tilapia=(int)DagrilIntegrationTest::scalar("SELECT id FROM livestock_species WHERE code='TILAPIA'");$porc=(int)DagrilIntegrationTest::scalar("SELECT id FROM livestock_species WHERE code='PORC'");
q("INSERT INTO livestock_facilities(site_id,code,name,facility_type,area_m2,status) VALUES(?,?,'Étang de validation','pond',1000,'active')",[$mut,'ET-'.strtoupper(bin2hex(random_bytes(3)))]);$pond=(int)$db->lastInsertId();
ok($fish->fishAvailable(),'Migration 042 détectée : suivi par étang et parcelle actif');

// Parcels: one pond holds one or several parcels within its surface.
$p1=$fish->createParcel(k(['facility_id'=>$pond,'name'=>'','area_m2'=>'600']),$user);
$p2=$fish->createParcel(k(['facility_id'=>$pond,'name'=>'Parcelle nord','area_m2'=>'400']),$user);
$r=q('SELECT code,name FROM livestock_pond_parcels WHERE id IN (?,?) ORDER BY id',[$p1,$p2])->fetchAll();
ok($r[0]['code']==='P1'&&$r[0]['name']==='Parcelle 1'&&$r[1]['code']==='P2','Codes P1, P2 attribués, nom par défaut');
no(fn()=>$fish->createParcel(k(['facility_id'=>$pond,'area_m2'=>'1']),$user),'Surface totale des parcelles limitée à celle de l’étang','dépasserait');

// Stocking: a free parcel receives a lot of fingerlings at a date.
$lot=$fish->stock(k(['parcel_id'=>$p1,'species_id'=>$tilapia,'heads'=>'1000','stocked_weight_kg'=>'5','stocked_on'=>date('Y-m-d',strtotime('-6 months'))]),$user);
$l=lot($lot);ok($l['status']==='active'&&(int)$l['initial_heads']===1000&&(int)$l['parcel_id']===$p1&&(float)$l['stocked_kg']===5.0,'Mise en charge : lot de 1 000 alevins dans P1');
ok((int)DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM livestock_head_movements WHERE batch_id=? AND movement_type='stocking' AND quantity_heads=1000",[$lot])===1,'Mouvement d’effectif de mise en charge');
no(fn()=>$fish->stock(k(['parcel_id'=>$p1,'species_id'=>$tilapia,'heads'=>'10','stocked_on'=>date('Y-m-d')]),$user),'Parcelle occupée refusée','déjà un lot en cours');
no(fn()=>$fish->stock(k(['parcel_id'=>$p2,'species_id'=>$porc,'heads'=>'10','stocked_on'=>date('Y-m-d')]),$user),'Espèce non piscicole refusée','espèce de poisson');
no(fn()=>$fish->stock(k(['parcel_id'=>$p2,'species_id'=>$tilapia,'heads'=>'10','stocked_on'=>date('Y-m-d',strtotime('+1 day'))]),$user),'Mise en charge future refusée','futur');
no(fn()=>$fish->updateParcel($p1,k(['name'=>'Parcelle 1','area_m2'=>'600','status'=>'inactive']),$user),'Parcelle en élevage non désactivable','lot en cours');
no(fn()=>(new Livestock())->createBatch(['site_id'=>$mut,'species_id'=>$tilapia,'facility_id'=>$pond,'purpose'=>'meat','started_at'=>date('Y-m-d'),'initial_heads'=>10,'initial_total_weight_kg'=>1],$user),'Parcours générique refusé pour les poissons','Élevage › Poissons');

// Harvests: survivors are counted and weighed at the farm.
$base=['batch_id'=>$lot,'caught_at'=>date('Y-m-d H:i'),'expiry_date'=>date('Y-m-d',strtotime('+3 days'))];
no(fn()=>$fish->harvest(k($base+['heads'=>'1001','weighing_mode'=>'net','net_kg'=>'10']),$user),'Plus de poissons que d’alevins refusé','restants');
no(fn()=>$fish->harvest(k(array_replace($base,['heads'=>'10','weighing_mode'=>'net','net_kg'=>'10','expiry_date'=>date('Y-m-d',strtotime('-1 day'))])),$user),'DLC dépassée refusée','DLC');
no(fn()=>$fish->harvest(k(array_replace($base,['heads'=>'10','weighing_mode'=>'net','net_kg'=>'10','caught_at'=>date('Y-m-d H:i',strtotime('-7 months'))])),$user),'Pêche avant la mise en charge refusée','précéder');
$h1=$fish->harvest(k($base+['heads'=>'300','weighing_mode'=>'gross','gross_kg'=>'160','tare_kg'=>'10','tare_note'=>'Glace et caisses']),$user);
$c=q('SELECT c.net_weight_kg,c.tare_note,fc.tare_note fc_tare,fc.yield_kg_m2,i.code FROM livestock_conversions c JOIN fish_catches fc ON fc.conversion_id=c.id JOIN butchery_items i ON i.id=fc.item_id WHERE c.id=?',[$h1])->fetch();
ok((float)$c['net_weight_kg']===150.0&&$c['tare_note']==='Glace et caisses'&&$c['fc_tare']==='Glace et caisses'&&$c['code']==='MP-POISSON'&&(float)$c['yield_kg_m2']===0.25,'Pêche pesée brut − tare, article Poisson frais, rendement sur la surface de la parcelle');
ok(lot($lot)['remaining_heads']===700,'Poissons en attente de validation déduits des restants');
no(fn()=>$fish->harvest(k($base+['heads'=>'10','weighing_mode'=>'net','net_kg'=>'5','final_harvest'=>'1']),$user),'Pêche finale avec pêche en attente refusée','en attente');
$s1=$fish->validateHarvest($h1,k(),$user);
ok((float)DagrilIntegrationTest::scalar('SELECT quantity_kg FROM livestock_weight_stocks WHERE id=?',[$s1])===150.0&&(int)lot($lot)['current_heads']===700,'Validation : 300 poissons sortis du lot, 150 kg en stock pesé');
ok(DagrilIntegrationTest::scalar('SELECT status FROM fish_catches WHERE conversion_id=?',[$h1])==='validated','Pêche validée');
no(fn()=>$fish->validateHarvest($h1,k(),$user),'Double validation refusée','déjà validée');
$hc=$fish->harvest(k($base+['heads'=>'20','weighing_mode'=>'net','net_kg'=>'9']),$user);
ok((new OperationalAttentionService($db))->countFor('pending_fish_harvests')>=1,'Pêche en attente signalée dans le menu');
$fish->cancelHarvest($hc,k(['reason'=>'Saisie en double']),$user);
ok(DagrilIntegrationTest::scalar('SELECT status FROM fish_catches WHERE conversion_id=?',[$hc])==='cancelled'&&lot($lot)['remaining_heads']===700,'Pêche annulée, poissons restants rétablis');
$h2=$fish->harvest(k($base+['heads'=>'550','weighing_mode'=>'net','net_kg'=>'280','final_harvest'=>'1']),$user);
no(fn()=>$fish->harvest(k($base+['heads'=>'10','weighing_mode'=>'net','net_kg'=>'5']),$user),'Nouvelle pêche refusée pendant une pêche finale en attente','pêche finale');
$s2=$fish->validateHarvest($h2,k(),$user);
$l=lot($lot);
ok($l['status']==='completed'&&(int)$l['current_heads']===0&&(int)$l['harvested_heads']===850&&(int)$l['dead_heads']===150,'Pêche finale : lot clôturé, 150 poissons non pêchés comptés comme morts');
ok((float)$l['survival_pct']===85.0&&(float)$l['harvested_kg']===430.0&&(float)$l['yield_kg_m2']===0.717,'Bilan : survie 85 %, 430 kg, 0,717 kg/m²');
$again=$fish->stock(k(['parcel_id'=>$p1,'species_id'=>$tilapia,'heads'=>'2000','stocked_on'=>date('Y-m-d')]),$user);
ok($again!==$lot&&lot($again)['status']==='active','Parcelle libérée : nouvelle mise en charge possible');

// Shipment from the farm, then weighing at the butchery.
$t1=ship($s1,'150','POIS-T1');
ok(strpos((string)DagrilIntegrationTest::scalar('SELECT transfer_number FROM livestock_transfers WHERE id=?',[$t1]),'BTR-POIS-')===0&&(float)DagrilIntegrationTest::scalar('SELECT quantity_kg FROM livestock_weight_stocks WHERE id=?',[$s1])===0.0,'Envoi de poisson : BTR-POIS, stock ferme diminué à l’expédition');
ok(($fish->shipmentBtr($t1)['flow']??'')==='fish','Bon de transfert identifié comme poisson');
Auth::selectSite((string)$bouch);
q('DELETE FROM livestock_transit_tolerances WHERE species_id=?',[$tilapia]);
$incoming=array_values(array_filter($fish->butcheryDirectory()['incoming'],static fn($t)=>(int)$t['id']===$t1));
ok(count($incoming)===1&&$incoming[0]['flow']==='fish'&&$incoming[0]['species_name']!==''&&$incoming[0]['tolerance_pct']===null,'Envoi de poisson attendu à la réception, sans tolérance paramétrée');
no(fn()=>$fish->arrival(k(['transfer_id'=>$t1,'weighing_mode'=>'net','net_kg'=>'149.5']),$user),'Sans tolérance, tout écart doit être motivé','sans tolérance paramétrée');
$weighing=new TransitWeighingService($db);
no(fn()=>$weighing->saveTolerance(['species_id'=>(string)$tilapia,'tolerance_pct'=>'60','approver_role_id'=>''],$user,$bouch),'Tolérance supérieure à 50 % refusée','de 0 à 50');
$weighing->saveTolerance(['species_id'=>(string)$tilapia,'tolerance_pct'=>'3','approver_role_id'=>''],$user,$bouch);
ok($weighing->tolerance($tilapia)['pct']===3.0&&(int)DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM activity_logs WHERE action='transit_tolerance_update' AND entity_id=?",[$tilapia])>=1,'Tolérance paramétrée et auditée');
$r1=$fish->arrival(k(['transfer_id'=>$t1,'weighing_mode'=>'gross','gross_kg'=>'158','tare_kg'=>'12','tare_note'=>'Glace']),$user);
$r=q('SELECT * FROM butchery_receipts WHERE id=?',[$r1])->fetch();
ok((float)$r['sent_weight_kg']===150.0&&(float)$r['received_weight_kg']===146.0&&(float)$r['received_gross_kg']===158.0&&(float)$r['received_tare_kg']===12.0&&$r['tare_note']==='Glace'&&(float)$r['weight_gap_kg']===4.0&&(float)$r['tolerance_pct']===3.0,'Pesée d’arrivée : 146 kg nets, écart de 4 kg (2,67 %) dans la tolérance');
ok(strpos($r['receipt_number'],'REC-POIS-')===0,'Réception numérotée pour le poisson');
$fish->receive(k(['receipt_id'=>$r1,'visual_conform'=>'1','documents_conform'=>'1','temperature_c'=>'2']),$user);
$raw=q('SELECT l.quantity_kg,i.code FROM butchery_raw_lots l JOIN butchery_items i ON i.id=l.item_id WHERE l.receipt_id=?',[$r1])->fetch();
ok((float)$raw['quantity_kg']===146.0&&$raw['code']==='MP-POISSON','Chambre froide : le lot part du poids reçu (146 kg), pas du poids envoyé');
ok(DagrilIntegrationTest::scalar('SELECT gap_approved_by FROM butchery_receipts WHERE id=?',[$r1])===null,'Écart dans la tolérance : aucune validation spécifique');

// Gap beyond tolerance: reason at weighing, explicit approval at control.
$t2=ship($s2,'200','POIS-T2');Auth::selectSite((string)$bouch);
no(fn()=>$fish->arrival(k(['transfer_id'=>$t2,'weighing_mode'=>'net','net_kg'=>'190']),$user),'Écart hors tolérance sans motif refusé','précisez le motif');
$r2=$fish->arrival(k(['transfer_id'=>$t2,'weighing_mode'=>'net','net_kg'=>'190','gap_reason'=>'Égouttage et glace fondue']),$user);
$pending=array_values(array_filter($fish->butcheryDirectory()['pending'],static fn($p)=>(int)$p['id']===$r2))[0];
ok($pending['gap_exceeded']&&$pending['can_approve_gap'],'Réception en attente : écart hors tolérance, approbation possible pour l’administrateur');
no(fn()=>$fish->receive(k(['receipt_id'=>$r2,'visual_conform'=>'1','documents_conform'=>'1']),$user),'Entrée au froid refusée sans validation de l’écart','Validez l’écart');
$role=(int)DagrilIntegrationTest::scalar("SELECT id FROM roles WHERE slug NOT IN ('administrateur','administrateur-systeme') AND status='active' AND deleted_at IS NULL ORDER BY id LIMIT 1");
ok(!$weighing->canApprove(0,$role,$bouch)&&$weighing->canApprove(0,null,$bouch)&&$weighing->canApprove((int)$user['id'],$role,$bouch),'Rôle de validation : exigé s’il est paramétré, administrateurs toujours autorisés');
$fish->receive(k(['receipt_id'=>$r2,'visual_conform'=>'1','documents_conform'=>'1','gap_approved'=>'1']),$user);
$r=q('SELECT r.gap_approved_by,l.quantity_kg FROM butchery_receipts r JOIN butchery_raw_lots l ON l.receipt_id=r.id WHERE r.id=?',[$r2])->fetch();
ok((int)$r['gap_approved_by']===(int)$user['id']&&(float)$r['quantity_kg']===190.0,'Écart de 5 % validé et tracé, 190 kg entrés au froid');
$t3=ship($s2,'50','POIS-T3');Auth::selectSite((string)$bouch);
no(fn()=>$fish->arrival(k(['transfer_id'=>$t3,'weighing_mode'=>'net','net_kg'=>'20','gap_reason'=>'Erreur ?']),$user),'Écart de plus de 50 % refusé : pesée à vérifier','vérifiez la pesée');

// Report of transit gaps.
$report=$weighing->report($bouch,['from'=>date('Y-m-01',strtotime('-1 day')),'to'=>date('Y-m-d'),'species'=>(string)$tilapia]);
$mine=array_values(array_filter($report['rows'],static fn($row)=>in_array((int)$row['id'],[$r1,$r2],true)));
ok(count($mine)===2&&$report['totals']['exceeded']>=1&&$report['totals']['weighed']>=2,'Rapport : réceptions pesées et dépassements comptés');
ok(count($weighing->report($bouch,['from'=>date('Y-m-01',strtotime('-1 day')),'to'=>date('Y-m-d'),'status'=>'exceeded'])['rows'])>=1,'Rapport filtré sur les dépassements');
ok(substr($weighing->spreadsheet($report),0,2)==='PK','Export Excel du rapport');
// The receiving butchery reads the transfer note without any livestock permission.
$db->exec(file_get_contents(__DIR__.'/../database/038_add_butchery_manager_role.sql'));
q("INSERT INTO users(role_id,name,email,password,status) VALUES(NULL,'Boucher test',?,'unused','active')",['boucher-'.bin2hex(random_bytes(4)).'@example.test']);$butcherId=(int)$db->lastInsertId();
q("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')",[$butcherId,$bouch]);
q("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) SELECT ?,id,?,'direct',NOW(),'approved' FROM roles WHERE slug='responsable-boucherie'",[$butcherId,$bouch]);
Auth::login(q('SELECT * FROM users WHERE id=?',[$butcherId])->fetch());Auth::selectSite((string)$bouch);
ok(!Auth::can('livestock','read',$mut)&&Auth::can('butchery','read',$bouch),'Profil boucherie sans droit sur l’élevage');
ok(($fish->shipmentBtr($t1,'butchery')['transfer_number']??'')!==''&&($fish->shipmentBtr($t1,'butchery')['flow']??'')==='fish','BTR consultable par la boucherie destinataire');
ok($fish->shipmentBtr($t1,'farm')===null,'BTR côté ferme hors de son périmètre');
DagrilIntegrationTest::finish();
