<?php
require dirname(__DIR__).'/app/helpers/functions.php';
class Auth{static function currentSiteId(){return null;}static function can(...$args){return false;}}
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "OK - $label\n";}
$currentSiteLabel='Test';$plots=[['id'=>1,'name'=>'Parcelle','code'=>'PAR-1','site_id'=>1,'site_name'=>'Ferme','site_code'=>'FARM-MUT','status'=>'active','total_area_ha'=>100]];
$campaigns=[['id'=>10,'name'=>'Courante','start_date'=>date('Y').'-01-01','end_date'=>date('Y').'-12-31','status'=>'in_progress'],['id'=>9,'name'=>'Ancienne','start_date'=>'2020-01-01','end_date'=>'2020-12-31','status'=>'closed']];
$campaignPlots=[['id'=>100,'campaign_id'=>10,'plot_id'=>1,'plot_name'=>'Parcelle','plot_code'=>'PAR-1','campaign_code'=>'C-10','variety_name'=>'Maïs','actual_area_ha'=>20,'target_tons_per_ha'=>1.5,'status'=>'in_progress'],['id'=>90,'campaign_id'=>9,'plot_id'=>1,'plot_name'=>'Parcelle','plot_code'=>'PAR-1','campaign_code'=>'C-9','variety_name'=>'Maïs','actual_area_ha'=>100,'target_tons_per_ha'=>3,'status'=>'closed']];
$harvests=[['campaign_plot_id'=>100,'net_weight_kg'=>12000,'status'=>'validated'],['campaign_plot_id'=>100,'net_weight_kg'=>8000,'status'=>'submitted'],['campaign_plot_id'=>90,'net_weight_kg'=>90000,'status'=>'validated'],['campaign_plot_id'=>100,'net_weight_kg'=>7000,'status'=>'validated','deleted_at'=>'2026-01-01']];
ob_start();require view_path('agriculture.plots');$html=ob_get_clean();
check(str_contains($html,'data-plot-use="20"'),'20 ha sur 100 : 20 %, sans cumul historique');
check(str_contains($html,'data-plot-harvest="12"'),'Seules les récoltes validées non supprimées de la campagne affichée comptent');
check(str_contains($html,'12,00 t / 30,00 t')&&str_contains($html,'40,00 %'),'12 t sur 30 : objectif atteint à 40 %');
check(str_contains($html,'points="43,124 82,113.6 229,168.6 190,179"'),'Surface du polygone vert proportionnelle à 20 %');
preg_match_all('/<clipPath id="([^"]+)"/',$html,$ids);check(count($ids[1])===count(array_unique($ids[1])),'Identifiants SVG distincts entre carte et détail');
$mainPlan=$campaignPlots[0];$plot=$plots[0];$plotValidated=[100=>45];ob_start();require view_path('agriculture.plot_illustration');$over=ob_get_clean();check(str_contains($over,'150,00 %')&&str_contains($over,'value="100"'),'Dépassement conservé dans le libellé, barre limitée à 100 %');
$mainPlan=null;ob_start();require view_path('agriculture.plot_illustration');$empty=ob_get_clean();check(str_contains($empty,'Aucune planification')&&str_contains($empty,'Aucune campagne affichée'),'État sans planification explicite');
$mainPlan=$campaignPlots[0];$mainPlan['target_tons_per_ha']=0;ob_start();require view_path('agriculture.plot_illustration');$zero=ob_get_clean();check(str_contains($zero,'Objectif non défini'),'Objectif nul sans division par zéro');
