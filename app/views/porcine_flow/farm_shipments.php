<?php
// Pig carcasses at the farm and their shipments to the butchery, laid out like the cattle slaughters page.
$farmSpace=true;$returnPath='livestock/pigs/shipments';$partials=dirname(__DIR__).'/partials';$drawerKicker='PORCS · FERME';
$kg=static fn($v)=>number_format((float)$v,3,',',' ');$int=static fn($v)=>number_format((int)$v,0,',',' ');
$stamp=static fn($at)=>date('d/m/Y',strtotime($at)).' · '.date('H:i',strtotime($at));
$today=new DateTimeImmutable('today');
$shelf=static function($date)use($today){$days=(int)$today->diff(new DateTimeImmutable($date))->format('%r%a');return [$days<0?'Dépassée':($days===0?'Aujourd’hui':($days===1?'Demain':'Dans '.$days.' j')),$days<=1?'is-warning':''];};
$shipmentStates=['draft'=>['À approuver','warning'],'approved'=>['À expédier','warning'],'in_transit'=>['En route','progress'],'received'=>['Reçu','success'],'cancelled'=>['Annulé','neutral']];
$sheetFact=static fn($label,$value,$note='')=>'<div><dt>'.e($label).'</dt><dd>'.e($value).($note!==''?'<small>'.e($note).'</small>':'').'</dd></div>';
$origin=static fn(array $row)=>($row['facility_name']?:'Bâtiment non renseigné').' · '.$row['site_name'];
$stocks=$available?array_values(array_filter($farm['slaughters'],static fn($s)=>$s['status']==='validated'&&$s['weight_stock_id']&&(float)$s['quantity_kg']>0)):[];
foreach($stocks as &$s){$s['available_kg']=round((float)$s['quantity_kg']-(float)$s['reserved_kg'],3);$s['expired']=$s['expiry_date']<date('Y-m-d');$s['shippable']=$s['available_kg']>0&&!$s['expired']&&Auth::can('livestock','create',(int)$s['site_id']);}
unset($s);
$shippable=array_values(array_filter($stocks,static fn($s)=>$s['shippable']));
$shipments=$available?$farm['shipments']:[];
?>
<div class="flow-directory pig-shipments-page" data-flow-directory data-site-scope="<?=e(Auth::currentSiteId()??'all')?>" data-page-scope="farm-shipments">
<header class="ui-page-header"><span class="ui-page-header__icon"><i class="bi bi-truck" aria-hidden="true"></i></span><div class="ui-page-header__content"><p class="section-label">PORCS · À LA FERME</p><h2>Carcasses et envois</h2><p>Les carcasses validées sont pesées à la ferme, envoyées à la boucherie, puis pesées de nouveau à leur arrivée.</p></div><div class="ui-page-header__actions">
<a class="btn-secondary" href="<?=e(base_url('livestock/pigs/slaughters'))?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Abattage à la ferme</a>
<?php if($shippable): ?><button type="button" class="btn-primary" data-workspace-modal-open="flow-shipment"><i class="bi bi-truck" aria-hidden="true"></i> Préparer un envoi</button><?php endif; ?>
</div></header>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?><?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if(!$available): require __DIR__.'/unavailable.php'; else:
$stockKg=array_sum(array_map(static fn($s)=>$s['expired']?0:max(0,$s['available_kg']),$stocks));$reservedKg=array_sum(array_map(static fn($s)=>(float)$s['reserved_kg'],$stocks));
$toHandle=count(array_filter($shipments,static fn($t)=>in_array($t['status'],['draft','approved'],true)));$onRoad=count(array_filter($shipments,static fn($t)=>$t['status']==='in_transit'));
$weighed=array_values(array_filter($shipments,static fn($t)=>$t['weight_gap_kg']!==null));
$sentWeighed=array_sum(array_map(static fn($t)=>(float)$t['quantity_kg'],$weighed));$gapWeighed=array_sum(array_map(static fn($t)=>(float)$t['weight_gap_kg'],$weighed));
$expired=array_values(array_filter($stocks,static fn($s)=>$s['expired']));
?>
<div class="ui-kpi-grid fish-kpis" aria-label="Synthèse des carcasses et envois">
<?php foreach([
    ['Carcasses à envoyer',$kg($stockKg).' kg','bi-box-seam','',count($stocks).' stock(s) à la ferme'.($reservedKg>0?' · '.$kg($reservedKg).' kg réservés':'')],
    ['Envois à traiter',$int($toHandle),'bi-hourglass-split',$toHandle?'warning':'','À approuver ou à expédier'],
    ['En route',$int($onRoad),'bi-truck','','En attente de la pesée d’arrivée'],
    ['Écart ferme → boucherie',$weighed?$kg($gapWeighed).' kg':'—','bi-speedometer2',$weighed&&$gapWeighed>0?'warning':'',$weighed?number_format($sentWeighed>0?$gapWeighed/$sentWeighed*100:0,2,',',' ').' % sur '.count($weighed).' envoi(s) pesé(s)':'Aucun envoi pesé à l’arrivée'],
] as [$label,$value,$icon,$tone,$meta]): ?>
<section class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e($value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></section>
<?php endforeach; ?>
</div>
<?php if($expired): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?=count($expired)===1?'Un stock de carcasses a dépassé sa DLC':count($expired).' stocks de carcasses ont dépassé leur DLC'?> : <?=e(implode(', ',array_column($expired,'conversion_number')))?>. Ils ne peuvent plus être envoyés.</p><?php endif; ?>

<section class="fish-section" aria-labelledby="pig-stocks-title"><header class="pig-ref-section-head"><div><h3 id="pig-stocks-title">Carcasses à la ferme</h3><p>Stock pesé de chaque abattage validé. Un envoi approuvé réserve ses carcasses jusqu’au départ du camion.</p></div></header>
<?php if(!$stocks): ?><p class="flow-empty">Aucune carcasse à la ferme. Validez un abattage dans « Abattage à la ferme » pour créer un stock.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Carcasses de porcs à la ferme" data-search-placeholder="Rechercher un abattage, un lot, un bâtiment…"><thead><tr><th>Abattage</th><th>Lot</th><th class="num">Porcs</th><th class="num">Stock</th><th class="num">À envoyer</th><th>DLC</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($stocks as $s):[$shelfLabel,$shelfTone]=$shelf($s['expiry_date']);$state=$s['expired']?['DLC dépassée','danger']:($s['available_kg']>0?['Disponible','success']:['Réservé','progress']); ?>
<tr data-stock-id="<?=e($s['weight_stock_id'])?>">
<td data-label="Abattage" class="cell--lead" data-order="<?=e($s['converted_at'])?>"><strong class="cell-main"><?=e($stamp($s['converted_at']))?></strong><small class="cell-ref" title="<?=e($s['conversion_number'])?>"><?=e($s['conversion_number'])?></small></td>
<td data-label="Lot" class="cell--wide"><span class="cell-main" title="<?=e($origin($s))?>"><?=e($origin($s))?></span><small class="cell-ref" title="<?=e($s['batch_number'])?>"><?=e($s['batch_number'])?></small></td>
<td data-label="Porcs" class="num" data-order="<?=(int)$s['input_heads']?>"><strong><?=$int($s['input_heads'])?></strong><small>abattu(s)</small></td>
<td data-label="Stock" class="num" data-order="<?=e($s['quantity_kg'])?>"><strong><?=$kg($s['quantity_kg'])?></strong><small>kg<?=(float)$s['reserved_kg']>0?' · '.$kg($s['reserved_kg']).' réservés':''?></small></td>
<td data-label="À envoyer" class="num" data-order="<?=e($s['available_kg'])?>"><strong><?=$kg(max(0,$s['available_kg']))?></strong><small>kg</small></td>
<td data-label="DLC" data-order="<?=e($s['expiry_date'])?>"><span class="cell-main"><?=e(date('d/m/Y',strtotime($s['expiry_date'])))?></span><small class="<?=e($shelfTone)?>"><?=e($shelfLabel)?></small></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($state[1])?>"><?=e($state[0])?></span></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($s['shippable']): ?><button type="button" class="btn-primary row-action-button" data-workspace-modal-open="flow-shipment" data-preselect-field="weight_stock_id" data-preselect-value="<?=e($s['weight_stock_id'])?>"><i class="bi bi-truck" aria-hidden="true"></i><span>Envoyer</span></button><?php endif; ?>
<?php $menuId='pig-stock-menu-'.$s['weight_stock_id'];$menuLabel=$s['conversion_number'];$menuItems=[['Consulter le stock','bi-eye','pig-stock-detail-'.$s['weight_stock_id'],'',false]];require $partials.'/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<section class="fish-section" aria-labelledby="pig-shipments-title"><header class="pig-ref-section-head"><div><h3 id="pig-shipments-title">Envois à la boucherie</h3><p>Les carcasses sont pesées à la ferme puis à la boucherie : l’écart mesure les pertes de transport.</p></div></header>
<?php if(!$shipments): ?><p class="flow-empty">Aucun envoi. Préparez un envoi depuis le stock de carcasses à la ferme.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Envois de carcasses de porcs" data-search-placeholder="Rechercher un BTR, un camion, un chauffeur…"><thead><tr><th>Envoi</th><th>Origine</th><th>Transport</th><th class="num">Ferme</th><th class="num">Boucherie</th><th>Écart</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($shipments as $t):[$stateLabel,$stateTone]=$shipmentStates[$t['status']]??[$t['status'],'neutral'];$site=(int)$t['source_site_id'];$gap=$t['weight_gap_kg']===null?null:['kg'=>(float)$t['weight_gap_kg'],'pct'=>(float)$t['weight_gap_pct']];$exceeded=$gap!==null&&TransitWeighingService::exceeds($gap,$t['tolerance_pct']===null?null:(float)$t['tolerance_pct']);
$canApprove=$t['status']==='draft'&&Auth::can('livestock','validate',$site);$canDispatch=$t['status']==='approved'&&Auth::can('livestock','update',$site);$canCancel=in_array($t['status'],['draft','approved'],true)&&Auth::can('livestock','delete',$site); ?>
<tr data-shipment-id="<?=e($t['id'])?>">
<td data-label="Envoi" class="cell--lead" data-order="<?=e($t['created_at'])?>"><strong class="cell-main"><?=e($stamp($t['dispatched_at']??$t['created_at']))?></strong><small class="cell-ref" title="<?=e($t['transfer_number'])?>"><?=e($t['transfer_number'])?></small></td>
<td data-label="Origine" class="cell--wide"><span class="cell-main" title="<?=e($origin($t))?>"><?=e($origin($t))?></span><small class="cell-ref" title="<?=e($t['batch_number'].' · '.$t['conversion_number'])?>"><?=e($t['batch_number'])?></small></td>
<td data-label="Transport" class="cell--wide"><span class="cell-main"><?=e($t['truck_plate'])?></span><small><?=e($t['driver_name'])?></small></td>
<td data-label="Ferme" class="num" data-order="<?=e($t['quantity_kg'])?>"><strong><?=$kg($t['quantity_kg'])?></strong><small>kg</small></td>
<td data-label="Boucherie" class="num" data-order="<?=e((string)$t['received_weight_kg'])?>"><?php if($gap!==null): ?><strong><?=$kg($t['received_weight_kg'])?></strong><small>kg</small><?php else: ?><span class="cell-muted">—</span><small><?=$t['status']==='in_transit'?'À peser':($t['status']==='received'?'Non pesé':'—')?></small><?php endif; ?></td>
<td data-label="Écart" data-order="<?=$gap!==null?e($gap['pct']):''?>"><?php if($gap!==null): ?><span class="weighing-gap-chip <?=$exceeded?'is-exceeded':'is-within'?>"><?=e(TransitWeighingService::describe($gap))?></span><?php else: ?><span class="cell-muted">—</span><?php endif; ?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><?php if($t['status']==='in_transit'&&$t['receipt_status']==='sanitary_pending'): ?><small>Contrôle en cours</small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canApprove):$rowAction='livestock/pigs/shipments/'.$t['id'].'/approve';$rowConfirm='Approuver cet envoi et réserver les carcasses ?';$rowLabel='Approuver';$rowIcon='bi-check2-circle';require $partials.'/row_form.php';
elseif($canDispatch):$rowAction='livestock/pigs/shipments/'.$t['id'].'/dispatch';$rowConfirm='Confirmer le départ des carcasses vers la boucherie ?';$rowLabel='Expédier';$rowIcon='bi-truck';require $partials.'/row_form.php';endif; ?>
<?php $menuId='pig-shipment-menu-'.$t['id'];$menuLabel=$t['transfer_number'];$menuItems=[['Consulter l’envoi','bi-eye','pig-shipment-detail-'.$t['id'],'',false],['Bon de transfert','bi-file-earmark-text','btr-preview','',false,['btr-url'=>base_url('livestock/pigs/shipments/'.$t['id'].'/btr'),'btr-title'=>$t['transfer_number']]]];if($canCancel)$menuItems[]=['Annuler l’envoi','bi-x-circle','pig-shipment-cancel-'.$t['id'],'danger',false];require $partials.'/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<?php // Read-only sheets and cancellation, opened from the row menus.
foreach($stocks as $s):$stockShipments=array_values(array_filter($shipments,static fn($t)=>(int)$t['weight_stock_id']===(int)$s['weight_stock_id']));
$drawerId='pig-stock-detail-'.$s['weight_stock_id'];$drawerTitle='Carcasses du '.date('d/m/Y',strtotime($s['converted_at']));require __DIR__.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--<?=$s['expired']?'danger':($s['available_kg']>0?'success':'progress')?>"><?=$s['expired']?'DLC dépassée':($s['available_kg']>0?'Disponible':'Réservé')?></span><code><?=e($s['conversion_number'])?></code></div>
<h3>Origine</h3><dl class="detail-sheet__grid"><?=$sheetFact('Lot',$s['batch_number'],$s['sex']==='male'?'Mâles':($s['sex']==='female'?'Femelles':''))?><?=$sheetFact('Bâtiment',$s['facility_name']?:'—',$s['site_name'])?><?=$sheetFact('Abattage',$stamp($s['converted_at']),$s['validator']?'Validé par '.$s['validator']:'')?><?=$sheetFact('Porcs abattus',$int($s['input_heads']))?></dl>
<h3>Pesée des carcasses</h3><dl class="detail-sheet__grid"><?=$sheetFact('Poids net',$kg($s['net_weight_kg']).' kg',(int)$s['input_heads']>0?number_format((float)$s['net_weight_kg']/(int)$s['input_heads'],1,',',' ').' kg par carcasse':'')?><?php if((float)$s['tare_weight_kg']>0): ?><?=$sheetFact('Poids brut',$kg($s['gross_weight_kg']).' kg')?><?=$sheetFact('Tare',$kg($s['tare_weight_kg']).' kg',(string)$s['tare_note'])?><?php else: ?><?=$sheetFact('Mode de pesée','Poids net')?><?php endif; ?></dl>
<h3>Stock</h3><dl class="detail-sheet__grid"><?=$sheetFact('À la ferme',$kg($s['quantity_kg']).' kg')?><?=$sheetFact('Réservé',$kg($s['reserved_kg']).' kg','Envois approuvés, pas encore partis')?><?=$sheetFact('À envoyer',$kg(max(0,$s['available_kg'])).' kg')?><?=$sheetFact('DLC',date('d/m/Y',strtotime($s['expiry_date'])),$shelf($s['expiry_date'])[0])?></dl>
<h3>Envois de ce stock <span class="pig-ref-count"><?=count($stockShipments)?></span></h3>
<?php if(!$stockShipments): ?><p class="flow-empty">Aucun envoi pour ce stock.</p><?php else: ?>
<ul class="fish-lot-lines"><?php foreach($stockShipments as $t):[$stateLabel,$stateTone]=$shipmentStates[$t['status']]??[$t['status'],'neutral']; ?><li><span><strong><?=e(date('d/m/Y',strtotime($t['dispatched_at']??$t['created_at'])))?></strong> <small><?=e($t['transfer_number'])?></small></span><span><?=$kg($t['quantity_kg'])?> kg</span><span><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span></span></li><?php endforeach; ?></ul>
<?php endif; ?>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><?php if($s['shippable']): ?><button type="button" class="btn-primary" data-workspace-modal-open="flow-shipment" data-preselect-field="weight_stock_id" data-preselect-value="<?=e($s['weight_stock_id'])?>"><i class="bi bi-truck" aria-hidden="true"></i> Préparer un envoi</button><?php endif; ?></footer>
<?php require __DIR__.'/drawer_end.php'; ?>
<?php endforeach; ?>

<?php foreach($shipments as $t):[$stateLabel,$stateTone]=$shipmentStates[$t['status']]??[$t['status'],'neutral'];$site=(int)$t['source_site_id'];$gap=$t['weight_gap_kg']===null?null:['kg'=>(float)$t['weight_gap_kg'],'pct'=>(float)$t['weight_gap_pct']]; ?>
<?php $drawerId='pig-shipment-detail-'.$t['id'];$drawerTitle='Envoi '.$t['truck_plate'];require __DIR__.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><code><?=e($t['transfer_number'])?></code></div>
<h3>Marchandise</h3><dl class="detail-sheet__grid"><?=$sheetFact('Origine',$origin($t),$t['batch_number'])?><?=$sheetFact('Abattage',$t['conversion_number'],date('d/m/Y à H:i',strtotime($t['converted_at'])))?><?=$sheetFact('DLC',date('d/m/Y',strtotime($t['expiry_date'])))?><?=$sheetFact('Transport',$t['truck_plate'],$t['driver_name'])?></dl>
<h3>Double pesée</h3><dl class="detail-sheet__grid"><?=$sheetFact('Pesé à la ferme',$kg($t['quantity_kg']).' kg')?><?php if($gap!==null): ?><?=$sheetFact('Pesé à la boucherie',$kg($t['received_weight_kg']).' kg',(float)$t['received_tare_kg']>0?'Brut '.$kg($t['received_gross_kg']).' − tare '.$kg($t['received_tare_kg']).($t['receipt_tare_note']?' ('.$t['receipt_tare_note'].')':''):'Poids net')?><?=$sheetFact('Écart',TransitWeighingService::describe($gap),$t['tolerance_pct']===null?'Aucune tolérance paramétrée':'Tolérance '.number_format((float)$t['tolerance_pct'],2,',',' ').' %')?><?php if($t['gap_reason']): ?><?=$sheetFact('Motif déclaré',$t['gap_reason'],$t['gap_approver']?'Écart validé par '.$t['gap_approver']:'')?><?php endif; ?><?php else: ?><?=$sheetFact('Pesé à la boucherie','—',$t['status']==='in_transit'?'En attente de la pesée d’arrivée':($t['status']==='received'?'Reçu avant la pesée d’arrivée':'Non pesé'))?><?php endif; ?></dl>
<h3>Suivi</h3><dl class="detail-sheet__grid"><?=$sheetFact('Préparé par',$t['author']?:'—',date('d/m/Y H:i',strtotime($t['created_at'])))?><?=$sheetFact('Approuvé par',$t['approver']?:'—')?><?=$sheetFact('Expédié par',$t['dispatcher']?:'—',$t['dispatched_at']?date('d/m/Y H:i',strtotime($t['dispatched_at'])):'')?><?=$sheetFact('Réception',$t['receipt_number']?:'—',$t['weigher']?'Pesé par '.$t['weigher'].($t['receipt_received_at']?' le '.date('d/m/Y H:i',strtotime($t['receipt_received_at'])):''):'')?></dl>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><button type="button" class="btn-secondary" data-workspace-modal-open="btr-preview" data-btr-url="<?=e(base_url('livestock/pigs/shipments/'.$t['id'].'/btr'))?>" data-btr-title="<?=e($t['transfer_number'])?>" data-workspace-return-focus="pig-shipment-menu-<?=e($t['id'])?>-toggle"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Bon de transfert</button>
<?php if($t['status']==='draft'&&Auth::can('livestock','validate',$site)):$rowAction='livestock/pigs/shipments/'.$t['id'].'/approve';$rowConfirm='Approuver cet envoi et réserver les carcasses ?';$rowLabel='Approuver';$rowIcon='bi-check2-circle';require $partials.'/row_form.php';
elseif($t['status']==='approved'&&Auth::can('livestock','update',$site)):$rowAction='livestock/pigs/shipments/'.$t['id'].'/dispatch';$rowConfirm='Confirmer le départ des carcasses vers la boucherie ?';$rowLabel='Expédier';$rowIcon='bi-truck';require $partials.'/row_form.php';
elseif($t['status']==='in_transit'&&Auth::can('butchery','read')): ?><a class="btn-secondary" href="<?=e(base_url('butchery/receipts'))?>"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Suivre la réception</a><?php endif; ?>
</footer>
<?php require __DIR__.'/drawer_end.php'; ?>
<?php if(in_array($t['status'],['draft','approved'],true)&&Auth::can('livestock','delete',$site)): $drawerId='pig-shipment-cancel-'.$t['id'];$drawerTitle='Annuler l’envoi';require __DIR__.'/drawer_start.php';$action='livestock/pigs/shipments/'.$t['id'].'/cancel';$confirm='Annuler cet envoi ? La réservation de carcasses est libérée.';require __DIR__.'/form_start.php'; ?>
<p class="livestock-guide">Envoi <?=e($t['transfer_number'])?> · <?=$kg($t['quantity_kg'])?> kg · <?=e($t['truck_plate'].' · '.$t['driver_name'])?>. Les carcasses redeviennent disponibles à la ferme.</p>
<div class="form-grid"><label class="form-wide"><span>Motif d’annulation *</span><textarea name="reason" maxlength="500" rows="3" required placeholder="Ex. camion indisponible, quantité erronée"></textarea></label></div>
<?php $submitLabel='Annuler l’envoi';$destructiveSubmit=true;require __DIR__.'/form_end.php';unset($destructiveSubmit);require __DIR__.'/drawer_end.php';endif; ?>
<?php endforeach; ?>
<?php if($shipments){$btrKicker='PORCS · BON DE TRANSFERT';require $partials.'/btr_preview.php';} ?>

<?php if($shippable):$drawerId='flow-shipment';$drawerTitle='Préparer un envoi de carcasses';require __DIR__.'/drawer_start.php';$action='livestock/pigs/shipments';$confirm='Préparer cet envoi pour approbation ?';require __DIR__.'/form_start.php'; ?>
<p class="livestock-guide">Le poids envoyé est celui pesé à la ferme. La boucherie pèsera de nouveau les carcasses à leur arrivée.</p>
<div class="form-grid"><label class="form-wide"><span>Carcasses à envoyer *</span><select name="weight_stock_id" required><option value="">Choisir le stock de carcasses</option><?php foreach($shippable as $s): ?><option value="<?=e($s['weight_stock_id'])?>" data-max="<?=e($s['available_kg'])?>"><?=e($s['conversion_number'].' · '.$origin($s).' · '.$kg($s['available_kg']).' kg disponibles · DLC '.date('d/m/Y',strtotime($s['expiry_date'])))?></option><?php endforeach; ?></select></label>
<label><span>Quantité à envoyer (kg) *</span><input name="quantity_kg" type="number" min="0.001" step="0.001" inputmode="decimal" required></label><label><span>Immatriculation *</span><input name="truck_plate" maxlength="40" required></label><label><span>Chauffeur *</span><input name="driver_name" maxlength="150" required></label></div>
<p class="livestock-guide" data-flow-preview aria-live="polite"></p>
<?php $submitLabel='Préparer l’envoi';require __DIR__.'/form_end.php';require __DIR__.'/drawer_end.php';endif; ?>
<?php endif; ?>
</div>
