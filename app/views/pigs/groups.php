<?php
$canAdmin=$available&&$referential['canAdminister'];
$allGroups=$available?$referential['groups']:[];
$active=array_values(array_filter($allGroups,static fn($g)=>$g['status']==='active'));
$inactive=array_values(array_filter($allGroups,static fn($g)=>$g['status']!=='active'));
// The open-ended last group is drawn three months wide on the age journey.
$journeyEnd=($active?end($active)['min_age_months']:0)+3;
$actionLabels=['pig_group_create'=>['Création','success'],'pig_group_update'=>['Modification','progress'],'pig_group_activate'=>['Réactivation','success'],'pig_group_deactivate'=>['Désactivation','warning'],'pig_group_delete'=>['Suppression','neutral']];
$clientGroups=array_map(static fn($g)=>['id'=>$g['id'],'code'=>$g['code'],'name'=>$g['name'],'min'=>$g['min_age_months'],'status'=>$g['status'],'accepts_births'=>$g['accepts_births'],'allows_breeding'=>$g['allows_breeding'],'allows_slaughter'=>$g['allows_slaughter']],$allGroups);
?>
<div class="pig-directory pig-compact pig-referential" data-pig-referential data-groups="<?=e(json_encode($clientGroups))?>" data-lots="<?=e(json_encode($available?$referential['lots']:[]))?>" data-today="<?=e(date('Y-m-d'))?>">
<section class="dashboard-hero ui-page-header"><img class="pig-header-art" src="<?=e(asset_url('illustrations/livestock/pig-farm.svg'))?>" width="80" height="60" alt="" aria-hidden="true"><div class="ui-page-header__content"><p class="section-label">ÉLEVAGE · PORCS</p><h2>Groupes d’âge</h2><p>Chaque lot est classé selon l’âge de ses porcs. Le changement de groupe est ensuite confirmé sur le terrain.</p></div><div class="ui-page-header__actions"><a class="btn-secondary" href="<?=e(base_url('livestock/pigs'))?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Effectifs et groupes</a><?php if($canAdmin): ?><button type="button" class="btn-primary" data-workspace-modal-open="pig-group-new"><i class="bi bi-plus-circle" aria-hidden="true"></i> Ajouter un groupe</button><?php endif; ?></div></section>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?>
<?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if(!$available): ?><p class="app-alert" role="status">Le suivi porcin est en cours d’activation.</p><?php else: ?>
<?php if(!$referential['installed']): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Les règles historiques A, B et C s’appliquent. Pour les modifier, appliquez la migration <code>041_add_pig_group_referential.sql</code>.</p>
<?php elseif(!$canAdmin): ?><p class="app-alert alert-info" role="status"><i class="bi bi-eye" aria-hidden="true"></i> Consultation seule. Ces règles s’appliquent aux deux fermes : leur modification est réservée aux administrateurs de l’élevage.</p><?php endif; ?>

<section class="pig-ref-panel" aria-labelledby="pig-ref-journey-title">
<header class="pig-ref-section-head"><div><h3 id="pig-ref-journey-title">Parcours par âge</h3><p>De la naissance à l’abattage. Le dernier groupe n’a pas de limite d’âge.</p></div></header>
<ol class="pig-timeline">
<?php foreach($active as $g): ?>
<li class="pig-timeline__step" style="--pig-span:<?=max(1,($g['max_age_months']??$journeyEnd)-$g['min_age_months'])?>"><span class="pig-timeline__bar"><span class="pig-timeline__code"><?=e($g['code'])?></span></span><strong><?=e($g['name'])?></strong><small><?=e($g['age_label'])?></small><span class="pig-timeline__caps"><?php foreach(PigGroupService::CAPABILITIES as $key=>[$label,$icon]): if(!$g[$key])continue; ?><span class="pig-capability-chip"><i class="bi <?=e($icon)?>" aria-hidden="true"></i> <?=e($label)?></span><?php endforeach; ?></span></li>
<?php endforeach; ?>
</ol>
<ol class="pig-ref-steps"><li><i class="bi bi-calendar-event" aria-hidden="true"></i> La date de naissance donne le groupe attendu.</li><li><i class="bi bi-clipboard2-check" aria-hidden="true"></i> Le passage est confirmé après vérification des animaux.</li><li><i class="bi bi-toggles" aria-hidden="true"></i> Les capacités du groupe autorisent les opérations.</li></ol>
</section>

<?php $failedChecks=count(array_filter($referential['checks'],static fn($c)=>!$c['ok'])); ?>
<details class="pig-ref-checks-wrap<?=$failedChecks?' is-ko':''?>"<?=$failedChecks?' open':''?>><summary><i class="bi <?=$failedChecks?'bi-exclamation-triangle-fill':'bi-shield-check'?>" aria-hidden="true"></i> <?=$failedChecks?$failedChecks.' contrôle(s) de cohérence à corriger':'Référentiel cohérent : '.count($referential['checks']).' contrôles respectés'?></summary>
<div class="pig-ref-checks"><?php foreach($referential['checks'] as $check): ?><div class="pig-ref-check <?=$check['ok']?'is-ok':'is-ko'?>"><i class="bi <?=$check['ok']?'bi-check-circle-fill':'bi-exclamation-triangle-fill'?>" aria-hidden="true"></i><div><strong><?=e($check['label'])?></strong><small><?=e($check['detail'])?></small></div></div><?php endforeach; ?></div>
</details>

<section aria-labelledby="pig-ref-active-title">
<header class="pig-ref-section-head"><div><h3 id="pig-ref-active-title">Groupes actifs <span class="pig-ref-count"><?=count($active)?></span></h3><p>Les compteurs portent sur les lots vivants de votre périmètre.</p></div></header>
<div class="pig-ref-grid"><?php foreach($active as $g)require __DIR__.'/group_card.php'; ?></div>
</section>
<?php if($inactive): ?>
<details class="pig-ref-inactive"><summary>Groupes désactivés <span class="pig-ref-count"><?=count($inactive)?></span></summary><p>Un groupe désactivé ne classe plus aucun lot. Les lots qu’il contient encore sont signalés pour être reclassés sur le terrain.</p><div class="pig-ref-grid"><?php foreach($inactive as $g)require __DIR__.'/group_card.php'; ?></div></details>
<?php endif; ?>

<?php if($referential['history']): ?>
<section class="pig-ref-panel pig-ref-history" aria-labelledby="pig-ref-history-title"><header class="pig-ref-section-head"><div><h3 id="pig-ref-history-title">Dernières modifications</h3><p>Chaque modification est enregistrée dans l’audit du système.</p></div></header>
<ol><?php foreach($referential['history'] as $entry):[$actionLabel,$tone]=$actionLabels[$entry['action']]??[$entry['action'],'neutral']; ?><li><span class="ui-status ui-status--<?=e($tone)?>"><?=e($actionLabel)?></span><span><?=e($entry['description'])?></span><small><?=e(($entry['user_name']?:'Système').' · '.date('d/m/Y H:i',strtotime($entry['created_at'])))?></small></li><?php endforeach; ?></ol></section>
<?php endif; ?>

<?php if($canAdmin): $g=null;require __DIR__.'/group_form.php';foreach($allGroups as $g)require __DIR__.'/group_form.php'; ?><div class="entity-modal-backdrop workspace-modal-backdrop" data-workspace-modal-close></div><?php endif; ?>
<?php endif; ?>
</div>
