<?php
require_once dirname(__DIR__).'/app/helpers/functions.php';
require_once dirname(__DIR__).'/app/core/Database.php';
require_once dirname(__DIR__).'/app/core/Model.php';
require_once dirname(__DIR__).'/app/core/Auth.php';
require_once dirname(__DIR__).'/app/services/TransferService.php';
ob_start(); Auth::start(); $db=Database::getInstance()->connection(); $transferId=0; $productId=0; $formatId=0; $stocks=[];
function tq($db,$sql,$p=[]){$s=$db->prepare($sql);$s->execute($p);return $s;}
function tv($db,$sql,$p=[]){return tq($db,$sql,$p)->fetchColumn();}
function to($db,$sql,$p=[]){return tq($db,$sql,$p)->fetch();}
function ok($v,$m){if(!$v){throw new RuntimeException('ECHEC: '.$m);}echo "[OK] {$m}\n";}
function blocked($f,$m){$v=false;try{$f();}catch(Exception $e){$v=true;}ok($v,$m);}
try {
 $creator=to($db,"SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrateur' AND u.status='active' LIMIT 1");
 $validator=to($db,"SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='direction' AND u.status='active' LIMIT 1");
 $source=to($db,"SELECT id FROM sites WHERE code='MINO'"); $dest=to($db,"SELECT id FROM sites WHERE code='DEP-DEV'");
 Auth::login($creator); Auth::selectSite((string)$source['id']); $suffix=random_int(10000,99999);
 tq($db,"INSERT INTO products(name,code,category,unit,status) VALUES('QA Transfer',:code,'finished_product','kg','active')",['code'=>'TST-TR-'.$suffix]); $productId=(int)$db->lastInsertId();
 tq($db,"INSERT INTO bag_formats(name,weight_kg,status) VALUES(:name,25,'active')",['name'=>'QA Bag '.$suffix]); $formatId=(int)$db->lastInsertId();
 tq($db,"INSERT INTO finished_stocks(site_id,product_id,bag_format_id,quantity_bags,total_weight_kg,status) VALUES(:site,:product,:format,20,500,'active')",['site'=>$source['id'],'product'=>$productId,'format'=>$formatId]); $sourceStock=(int)$db->lastInsertId(); $stocks[]=$sourceStock;
 $svc=new TransferService($db);
 $transferId=$svc->create(['transfer_type'=>'inter_site','source_site_id'=>$source['id'],'destination_site_id'=>$dest['id'],'items'=>[['finished_stock_id'=>$sourceStock,'quantity_bags'=>10]]],$creator);
 $item=to($db,'SELECT * FROM stock_transfer_items WHERE transfer_id=?',[$transferId]); ok((int)tv($db,'SELECT quantity_bags FROM finished_stocks WHERE id=?',[$sourceStock])===20,'Création sans effet stock');
 $svc->submit($transferId,$creator); blocked(function()use($svc,$transferId,$creator){$svc->approve($transferId,$creator);},'Créateur interdit de validation');
 $svc->approve($transferId,$validator); ok((int)tv($db,'SELECT reserved_bags FROM finished_stocks WHERE id=?',[$sourceStock])===10,'Validation et réservation atomiques');
 blocked(function()use($svc,$transferId,$item,$creator){$svc->ship($transferId,[$item['id']=>11],$creator);},'Surexpédition interdite');
 $shipment=$svc->ship($transferId,[$item['id']=>6],$creator,'QA-TRUCK'); $shipmentItem=to($db,'SELECT * FROM transfer_shipment_items WHERE shipment_id=?',[$shipment]);
 ok((int)tv($db,'SELECT quantity_bags FROM finished_stocks WHERE id=?',[$sourceStock])===14,'Expédition baisse le physique'); ok((int)tv($db,'SELECT reserved_bags FROM finished_stocks WHERE id=?',[$sourceStock])===4,'Expédition consomme le réservé');
 Auth::selectSite((string)$dest['id']); $key1=hash('sha256','r1-'.$suffix); $svc->receive($shipment,$key1,[$shipmentItem['id']=>['accepted_bags'=>4,'rejected_bags'=>0]],$creator);
 $destStock=to($db,'SELECT * FROM finished_stocks WHERE site_id=? AND product_id=?',[$dest['id'],$productId]); $stocks[]=$destStock['id']; ok((int)$destStock['quantity_bags']===4,'Réception partielle acceptée');
 $key2=hash('sha256','r2-'.$suffix); $receipt=$svc->receive($shipment,$key2,[$shipmentItem['id']=>['accepted_bags'=>1,'rejected_bags'=>1,'quality_notes'=>'Endommagé']],$creator);
 ok((int)tv($db,'SELECT quantity_bags FROM finished_stocks WHERE id=?',[$destStock['id']])===5,'Acceptation partielle crédite seulement le conforme'); ok((int)tv($db,'SELECT shipped_bags-received_bags FROM stock_transfer_items WHERE id=?',[$item['id']])===0,'Transit soldé');
 ok((int)tv($db,'SELECT COUNT(*) FROM transfer_non_conformities WHERE transfer_id=?',[$transferId])===2,'Non-conformités enregistrées'); ok((int)tv($db,'SELECT COUNT(*) FROM transfer_returns WHERE transfer_id=?',[$transferId])===1,'Retour traçable planifié');
 $before=(int)tv($db,'SELECT quantity_bags FROM finished_stocks WHERE id=?',[$destStock['id']]); $same=$svc->receive($shipment,$key2,[$shipmentItem['id']=>['accepted_bags'=>1,'rejected_bags'=>0]],$creator); ok((int)$same===(int)$receipt&&(int)tv($db,'SELECT quantity_bags FROM finished_stocks WHERE id=?',[$destStock['id']])===$before,'Réception idempotente');
 blocked(function()use($svc,$shipment,$shipmentItem,$creator,$suffix){$svc->receive($shipment,hash('sha256','extra-'.$suffix),[$shipmentItem['id']=>['accepted_bags'=>1,'rejected_bags'=>0]],$creator);},'Double réception interdite');
 $returnId=(int)tv($db,'SELECT id FROM transfer_returns WHERE transfer_id=?',[$transferId]); $svc->dispatchReturn($returnId,$creator); Auth::selectSite((string)$source['id']); $svc->receiveReturn($returnId,$creator); ok((int)tv($db,'SELECT quantity_bags FROM finished_stocks WHERE id=?',[$sourceStock])===15,'Retour réintégré par mouvement');
 $svc->close($transferId,$creator); ok((int)tv($db,'SELECT reserved_bags FROM finished_stocks WHERE id=?',[$sourceStock])===0,'Reliquat réservé libéré'); ok((int)tv($db,'SELECT quantity_bags FROM finished_stocks WHERE id=?',[$sourceStock])+(int)tv($db,'SELECT quantity_bags FROM finished_stocks WHERE id=?',[$destStock['id']])===20,'Conservation totale');
 ok((int)tv($db,'SELECT COUNT(*) FROM stock_transfer_movements WHERE transfer_id=?',[$transferId])>=5,'Mouvements journalisés');
 foreach(['BTR','BS','BR','BRET','NC'] as $code){ok((int)tv($db,"SELECT COUNT(*) FROM documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE dt.code=? AND d.created_at>=DATE_SUB(NOW(),INTERVAL 5 MINUTE)",[$code])>0,'Document '.$code);}
 echo "Tous les tests de transferts inter-sites sont conformes.\n";
} finally {
 if($transferId){
  $docs=tq($db,"SELECT d.id FROM documents d WHERE (d.entity_type='stock_transfers' AND d.entity_id=?) OR (d.entity_type='transfer_shipments' AND d.entity_id IN(SELECT id FROM transfer_shipments WHERE transfer_id=?)) OR (d.entity_type='transfer_receipts' AND d.entity_id IN(SELECT id FROM transfer_receipts WHERE transfer_id=?)) OR (d.entity_type='transfer_returns' AND d.entity_id IN(SELECT id FROM transfer_returns WHERE transfer_id=?)) OR (d.entity_type='transfer_non_conformities' AND d.entity_id IN(SELECT id FROM transfer_non_conformities WHERE transfer_id=?))",array_fill(0,5,$transferId))->fetchAll(PDO::FETCH_COLUMN);
  if($docs){$p=implode(',',array_fill(0,count($docs),'?'));$wis=tq($db,"SELECT id FROM workflow_instances WHERE document_id IN({$p})",$docs)->fetchAll(PDO::FETCH_COLUMN);if($wis){$q=implode(',',array_fill(0,count($wis),'?'));foreach(['workflow_notifications','workflow_level_approvals','workflow_transitions'] as $t){tq($db,"DELETE FROM {$t} WHERE workflow_instance_id IN({$q})",$wis);}tq($db,"DELETE FROM workflow_instances WHERE id IN({$q})",$wis);}tq($db,"DELETE FROM document_status_history WHERE document_id IN({$p})",$docs);tq($db,"DELETE FROM documents WHERE id IN({$p})",$docs);}
  foreach(['stock_transfer_movements','stock_transfer_history'] as $t){tq($db,"DELETE FROM {$t} WHERE transfer_id=?",[$transferId]);}
  tq($db,'DELETE FROM transfer_return_items WHERE return_id IN(SELECT id FROM transfer_returns WHERE transfer_id=?)',[$transferId]); tq($db,'DELETE FROM transfer_returns WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM transfer_non_conformities WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM transfer_receipt_items WHERE receipt_id IN(SELECT id FROM transfer_receipts WHERE transfer_id=?)',[$transferId]); tq($db,'DELETE FROM transfer_receipts WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM transfer_shipment_items WHERE shipment_id IN(SELECT id FROM transfer_shipments WHERE transfer_id=?)',[$transferId]); tq($db,'DELETE FROM transfer_shipments WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM stock_transfer_reservations WHERE transfer_item_id IN(SELECT id FROM stock_transfer_items WHERE transfer_id=?)',[$transferId]); tq($db,'DELETE FROM stock_transfer_items WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM stock_transfers WHERE id=?',[$transferId]);
 }
 foreach(array_unique($stocks) as $id){tq($db,'DELETE FROM finished_stocks WHERE id=?',[$id]);} if($formatId){tq($db,'DELETE FROM bag_formats WHERE id=?',[$formatId]);} if($productId){tq($db,'DELETE FROM products WHERE id=?',[$productId]);} Auth::logout(); $out=ob_get_clean(); echo $out;
}
