<?php
$revision=$b['status']==='active';$dg=$b['status']==='pending_dg';
$action=$revision?'revisions':($dg?'approve-dg':'approve-df');
$amount=$revision||$dg?$b['total_validated']:$b['total_proposed'];
?>
<form method="post" action="<?=e(base_url('budgets/'.$b['id'].'/'.$action))?>" data-budget-form data-budget-review <?=$revision?'data-budget-revision':''?> data-current-amount="<?=e($amount)?>" data-native-submit>
<?=csrf_field()?>
<div class="feed-form-body"><p class="app-alert app-alert-error" data-budget-error role="alert" hidden></p>
<div class="budget-submit-summary"><div><small><?=e($budgetLabels[$b['status']])?></small><strong><?=e($b['budget_number'])?></strong></div><div><small><?=$revision?'Montant actuel':($dg?'Montant validé par la DF':'Montant proposé')?></small><strong><?=e(number_format($amount,2,',',' '))?></strong></div></div>
<dl class="butchery-history-details budget-submit-details"><?php foreach(['Site'=>$b['site_code'],'Centre de coût'=>$b['center_code'],'Période'=>$b['period_name'],'Révision'=>$b['revision_number']] as $label=>$value):?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?></dl>
<?php if($revision):?>
<div class="form-grid"><label><span>Nouveau montant *</span><input name="amount" type="number" step="0.01" min="0.01" required value="<?=e(number_format((float)$amount,2,'.',''))?>" aria-describedby="budget-revision-difference-<?=e($b['id'])?>"></label><label><span>Motif *</span><textarea name="reason" rows="2" required placeholder="Pourquoi réviser ce budget ?"></textarea></label></div>
<p id="budget-revision-difference-<?=e($b['id'])?>" class="budget-revision-difference" data-budget-difference aria-live="polite">Montant inchangé</p>
<p class="budget-submit-next"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Crée une nouvelle version en préparation, à soumettre pour validation. Le budget actuel reste actif.</span></p>
<?php else:?><p class="budget-submit-next"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i><span><?=$dg?'Cette validation active le budget.':'Le budget sera activé ou transmis à la DG selon le seuil de validation applicable.'?></span></p><?php endif;?>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary"><?=$revision?'Créer la révision':($dg?'Valider et activer':'Confirmer la validation DF')?></button></footer></form>
