<?php
// Shipment and receipt forms: one line per product and format, its lots (oldest first) folded underneath.
$lotLabel=static fn(array $item)=>'Lot #'.($item['source_finished_stock_id']??$item['id']).(!empty($item['stock_date'])?' · en stock depuis le '.date('d/m/Y',strtotime($item['stock_date'])):'');
$kgText=static fn($kg)=>number_format((float)$kg,0,',',' ').' kg';
$groupLots=static function(array $items,callable $left){
    $groups=[];
    foreach($items as $item){$bags=$left($item);if($bags<=0)continue;$key=$item['product_name'].'|'.$item['format_name'];$groups[$key]['product']=$item['product_name'];$groups[$key]['format']=$item['format_name'];$groups[$key]['weight']=(float)($item['weight_kg']??0);$groups[$key]['lots'][]=$item+['left'=>$bags];}
    return $groups;
};
$orderContext=!empty($t['customer_order'])?'Commande '.$t['customer_order']['order_number'].' · '.$t['customer_order']['customer_name']:'';
?>
<?php if($canUpdate&&$source&&in_array($t['status'],['reserved','partially_shipped'],true)):
$groups=$groupLots($t['items'],static fn($item)=>max(0,(int)$item['reserved_bags']-(int)$item['shipped_bags']));
ob_start();?>
<form method="post" action="<?=e(base_url('transfers/'.$t['id'].'/ship'))?>" data-transfer-operation="ship" data-transfer-ship data-native-submit data-source-name="<?=e($t['source_name']??$t['source_code'])?>" data-destination-name="<?=e($t['destination_name']??$t['destination_code'])?>" data-order="<?=e($orderContext)?>"><?=csrf_field()?>
<div class="feed-form-body transfer-op"><p data-transfer-error class="app-alert app-alert-error" role="alert" hidden></p>
<div class="transfer-op__context"><span><i class="bi bi-truck" aria-hidden="true"></i> <?=e($t['source_code'].' → '.$t['destination_code'])?></span><?php if($orderContext!==''):?><span><i class="bi bi-receipt" aria-hidden="true"></i> <?=e($orderContext)?></span><?php endif;?></div>
<label class="transfer-op__vehicle"><span>Véhicule</span><input name="vehicle_reference" maxlength="100" placeholder="Immatriculation (facultatif)" autocomplete="off"></label>
<div class="transfer-op__head" aria-hidden="true"><span>Produit</span><span>Restant</span><span>À expédier</span></div>
<?php foreach($groups as $g):$total=array_sum(array_column($g['lots'],'left'));$single=count($g['lots'])===1; ?>
<section class="transfer-op__group" data-op-group data-weight="<?=e($g['weight'])?>" data-label="<?=e($g['product'].' · '.$g['format'])?>">
<div class="transfer-op__row"><div><strong><?=e($g['product'])?></strong><small><?=e($g['format'])?><?=$single?'':' · '.count($g['lots']).' lots'?></small></div>
<div class="transfer-op__left"><strong><?=$total?></strong><small><?=$g['weight']>0?e($kgText($total*$g['weight'])):'sacs'?></small></div>
<div class="transfer-op__qty"><input type="number" min="0" max="<?=$total?>" step="1" value="<?=$total?>" inputmode="numeric" data-op-total aria-label="<?=e('Sacs à expédier : '.$g['product'].' '.$g['format'])?>"><span><button type="button" class="btn-secondary" data-op-fill="all" title="Tout expédier">Tout</button><button type="button" class="btn-secondary" data-op-fill="none" title="Ne rien expédier">0</button></span></div></div>
<details class="transfer-op__lots"<?=$single?' hidden':''?>><summary>Répartition par lot · les plus anciens d’abord</summary>
<?php foreach($g['lots'] as $item):?><div class="transfer-op__lot" data-op-lot data-lot-label="<?=e('Lot #'.($item['source_finished_stock_id']??$item['id']))?>"><span><?=e($lotLabel($item))?></span><span><?=$item['left']?> restant(s)</span><input type="number" min="0" max="<?=$item['left']?>" step="1" value="<?=$item['left']?>" inputmode="numeric" name="quantity_bags[<?=e($item['id'])?>]" data-op-lot-input aria-label="<?=e('Sacs à expédier : '.$lotLabel($item))?>"></div><?php endforeach;?>
</details></section>
<?php endforeach;?>
<p class="transfer-op__summary" data-op-summary aria-live="polite"></p>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary" data-op-submit>Confirmer l’expédition</button></footer></form>
<?php $modal('transfer-ship','Expédier le transfert',ob_get_clean());endif;?>
<?php foreach($t['shipments'] as $shipment):if(!$canUpdate||!$destination||!in_array($shipment['status'],['dispatched','partially_received'],true))continue;
$groups=$groupLots($shipment['items'],static fn($item)=>max(0,(int)$item['quantity_bags']-(int)$item['received_bags']));
ob_start();?>
<form method="post" action="<?=e(base_url('transfers/'.$t['id'].'/receive'))?>" data-transfer-operation="receive" data-transfer-receive data-native-submit data-shipment="<?=e($shipment['shipment_number'])?>" data-source-name="<?=e($t['source_name']??$t['source_code'])?>" data-destination-name="<?=e($t['destination_name']??$t['destination_code'])?>" data-order="<?=e($orderContext)?>"><?=csrf_field()?>
<input type="hidden" name="shipment_id" value="<?=e($shipment['id'])?>"><input type="hidden" name="idempotency_key" value="<?=e(bin2hex(random_bytes(16)))?>">
<div class="feed-form-body transfer-op"><p data-transfer-error class="app-alert app-alert-error" role="alert" hidden></p>
<div class="transfer-op__context"><span><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> <?=e($shipment['shipment_number'])?></span><span><?=e(($shipment['vehicle_reference']?:'Véhicule non renseigné').(!empty($shipment['dispatched_at'])?' · parti le '.date('d/m/Y H:i',strtotime($shipment['dispatched_at'])):''))?></span><?php if($orderContext!==''):?><span><i class="bi bi-receipt" aria-hidden="true"></i> <?=e($orderContext)?></span><?php endif;?></div>
<div class="transfer-op__actions"><button type="button" class="btn-secondary" data-op-fill-all="accept"><i class="bi bi-check2-all" aria-hidden="true"></i> Tout accepter</button><button type="button" class="btn-secondary" data-op-fill-all="none"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Remettre à zéro</button></div>
<div class="transfer-op__head is-receive" aria-hidden="true"><span>Lot</span><span>Attendu</span><span>Acceptés</span><span>Refusés</span></div>
<?php foreach($groups as $g):?>
<section class="transfer-op__group" data-op-group data-weight="<?=e($g['weight'])?>" data-label="<?=e($g['product'].' · '.$g['format'])?>">
<p class="transfer-op__group-title"><strong><?=e($g['product'])?></strong> <small><?=e($g['format'])?> · <?=array_sum(array_column($g['lots'],'left'))?> sac(s) attendu(s)</small></p>
<?php foreach($g['lots'] as $item):?><div class="transfer-op__lot is-receive" data-receive-left="<?=$item['left']?>" data-lot-label="<?=e('Lot #'.($item['source_finished_stock_id']??$item['id']))?>"><span><?=e($lotLabel($item))?></span><span><?=$item['left']?></span>
<label><span class="sr-only">Acceptés</span><input type="number" min="0" max="<?=$item['left']?>" step="1" value="<?=$item['left']?>" inputmode="numeric" name="accepted_bags[<?=e($item['id'])?>]" data-accepted aria-label="<?=e('Sacs acceptés : '.$lotLabel($item))?>"></label>
<label><span class="sr-only">Refusés</span><input type="number" min="0" max="<?=$item['left']?>" step="1" value="0" inputmode="numeric" name="rejected_bags[<?=e($item['id'])?>]" data-rejected aria-label="<?=e('Sacs refusés : '.$lotLabel($item))?>"></label>
<label class="transfer-op__note" data-op-note hidden><span>Motif du refus</span><input name="quality_notes[<?=e($item['id'])?>]" maxlength="500" placeholder="Ex. sacs déchirés, humidité" aria-label="<?=e('Note qualité : '.$lotLabel($item))?>"></label></div><?php endforeach;?>
</section>
<?php endforeach;?>
<p class="transfer-op__summary" data-op-summary aria-live="polite"></p>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary" data-op-submit>Enregistrer la réception</button></footer></form>
<?php $modal('transfer-receive-'.$shipment['id'],'Réceptionner l’expédition',ob_get_clean());endforeach;?>
