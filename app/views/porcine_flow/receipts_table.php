<?php
$partials=dirname(__DIR__).'/partials';
$today=new DateTimeImmutable('today');
// Remaining shelf life under the date, in warning tone from the day before expiry.
$shelf=static function($date)use($today){$days=(int)$today->diff(new DateTimeImmutable($date))->format('%r%a');return [$days<0?'DLC dépassée':($days===0?'Aujourd’hui':($days===1?'Demain':'Dans '.$days.' j')),$days<=1?'is-warning':''];};
$stamp=static fn($at)=>date('d/m/Y',strtotime($at)).' · '.date('H:i',strtotime($at));
$gapChip=static function(array$t){if(empty($t['weighed']))return '';$gap=['kg'=>(float)$t['weight_gap_kg'],'pct'=>(float)$t['weight_gap_pct']];return '<span class="weighing-gap-chip '.($t['gap_exceeded']?'is-exceeded':'is-within').'">'.e(TransitWeighingService::describe($gap)).'</span>';};
$species=static fn($t)=>'<span class="receipt-species receipt-species--'.e($t['flow']).'"><i class="bi '.($t['flow']==='fish'?'bi-water':(strpos($t['flow'],'poultry')===0?'bi-egg':'bi-heart-pulse')).'" aria-hidden="true"></i>'.e($t['species_name']).($t['flow']==='poultry_live'?' vivant':'').'</span>';
// Poultry is counted as well as weighed: carcasses in pieces, live birds in heads.
$pieces=static function($t,bool $received=false){$live=$t['flow']==='poultry_live';$n=$received?($t['received_pieces']??null):($live?$t['quantity_heads']:($t['quantity_pieces']??null));return $n===null?'':number_format((int)$n,0,',',' ').' '.($live?'poulet(s)':'carcasse(s)');};
$btrItem=static fn($t,$transferId)=>['Bon de transfert','bi-file-earmark-text','btr-preview','',false,['btr-url'=>base_url('butchery/receipts/transfers/'.$transferId.'/btr'),'btr-title'=>$t['transfer_number']]];
?>
<div class="table-responsive slaughter-table-wrap"><table class="enterprise-table slaughter-table receipts-table" data-datatable="false" aria-label="Réceptions de la ferme"><thead><tr><?php foreach(['reference'=>'Référence / lot','source'=>'Origine','date'=>'Date','weight'=>'Poids (kg)','expiry'=>'DLC','state'=>'Étape'] as$key=>$label):?><th scope="col" class="<?=$key==='weight'?'num':''?>" aria-sort="none"><button type="button" data-flow-sort="<?=e($key)?>"><?=e($label)?> <span aria-hidden="true">↕</span></button></th><?php endforeach;?><th scope="col" class="receipts-actions-head">Actions</th></tr></thead><tbody data-flow-table-body>
<?php foreach($incoming as$t):$sentAt=$t['dispatched_at']??$t['created_at'];[$shelfLabel,$shelfTone]=$shelf($t['expiry_date']);$canWeigh=$canOperate&&Auth::can('butchery','create',$site);?>
<tr data-transfer-id="<?=e($t['id'])?>" data-flow-key="transfer-<?=e($t['id'])?>" data-flow-item data-kind="incoming" data-state="incoming" data-reference="<?=e($t['transfer_number'])?>" data-source="<?=e($t['source_name'])?>" data-date="<?=e($sentAt)?>" data-weight="<?=e($t['quantity_kg'])?>" data-expiry="<?=e($t['expiry_date'])?>" data-search="<?=e($t['species_name'].' '.$t['source_name'].' '.$t['batch_number'].' '.$t['transfer_number'].' '.$t['conversion_number'].' '.$t['truck_plate'].' '.$t['driver_name'])?>">
<td data-label="Référence / lot" class="cell--lead"><strong class="receipts-ref" title="<?=e($t['transfer_number'])?>"><?=e($t['transfer_number'])?></strong><small><?=$species($t)?> · <span class="receipts-batch" title="<?=e($t['batch_number'])?>"><?=e($t['batch_number'])?></span></small></td>
<td data-label="Origine" class="cell--wide"><span class="receipts-main"><?=e($t['source_name'])?></span><small><?=e($t['truck_plate'].' · '.$t['driver_name'])?></small></td>
<td data-label="Date"><span class="receipts-main"><?=e($stamp($sentAt))?></span><small>Expédié</small></td>
<td class="num" data-label="Poids (kg)"><strong><?=$kg($t['quantity_kg'])?></strong><small><?=$pieces($t)!==''?e($pieces($t)).' · ferme':'Pesé à la ferme'?></small></td>
<td data-label="DLC"><span class="receipts-main"><?=e(date('d/m/Y',strtotime($t['expiry_date'])))?></span><small class="<?=e($shelfTone)?>"><?=e($shelfLabel)?></small></td>
<td data-label="Étape" class="cell--state"><span class="ui-status ui-status--warning"><?=$weighingReady?'À peser':'À réceptionner'?></span></td>
<td data-label="Actions" class="receipts-actions"><div class="row-actions">
<?php if($canWeigh&&$weighingReady):?><button class="btn-primary row-action-button" type="button" data-workspace-modal-open="flow-arrival-<?=e($t['id'])?>"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Peser</span></button>
<?php elseif($canWeigh):$rowAction='butchery/flow/arrivals';$rowConfirm='Enregistrer l’arrivée de cet envoi pour contrôle sanitaire ?';$rowLabel='Arrivée';$rowIcon='bi-box-arrow-in-down';$rowFields=['transfer_id'=>$t['id']];require $partials.'/row_form.php';endif;?>
<?php $menuId='receipt-transfer-menu-'.$t['id'];$menuLabel=$t['transfer_number'];$menuItems=[['Consulter l’envoi','bi-eye','receipt-transfer-detail-'.$t['id'],'',false],$btrItem($t,$t['id'])];require $partials.'/row_menu.php';?>
</div></td></tr>
<?php endforeach;?>
<?php foreach($pending as$t):[$shelfLabel,$shelfTone]=$shelf($t['expiry_date']);$canControl=$canOperate&&Auth::can('butchery','validate',$site);?>
<tr data-receipt-id="<?=e($t['id'])?>" data-flow-key="receipt-<?=e($t['id'])?>" data-flow-item data-kind="pending" data-state="pending" data-reference="<?=e($t['receipt_number'])?>" data-source="<?=e($t['source_name'])?>" data-date="<?=e($t['received_at'])?>" data-weight="<?=e($t['received_weight_kg'])?>" data-expiry="<?=e($t['expiry_date'])?>" data-search="<?=e($t['species_name'].' '.$t['receipt_number'].' '.$t['source_name'].' '.$t['batch_number'].' '.$t['transfer_number'].' '.$t['truck_plate'].' '.$t['driver_name'])?>">
<td data-label="Référence / lot" class="cell--lead"><strong class="receipts-ref" title="<?=e($t['receipt_number'])?>"><?=e($t['receipt_number'])?></strong><small><?=$species($t)?> · <span class="receipts-batch" title="<?=e($t['batch_number'])?>"><?=e($t['batch_number'])?></span></small></td>
<td data-label="Origine" class="cell--wide"><span class="receipts-main"><?=e($t['source_name'])?></span><small title="<?=e($t['transfer_number'])?>"><?=e($t['truck_plate'].' · '.$t['driver_name'])?></small></td>
<td data-label="Date"><span class="receipts-main"><?=e($stamp($t['received_at']))?></span><small>Arrivée enregistrée</small></td>
<td class="num" data-label="Poids (kg)"><strong><?=$kg($t['received_weight_kg'])?></strong><small><?=$pieces($t,true)!==''?e($pieces($t,true)).' · ':''?><?=$t['weighed']?'Ferme '.$kg($t['sent_weight_kg']):'Poids de la ferme'?></small><?=$gapChip($t)?></td>
<td data-label="DLC"><span class="receipts-main"><?=e(date('d/m/Y',strtotime($t['expiry_date'])))?></span><small class="<?=e($shelfTone)?>"><?=e($shelfLabel)?></small></td>
<td data-label="Étape" class="cell--state"><span class="ui-status ui-status--warning"><?=$t['gap_exceeded']?'Écart à valider':'À contrôler'?></span><?php if($t['gap_exceeded']&&$t['approver_role_name']):?><small><?=e($t['approver_role_name'])?></small><?php endif;?></td>
<td data-label="Actions" class="receipts-actions"><div class="row-actions">
<?php if($canControl):?><button class="btn-primary row-action-button" type="button" data-workspace-modal-open="flow-receive-<?=e($t['id'])?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Contrôler</span></button><?php endif;?>
<?php $menuId='receipt-pending-menu-'.$t['id'];$menuLabel=$t['receipt_number'];$menuItems=[['Consulter la réception','bi-eye','receipt-pending-detail-'.$t['id'],'',false],$btrItem($t,$t['livestock_transfer_id'])];require $partials.'/row_menu.php';?>
</div></td></tr>
<?php endforeach;?></tbody></table></div>
<div class="campaign-pagination slaughter-pagination"><span data-flow-page-info role="status"></span><div><button type="button" class="btn-secondary" data-flow-page="-1">Précédent</button><span data-flow-page-label></span><button type="button" class="btn-secondary" data-flow-page="1">Suivant</button></div></div>
<?php if(!$incoming&&!$pending):?><p class="flow-empty">Aucune réception à traiter. Les envois de carcasses, de poisson et de volailles apparaîtront après leur expédition depuis la ferme.</p><?php endif;?>
<?php require __DIR__.'/receipt_details.php'; ?>
