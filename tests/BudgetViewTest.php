<?php
require __DIR__.'/../app/helpers/functions.php';
class Auth{public static $allowed=true;public static function start(){}public static function can($c,$a){return self::$allowed;}}
$refs=array_fill_keys(['years','periods','sites','centers','categories','users'],[]);$lines=[];$report=[];$approvals=['expenses'=>[],'derogations'=>[],'delegations'=>[]];$success=$error=null;$budgets=[];
foreach(['preparation','pending_df','pending_dg','active','replaced','cancelled'] as $i=>$status)$budgets[]=['id'=>$i+1,'budget_number'=>'BUD-TEST-'.($i+1),'site_code'=>'SRC','center_code'=>'CENTRE','period_name'=>'Année 2027','status'=>$status,'total_proposed'=>1000,'total_validated'=>800,'revision_number'=>1,'created_at'=>'2027-01-01 10:00:00'];
set_error_handler(static function($n,$m,$f,$l){throw new ErrorException($m,0,$n,$f,$l);});
ob_start();require __DIR__.'/../app/views/budgets/index.php';$html=ob_get_clean();if(strpos($html,'budget-detail-4')===false)throw new RuntimeException('Missing detail');file_put_contents(sys_get_temp_dir().'/dagril-budgets.html',$html);
Auth::$allowed=false;ob_start();require __DIR__.'/../app/views/budgets/index.php';$html=ob_get_clean();if(strpos($html,'data-budget-form')!==false)throw new RuntimeException('Read only actions visible');restore_error_handler();echo "OK : rendu des statuts, détails et droits de lecture seule.\n";
