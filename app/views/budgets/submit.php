<form method="post" action="<?=e(base_url('budgets/'.$b['id'].'/submit'))?>" data-budget-form data-budget-submit data-native-submit>
<?=csrf_field()?>
<div class="feed-form-body">
<p class="app-alert app-alert-error" data-budget-error role="alert" hidden></p>
<div class="budget-submit-summary"><div><small>Budget en préparation</small><strong><?=e($b['budget_number'])?></strong></div><div><small>Montant proposé</small><strong><?=e(number_format($b['total_proposed'],2,',',' '))?></strong></div></div>
<dl class="butchery-history-details budget-submit-details">
<?php foreach(['Site'=>$b['site_code'],'Centre de coût'=>$b['center_code'],'Période'=>$b['period_name'],'Révision'=>$b['revision_number']] as $label=>$value):?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?>
</dl>
<p class="budget-submit-next"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i><span>Le budget sera transmis à la Direction financière pour validation.</span></p>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary">Soumettre à la DF</button></footer>
</form>
