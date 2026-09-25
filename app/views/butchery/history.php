<?php
$isReceiptHistory = $section === 'receipts';
$historyLabels = ['draft'=>'Brouillon','sanitary_pending'=>'À contrôler','accepted'=>'Acceptée','rejected'=>'Refusée','cancelled'=>'Annulée','submitted'=>'À valider','validated'=>'Validé'];
$historyDate = static function ($value) { return $value ? date('d/m/Y H:i', strtotime($value)) : '—'; };
$historyKg = static function ($value) { return $value !== null ? number_format((float)$value, 3, ',', ' ') . ' kg' : '—'; };
$historyConformity = ['conform'=>'Conforme','non_conform'=>'Non conforme'];
?>
<section class="table-panel butchery-history" aria-labelledby="butchery-history-title">
    <header class="butchery-history-heading">
        <div><h3 id="butchery-history-title">Historique des <?= $isReceiptHistory ? 'réceptions' : 'abattages' ?></h3><p>Tous les statuts · Du plus récent au plus ancien</p></div>
        <span class="butchery-status"><?=count($history)?> opération<?=count($history) > 1 ? 's' : ''?></span>
    </header>
    <?php if (!$history): ?>
        <p class="butchery-empty">Aucun<?= $isReceiptHistory ? 'e réception enregistrée' : ' abattage enregistré' ?>. Les opérations apparaîtront ici avec leur statut et leurs détails.</p>
    <?php else: ?>
    <table class="enterprise-table" data-butchery-history="<?=e($section)?>" data-search-placeholder="Rechercher : référence, origine, date, statut…" aria-label="Historique des <?= $isReceiptHistory ? 'réceptions' : 'abattages' ?>">
        <thead><tr><th>Date</th><th>Référence</th><th><?= $isReceiptHistory ? 'Origine' : 'Lot / matière' ?></th><th><?= $isReceiptHistory ? 'Quantité' : 'Têtes / poids net' ?></th><th>Statut</th><th>Détail</th></tr></thead>
        <tbody>
        <?php foreach ($history as $entry):
            $reference = $isReceiptHistory ? $entry['receipt_number'] : $entry['slaughter_number'];
            $date = $isReceiptHistory ? $entry['received_at'] : $entry['slaughtered_at'];
            $status = $entry['status'];
            $label = $historyLabels[$status] ?? $status;
            if (!$isReceiptHistory && $status === 'cancelled') $label = 'Annulé';
            $tone = in_array($status,['accepted','validated'],true) ? 'success' : (in_array($status,['rejected','cancelled'],true) ? 'muted' : 'pending');
            $origin = $isReceiptHistory ? ($entry['source_type'] === 'internal_btr' ? ($entry['source_site_name'] ?: 'Élevage') : ($entry['supplier_name'] ?: 'Fournisseur')) : $entry['lot_number'];
            $quantity = $isReceiptHistory ? ($entry['received_unit'] === 'head' ? number_format((int)$entry['received_heads'],0,',',' ').' têtes' : $historyKg($entry['received_weight_kg'])) : $historyKg($entry['net_weight_kg']);
            $detailId = 'butchery-history-' . $section . '-' . (int)$entry['id'];
            $detail = ['Référence'=>$reference,'Statut'=>$label,'Date'=>$historyDate($date),'Enregistré par'=>$entry['creator_name'] ?: '—'];
            if ($isReceiptHistory) {
                $detail += ['Origine'=>$origin,'Type de réception'=>$entry['source_type']==='internal_btr'?'Transfert d’élevage':'Fournisseur externe','Référence source'=>$entry['source_reference'] ?: '—','Lot d’élevage'=>$entry['batch_number'] ?: '—','Espèce'=>$entry['species_name'] ?: '—','Quantité reçue'=>$quantity,'Coût unitaire (par '.($entry['received_unit']==='head'?'tête':'kg').')'=>number_format((float)$entry['unit_cost'],4,',',' '),'Contrôle effectué le'=>$historyDate($entry['controlled_at']),'Contrôlé par'=>$entry['inspector_name'] ?: '—','Décision sanitaire'=>isset($entry['decision']) ? ($entry['decision']==='accept'?'Acceptée':'Refusée') : 'Non effectué','Température'=>$entry['temperature_c'] !== null ? $entry['temperature_c'].' °C' : '—','Contrôle visuel'=>$historyConformity[$entry['visual_status']] ?? '—','Documents'=>$historyConformity[$entry['document_status']] ?? '—','Observations'=>$entry['notes'] ?: '—'];
            } else {
                $detail += ['Lot vivant'=>$entry['lot_number'],'Réception source'=>$entry['receipt_number'] ?: '—','Matière obtenue'=>$entry['item_name'] ?: '—','Animaux abattus'=>number_format((int)$entry['input_heads'],0,',',' ').' têtes','Poids brut'=>$historyKg($entry['gross_weight_kg']),'Tare'=>$historyKg($entry['tare_weight_kg']),'Poids net'=>$quantity,'Validé le'=>$historyDate($entry['validated_at']),'Validé par'=>$entry['validator_name'] ?: '—'];
            }
            $detail += ['Lot créé'=>$entry['resulting_lot'] ?: '—','Date limite de consommation'=>$entry['expiry_date'] ? date('d/m/Y',strtotime($entry['expiry_date'])) : '—'];
            ob_start();
        ?>
        <section id="<?=e($detailId)?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?=e($detailId)?>-title">
            <header><h2 id="<?=e($detailId)?>-title"><?= $isReceiptHistory ? 'Détail de la réception' : 'Détail de l’abattage' ?></h2><button class="modal-close" type="button" data-workspace-modal-close aria-label="Fermer">×</button></header>
            <div class="feed-form-body"><?php if(!$isReceiptHistory): require __DIR__.'/slaughter_detail.php'; else: ?><dl class="butchery-history-details"><?php foreach ($detail as $name=>$value): ?><div><dt><?=e($name)?></dt><dd><?=e($value)?></dd></div><?php endforeach; ?></dl><?php endif; ?></div>
            <footer><button class="btn-secondary" type="button" data-workspace-modal-close>Fermer</button></footer>
        </section>
        <?php $butcheryModals[] = ob_get_clean(); ?>
        <tr data-status="<?=e($status)?>" data-type="<?=e($isReceiptHistory ? $entry['source_type'] : '')?>" data-date="<?=e(substr($date,0,10))?>">
            <td data-order="<?=e(strtotime($date))?>"><?=e($historyDate($date))?></td>
            <td><strong><?=e($reference)?></strong></td>
            <td><?=e($origin)?><small><?=e($isReceiptHistory ? ($entry['source_type']==='internal_btr'?'Élevage':'Fournisseur') : $entry['item_name'])?></small></td>
            <td data-order="<?=e($isReceiptHistory && $entry['received_unit']==='head' ? $entry['received_heads'] : ($isReceiptHistory ? $entry['received_weight_kg'] : $entry['net_weight_kg']))?>"><?=e($quantity)?><?php if(!$isReceiptHistory):?><small><?=e($entry['input_heads'])?> têtes</small><?php endif;?></td>
            <td><span class="butchery-status butchery-status-<?=e($tone)?>"><?=e($label)?></span></td>
            <td><button class="btn-secondary" type="button" data-workspace-modal-open="<?=e($detailId)?>" aria-label="<?=e('Consulter '.$reference)?>"><i class="bi bi-eye" aria-hidden="true"></i> Consulter</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
