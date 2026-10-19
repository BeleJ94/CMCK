<div class="ui-filterbar" role="search" aria-label="Filtrer les opérations">
<label class="ui-filterbar__search"><span>Rechercher</span><input type="search" data-flow-search placeholder="Lot, produit, numéro ou nom…"></label>
<?php if(count($filterKinds)>1): ?><label><span>Afficher</span><select data-flow-kind><option value="">Toutes les catégories</option><?php foreach($filterKinds as $value=>$label): ?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach; ?></select></label><?php endif; ?>
<label><span>État</span><select data-flow-state><option value="">Tous les états</option><?php foreach($filterStates as $value=>$label): ?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach; ?></select></label>
<div class="ui-filterbar__summary"><span data-flow-count aria-live="polite"></span><small data-flow-filter-count></small></div>
<button type="button" class="btn-secondary ui-filterbar__reset" data-flow-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Réinitialiser</button>
</div>
<p class="flow-empty" data-flow-filter-empty role="status" hidden>Aucun résultat pour ces filtres. Modifiez votre recherche ou réinitialisez les filtres.</p>
<p class="app-alert alert-info" data-flow-filter-update role="status" hidden><i class="bi bi-info-circle" aria-hidden="true"></i> Des résultats nouveaux ou modifiés sont masqués par vos filtres. <button type="button" class="btn-secondary" data-flow-reveal-results>Afficher tous les résultats</button></p>
