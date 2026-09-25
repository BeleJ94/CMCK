<?php
require __DIR__.'/../app/helpers/functions.php';
class Auth { public static function start() {} public static function can(...$args){return true;} }
$success=$error=null;$filters=['type'=>'','level'=>'','state'=>'','start_date'=>'2026-09-01','end_date'=>'2026-09-25'];$types=[];$levels=['danger','warning','info','success'];
$alerts=[];
foreach([['danger','active','2026-09-02',null],['warning','active',null,null],['danger','inactive','2026-09-02','2026-09-03']] as $i=>$values){
 $alerts[]=['id'=>$i+1,'severity'=>$values[0],'status'=>$values[1],'read_at'=>$values[2],'resolved_at'=>$values[3],'title'=>'<script>unsafe</script>','message'=>'<img src=x>','type_label'=>'Stock','site_id'=>1,'created_at'=>'2026-09-01 10:00:00'];
}
set_error_handler(static function($n,$m,$f,$l){throw new ErrorException($m,0,$n,$f,$l);});
ob_start();require __DIR__.'/../app/views/alerts/index.php';$html=ob_get_clean();
if(count($groups['active'][1])!==2||count($groups['danger'][1])!==1||count($groups['unread'][1])!==1)throw new RuntimeException('Read critical alert must remain active; resolved excluded');
if(strpos($html,'<script>unsafe</script>')!==false||strpos($html,'<img src=x>')!==false)throw new RuntimeException('Unescaped content');
if(strpos($html,'alerts/3/resolve')!==false||strpos($html,'alerts/1/resolve')===false)throw new RuntimeException('Invalid resolution action');
if(strpos($html,'name="start_date" value="2026-09-01"')===false)throw new RuntimeException('Lost date filter');
$alerts=[];ob_start();require __DIR__.'/../app/views/alerts/index.php';$html=ob_get_clean();
if(strpos($html,'Aucune alerte ne correspond')===false)throw new RuntimeException('Missing empty state');
restore_error_handler();echo "OK : priorité critique lue, états, actions, filtres, échappement et vue vide.\n";
