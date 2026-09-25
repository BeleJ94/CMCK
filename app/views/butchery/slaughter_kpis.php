<?php foreach (['Têtes disponibles','Abattages à valider'] as $index=>$title): ?>
<section id="butchery-kpi-<?=$index?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor butchery-kpi-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="butchery-kpi-title-<?=$index?>">
    <header><h2 id="butchery-kpi-title-<?=$index?>"><?=e($title)?></h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header>
    <div class="feed-form-body">
    <?php if ($index===0): ?>
        <p class="butchery-guide"><?=e(number_format($heads,0,',',' '))?> têtes réparties dans <?=count($availableLive)?> lot(s) disponibles.</p>
        <?php if (!$availableLive): ?><p class="butchery-empty">Aucun lot vivant disponible pour un abattage.</p><?php else: ?>
        <table class="enterprise-table" data-search-placeholder="Lot, espèce, provenance…"><thead><tr><th>Lot vivant</th><th>Espèce / provenance</th><th>Reçu le</th><th>Têtes</th><th>Statut</th></tr></thead><tbody>
        <?php foreach($availableLive as $lot): ?><tr><td><?=e($lot['lot_number'])?><small><?=e($lot['batch_number'] ?: '—')?></small></td><td><?=e($lot['species_name'] ?: '—')?><small><?=e($lot['source_site_name'] ?: '—')?></small></td><td data-order="<?=e(strtotime($lot['received_at']))?>"><?=e(date('d/m/Y H:i',strtotime($lot['received_at'])))?></td><td><?=e($lot['available_heads'])?></td><td><?=e($lot['status']==='partially_slaughtered'?'Partiellement abattu':'Disponible')?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
    <?php else: ?>
        <p class="butchery-guide"><?=count($slaughters)?> abattage(s) en attente de validation.</p>
        <?php if (!$slaughters): ?><p class="butchery-empty">Aucun abattage à valider.</p><?php else: ?>
        <table class="enterprise-table" data-search-placeholder="Référence, lot, date…"><thead><tr><th>Date</th><th>Abattage / lot</th><th>Têtes</th><th>Poids brut</th><th>Tare</th><th>Poids net</th><th>Action</th></tr></thead><tbody>
        <?php foreach($slaughters as $entry): ?><tr><td data-order="<?=e(strtotime($entry['slaughtered_at']))?>"><?=e(date('d/m/Y H:i',strtotime($entry['slaughtered_at'])))?></td><td><?=e($entry['slaughter_number'])?><small><?=e($entry['lot_number'])?></small></td><td><?=e($entry['input_heads'])?></td><?php foreach(['gross_weight_kg','tare_weight_kg','net_weight_kg'] as $weight):?><td data-order="<?=e($entry[$weight])?>"><?=e(number_format((float)$entry[$weight],3,',',' '))?> kg</td><?php endforeach;?><td><?=$slaughterValidationButtons[$entry['id']] ?? '—'?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
    <?php endif; ?>
    </div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button></footer>
</section>
<?php endforeach; ?>
