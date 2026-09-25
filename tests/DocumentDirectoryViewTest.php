<?php
require __DIR__.'/../app/helpers/functions.php';$success=$error=null;$documents=[];
foreach(['draft','pending_approval','pending_validation','validated','rejected','cancelled','closed'] as $i=>$status)$documents[]=['id'=>$i+1,'document_type_id'=>1,'document_number'=>'DOC-TEST-'.$i,'display_code'=>'BT','type_name'=>'Bon de transport','site_code'=>'SILO','status'=>$status,'attachment_count'=>$i%2,'document_date'=>'2026-09-01 10:00:00','creator_name'=>'<script>unsafe</script>','validator_name'=>null,'entity_type'=>'transports','entity_id'=>1];
set_error_handler(static function($n,$m,$f,$l){throw new ErrorException($m,0,$n,$f,$l);});
ob_start();require __DIR__.'/../app/views/documents/index.php';$html=ob_get_clean();
if(strpos($html,'<script>unsafe</script>')!==false)throw new RuntimeException('Unescaped user');
if(count($groups['pending'][1])!==2||count($groups['rejected'][1])!==1||count($groups['unattached'][1])!==4)throw new RuntimeException('Incorrect executive counts');
if(strpos($html,'data-butchery-history="documents"')===false||strpos($html,'document-detail-7')===false)throw new RuntimeException('Missing UI');
$documents=[];ob_start();require __DIR__.'/../app/views/documents/index.php';$html=ob_get_clean();if(strpos($html,'Aucun document dans le périmètre')===false)throw new RuntimeException('Missing empty state');restore_error_handler();echo "OK : compteurs, filtres, détails, échappement et registre vide.\n";
