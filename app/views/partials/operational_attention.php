<?php
$attentionItems=[];
try {
    require_once dirname(__DIR__,2).'/services/OperationalAttentionService.php';
    $attentionItems=(new OperationalAttentionService())->items();
} catch(Throwable $exception) { error_log('DAGRIL: operational attention counts unavailable.'); }
$attentionTotal=array_sum(array_column($attentionItems,'count'));
?>
<div class="ui-attention" data-ui-attention>
    <button type="button" class="topbar-icon-button ui-attention-toggle" data-ui-attention-toggle aria-expanded="false" aria-controls="operationalAttentionPanel" aria-label="À suivre : <?=e($attentionTotal)?> élément(s)" title="À suivre sur ce site">
        <i class="bi bi-bell" aria-hidden="true"></i><span class="ui-attention-count" data-ui-attention-count <?=!$attentionTotal?'hidden':''?>><?=e($attentionTotal)?></span>
    </button>
    <section id="operationalAttentionPanel" class="ui-attention-panel" data-ui-attention-panel aria-label="Éléments à suivre sur le site courant" hidden>
        <header class="ui-attention-heading"><div><h2>À suivre</h2><p>Dans votre périmètre de travail</p></div><button type="button" class="icon-button" data-ui-attention-close title="Fermer les notifications" aria-label="Fermer les notifications"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
        <?php if($attentionItems): ?><ul class="ui-attention-list">
        <?php foreach($attentionItems as $item): ?><li><a href="<?=e(base_url($item['path']))?>"><span class="ui-attention-icon ui-status--<?=e($item['severity'])?>"><i class="bi <?=e($item['icon'])?>" aria-hidden="true"></i></span><span class="ui-attention-copy"><strong><?=e($item['label'])?></strong><small><?=e($item['count'])?> élément(s) · Ouvrir la liste</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a></li><?php endforeach; ?>
        </ul><?php else: ?><p class="ui-attention-empty"><i class="bi bi-check-circle" aria-hidden="true"></i>Aucun élément à traiter dans ce périmètre.</p><?php endif; ?>
        <?php if(Auth::can('alerts','read')): ?><a class="ui-attention-footer" href="<?=e(base_url('alerts'))?>">Consulter le registre des alertes<i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
    </section>
</div>
