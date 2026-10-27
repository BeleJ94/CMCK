<?php
$canAdmin=$ready&&$canAdminister;$returnPath='butchery/products';
$kg=static fn($v)=>number_format((float)$v,3,',',' ');
$productsById=array_column($products,null,'id');$materialNames=array_column($materials,'name','id');
$materialIcon=static fn(array $m)=>strpos($m['code'],'POISSON')!==false?'bi-water':(preg_match('/VOLAILLE|POULE|POULET/',$m['code'])?'bi-egg':(strpos($m['code'],'PORC')!==false?'bi-piggy-bank':'bi-box-seam'));
$offeredCount=count(array_filter($products,static fn($p)=>$p['sources']));
$emptyMaterials=array_values(array_filter($materials,static fn($m)=>!$m['products']));
$actionLabels=['butchery_product_create'=>['Création','success'],'butchery_product_update'=>['Modification','progress'],'butchery_material_products'=>['Matière','neutral']];
$chip=static fn(array $p)=>'<li class="product-chip'.($p['item_kind']==='co_product'?' is-co':'').'">'.e($p['name']).($p['item_kind']==='co_product'?' <small>coproduit</small>':'').'</li>';
?>
<div class="pig-directory pig-compact pig-referential butchery-products" data-butchery-products>
<section class="dashboard-hero ui-page-header"><img class="pig-header-art" src="<?=e(asset_url('illustrations/butchery/production.svg'))?>" width="80" height="60" alt="" aria-hidden="true"><div class="ui-page-header__content"><p class="section-label">BOUCHERIE · RÉFÉRENTIEL</p><h2>Produits de transformation</h2><p>Pour chaque matière, les produits que l’on peut en obtenir. Le formulaire de transformation ne propose que les produits de la matière choisie.</p></div><div class="ui-page-header__actions"><a class="btn-secondary" href="<?=e(base_url('butchery/production'))?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Transformation</a><?php if($canAdmin): ?><button type="button" class="btn-primary" data-workspace-modal-open="butchery-product-new"><i class="bi bi-plus-circle" aria-hidden="true"></i> Ajouter un produit</button><?php endif; ?></div></section>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?>
<?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if(!$ready): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Tous les produits sont proposés pour chaque matière. Pour choisir les produits de chaque matière, appliquez la migration <code>046_add_butchery_product_sources.sql</code>.</p>
<?php elseif(!$canAdmin): ?><p class="app-alert alert-info" role="status"><i class="bi bi-eye" aria-hidden="true"></i> Consultation seule. Le paramétrage des produits est réservé aux administrateurs de la boucherie.</p><?php endif; ?>

<?php if($ready): ?>
<?php if($emptyMaterials): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?=count($emptyMaterials)===1?'Une matière n’a':count($emptyMaterials).' matières n’ont'?> aucun produit : <?=e(implode(', ',array_column($emptyMaterials,'name')))?>. Leur transformation est impossible tant qu’aucun produit n’y est rattaché.</p><?php endif; ?>
<section class="pig-ref-panel" aria-labelledby="product-materials-title">
<header class="pig-ref-section-head"><div><h3 id="product-materials-title">Matières et produits <span class="pig-ref-count"><?=count($materials)?></span></h3><p>Ce qui sort de chaque matière. Les coproduits (abats, os…) sont comptés à part dans le rendement.</p></div></header>
<div class="pig-ref-grid">
<?php foreach($materials as $m): $mine=array_map(static fn($id)=>$productsById[$id],$m['products']); ?>
<article class="pig-ref-card product-material-card<?=$mine?'':' is-empty'?>" data-product-material="<?=e($m['id'])?>">
<div class="product-material-card__head"><span class="product-material-card__icon"><i class="bi <?=e($materialIcon($m))?>" aria-hidden="true"></i></span><div><span class="pig-ref-card__code"><?=e($m['code'])?></span><h4><?=e($m['name'])?></h4><p class="pig-ref-card__age"><i class="bi bi-snow" aria-hidden="true"></i> <?=(float)$m['stock_kg']>0?e($kg($m['stock_kg'])).' kg disponibles en chambre froide':'Aucun stock en chambre froide'?></p></div></div>
<?php if($mine): ?><ul class="product-chips" aria-label="Produits de <?=e($m['name'])?>"><?php foreach($mine as $p)echo $chip($p); ?></ul>
<?php else: ?><p class="product-material-card__empty"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Aucun produit : sa transformation est impossible.</p><?php endif; ?>
<div class="pig-ref-card__actions"><span class="product-material-card__count"><?=count($mine)?> produit(s)</span><?php if($canAdmin): ?><button type="button" class="btn-secondary" data-workspace-modal-open="butchery-material-<?=e($m['id'])?>"><i class="bi bi-ui-checks" aria-hidden="true"></i> Choisir les produits</button><?php endif; ?></div>
</article>
<?php endforeach; ?>
</div>
<ol class="pig-ref-steps"><li><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> La transformation commence par la matière.</li><li><i class="bi bi-funnel" aria-hidden="true"></i> Seuls ses produits sont proposés.</li><li><i class="bi bi-shield-check" aria-hidden="true"></i> Un autre produit est refusé à l’enregistrement.</li></ol>
</section>
<?php endif; ?>

<section class="pig-ref-panel" aria-labelledby="product-catalog-title">
<header class="pig-ref-section-head"><div><h3 id="product-catalog-title">Catalogue des produits <span class="pig-ref-count"><?=count($products)?></span></h3><p><?=$ready?$offeredCount.' produit(s) proposé(s) à la transformation.':'Produits finis et coproduits de la boucherie.'?></p></div></header>
<div class="ui-filterbar slaughter-register-filters product-catalog-filters" role="search" aria-label="Filtrer les produits">
<label class="ui-filterbar__search"><span>Rechercher un produit</span><span class="slaughter-register-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" data-product-search placeholder="Nom ou code…"></span></label>
<?php if($ready): ?><label><span>Matière</span><select data-product-material-filter><option value="">Toutes les matières</option><?php foreach($materials as $m): ?><option value="<?=e($m['id'])?>"><?=e($m['name'])?></option><?php endforeach; ?><option value="none">Non proposés</option></select></label><?php endif; ?>
<p class="product-catalog-count" data-product-count aria-live="polite"></p>
</div>
<div class="table-responsive"><table class="enterprise-table product-catalog" data-datatable="false" aria-label="Catalogue des produits"><thead><tr><th scope="col">Produit</th><th scope="col">Type</th><?php if($ready): ?><th scope="col">Obtenu de</th><?php endif; ?><th scope="col">Suivi</th><th scope="col">DLC conseillée</th><th scope="col">Transformations</th><?php if($canAdmin): ?><th scope="col"><span class="sr-only">Actions</span></th><?php endif; ?></tr></thead><tbody>
<?php foreach($products as $p): ?>
<tr data-product-row data-sources="<?=e(implode(',',$p['sources']))?>" data-search="<?=e(mb_strtolower($p['name'].' '.$p['code']))?>">
<td><strong><?=e($p['name'])?></strong><small><?=e($p['code'])?></small></td>
<td><span class="ui-status ui-status--<?=$p['item_kind']==='co_product'?'neutral':'success'?>"><?=e(ButcheryProductService::KINDS[$p['item_kind']])?></span></td>
<?php if($ready): ?><td><?php if($p['sources']): ?><ul class="product-chips is-compact"><?php foreach($p['sources'] as $raw): ?><li class="product-chip is-material"><?=e($materialNames[$raw]??'—')?></li><?php endforeach; ?></ul><?php else: ?><span class="ui-status ui-status--warning">Non proposé</span><?php endif; ?></td><?php endif; ?>
<td><?=!empty($p['track_pieces'])?'Pièces et kilos':'Kilos'?></td>
<td data-order="<?=e($p['default_shelf_life_days']??0)?>"><?=$p['default_shelf_life_days']!==null?e($p['default_shelf_life_days']).' jour(s)':'—'?></td>
<td data-order="<?=e($p['outputs'])?>"><?=(int)$p['outputs']?></td>
<?php if($canAdmin): ?><td><button type="button" class="btn-secondary" data-workspace-modal-open="butchery-product-<?=e($p['id'])?>" aria-label="Modifier <?=e($p['name'])?>"><i class="bi bi-pencil" aria-hidden="true"></i> Modifier</button></td><?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<p class="flow-empty" data-product-empty hidden>Aucun produit pour ces filtres.</p>
</section>

<?php if($history): ?>
<section class="pig-ref-panel pig-ref-history" aria-labelledby="product-history-title"><header class="pig-ref-section-head"><div><h3 id="product-history-title">Dernières modifications</h3><p>Chaque modification est enregistrée dans l’audit du système.</p></div></header>
<ol><?php foreach($history as $entry):[$actionLabel,$tone]=$actionLabels[$entry['action']]??[$entry['action'],'neutral']; ?><li><span class="ui-status ui-status--<?=e($tone)?>"><?=e($actionLabel)?></span><span><?=e($entry['description'])?></span><small><?=e(($entry['user_name']?:'Système').' · '.date('d/m/Y H:i',strtotime($entry['created_at'])))?></small></li><?php endforeach; ?></ol></section>
<?php endif; ?>

<?php if($canAdmin): ?>
<?php $p=null;require __DIR__.'/product_form.php';foreach($products as $p)require __DIR__.'/product_form.php'; ?>
<?php foreach($materials as $m): $drawerId='butchery-material-'.$m['id'];$drawerKicker='BOUCHERIE · PRODUITS';$drawerTitle='Produits de « '.$m['name'].' »';require __DIR__.'/drawer_start.php';$action='butchery/products/materials/'.$m['id'];$confirm='Enregistrer les produits de « '.$m['name'].' » ? Seuls ceux-ci seront proposés à sa transformation.';require __DIR__.'/form_start.php'; ?>
<input type="hidden" name="version" value="<?=e($m['version'])?>">
<div data-material-products>
<p class="livestock-guide">Cochez les produits que l’on obtient en transformant cette matière. <span data-material-selected aria-live="polite"></span></p>
<?php if(count($products)>8): ?><label><span>Rechercher un produit</span><input type="search" data-material-product-search placeholder="Nom ou code…" autocomplete="off"></label><?php endif; ?>
<fieldset class="product-choices"><legend class="sr-only">Produits obtenus</legend>
<?php foreach($products as $p): ?><label class="pig-ref-toggle product-choice" data-search="<?=e(mb_strtolower($p['name'].' '.$p['code']))?>"><input type="checkbox" name="product_ids[]" value="<?=e($p['id'])?>"<?=in_array((int)$p['id'],$m['products'],true)?' checked':''?>><span class="pig-ref-toggle__icon"><i class="bi <?=$p['item_kind']==='co_product'?'bi-recycle':'bi-tag'?>" aria-hidden="true"></i></span><span class="pig-ref-toggle__text"><strong><?=e($p['name'])?></strong><small><?=e($p['code'].' · '.ButcheryProductService::KINDS[$p['item_kind']])?></small></span></label><?php endforeach; ?>
</fieldset>
<p class="receipt-control-help">Sans produit coché, cette matière ne peut plus être transformée. Un produit absent de la liste se crée avec « Ajouter un produit ».</p>
</div>
<?php $submitLabel='Enregistrer les produits';require __DIR__.'/form_end.php';require __DIR__.'/drawer_end.php';endforeach; ?>
<?php endif; ?>
</div>
