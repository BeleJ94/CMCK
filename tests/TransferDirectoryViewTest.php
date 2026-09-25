<?php
require __DIR__.'/../app/helpers/functions.php';
class Auth { public static function start(){} public static function canAccessSite($id){return true;} public static function currentSiteId(){return null;} public static function can($component,$action,$site=null){return true;} }
$references=['sites'=>[['id'=>1,'code'=>'SRC','name'=>'Source'],['id'=>2,'code'=>'DST','name'=>'Destination']], 'balances'=>[['site_id'=>1,'product_id'=>1,'bag_format_id'=>1,'product_name'=>'Produit test','format_name'=>'25 kg','physical'=>40,'reserved'=>10,'available'=>30],['site_id'=>2,'product_id'=>1,'bag_format_id'=>1,'product_name'=>'Produit test','format_name'=>'25 kg','physical'=>20,'reserved'=>20,'available'=>0],['site_id'=>1,'product_id'=>2,'bag_format_id'=>1,'product_name'=>'Autre produit','format_name'=>'25 kg','physical'=>5,'reserved'=>5,'available'=>0]],'stocks'=>[['product_id'=>1,'bag_format_id'=>1,'id'=>10,'site_id'=>1,'quantity_bags'=>40,'reserved_bags'=>10,'product_name'=>'Produit test','format_name'=>'25 kg']]];
$transfers=[];$transferItems=[];$success=$error=null;
foreach(['draft','submitted','reserved','in_transit','partially_received','quality_control','accepted','return_pending','closed','cancelled','rejected','partially_shipped'] as $i=>$status){
 $id=$i+1;$transfers[]=['id'=>$id,'transfer_number'=>'TEST-TRANSFER-'.$id,'transfer_type'=>$i%2?'internal':'inter_site','status'=>$status,'requested_at'=>sprintf('2026-09-%02d 10:00:00',$id),'source_code'=>'SRC','source_name'=>'Site source','destination_code'=>'DST','destination_name'=>'Site destination','creator_name'=>'Agent test','approver_name'=>null,'submitted_at'=>null,'approved_at'=>null,'closed_at'=>null,'notes'=>'<script>unsafe</script>'];
 $transferItems[$id]=[['product_name'=>'Produit test','format_name'=>'Sac 25 kg','requested_bags'=>20,'shipped_bags'=>10,'received_bags'=>8,'accepted_bags'=>7,'rejected_bags'=>1]];
}
ob_start();require __DIR__.'/../app/views/transfers/index.php';$html=ob_get_clean();
if(strpos($html,'<script>unsafe</script>')!==false||strpos($html,'&lt;script&gt;unsafe&lt;/script&gt;')===false)throw new RuntimeException('Notes must be escaped');
if(preg_match_all('/id="transfer-detail-[0-9]+"/', $html)!==12)throw new RuntimeException('Missing detail modal');
file_put_contents(sys_get_temp_dir().'/dagril-transfers-workspace.html',$html);
echo "OK : rendu des 12 statuts, détails et échappement des notes.\n";
