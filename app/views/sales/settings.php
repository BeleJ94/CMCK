<?php
$flowViews=dirname(__DIR__).'/porcine_flow';$returnPath='sales/settings';$drawerKicker='DISTRIBUTION · TARIFS ET TAUX';
$money=static fn($v,$digits=2)=>number_format((float)$v,$digits,',',' ');$stamp=static fn($at)=>date('d/m/Y H:i',strtotime($at));
$current=$ready&&$rate?(float)$rate['rate']:null;
$cdf=static fn($usd)=>$current!==null?$money((float)$usd*$current,0).' CDF':'—';
?>
<div class="sales-settings-page" data-sales-settings>
<header class="ui-page-header"><span class="ui-page-header__icon"><i class="bi bi-currency-exchange" aria-hidden="true"></i></span><div class="ui-page-header__content"><p class="section-label">DISTRIBUTION · PARAMÈTRES</p><h2>Tarifs et taux</h2><p>Taux du jour USD → CDF, tarifs des clients entreprises et du comptoir, et dépôts du bureau principal.</p></div><div class="ui-page-header__actions"><a class="btn-secondary" href="<?=e(base_url('customers'))?>"><i class="bi bi-person-vcard" aria-hidden="true"></i> Clients</a></div></header>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?><?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if(!$ready): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Les tarifs et le taux ne sont pas encore activés : appliquez la migration <code>047_add_customers_and_sales_settings.sql</code>.</p>
<?php else: ?>
<?php if(!$canConfigure): ?><p class="app-alert alert-info" role="status"><i class="bi bi-eye" aria-hidden="true"></i> Consultation seule : le taux et les tarifs sont fixés par un responsable habilité.</p><?php endif; ?>

<section class="pig-ref-panel sales-rate" aria-labelledby="sales-rate-title">
<header class="pig-ref-section-head"><div><h3 id="sales-rate-title">Taux du jour</h3><p>Les montants en CDF sont calculés à ce taux. Chaque opération gardera le taux appliqué.</p></div></header>
<div class="sales-rate__body">
<div class="sales-rate__current<?=$current===null?' is-missing':''?>"><?php if($current!==null): ?><span>1 USD =</span><strong><?=e($money($current))?> CDF</strong><small>Depuis le <?=e($stamp($rate['effective_at']))?> · <?=e($rate['author'])?><?=$rate['notes']?' · '.e($rate['notes']):''?></small><?php else: ?><span>Aucun taux saisi</span><strong>—</strong><small>Saisissez le taux du jour avant les premières ventes.</small><?php endif; ?></div>
<?php if($canConfigure): ?>
<form method="post" action="<?=e(base_url('sales/settings/rates'))?>" class="enterprise-form sales-rate__form" data-flow-form data-sales-rate-form data-current-rate="<?=e((string)$current)?>" data-alert="<?=e(SalesSettingsService::RATE_ALERT*100)?>" data-confirm="Enregistrer ce taux du jour ? Il s’applique immédiatement aux prochaines opérations."><?=csrf_field()?>
<p class="app-alert app-alert-error" data-feed-error role="alert" hidden></p>
<label><span>Nouveau taux (CDF pour 1 USD) *</span><span class="pig-ref-unit"><input type="number" name="rate" min="0.0001" step="0.0001" inputmode="decimal" required placeholder="Ex. 2850"><span aria-hidden="true">CDF</span></span></label>
<label><span>Observation</span><input name="notes" maxlength="255" placeholder="Ex. taux de la banque, marché"></label>
<p class="sales-rate__variation" data-rate-variation aria-live="polite"></p>
<label class="flow-check" data-rate-confirm hidden><input type="checkbox" name="confirm_variation" value="1"> Je confirme cette variation importante.</label>
<button type="submit" class="btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> Enregistrer le taux</button>
</form>
<?php endif; ?>
</div>
<?php if($rates): ?><details class="sales-rate__history"><summary>Historique des taux <span class="pig-ref-count"><?=count($rates)?></span></summary>
<div class="table-responsive"><table class="enterprise-table compact-table" data-datatable="false" aria-label="Historique des taux"><thead><tr><th>Date</th><th class="num">1 USD</th><th>Saisi par</th><th>Observation</th></tr></thead><tbody>
<?php foreach($rates as $r): ?><tr><td><?=e($stamp($r['effective_at']))?></td><td class="num"><strong><?=e($money($r['rate']))?></strong><small>CDF</small></td><td><?=e($r['author'])?></td><td><?=e($r['notes']?:'—')?></td></tr><?php endforeach; ?>
</tbody></table></div></details><?php endif; ?>
</section>

<section class="pig-ref-panel" aria-labelledby="sales-prices-title">
<header class="pig-ref-section-head"><div><h3 id="sales-prices-title">Tarifs par sac</h3><p>Prix en USD. Le tarif entreprises est proposé à la commande et figé sur celle-ci ; le tarif comptoir s’applique aux particuliers. L’équivalent CDF suit le taux du jour.</p></div></header>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Tarifs par sac" data-search-placeholder="Rechercher un produit ou un format…"><thead><tr><th>Produit</th><th>Format</th><th class="num">Entreprises</th><th class="num">Comptoir</th><th>Dernière modification</th><?php if($canConfigure): ?><th class="cell-actions-head">Actions</th><?php endif; ?></tr></thead><tbody>
<?php foreach($prices as $p):$last=null;foreach(['company','counter'] as $channel)if($p[$channel]&&(!$last||$p[$channel]['effective_at']>$last['effective_at']))$last=$p[$channel]; ?>
<tr data-price-key="<?=e($p['key'])?>">
<td data-label="Produit" class="cell--lead"><strong class="cell-main"><?=e($p['product_name'])?></strong><small class="cell-ref"><?=e($p['product_code'])?></small></td>
<td data-label="Format"><span class="cell-main"><?=e($p['format_name'])?></span><small><?=e($money($p['weight_kg'],0))?> kg</small></td>
<?php foreach(['company','counter'] as $channel): ?><td data-label="<?=e(SalesSettingsService::CHANNELS[$channel])?>" class="num" data-order="<?=e($p[$channel]['price_usd']??0)?>"><?php if($p[$channel]): ?><strong><?=e($money($p[$channel]['price_usd']))?> USD</strong><small><?=e($cdf($p[$channel]['price_usd']))?></small><?php else: ?><span class="ui-status ui-status--warning">À fixer</span><?php endif; ?></td><?php endforeach; ?>
<td data-label="Dernière modification"><?php if($last): ?><span class="cell-main"><?=e($stamp($last['effective_at']))?></span><small><?=e($last['author'])?></small><?php else: ?><span class="cell-muted">—</span><?php endif; ?></td>
<?php if($canConfigure): ?><td data-label="Actions" class="cell-actions"><div class="row-actions"><button type="button" class="btn-secondary row-action-button" data-workspace-modal-open="sales-price-<?=e($p['key'])?>"><i class="bi bi-pencil" aria-hidden="true"></i><span>Modifier</span></button></div></td><?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if(!$prices): ?><p class="flow-empty">Aucun produit fini avec un format de sac. Les formats se paramètrent dans les emballages vides.</p><?php endif; ?>
</section>

<section class="pig-ref-panel" aria-labelledby="sales-office-title">
<header class="pig-ref-section-head"><div><h3 id="sales-office-title">Bureau principal</h3><p>Dépôts où arriveront les marchandises des clients et d’où vendra le comptoir.</p></div><?php if($canAdminister): ?><button type="button" class="btn-secondary" data-workspace-modal-open="sales-main-office"><i class="bi bi-pencil" aria-hidden="true"></i> Modifier</button><?php endif; ?></header>
<div class="pig-ref-grid sales-depots">
<?php foreach($depots as $d): ?><article class="pig-ref-card sales-depot<?=$d['main_office']?' is-main':''?>"><div class="product-material-card__head"><span class="product-material-card__icon"><i class="bi bi-shop-window" aria-hidden="true"></i></span><div><span class="pig-ref-card__code"><?=e($d['code'])?></span><h4><?=e($d['name'])?></h4><p class="pig-ref-card__age"><?=$d['main_office']?'<i class="bi bi-check2-circle" aria-hidden="true"></i> Bureau principal':'Hors bureau principal'?></p></div></div><p class="sales-depot__stock"><?=(int)$d['free_bags']>0?e(number_format((int)$d['free_bags'],0,',',' ')).' sac(s) libres · '.e($money($d['free_kg'],0)).' kg':'Aucun stock de produits finis'?></p></article><?php endforeach; ?>
</div>
</section>

<?php if($canConfigure):foreach($prices as $p):$drawerId='sales-price-'.$p['key'];$drawerTitle=$p['product_name'].' · '.$p['format_name'];require $flowViews.'/drawer_start.php';$action='sales/settings/prices';$confirm='Enregistrer ces tarifs ? Ils s’appliquent aux prochaines commandes et ventes.';require $flowViews.'/form_start.php'; ?>
<input type="hidden" name="product_id" value="<?=e($p['product_id'])?>"><input type="hidden" name="bag_format_id" value="<?=e($p['bag_format_id'])?>">
<div class="form-grid" data-sales-price-form data-rate="<?=e((string)$current)?>">
<?php foreach(SalesSettingsService::CHANNELS as $channel=>$label): ?><label><span>Tarif <?=e(mb_strtolower($label))?> (USD par sac)</span><span class="pig-ref-unit"><input type="number" name="price_<?=e($channel)?>" min="0.01" step="0.01" inputmode="decimal" value="<?=e($p[$channel]['price_usd']??'')?>" data-price-input><span aria-hidden="true">USD</span></span><small data-price-cdf><?=$p[$channel]?e($cdf($p[$channel]['price_usd'])):'Pas encore de tarif'?></small></label><?php endforeach; ?>
</div>
<p class="livestock-guide">Un tarif laissé vide n’est pas modifié. Les commandes déjà passées gardent leur prix.</p>
<?php if($p['history']): ?><h3 class="sales-price-history-title">Historique</h3><ul class="fish-lot-lines"><?php foreach($p['history'] as $h): ?><li><span><strong><?=e($stamp($h['effective_at']))?></strong> <small><?=e($h['author'])?></small></span><span><?=e(SalesSettingsService::CHANNELS[$h['channel']])?></span><span><?=e($money($h['price_usd']))?> USD</span></li><?php endforeach; ?></ul><?php endif; ?>
<?php $submitLabel='Enregistrer les tarifs';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endforeach;endif; ?>
<?php if($canAdminister):$drawerId='sales-main-office';$drawerTitle='Dépôts du bureau principal';require $flowViews.'/drawer_start.php';$action='sales/settings/main-office';$confirm='Enregistrer les dépôts du bureau principal ?';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Cochez les dépôts situés au bureau principal.</p>
<fieldset class="pig-ref-toggles sales-office-choices"><legend class="sr-only">Dépôts</legend><?php foreach($depots as $d): ?><label class="pig-ref-toggle"><input type="checkbox" name="site_ids[]" value="<?=e($d['id'])?>"<?=$d['main_office']?' checked':''?>><span class="pig-ref-toggle__icon"><i class="bi bi-shop-window" aria-hidden="true"></i></span><span class="pig-ref-toggle__text"><strong><?=e($d['name'])?></strong><small><?=e($d['code'])?></small></span></label><?php endforeach; ?></fieldset>
<?php $submitLabel='Enregistrer';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>
<?php endif; ?>
<div class="entity-modal-backdrop workspace-modal-backdrop" data-workspace-modal-close></div>
</div>
