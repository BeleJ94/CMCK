<div class="fish-page compact-page" data-fish-page>
<?php require __DIR__.'/header.php'; ?>
<?php if($available):
$returnPath='livestock/fish/harvests';$flowViews=dirname(__DIR__).'/porcine_flow';
$pending=array_values(array_filter($harvests,static fn($h)=>$h['status']==='submitted'));
$farmStock=array_sum(array_map(static fn($h)=>$h['status']==='validated'&&in_array($h['stock_status'],['available','reserved'],true)?(float)$h['stock_kg']:0,$harvests));
$moving=count(array_filter($shipments,static fn($t)=>in_array($t['status'],['draft','approved','in_transit'],true)));
$weighed=array_values(array_filter($shipments,static fn($t)=>$t['weight_gap_kg']!==null));
$sentWeighed=array_sum(array_map(static fn($t)=>(float)$t['quantity_kg'],$weighed));$gapWeighed=array_sum(array_map(static fn($t)=>(float)$t['weight_gap_kg'],$weighed));
$harvestStates=['submitted'=>['À valider','warning'],'validated'=>['Validée','success'],'cancelled'=>['Annulée','neutral']];
$shipmentStates=['draft'=>['À approuver','warning'],'approved'=>['À expédier','warning'],'in_transit'=>['En route','progress'],'received'=>['Reçu','success'],'cancelled'=>['Annulé','neutral']];
$today=new DateTimeImmutable('today');
// Remaining shelf life, shown under the date with a warning tone from the day before expiry.
$shelf=static function($date)use($today){$days=(int)$today->diff(new DateTimeImmutable($date))->format('%r%a');return [$days<0?'Dépassée':($days===0?'Aujourd’hui':($days===1?'Demain':'Dans '.$days.' j')),$days<=1?'is-warning':''];};
$stamp=static fn($at)=>date('d/m/Y',strtotime($at)).' · '.date('H:i',strtotime($at));
?>
<div class="ui-kpi-grid fish-kpis" aria-label="Synthèse des pêches et envois">
<?php foreach([
    ['Pêches à valider',$int(count($pending)),'bi-clipboard-check',count($pending)?'warning':'','Validation par un autre utilisateur autorisé'],
    ['Poisson pesé à la ferme',$kg($farmStock).' kg','bi-box-seam','','Stock disponible pour un envoi'],
    ['Envois en cours',$int($moving),'bi-truck','','À approuver, à expédier ou en route'],
    ['Écart ferme → boucherie',$weighed?$kg($gapWeighed).' kg':'—','bi-speedometer2',$weighed&&$gapWeighed>0?'warning':'',$weighed?number_format($sentWeighed>0?$gapWeighed/$sentWeighed*100:0,2,',',' ').' % sur '.count($weighed).' envoi(s) pesé(s)':'Aucun envoi pesé à l’arrivée'],
] as [$label,$value,$icon,$tone,$meta]): ?>
<section class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e($value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></section>
<?php endforeach; ?>
</div>

<section class="fish-section" aria-labelledby="fish-harvests-title"><header class="pig-ref-section-head"><div><h3 id="fish-harvests-title">Pêches</h3><p>Une pêche validée retire les poissons du lot et crée le stock pesé à la ferme.</p></div></header>
<?php if(!$harvests): ?><p class="flow-empty">Aucune pêche enregistrée. Utilisez « Enregistrer une pêche » ou le bouton « Pêcher » d’une parcelle.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Pêches" data-search-placeholder="Rechercher une pêche, un lot, un étang…"><thead><tr><th>Pêche</th><th>Lot</th><th class="num">Poissons</th><th class="num">Poids net</th><th>DLC</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($harvests as $h):[$stateLabel,$stateTone]=$harvestStates[$h['status']]??[$h['status'],'neutral'];$site=(int)$h['site_id'];$availableKg=round((float)$h['stock_kg']-(float)$h['stock_reserved_kg'],3);[$shelfLabel,$shelfTone]=$shelf($h['expiry_date']);
$canValidate=$h['status']==='submitted'&&Auth::can('livestock','validate',$site);$canShip=$h['status']==='validated'&&$availableKg>0&&$h['expiry_date']>=date('Y-m-d')&&Auth::can('livestock','create',$site);$canCancel=$h['status']==='submitted'&&Auth::can('livestock','delete',$site); ?>
<tr>
<td data-label="Pêche" class="cell--lead" data-order="<?=e($h['caught_at'])?>"><strong class="cell-main"><?=e($stamp($h['caught_at']))?></strong><small class="cell-ref" title="<?=e($h['catch_number'])?>"><?=e($h['catch_number'])?></small></td>
<td data-label="Lot" class="cell--wide"><span class="cell-main"><?=e($h['pond_name'].' · '.$h['parcel_code'])?></span><small class="cell-ref" title="<?=e($h['batch_number'])?>"><?=e($h['species_name'].' · '.$h['batch_number'])?></small></td>
<td data-label="Poissons" class="num" data-order="<?=(int)$h['heads_caught']?>"><strong><?=$int($h['heads_caught'])?></strong><?php if((int)$h['final_harvest']): ?><small class="fish-tag">Pêche finale</small><?php endif; ?></td>
<td data-label="Poids net" class="num" data-order="<?=e($h['net_weight_kg'])?>"><strong><?=$kg($h['net_weight_kg'])?></strong><small>kg<?=(float)$h['tare_weight_kg']>0?' · tare '.$kg($h['tare_weight_kg']):''?></small></td>
<td data-label="DLC" data-order="<?=e($h['expiry_date'])?>"><span class="cell-main"><?=e(date('d/m/Y',strtotime($h['expiry_date'])))?></span><?php if($h['status']!=='cancelled'&&!($h['status']==='validated'&&$availableKg<=0)): ?><small class="<?=e($shelfTone)?>"><?=e($shelfLabel)?></small><?php endif; ?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><?php if($h['status']==='validated'&&$h['weight_stock_id']): ?><small><?=$availableKg>0?$kg($availableKg).' kg à envoyer':'Entièrement envoyée'?></small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canValidate):$rowAction='livestock/fish/harvests/'.$h['conversion_id'].'/validate';$rowConfirm=(int)$h['final_harvest']?'Valider cette pêche finale ? Le lot sera clôturé et les poissons non pêchés comptés comme morts.':'Valider cette pêche et créer le stock pesé ?';$rowLabel='Valider';$rowIcon='bi-check2-circle';require dirname(__DIR__).'/partials/row_form.php';
elseif($canShip): ?><button type="button" class="btn-primary row-action-button" data-workspace-modal-open="fish-shipment" data-fish-stock="<?=e($h['weight_stock_id'])?>"><i class="bi bi-truck" aria-hidden="true"></i><span>Envoyer</span></button>
<?php endif; ?>
<?php $menuId='fish-harvest-menu-'.$h['id'];$menuLabel=$h['catch_number'];$menuItems=[['Consulter la pêche','bi-eye','fish-harvest-detail-'.$h['id'],'',false]];if($canCancel)$menuItems[]=['Annuler la pêche','bi-x-circle','fish-harvest-cancel-'.$h['id'],'danger',false];require dirname(__DIR__).'/partials/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<section class="fish-section" aria-labelledby="fish-shipments-title"><header class="pig-ref-section-head"><div><h3 id="fish-shipments-title">Envois à la boucherie</h3><p>Le poisson est pesé à la ferme puis à la boucherie : l’écart mesure les pertes de transport.</p></div></header>
<?php if(!$shipments): ?><p class="flow-empty">Aucun envoi. Préparez un envoi depuis une pêche validée.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Envois de poisson" data-search-placeholder="Rechercher un BTR, un camion, un chauffeur…"><thead><tr><th>Envoi</th><th>Origine</th><th>Transport</th><th class="num">Ferme</th><th class="num">Boucherie</th><th>Écart</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($shipments as $t):[$stateLabel,$stateTone]=$shipmentStates[$t['status']]??[$t['status'],'neutral'];$site=(int)$t['source_site_id'];$gap=$t['weight_gap_kg']===null?null:['kg'=>(float)$t['weight_gap_kg'],'pct'=>(float)$t['weight_gap_pct']];$exceeded=$gap!==null&&TransitWeighingService::exceeds($gap,$t['tolerance_pct']===null?null:(float)$t['tolerance_pct']);
$canApprove=$t['status']==='draft'&&Auth::can('livestock','validate',$site);$canDispatch=$t['status']==='approved'&&Auth::can('livestock','update',$site);$canCancel=in_array($t['status'],['draft','approved'],true)&&Auth::can('livestock','delete',$site); ?>
<tr>
<td data-label="Envoi" class="cell--lead" data-order="<?=e($t['created_at'])?>"><strong class="cell-main"><?=e($stamp($t['dispatched_at']??$t['created_at']))?></strong><small class="cell-ref" title="<?=e($t['transfer_number'])?>"><?=e($t['transfer_number'])?></small></td>
<td data-label="Origine" class="cell--wide"><span class="cell-main"><?=e($t['pond_name'].' · '.$t['parcel_code'])?></span><small class="cell-ref" title="<?=e($t['catch_number'])?>"><?=e($t['catch_number'])?></small></td>
<td data-label="Transport" class="cell--wide"><span class="cell-main"><?=e($t['truck_plate'])?></span><small><?=e($t['driver_name'])?></small></td>
<td data-label="Ferme" class="num" data-order="<?=e($t['quantity_kg'])?>"><strong><?=$kg($t['quantity_kg'])?></strong><small>kg</small></td>
<td data-label="Boucherie" class="num" data-order="<?=e((string)$t['received_weight_kg'])?>"><?php if($gap!==null): ?><strong><?=$kg($t['received_weight_kg'])?></strong><small>kg</small><?php else: ?><span class="cell-muted">—</span><small><?=$t['status']==='in_transit'?'À peser':'—'?></small><?php endif; ?></td>
<td data-label="Écart" data-order="<?=$gap!==null?e($gap['pct']):''?>"><?php if($gap!==null): ?><span class="weighing-gap-chip <?=$exceeded?'is-exceeded':'is-within'?>"><?=e(TransitWeighingService::describe($gap))?></span><?php else: ?><span class="cell-muted">—</span><?php endif; ?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><?php if($t['status']==='in_transit'&&$t['receipt_status']==='sanitary_pending'): ?><small>Contrôle en cours</small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canApprove):$rowAction='livestock/fish/shipments/'.$t['id'].'/approve';$rowConfirm='Approuver cet envoi et réserver le poisson ?';$rowLabel='Approuver';$rowIcon='bi-check2-circle';require dirname(__DIR__).'/partials/row_form.php';
elseif($canDispatch):$rowAction='livestock/fish/shipments/'.$t['id'].'/dispatch';$rowConfirm='Confirmer le départ du poisson vers la boucherie ?';$rowLabel='Expédier';$rowIcon='bi-truck';require dirname(__DIR__).'/partials/row_form.php';endif; ?>
<?php $menuId='fish-shipment-menu-'.$t['id'];$menuLabel=$t['transfer_number'];$menuItems=[['Consulter l’envoi','bi-eye','fish-shipment-detail-'.$t['id'],'',false],['Bon de transfert','bi-file-earmark-text','btr-preview','',false,['btr-url'=>base_url('livestock/fish/shipments/'.$t['id'].'/btr'),'btr-title'=>$t['transfer_number']]]];if($canCancel)$menuItems[]=['Annuler l’envoi','bi-x-circle','fish-shipment-cancel-'.$t['id'],'danger',false];require dirname(__DIR__).'/partials/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
<?php require __DIR__.'/harvest_drawers.php'; ?>
<?php require __DIR__.'/forms.php'; ?>
<?php endif; ?>
</div>
