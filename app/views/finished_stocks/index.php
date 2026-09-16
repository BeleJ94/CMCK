<div class="finished-directory" data-finished-directory>
<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-boxes"></i></span>
    <div>
        <p class="section-label">Stock</p>
        <h2>Produits finis</h2>
        <p>Consultez les sacs disponibles, leur répartition par format et leur historique.</p>
    </div>
    <a href="<?= e(base_url('finished-stocks/movements')) ?>" class="page-action"><i class="bi bi-clock-history"></i><span>Mouvements</span></a>
</section>

<?php foreach ($alerts as $alert): ?>
    <div class="app-alert <?= $alert['severity'] === 'danger' ? 'app-alert-error' : 'finished-warning' ?>"><i class="bi <?= $alert['severity'] === 'danger' ? 'bi-exclamation-triangle' : 'bi-info-circle' ?>"></i><span><strong><?= e($alert['title']) ?></strong> - <?= e($alert['message']) ?></span></div>
<?php endforeach; ?>

<nav class="pellet-tabs" aria-label="Stocks de produits finis">
<?php foreach (['products'=>['box-seam','Produits'],'formats'=>['bag','Formats'],'entries'=>['arrow-down-circle','Entrées'],'outputs'=>['arrow-up-circle','Sorties']] as $tab=>$label): ?>
<button type="button" data-finished-tab="<?=e($tab)?>" aria-pressed="<?=$tab==='products'?'true':'false'?>"><i class="bi bi-<?=e($label[0])?>"></i> <?=e($label[1])?></button>
<?php endforeach; ?></nav>
<section data-finished-space="products" aria-label="Stock par produit">
<p class="finished-help">Stock du site sélectionné, ou cumul en vue « Tous les sites ». Les palettes sont symboliques ; les quantités affichées font référence.</p>
<div class="finished-stock-cards">
<?php foreach ($productStock as $row):
    $available=(float)$row['available_kg'];
    $state=$available<=0?'empty':($available<=500?'low':'available');
    $stateLabel=['empty'=>'Rupture','low'=>'Stock faible','available'=>'Disponible'][$state];
    $formats=array_filter($formatStock,fn($format)=>$format['product_code']===$row['code']);
?>
<article class="finished-stock-card finished-stock-<?=e($state)?>">
<header><div><small><?=e($row['code'])?></small><h3><?=e($row['name'])?></h3></div><span class="finished-stock-state"><i class="bi bi-<?=$state==='empty'?'x-circle':($state==='low'?'exclamation-triangle':'check-circle')?>"></i> <?=e($stateLabel)?></span></header>
<div class="finished-stock-overview">
<?php require __DIR__.'/palette.php'; ?>
<div class="finished-stock-quantity"><small>Poids disponible</small><strong><?=e(rtrim(rtrim(number_format($available,3,',',' '),'0'),','))?> <span>kg</span></strong><p><i class="bi bi-bag-check"></i> <?=e(number_format((int)$row['available_bags'],0,',',' '))?> sacs remplis</p></div>
</div>
<div class="finished-stock-breakdown"><h4>Formats disponibles</h4>
<?php if(!$formats): ?><p>Aucun stock par format enregistré.</p><?php else: foreach($formats as $format): ?>
<div><span><?=e($format['format_name'])?></span><strong><?=e(number_format((int)$format['available_bags'],0,',',' '))?> sacs</strong></div>
<?php endforeach; endif; ?></div>
<footer><small><?=$state==='empty'?'Aucun poids disponible.':($state==='low'?'Seuil de vigilance : 500 kg.':'Stock prêt à être distribué.')?></small><button type="button" class="btn-secondary" data-workspace-modal-open="finishedProduct<?=e($row['id'])?>" aria-label="Consulter <?=e($row['name'])?>"><i class="bi bi-eye"></i> Consulter</button></footer>
</article>
<?php endforeach; ?>
<?php if(!$productStock): ?><p class="app-alert">Aucun produit fini à afficher dans ce périmètre.</p><?php endif; ?>
</div></section>

<section data-finished-space="formats" hidden aria-label="Stocks par format">
<?php
$formatProducts=[]; $formatWeights=[];
foreach($formatStock as $format){$formatProducts[$format['product_code']]=$format['product_name'];$formatWeights[(string)$format['weight_kg']]=$format['weight_kg'];}
asort($formatProducts); asort($formatWeights);
?>
<div class="finished-format-filters" role="search" aria-label="Filtrer les formats">
<label>Produit<select data-format-filter="product"><option value="">Tous les produits</option><?php foreach($formatProducts as $code=>$name): ?><option value="<?=e($code)?>"><?=e($name)?></option><?php endforeach; ?></select></label>
<label>Format<select data-format-filter="weight"><option value="">Tous les formats</option><?php foreach($formatWeights as $weight): ?><option value="<?=e($weight)?>"><?=e(rtrim(rtrim(number_format((float)$weight,3,',',' '),'0'),','))?> kg</option><?php endforeach; ?></select></label>
<label>Disponibilité<select data-format-filter="state"><option value="">Tous les états</option><option value="available">Disponible</option><option value="empty">Épuisé</option></select></label>
<button type="button" class="btn-secondary" data-format-reset><i class="bi bi-arrow-counterclockwise"></i> Réinitialiser</button>
</div>
<p class="finished-help">Palettes symboliques : les quantités indiquées sont exactes. Formats enregistrés dans le périmètre du site sélectionné.</p>
<p class="finished-help" data-format-count aria-live="polite"></p>
<div class="finished-stock-cards finished-format-cards">
<?php foreach($formatStock as $format):
$state=(int)$format['available_bags']>0 && (float)$format['available_kg']>0?'available':'empty';
$paletteLabel=rtrim(rtrim(number_format((float)$format['weight_kg'],3,',',' '),'0'),',').' kg';
?>
<article class="finished-stock-card finished-stock-<?=e($state)?>" data-format-card data-product="<?=e($format['product_code'])?>" data-weight="<?=e($format['weight_kg'])?>" data-state="<?=e($state)?>">
<header><div><small><?=e($format['product_name'])?></small><h3><?=e($format['format_name'])?></h3></div><span class="finished-stock-state"><i class="bi bi-<?=$state==='empty'?'x-circle':'check-circle'?>"></i> <?=$state==='empty'?'Épuisé':'Disponible'?></span></header>
<div class="finished-stock-overview"><?php require __DIR__.'/palette.php'; ?>
<div class="finished-stock-quantity"><small>Sacs disponibles</small><strong><?=e(number_format((int)$format['available_bags'],0,',',' '))?> <span>sacs</span></strong><p><?=e(rtrim(rtrim(number_format((float)$format['available_kg'],3,',',' '),'0'),','))?> kg au total</p></div></div>
<footer><small><i class="bi bi-bag"></i> <?=e($paletteLabel)?> par sac</small><small><?=$state==='empty'?'Aucun sac disponible.':'Stock de sacs remplis.'?></small></footer>
</article>
<?php endforeach; unset($paletteLabel); ?>
</div><p class="app-alert" data-format-empty <?=count($formatStock)?'hidden':''?>>Aucun format ne correspond aux filtres sélectionnés.</p>
</section>

<section class="table-panel" data-finished-space="entries" hidden>
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-arrow-down-circle"></i></span><div><h3>Entrées de conditionnement</h3><p>Opérations validées : ces quantités sont historiques, et non le stock restant.</p></div></div>
    <div class="table-responsive">
        <table id="finishedEntriesTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lot</th>
                    <th>Produit</th>
                    <th>Format</th>
                    <th>Sacs</th>
                    <th>Poids</th>
                    <th>Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $row): ?>
                    <tr>
                        <td><?= e($row['packaged_at']) ?></td>
                        <td><strong><?= e($row['batch_number']) ?></strong></td>
                        <td><?= e($row['product_name']) ?></td>
                        <td><?= e($row['format_name']) ?></td>
                        <td><?= e(number_format((int) $row['bags_count'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['total_weight_kg'], 3, ',', ' ')) ?> kg</td>
                        <td><?= e($row['agent_name'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="table-panel" data-finished-space="outputs" hidden>
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-arrow-up-circle"></i></span><div><h3>Sorties de distribution</h3><p>Distributions validées vers les bénéficiaires et clients.</p></div></div>
    <div class="table-responsive">
        <table id="finishedOutputsTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Destinataire</th>
                    <th>Produit</th>
                    <th>Format</th>
                    <th>Sacs</th>
                    <th>Poids</th>
                    <th>Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($outputs as $row): ?>
                    <tr>
                        <td><?= e($row['distributed_at']) ?></td>
                        <td><strong><?= e($row['recipient_name']) ?></strong></td>
                        <td><?= e($row['product_name']) ?></td>
                        <td><?= e($row['format_name']) ?></td>
                        <td><?= e(number_format((int) $row['quantity_bags'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['total_weight_kg'], 3, ',', ' ')) ?> kg</td>
                        <td><?= e($row['agent_name'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($productStock as $product): $formats=array_filter($formatStock, fn($format)=>$format['product_code']===$product['code']); ?>
<section id="finishedProduct<?=e($product['id'])?>" class="entity-modal workspace-entity-modal feed-editor finished-detail" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="finishedTitle<?=e($product['id'])?>">
<header><div><p class="page-kicker">STOCK PRODUIT FINI · <?=e($product['code'])?></p><h2 id="finishedTitle<?=e($product['id'])?>"><?=e($product['name'])?></h2></div><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header>
<div class="feed-form-body">
<div class="finished-balance"><i class="bi bi-box-seam"></i><div><small>Disponible actuellement</small><strong><?=e(number_format((float)$product['available_kg'],3,',',' '))?> kg</strong><span><?=e(number_format((int)$product['available_bags'],0,',',' '))?> sacs remplis</span></div></div>
<p class="finished-help">Les quantités correspondent au site sélectionné dans l’application. En vue « Tous les sites », elles sont cumulées.</p>
<h3>Répartition par format</h3>
<?php if (!$formats): ?><p class="app-alert">Aucun stock par format enregistré dans ce périmètre.</p><?php else: ?>
<div class="finished-formats"><?php foreach ($formats as $format): ?><article><i class="bi bi-bag"></i><div><strong><?=e($format['format_name'])?></strong><small><?=e(number_format((float)$format['weight_kg'],3,',',' '))?> kg par sac</small></div><div><strong><?=e(number_format((int)$format['available_bags'],0,',',' '))?> sacs</strong><small><?=e(number_format((float)$format['available_kg'],3,',',' '))?> kg disponibles</small></div></article><?php endforeach; ?></div>
<?php endif; ?>
<h3>Historique cumulé</h3><dl class="finished-totals"><div><dt>Entrées</dt><dd><?=e(number_format((float)$product['entries_kg'],3,',',' '))?> kg</dd></div><div><dt>Sorties</dt><dd><?=e(number_format((float)$product['outputs_kg'],3,',',' '))?> kg</dd></div></dl>
<p class="finished-help">Stock faible : de plus de 0 à 500 kg. Rupture : aucun poids disponible. Ces seuils servent de repères et ne représentent pas une capacité de stockage.</p>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button></footer>
</section>
<?php endforeach; ?>
</div>
