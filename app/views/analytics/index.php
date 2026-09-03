<?php
$query=http_build_query(['start_date'=>$filters['start_date'],'end_date'=>$filters['end_date'],'site_id'=>$filters['consolidated']?'all':$filters['effective_site_id']]);
$periodLabel=date('d/m/Y',strtotime($filters['start_date'])).' — '.date('d/m/Y',strtotime($filters['end_date']));
?>
<section class="analytics-hero">
    <div><p class="section-label"><?= $filters['consolidated']?'DIRECTION · VUE CONSOLIDÉE':'PILOTAGE · SITE ACTIF' ?></p><h2><?=e($title)?></h2><p>Indicateurs issus exclusivement des opérations validées · Actualisé le <?=e($generatedAt)?></p></div>
    <?php if(empty($exportMode)):?><a class="page-action" href="<?=e(base_url('reports'))?>"><i class="bi bi-file-earmark-bar-graph"></i><span>Rapports détaillés</span></a><?php endif;?>
</section>

<?php if(empty($exportMode)):?>
<section class="analytics-filterbar" aria-label="Filtres du tableau de bord">
    <form method="get" action="<?=e(base_url($detailCode?'analytics/'.$detailCode:'analytics'))?>">
        <label><span>Du</span><input type="date" name="start_date" value="<?=e($filters['start_date'])?>"></label>
        <label><span>Au</span><input type="date" name="end_date" value="<?=e($filters['end_date'])?>"></label>
        <label class="analytics-site-filter"><span>Site / consolidation</span><select name="site_id"><?php if(Auth::canViewConsolidated()):?><option value="all" <?=$filters['consolidated']?'selected':''?>>Tous les sites</option><?php endif;?><?php foreach($sites as$s):?><option value="<?=$s['id']?>" <?=(int)$filters['effective_site_id']===(int)$s['id']?'selected':''?>><?=e($s['code'].' — '.$s['name'])?></option><?php endforeach;?></select></label>
        <button class="btn-primary"><i class="bi bi-arrow-clockwise"></i> Actualiser</button>
    </form>
    <div class="analytics-filter-actions"><span><i class="bi bi-calendar3"></i><?=e($periodLabel)?></span><a href="<?=e(base_url(($detailCode?'analytics/'.$detailCode:'analytics').'?export=excel&'.$query))?>">Excel</a><a target="_blank" href="<?=e(base_url(($detailCode?'analytics/'.$detailCode:'analytics').'?export=pdf&'.$query))?>">PDF</a></div>
</section>
<?php endif;?>

<section class="analytics-section-head"><div><span>Vue d’ensemble</span><h3>Indicateurs clés</h3></div><small><?=count($kpis)?> indicateur(s) autorisé(s)</small></section>
<section class="analytics-card-grid">
<?php foreach($kpis as$index=>$k):$warning=$k['status']==='warning';?>
    <?php if(empty($exportMode)):?><button type="button" class="analytics-card <?=$warning?'is-warning':''?>" data-analytics-open="<?=$index?>" aria-label="Ouvrir le graphique <?=e($k['label'])?>"><?php else:?><article class="analytics-card <?=$warning?'is-warning':''?>"><?php endif;?>
        <div class="analytics-card-head"><span><?=e($k['label'])?></span><i class="bi <?=$warning?'bi-exclamation-circle':'bi-arrow-up-right'?>"></i></div>
        <div class="analytics-card-value"><strong><?=e(number_format((float)$k['value'],in_array($k['unit'],['%','t/ha','kg/jour','kg/m²','/kg'],true)?2:0,',',' '))?></strong><span><?=e($k['unit'])?></span></div>
        <div class="analytics-mini-chart"><canvas data-analytics-mini="<?=$index?>" aria-hidden="true"></canvas></div>
        <div class="analytics-card-foot"><span><i class="bi bi-check2-circle"></i><?=e($k['count'])?> source(s) validée(s)</span><?php if(empty($exportMode)):?><em>Analyser <i class="bi bi-chevron-right"></i></em><?php endif;?></div>
    <?php if(empty($exportMode)):?></button><?php else:?></article><?php endif;?>
<?php endforeach;?>
</section>

<?php if($detailCode):?><section class="table-panel"><div class="panel-heading"><span class="panel-icon"><i class="bi bi-list-check"></i></span><div><h3>Justification — <?=e($detailMeta['label'])?></h3><p>Uniquement les opérations validées ou les états de stock issus de mouvements validés.</p></div></div><div class="table-responsive"><table class="enterprise-table"><thead><tr><?php $columns=$detailRows?array_keys($detailRows[0]):['reference','site','operation_date','value'];foreach($columns as$c):?><th><?=e(str_replace('_',' ',$c))?></th><?php endforeach;?></tr></thead><tbody><?php foreach($detailRows as$r):?><tr><?php foreach($columns as$c):?><td><?=e($r[$c]??'')?></td><?php endforeach;?></tr><?php endforeach;?><?php if(!$detailRows):?><tr><td colspan="<?=count($columns)?>">Aucune opération validée sur ce périmètre.</td></tr><?php endif;?></tbody></table></div></section><?php endif;?>

<?php if(empty($exportMode)):?>
<div class="analytics-modal-backdrop" data-analytics-close></div>
<section class="analytics-modal" role="dialog" aria-modal="true" aria-labelledby="analyticsModalTitle" aria-hidden="true" data-analytics-modal>
    <header><div><p class="section-label">ANALYSE DE L’INDICATEUR</p><h2 id="analyticsModalTitle" data-analytics-title>Indicateur</h2><span data-analytics-subtitle></span></div><button type="button" class="modal-close" data-analytics-close aria-label="Fermer"><i class="bi bi-x-lg"></i></button></header>
    <div class="analytics-modal-body">
        <div class="analytics-modal-summary"><article><span>Valeur actuelle</span><strong data-analytics-value>0</strong></article><article><span>Périmètre</span><strong><?=e($filters['consolidated']?'Tous les sites':'Site sélectionné')?></strong></article><article><span>Période</span><strong><?=e($periodLabel)?></strong></article></div>
        <div class="analytics-modal-chart"><canvas data-analytics-chart></canvas><div class="analytics-chart-tooltip" data-analytics-tooltip></div><p data-analytics-empty>Aucune donnée validée sur cette période.</p></div>
        <div class="analytics-modal-legend"><span><i></i>Opérations validées</span><small>Survolez le graphique pour afficher les valeurs</small></div>
    </div>
    <footer><span><i class="bi bi-shield-check"></i> Données filtrées selon vos droits et votre site</span><a class="btn-primary" data-analytics-detail href="#"><i class="bi bi-list-ul"></i> Voir les données sources</a></footer>
</section>
<script>window.dagrilAnalytics=<?=json_encode(array_values($kpis),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;window.dagrilAnalyticsQuery=<?=json_encode($query,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;window.dagrilAnalyticsBase=<?=json_encode(rtrim(base_url(),'/'),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;</script>
<script src="<?=e(asset_url('js/analytics-dashboard.js'))?>"></script>
<?php endif;?>
