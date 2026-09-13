<?php
$campaignStates=['draft'=>'Brouillon','planned'=>'Planifiée','in_progress'=>'En cours','harvesting'=>'En récolte','closed'=>'Clôturée','cancelled'=>'Annulée'];
$campaignNumber=function($n,$precision=2){return number_format((float)$n,$precision,',',' ');};
$campaignDate=function($d){return $d?date('d/m/Y',strtotime($d)):'—';};
$createdCampaignId=flash('created_campaign_id');
$campaignSites=[];$campaignSeasons=[];$plotsByCampaign=[];$harvestsByCampaign=[];$plotCampaign=[];
foreach($campaigns as$c){$campaignSites[$c['site_id']]=$c['site_code'];$campaignSeasons[$c['season_id']]=$c['season_name'];}
foreach($campaignPlots as$p){$plotsByCampaign[$p['campaign_id']][]=$p;$plotCampaign[$p['id']]=$p['campaign_id'];}
foreach($harvests as$h){if(isset($plotCampaign[$h['campaign_plot_id']]))$harvestsByCampaign[$plotCampaign[$h['campaign_plot_id']]][]=$h;}
?>
<section id="agriCampaigns" class="campaign-directory" data-campaign-directory data-context="<?=e((string)(Auth::currentSiteId()??'all'))?>" data-created="<?=e($createdCampaignId??'')?>">
    <header class="campaign-heading"><div><p class="page-kicker">AGRICULTURE · <?=e($currentSiteLabel)?></p><h2>Campagnes agricoles</h2><p>Suivez vos objectifs et préparez la prochaine étape.</p></div>
    <?php if(Auth::can('agriculture','create')):?><button class="btn-primary" type="button" data-workspace-modal-open="campaignModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouvelle campagne</button><?php endif;?></header>
    <div class="campaign-summary" aria-label="Synthèse des campagnes filtrées">
        <div><span>Campagnes en cours</span><strong data-campaign-kpi="active">—</strong><small>En cours ou en récolte</small></div>
        <div><span>Superficie exploitée</span><strong data-campaign-kpi="area">—</strong><small>Hectares</small></div>
        <div><span>Objectif de production</span><strong data-campaign-kpi="target">—</strong><small>Tonnes</small></div>
        <div><span>Production validée</span><strong data-campaign-kpi="actual">—</strong><small data-campaign-kpi="progress">Selon les filtres sélectionnés</small></div>
    </div>
    <div class="campaign-list">
        <div class="campaign-filters" role="search" aria-label="Filtrer les campagnes">
            <label>Recherche<input type="search" data-campaign-filter="search" placeholder="Nom ou code de campagne"></label>
            <label>Ferme<select data-campaign-filter="site"><option value="">Toutes les fermes</option><?php foreach($campaignSites as$id=>$label):?><option value="<?=e($id)?>"><?=e($label)?></option><?php endforeach;?></select></label>
            <label>Saison<select data-campaign-filter="season"><option value="">Toutes les saisons</option><?php foreach($campaignSeasons as$id=>$label):?><option value="<?=e($id)?>"><?=e($label)?></option><?php endforeach;?></select></label>
            <label>Statut<select data-campaign-filter="status"><option value="">Tous les statuts</option><?php foreach($campaignStates as$value=>$label):?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach;?></select></label>
            <button type="button" class="btn-secondary" data-campaign-reset>Réinitialiser</button>
        </div>
        <div class="table-responsive"><table class="enterprise-table campaign-table" data-datatable="false"><thead><tr><th>Campagne / ferme</th><th>Saison / période</th><th>Surface</th><th>Réalisé / objectif</th><th>Statut</th><th>Action</th></tr></thead><tbody>
        <?php foreach($campaigns as$c):$progress=$c['target_tons']>0?100*$c['realized_tons']/$c['target_tons']:null;?>
            <tr data-campaign-row data-id="<?=e($c['id'])?>" data-search="<?=e($c['name'].' '.$c['code'].' '.$c['site_code'])?>" data-site="<?=e($c['site_id'])?>" data-season="<?=e($c['season_id'])?>" data-status="<?=e($c['status'])?>" data-area="<?=e($c['actual_area_ha'])?>" data-target="<?=e($c['target_tons'])?>" data-actual="<?=e($c['realized_tons'])?>">
                <td><strong><?=e($c['name'])?></strong><small class="table-subline"><?=e($c['code'].' · '.$c['site_code'])?></small></td>
                <td><?=e($c['season_name'])?><small class="table-subline"><?=e($campaignDate($c['start_date']).' – '.$campaignDate($c['end_date']))?></small></td>
                <td><?=e($campaignNumber($c['actual_area_ha']))?> ha</td>
                <td><strong><?=e($campaignNumber($c['realized_tons']))?></strong> / <?=e($campaignNumber($c['target_tons']))?> t<?php if($progress!==null):?><progress max="100" value="<?=e(min(100,$progress))?>" aria-label="Objectif atteint à <?=e($campaignNumber($progress,1))?> %"></progress><small><?=e($campaignNumber($progress,1))?> % atteint</small><?php else:?><small class="table-subline">Objectif à définir</small><?php endif;?></td>
                <td><span class="campaign-status" data-state="<?=e($c['status'])?>"><?=e($campaignStates[$c['status']]??$c['status'])?></span></td>
                <td><button type="button" class="btn-secondary" data-campaign-open="<?=e($c['id'])?>" aria-haspopup="dialog" aria-label="Consulter <?=e($c['name'])?>">Consulter</button></td>
            </tr>
        <?php endforeach;?>
        <tr data-campaign-empty <?=count($campaigns)?'hidden':''?>><td colspan="6"><?=count($campaigns)?'Aucune campagne ne correspond aux filtres.':'Aucune campagne. Créez une campagne, puis planifiez ses parcelles.'?></td></tr>
        </tbody></table></div>
        <footer class="campaign-pagination"><span data-campaign-count role="status"></span><div><button type="button" class="btn-secondary" data-campaign-page="-1" aria-label="Page précédente">Précédent</button><span data-campaign-page-label></span><button type="button" class="btn-secondary" data-campaign-page="1" aria-label="Page suivante">Suivant</button></div></footer>
    </div>
    <?php foreach($campaigns as$c):$campaignPlans=$plotsByCampaign[$c['id']]??[];$campaignHarvests=$harvestsByCampaign[$c['id']]??[];?>
    <template data-campaign-detail="<?=e($c['id'])?>">
        <header class="campaign-drawer-heading"><div><p class="page-kicker"><?=e($c['site_code'].' · '.$c['season_name'])?></p><h2 id="campaignDrawerTitle"><?=e($c['name'])?></h2><small><?=e($c['code'])?></small></div><button type="button" class="modal-close" data-campaign-close aria-label="Fermer le détail" autofocus><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
        <div class="campaign-drawer-body">
            <?php if(Auth::hasRole(['administrateur'])&&Auth::can('agriculture','update',$c['site_id'])):?>
            <form data-campaign-edit-form data-ajax="false" method="post" action="<?=e(base_url('agriculture/campaigns/'.$c['id'].'/update'))?>" hidden>
                <?=csrf_field()?><input type="hidden" name="_return_to" value="agriculture/campaigns"><input type="hidden" name="version" value="<?=e(Agriculture::campaignVersion($c))?>">
                <h3>Modifier la campagne</h3><p>Ferme : <strong><?=e($c['site_name'])?></strong>. Les surfaces et objectifs se règlent dans la planification des parcelles.</p>
                <div class="app-alert app-alert-error" data-campaign-edit-error role="alert" hidden></div>
                <div class="campaign-edit-grid">
                    <label>Nom *<input name="name" value="<?=e($c['name'])?>" maxlength="160" required></label>
                    <label>Code *<input name="code" value="<?=e($c['code'])?>" maxlength="80" required></label>
                    <label>Saison *<select name="season_id" required><?php if(!in_array($c['season_id'],array_column($refs['seasons'],'id'))):?><option value="<?=e($c['season_id'])?>" selected><?=e($c['season_name'])?> (saison actuelle)</option><?php endif;?><?php foreach($refs['seasons'] as$season):?><option value="<?=e($season['id'])?>" <?=$season['id']==$c['season_id']?'selected':''?>><?=e($season['name'])?></option><?php endforeach;?></select></label>
                    <label>Statut *<select name="status" required><?php foreach($campaignStates as$value=>$label):?><option value="<?=e($value)?>" <?=$value===$c['status']?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                    <label>Début *<input type="date" name="start_date" value="<?=e($c['start_date'])?>" required></label><label>Fin *<input type="date" name="end_date" value="<?=e($c['end_date'])?>" required></label>
                    <label class="wide">Motif de modification *<textarea name="reason" rows="2" maxlength="500" required placeholder="Expliquez brièvement la correction"></textarea></label>
                </div><p>Clôturer ou annuler la campagne bloque les nouvelles saisies de travaux et leurs corrections. Les opérations existantes sont conservées.</p>
                <div class="campaign-edit-footer"><button type="button" class="btn-secondary" data-campaign-edit-back>Retour au détail</button><button type="submit" class="btn-primary">Vérifier et enregistrer</button></div>
            </form>
            <?php endif;?>
            <div data-campaign-summary>
            <?php if((string)$createdCampaignId===(string)$c['id']):?><p class="app-alert app-alert-success">Campagne créée. Planifiez maintenant ses parcelles.</p><?php endif;?>
            <div class="campaign-detail-meta"><span><i class="bi bi-calendar3" aria-hidden="true"></i> <?=e($campaignDate($c['start_date']).' au '.$campaignDate($c['end_date']))?></span><span class="campaign-status" data-state="<?=e($c['status'])?>"><?=e($campaignStates[$c['status']]??$c['status'])?></span></div>
            <dl class="campaign-detail-numbers"><div><dt>Surface exploitée</dt><dd><?=e($campaignNumber($c['actual_area_ha'],3))?> ha</dd></div><div><dt>Objectif</dt><dd><?=e($campaignNumber($c['target_tons'],3))?> t</dd></div><div><dt>Production validée</dt><dd><?=e($campaignNumber($c['realized_tons'],3))?> t</dd></div></dl>
            <?php if($c['target_tons']>0):$detailProgress=100*$c['realized_tons']/$c['target_tons'];?><div class="campaign-detail-progress"><span><?=e($campaignNumber($detailProgress,1))?> % de l’objectif atteint</span><progress max="100" value="<?=e(min(100,$detailProgress))?>" aria-label="Objectif atteint à <?=e($campaignNumber($detailProgress,1))?> %"></progress><small>Seules les récoltes validées comptent dans la production.</small></div><?php endif;?>
            <div class="campaign-detail-actions"><?php if(Auth::hasRole(['administrateur'])&&Auth::can('agriculture','update',$c['site_id'])):?><button type="button" class="btn-primary" data-campaign-edit><i class="bi bi-pencil-square" aria-hidden="true"></i> Modifier la campagne</button><?php endif;?><?php if(Auth::can('agriculture','create',$c['site_id'])&&!in_array($c['status'],['closed','cancelled'],true)):?><button type="button" class="btn-primary" data-campaign-plan data-workspace-modal-open="planModal" data-plan-site-id="<?=e($c['site_id'])?>" data-plan-campaign-id="<?=e($c['id'])?>">Planifier une parcelle</button><?php endif;?><button type="button" class="btn-secondary" data-campaign-show-harvests>Voir les récoltes (<?=count($campaignHarvests)?>)</button></div>
            <div class="campaign-detail-section"><h3>Parcelles planifiées <span><?=count($campaignPlans)?></span></h3>
            <?php if(!$campaignPlans):?><p class="campaign-empty-note">Aucune parcelle planifiée. L’objectif sera calculé à partir des surfaces et rendements définis.</p><?php endif;?>
            <?php foreach($campaignPlans as$p):?><article class="campaign-detail-item"><strong><?=e($p['plot_name'].' · '.$p['plot_code'])?></strong><span><?=e($p['variety_name'])?></span><small><?=e($campaignNumber($p['actual_area_ha'],3))?> ha exploités · <?=e($campaignNumber($p['target_tons_per_ha'],3))?> t/ha visées</small></article><?php endforeach;?>
            </div><div class="campaign-detail-section"><h3 data-campaign-harvest-heading tabindex="-1">Récoltes · <?=count($campaignHarvests)?></h3>
            <?php if(!$campaignHarvests):?><p class="campaign-empty-note">Aucune récolte enregistrée pour cette campagne.</p><?php endif;?>
            <?php $harvestStates=['draft'=>'Brouillon','drying'=>'En séchage','submitted'=>'À valider','validated'=>'Validée','cancelled'=>'Annulée'];foreach($campaignHarvests as$h):?><article class="campaign-detail-item"><strong><?=e($h['harvest_number'])?></strong><span><?=e($h['plot_code'].' · '.$campaignDate($h['harvested_at']))?></span><small><?=e($campaignNumber($h['net_weight_kg'],3))?> kg · <?=e($harvestStates[$h['status']]??$h['status'])?></small></article><?php endforeach;?>
            </div></div>
        </div>
    </template>
    <?php endforeach;?>
    <dialog class="campaign-drawer campaign-detail-modal" data-campaign-drawer aria-labelledby="campaignDrawerTitle"></dialog>
</section>
