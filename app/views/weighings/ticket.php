<?php
$ticketStatus=['pending'=>'Sortie à valider','validated'=>'Livraison validée','rejected'=>'Livraison refusée','return_pending'=>'Retour à organiser','returned'=>'Marchandise retournée','cancelled'=>'Pesée annulée'];
$pendingTicket=$weighing['status']==='pending';
if($pendingTicket&&empty($weighing['exit_prepared_at']))$ticketStatus['pending']='Pesée d’entrée enregistrée';
$ticketWeight=function($value){return $value===null?'—':rtrim(rtrim(number_format((float)$value,3,',',' '),'0'),',').' kg';};
$ticketDate=function($value){return $value?date('d/m/Y · H:i',strtotime($value)):'—';};
$ticketConformity=['pending'=>'À contrôler','conform'=>'Conforme','compliant'=>'Conforme','non_conform'=>'Non conforme','non_compliant'=>'Non conforme','accepted_with_variance'=>'Acceptée avec écart','rejected'=>'Refusée'];
?>
<div class="weighing-ticket-workspace">
<?php if(empty($pdfMode)):?>
<header class="weighing-entry-heading print-hidden"><div><p class="page-kicker">PONT-BASCULE · DOCUMENT</p><h2>Ticket de pesée</h2><p>Prévisualisez le ticket pour l’imprimer ou l’enregistrer en PDF.</p></div><div class="weighing-ticket-actions"><a class="btn-secondary" href="<?=e(base_url('weighings'))?>">Toutes les pesées</a><button type="button" class="btn-primary" data-ticket-preview-open aria-haspopup="dialog" aria-controls="ticketPreview"><i class="bi bi-file-earmark-richtext" aria-hidden="true"></i> Prévisualiser le ticket</button></div></header>
<?php if(!empty($success)):?><div class="app-alert app-alert-success print-hidden" role="status"><?=e($success)?></div><?php endif;?>
<?php if(!empty($error)):?><div class="app-alert app-alert-error print-hidden" role="alert"><?=e($error)?></div><?php endif;?>
<?php if($pendingTicket):?><div class="ticket-next-step print-hidden"><div><strong>La réception reste à finaliser</strong><p>Ce ticket reprend la pesée d’entrée. Le silo sera crédité après validation d’une livraison acceptée.</p></div><a class="btn-primary" href="<?=e(base_url('weighings/'.$weighing['id'].'/exit'))?>"><?=!empty($weighing['exit_prepared_at'])?'Examiner la sortie':'Compléter la pesée de sortie'?></a></div><?php endif;?>
<?php if(!empty($weighing['return_status'])&&in_array($weighing['return_status'],['planned','dispatched','received'],true)):?>
<form method="post" action="<?=e(base_url('weighings/'.$weighing['id'].'/return'))?>" class="ticket-next-step print-hidden"><?=csrf_field()?>
<div><strong>Suivi du retour</strong><p>Référence : <?=e($weighing['return_number']?:'—')?></p></div>
<input type="hidden" name="return_action" value="<?=$weighing['return_status']==='planned'?'dispatch':($weighing['return_status']==='dispatched'?'receive':'close')?>">
<button class="btn-primary"><?=e($weighing['return_status']==='planned'?'Expédier le retour':($weighing['return_status']==='dispatched'?'Confirmer réception origine':'Clôturer le retour'))?></button></form>
<?php endif;endif;?>
<section class="ticket-card compact-weighing-ticket">
<div class="ticket-header"><div><p>DAGRIL · PONT-BASCULE</p><h2>Ticket de pesée</h2><span class="ticket-state"><?=e($ticketStatus[$weighing['status']]??$weighing['status'])?></span></div><div class="ticket-reference"><strong><?=e($weighing['official_document_number']?:$weighing['reference'])?></strong><?php if(!empty($weighing['official_document_number'])):?><small>Pesée : <?=e($weighing['reference'])?></small><?php endif;?><small>Entrée : <?=e($ticketDate($weighing['weighed_at']))?></small></div></div>
<div class="ticket-weights">
<div><span>Poids brut</span><strong><?=e($ticketWeight($weighing['poids_brut']))?></strong></div>
<div><span>Tare du camion</span><strong><?=e($pendingTicket?'À valider':$ticketWeight($weighing['poids_tare']))?></strong></div>
<div class="ticket-weight-net"><span>Poids net</span><strong><?=e($pendingTicket?'À valider':$ticketWeight($weighing['poids_net']))?></strong></div>
</div>
<h3 class="ticket-section-title">01 <span>Transport et livraison</span></h3>
<div class="ticket-grid"><div><span>Bon de transport</span><strong><?=e($weighing['bt_number']?:'—')?></strong></div><div><span>Camion</span><strong><?=e($weighing['plate_number'])?></strong></div><div><span>Chauffeur</span><strong><?=e($weighing['driver_name']?:'—')?></strong></div></div>
<div class="ticket-grid"><div><span><?=($weighing['origin_type']??'')==='internal_farm'?'Ferme d’origine':'Fournisseur'?></span><strong><?=e(($weighing['origin_site_name']??'')?:$weighing['supplier_name'])?></strong></div><div><span>Produit</span><strong><?=e($weighing['product_name'])?></strong></div><div><span>Silo de destination</span><strong><?=e(($weighing['silo_name']?:'—').(!empty($weighing['silo_code'])?' ('.$weighing['silo_code'].')':''))?></strong></div></div>
<div class="ticket-grid"><div><span>Quantité expédiée</span><strong><?=e($ticketWeight($weighing['shipped_quantity_kg']))?></strong></div><div><span>Écart de poids</span><strong><?=e($pendingTicket?'À valider':$ticketWeight($weighing['weight_variance_kg']))?></strong></div><div><span>Trajet</span><strong><?=e($weighing['route_description']?:'—')?></strong></div></div>
<?php if(!empty($weighing['toll_amount'])||!empty($weighing['toll_details'])):?><p class="ticket-detail-note">Péages : <?=e(number_format((float)$weighing['toll_amount'],2,',',' '))?><?=!empty($weighing['toll_details'])?' · '.e($weighing['toll_details']):''?></p><?php endif;?>
<h3 class="ticket-section-title">02 <span>Qualité et validation</span></h3>
<div class="ticket-grid"><div><span>Humidité</span><strong><?=e($weighing['humidity_percent']!==null?$weighing['humidity_percent'].' %':'À contrôler')?></strong></div><div><span>Impuretés</span><strong><?=e($weighing['impurities_percent']!==null?$weighing['impurities_percent'].' %':'À contrôler')?></strong></div><div><span>Conformité</span><strong><?=e($ticketConformity[$weighing['conformity_status']]??($weighing['conformity_status']?:'À contrôler'))?></strong></div></div>
<?php if(!empty($weighing['nc_reference'])||!empty($weighing['return_number'])):?><p class="ticket-detail-note">Non-conformité : <?=e($weighing['nc_reference']?:'—')?> · Retour : <?=e($weighing['return_number']?:'—')?></p><?php endif;?>
<?php if(!empty($weighing['quality_notes'])):?><p class="ticket-detail-note"><strong>Observations</strong> <?=e($weighing['quality_notes'])?></p><?php endif;?>
<div class="ticket-footer"><div><span>Agent d’entrée</span><strong><?=e($weighing['agent_name']?:'—')?></strong></div><div><span>Validé par</span><strong><?=e($weighing['validator_name']?:'En attente')?></strong></div><div><span>Date du contrôle qualité</span><strong><?=e($ticketDate($weighing['quality_checked_at']??null))?></strong></div></div>
</section>
</div>

<?php if(empty($pdfMode)):?>
<dialog id="ticketPreview" class="ticket-preview-modal print-hidden" aria-labelledby="ticketPreviewTitle" data-ticket-preview>
    <div class="ticket-preview-header">
        <h2 id="ticketPreviewTitle">Aperçu du ticket de pesée</h2>
        <div class="weighing-ticket-actions">
            <a class="btn-secondary" href="<?=e(base_url('weighings/'.$weighing['id'].'/ticket?export=pdf'))?>" download><i class="bi bi-download" aria-hidden="true"></i> Télécharger</a>
            <button type="button" class="modal-close" data-ticket-preview-close aria-label="Fermer l’aperçu" autofocus><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
    </div>
    <iframe class="ticket-preview-frame" title="Document PDF du ticket de pesée" data-src="<?=e(base_url('weighings/'.$weighing['id'].'/ticket?export=pdf&preview=1'))?>"></iframe>
</dialog>
<?php endif;?>
