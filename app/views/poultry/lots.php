<div class="poultry-page compact-page" data-poultry-page>
<?php require __DIR__.'/header.php'; ?>
<?php if($available):
$returnPath='livestock/poultry';$partials=dirname(__DIR__).'/partials';
$byType=static fn($type)=>array_values(array_filter($activeLots,static fn($l)=>$l['flock_type']===$type));
$eggsToday=array_sum(array_column($lots,'eggs_today'));$deadToday=array_sum(array_column($lots,'dead_today'));
?>
<div class="ui-kpi-grid fish-kpis" aria-label="Synthèse des volailles">
<?php foreach([
    ['Poulets de chair',$int(array_sum(array_column($byType('broiler'),'current_heads'))),'bi-egg','',$int(count($byType('broiler'))).' lot(s) en élevage'],
    ['Pondeuses',$int(array_sum(array_column($byType('layer'),'current_heads'))),'bi-egg-fill','',$int(count($byType('layer'))).' lot(s) en ponte'],
    ['Œufs ramassés aujourd’hui',$int($eggsToday),'bi-basket','',$eggsToday?'Saisies du '.date('d/m/Y'):'Aucune saisie aujourd’hui'],
    ['Mortalité du jour',$int($deadToday),'bi-heartbreak',$deadToday?'warning':'',$deadToday?'Oiseaux retirés des lots':'Aucun oiseau mort saisi'],
] as [$label,$value,$icon,$tone,$meta]): ?>
<section class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e($value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></section>
<?php endforeach; ?>
</div>

<section class="fish-section" aria-labelledby="poultry-lots-title"><header class="pig-ref-section-head"><div><h3 id="poultry-lots-title">Lots de volailles</h3><p>Un lot regroupe les oiseaux d’une réception ou d’une éclosion. Il se termine quand tous ses oiseaux sont sortis.</p></div></header>
<?php if(!$lots): ?><div class="fish-empty"><i class="bi bi-egg" aria-hidden="true"></i><div><h3>Aucune volaille dans ce périmètre</h3><p>Réceptionnez les premiers poussins ou les premières pondeuses livrés par un fournisseur.</p><?php if($canReceive): ?><button type="button" class="btn-primary" data-workspace-modal-open="poultry-receive"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Réceptionner des volailles</button><?php endif; ?></div></div>
<?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Lots de volailles" data-search-placeholder="Rechercher un lot, un fournisseur, un poulailler…"><thead><tr><th>Lot</th><th>Type</th><th>Poulailler</th><th class="num">Effectif</th><th class="num">Aujourd’hui</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($lots as $l):$active=$l['status']==='active';$layer=$l['flock_type']==='layer';$site=(int)$l['site_id']; ?>
<tr>
<td data-label="Lot" class="cell--lead"><strong class="cell-main" title="<?=e($origin($l))?>"><?=e($origin($l))?></strong><small class="cell-ref" title="<?=e($l['batch_number'])?>"><?=e($l['batch_number'])?></small></td>
<td data-label="Type" data-order="<?=e($l['flock_type'])?>"><span class="tag-chip poultry-type poultry-type--<?=e($l['flock_type'])?>"><?=e($types[$l['flock_type']])?></span><small><?=$l['age_now']!==null?e('J+'.$l['age_now']):'Depuis le '.e(date('d/m/Y',strtotime($l['received_at'])))?></small></td>
<td data-label="Poulailler" class="cell--wide"><span class="cell-main"><?=e($l['facility_name'])?></span><small><?=e($l['site_name'])?></small></td>
<td data-label="Effectif" class="num" data-order="<?=(int)$l['current_heads']?>"><strong><?=$int($l['current_heads'])?></strong><small>sur <?=$int($l['initial_heads'])?> · mortalité <?=e(number_format($l['mortality_pct'],1,',',' '))?> %</small></td>
<td data-label="Aujourd’hui" class="num" data-order="<?=(int)($layer?$l['eggs_today']:$l['dead_today'])?>"><?php if($layer): ?><strong><?=$int($l['eggs_today'])?></strong><small>œuf(s)<?=(int)$l['dead_today']?' · '.$int($l['dead_today']).' mort(s)':''?></small><?php elseif((int)$l['dead_today']): ?><strong><?=$int($l['dead_today'])?></strong><small>mort(s)</small><?php else: ?><span class="cell-muted">—</span><small><?=$l['last_record_on']?'Dernière saisie '.e(date('d/m',strtotime($l['last_record_on']))):'Aucune saisie'?></small><?php endif; ?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=$active?'progress':'success'?>"><?=$active?($layer?'En ponte':'En élevage'):'Terminé'?></span><?php if((int)$l['pending_heads']||(int)$l['shipping_heads']): ?><small><?=$int((int)$l['pending_heads']+(int)$l['shipping_heads'])?> engagé(s)</small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($active&&Auth::can('livestock','update',$site)): ?><button type="button" class="btn-primary row-action-button" data-workspace-modal-open="poultry-record" data-preselect-field="batch_id" data-preselect-value="<?=e($l['id'])?>"><i class="bi bi-journal-check" aria-hidden="true"></i><span>Saisir</span></button><?php endif; ?>
<?php
$menuId='poultry-lot-menu-'.$l['id'];$menuLabel=$l['batch_number'];$menuItems=[['Consulter le lot','bi-eye','poultry-lot-detail-'.$l['id'],'',false]];
if($active&&$l['available_heads']>0&&$canCreate($l)){$menuItems[]=['Abattre des oiseaux','bi-clipboard-check','poultry-slaughter','',false,['preselect-field'=>'batch_id','preselect-value'=>$l['id']]];if(!$layer)$menuItems[]=['Envoyer vivants','bi-truck','poultry-live-shipment','',false,['preselect-field'=>'batch_id','preselect-value'=>$l['id']]];}
if($layer&&$canCreate($l)&&$houses)$menuItems[]=['Enregistrer une éclosion','bi-egg-fried','poultry-hatch','',false,['preselect-field'=>'mother_batch_id','preselect-value'=>$l['id']]];
require $partials.'/row_menu.php';
?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<section class="fish-section" aria-labelledby="poultry-records-title"><header class="pig-ref-section-head"><div><h3 id="poultry-records-title">Journal des saisies</h3><p>Œufs ramassés et oiseaux morts, saisie par saisie. Plusieurs saisies par jour sont possibles.</p></div></header>
<?php if(!$records): ?><p class="flow-empty">Aucune saisie. Utilisez « Saisie du jour » ou le bouton « Saisir » d’un lot.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Journal des saisies de volailles" data-search-placeholder="Rechercher un lot, un poulailler, une observation…"><thead><tr><th>Date</th><th>Lot</th><th class="num">Œufs</th><th class="num">Morts</th><th>Observation</th><th>Saisi par</th></tr></thead><tbody>
<?php foreach($records as $r): ?>
<tr>
<td data-label="Date" class="cell--lead" data-order="<?=e($r['recorded_on'].' '.$r['created_at'])?>"><strong class="cell-main"><?=e(date('d/m/Y',strtotime($r['recorded_on'])))?></strong><small><?=e(date('H:i',strtotime($r['created_at'])))?></small></td>
<td data-label="Lot" class="cell--wide"><span class="cell-main"><?=e($r['facility_name'])?> <span class="tag-chip poultry-type poultry-type--<?=e($r['flock_type'])?>"><?=e($types[$r['flock_type']])?></span></span><small class="cell-ref"><?=e($r['batch_number'])?></small></td>
<td data-label="Œufs" class="num"><?=(int)$r['eggs_count']?'<strong>'.$int($r['eggs_count']).'</strong>':'<span class="cell-muted">—</span>'?></td>
<td data-label="Morts" class="num"><?=(int)$r['mortality_heads']?'<strong>'.$int($r['mortality_heads']).'</strong>':'<span class="cell-muted">—</span>'?></td>
<td data-label="Observation" class="cell--wide"><span class="cell-main"><?=e($r['notes']?:'—')?></span></td>
<td data-label="Saisi par"><span class="cell-main"><?=e($r['author']?:'—')?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
<?php require __DIR__.'/lot_drawers.php'; ?>
<?php require __DIR__.'/forms.php'; ?>
<?php endif; ?>
</div>
