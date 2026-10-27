<?php
$editing=$g!==null;
$formId=$editing?'pig-group-edit-'.$g['id']:'pig-group-new';
$value=static fn($key,$default='')=>$g[$key]??$default;
?>
<section id="<?=e($formId)?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor pig-ref-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?=e($formId)?>-title">
<header><div><p class="page-kicker">PORCS · GROUPES D’ÂGE</p><h2 id="<?=e($formId)?>-title"><?=$editing?'Modifier le groupe '.e($g['code']):'Ajouter un groupe'?></h2></div><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header>
<form method="post" action="<?=e(base_url($editing?'livestock/pigs/groups/'.$g['id'].'/update':'livestock/pigs/groups'))?>" class="enterprise-form" data-pig-form data-pig-group-form data-group-id="<?=e($editing?$g['id']:'')?>" data-confirm="Enregistrer ce groupe ?"><?=csrf_field()?><?php if($editing): ?><input type="hidden" name="version" value="<?=e($g['version'])?>"><?php endif; ?>
<div class="feed-form-body">
<p class="app-alert app-alert-error" data-feed-error hidden role="alert"></p>
<?php if($editing&&$g['status']!=='active'): ?><p class="livestock-guide"><i class="bi bi-pause-circle" aria-hidden="true"></i> Ce groupe est désactivé : vos modifications s’appliqueront à sa réactivation.</p><?php endif; ?>
<div class="form-grid">
<?php if($editing): ?><label><span>Code</span><input name="code" maxlength="10" readonly value="<?=e($g['code'])?>" aria-describedby="<?=e($formId)?>-code-help"><small id="<?=e($formId)?>-code-help">Le code identifie le groupe dans l’historique des lots : il ne change plus.</small></label>
<?php else: $suggested=$referential['suggestedCode']; ?><label><span>Code <span class="pig-ref-auto-badge" data-pig-code-badge>Automatique</span></span><span class="pig-ref-code"><input name="code" maxlength="10" pattern="[A-Za-z0-9][A-Za-z0-9\-]{0,9}" autocapitalize="characters" autocomplete="off" spellcheck="false" value="<?=e($suggested)?>" placeholder="<?=e($suggested)?>" data-pig-group-code data-suggested-code="<?=e($suggested)?>" aria-describedby="<?=e($formId)?>-code-help"><button type="button" class="btn-secondary pig-ref-code-reset" data-pig-code-reset hidden><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Revenir à <?=e($suggested)?></button></span><small id="<?=e($formId)?>-code-help" data-pig-code-help aria-live="polite">Proposé à la suite des codes existants. Vous pouvez le modifier.</small></label>
<?php endif; ?>
<label><span>Libellé *</span><input name="name" maxlength="80" required value="<?=e($value('name'))?>" placeholder="Ex. Porcs de réforme"><small>Affiché à côté du code dans les écrans et les exports.</small></label>
<label><span>Âge d’entrée *</span><span class="pig-ref-unit"><input type="number" name="min_age_months" min="0" max="<?=PigGroupService::MAX_AGE_MONTHS?>" step="1" inputmode="numeric" required value="<?=e($value('min_age_months'))?>" aria-describedby="<?=e($formId)?>-range"><span aria-hidden="true">mois</span></span><small id="<?=e($formId)?>-range" data-pig-group-range aria-live="polite">Âge à partir duquel les porcs rejoignent ce groupe.</small></label>
<label class="form-wide"><span>Description</span><textarea name="description" maxlength="255" rows="2" placeholder="Ce qui caractérise les porcs de ce groupe (facultatif)"><?=e($value('description'))?></textarea></label>
<fieldset class="form-wide pig-ref-toggles"><legend>Ce que ce groupe autorise</legend>
<?php foreach(PigGroupService::CAPABILITIES as $key=>[$label,$icon,$help]): ?><label class="pig-ref-toggle"><input type="checkbox" name="<?=e($key)?>" value="1"<?=$value($key,0)?' checked':''?>><span class="pig-ref-toggle__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="pig-ref-toggle__text"><strong><?=e($label)?></strong><small><?=e($help)?></small></span></label><?php endforeach; ?>
</fieldset>
<fieldset class="form-wide pig-ref-illustrations"><legend>Illustration</legend>
<?php foreach(PigGroupService::ILLUSTRATIONS as $key=>$label): ?><label class="pig-ref-illustration"><input type="radio" name="illustration" value="<?=e($key)?>" required<?=$value('illustration','young-pig')===$key?' checked':''?>><img src="<?=e(asset_url('illustrations/livestock/'.$key.'.svg'))?>" width="72" height="54" alt="" aria-hidden="true"><span><?=e($label)?></span></label><?php endforeach; ?>
</fieldset>
</div>
<section class="pig-ref-preview" aria-labelledby="<?=e($formId)?>-preview-title"><h3 id="<?=e($formId)?>-preview-title"><i class="bi bi-eye" aria-hidden="true"></i> Aperçu après enregistrement</h3><ol class="pig-timeline pig-timeline--mini" data-pig-preview-timeline></ol><ul class="pig-ref-issues" data-pig-preview-issues aria-live="polite"></ul><p class="pig-ref-impact" data-pig-preview-impact aria-live="polite"></p></section>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary"><?=$editing?'Enregistrer les modifications':'Ajouter le groupe'?></button></footer>
</form></section>
