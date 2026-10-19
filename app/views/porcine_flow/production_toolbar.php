<section class="slaughter-register-tools" aria-label="Recherche et gestion des transformations">
<div class="ui-filterbar slaughter-register-filters production-register-filters" role="search" aria-label="Filtrer les transformations">
<label class="ui-filterbar__search"><span>Rechercher une transformation</span><span class="slaughter-register-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" data-flow-search placeholder="Référence, fiche, produit ou lot…"></span></label>
<label><span>État</span><select data-flow-state><option value="">Tous les états</option><?php foreach($filterStates as$value=>$label):?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach;?></select></label>
<label><span>Lignes par page</span><select data-flow-page-size><option value="10" selected>10</option><option value="25">25</option><option value="50">50</option></select></label>
<button type="button" class="btn-secondary" data-flow-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Réinitialiser</button>
<?php if($canOperate&&Auth::can('butchery','create',$site)):?><button type="button" class="btn-primary" data-workspace-modal-open="flow-transform"><i class="bi bi-plus-circle" aria-hidden="true"></i> Enregistrer une transformation</button><?php endif;?>
</div>
<div class="slaughter-register-context"><div class="ui-filterbar__summary"><strong data-flow-count aria-live="polite"></strong><span aria-hidden="true">·</span><small data-flow-filter-count></small></div><p><i class="bi bi-info-circle" aria-hidden="true"></i> La validation consomme la matière et répartit les produits entre froid et comptoir.</p><a class="production-recipes-link" href="<?=e(base_url('butchery/recipes'))?>"><i class="bi bi-journal-text" aria-hidden="true"></i> Fiches de transformation</a></div>
</section>
<p class="flow-empty" data-flow-filter-empty role="status" hidden>Aucune transformation pour ces filtres. Modifiez la recherche ou réinitialisez les filtres.</p>
<p class="app-alert alert-info" data-flow-filter-update role="status" hidden><i class="bi bi-info-circle" aria-hidden="true"></i> Des transformations nouvelles ou modifiées sont masquées par vos filtres. <button type="button" class="btn-secondary" data-flow-reveal-results>Afficher tous les résultats</button></p>
