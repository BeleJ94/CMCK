<?php
// Read-only sheets of incoming shipments and pending receipts, opened from the row action menus.
$sheetFact=static fn($label,$value,$note='')=>'<div><dt>'.e($label).'</dt><dd>'.e($value).($note!==''?'<small>'.e($note).'</small>':'').'</dd></div>';
$btrButton=static fn($t,$transferId,$menuId)=>'<button type="button" class="btn-secondary" data-workspace-modal-open="btr-preview" data-btr-url="'.e(base_url('butchery/receipts/transfers/'.$transferId.'/btr')).'" data-btr-title="'.e($t['transfer_number']).'" data-workspace-return-focus="'.e($menuId).'-toggle"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Bon de transfert</button>';
$origin=static fn($t)=>$t['flow']==='fish'?'Pêche':($t['flow']==='poultry_live'?'Envoi vivant':'Abattage');
$count=static function($t,bool $received=false){$live=$t['flow']==='poultry_live';$n=$received?($t['received_pieces']??null):($live?$t['quantity_heads']:($t['quantity_pieces']??null));return $n===null?null:number_format((int)$n,0,',',' ').' '.($live?'poulet(s) vivant(s)':'carcasse(s)');};
?>
<?php foreach($incoming as$t):$sentAt=$t['dispatched_at']??$t['created_at']; ?>
<?php $drawerId='receipt-transfer-detail-'.$t['id'];$drawerTitle='Envoi '.$t['truck_plate'];require __DIR__.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--warning"><?=$weighingReady?'À peser à l’arrivée':'À réceptionner'?></span><span class="tag-chip"><?=e($t['species_name'])?></span><code><?=e($t['transfer_number'])?></code></div>
<h3>Envoi</h3><dl class="detail-sheet__grid"><?=$sheetFact('Ferme d’origine',$t['source_name'])?><?=$sheetFact('Lot',$t['batch_number'],$t['species_name'])?><?=$sheetFact($origin($t),$t['conversion_number']?:$t['transfer_number'],date('d/m/Y à H:i',strtotime($t['converted_at'])))?><?=$sheetFact('Transport',$t['truck_plate'],$t['driver_name'])?><?=$sheetFact('Expédié le',date('d/m/Y à H:i',strtotime($sentAt)))?><?=$sheetFact($t['flow']==='poultry_live'?'À vendre avant':'DLC',date('d/m/Y',strtotime($t['expiry_date'])))?></dl>
<h3>Pesée</h3><dl class="detail-sheet__grid"><?php if($count($t)!==null):?><?=$sheetFact('Compté à la ferme',$count($t))?><?php endif;?><?=$sheetFact('Pesé à la ferme',$kg($t['quantity_kg']).' kg')?><?=$sheetFact('Tolérance de transport',$t['tolerance_pct']===null?'Non paramétrée':number_format((float)$t['tolerance_pct'],2,',',' ').' %',$t['approver_role_name']?'Dépassement validé par : '.$t['approver_role_name']:'Tout écart doit être justifié')?></dl>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><?=$btrButton($t,$t['id'],'receipt-transfer-menu-'.$t['id'])?>
<?php if($canOperate&&Auth::can('butchery','create',$site)&&$weighingReady): ?><button type="button" class="btn-primary" data-workspace-modal-open="flow-arrival-<?=e($t['id'])?>"><i class="bi bi-speedometer2" aria-hidden="true"></i> Peser à l’arrivée</button><?php endif; ?>
</footer>
<?php require __DIR__.'/drawer_end.php'; ?>
<?php endforeach; ?>
<?php foreach($pending as$t):$gap=['kg'=>(float)$t['weight_gap_kg'],'pct'=>(float)$t['weight_gap_pct']]; ?>
<?php $drawerId='receipt-pending-detail-'.$t['id'];$drawerTitle='Réception '.$t['truck_plate'];require __DIR__.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--warning"><?=$t['gap_exceeded']?'Contrôle et écart à valider':'Contrôle sanitaire à effectuer'?></span><span class="tag-chip"><?=e($t['species_name'])?></span><code><?=e($t['receipt_number'])?></code></div>
<h3>Envoi</h3><dl class="detail-sheet__grid"><?=$sheetFact('Ferme d’origine',$t['source_name'],$t['transfer_number'])?><?=$sheetFact('Lot',$t['batch_number'],$t['species_name'])?><?=$sheetFact($origin($t),$t['conversion_number']?:$t['transfer_number'],date('d/m/Y à H:i',strtotime($t['converted_at'])))?><?=$sheetFact('Transport',$t['truck_plate'],$t['driver_name'])?><?=$sheetFact($t['flow']==='poultry_live'?'À vendre avant':'DLC',date('d/m/Y',strtotime($t['expiry_date'])))?><?=$sheetFact('Arrivée enregistrée',date('d/m/Y à H:i',strtotime($t['received_at'])),$t['weigher_name']?'Par '.$t['weigher_name']:'')?></dl>
<h3>Double pesée</h3><dl class="detail-sheet__grid">
<?php if($count($t,true)!==null): ?><?=$sheetFact('Compté à l’arrivée',$count($t,true),'Ferme : '.$count($t))?><?php endif; ?><?php if($t['weighed']): ?><?=$sheetFact('Pesé à la ferme',$kg($t['sent_weight_kg']).' kg')?><?=$sheetFact('Pesé à l’arrivée',$kg($t['received_weight_kg']).' kg',(float)$t['received_tare_kg']>0?'Brut '.$kg($t['received_gross_kg']).' − tare '.$kg($t['received_tare_kg']).($t['tare_note']?' ('.$t['tare_note'].')':''):'Poids net')?><?=$sheetFact('Écart',TransitWeighingService::describe($gap),$t['tolerance_pct']===null?'Aucune tolérance paramétrée':'Tolérance '.number_format((float)$t['tolerance_pct'],2,',',' ').' %')?><?php if($t['gap_reason']): ?><?=$sheetFact('Motif déclaré',$t['gap_reason'],$t['gap_exceeded']?'Validation : '.($t['approver_role_name']?:'tout validateur boucherie'):'')?><?php endif; ?>
<?php else: ?><?=$sheetFact('Poids à réceptionner',$kg($t['received_weight_kg']).' kg','Arrivée enregistrée avant la pesée d’arrivée')?><?php endif; ?>
</dl>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><?=$btrButton($t,$t['livestock_transfer_id'],'receipt-pending-menu-'.$t['id'])?>
<?php if($canOperate&&Auth::can('butchery','validate',$site)): ?><button type="button" class="btn-primary" data-workspace-modal-open="flow-receive-<?=e($t['id'])?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Contrôler et réceptionner</button><?php endif; ?>
</footer>
<?php require __DIR__.'/drawer_end.php'; ?>
<?php endforeach; ?>
<?php if($incoming||$pending){$btrKicker='BOUCHERIE · BON DE TRANSFERT';require dirname(__DIR__).'/partials/btr_preview.php';} ?>
