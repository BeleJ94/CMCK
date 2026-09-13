<?php
/** php tests/FarmTransportBridgeIntegrationTest.php
 * Uses only connection-local temporary tables, empty schema copies and synthetic data.
 * Auth is simulated to test farm-only and receiving-site access independently.
 */
require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Model.php';
class Auth
{
    public static $site = 1;
    public static $allowed = [1];
    public static function canSelfValidate($userId) { return false; }
    public static function requirePermission($component,$action,$site=null) { self::requireSiteAccess($site); }
    public static function currentSiteId() { return self::$site; }
    public static function requireCurrentSite() { self::requireSiteAccess(self::$site); return self::$site; }
    public static function requireSiteAccess($id) { if (!in_array((int)$id, self::$allowed, true)) { throw new RuntimeException('Site interdit.'); } }
    public static function siteClause($column, array &$params, $parameter='scope_site_id') { $params[$parameter]=self::$site; return ' AND '.$column.'=:'.$parameter; }
}
require_once dirname(__DIR__) . '/app/models/Agriculture.php';
require_once dirname(__DIR__) . '/app/models/WeighbridgeTransport.php';
require_once dirname(__DIR__) . '/app/models/Weighing.php';
$db = Database::getInstance()->connection();
// No source records are copied. Temporary tables disappear on disconnect.
foreach (['sites','products','suppliers','users','agricultural_stocks','agricultural_transports','agricultural_stock_movements','weighbridge_transports','trucks','activity_logs','documents','document_types','weighings','silos','silo_movements','document_numbering_rules','document_number_counters','document_status_history','workflow_definitions','workflow_instances','workflow_transitions'] as $table) {
    $definition = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $definition = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $definition);
    $definition = preg_replace('/^\s*CONSTRAINT .*FOREIGN KEY.*\n/m', '', $definition);
    $definition = preg_replace('/,\n\)/', "\n)", $definition);
    $db->exec($definition);
}
// Apply the migration to the connection-local schema when testing an older database.
$indexes=$db->query("SHOW INDEX FROM agricultural_transports WHERE Key_name='uq_ag_transport_stock'")->fetchAll();
if($indexes)$db->exec(file_get_contents(dirname(__DIR__).'/database/031_allow_partial_farm_transports.sql'));
if(!$db->query("SHOW COLUMNS FROM weighings LIKE 'exit_draft'")->fetch())$db->exec(file_get_contents(dirname(__DIR__).'/database/032_add_weighing_exit_drafts.sql'));
$db->exec("INSERT INTO sites(id,site_type_id,code,name,status) VALUES(1,1,'FARM-MUT','Ferme test','active'),(2,1,'SILO','Réception test','active'),(3,1,'MINO','Minoterie test','active'),(4,1,'FARM-DIK','Autre ferme','active')");
$db->exec("INSERT INTO products(id,name,code,category,status) VALUES(1,'Maïs test','MAIS-TEST','raw_material','active')");
$db->exec("INSERT INTO document_types(id,code,display_code,name) VALUES(1,'BT','BT','Bon de transport test')");
$db->exec("INSERT INTO silos(id,site_id,name,code,product_id,capacity_kg,current_stock_kg) VALUES(1,2,'Silo test','S-TEST',1,50000,0)");
function bridge_scalar($sql,$params=[]) { global $db; $q=$db->prepare($sql);$q->execute($params);return $q->fetchColumn(); }
function bridge_check($ok,$message) { global $checks; if(!$ok)throw new RuntimeException('ÉCHEC : '.$message);$checks++;echo 'OK - '.$message.PHP_EOL; }
function bridge_reject($callback,$message) { $rejected=false;try{$callback();}catch(Exception $e){$rejected=true;}bridge_check($rejected,$message); }
function bridge_fixture($id,$destination=2) {
    global $db;
    $db->prepare("INSERT INTO agricultural_stocks(id,site_id,harvest_id,product_id,variety_id,physical_quantity_kg,reserved_quantity_kg,status) VALUES(?,1,?,1,1,10000,4000,'reserved')")->execute([$id,$id]);
    $db->prepare("INSERT INTO agricultural_transports(id,stock_id,source_site_id,destination_site_id,transport_number,quantity_kg,status,truck_plate,driver_name,route_description,created_by,approved_by) VALUES(?,?,1,?, ?,4000,'approved',?,'Chauffeur test','Ferme - réception',1,2)")->execute([$id,$id,$destination,'BT-AG-TEST-'.$id,'TRUCK-TEST-'.$id]);
    $db->prepare("INSERT INTO documents(document_type_id,document_number,site_id,period_key,entity_type,entity_id,status,document_date,created_by) VALUES(1,?,1,'2026','agricultural_transports',?,'approved',NOW(),1)")->execute(['BT/FARM/2026/'.$id,$id]);
}
$checks=0;$ag=new Agriculture();$actor=['id'=>2];
bridge_fixture(1);
bridge_check(count((new Weighing())->availableTransports())===0,'aucun BT au pont-bascule avant expédition');
$bridge=$ag->dispatchTransport(1,$actor);
bridge_check((int)bridge_scalar('SELECT agricultural_transport_id FROM weighbridge_transports WHERE id=?',[$bridge])===1,'liaison agricole créée automatiquement');
bridge_check((int)bridge_scalar('SELECT site_id FROM weighbridge_transports WHERE id=?',[$bridge])===2,'réception rattachée au site SILO');
bridge_check((int)bridge_scalar('SELECT origin_site_id FROM weighbridge_transports WHERE id=?',[$bridge])===1,'ferme d’origine conservée');
bridge_check(bridge_scalar('SELECT transport_reference FROM weighbridge_transports WHERE id=?',[$bridge])==='BT/FARM/2026/1','numéro officiel conservé');
bridge_check((int)bridge_scalar("SELECT COUNT(*) FROM documents WHERE entity_type='weighbridge_transports'")===0,'pas de deuxième document BT');
bridge_check((float)bridge_scalar('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=1')===6000.0&&(float)bridge_scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=1')===0.0,'sortie et réservation comptabilisées une fois');
bridge_check((int)bridge_scalar("SELECT COUNT(*) FROM trucks WHERE plate_number='TRUCK-TEST-1' AND driver_name='Chauffeur test'")===1,'camion et chauffeur repris automatiquement');
bridge_check(count((new Weighing())->availableTransports())===0,'transport masqué depuis le contexte ferme');
bridge_reject(function()use($ag,$actor){$ag->dispatchTransport(1,$actor);},'double expédition refusée');
bridge_check((int)bridge_scalar('SELECT COUNT(*) FROM weighbridge_transports WHERE agricultural_transport_id=1')===1,'un seul transport malgré une seconde tentative');
bridge_check((int)bridge_scalar("SELECT COUNT(*) FROM activity_logs WHERE site_id=2 AND module='pont-bascule'")===1,'journalisation sur le site destinataire');
Auth::$site=2;Auth::$allowed=[2];
$available=(new Weighing())->availableTransports();
bridge_check(count($available)===1&&$available[0]['bt_number']==='BT/FARM/2026/1','BT proposé au pont-bascule à un utilisateur du seul site destinataire');
Auth::$site=null;
bridge_reject(function()use($bridge,$actor){(new Weighing())->createEntry(['transport_id'=>$bridge,'poids_brut'=>6000],$actor);},'entrée sans site précis refusée');
bridge_check((int)bridge_scalar('SELECT COUNT(*) FROM weighings')===0&&!$db->inTransaction(),'refus sans pesée partielle');
Auth::$site=2;
$weighing=(new Weighing())->createEntry(['transport_id'=>$bridge,'poids_brut'=>6000],$actor);
$draft=['poids_tare'=>2000,'silo_id'=>1,'humidity_percent'=>12,'impurities_percent'=>1,'weight_tolerance_percent'=>2,'max_humidity_percent'=>14,'max_impurities_percent'=>2,'decision'=>'accept','quality_notes'=>'Contrôle test'];
(new Weighing())->saveExitDraft($weighing,$draft,$actor);
$saved=json_decode(bridge_scalar('SELECT exit_draft FROM weighings WHERE id=?',[$weighing]),true);
bridge_check($saved===$draft,'données de sortie persistées sans perte');
bridge_check(bridge_scalar('SELECT status FROM weighings WHERE id=?',[$weighing])==='pending'&&(float)bridge_scalar('SELECT poids_net FROM weighings WHERE id=?',[$weighing])===0.0,'préparation sans validation ni poids net définitif');
bridge_check((float)bridge_scalar('SELECT current_stock_kg FROM silos WHERE id=1')===0.0,'préparation sans crédit du silo');
bridge_reject(function()use($weighing,$draft,$actor){(new Weighing())->validateExit($weighing,$draft,$actor);},'préparateur empêché de valider sa sortie');
$invalidDraft=$draft;$invalidDraft['poids_tare']=7000;
bridge_reject(function()use($weighing,$invalidDraft,$actor){(new Weighing())->saveExitDraft($weighing,$invalidDraft,$actor);},'tare incohérente refusée à la préparation');
bridge_check(json_decode(bridge_scalar('SELECT exit_draft FROM weighings WHERE id=?',[$weighing]),true)===$draft,'préparation précédente conservée après erreur');
bridge_check((float)bridge_scalar('SELECT shipped_quantity_kg FROM weighings WHERE id=?',[$weighing])===4000.0,'entrée reprend la quantité agricole sans ressaisie');
bridge_check((float)bridge_scalar('SELECT current_stock_kg FROM silos WHERE id=1')===0.0,'première pesée sans crédit prématuré du silo');
bridge_check(count((new Weighing())->availableTransports())===0,'BT arrivé retiré des entrées disponibles');
bridge_check((new Weighing())->findDetailed($weighing)['bt_number']==='BT/FARM/2026/1','ticket affiche la référence agricole');
bridge_reject(function()use($bridge,$actor){(new Weighing())->createEntry(['transport_id'=>$bridge,'poids_brut'=>6000],$actor);},'double entrée refusée');
Auth::$site=1;Auth::$allowed=[1];
bridge_fixture(2,3);$legacy=$ag->dispatchTransport(2,$actor);
bridge_check((int)bridge_scalar('SELECT site_id FROM weighbridge_transports WHERE id=?',[$legacy])===2,'ancien BT approuvé vers MINO routé au pont-bascule SILO');
bridge_fixture(3);$db->exec("UPDATE agricultural_transports SET status='draft' WHERE id=3");
bridge_reject(function()use($ag,$actor){$ag->dispatchTransport(3,$actor);},'brouillon non expédiable');
bridge_fixture(4);Auth::$site=4;Auth::$allowed=[4];
bridge_reject(function()use($ag,$actor){$ag->dispatchTransport(4,$actor);},'expédition depuis une ferme non autorisée refusée');
Auth::$site=1;Auth::$allowed=[1];
// Force failure AFTER truck creation: unique reference collision must roll everything back.
bridge_fixture(5);
$db->exec("INSERT INTO weighbridge_transports(site_id,origin_type,supplier_id,product_id,truck_id,transport_reference,shipped_quantity_kg,status) VALUES(2,'external_supplier',NULL,1,1,'BT/FARM/2026/5',1,'in_transit')");
bridge_reject(function()use($ag,$actor){$ag->dispatchTransport(5,$actor);},'échec de création du transport remonté');
bridge_check((int)bridge_scalar("SELECT COUNT(*) FROM trucks WHERE plate_number='TRUCK-TEST-5'")===0,'camion créé dans la transaction annulé');
bridge_check((float)bridge_scalar('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=5')===10000.0&&(float)bridge_scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=5')===4000.0,'échec conserve stock et réservation');
bridge_check(bridge_scalar('SELECT status FROM agricultural_transports WHERE id=5')==='approved'&&(int)bridge_scalar('SELECT COUNT(*) FROM weighbridge_transports WHERE agricultural_transport_id=5')===0,'échec conserve BT approuvé sans transport orphelin');
bridge_check(!$db->inTransaction(),'aucune transaction laissée ouverte');
// Exercise creation with the real document and workflow services, on fictional data.
$db->exec("INSERT INTO document_numbering_rules(document_type_id) VALUES(1)");
$db->exec("INSERT INTO workflow_definitions(name,document_type_id) VALUES('Validation test',1)");
foreach ([20,21,22,23] as $stockId) {
    $db->prepare("INSERT INTO agricultural_stocks(id,site_id,harvest_id,product_id,variety_id,physical_quantity_kg,reserved_quantity_kg,status) VALUES(?,1,?,1,1,10000,0,'available')")->execute([$stockId,$stockId]);
}
$data=['stock_id'=>20,'quantity_kg'=>100,'truck_plate'=>'TEST-ROUTE','driver_name'=>'Chauffeur test','route_description'=>'Destination falsifiée','destination_site_id'=>3];
$id=$ag->createTransport($data,$actor);
bridge_check(bridge_scalar('SELECT route_description FROM agricultural_transports WHERE id=?',[$id])==='Ferme test → Réception test','trajet calculé sans saisie et trajet falsifié ignoré');
bridge_check((int)bridge_scalar('SELECT destination_site_id FROM agricultural_transports WHERE id=?',[$id])===2,'destination imposée au site SILO');
bridge_check(bridge_scalar('SELECT status FROM agricultural_transports WHERE id=?',[$id])==='draft','création du BT en brouillon');
bridge_check((int)bridge_scalar("SELECT COUNT(*) FROM documents WHERE entity_type='agricultural_transports' AND entity_id=?",[$id])===1,'document officiel créé avec le trajet automatique');
$expectedReference=bridge_scalar("SELECT document_number FROM documents WHERE entity_type='agricultural_transports' AND entity_id=?",[$id]);
$message='';
try{$ag->createTransport($data,$actor);}catch(RuntimeException $e){$message=$e->getMessage();}
bridge_check(strpos($message,'Ce stock possède déjà le BT en brouillon '.$expectedReference)!==false,'second brouillon refusé avec référence lisible');
bridge_check((int)bridge_scalar('SELECT COUNT(*) FROM agricultural_transports WHERE stock_id=20')===1,'nouvelle tentative sans duplication du BT');
bridge_check((float)bridge_scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=20')===0.0&&!$db->inTransaction(),'refus du doublon sans réservation ni transaction ouverte');
$data['stock_id']=21;$data['route_notes']='  via accès nord  ';
$db->exec("UPDATE agricultural_stocks SET site_id=4 WHERE id=21");
Auth::$allowed=[4];Auth::$site=4;
$id=$ag->createTransport($data,$actor);
bridge_check(bridge_scalar('SELECT route_description FROM agricultural_transports WHERE id=?',[$id])==='Autre ferme → Réception test · via accès nord','origine issue du stock et précisions conservées');
Auth::$allowed=[1];Auth::$site=1;$data['stock_id']=22;
$data['route_notes']=str_repeat('é',256);
bridge_reject(function()use($ag,$data,$actor){$ag->createTransport($data,$actor);},'trajet trop long refusé');
bridge_check((int)bridge_scalar('SELECT COUNT(*) FROM agricultural_transports WHERE stock_id=22')===0&&!$db->inTransaction(),'refus sans BT partiel et transaction annulée');
$data['route_notes']=['invalide'];
bridge_reject(function()use($ag,$data,$actor){$ag->createTransport($data,$actor);},'précisions non textuelles refusées');
$data['route_notes']=str_repeat('é',255-mb_strlen('Ferme test → Réception test · ','UTF-8'));
$id=$ag->createTransport($data,$actor);
bridge_check(mb_strlen(bridge_scalar('SELECT route_description FROM agricultural_transports WHERE id=?',[$id]),'UTF-8')===255,'limite de 255 caractères Unicode acceptée');
// Reproduce the reported remainder: 1,000 kg shipped and 800 kg still on farm.
$db->exec("INSERT INTO agricultural_stocks(id,site_id,harvest_id,product_id,variety_id,physical_quantity_kg,reserved_quantity_kg,status) VALUES(30,1,30,1,1,800,0,'available')");
$db->exec("INSERT INTO agricultural_transports(stock_id,source_site_id,destination_site_id,transport_number,quantity_kg,status,truck_plate,driver_name,created_by) VALUES(30,1,2,'BT-PARTIAL-OLD',1000,'in_transit','PARTIAL-OLD','Test',1)");
$partial=['stock_id'=>30,'quantity_kg'=>801,'truck_plate'=>'PARTIAL-NEW','driver_name'=>'Test'];
bridge_reject(function()use($ag,$partial,$actor){$ag->createTransport($partial,$actor);},'expédition supérieure aux 800 kg restants refusée');
$partial['quantity_kg']=500;
$partialId=$ag->createTransport($partial,$actor);
bridge_check((int)bridge_scalar('SELECT COUNT(*) FROM agricultural_transports WHERE stock_id=30')===2,'nouveau BT possible après une expédition partielle');
$ag->approveTransport($partialId,['id'=>3]);
bridge_check((float)bridge_scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=30')===500.0,'500 kg réservés sur les 800 kg restants');
$partial['quantity_kg']=301;
bridge_reject(function()use($ag,$partial,$actor){$ag->createTransport($partial,$actor);},'réservation existante déduite du disponible');
$partial['quantity_kg']=300;$lastId=$ag->createTransport($partial,$actor);
$ag->approveTransport($lastId,['id'=>3]);
$ag->dispatchTransport($partialId,$actor);
bridge_check(bridge_scalar('SELECT status FROM agricultural_stocks WHERE id=30')==='reserved'&&(float)bridge_scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=30')===300.0,'premier départ conserve la réservation du second camion');
$ag->dispatchTransport($lastId,$actor);
bridge_check((float)bridge_scalar('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=30')===0.0&&(float)bridge_scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=30')===0.0,'deux départs soldent exactement les 800 kg');
bridge_reject(function()use($ag,$partial,$actor){$ag->createTransport($partial,$actor);},'stock épuisé non transportable');
// Manual reception-side creation is reserved for external suppliers.
Auth::$site=2;Auth::$allowed=[2];
$db->exec("INSERT INTO suppliers(id,name,status) VALUES(10,'Fournisseur test','active')");
$manual=['origin_type'=>'internal_farm','origin_site_id'=>1,'supplier_id'=>10,'product_id'=>1,'truck_plate_number'=>'SUPPLIER-TEST','driver_name'=>'Test','driver_phone'=>'','shipped_quantity_kg'=>100,'route_description'=>'Fournisseur → Silos','toll_amount'=>0,'toll_details'=>''];
$countBefore=(int)bridge_scalar('SELECT COUNT(*) FROM weighbridge_transports');
bridge_reject(function()use($manual,$actor){(new WeighbridgeTransport())->createTransport($manual,$actor);},'création manuelle agricole refusée au pont-bascule');
bridge_check((int)bridge_scalar('SELECT COUNT(*) FROM weighbridge_transports')===$countBefore&&!$db->inTransaction(),'refus agricole sans transport ni transaction ouverte');
$manual['origin_type']='external_supplier';$manual['supplier_id']=999;
bridge_reject(function()use($manual,$actor){(new WeighbridgeTransport())->createTransport($manual,$actor);},'fournisseur inexistant refusé');
$manual['supplier_id']=10;
$supplierTransport=(new WeighbridgeTransport())->createTransport($manual,$actor);
bridge_check(bridge_scalar('SELECT origin_type FROM weighbridge_transports WHERE id=?',[$supplierTransport])==='external_supplier','transport fournisseur créé');
bridge_check(bridge_scalar('SELECT agricultural_transport_id FROM weighbridge_transports WHERE id=?',[$supplierTransport])===null,'transport fournisseur sans faux lien agricole');
bridge_check((int)bridge_scalar("SELECT COUNT(*) FROM documents WHERE entity_type='weighbridge_transports' AND entity_id=?",[$supplierTransport])===1,'BT fournisseur officiel créé');
$availableIds=array_column((new Weighing())->availableTransports(),'id');
bridge_check(in_array($supplierTransport,array_map('intval',$availableIds),true),'BT fournisseur proposé à la réception');
Auth::$site=1;Auth::$allowed=[1];
$data['stock_id']=23;$data['route_notes']='';Auth::$allowed=[4];
bridge_reject(function()use($ag,$data,$actor){$ag->createTransport($data,$actor);},'création depuis un stock non autorisé refusée');
Auth::$allowed=[1];$db->exec("UPDATE sites SET status='inactive' WHERE code='SILO'");
bridge_reject(function()use($ag,$data,$actor){$ag->createTransport($data,$actor);},'absence de site SILO actif signalée');
bridge_check(!$db->inTransaction(),'aucune transaction ouverte après les contrôles du trajet');
echo $checks." vérifications réussies. Tables temporaires supprimées à la déconnexion.\n";
