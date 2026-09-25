<?php
$fuelLabels=['draft'=>'Brouillon','submitted'=>'À valider','approved'=>'Approuvé','partially_received'=>'Réception partielle','received'=>'Reçu','in_progress'=>'En cours','completed'=>'Terminé','justification_pending'=>'À justifier','settled'=>'Soldé','cancelled'=>'Annulé'];
$fuelModals=[];
$fuelForm=static function($title,$body,$permission)use(&$fuelModals){if(!Auth::can('fuel-logistics',$permission))return;$id='fuel-form-'.count($fuelModals);ob_start();?>
<section id="<?=e($id)?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?=e($id)?>-title"><header><h2 id="<?=e($id)?>-title"><?=e($title)?></h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><?=$body?></section>
<?php $fuelModals[]=ob_get_clean();?><button type="button" class="btn-secondary" data-workspace-modal-open="<?=e($id)?>"><?=e(explode(' · ',$title)[0])?></button><?php };
