<?php foreach($filterKinds as$category=>$label):$articles=array_filter($coldArticles,static fn($article)=>$article['kind']===$category);if(!$articles)continue;?>
<section class="cold-room-category" data-cold-category="<?=e($category)?>" aria-labelledby="cold-room-category-<?=e($category)?>">
<div class="cold-room-category-heading"><h3 id="cold-room-category-<?=e($category)?>"><?=e($label)?></h3><span data-cold-category-summary><?=count($articles)?> article(s) · <?=$kg(array_sum(array_column($articles,'physical')))?> kg au froid</span></div>
<div class="plot-card-grid cold-room-article-grid">
<?php foreach($articles as$article):$summary=$kg($article['physical']).' kg au froid · '.$kg($article['available']).' kg disponibles · '.$article['lots'].' lot(s)';?>
<article class="plot-card butchery-stage-card cold-room-article-card" data-cold-card data-article-key="<?=e($article['key'])?>" data-article-name="<?=e($article['name'])?>" data-kind="<?=e($category)?>" data-search="<?=e($article['name'].' '.implode(' ',$article['search']))?>">
<div class="plot-card-visual"><img src="<?=e(asset_url('illustrations/butchery/'.($category==='raw'?'cold-room':'production').'.svg'))?>" width="288" height="160" alt="" aria-hidden="true" loading="lazy"></div>
<div class="plot-card-body"><p class="section-label"><?=e($category==='raw'?'CARCASSES ET MATIÈRES':'PRODUITS TRANSFORMÉS')?></p><h3><?=e($article['name'])?></h3>
<p class="butchery-stage-value"><strong data-cold-physical="<?=e($article['physical'])?>"><?=$kg($article['physical'])?></strong><span>kg au froid</span></p>
<p class="cold-room-article-available"><strong data-cold-available="<?=e($article['available'])?>"><?=$kg($article['available'])?> kg</strong> disponibles</p>
<p class="butchery-stage-summary"><span data-cold-reserved="<?=e($article['reserved'])?>"><?=$kg($article['reserved'])?> kg réservés</span> · <?=e($article['lots'])?> lot(s)</p>
<?php if($article['expired']):?><p class="cold-room-article-alert"><span class="ui-status ui-status--danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?=e($article['expired'])?> lot(s) à DLC dépassée</span></p><?php endif;?>
<div class="plot-card-actions"><button id="cold-room-article-<?=e($article['key'])?>" type="button" class="btn-secondary" data-workspace-modal-open="cold-room-article-detail" data-cold-article="<?=e($article['key'])?>" data-cold-summary="<?=e($summary)?>" aria-haspopup="dialog" aria-controls="cold-room-article-detail" aria-label="<?=e('Voir les lots : '.$article['name'])?>">Voir les lots <i class="bi bi-arrow-right" aria-hidden="true"></i></button></div></div></article>
<?php endforeach;?></div></section>
<?php endforeach;?>
<?php if(!$coldArticles):?><p class="flow-empty">La chambre froide est vide. Réceptionnez des carcasses pour commencer.</p><?php endif;?>
<p class="butchery-home-note">Le poids au froid inclut les réservations et les lots bloqués. Les quantités placées aux comptoirs sont exclues. Seul le poids disponible peut être utilisé.</p>
<section id="cold-room-article-detail" class="entity-modal workspace-entity-modal feed-editor cold-room-article-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="cold-room-article-title">
<header><div><p class="page-kicker">BOUCHERIE · LOTS DE L’ARTICLE</p><h2 id="cold-room-article-title" data-cold-article-title>Lots de l’article</h2><p data-cold-article-summary></p></div><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer les lots de l’article">×</button></header>
<div class="feed-form-body"><input type="hidden" data-flow-article value="">
<section class="slaughter-register-tools" aria-label="Recherche dans les lots de l’article"><div class="ui-filterbar slaughter-register-filters cold-room-lot-filters" role="search" aria-label="Filtrer les lots de l’article">
<label class="ui-filterbar__search"><span>Rechercher un lot</span><span class="slaughter-register-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" data-flow-search placeholder="Numéro de lot ou lot d’élevage…"></span></label>
<label><span>État</span><select data-flow-state><option value="">Tous les états</option><?php foreach($filterStates as$value=>$stateLabel):?><option value="<?=e($value)?>"><?=e($stateLabel)?></option><?php endforeach;?></select></label>
<label><span>Lignes par page</span><select data-flow-page-size><option value="10" selected>10</option><option value="25">25</option><option value="50">50</option></select></label>
<button type="button" class="btn-secondary" data-flow-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Réinitialiser</button></div>
<div class="slaughter-register-context"><div class="ui-filterbar__summary"><strong data-flow-count aria-live="polite"></strong><span aria-hidden="true">·</span><small data-flow-filter-count></small></div></div></section>
<p class="flow-empty" data-flow-filter-empty role="status" hidden>Aucun lot pour ces filtres. Modifiez la recherche ou réinitialisez les filtres.</p>
<p class="app-alert alert-info" data-flow-filter-update role="status" hidden>Des lots nouveaux ou modifiés sont masqués par vos filtres. <button type="button" class="btn-secondary" data-flow-reveal-results>Afficher tous les résultats</button></p>
<?php require __DIR__.'/cold_room_table.php';?>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button></footer></section>
