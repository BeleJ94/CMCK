<div class="cattle-page compact-page" data-cattle-page>
<?php require __DIR__.'/header.php'; ?>
<?php if($available):
$returnPath='livestock/cattle';$partials=dirname(__DIR__).'/partials';
$monthStart=date('Y-m-01');
$receivedThisMonth=array_values(array_filter($lots,static fn($l)=>$l['received_at']!==null&&$l['received_at']>=$monthStart));
$pendingSlaughters=count(array_filter($slaughters,static fn($s)=>$s['status']==='submitted'));
?>
<div class="ui-kpi-grid fish-kpis" aria-label="Synthèse des bovins">
<?php foreach([
    ['Bovins vivants',$int(array_sum(array_column($activeLots,'current_heads'))),'bi-heart-pulse','',$int(count($activeLots)).' lot(s) en élevage',null],
    ['Réceptions du mois',$int(count($receivedThisMonth)),'bi-box-arrow-in-down','',$int(array_sum(array_map(static fn($l)=>(int)$l['initial_heads'],$receivedThisMonth))).' tête(s) reçue(s)',null],
    ['Disponibles à l’abattage',$int(array_sum(array_column($activeLots,'available_heads'))),'bi-clipboard-check','','Hors abattages en attente',null],
    ['Abattages à valider',$int($pendingSlaughters),'bi-hourglass-split',$pendingSlaughters?'warning':'','Voir les abattages et envois','livestock/cattle/slaughters'],
] as [$label,$value,$icon,$tone,$meta,$href]):$tag=$href?'a':'section'; ?>
<<?=$tag?> class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"<?=$href?' href="'.e(base_url($href)).'"':''?>><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e($value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></<?=$tag?>>
<?php endforeach; ?>
</div>

<section class="fish-section" aria-labelledby="cattle-lots-title"><header class="pig-ref-section-head"><div><h3 id="cattle-lots-title">Lots de bovins</h3><p>Un lot regroupe les bovins d’une réception fournisseur. Il se termine quand tous ses animaux sont abattus.</p></div></header>
<?php if(!$lots): ?><div class="fish-empty"><i class="bi bi-truck-flatbed" aria-hidden="true"></i><div><h3>Aucun bovin dans ce périmètre</h3><p>Réceptionnez les premiers bœufs livrés par un fournisseur.</p><?php if($canReceive): ?><button type="button" class="btn-primary" data-workspace-modal-open="cattle-receive"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Réceptionner des bœufs</button><?php endif; ?></div></div>
<?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Lots de bovins" data-search-placeholder="Rechercher un fournisseur, un lot, un bâtiment…"><thead><tr><th>Lot</th><th>Arrivée</th><th>Bâtiment</th><th class="num">Effectif</th><th class="num">Poids vif moyen</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($lots as $l):$active=$l['status']==='active';$arrival=$l['received_at']??$l['started_at']; ?>
<tr>
<td data-label="Lot" class="cell--lead"><strong class="cell-main"><?=e($l['supplier_name']?:'Origine non renseignée')?></strong><small class="cell-ref" title="<?=e($l['batch_number'])?>"><?=e($l['batch_number'])?></small></td>
<td data-label="Arrivée" data-order="<?=e($arrival)?>"><span class="cell-main"><?=e(date('d/m/Y',strtotime($arrival)))?></span><small><?=e($l['delivery_reference']?'BL '.$l['delivery_reference']:($l['receipt_id']?'Sans bon de livraison':'Lot antérieur'))?></small></td>
<td data-label="Bâtiment" class="cell--wide"><span class="cell-main"><?=e($l['facility_name'])?></span><small><?=e($l['site_name'])?></small></td>
<td data-label="Effectif" class="num" data-order="<?=(int)$l['current_heads']?>"><strong><?=$int($l['current_heads'])?></strong><small>sur <?=$int($l['initial_heads'])?> reçus<?=(int)$l['pending_heads']?' · '.$int($l['pending_heads']).' en attente':''?></small></td>
<td data-label="Poids vif moyen" class="num" data-order="<?=e((string)$l['average_live_kg'])?>"><?php if($l['average_live_kg']!==null): ?><strong><?=e(number_format($l['average_live_kg'],1,',',' '))?></strong><small>kg / tête</small><?php else: ?><span class="cell-muted">—</span><small>Non pesé</small><?php endif; ?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=$active?'progress':'success'?>"><?=$active?'En élevage':'Terminé'?></span><?php if((int)$l['slaughtered_heads']): ?><small><?=$int($l['slaughtered_heads'])?> abattu(s)</small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($active&&$l['available_heads']>0&&Auth::can('livestock','create',(int)$l['site_id'])): ?><button type="button" class="btn-primary row-action-button" data-workspace-modal-open="cattle-slaughter" data-preselect-field="batch_id" data-preselect-value="<?=e($l['id'])?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Abattre</span></button><?php endif; ?>
<?php $menuId='cattle-lot-menu-'.$l['id'];$menuLabel=$l['batch_number'];$menuItems=[['Consulter le lot','bi-eye','cattle-lot-detail-'.$l['id'],'',false]];require $partials.'/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
<?php require __DIR__.'/lot_drawers.php'; ?>
<?php require __DIR__.'/forms.php'; ?>
<?php endif; ?>
</div>
