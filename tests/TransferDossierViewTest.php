<?php
require __DIR__.'/../app/helpers/functions.php';
class Auth {public static $writable=true;public static function start(){} public static function canAccessSite($id){return true;} public static function can($component,$action){return self::$writable;}}
$success=$error=null;
$transfer=['id'=>1,'transfer_number'=>'TR-TEST-1','transfer_type'=>'inter_site','status'=>'partially_shipped','source_site_id'=>1,'destination_site_id'=>2,'source_code'=>'SRC','destination_code'=>'DST','source_name'=>'Source','destination_name'=>'Destination','creator_name'=>'Agent test','approver_name'=>'Responsable','requested_at'=>'2026-09-20 10:00:00','submitted_at'=>null,'approved_at'=>null,'closed_at'=>null,'notes'=>'<script>unsafe</script>','returns'=>[],'non_conformities'=>[], 'history'=>[['created_at'=>'2026-09-20 10:00:00','action'=>'create','new_status'=>'draft','actor_name'=>'Agent test','reason'=>null]]];
$transfer['items']=[['id'=>1,'source_finished_stock_id'=>10,'product_name'=>'Produit test','format_name'=>'25 kg','requested_bags'=>20,'reserved_bags'=>20,'shipped_bags'=>10,'received_bags'=>2,'accepted_bags'=>2,'rejected_bags'=>0]];
$transfer['shipments']=[['id'=>1,'shipment_number'=>'EXP-TEST-1','status'=>'partially_received','dispatched_at'=>'2026-09-21 10:00:00','vehicle_reference'=>'TEST-123','items'=>[['id'=>1,'product_name'=>'Produit test','format_name'=>'25 kg','quantity_bags'=>10,'received_bags'=>2]]]];
ob_start();require __DIR__.'/../app/views/transfers/show.php';$html=ob_get_clean();
if(strpos($html,'<script>unsafe</script>')!==false)throw new RuntimeException('Unsafe note');
foreach(['transfer-info','transfer-ship','transfer-receive-1','Historique des opérations'] as $text)if(strpos($html,$text)===false)throw new RuntimeException('Missing '.$text);
file_put_contents(sys_get_temp_dir().'/dagril-transfer-dossier.html',$html);echo "OK : dossier, modals et notes échappées.\n";

Auth::$writable=false;
ob_start();require __DIR__.'/../app/views/transfers/show.php';$readOnly=ob_get_clean();
if(strpos($readOnly,'data-transfer-operation=')!==false)throw new RuntimeException('Read-only user sees operation form');
echo "OK : actions masquées en lecture seule.\n";
