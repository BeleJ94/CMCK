<?php
// Render the actual transport modal with fictional harvests, without a database.
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function agro_modal_start(...$args) { echo '<form>'; }
function agro_modal_end(...$args) { echo '</form>'; }
$source=file_get_contents(dirname(__DIR__).'/app/views/agriculture/index.php');
$start=strpos($source,'<?php $transportDestination =');
if($start===false)throw new RuntimeException('Formulaire de transport introuvable.');
$template=substr($source,$start);
$refs=['transport_destination'=>['name'=>'Silos test']];
$base=['stock_status'=>'available','physical_quantity_kg'=>1000,'reserved_quantity_kg'=>0,'site_code'=>'FARM-TEST','site_name'=>'Ferme test','harvest_number'=>'REC-TEST','variety_name'=>'Maïs'];
$checks=0;
foreach ([false,true] as $allAssigned) {
    $harvests=[array_merge($base,['stock_id'=>10,'has_draft_transport'=>1]),array_merge($base,['stock_status'=>'reserved','reserved_quantity_kg'=>200,'stock_id'=>20,'has_draft_transport'=>$allAssigned?1:0])];
    ob_start();eval('?>'.$template);$html=ob_get_clean();
    $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath=new DOMXPath($dom);
    if($xpath->query('//select[@name="stock_id"]/option[@value="10"]')->length!==0)throw new RuntimeException('Stock déjà associé proposé.');
    $checks++;
    if($xpath->query('//select[@name="stock_id"]/option[@value="20"]')->length!==($allAssigned?0:1))throw new RuntimeException('Filtrage des stocks incorrect.');
    $checks++;
    if($allAssigned && strpos($html,'Aucun stock disponible sans BT en brouillon')===false)throw new RuntimeException('État vide absent.');
    if(!$allAssigned && strpos($html,'Ferme test → Silos test')===false)throw new RuntimeException('Trajet initial absent.');
    $checks++;
}
echo $checks." vérifications du formulaire réussies.\n";
