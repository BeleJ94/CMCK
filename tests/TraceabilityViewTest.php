<?php
require __DIR__.'/../app/helpers/functions.php';class Auth{public static function canViewConsolidated(){return true;}}
$filters=['q'=>'','site_id'=>'','start_date'=>'2026-09-01','end_date'=>'2026-09-30'];$sites=[];$error=null;$results=[];
$dossier=[['stage'=>'Réception','status'=>'validated','reference'=>'BT-TEST','label'=>'<script>unsafe</script>','quantity'=>100,'unit'=>'kg','site_code'=>'SILO','event_date'=>'2026-09-01 10:00:00','user_name'=>'Agent','validator_name'=>null,'documents'=>[],'movements'=>[],'nonconformities'=>[['reference'=>'NC-TEST','status'=>'open','description'=>'Écart']],'corrections'=>[]]];
set_error_handler(static function($n,$m,$f,$l){throw new ErrorException($m,0,$n,$f,$l);});
ob_start();require __DIR__.'/../app/views/traceability/index.php';$html=ob_get_clean();if(strpos($html,'<script>unsafe</script>')!==false||strpos($html,'1 non-conformité(s) à examiner')===false)throw new RuntimeException('Incorrect executive summary');file_put_contents(sys_get_temp_dir().'/dagril-traceability.html',$html);
$dossier=null;ob_start();require __DIR__.'/../app/views/traceability/index.php';$html=ob_get_clean();if(strpos($html,'Aucun résultat')===false)throw new RuntimeException('Missing empty state');restore_error_handler();echo "OK : synthèse, état vide, détails et échappement.\n";
