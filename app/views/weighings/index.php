<?php
$weighingLabels=['pending'=>'À décharger','validated'=>'Validée','rejected'=>'Refusée','return_pending'=>'Retour à organiser','returned'=>'Retournée','cancelled'=>'Annulée'];
$validatedCount=0;$attentionCount=0;
foreach($weighings as$item){if($item['status']==='validated')$validatedCount++;if(in_array($item['status'],['rejected','return_pending'],true))$attentionCount++;}
$formatWeight=function($value){return $value===null?'—':rtrim(rtrim(number_format((float)$value,3,',',' '),'0'),',').' kg';};
?>
<div class="weighing-workspace">
<section class="weighing-heading">
    <div><p class="page-kicker">PONT-BASCULE</p><h2>Suivi des pesées</h2><p>Réceptionnez, pesez et validez les arrivages.</p></div>
    <div class="weighing-primary-actions">
        <a href="<?=e(base_url('weighings/entry'))?>" class="btn-primary"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Réceptionner un BT</a>
        <a href="<?=e(base_url('weighings/exit'))?>" class="btn-secondary"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Pesée de sortie<?php if(count($pending)):?><span class="weighing-action-count"><?=e(count($pending))?></span><?php endif;?></a>
    </div>
</section>
<?php if(!empty($success)):?><div class="app-alert app-alert-success" role="status"><?=e($success)?></div><?php endif;?>
<?php if(!empty($error)):?><div class="app-alert app-alert-error" role="alert"><?=e($error)?></div><?php endif;?>
<section class="weighing-summary" aria-label="Synthèse des pesées">
    <a href="<?=e(base_url('weighings/exit'))?>" class="weighing-summary-pending"><i class="bi bi-hourglass-split" aria-hidden="true"></i><strong><?=e(count($pending))?></strong><span>À décharger / peser en sortie</span><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    <div><strong><?=e($validatedCount)?></strong><span>Validées</span></div>
    <div><strong><?=e($attentionCount)?></strong><span>Refusées / retours à organiser</span></div>
    <div><strong><?=e(count($weighings))?></strong><span>Pesées au total</span></div>
</section>
<div class="weighing-journey"><p><span>1. Entrée : poids brut</span><i class="bi bi-chevron-right" aria-hidden="true"></i><span>2. Déchargement</span><i class="bi bi-chevron-right" aria-hidden="true"></i><span>3. Sortie : tare et validation</span></p><a href="<?=e(base_url('weighbridge-transports'))?>">Enregistrer un transport fournisseur <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
<section class="table-panel weighing-history">
    <div class="panel-heading"><div><h3>Historique des pesées</h3><p>Retrouvez un camion, une origine ou une pesée et poursuivez son traitement.</p></div></div>
    <div class="table-responsive">
        <table id="weighingsTable" class="enterprise-table" data-search-placeholder="Référence, camion, origine, statut…">
            <thead><tr><th>Pesée / entrée</th><th>Camion / origine</th><th>Produit</th><th>Brut / tare</th><th>Poids net</th><th>Statut</th><th data-sortable="false">Actions</th></tr></thead>
            <tbody>
            <?php foreach($weighings as$weighing):?>
                <tr>
                    <td><strong class="weighing-reference"><?=e($weighing['reference'])?></strong><small class="weighing-cell-note"><?=e(date('d/m/Y · H:i',strtotime($weighing['weighed_at'])))?></small></td>
                    <td><strong><?=e($weighing['plate_number'])?></strong><small class="weighing-cell-note weighing-origin"><?=e($weighing['supplier_name'])?></small></td>
                    <td><?=e($weighing['product_name'])?></td>
                    <td class="weighing-mass"><span><small>Brut</small> <?=e($formatWeight($weighing['poids_brut']))?></span><span><small>Tare</small> <?=e($weighing['status']==='pending'?'À mesurer':$formatWeight($weighing['poids_tare']))?></span></td>
                    <td class="weighing-net"><?=e($weighing['status']==='pending'?'À calculer':$formatWeight($weighing['poids_net']))?></td>
                    <td><span class="status-badge status-<?=e($weighing['status'])?>"><?=e($weighing['status']==='pending'&&!empty($weighing['exit_prepared_at'])?'Sortie à valider':($weighingLabels[$weighing['status']]??$weighing['status']))?></span></td>
                    <td><div class="table-actions">
                        <?php if($weighing['status']==='pending'):?><a class="btn-table-action" href="<?=e(base_url('weighings/'.$weighing['id'].'/exit'))?>" aria-label="<?=e('Pesée de sortie pour '.$weighing['reference'])?>"><?=!empty($weighing['exit_prepared_at'])?'Examiner la sortie':'Pesée de sortie'?></a><?php endif;?>
                        <a class="weighing-ticket" href="<?=e(base_url('weighings/'.$weighing['id'].'/ticket'))?>" aria-label="<?=e('Ticket de '.$weighing['reference'])?>"><i class="bi bi-printer" aria-hidden="true"></i> Ticket</a>
                    </div></td>
                </tr>
            <?php endforeach;?>
            <?php if(!$weighings):?><tr><td colspan="7"><div class="weighing-empty"><i class="bi bi-truck" aria-hidden="true"></i><strong>Aucune pesée enregistrée</strong><p>Commencez par réceptionner un BT en transit à l’arrivée du camion.</p><a href="<?=e(base_url('weighings/entry'))?>" class="btn-primary">Réceptionner un BT</a></div></td></tr><?php endif;?>
            </tbody>
        </table>
    </div>
</section>
</div>
