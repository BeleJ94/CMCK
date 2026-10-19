<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/integration/TestBootstrap.php';$db=DagrilIntegrationTest::boot();require_once __DIR__.'/../app/services/PorcineFlowService.php';
$fx=json_decode(file_get_contents(getenv('DAGRIL_FLOW_FIXTURE')),true);if($fx['database']!==getenv('DAGRIL_DB_DATABASE'))throw new RuntimeException('Fixture incompatible.');
$flow=new PorcineFlowService($db);$user=DagrilIntegrationTest::loginRole('administrateur');Auth::selectSite((string)$fx['butchery']);
function reportCheck($ok,$message){DagrilIntegrationTest::check($ok,$message);if(!$ok)throw new RuntimeException($message);}
function reportBad($callback,$message){$denied=false;try{$callback();}catch(Throwable$e){$denied=true;}reportCheck($denied,$message);}
$prefix='RAPPORT-'.strtoupper(bin2hex(random_bytes(4)));$today=date('Y-m-d');$outside=date('Y-m-d',strtotime('+1 day'));$tickets=[];
$insert=$db->prepare("INSERT INTO butchery_pos_tickets(site_id,counter_id,ticket_number,counter_name,cashier_name,customer_name,currency,total,payment_method,received_amount,change_amount,status,created_by,sold_at) VALUES(?,?,?,'Comptoir rapport','Vendeur test',?,?,?,?,?,?,?,?,?)");
$line=$db->prepare('INSERT INTO butchery_pos_lines(ticket_id,item_id,item_name,quantity_kg,unit_price,amount) VALUES(?,?,?,?,?,?)');
$db->beginTransaction();
try{
 for($i=1;$i<=105;$i++){
  $reference=$prefix.'-'.str_pad((string)$i,3,'0',STR_PAD_LEFT);$customer=$i===1?'=HYPERLINK("https://example.test","TEST")':'Client rapport';$time=$i===1?'00:00:00':($i===105?'23:59:59':'12:00:00');
  $insert->execute([$fx['butchery'],$fx['counter'],$reference,$customer,'USD','10.01','cash','20.00','9.99','paid',$user['id'],$today.' '.$time]);$id=(int)$db->lastInsertId();$tickets[]=$id;
  $line->execute([$id,$fx['item1'],'Article A','0.125','26.64','3.33']);$line->execute([$id,$fx['item2'],'Article B','0.500','13.36','6.68']);
 }
 $insert->execute([$fx['butchery'],$fx['counter'],$prefix.'-ANNULE','Client annulé','USD','99.99','cash','100.00','0.01','cancelled',$user['id'],$today.' 15:00:00']);$id=(int)$db->lastInsertId();$line->execute([$id,$fx['item1'],'Article A','1','99.99','99.99']);
 foreach([['3333.33','mobile_money',$fx['counter']],['6666.67','cash',$fx['counter']]]as[$amount,$method,$counter]){$insert->execute([$fx['butchery'],$counter,$prefix.'-CDF-'.$method,'Client CDF','CDF',$amount,$method,$amount,'0','paid',$user['id'],$today.' 16:00:00']);$id=(int)$db->lastInsertId();$line->execute([$id,$fx['item1'],'Article A CDF','1',$amount,$amount]);}
 $insert->execute([$fx['butchery'],$fx['counter'],$prefix.'-DEMAIN','Hors période','USD','777.77','cash','777.77','0','paid',$user['id'],$outside.' 00:00:00']);$id=(int)$db->lastInsertId();$line->execute([$id,$fx['item1'],'Hors période','1','777.77','777.77']);
 $other=(int)$db->query("SELECT id FROM sites WHERE code='FARM-DIK'")->fetchColumn();$db->prepare("INSERT INTO butchery_counters(site_id,code,name,status) VALUES(?,?,'Autre site test','active')")->execute([$other,$prefix]);$otherCounter=(int)$db->lastInsertId();
 $insert->execute([$other,$otherCounter,$prefix.'-AUTRE-SITE','Autre site','USD','888.88','cash','888.88','0','paid',$user['id'],$today.' 12:00:00']);$id=(int)$db->lastInsertId();$line->execute([$id,$fx['item1'],'Autre site','1','888.88','888.88']);
 $db->commit();
}catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
$query=['from'=>$today,'to'=>$today,'q'=>$prefix];$report=$flow->posSalesReport($query);
reportCheck(count($report['tickets'])===108,'Toutes les ventes filtrées sont présentes, au-delà des 100 derniers tickets');
reportCheck(!$db->inTransaction(),'La lecture du rapport libère sa transaction cohérente');
$summary=array_column($report['summary'],null,'currency');
reportCheck(count($summary)===2,'Les deux devises sont présentées séparément');
reportCheck($summary['USD']['revenue_cents']===105105&&$summary['USD']['paid']===105,'Chiffre d’affaires USD : un total par ticket, sans multiplication par les articles');
reportCheck($summary['USD']['cancelled']===1&&$summary['USD']['cancelled_cents']===9999,'Annulation visible et exclue du chiffre d’affaires');
reportCheck($summary['USD']['grams']===65625,'Poids vendu : seules les ventes encaissées sont comptées');
reportCheck($summary['CDF']['revenue_cents']===1000000,'Montant CDF indépendant du total USD');
reportCheck(count($report['articles'])===3,'Synthèse des articles séparée par devise');
$articleUsd=array_filter($report['articles'],static fn($a)=>$a['currency']==='USD');reportCheck(array_sum(array_column($articleUsd,'cents'))===105105,'Somme des montants enregistrés des articles égale au chiffre d’affaires');
$cashUsd=array_values(array_filter($report['payments'],static fn($p)=>$p['currency']==='USD'));reportCheck($cashUsd[0]['cents']===105105,'Paiements : montant vendu, pas montant reçu avant monnaie rendue');
reportCheck(count($flow->posSalesReport($query+['currency'=>'USD','status'=>'paid'])['tickets'])===105,'Filtres devise et état combinés');
reportCheck(count($flow->posSalesReport($query+['payment'=>'mobile_money'])['tickets'])===1,'Filtre du mode de paiement');
reportCheck(count($flow->posSalesReport($query+['status'=>'cancelled'])['articles'])===0,'Les tickets annulés ne créent pas de chiffre d’affaires par article');
reportCheck(count($flow->posSalesReport(['from'=>$today,'to'=>$today,'q'=>'=HYPERLINK'])['tickets'])===1,'Recherche littérale sûre sur le client');
reportBad(fn()=>$flow->posSalesReport($query+['counter'=>(string)$otherCounter]),'Comptoir d’un autre site refusé');
$emptyCounter=(int)$db->query('SELECT id FROM butchery_counters WHERE site_id='.(int)$fx['butchery'].' AND id<>'.(int)$fx['counter'].' LIMIT 1')->fetchColumn();
reportCheck(count($flow->posSalesReport($query+['counter'=>(string)$emptyCounter])['tickets'])===0,'Filtre sur un autre comptoir autorisé sans vente');
foreach([['from'=>'2026-02-30'],['from'=>$outside],['currency'=>'EUR'],['payment'=>'inconnu'],['status'=>'autre'],['q'=>['invalide']]]as$patch)reportBad(fn()=>$flow->posSalesReport(array_replace($query,$patch)),'Filtre invalide refusé : '.array_key_first($patch));
$empty=$flow->posSalesReport(array_replace($query,['q'=>'rapport-absent-786832']));reportCheck(!$empty['tickets']&&!$empty['summary']&&!$empty['articles'],'Rapport vide sans faux montants');
$exporter=new ButcherySalesReportService();$bytes=$exporter->spreadsheet($report);reportCheck(substr($bytes,0,2)==='PK','Export Excel au vrai format XLSX');
$path=tempnam(sys_get_temp_dir(),'pos-report-test-');file_put_contents($path,$bytes);
try{
 $zip=new ZipArchive();reportCheck($zip->open($path)===true,'Classeur lisible');$book=new DOMDocument();$book->loadXML($zip->getFromName('xl/workbook.xml'));$xp=new DOMXPath($book);$xp->registerNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');reportCheck($xp->query('//m:sheet')->length===4,'Classeur avec synthèse, tickets, articles et paiements');
 foreach(range(1,4)as$i){$xml=new DOMDocument();reportCheck($xml->loadXML($zip->getFromName('xl/worksheets/sheet'.$i.'.xml')),'Feuille XML valide '.$i);$xp=new DOMXPath($xml);$xp->registerNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');reportCheck($xp->query('//m:f')->length===0,'Aucune formule issue du contenu utilisateur');}
 $xml=new DOMDocument();$xml->loadXML($zip->getFromName('xl/worksheets/sheet2.xml'));$xp=new DOMXPath($xml);$xp->registerNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
 reportCheck($xp->query('//m:row[number(@r)>=6 and number(@r)<=113]')->length===108,'Excel contient toutes les pages et les 108 tickets');
 reportCheck($xp->query('//m:c[@r="G6" and @s="3"]/m:v')->length===1,'Montants Excel numériques à deux décimales');
 reportCheck(strpos($xml->textContent,'=HYPERLINK(')!==false,'Contenu client conservé en texte sans formule');
 $zip->close();
}finally{unlink($path);}
require_once __DIR__.'/../app/core/Controller.php';require_once __DIR__.'/../app/controllers/PorcineFlowController.php';
$_GET=$query;ob_start();(new PorcineFlowController())->posReport();$html=ob_get_clean();reportCheck(strpos($html,'Rapport de ventes')!==false&&strpos($html,'Warning:')===false,'Page du rapport rendue sans avertissement');
$db->beginTransaction();
try{
 $slug='sales-report-read-'.bin2hex(random_bytes(4));$db->prepare("INSERT INTO roles(name,slug,status) VALUES('Lecture rapport',?,'active')")->execute([$slug]);$role=(int)$db->lastInsertId();$db->prepare("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component='butchery' AND action='read'")->execute([$role]);$db->prepare("INSERT INTO users(role_id,name,email,password,status) VALUES(?,'Lecteur rapport',?,'unused','active')")->execute([$role,$slug.'@example.test']);$reader=(int)$db->lastInsertId();$db->prepare("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')")->execute([$reader,$fx['butchery']]);$db->prepare("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')")->execute([$reader,$role,$fx['butchery']]);$row=$db->query('SELECT * FROM users WHERE id='.$reader)->fetch();Auth::login($row);Auth::selectSite((string)$fx['butchery']);
 reportCheck(count($flow->posSalesReport($query)['tickets'])===108,'Lecture seule : rapport et export autorisés');
 $db->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$role]);Auth::login($row);reportBad(fn()=>$flow->posSalesReport($query),'Absence du droit de lecture : rapport refusé');
}finally{if($db->inTransaction())$db->rollBack();Auth::login($user);Auth::selectSite((string)$fx['butchery']);}
if(getenv('DAGRIL_REPORT_FIXTURE'))file_put_contents(getenv('DAGRIL_REPORT_FIXTURE'),json_encode(['database'=>getenv('DAGRIL_DB_DATABASE'),'prefix'=>$prefix,'today'=>$today,'counter'=>$fx['counter'],'query'=>$query]));
DagrilIntegrationTest::finish();
