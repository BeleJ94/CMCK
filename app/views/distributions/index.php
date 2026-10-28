<?php
// Client exits hub: orders to deliver, exits without an order, and one history for delivery notes (BL) and exit notes (BS).
$flowViews=dirname(__DIR__).'/porcine_flow';$partials=dirname(__DIR__).'/partials';$returnPath='distributions';$drawerKicker='DISTRIBUTION · SORTIES CLIENTS';
$int=static fn($v)=>number_format((int)$v,0,',',' ');$kg=static fn($v)=>number_format((float)$v,0,',',' ').' kg';$money=static fn($v,$d=2)=>number_format((float)$v,$d,',',' ');
$stamp=static fn($at)=>$at?date('d/m/Y H:i',strtotime($at)):'—';
$sheetFact=static fn($label,$value,$note='')=>'<div><dt>'.e($label).'</dt><dd>'.e($value).($note!==''?'<small>'.e($note).'</small>':'').'</dd></div>';
$ready=!empty($delivery['ready']);$orders=$delivery['orders']??[];$deliveries=$delivery['deliveries']??[];
$canDeliver=$ready&&!empty($delivery['canDeliver']);$canCancelDelivery=$ready&&!empty($delivery['canCancel']);$canExit=Auth::can('distributions','create');
$modes=CustomerDeliveryService::MODES;$stages=CustomerDeliveryService::STAGES;
$exitLabels=['validated'=>['Validée','success'],'cancelled'=>['Annulée','neutral'],'draft'=>['Brouillon','progress']];
$deliveryLabels=['validated'=>['Livrée','success'],'cancelled'=>['Annulée','neutral']];

// One history: client deliveries and exits without an order, newest first.
$history=[];
foreach($deliveries as $d)$history[]=['kind'=>'delivery','id'=>(int)$d['id'],'number'=>$d['document_number']?:$d['delivery_number'],'at'=>$d['delivered_at'],'who'=>$d['customer_name'],'note'=>'Commande '.$d['order_number'].' · '.$d['site_name'],'products'=>array_map(static fn($l)=>$l['format_name'].' × '.$int($l['bags']),$d['lines']),'bags'=>(int)$d['bags'],'kg'=>(float)$d['kg'],'status'=>$d['status'],'row'=>$d];
foreach($distributions as $r)$history[]=['kind'=>'direct','id'=>(int)$r['id'],'number'=>$r['official_document_number']?:$r['exit_voucher'],'at'=>$r['distributed_at'],'who'=>$r['recipient_name'],'note'=>'Sans commande · '.$r['site_code'],'products'=>[$r['format_name'].' × '.$int($r['quantity_bags'])],'bags'=>(int)$r['quantity_bags'],'kg'=>(float)$r['total_weight_kg'],'status'=>$r['status'],'row'=>$r];
usort($history,static fn($a,$b)=>[$b['at'],$b['id']]<=>[$a['at'],$a['id']]);

$today=date('Y-m-d');$todayRows=array_filter($history,static fn($h)=>$h['status']==='validated'&&substr($h['at'],0,10)===$today);
$readyOrders=array_values(array_filter($orders,static fn($o)=>$o['ready_bags']>0&&$o['customer_status']==='active'));
$freeBags=array_sum(array_column($availableStocks,'quantity_bags'));$freeKg=array_sum(array_column($availableStocks,'total_weight_kg'));

// Every note opens in the preview modal, as the PDF itself: what is seen is what is downloaded and printed.
$note=static fn(bool $isDelivery,int $id,string $number)=>$isDelivery
    ?['note-url'=>base_url('distributions/deliveries/'.$id.'/print'),'note-download'=>base_url('distributions/deliveries/'.$id.'/print?export=pdf'),'note-title'=>'Bon de livraison '.$number]
    :['note-url'=>base_url('distributions/'.$id.'/print?export=pdf&preview=1'),'note-download'=>base_url('distributions/'.$id.'/print?export=pdf'),'note-title'=>'Bon de sortie '.$number];
$noteButton=static function(array $attributes,string $label,string $class='btn-secondary'){$data='';foreach($attributes as $name=>$value)$data.=' data-'.$name.'="'.e($value).'"';return '<button type="button" class="'.$class.'" data-workspace-modal-open="note-preview"'.$data.'><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> '.e($label).'</button>';};
// Banner after a delivery or an exit: its note, one click away.
$done=null;
if(!empty($deliveryDone))foreach($deliveries as $d){if((int)$d['id']===(int)$deliveryDone){$done=$note(true,(int)$d['id'],$d['document_number']?:$d['delivery_number']);break;}}
if(!empty($exitDone))foreach($distributions as $r){if((int)$r['id']===(int)$exitDone){$done=$note(false,(int)$r['id'],$r['official_document_number']?:$r['exit_voucher']);break;}}
?>
<div class="distribution-workspace distribution-hub">
<header class="ui-page-header"><span class="ui-page-header__icon"><i class="bi bi-send-check" aria-hidden="true"></i></span><div class="ui-page-header__content"><p class="section-label">DISTRIBUTION · SORTIES CLIENTS</p><h2>Sorties clients</h2><p>Livrez les commandes clients et enregistrez les sorties sans commande. Chaque sortie déduit le stock et produit un bon à imprimer.</p></div>
<div class="ui-page-header__actions"><?php if($ready&&Auth::can('distributions','read')): ?><a class="btn-secondary" href="<?=e(base_url('sales/orders'))?>"><i class="bi bi-receipt" aria-hidden="true"></i> Commandes clients</a><?php endif; ?><?php if($canExit): ?><button type="button" class="btn-primary" data-workspace-modal-open="distribution-create"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sortie sans commande</button><?php endif; ?></div></header>

<?php if($done): ?><div class="app-alert app-alert-success distribution-done" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span><?=e($success?:'Opération enregistrée.')?></span><?=$noteButton($done,'Voir le '.lcfirst($done['note-title']))?></div>
<?php elseif($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?>
<?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>

<div class="ui-kpi-grid" aria-label="Synthèse des sorties">
<section class="ui-kpi<?=$readyOrders?' ui-kpi--success':''?>"><span class="ui-kpi__icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span><span class="ui-kpi__label">Prêt à livrer</span><strong class="ui-kpi__value"><?=$int(array_sum(array_column($readyOrders,'ready_bags')))?> <small>sacs</small></strong><span class="ui-kpi__meta"><?=$int(count($readyOrders))?> commande(s) prête(s)</span></section>
<section class="ui-kpi"><span class="ui-kpi__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span><span class="ui-kpi__label">Reste à livrer</span><strong class="ui-kpi__value"><?=$int(array_sum(array_column($orders,'to_deliver')))?> <small>sacs</small></strong><span class="ui-kpi__meta">sur <?=$int(count($orders))?> commande(s) en cours</span></section>
<section class="ui-kpi"><span class="ui-kpi__icon"><i class="bi bi-truck" aria-hidden="true"></i></span><span class="ui-kpi__label">Sorti aujourd’hui</span><strong class="ui-kpi__value"><?=$int(array_sum(array_column($todayRows,'bags')))?> <small>sacs</small></strong><span class="ui-kpi__meta"><?=$int(count($todayRows))?> bon(s) · <?=e($kg(array_sum(array_column($todayRows,'kg'))))?></span></section>
<button type="button" class="ui-kpi" data-workspace-modal-open="distribution-kpi-stock"><span class="ui-kpi__icon"><i class="bi bi-boxes" aria-hidden="true"></i></span><span class="ui-kpi__label">Stock libre</span><strong class="ui-kpi__value"><?=$int($freeBags)?> <small>sacs</small></strong><span class="ui-kpi__meta"><?=e($kg($freeKg))?> · non réservés · voir les lots</span></button>
</div>

<section class="fish-section" aria-labelledby="delivery-queue-title"><header class="pig-ref-section-head"><div><h3 id="delivery-queue-title">Commandes à livrer <span class="pig-ref-count"><?=count($orders)?></span></h3><p>Les sacs réservés au client, prêts au bureau principal ou directement au site de production.</p></div></header>
<?php if(!$ready): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Les livraisons de commandes ne sont pas encore activées : appliquez la migration <code>049_add_customer_deliveries.sql</code>.</p>
<?php elseif(!$orders): ?><p class="flow-empty">Aucune commande en cours. Les commandes validées apparaissent ici, avec les sacs qui leur sont réservés.</p>
<?php else: ?><div class="delivery-queue">
<?php foreach($orders as $o):$waiting=$o['ready_bags']<=0;$blocked=$o['customer_status']!=='active';$delivered=(int)$o['delivered_bags'];$total=max(1,(int)$o['quantity_bags']); ?>
<article class="delivery-card<?=$waiting?' is-waiting':''?>" data-delivery-order="<?=e($o['id'])?>">
<header><div><strong><?=e($o['customer_name'])?></strong><small><?=e($o['order_number'].' · '.$o['customer_code'].($o['customer_phone']?' · '.$o['customer_phone']:''))?></small></div><span class="ui-status ui-status--<?=$blocked?'warning':($waiting?'neutral':'success')?>"><?=$blocked?'Client bloqué':($waiting?'En attente':'Prête · '.$int($o['ready_bags']).' sac(s)')?></span></header>
<ul class="delivery-card__lines"><?php foreach($o['lines'] as $l): ?><li><span><?=e($l['product_name'])?> <small><?=e($l['format_name'])?></small></span><span><?=$int($l['delivered_bags'])?> / <?=$int($l['quantity_bags'])?> <small>livrés</small></span></li><?php endforeach; ?></ul>
<span class="delivery-card__progress" aria-hidden="true"><span style="width:<?=e(round($delivered/$total*100,2))?>%"></span></span>
<?php if($blocked): ?><p class="delivery-card__where is-muted"><i class="bi bi-slash-circle" aria-hidden="true"></i><span><?=e('Livraison suspendue'.($o['blocked_reason']?' : '.$o['blocked_reason']:'').'.')?></span></p><?php endif; ?>
<?php if(!$waiting): ?><p class="delivery-card__where"><?php foreach($o['options'] as $opt): ?><span><i class="bi <?=$opt['stage']==='office'?'bi-shop-window':'bi-building-gear'?>" aria-hidden="true"></i><span><?=e($opt['site_name'])?> · <strong><?=$int($opt['bags'])?></strong> prêt(s)</span></span><?php endforeach; ?></p>
<?php else:$waits=array_filter([(int)$o['transit_bags']?$int($o['transit_bags']).' en route vers le bureau':'',(int)$o['source_bags']?$int($o['source_bags']).' en préparation d’envoi':'',(int)$o['remaining_bags']?$int($o['remaining_bags']).' à produire':'']); ?><p class="delivery-card__where is-muted"><i class="bi bi-hourglass-split" aria-hidden="true"></i><span><?=e($waits?implode(' · ',$waits):'Aucun sac prêt sur vos sites.')?></span></p><?php endif; ?>
<footer><a class="btn-secondary" href="<?=e(base_url('sales/orders'))?>#order-detail-<?=e($o['id'])?>">Voir la commande</a><?php if($canDeliver&&!$waiting&&!$blocked): ?><button type="button" class="btn-primary" data-workspace-modal-open="deliver-<?=e($o['id'])?>"><i class="bi bi-truck" aria-hidden="true"></i> Livrer</button><?php endif; ?></footer>
</article>
<?php endforeach; ?>
</div><?php endif; ?>
</section>

<section class="fish-section" aria-labelledby="exit-history-title"><header class="pig-ref-section-head"><div><h3 id="exit-history-title">Historique des sorties</h3><p>Bons de livraison des commandes (BL) et bons de sortie sans commande (BS).</p></div></header>
<?php if(!$history): ?><p class="flow-empty">Aucune sortie enregistrée.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" id="distributionsTable" data-butchery-history="distributions" aria-label="Historique des sorties"><thead><tr><th>Bon / date</th><th>Client / destination</th><th>Produits</th><th class="num">Sacs</th><th class="num">Poids</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($history as $h):$isDelivery=$h['kind']==='delivery';[$stateLabel,$stateTone]=($isDelivery?$deliveryLabels:$exitLabels)[$h['status']]??[$h['status'],'neutral'];$detail=($isDelivery?'delivery-detail-':'distribution-detail-').$h['id']; ?>
<tr data-status="<?=e($h['status'])?>" data-type="<?=e($h['kind'])?>" data-date="<?=e(substr($h['at'],0,10))?>">
<td data-label="Bon" class="cell--lead" data-order="<?=e($h['at'])?>"><strong class="cell-main"><?=e($h['number'])?></strong><small class="cell-ref"><?=e($stamp($h['at']))?></small></td>
<td data-label="Client" class="cell--wide"><span class="cell-main" title="<?=e($h['who'])?>"><?=e($h['who'])?></span><small><?=e($h['note'])?></small></td>
<td data-label="Produits" class="cell--wide"><?php foreach(array_slice($h['products'],0,2) as $p): ?><small class="order-line-chip"><?=e($p)?></small><?php endforeach; ?><?php if(count($h['products'])>2): ?><small class="order-line-chip">+<?=count($h['products'])-2?></small><?php endif; ?></td>
<td data-label="Sacs" class="num" data-order="<?=e($h['bags'])?>"><?=$int($h['bags'])?></td>
<td data-label="Poids" class="num" data-order="<?=e($h['kg'])?>"><?=e($kg($h['kg']))?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions"><button type="button" class="btn-secondary row-action-button" data-workspace-modal-open="<?=e($detail)?>" aria-label="<?=e('Consulter '.$h['number'])?>"><i class="bi bi-eye" aria-hidden="true"></i><span>Détails</span></button>
<?php $menuId='exit-menu-'.$h['kind'].'-'.$h['id'];$menuLabel=$h['number'];
$menuItems=[[$isDelivery?'Voir le bon de livraison':'Voir le bon de sortie','bi-file-earmark-pdf','note-preview','',false,$note($isDelivery,$h['id'],$h['number'])]];
if(!$isDelivery)$menuItems[]=['Ouvrir le dossier','bi-folder2-open',base_url('distributions/'.$h['id']),'',true];
$cancellable=$isDelivery&&$canCancelDelivery&&$h['status']==='validated'&&($h['row']['order_status']==='validated'||($h['row']['order_status']==='closed'&&$h['row']['order_close_reason']===CustomerDeliveryService::FULLY_DELIVERED));
if($cancellable)$menuItems[]=['Annuler la livraison','bi-x-circle','delivery-cancel-'.$h['id'],'danger',false];
require $partials.'/row_menu.php'; ?></div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<?php // Delivery drawers: one per order with bags ready on the user's sites.
if($canDeliver)foreach($orders as $o){if($o['ready_bags']>0&&$o['customer_status']==='active')require __DIR__.'/delivery_form.php';} ?>

<?php foreach($history as $h):$r=$h['row'];
if($h['kind']==='delivery'):[$stateLabel,$stateTone]=$deliveryLabels[$h['status']]??[$h['status'],'neutral'];
$cancellable=$canCancelDelivery&&$h['status']==='validated'&&($r['order_status']==='validated'||($r['order_status']==='closed'&&$r['order_close_reason']===CustomerDeliveryService::FULLY_DELIVERED));
$drawerId='delivery-detail-'.$h['id'];$drawerTitle='Livraison '.$h['number'];require $flowViews.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><code><?=e($h['number'])?></code></div>
<?php if($h['status']==='cancelled'): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-x-circle" aria-hidden="true"></i> Annulée le <?=e($stamp($r['cancelled_at']))?> par <?=e($r['canceller_name']?:'—')?> : <?=e((string)$r['cancel_reason'])?></p><?php endif; ?>
<h3>Remise au client</h3><dl class="detail-sheet__grid"><?=$sheetFact('Client',$r['customer_name'],$r['customer_code'])?><?=$sheetFact('Commande',$r['order_number'])?><?=$sheetFact('Départ',$r['site_name'],$stages[$r['source_stage']]??'')?><?=$sheetFact('Livré le',$stamp($r['delivered_at']))?><?=$sheetFact('Transport',$modes[$r['delivery_mode']]??$r['delivery_mode'],trim(($r['vehicle_reference']?:'').($r['driver_name']?' · '.$r['driver_name']:''),' ·'))?><?=$sheetFact('Reçu par',$r['receiver_name'],(string)$r['receiver_phone'])?></dl>
<h3>Sacs livrés</h3><ul class="fish-lot-lines"><?php foreach($r['lines'] as $l): ?><li><span><strong><?=e($l['product_name'])?></strong> <small><?=e($l['format_name'])?></small></span><span><?=$int($l['bags'])?> sac(s)</span><span><?=e($kg($l['kg']))?></span></li><?php endforeach; ?></ul>
<dl class="detail-sheet__grid"><?=$sheetFact('Montant',$money($r['amount_usd']).' USD',$r['currency']==='CDF'&&$r['exchange_rate']?$money((float)$r['amount_usd']*(float)$r['exchange_rate'],0).' CDF au taux de la commande':'Prix figés à la commande')?><?=$sheetFact('Enregistré par',$r['author'],$stamp($r['created_at']))?></dl>
<?php if($r['notes']): ?><p class="livestock-guide"><?=e($r['notes'])?></p><?php endif; ?>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><?php if($cancellable): ?><button type="button" class="btn-secondary" data-workspace-modal-open="delivery-cancel-<?=e($h['id'])?>">Annuler la livraison</button><?php endif; ?><?=$noteButton($note(true,$h['id'],$h['number']),'Voir le bon','btn-primary')?></footer>
<?php require $flowViews.'/drawer_end.php'; ?>
<?php if($cancellable): $drawerId='delivery-cancel-'.$h['id'];$drawerTitle='Annuler '.$h['number'];require $flowViews.'/drawer_start.php';$action='distributions/deliveries/'.$h['id'].'/cancel';$confirm='Annuler cette livraison ? Les sacs reviennent en stock, réservés à la commande.';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide"><?=e($r['customer_name'].' · '.$r['order_number'].' · '.$int($r['bags']).' sac(s)')?>. Les sacs reviennent dans le stock de <?=e($r['site_name'])?>, toujours réservés à la commande<?=$r['order_status']==='closed'?', qui est rouverte':''?>. Le bon de livraison est annulé.</p>
<div class="form-grid"><label class="form-wide"><span>Motif d’annulation *</span><textarea name="reason" maxlength="255" rows="3" required placeholder="Ex. livraison refusée, erreur de saisie"></textarea></label></div>
<?php $submitLabel='Annuler la livraison';$destructiveSubmit=true;require $flowViews.'/form_end.php';unset($destructiveSubmit);require $flowViews.'/drawer_end.php';endif; ?>
<?php else:[$stateLabel,$stateTone]=$exitLabels[$h['status']]??[$h['status'],'neutral'];$drawerId='distribution-detail-'.$h['id'];$drawerTitle='Sortie '.$h['number'];require $flowViews.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><code><?=e($h['number'])?></code></div>
<dl class="detail-sheet__grid"><?=$sheetFact('Destinataire',$r['recipient_name'],!empty($r['customer_id'])?'Client enregistré':'')?><?=$sheetFact('Sortie le',$stamp($r['distributed_at']))?><?=$sheetFact('Produit',$r['product_name'],$r['format_name'])?><?=$sheetFact('Quantité',$int($r['quantity_bags']).' sac(s)',$kg($r['total_weight_kg']))?><?=$sheetFact('Site',$r['site_code'],'Lot #'.$r['finished_stock_id'])?><?=$sheetFact('Transporteur',$r['transporter']?:'—')?><?=$sheetFact('Créée par',$r['agent_name']?:'—')?><?=$sheetFact('Validée par',$r['validator_name']?:'—')?></dl>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><a class="btn-secondary" href="<?=e(base_url('distributions/'.$h['id']))?>">Ouvrir le dossier</a><?=$noteButton($note(false,$h['id'],$h['number']),'Voir le bon','btn-primary')?></footer>
<?php require $flowViews.'/drawer_end.php';endif;endforeach; ?>

<?php $drawerId='distribution-kpi-stock';$drawerTitle='Stock libre · hors réservations';require $flowViews.'/drawer_start.php'; ?>
<div class="feed-form-body"><?php if(!$availableStocks): ?><p class="flow-empty">Aucun stock libre.</p><?php else: ?><div class="table-responsive"><table class="enterprise-table compact-table" data-datatable="false"><thead><tr><th>Site / lot</th><th>Produit</th><th class="num">Sacs libres</th><th class="num">Poids</th></tr></thead><tbody><?php foreach($availableStocks as $s): ?><tr><td><?=e($s['site_name']??$s['site_code'])?><small>Lot #<?=e($s['id'])?><?=!empty($s['stock_date'])?' · depuis le '.e(date('d/m/Y',strtotime($s['stock_date']))):''?></small></td><td><?=e($s['product_name'])?><small><?=e($s['format_name'])?></small></td><td class="num"><?=$int($s['quantity_bags'])?></td><td class="num"><?=e($kg($s['total_weight_kg']))?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button></footer>
<?php require $flowViews.'/drawer_end.php'; ?>
<?php if($canExit)require __DIR__.'/modal_create.php'; ?>
<?php if($history)require __DIR__.'/note_preview.php'; ?>
<div class="entity-modal-backdrop workspace-modal-backdrop" data-workspace-modal-close></div>
</div>
