<section class="dashboard-hero print-hidden">
    <span class="hero-icon"><i class="bi bi-printer"></i></span>
    <div>
        <p class="section-label">Ticket</p>
        <h2>Ticket de pesee</h2>
        <p>Document imprimable pour la livraison <?= e($weighing['reference']) ?>.</p>
    </div>
    <a class="page-action" href="<?= e(base_url('weighings/' . $weighing['id'] . '/ticket?export=pdf')) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i><span>PDF</span></a>
    <button type="button" class="page-action" onclick="window.print()"><i class="bi bi-printer"></i><span>Imprimer</span></button>
</section>
<?php if(!empty($weighing['return_status'])&&in_array($weighing['return_status'],['planned','dispatched','received'],true)):?>
<form method="post" action="<?=e(base_url('weighings/'.$weighing['id'].'/return'))?>" class="form-actions print-hidden"><?=csrf_field()?>
<input type="hidden" name="return_action" value="<?=$weighing['return_status']==='planned'?'dispatch':($weighing['return_status']==='dispatched'?'receive':'close')?>">
<button class="btn-primary"><i class="bi bi-arrow-return-left"></i><span><?=e($weighing['return_status']==='planned'?'Expédier le retour':($weighing['return_status']==='dispatched'?'Confirmer réception origine':'Clôturer le retour'))?></span></button></form>
<?php endif;?>

<section class="ticket-card">
    <div class="ticket-header">
        <div>
            <p>DAGRIL ERP</p>
            <h2>Ticket de pesee</h2>
        </div>
        <strong><?= e($weighing['official_document_number'] ?: $weighing['reference']) ?></strong>
        <?php if (!empty($weighing['official_document_number'])): ?><small>Référence pesée : <?= e($weighing['reference']) ?></small><?php endif; ?>
    </div>

    <div class="ticket-grid">
        <div><span>Origine</span><strong><?=e(($weighing['origin_type']??'')==='internal_farm'?'Interne — '.($weighing['origin_site_name']?:'-'):'Externe — '.$weighing['supplier_name'])?></strong></div>
        <div><span>BT</span><strong><?=e($weighing['bt_number']?:'-')?></strong></div>
        <div><span>Fournisseur</span><strong><?= e($weighing['supplier_name']) ?></strong></div>
        <div><span>Camion</span><strong><?= e($weighing['plate_number']) ?></strong></div>
        <div><span>Chauffeur</span><strong><?= e($weighing['driver_name'] ?: '-') ?></strong></div>
        <div><span>Produit</span><strong><?= e($weighing['product_name']) ?></strong></div>
        <div><span>Date entree</span><strong><?= e($weighing['weighed_at']) ?></strong></div>
        <div><span>Silo destination</span><strong><?= e(($weighing['silo_name'] ?: '-') . ($weighing['silo_code'] ? ' (' . $weighing['silo_code'] . ')' : '')) ?></strong></div>
        <div><span>Trajet / péages</span><strong><?=e(($weighing['route_description']?:'-').' / '.number_format((float)$weighing['toll_amount'],2,',',' '))?></strong></div>
    </div>

    <div class="ticket-weights">
        <div><span>Poids brut</span><strong><?= e(number_format((float) $weighing['poids_brut'], 0, ',', ' ')) ?> kg</strong></div>
        <div><span>Poids tare</span><strong><?= e(number_format((float) $weighing['poids_tare'], 0, ',', ' ')) ?> kg</strong></div>
        <div><span>Poids net</span><strong><?= e(number_format((float) $weighing['poids_net'], 0, ',', ' ')) ?> kg</strong></div>
        <div><span>Quantité expédiée</span><strong><?=e(number_format((float)$weighing['shipped_quantity_kg'],0,',',' '))?> kg</strong></div>
        <div><span>Écart</span><strong><?=e(number_format((float)$weighing['weight_variance_kg'],0,',',' '))?> kg</strong></div>
    </div>

    <div class="ticket-grid"><div><span>Humidité</span><strong><?=e($weighing['humidity_percent']!==null?$weighing['humidity_percent'].' %':'-')?></strong></div><div><span>Impuretés</span><strong><?=e($weighing['impurities_percent']!==null?$weighing['impurities_percent'].' %':'-')?></strong></div><div><span>Conformité</span><strong><?=e($weighing['conformity_status'])?></strong></div><div><span>NC / retour</span><strong><?=e($weighing['nc_reference']?:($weighing['return_number']?:'-'))?></strong></div></div>

    <div class="ticket-footer">
        <div><span>Agent entree</span><strong><?= e($weighing['agent_name'] ?: '-') ?></strong></div>
        <div><span>Validation</span><strong><?= e($weighing['validator_name'] ?: '-') ?></strong></div>
        <div><span>Statut</span><strong><?= $weighing['status'] === 'pending' ? 'En attente de dechargement' : e($weighing['status']) ?></strong></div>
    </div>
</section>
