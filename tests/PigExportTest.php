<?php
require_once dirname(__DIR__).'/app/services/PigExportService.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$rows=[];
foreach([['A','male','fattening',50],['C','male','fattening',8],['C','female','fattening',8],['C','male','breeding',2],['C','female','breeding',2]] as$i=>$r){
    $rows[]=['id'=>$i+1,'batch_number'=>'PORC-'.$i,'site_name'=>'Ferme DIKAPA','facility_name'=>'Bâtiment 1','confirmed_group'=>$r[0],'sex'=>$r[1],'purpose'=>$r[2],'current_heads'=>$r[3],'born_on'=>'2026-01-01','pending'=>false,'expected_group'=>$r[0]];
}
$s=new PigExportService();$r=$s->report($rows,['filter'=>'C']);
check($r['totals']['heads']===20&&$r['totals']['male']===10&&$r['totals']['female']===10&&$r['totals']['breeders']===4,'Totaux groupe C');
$r=$s->report($rows,['filter'=>'C','metric'=>'breeding','q'=>'batiment']);
check(count($r['rows'])===2&&$r['totals']['heads']===4,'Intersection groupe/reproducteurs/recherche accentuée');
$groups=[['code'=>'A'],['code'=>'B'],['code'=>'C'],['code'=>'D']];
check(count($s->report($rows,['filter'=>'B'],$groups)['rows'])===0&&count($s->report($rows,['filter'=>'D'],$groups)['rows'])===0,'Export vide, y compris pour un groupe ajouté au référentiel');
try{$s->report($rows,['filter'=>'Z'],$groups);check(false,'Groupe inconnu refusé');}catch(InvalidArgumentException $e){}
$many=[];for($i=1;$i<=23;$i++){$row=$rows[0];$row['id']=$i;$row['current_heads']=$i;$row['batch_number']='=FORMULA';$many[]=$row;}
$r=$s->report($many,['sort'=>'heads','direction'=>'desc']);
check(count($r['rows'])===23&&$r['rows'][0]['current_heads']===23,'Toutes les pages et tri');
$file=tempnam(sys_get_temp_dir(),'pig-export-test');
try{file_put_contents($file,$s->spreadsheet($r));$zip=new ZipArchive();check($zip->open($file)===true,'Vrai XLSX');$xml=$zip->getFromName('xl/worksheets/sheet1.xml');check(strpos($xml,'=FORMULA')!==false&&strpos($xml,'<f>')===false,'Valeurs sans formule');check(substr_count($xml,'<row ')===29,'23 lots, en-tête et total');check(strpos($xml,'autoFilter ref="A5:I28"')!==false,'Filtres Excel');$zip->close();}finally{unlink($file);}
echo "OK : exports filtrés, totaux, tri, toutes les pages, vrai XLSX et valeurs sans formule.\n";
