<?php
$budgetLabels=['preparation'=>'En préparation','submitted'=>'Soumis','pending_df'=>'À valider · DF','pending_dg'=>'À valider · DG','active'=>'Actif','rejected'=>'Refusé','replaced'=>'Remplacé','cancelled'=>'Annulé'];
$budgetModals=[];
$budgetModal=static function($id,$title,$body)use(&$budgetModals){ob_start();?>
<section id="<?=e($id)?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor butchery-kpi-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?=e($id)?>-title"><header><h2 id="<?=e($id)?>-title"><?=e($title)?></h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><?=$body?></section>
<?php $budgetModals[]=ob_get_clean();};
$budgetForm=static function($title,$body,$permission,$buttonLabel=null)use(&$budgetModals,$budgetModal){if(!Auth::can('budgets',$permission))return;$id='budget-form-'.count($budgetModals);$budgetModal($id,$title,$body);?><button type="button" class="btn-secondary" data-workspace-modal-open="<?=e($id)?>"><?=e($buttonLabel??$title)?></button><?php };
