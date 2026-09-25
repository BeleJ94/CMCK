<?php
require __DIR__.'/labels.php';
$statusOptions=[];foreach($transferLabels as $key=>$label)$statusOptions[]=[$key,$label];
$groups=[['Tous les transferts',[]],['À préparer',['draft','submitted','approved','reserved']],['En acheminement',['partially_shipped','in_transit','partially_received']],['Qualité / retours',['quality_control','partially_accepted','rejected','return_pending']]];
$groupRows=[];$totals=[];
foreach($transfers as $t){foreach($groups as $key=>$group)if(!$group[1]||in_array($t['status'],$group[1],true))$groupRows[$key][]=$t;
 $totals[$t['id']]=array_fill_keys(['requested_bags','shipped_bags','received_bags','accepted_bags','rejected_bags'],0);
 foreach($transferItems[$t['id']]??[] as $line)foreach($totals[$t['id']] as $key=>$value)$totals[$t['id']][$key]+=(int)$line[$key];
}
$transferDate=static function($value){return $value?date('d/m/Y H:i',strtotime($value)):'—';};
?>
<div class="butchery-workspace transfer-workspace">
<header class="butchery-heading"><span class="butchery-heading-icon"><i class="bi bi-truck" aria-hidden="true"></i></span><div><p>Logistique</p><h2>Transferts inter-sites</h2></div></header>
<?php if($success):?><div class="app-alert app-alert-success"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="app-alert app-alert-error"><?=e($error)?></div><?php endif;?>
<p class="butchery-guide">Suivez les demandes, les expéditions et les réceptions des sites accessibles dans le contexte sélectionné.</p>
<div class="butchery-metrics"><?php foreach($groups as $key=>$group):?><button type="button" class="butchery-kpi" data-workspace-modal-open="transfer-kpi-<?=$key?>" aria-haspopup="dialog"><strong><?=count($groupRows[$key]??[])?></strong><span><?=e($group[0])?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></button><?php endforeach;?></div>
<section class="form-panel butchery-slaughter-actions"><div class="butchery-slaughter-toolbar"><h3>Demandes et suivi</h3><?php if(Auth::can('transfers','create')):?><button type="button" class="btn-primary" data-workspace-modal-open="transfer-create"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouvelle demande</button><?php endif;?></div></section>
<section class="table-panel butchery-history" data-transfer-directory><header class="butchery-history-heading"><div><h3>Historique des transferts</h3><p>Quantités en sacs · Période filtrée sur la date de demande</p></div></header><?php $transferRows=$transfers;$withFilters=true;require __DIR__.'/table.php';?></section>
<?php foreach($groups as $key=>$group):?>
<section id="transfer-kpi-<?=$key?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor butchery-kpi-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="transfer-kpi-title-<?=$key?>"><header><h2 id="transfer-kpi-title-<?=$key?>"><?=e($group[0])?></h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><div class="feed-form-body"><?php $transferRows=$groupRows[$key]??[];$withFilters=false;require __DIR__.'/table.php';?></div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button></footer></section>
<?php endforeach;?>
<?php foreach($transfers as $t):?>
<section id="transfer-detail-<?=e($t['id'])?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor butchery-kpi-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="transfer-detail-title-<?=e($t['id'])?>"><header><h2 id="transfer-detail-title-<?=e($t['id'])?>">Détail du transfert</h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><div class="feed-form-body">
<div class="butchery-detail-banner"><div><small><?=$t['transfer_type']==='internal'?'Transfert interne':'Transfert inter-site'?></small><strong><?=e($t['transfer_number'])?></strong></div><span class="butchery-status"><?=e($transferLabels[$t['status']]??$t['status'])?></span></div>
<dl class="butchery-history-details"><?php foreach(['Origine'=>$t['source_code'].' · '.$t['source_name'],'Destination'=>$t['destination_code'].' · '.$t['destination_name'],'Demandé le'=>$transferDate($t['requested_at']),'Créé par'=>$t['creator_name'],'Soumis le'=>$transferDate($t['submitted_at']),'Approuvé par'=>$t['approver_name']?:'—','Approuvé le'=>$transferDate($t['approved_at']),'Clôturé le'=>$transferDate($t['closed_at'])] as $label=>$value):?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?></dl>
<div class="table-responsive"><table class="enterprise-table" data-datatable="false"><thead><tr><th>Produit / format</th><th>Demandés</th><th>Expédiés</th><th>Reçus</th><th>Acceptés</th><th>Refusés</th></tr></thead><tbody><?php foreach($transferItems[$t['id']]??[] as $line):?><tr><td><strong><?=e($line['product_name'])?></strong><small><?=e($line['format_name'])?></small></td><?php foreach(['requested_bags','shipped_bags','received_bags','accepted_bags','rejected_bags'] as $field):?><td><?=e(number_format((int)$line[$field],0,',',' '))?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div>
<p class="butchery-detail-note">Quantités en sacs. Les reçus comprennent les sacs acceptés et refusés. Le dossier regroupe les expéditions, réceptions et retours à traiter.</p>
<?php if(!empty($t['notes'])):?><div class="transfer-notes"><strong>Notes</strong><p><?=nl2br(e($t['notes']))?></p></div><?php endif;?>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><a class="btn-primary" href="<?=e(base_url('transfers/'.$t['id']))?>">Ouvrir le dossier</a></footer></section>
<?php endforeach;?>
</div>

<?php if(Auth::can('transfers','create')):$sites=$references['sites']??[];$stocks=$references['stocks']??[];?>
<section id="transfer-create" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="transfer-create-title"><header><h2 id="transfer-create-title">Nouvelle demande de transfert</h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><?php require __DIR__.'/form.php';?></section>
<?php endif;?>
