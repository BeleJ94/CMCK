<?php
// Render the actual transport modal with fictional farm stock, without a database.
// The modal offers products grouped by farm (global stock of all harvests), not individual harvests.
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function agro_modal_start(...$args) { echo '<form>'; }
function agro_modal_end(...$args) { echo '</form>'; }
$source=file_get_contents(dirname(__DIR__).'/app/views/agriculture/index.php');
$start=strpos($source,'<?php $transportDestination =');
if($start===false)throw new RuntimeException('Formulaire de transport introuvable.');
$template=substr($source,$start);
$checks=0;
$group=['key'=>'1:5:7','site_id'=>1,'site_code'=>'FARM-TEST','site_name'=>'Ferme test','product_name'=>'Maïs','variety_name'=>'Hybride','free_kg'=>1800.0,'lots'=>[['id'=>10,'label'=>'REC-1','date'=>'01/03/2026','kg'=>1000.0],['id'=>20,'label'=>'REC-2','date'=>'02/03/2026','kg'=>800.0]]];
foreach ([[$group],[]] as $products) {
    $harvests=[];
    $refs=['transport_destination'=>['name'=>'Silos test'],'transport_products'=>$products];
    ob_start();eval('?>'.$template);$html=ob_get_clean();
    $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath=new DOMXPath($dom);
    if($xpath->query('//select[@name="stock_id"]')->length!==0)throw new RuntimeException('Le choix d’une récolte isolée ne doit plus être proposé.');
    $checks++;
    $options=$xpath->query('//select[@name="stock_key"]/optgroup/option');
    if($options->length!==count($products))throw new RuntimeException('Produits proposés incorrects.');
    $checks++;
    if($products){
        $option=$options->item(0);
        if($option->getAttribute('value')!=='1:5:7'||$xpath->query('//select[@name="stock_key"]/optgroup[@label="Ferme test"]')->length!==1)throw new RuntimeException('Regroupement par ferme absent.');
        if(strpos($option->textContent,'1 800 kg disponibles (2 récoltes)')===false)throw new RuntimeException('Disponible global absent : '.$option->textContent);
        $lots=json_decode($option->getAttribute('data-lots'),true);
        if(count($lots)!==2||$lots[0]['label']!=='REC-1')throw new RuntimeException('Récoltes de l’aperçu absentes ou dans le mauvais ordre.');
        if((float)$option->getAttribute('data-free')!==1800.0)throw new RuntimeException('Disponible du produit absent.');
        if($xpath->query('//*[@data-farm-transport-lots]')->length!==1||$xpath->query('//*[@data-farm-transport-fill]')->length!==1)throw new RuntimeException('Aperçu des récoltes ou bouton « Tout » absent.');
        if(strpos($html,'Ferme test → Silos test')===false)throw new RuntimeException('Trajet initial absent.');
    }elseif(strpos($html,'Aucun stock agricole disponible')===false){
        throw new RuntimeException('État vide absent.');
    }
    $checks++;
}
echo $checks." vérifications du formulaire réussies.\n";
