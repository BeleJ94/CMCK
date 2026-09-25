<?php
$types=['slaughter'=>'Abattage','fish_catch'=>'Pêche','formal_weighing'=>'Pesée de conversion'];
$sites=array_column(Auth::sites(),'name','id');
$user=Auth::user();
$kg=static fn($v)=>number_format((float)$v,3,',',' ');
?>
<section class="form-panel conversion-workspace" data-livestock-space="conversions">
<div class="conversion-heading"><h3>Historique des conversions</h3>
<?php if(Auth::can('livestock','create')): ?><button type="button" class="btn-primary" data-workspace-modal-open="livestock-conversions"><i class="bi bi-plus-circle" aria-hidden="true"></i> Nouvelle conversion</button><?php endif; ?></div>
<?php if(!$conversions): ?><p class="livestock-guide">Aucune conversion sur ce périmètre. Commencez par sélectionner un lot vivant et saisir sa pesée.</p><?php else: ?>
<table class="enterprise-table" data-conversion-filters data-search-placeholder="Rechercher : référence, lot, site ou statut"><thead><tr><th>Référence / date</th><th>Lot / site</th><th>Opération</th><th>Quantité convertie</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
<?php foreach($conversions as $c): ?><tr data-status="<?=e($c['status'])?>" data-type="<?=e($c['conversion_type'])?>" data-date="<?=e(substr($c['converted_at'],0,10))?>">
<td><strong><?=e($c['conversion_number'])?></strong><small class="conversion-muted"><?=e(date('d/m/Y H:i',strtotime($c['converted_at'])))?></small></td>
<td><?=e($c['batch_number'])?><small class="conversion-muted"><?=e($c['species_name'].' · '.($sites[$c['site_id']]??'—'))?></small></td>
<td><?=e($types[$c['conversion_type']]??$c['conversion_type'])?></td><td><?=e($kg($c['net_weight_kg']))?> kg<small class="conversion-muted"><?=e($c['input_heads'])?> têtes</small></td>
<td><span class="conversion-status <?= $c['status']==='validated'?'is-validated':'is-pending' ?>"><?=e($statusLabels[$c['status']]??$c['status'])?></span></td>
<td><button type="button" class="btn-secondary" data-workspace-modal-open="livestock-conversion-<?=e($c['id'])?>"><i class="bi bi-eye" aria-hidden="true"></i> Consulter</button></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?>
</section>
<?php foreach($conversions as $c):
$canValidate=Auth::can('livestock','validate',$c['site_id']);
$selfBlocked=(int)$c['created_by']===(int)$user['id']&&!Auth::canSelfValidate($user['id']);
?>
<section id="livestock-conversion-<?=e($c['id'])?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="conversion-title-<?=e($c['id'])?>">
<header><div><p class="page-kicker">ÉLEVAGE · CONVERSION</p><h2 id="conversion-title-<?=e($c['id'])?>"><?=e($types[$c['conversion_type']]??$c['conversion_type'])?></h2><p><?=e($c['conversion_number'])?></p></div><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header>
<div class="feed-form-body">
<span class="conversion-status <?= $c['status']==='validated'?'is-validated':'is-pending' ?>"><?=e($statusLabels[$c['status']]??$c['status'])?></span>
<dl class="conversion-facts"><div><dt>Lot · espèce</dt><dd><?=e($c['batch_number'].' · '.$c['species_name'])?></dd></div><div><dt>Site</dt><dd><?=e($sites[$c['site_id']]??'—')?></dd></div><div><dt>Date de l’opération</dt><dd><?=e(date('d/m/Y H:i',strtotime($c['converted_at'])))?></dd></div><div><dt>Têtes converties</dt><dd><?=e($c['input_heads'])?></dd></div></dl>
<div class="conversion-weight"><span>Brut <strong><?=e($kg($c['gross_weight_kg']))?> kg</strong></span><span>− Tare <strong><?=e($kg($c['tare_weight_kg']))?> kg</strong></span><span>= Poids net <strong><?=e($kg($c['net_weight_kg']))?> kg</strong></span></div>
<?php if($c['status']==='submitted'): ?><p class="livestock-guide">La validation retirera <strong><?=e($c['input_heads'])?> têtes</strong> du lot et créera <strong><?=e($kg($c['net_weight_kg']))?> kg</strong> de stock. Aucun mouvement de stock n’a encore été effectué pour cette conversion.</p>
<?php if($selfBlocked): ?><p class="livestock-guide">Vous avez créé cette conversion. Un autre utilisateur habilité doit la valider.</p><?php elseif(!$canValidate): ?><p class="livestock-guide">La validation est réservée aux utilisateurs habilités sur ce site.</p><?php endif; ?>
<?php elseif($c['stock_id']): ?><dl class="conversion-facts"><div><dt>Stock restant</dt><dd><?=e($kg($c['quantity_kg']))?> kg</dd></div><div><dt>Réservé</dt><dd><?=e($kg($c['reserved_kg']))?> kg</dd></div><div><dt>Disponible pour un transfert</dt><dd><?=e($kg(max(0,(float)$c['quantity_kg']-(float)$c['reserved_kg'])))?> kg</dd></div><div><dt>Validée le</dt><dd><?=e($c['validated_at']?date('d/m/Y H:i',strtotime($c['validated_at'])):'—')?></dd></div></dl><?php endif; ?>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button>
<?php if($c['status']==='submitted'&&$canValidate&&!$selfBlocked): ?><form method="post" action="<?=e(base_url('livestock/conversions/'.$c['id'].'/validate'))?>" data-confirm="<?=e('Valider cette conversion ? '.$c['input_heads'].' têtes seront retirées du lot '.$c['batch_number'].' et '.$kg($c['net_weight_kg']).' kg de stock seront créés.')?>"><?=csrf_field()?><button type="submit" class="btn-primary"><i class="bi bi-check2-circle" aria-hidden="true"></i> Valider et créer le stock</button></form><?php endif; ?>
</footer></section>
<?php endforeach; ?>
