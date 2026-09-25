<?php
require __DIR__.'/../app/helpers/functions.php';
class Auth{public static function start(){} public static function can($c,$a,$site=null){return true;} public static function user(){return ['id'=>1];}}
$success=$error=null;$policies=[];$requests=[];
foreach(['pending_approval','completed','rejected'] as $i=>$status)$requests[]=['id'=>$i+1,'status'=>$status,'requested_by'=>1,'site_id'=>1,'request_number'=>'ANN-TEST-'.$i,'label'=>'Sortie','entity_id'=>10,'requested_at'=>'2026-09-25 10:00:00','site_code'=>'SILO','requester_name'=>'Agent','approver_name'=>null,'reversal_number'=>$status==='completed'?'CTR-TEST':null,'reason'=>'<script>unsafe</script>','stock_effects'=>'Restitution','blocking_dependencies'=>'Contrôles métier','document_types'=>'BS'];
set_error_handler(static function($n,$m,$f,$l){throw new ErrorException($m,0,$n,$f,$l);});
ob_start();require __DIR__.'/../app/views/cancellations/index.php';$html=ob_get_clean();
if(strpos($html,'<script>unsafe</script>')!==false||strpos($html,'Approuver et exécuter')!==false)throw new RuntimeException('Unsafe content or self approval');
if(count($groups['pending'][1])!==1||count($groups['completed'][1])!==1)throw new RuntimeException('Wrong metrics');
$requests[0]['requested_by']=2;ob_start();require __DIR__.'/../app/views/cancellations/index.php';$html=ob_get_clean();if(strpos($html,'Approuver et exécuter')===false||strpos($html,'Confirmer le refus')===false)throw new RuntimeException('Missing decision form');
$requests=[];ob_start();require __DIR__.'/../app/views/cancellations/index.php';$html=ob_get_clean();if(strpos($html,'Aucune demande')===false)throw new RuntimeException('Missing empty state');restore_error_handler();echo "OK : compteurs, détails, décisions, séparation des acteurs et état vide.\n";
