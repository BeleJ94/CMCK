<?php
$severityLabels = ['danger'=>'Critique','warning'=>'Vigilance','info'=>'Information','success'=>'Positif'];
$active = static function ($a) { return $a['status'] === 'active' && empty($a['resolved_at']); };
$groups = [
    'active'=>['À traiter',array_values(array_filter($alerts,$active))],
    'danger'=>['Critiques actives',array_values(array_filter($alerts,static function($a) use ($active){return $active($a)&&$a['severity']==='danger';}))],
    'warning'=>['Sous vigilance',array_values(array_filter($alerts,static function($a) use ($active){return $active($a)&&$a['severity']==='warning';}))],
    'unread'=>['Actives non lues',array_values(array_filter($alerts,static function($a) use ($active){return $active($a)&&empty($a['read_at']);}))],
];
$date = static function($v){return $v ? date('d/m/Y H:i',strtotime($v)) : '—';};
$oldest = array_filter(array_column($groups['active'][1],'created_at'));
$contextFields = static function() use ($filters) { foreach($filters as $key=>$value) echo '<input type="hidden" name="'.e($key).'" value="'.e($value).'">'; };
?>
<div class="butchery-workspace transfer-workspace alerts-workspace">
<header class="butchery-heading"><span class="butchery-heading-icon"><i class="bi bi-bell" aria-hidden="true"></i></span><div><p>Supervision</p><h2>Alertes</h2></div></header>
<?php if($success):?><div class="app-alert app-alert-success"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="app-alert app-alert-error"><?=e($error)?></div><?php endif;?>
<form method="get" action="<?=e(base_url('alerts'))?>" class="alerts-filters">
<label>Du<input type="date" name="start_date" value="<?=e($filters['start_date'])?>"></label>
<label>Au<input type="date" name="end_date" value="<?=e($filters['end_date'])?>"></label>
<label>Type<select name="type"><option value="">Tous les types</option><?php foreach($types as $key=>$type):?><option value="<?=e($key)?>" <?=$filters['type']===$key?'selected':''?>><?=e($type['label'])?></option><?php endforeach;?></select></label>
<label>Niveau<select name="level"><option value="">Tous les niveaux</option><?php foreach($levels as $level):?><option value="<?=e($level)?>" <?=$filters['level']===$level?'selected':''?>><?=e($severityLabels[$level]??$level)?></option><?php endforeach;?></select></label>
<label>État<select name="state"><?php foreach([''=>'Tous les états','active'=>'À traiter','resolved'=>'Résolues','unread'=>'Actives non lues'] as $key=>$label):?><option value="<?=e($key)?>" <?=($filters['state']??'')===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<button class="btn-primary" type="submit">Appliquer</button><a class="btn-secondary" href="<?=e(base_url('alerts'))?>" aria-label="Réinitialiser les filtres"><i class="bi bi-arrow-counterclockwise"></i></a>
</form>
<div class="butchery-metrics"><?php foreach($groups as $key=>$group):?><button class="butchery-kpi" type="button" data-workspace-modal-open="alerts-kpi-<?=e($key)?>"><strong><?=count($group[1])?></strong><span><?=e($group[0])?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></button><?php endforeach;?></div>
<section class="trace-brief"><h3>Synthèse de pilotage</h3>
<?php if(!$alerts):?><p>Aucune alerte ne correspond aux filtres.</p><?php elseif($groups['active'][1]):?><p><strong><?=count($groups['danger'][1])?> critique(s)</strong> à examiner en priorité ; <?=count($groups['warning'][1])?> alerte(s) sous vigilance. <?=count($groups['unread'][1])?> alerte(s) active(s) restent à lire.</p><?php if($oldest):?><p>Plus ancienne alerte encore active : <?=e($date(min($oldest)))?>.</p><?php endif;?><?php else:?><p>Aucune alerte active dans cette sélection.</p><?php endif;?>
<small>Indicateurs selon les filtres et les sites accessibles. Une alerte lue reste à traiter jusqu’à sa résolution. La recherche du tableau ne modifie pas les indicateurs.</small></section>
<section class="table-panel butchery-history"><header class="fuel-section-heading"><h3>Registre des alertes · <?=count($alerts)?></h3></header><?php $rows=$alerts;require __DIR__.'/table.php';?></section>
<?php foreach($groups as $key=>$group):?>
<section id="alerts-kpi-<?=e($key)?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor butchery-kpi-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="alerts-kpi-title-<?=e($key)?>"><header><h2 id="alerts-kpi-title-<?=e($key)?>"><?=e($group[0])?></h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><div class="feed-form-body"><?php $rows=$group[1];require __DIR__.'/table.php';?></div><footer><button class="btn-secondary" type="button" data-workspace-modal-close>Fermer</button></footer></section>
<?php endforeach;?>
<?php foreach($alerts as $a):?>
<section id="alert-detail-<?=e($a['id'])?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="alert-title-<?=e($a['id'])?>"><header><h2 id="alert-title-<?=e($a['id'])?>">Détail de l’alerte</h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><div class="feed-form-body"><div class="butchery-detail-banner"><strong><?=e($a['title'])?></strong><span class="butchery-status"><?=e($severityLabels[$a['severity']]??$a['severity'])?></span></div><p class="alerts-message"><?=nl2br(e($a['message']))?></p><dl class="butchery-history-details"><?php foreach(['Type'=>$a['type_label'],'État'=>$active($a)?'À traiter':(!empty($a['resolved_at'])?'Résolue':'Inactive'),'Créée le'=>$date($a['created_at']),'Lue le'=>$date($a['read_at']),'Résolue le'=>$date($a['resolved_at']??null)] as $label=>$value):?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?></dl></div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button>
<?php if(empty($a['read_at'])):?><form method="post" action="<?=e(base_url('alerts/'.$a['id'].'/read'))?>"><?=csrf_field()?><?php $contextFields();?><button class="btn-secondary" type="submit">Marquer comme lue</button></form><?php endif;?>
<?php if($active($a)&&Auth::can('alerts','validate',$a['site_id']??null)):?><form method="post" action="<?=e(base_url('alerts/'.$a['id'].'/resolve'))?>" data-confirm="Confirmez que cette alerte a été traitée. Cette action enregistre sa résolution."><?=csrf_field()?><?php $contextFields();?><button class="btn-primary" type="submit">Résoudre l’alerte</button></form><?php endif;?>
</footer></section><?php endforeach;?></div>
