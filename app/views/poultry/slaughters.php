<div class="poultry-page compact-page" data-poultry-page>
<?php require __DIR__.'/header.php'; ?>
<?php if($available):
$returnPath='livestock/poultry/slaughters';$partials=dirname(__DIR__).'/partials';
$pending=array_values(array_filter($slaughters,static fn($s)=>$s['status']==='submitted'));
$farmStocks=array_values(array_filter($slaughters,static fn($s)=>$s['status']==='validated'&&in_array($s['stock_status'],['available','reserved'],true)));
$moving=count(array_filter($shipments,static fn($t)=>in_array($t['status'],['draft','approved','in_transit'],true)));
$weighed=array_values(array_filter($shipments,static fn($t)=>$t['weight_gap_kg']!==null));
$sentWeighed=array_sum(array_map(static fn($t)=>(float)$t['quantity_kg'],$weighed));$gapWeighed=array_sum(array_map(static fn($t)=>(float)$t['weight_gap_kg'],$weighed));
$slaughterStates=['submitted'=>['À valider','warning'],'validated'=>['Validé','success'],'cancelled'=>['Annulé','neutral']];
$shipmentStates=['draft'=>['À approuver','warning'],'approved'=>['À expédier','warning'],'in_transit'=>['En route','progress'],'received'=>['Reçu','success'],'cancelled'=>['Annulé','neutral']];
$today=new DateTimeImmutable('today');
$shelf=static function($date)use($today){$days=(int)$today->diff(new DateTimeImmutable($date))->format('%r%a');return [$days<0?'Dépassée':($days===0?'Aujourd’hui':($days===1?'Demain':'Dans '.$days.' j')),$days<=1?'is-warning':''];};
$stamp=static fn($at)=>date('d/m/Y',strtotime($at)).' · '.date('H:i',strtotime($at));
// Pieces sent with each shipment: carcasses, or live birds counted in heads.
$sentPieces=static fn($t)=>$t['kind']==='live'?(int)$t['quantity_heads']:(int)$t['quantity_pieces'];
$goods=static fn($t)=>$t['kind']==='live'?'Poulets vivants':$t['item_name'];
$unit=static fn($t)=>$t['kind']==='live'?'poulet(s)':'carcasse(s)';
?>
<div class="ui-kpi-grid fish-kpis" aria-label="Synthèse des abattages et envois">
<?php foreach([
    ['Abattages à valider',$int(count($pending)),'bi-hourglass-split',count($pending)?'warning':'','Validation par un autre utilisateur autorisé'],
    ['Carcasses à la ferme',$int(array_sum(array_column($farmStocks,'available_pieces'))).' pièce(s)','bi-box-seam','',$kg(array_sum(array_column($farmStocks,'available_kg'))).' kg disponibles pour un envoi'],
    ['Envois en cours',$int($moving),'bi-truck','','Carcasses ou poulets vivants'],
    ['Écart ferme → boucherie',$weighed?$kg($gapWeighed).' kg':'—','bi-speedometer2',$weighed&&$gapWeighed>0?'warning':'',$weighed?number_format($sentWeighed>0?$gapWeighed/$sentWeighed*100:0,2,',',' ').' % sur '.count($weighed).' envoi(s) pesé(s)':'Aucun envoi pesé à l’arrivée'],
] as [$label,$value,$icon,$tone,$meta]): ?>
<section class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e($value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></section>
<?php endforeach; ?>
</div>

<section class="fish-section" aria-labelledby="poultry-slaughters-title"><header class="pig-ref-section-head"><div><h3 id="poultry-slaughters-title">Abattages</h3><p>Un abattage validé retire les oiseaux du lot, morts à l’abattage compris, et crée le stock de carcasses à la ferme.</p></div></header>
<?php if(!$slaughters): ?><p class="flow-empty">Aucun abattage enregistré. Utilisez « Enregistrer un abattage ».</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Abattages de volailles" data-search-placeholder="Rechercher un abattage, un lot, un poulailler…"><thead><tr><th>Abattage</th><th>Lot</th><th class="num">Carcasses</th><th class="num">Poids</th><th>DLC</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($slaughters as $s):[$stateLabel,$stateTone]=$slaughterStates[$s['status']]??[$s['status'],'neutral'];$site=(int)$s['site_id'];[$shelfLabel,$shelfTone]=$shelf($s['expiry_date']);
$canValidate=$s['status']==='submitted'&&Auth::can('livestock','validate',$site);$canShip=$s['status']==='validated'&&$s['available_pieces']>0&&$s['available_kg']>0&&$s['expiry_date']>=date('Y-m-d')&&Auth::can('livestock','create',$site);$canCancel=$s['status']==='submitted'&&Auth::can('livestock','delete',$site); ?>
<tr>
<td data-label="Abattage" class="cell--lead" data-order="<?=e($s['converted_at'])?>"><strong class="cell-main"><?=e($stamp($s['converted_at']))?></strong><small class="cell-ref" title="<?=e($s['conversion_number'])?>"><?=e($s['conversion_number'])?></small></td>
<td data-label="Lot" class="cell--wide"><span class="cell-main" title="<?=e($s['facility_name'])?>"><?=e($s['facility_name'])?> <span class="tag-chip poultry-type poultry-type--<?=e($s['flock_type'])?>"><?=e($types[$s['flock_type']])?></span></span><small class="cell-ref" title="<?=e($s['batch_number'])?>"><?=e($s['batch_number'])?></small></td>
<td data-label="Carcasses" class="num" data-order="<?=(int)$s['input_heads']?>"><strong><?=$int($s['input_heads'])?></strong><small><?=(int)$s['dead_heads']?$int($s['dead_heads']).' mort(s) à l’abattage':'pièce(s)'?></small></td>
<td data-label="Poids" class="num" data-order="<?=e($s['net_weight_kg'])?>"><strong><?=$kg($s['net_weight_kg'])?></strong><small>kg · <?=e(number_format((float)$s['net_weight_kg']/max(1,(int)$s['input_heads']),3,',',' '))?> / pièce</small></td>
<td data-label="DLC" data-order="<?=e($s['expiry_date'])?>"><span class="cell-main"><?=e(date('d/m/Y',strtotime($s['expiry_date'])))?></span><?php if($s['status']!=='cancelled'&&!($s['status']==='validated'&&$s['available_pieces']<=0)): ?><small class="<?=e($shelfTone)?>"><?=e($shelfLabel)?></small><?php endif; ?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><?php if($s['status']==='validated'&&$s['weight_stock_id']): ?><small><?=$s['available_pieces']>0?$int($s['available_pieces']).' à envoyer':'Entièrement envoyé'?></small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canValidate):$rowAction='livestock/poultry/slaughters/'.$s['id'].'/validate';$rowConfirm='Valider cet abattage ? Les oiseaux quittent l’effectif et le stock de carcasses est créé.';$rowLabel='Valider';$rowIcon='bi-check2-circle';require $partials.'/row_form.php';
elseif($canShip): ?><button type="button" class="btn-primary row-action-button" data-workspace-modal-open="poultry-shipment" data-preselect-field="weight_stock_id" data-preselect-value="<?=e($s['weight_stock_id'])?>"><i class="bi bi-truck" aria-hidden="true"></i><span>Envoyer</span></button>
<?php endif; ?>
<?php $menuId='poultry-slaughter-menu-'.$s['id'];$menuLabel=$s['conversion_number'];$menuItems=[['Consulter l’abattage','bi-eye','poultry-slaughter-detail-'.$s['id'],'',false]];if($canCancel)$menuItems[]=['Annuler l’abattage','bi-x-circle','poultry-slaughter-cancel-'.$s['id'],'danger',false];require $partials.'/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<section class="fish-section" aria-labelledby="poultry-shipments-title"><header class="pig-ref-section-head"><div><h3 id="poultry-shipments-title">Envois à la boucherie</h3><p>Carcasses et poulets vivants sont comptés et pesés à la ferme puis à la boucherie : l’écart mesure les pertes de transport.</p></div></header>
<?php if(!$shipments): ?><p class="flow-empty">Aucun envoi. Préparez un envoi depuis un abattage validé, ou envoyez des poulets vivants.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Envois de volailles" data-search-placeholder="Rechercher un BTR, un camion, un chauffeur…"><thead><tr><th>Envoi</th><th>Marchandise</th><th>Transport</th><th class="num">Ferme</th><th class="num">Boucherie</th><th>Écart</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($shipments as $t):[$stateLabel,$stateTone]=$shipmentStates[$t['status']]??[$t['status'],'neutral'];$site=(int)$t['source_site_id'];$gap=$t['weight_gap_kg']===null?null:['kg'=>(float)$t['weight_gap_kg'],'pct'=>(float)$t['weight_gap_pct']];$exceeded=$gap!==null&&TransitWeighingService::exceeds($gap,$t['tolerance_pct']===null?null:(float)$t['tolerance_pct']);
$missing=$t['received_pieces']!==null?$sentPieces($t)-(int)$t['received_pieces']:0;
$canApprove=$t['status']==='draft'&&Auth::can('livestock','validate',$site);$canDispatch=$t['status']==='approved'&&Auth::can('livestock','update',$site);$canCancel=in_array($t['status'],['draft','approved'],true)&&Auth::can('livestock','delete',$site); ?>
<tr>
<td data-label="Envoi" class="cell--lead" data-order="<?=e($t['created_at'])?>"><strong class="cell-main"><?=e($stamp($t['dispatched_at']??$t['created_at']))?></strong><small class="cell-ref" title="<?=e($t['transfer_number'])?>"><?=e($t['transfer_number'])?></small></td>
<td data-label="Marchandise" class="cell--wide"><span class="cell-main"><i class="bi <?=$t['kind']==='live'?'bi-egg':'bi-box-seam'?>" aria-hidden="true"></i> <?=e($goods($t))?></span><small title="<?=e($t['facility_name'].' · '.$t['batch_number'])?>"><?=e($t['facility_name'])?></small></td>
<td data-label="Transport" class="cell--wide"><span class="cell-main"><?=e($t['truck_plate'])?></span><small><?=e($t['driver_name'])?></small></td>
<td data-label="Ferme" class="num" data-order="<?=e($t['quantity_kg'])?>"><strong><?=$kg($t['quantity_kg'])?></strong><small>kg · <?=$int($sentPieces($t))?> <?=e($unit($t))?></small></td>
<td data-label="Boucherie" class="num" data-order="<?=e((string)$t['received_weight_kg'])?>"><?php if($gap!==null): ?><strong><?=$kg($t['received_weight_kg'])?></strong><small>kg<?=$t['received_pieces']!==null?' · '.$int($t['received_pieces']).' '.e($unit($t)):''?></small><?php else: ?><span class="cell-muted">—</span><small><?=$t['status']==='in_transit'?'À compter et peser':'—'?></small><?php endif; ?></td>
<td data-label="Écart" data-order="<?=$gap!==null?e($gap['pct']):''?>"><?php if($gap!==null): ?><span class="weighing-gap-chip <?=$exceeded?'is-exceeded':'is-within'?>"><?=e(TransitWeighingService::describe($gap))?></span><?php if($missing>0): ?><small class="is-warning"><?=$int($missing)?> manquant(s)</small><?php endif; ?><?php else: ?><span class="cell-muted">—</span><?php endif; ?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><?php if($t['status']==='in_transit'&&$t['receipt_status']==='sanitary_pending'): ?><small>Contrôle en cours</small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canApprove):$rowAction='livestock/poultry/shipments/'.$t['id'].'/approve';$rowConfirm='Approuver cet envoi ?';$rowLabel='Approuver';$rowIcon='bi-check2-circle';require $partials.'/row_form.php';
elseif($canDispatch):$rowAction='livestock/poultry/shipments/'.$t['id'].'/dispatch';$rowConfirm=$t['kind']==='live'?'Confirmer le départ des poulets vivants ? Ils quittent l’effectif du lot.':'Confirmer le départ des carcasses vers la boucherie ?';$rowLabel='Expédier';$rowIcon='bi-truck';require $partials.'/row_form.php';endif; ?>
<?php $menuId='poultry-shipment-menu-'.$t['id'];$menuLabel=$t['transfer_number'];$menuItems=[['Consulter l’envoi','bi-eye','poultry-shipment-detail-'.$t['id'],'',false],['Bon de transfert','bi-file-earmark-text','btr-preview','',false,['btr-url'=>base_url('livestock/poultry/shipments/'.$t['id'].'/btr'),'btr-title'=>$t['transfer_number']]]];if($canCancel)$menuItems[]=['Annuler l’envoi','bi-x-circle','poultry-shipment-cancel-'.$t['id'],'danger',false];require $partials.'/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
<?php require __DIR__.'/slaughter_drawers.php'; ?>
<?php require __DIR__.'/forms.php'; ?>
<?php endif; ?>
</div>
