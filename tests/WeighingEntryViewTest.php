<?php
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function base_url($p){return '/'.$p;}
function csrf_field(){return '<input type="hidden" name="_token" value="test">';}
class Auth{public static function user(){return ['name'=>'Agent test'];}}
$entry=['transport_id'=>'4','poids_brut'=>'8350'];$errors=[];
$receptionSites=[['id'=>2,'code'=>'SILO','name'=>'Silos']];
$transports=[['id'=>4,'bt_number'=>'BT/TEST/4','transport_reference'=>'TEST','plate_number'=>'AB-TEST','origin_site_name'=>'Ferme test','product_name'=>'Maïs','shipped_quantity_kg'=>350]];
$checks=0;
function entry_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'OK - '.$label.PHP_EOL;}
foreach([null,2] as$currentSiteId){
    $error='Sélectionnez un site avant d’effectuer cette opération.';
    ob_start();require dirname(__DIR__).'/app/views/weighings/entry.php';$html=ob_get_clean();
    entry_check(strpos($html,e($error))!==false,'erreur affichée sur la page d’entrée');
    if($currentSiteId===null){
        entry_check(strpos($html,'action="/context/site"')!==false&&strpos($html,'value="weighings/entry"')!==false,'choix du site avec retour à la réception');
        entry_check(strpos($html,'data-weighing-entry')===false,'aucun formulaire de pesée sans site');
    }else{
        entry_check(strpos($html,'data-weighing-entry')!==false,'saisie disponible avec site précis');
        entry_check(strpos($html,'value="8350"')!==false&&strpos($html,'value="4" selected')!==false,'BT et poids conservés après refus');
    }
}
echo $checks." vérifications réussies.\n";
