<div class="cattle-page compact-page" data-cattle-page>
<?php require __DIR__.'/header.php'; ?>
<?php if($available):
$returnPath='livestock/cattle/slaughters';$partials=dirname(__DIR__).'/partials';
$pending=array_values(array_filter($slaughters,static fn($s)=>$s['status']==='submitted'));
$farmStock=array_sum(array_map(static fn($s)=>$s['status']==='validated'&&in_array($s['stock_status'],['available','reserved'],true)?(float)$s['stock_kg']:0,$slaughters));
$moving=count(array_filter($shipments,static fn($t)=>in_array($t['status'],['draft','approved','in_transit'],true)));
$weighed=array_values(array_filter($shipments,static fn($t)=>$t['weight_gap_kg']!==null));
$sentWeighed=array_sum(array_map(static fn($t)=>(float)$t['quantity_kg'],$weighed));$gapWeighed=array_sum(array_map(static fn($t)=>(float)$t['weight_gap_kg'],$weighed));
$slaughterStates=['submitted'=>['À valider','warning'],'validated'=>['Validé','success'],'cancelled'=>['Annulé','neutral']];
$shipmentStates=['draft'=>['À approuver','warning'],'approved'=>['À expédier','warning'],'in_transit'=>['En route','progress'],'received'=>['Reçu','success'],'cancelled'=>['Annulé','neutral']];
$today=new DateTimeImmutable('today');
$shelf=static function($date)use($today){$days=(int)$today->diff(new DateTimeImmutable($date))->format('%r%a');return [$days<0?'Dépassée':($days===0?'Aujourd’hui':($days===1?'Demain':'Dans '.$days.' j')),$days<=1?'is-warning':''];};
$stamp=static fn($at)=>date('d/m/Y',strtotime($at)).' · '.date('H:i',strtotime($at));
?>
<div class="ui-kpi-grid fish-kpis" aria-label="Synthèse des abattages et envois">
<?php foreach([
    ['Abattages à valider',$int(count($pending)),'bi-hourglass-split',count($pending)?'warning':'','Validation par un autre utilisateur autorisé'],
    ['Carcasses à la ferme',$kg($farmStock).' kg','bi-box-seam','','Stock disponible pour un envoi'],
    ['Envois en cours',$int($moving),'bi-truck','','À approuver, à expédier ou en route'],
    ['Écart ferme → boucherie',$weighed?$kg($gapWeighed).' kg':'—','bi-speedometer2',$weighed&&$gapWeighed>0?'warning':'',$weighed?number_format($sentWeighed>0?$gapWeighed/$sentWeighed*100:0,2,',',' ').' % sur '.count($weighed).' envoi(s) pesé(s)':'Aucun envoi pesé à l’arrivée'],
] as [$label,$value,$icon,$tone,$meta]): ?>
<section class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e($value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></section>
<?php endforeach; ?>
</div>

<section class="fish-section" aria-labelledby="cattle-slaughters-title"><header class="pig-ref-section-head"><div><h3 id="cattle-slaughters-title">Abattages</h3><p>Un abattage validé retire les bovins du lot et crée le stock de carcasses à la ferme.</p></div></header>
<?php if(!$slaughters): ?><p class="flow-empty">Aucun abattage enregistré. Utilisez « Enregistrer un abattage » ou le bouton « Abattre » d’un lot.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Abattages de bovins" data-search-placeholder="Rechercher un abattage, un fournisseur, un lot…"><thead><tr><th>Abattage</th><th>Lot</th><th class="num">Bovins</th><th class="num">Carcasses</th><th>DLC</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($slaughters as $s):[$stateLabel,$stateTone]=$slaughterStates[$s['status']]??[$s['status'],'neutral'];$site=(int)$s['site_id'];$availableKg=round((float)$s['stock_kg']-(float)$s['stock_reserved_kg'],3);[$shelfLabel,$shelfTone]=$shelf($s['expiry_date']);
$canValidate=$s['status']==='submitted'&&Auth::can('livestock','validate',$site);$canShip=$s['status']==='validated'&&$availableKg>0&&$s['expiry_date']>=date('Y-m-d')&&Auth::can('livestock','create',$site);$canCancel=$s['status']==='submitted'&&Auth::can('livestock','delete',$site); ?>
<tr>
<td data-label="Abattage" class="cell--lead" data-order="<?=e($s['converted_at'])?>"><strong class="cell-main"><?=e($stamp($s['converted_at']))?></strong><small class="cell-ref" title="<?=e($s['conversion_number'])?>"><?=e($s['conversion_number'])?></small></td>
<td data-label="Lot" class="cell--wide"><span class="cell-main" title="<?=e(($s['supplier_name']?:'Lot antérieur').' · '.$s['facility_name'])?>"><?=e(($s['supplier_name']?:'Lot antérieur').' · '.$s['facility_name'])?></span><small class="cell-ref" title="<?=e($s['batch_number'])?>"><?=e($s['batch_number'])?></small></td>
<td data-label="Bovins" class="num" data-order="<?=(int)$s['input_heads']?>"><strong><?=$int($s['input_heads'])?></strong><small>tête(s)</small></td>
<td data-label="Carcasses" class="num" data-order="<?=e($s['net_weight_kg'])?>"><strong><?=$kg($s['net_weight_kg'])?></strong><small>kg<?=(float)$s['tare_weight_kg']>0?' · tare '.$kg($s['tare_weight_kg']):''?></small></td>
<td data-label="DLC" data-order="<?=e($s['expiry_date'])?>"><span class="cell-main"><?=e(date('d/m/Y',strtotime($s['expiry_date'])))?></span><?php if($s['status']!=='cancelled'&&!($s['status']==='validated'&&$availableKg<=0)): ?><small class="<?=e($shelfTone)?>"><?=e($shelfLabel)?></small><?php endif; ?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><?php if($s['status']==='validated'&&$s['weight_stock_id']): ?><small><?=$availableKg>0?$kg($availableKg).' kg à envoyer':'Entièrement envoyé'?></small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canValidate):$rowAction='livestock/cattle/slaughters/'.$s['id'].'/validate';$rowConfirm='Valider cet abattage ? Les bovins quittent l’effectif et le stock de carcasses est créé.';$rowLabel='Valider';$rowIcon='bi-check2-circle';require $partials.'/row_form.php';
elseif($canShip): ?><button type="button" class="btn-primary row-action-button" data-workspace-modal-open="cattle-shipment" data-preselect-field="weight_stock_id" data-preselect-value="<?=e($s['weight_stock_id'])?>"><i class="bi bi-truck" aria-hidden="true"></i><span>Envoyer</span></button>
<?php endif; ?>
<?php $menuId='cattle-slaughter-menu-'.$s['id'];$menuLabel=$s['conversion_number'];$menuItems=[['Consulter l’abattage','bi-eye','cattle-slaughter-detail-'.$s['id'],'',false]];if($canCancel)$menuItems[]=['Annuler l’abattage','bi-x-circle','cattle-slaughter-cancel-'.$s['id'],'danger',false];require $partials.'/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<section class="fish-section" aria-labelledby="cattle-shipments-title"><header class="pig-ref-section-head"><div><h3 id="cattle-shipments-title">Envois à la boucherie</h3><p>Les carcasses sont pesées à la ferme puis à la boucherie : l’écart mesure les pertes de transport.</p></div></header>
<?php if(!$shipments): ?><p class="flow-empty">Aucun envoi. Préparez un envoi depuis un abattage validé.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Envois de carcasses de bœuf" data-search-placeholder="Rechercher un BTR, un camion, un chauffeur…"><thead><tr><th>Envoi</th><th>Origine</th><th>Transport</th><th class="num">Ferme</th><th class="num">Boucherie</th><th>Écart</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($shipments as $t):[$stateLabel,$stateTone]=$shipmentStates[$t['status']]??[$t['status'],'neutral'];$site=(int)$t['source_site_id'];$gap=$t['weight_gap_kg']===null?null:['kg'=>(float)$t['weight_gap_kg'],'pct'=>(float)$t['weight_gap_pct']];$exceeded=$gap!==null&&TransitWeighingService::exceeds($gap,$t['tolerance_pct']===null?null:(float)$t['tolerance_pct']);
$canApprove=$t['status']==='draft'&&Auth::can('livestock','validate',$site);$canDispatch=$t['status']==='approved'&&Auth::can('livestock','update',$site);$canCancel=in_array($t['status'],['draft','approved'],true)&&Auth::can('livestock','delete',$site); ?>
<tr>
<td data-label="Envoi" class="cell--lead" data-order="<?=e($t['created_at'])?>"><strong class="cell-main"><?=e($stamp($t['dispatched_at']??$t['created_at']))?></strong><small class="cell-ref" title="<?=e($t['transfer_number'])?>"><?=e($t['transfer_number'])?></small></td>
<td data-label="Origine" class="cell--wide"><span class="cell-main" title="<?=e($t['supplier_name']?:'Lot antérieur')?>"><?=e($t['supplier_name']?:'Lot antérieur')?></span><small title="<?=e($t['facility_name'].' · '.$t['conversion_number'])?>"><?=e($t['facility_name'])?></small></td>
<td data-label="Transport" class="cell--wide"><span class="cell-main"><?=e($t['truck_plate'])?></span><small><?=e($t['driver_name'])?></small></td>
<td data-label="Ferme" class="num" data-order="<?=e($t['quantity_kg'])?>"><strong><?=$kg($t['quantity_kg'])?></strong><small>kg</small></td>
<td data-label="Boucherie" class="num" data-order="<?=e((string)$t['received_weight_kg'])?>"><?php if($gap!==null): ?><strong><?=$kg($t['received_weight_kg'])?></strong><small>kg</small><?php else: ?><span class="cell-muted">—</span><small><?=$t['status']==='in_transit'?'À peser':'—'?></small><?php endif; ?></td>
<td data-label="Écart" data-order="<?=$gap!==null?e($gap['pct']):''?>"><?php if($gap!==null): ?><span class="weighing-gap-chip <?=$exceeded?'is-exceeded':'is-within'?>"><?=e(TransitWeighingService::describe($gap))?></span><?php else: ?><span class="cell-muted">—</span><?php endif; ?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><?php if($t['status']==='in_transit'&&$t['receipt_status']==='sanitary_pending'): ?><small>Contrôle en cours</small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canApprove):$rowAction='livestock/cattle/shipments/'.$t['id'].'/approve';$rowConfirm='Approuver cet envoi et réserver les carcasses ?';$rowLabel='Approuver';$rowIcon='bi-check2-circle';require $partials.'/row_form.php';
elseif($canDispatch):$rowAction='livestock/cattle/shipments/'.$t['id'].'/dispatch';$rowConfirm='Confirmer le départ des carcasses vers la boucherie ?';$rowLabel='Expédier';$rowIcon='bi-truck';require $partials.'/row_form.php';endif; ?>
<?php $menuId='cattle-shipment-menu-'.$t['id'];$menuLabel=$t['transfer_number'];$menuItems=[['Consulter l’envoi','bi-eye','cattle-shipment-detail-'.$t['id'],'',false],['Bon de transfert','bi-file-earmark-text','btr-preview','',false,['btr-url'=>base_url('livestock/cattle/shipments/'.$t['id'].'/btr'),'btr-title'=>$t['transfer_number']]]];if($canCancel)$menuItems[]=['Annuler l’envoi','bi-x-circle','cattle-shipment-cancel-'.$t['id'],'danger',false];require $partials.'/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
<?php require __DIR__.'/slaughter_drawers.php'; ?>
<?php require __DIR__.'/forms.php'; ?>
<?php endif; ?>
</div>
