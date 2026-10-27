<section class="slaughter-register-tools" aria-label="Recherche et gestion des opérations">
<div class="ui-filterbar slaughter-register-filters" role="search" aria-label="Filtrer les opérations">
<label class="ui-filterbar__search"><span>Rechercher un abattage</span><span class="slaughter-register-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" data-flow-search placeholder="Numéro, lot, ferme ou personne…"></span></label>
<label><span>État</span><select data-flow-state><option value="">Tous les états</option><?php foreach($filterStates as$value=>$label):?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach;?></select></label>
<label><span>Lignes par page</span><select data-flow-page-size><option value="10" selected>10</option><option value="25">25</option><option value="50">50</option></select></label>
<button type="button" class="btn-secondary" data-flow-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Réinitialiser</button>
<?php if(Auth::can('livestock','create')):?><button type="button" class="btn-primary" data-workspace-modal-open="flow-slaughter"><i class="bi bi-plus-circle" aria-hidden="true"></i> Enregistrer un abattage</button><?php endif;?>
</div>
<div class="slaughter-register-context"><div class="ui-filterbar__summary"><strong data-flow-count aria-live="polite"></strong><span aria-hidden="true">·</span><small data-flow-filter-count></small></div><p><i class="bi bi-info-circle" aria-hidden="true"></i> La validation actualise les effectifs et crée le stock de carcasses.</p></div>
</section>
<p class="flow-empty" data-flow-filter-empty role="status" hidden>Aucun résultat pour ces filtres. Modifiez votre recherche ou réinitialisez les filtres.</p>
<p class="app-alert alert-info" data-flow-filter-update role="status" hidden><i class="bi bi-info-circle" aria-hidden="true"></i> Des résultats nouveaux ou modifiés sont masqués par vos filtres. <button type="button" class="btn-secondary" data-flow-reveal-results>Afficher tous les résultats</button></p>
