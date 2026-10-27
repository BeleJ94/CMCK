<?php
// Product drawer: creation when $p is null, otherwise edition of $p. Expects $materials, $materialIcon, $piecesReady, $returnPath.
$editing=$p!==null;$formId=$editing?'butchery-product-'.$p['id']:'butchery-product-new';
$drawerId=$formId;$drawerKicker='BOUCHERIE · PRODUITS';$drawerTitle=$editing?'Modifier « '.$p['name'].' »':'Ajouter un produit';require __DIR__.'/drawer_start.php';
$action=$editing?'butchery/products/'.$p['id']:'butchery/products';$confirm=$editing?'Enregistrer les modifications de ce produit ?':'Créer ce produit ? Il sera proposé à la transformation des matières cochées.';require __DIR__.'/form_start.php';
$kind=$editing?$p['item_kind']:'finished_product';$sources=$editing?$p['sources']:[];
?>
<?php if($editing): ?><input type="hidden" name="version" value="<?=e($p['version'])?>"><?php endif; ?>
<div class="form-grid" data-product-form<?=$editing?'':' data-product-new'?>>
<label><span>Nom du produit *</span><input name="name" maxlength="160" required value="<?=e($editing?$p['name']:'')?>" placeholder="Ex. Filet de porc" autocomplete="off"><small>Affiché dans la transformation, la chambre froide, la caisse et les rapports.</small></label>
<?php if($editing): ?><label><span>Code</span><input value="<?=e($p['code'])?>" readonly aria-describedby="<?=e($formId)?>-code-help"><small id="<?=e($formId)?>-code-help">Le code identifie le produit dans les stocks et l’historique : il ne change plus.</small></label>
<?php else: ?><label><span>Code <span class="pig-ref-auto-badge" data-product-code-badge>Automatique</span></span><span class="pig-ref-code"><input name="code" maxlength="60" pattern="[A-Za-z0-9][A-Za-z0-9\-]{0,59}" autocapitalize="characters" autocomplete="off" spellcheck="false" data-product-code aria-describedby="<?=e($formId)?>-code-help"></span><small id="<?=e($formId)?>-code-help" data-product-code-help aria-live="polite">Laissez vide : le code est déduit du nom, par exemple FILET-DE-PORC.</small></label><?php endif; ?>
<fieldset class="form-wide pig-ref-toggles product-kinds"><legend>Type de produit *</legend>
<?php foreach([['finished_product','bi-tag','Vendu ou stocké comme produit de la découpe.'],['co_product','bi-recycle','Abats, os, gras : comptés à part du rendement principal.']] as [$value,$icon,$help]): ?><label class="pig-ref-toggle"><input type="radio" name="item_kind" value="<?=e($value)?>" required<?=$kind===$value?' checked':''?>><span class="pig-ref-toggle__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="pig-ref-toggle__text"><strong><?=e(ButcheryProductService::KINDS[$value])?></strong><small><?=e($help)?></small></span></label><?php endforeach; ?>
</fieldset>
<fieldset class="form-wide pig-ref-toggles product-sources" data-product-sources><legend>Obtenu de *</legend>
<?php foreach($materials as $m): ?><label class="pig-ref-toggle"><input type="checkbox" name="source_ids[]" value="<?=e($m['id'])?>"<?=in_array((int)$m['id'],$sources,true)?' checked':''?>><span class="pig-ref-toggle__icon"><i class="bi <?=e($materialIcon($m))?>" aria-hidden="true"></i></span><span class="pig-ref-toggle__text"><strong><?=e($m['name'])?></strong><small><?=e($m['code'])?></small></span></label><?php endforeach; ?>
</fieldset>
<p class="form-wide product-sources-note" data-product-sources-note aria-live="polite"><?=$editing?'Décochez toutes les matières pour ne plus proposer ce produit à la transformation.':'Cochez au moins une matière : le produit ne sera proposé qu’à leur transformation.'?></p>
<?php if($editing): ?><label><span>Suivi</span><input value="<?=!empty($p['track_pieces'])?'Pièces et kilos':'Kilos'?>" readonly><small>Fixé à la création : les stocks existants en dépendent.</small></label>
<?php elseif($piecesReady): ?><fieldset class="form-wide pig-ref-toggles product-pieces"><legend>Suivi</legend><label class="pig-ref-toggle"><input type="checkbox" name="track_pieces" value="1"><span class="pig-ref-toggle__icon"><i class="bi bi-123" aria-hidden="true"></i></span><span class="pig-ref-toggle__text"><strong>Compté en pièces</strong><small>Pour un produit entier, comme un poulet entier : la transformation demande le nombre de pièces et la caisse peut le vendre à la pièce.</small></span></label></fieldset><?php endif; ?>
<label><span>DLC conseillée</span><span class="pig-ref-unit"><input type="number" name="default_shelf_life_days" min="1" max="365" step="1" inputmode="numeric" value="<?=e($editing?($p['default_shelf_life_days']??''):'')?>" placeholder="Ex. 4"><span aria-hidden="true">jours</span></span><small>Facultatif. Proposée lors de la transformation, à partir de sa date.</small></label>
</div>
<?php $submitLabel=$editing?'Enregistrer les modifications':'Ajouter le produit';require __DIR__.'/form_end.php';require __DIR__.'/drawer_end.php'; ?>
