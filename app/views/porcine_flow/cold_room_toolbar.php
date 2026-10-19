<section class="slaughter-register-tools" aria-label="Recherche et gestion de la chambre froide">
<div class="ui-filterbar slaughter-register-filters cold-room-article-filters" role="search" aria-label="Filtrer les articles au froid">
<label class="ui-filterbar__search"><span>Rechercher un article</span><span class="slaughter-register-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" data-cold-search placeholder="Article ou numéro de lot…"></span></label>
<label><span>Catégorie</span><select data-cold-kind><option value="">Toutes les catégories</option><?php foreach($filterKinds as$value=>$label):?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach;?></select></label>
<button type="button" class="btn-secondary" data-cold-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Tout afficher</button>
</div>
<div class="slaughter-register-context"><div class="ui-filterbar__summary"><strong data-cold-count aria-live="polite"></strong></div><div class="cold-room-toolbar-actions"><?php if($coldCanTransform):?><a class="btn-primary" href="<?=e(base_url('butchery/production'))?>"><i class="bi bi-gear" aria-hidden="true"></i> Transformer des carcasses</a><?php endif;?><?php if($coldCanSupply):?><button type="button" class="btn-secondary" data-workspace-modal-open="flow-supply"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Approvisionner un comptoir</button><?php endif;?></div><p><i class="bi bi-info-circle" aria-hidden="true"></i> Chaque carte regroupe le stock total d’un article. Ouvrez ses lots pour consulter les dates et les actions.</p></div>
</section>
<p class="flow-empty" data-cold-empty role="status" hidden>Aucun article pour ces filtres. Modifiez la recherche ou affichez tous les articles.</p>
