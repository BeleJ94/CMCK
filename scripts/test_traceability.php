<?php
$root=dirname(__DIR__);$model=file_get_contents($root.'/app/models/Traceability.php');$migration=file_get_contents($root.'/database/030_add_complete_traceability.sql');$routes=file_get_contents($root.'/public/index.php');
$checks=[
'liaison BT agricole/pont-bascule'=>strpos($migration,'agricultural_transport_id')!==false,
'reprise non ambiguë'=>strpos($migration,'x.n=1')!==false,
'RBAC traçabilité'=>strpos($migration,"('traceability','read'")!==false,
'recherche document'=>strpos($model,'d.document_number')!==false,
'recherche lot'=>strpos($model,'batch_number')!==false,
'recherche camion'=>strpos($model,'tr.plate_number')!==false,
'recherche produit'=>strpos($model,'pr.name')!==false,
'recherche fournisseur'=>strpos($model,'sup.name')!==false,
'filtre site serveur'=>strpos($model,'Auth::siteClause')!==false&&strpos($model,'requireSiteAccess')!==false,
'filtre période'=>strpos($model,'BETWEEN :start AND :end')!==false,
'quatre chaînes'=>strpos($model,"['agriculture','mill','waste','butchery']")!==false,
'documents et validations'=>strpos($model,'validator_name')!==false,
'mouvements spécialisés'=>strpos($model,'agricultural_stock_movements')!==false&&strpos($model,'waste_stock_movements')!==false&&strpos($model,'butchery_finished_movements')!==false,
'non-conformités'=>strpos($model,'weighbridge_non_conformities')!==false&&strpos($model,'transfer_non_conformities')!==false,
'annulations et corrections'=>strpos($model,'cancellation_reversals')!==false,
'routes protégées'=>substr_count($routes,"permission'=>['traceability','read']")===2,
'PDO paramètres de recherche uniques'=>strpos($model,"\$key='q'.")!==false,
];
$fail=0;foreach($checks as$n=>$ok){echo($ok?'[OK] ':'[ECHEC] ').$n.PHP_EOL;if(!$ok)$fail++;}exit($fail?1:0);
