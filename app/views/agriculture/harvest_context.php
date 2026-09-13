<?php
$harvestCampaignMap=array_column($campaigns,null,'id');$validatedByPlan=[];
foreach($harvests as$harvest){if($harvest['status']==='validated')$validatedByPlan[$harvest['campaign_plot_id']]=($validatedByPlan[$harvest['campaign_plot_id']]??0)+(float)$harvest['net_weight_kg'];}
$harvestContextNumber=fn($n)=>number_format((float)$n,2,',',' ');
?>
<label class="field-wide"><span>Campagne / parcelle *</span><select name="campaign_plot_id" required data-harvest-plan aria-controls="harvestPlanSummary">
<?php if(!$campaignPlots):?><option value="">Aucune parcelle planifiée disponible</option><?php endif;?>
<?php foreach($campaignPlots as$plan):$campaign=$harvestCampaignMap[$plan['campaign_id']]??[];
$summary=[
    'title'=>$plan['plot_name'].' · '.$plan['plot_code'],
    'campaign'=>($campaign['name']??$plan['campaign_code']).' · '.(!empty($campaign['start_date'])?date('d/m/Y',strtotime($campaign['start_date'])).' au '.date('d/m/Y',strtotime($campaign['end_date'])):'Période non renseignée'),
    'farm'=>$campaign['site_name']??'—', 'variety'=>$plan['variety_name'],
    'area'=>$harvestContextNumber($plan['actual_area_ha']).' ha',
    'yield'=>$harvestContextNumber($plan['target_tons_per_ha']).' t/ha',
    'target'=>$harvestContextNumber($plan['actual_area_ha']*$plan['target_tons_per_ha']).' t',
    'validated'=>$harvestContextNumber(($validatedByPlan[$plan['id']]??0)/1000).' t',
];?>
<option value="<?=e($plan['id'])?>" data-harvest-summary="<?=e(json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))?>"><?=e($plan['campaign_code'].' / '.$plan['plot_code'].' · '.$plan['actual_area_ha'].' ha')?></option>
<?php endforeach;?></select><small>Plusieurs récoltes peuvent être enregistrées sur cette même campagne et parcelle. Chaque saisie crée un lot distinct.</small></label>
<aside id="harvestPlanSummary" class="harvest-plan-summary field-wide" aria-label="Résumé de la parcelle sélectionnée" aria-live="polite" hidden>
    <div class="harvest-plan-summary-heading"><i class="bi bi-bounding-box" aria-hidden="true"></i><div><strong data-harvest-info="title"></strong><small data-harvest-info="campaign"></small></div></div>
    <dl><div><dt>Ferme</dt><dd data-harvest-info="farm"></dd></div><div><dt>Variété</dt><dd data-harvest-info="variety"></dd></div><div><dt>Surface exploitée</dt><dd data-harvest-info="area"></dd></div><div><dt>Rendement visé</dt><dd data-harvest-info="yield"></dd></div><div><dt>Objectif parcelle</dt><dd data-harvest-info="target"></dd></div><div><dt>Récoltes déjà validées</dt><dd data-harvest-info="validated"></dd></div></dl>
    <small class="harvest-plan-summary-note">Données de la campagne sélectionnée · hors récolte en cours de saisie.</small>
</aside>
