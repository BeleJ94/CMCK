<?php
require __DIR__.'/labels.php';
$t=$transfer;$date=static function($v){return $v?date('d/m/Y H:i',strtotime($v)):'—';};$day=static fn($v)=>$v?date('d/m/Y',strtotime($v)):'—';
$int=static fn($v)=>number_format((int)$v,0,',',' ');
$sum=[];foreach(['requested_bags','reserved_bags','shipped_bags','received_bags','accepted_bags','rejected_bags'] as $field)$sum[$field]=array_sum(array_column($t['items'],$field));
$closed=in_array($t['status'],['closed','cancelled'],true);
$toShip=$closed?0:array_sum(array_map(static fn($i)=>max(0,(int)$i['reserved_bags']-(int)$i['shipped_bags']),$t['items']));
$transit=max(0,$sum['shipped_bags']-$sum['received_bags']);
// Bags still reserved but no longer shippable once the receipt has started: released at closure.
$shippable=in_array($t['status'],['reserved','partially_shipped'],true);
$source=Auth::canAccessSite($t['source_site_id']);$destination=Auth::canAccessSite($t['destination_site_id']);
$canUpdate=Auth::can('transfers','update');$modals=[];
$modal=static function($id,$title,$body)use(&$modals){ob_start();?>
<section id="<?=e($id)?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor butchery-kpi-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?=e($id)?>-title"><header><h2 id="<?=e($id)?>-title"><?=e($title)?></h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><?=$body?></section>
<?php $modals[]=ob_get_clean();};
$shipmentLabels=['draft'=>'Brouillon','dispatched'=>'En route','partially_received'=>'Reçue en partie','received'=>'Reçue','return_pending'=>'Retour à traiter','returned'=>'Retournée'];
$returnLabels=['planned'=>'À expédier','dispatched'=>'En route vers le départ','received'=>'Reçu au départ','closed'=>'Clôturé'];
$actionLabels=['create'=>'Création','submit'=>'Soumission','approve_and_reserve'=>'Validation et réservation','approve'=>'Validation','reserve'=>'Réservation','ship'=>'Expédition','receive'=>'Réception','return_dispatch'=>'Expédition du retour','return_receive'=>'Réception du retour','close'=>'Clôture','cancel_order'=>'Annulation avec la commande'];
$tone=['draft'=>'neutral','submitted'=>'warning','approved'=>'progress','reserved'=>'progress','partially_shipped'=>'progress','in_transit'=>'progress','partially_received'=>'progress','quality_control'=>'warning','partially_accepted'=>'warning','accepted'=>'success','rejected'=>'danger','return_pending'=>'warning','returned'=>'progress','closed'=>'success','cancelled'=>'neutral'][$t['status']]??'neutral';
// First occurrence of each operation, for the progress steps.
$done=[];foreach(array_reverse($t['history']) as $h)if(!isset($done[$h['action']]))$done[$h['action']]=$h;
$validated=$done['approve_and_reserve']??$done['approve']??$done['reserve']??null;
$steps=[
    ['Demande','bi-pencil-square',$done['create']??null,false],
    ['Validation','bi-check2-circle',$validated,false],
    ['Expédition','bi-truck',$done['ship']??null,$shippable&&$toShip>0],
    ['Réception','bi-box-arrow-in-down',$done['receive']??null,$transit>0],
    ['Clôture','bi-flag',$done['close']??null,false],
];
$current=null;foreach($steps as $index=>$step)if(!$step[2]||$step[3]){$current=$index;break;}
$pendingShipments=array_values(array_filter($t['shipments'],static fn($s)=>in_array($s['status'],['dispatched','partially_received'],true)));
$openReturns=array_values(array_filter($t['returns'],static fn($r)=>in_array($r['status'],['planned','dispatched'],true)));
$closable=in_array($t['status'],['accepted','partially_accepted','returned'],true)&&$sum['shipped_bags']==$sum['received_bags']&&!array_filter($t['returns'],static fn($r)=>$r['status']!=='received');
// Pending work, with the button when the user can do it, otherwise who is expected.
$tasks=[];
foreach(['draft'=>['submit','Soumettre','create','Soumettre la demande pour validation.'],'submitted'=>['approve','Valider et réserver','validate','Valider la demande : les '.$int($sum['requested_bags']).' sacs seront réservés à '.$t['source_name'].'.'],'approved'=>['reserve','Réserver','validate','Réserver les sacs validés à '.$t['source_name'].'.']] as $state=>[$action,$label,$right,$text])
    if($t['status']===$state)$tasks[]=['text'=>$text,'form'=>$source&&Auth::can('transfers',$right)?['transfers/'.$t['id'].'/'.$action,$label,$label.' ce transfert ?']:null,'who'=>'Un responsable habilité de '.$t['source_name']];
if(in_array($t['status'],['reserved','partially_shipped'],true)&&$toShip>0)$tasks[]=['text'=>'Expédier '.($sum['shipped_bags']?'les ':'').$int($toShip).' sac(s) '.($sum['shipped_bags']?'restants ':'').'depuis '.$t['source_name'].'.','modal'=>$canUpdate&&$source?['transfer-ship','Expédier','bi-truck']:null,'who'=>'Un utilisateur de '.$t['source_name']];
foreach($pendingShipments as $s){$left=array_sum(array_column($s['items'],'quantity_bags'))-array_sum(array_column($s['items'],'received_bags'));$tasks[]=['text'=>'Réceptionner '.$s['shipment_number'].' à '.$t['destination_name'].' : '.$int($left).' sac(s) attendu(s).','modal'=>$canUpdate&&$destination?['transfer-receive-'.$s['id'],'Réceptionner','bi-box-arrow-in-down']:null,'who'=>'Un utilisateur de '.$t['destination_name']];}
foreach($openReturns as $r){$planned=$r['status']==='planned';$tasks[]=['text'=>($planned?'Renvoyer '.$int($r['bags']??0).' sac(s) refusé(s) vers '.$t['source_name']:'Recevoir à '.$t['source_name'].' les '.$int($r['bags']??0).' sac(s) retourné(s)').' · '.$r['return_number'].'.','form'=>$canUpdate&&($planned?$destination:$source)?['transfers/'.$t['id'].'/returns/'.$r['id'].'/'.($planned?'dispatch':'receive'),$planned?'Expédier le retour':'Recevoir au départ','Confirmer cette opération de retour ?']:null,'who'=>'Un utilisateur de '.($planned?$t['destination_name']:$t['source_name'])];}
if($closable)$tasks[]=['text'=>'Tout est reçu : clôturer le transfert.'.($toShip?' Les '.$int($toShip).' sac(s) non expédiés seront libérés.':''),'form'=>$canUpdate&&$source?['transfers/'.$t['id'].'/close','Clôturer','Clôturer le transfert et libérer les réservations restantes ?']:null,'who'=>'Un utilisateur de '.$t['source_name']];
$lots=[];foreach($t['items'] as $item){$key=$item['product_name'].'|'.$item['format_name'];$lots[$key][]=$item;}
?>
<div class="transfer-page" data-transfer-page>
<header class="transfer-page__header"><a class="icon-button" href="<?=e(base_url('transfers'))?>" aria-label="Retour aux transferts"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>
<div class="transfer-page__title"><p class="section-label"><?=$t['transfer_type']==='internal'?'TRANSFERT INTERNE':'TRANSFERT INTER-SITES'?></p><h2><?=e($t['transfer_number'])?> <span class="ui-status ui-status--<?=e($tone)?> transfer-page__status"><?=e($transferLabels[$t['status']]??$t['status'])?></span></h2>
<p class="transfer-page__route"><strong><?=e($t['source_name'])?></strong> <i class="bi bi-arrow-right" aria-hidden="true"></i> <strong><?=e($t['destination_name'])?></strong> · demandé le <?=e($date($t['requested_at']))?> par <?=e($t['creator_name'])?></p></div>
<div class="transfer-page__actions"><button type="button" class="btn-secondary" data-workspace-modal-open="transfer-info">Informations</button></div></header>
<?php if($success):?><div class="app-alert app-alert-success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="app-alert app-alert-error"><?=e($error)?></div><?php endif;?>
<?php if(!empty($t['customer_order'])):?><p class="app-alert alert-info transfer-order-link" role="status"><i class="bi bi-receipt" aria-hidden="true"></i> Envoi affecté à la commande <a href="<?=e(base_url('sales/orders'))?>"><strong><?=e($t['customer_order']['order_number'])?></strong></a> · <?=e($t['customer_order']['customer_name'].' ('.$t['customer_order']['customer_code'].')')?>. À la réception, les sacs acceptés restent réservés à ce client au bureau principal.</p><?php endif;?>

<?php if($t['status']==='cancelled'):?><p class="app-alert alert-warning" role="status"><i class="bi bi-x-circle" aria-hidden="true"></i> Ce transfert est annulé : ses réservations ont été libérées.</p>
<?php else:?>
<ol class="transfer-steps" aria-label="Avancement du transfert">
<?php foreach($steps as $index=>[$label,$icon,$event,$pending]):$state=$t['status']==='closed'||($event&&!$pending)?'is-done':($index===$current?'is-current':'is-todo'); ?>
<li class="transfer-step <?=$state?>"<?=$index===$current?' aria-current="step"':''?>><span class="transfer-step__icon"><i class="bi <?=$state==='is-done'?'bi-check-lg':e($icon)?>" aria-hidden="true"></i></span><span class="transfer-step__text"><strong><?=e($label)?></strong><small><?=$event?e($day($event['created_at']).' · '.$event['actor_name']).($pending?' · en cours':''):($index===$current?'À faire':'À venir')?></small></span></li>
<?php endforeach;?>
</ol>
<section class="transfer-next<?=$tasks?'':' is-idle'?>" aria-labelledby="transfer-next-title"><h3 id="transfer-next-title"><i class="bi <?=$tasks?'bi-lightning-charge':'bi-check2-all'?>" aria-hidden="true"></i> <?=$tasks?'À faire maintenant':($t['status']==='closed'?'Transfert clôturé le '.e($date($t['closed_at'])):'Rien à faire pour l’instant')?></h3>
<?php if($tasks):?><ul><?php foreach($tasks as $task):?><li><span><?=e($task['text'])?></span><?php if(!empty($task['modal'])):[$target,$label,$icon]=$task['modal'];?><button type="button" class="btn-primary" data-workspace-modal-open="<?=e($target)?>"><i class="bi <?=e($icon)?>" aria-hidden="true"></i> <?=e($label)?></button><?php elseif(!empty($task['form'])):[$action,$label,$confirm]=$task['form'];?><form method="post" action="<?=e(base_url($action))?>" data-confirm="<?=e($confirm)?>"><?=csrf_field()?><button class="btn-primary"><?=e($label)?></button></form><?php else:?><small class="transfer-next__who"><i class="bi bi-hourglass-split" aria-hidden="true"></i> En attente : <?=e($task['who'])?></small><?php endif;?></li><?php endforeach;?></ul>
<?php elseif($t['status']!=='closed'):?><p>Le transfert attend la suite de son traitement.</p><?php endif;?>
</section>
<?php endif;?>

<div class="ui-kpi-grid transfer-kpis" aria-label="Quantités du transfert">
<?php foreach([['Demandés',$sum['requested_bags'],'requested_bags','bi-list-check'],[$shippable||!$toShip?'À expédier':'Non expédiés',$toShip,'to_ship','bi-box-seam'],['En route',$transit,'transit','bi-truck'],['Acceptés',$sum['accepted_bags'],'accepted_bags','bi-check2-circle'],['Refusés',$sum['rejected_bags'],'rejected_bags','bi-x-circle']] as [$label,$value,$key,$icon]):?>
<button type="button" class="ui-kpi<?=$key==='rejected_bags'&&$value?' ui-kpi--warning':''?>" data-workspace-modal-open="transfer-quantities-<?=e($key)?>"><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e($int($value))?></strong><span class="ui-kpi__meta"><?=$key==='to_ship'&&$toShip&&!$shippable?'sacs · libérés à la clôture':'sacs · détail par lot'?></span></button>
<?php ob_start();?><div class="feed-form-body"><div class="table-responsive"><table class="enterprise-table compact-table" data-datatable="false"><thead><tr><th>Produit / lot</th><th class="num">Sacs</th></tr></thead><tbody><?php foreach($t['items'] as $item):$quantity=$key==='transit'?max(0,$item['shipped_bags']-$item['received_bags']):($key==='to_ship'?($closed?0:max(0,$item['reserved_bags']-$item['shipped_bags'])):$item[$key]);?><tr><td><?=e($item['product_name'].' · '.$item['format_name'])?><small>Lot #<?=e($item['source_finished_stock_id'])?></small></td><td class="num"><?=e($quantity)?></td></tr><?php endforeach;?></tbody></table></div></div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button></footer><?php $modal('transfer-quantities-'.$key,$label.' · sacs',ob_get_clean());?>
<?php endforeach;?>
</div>

<div class="transfer-page__grid">
<div class="transfer-page__main">
<section class="transfer-card" aria-labelledby="transfer-products-title"><header><h3 id="transfer-products-title">Produits</h3><small>Quantités en sacs, par lot</small></header>
<?php foreach($lots as $group):$first=$group[0];$req=array_sum(array_column($group,'requested_bags'));$ship=array_sum(array_column($group,'shipped_bags'));$acc=array_sum(array_column($group,'accepted_bags'));$rej=array_sum(array_column($group,'rejected_bags'));?>
<div class="transfer-product"><div class="transfer-product__head"><div><strong><?=e($first['product_name'])?></strong><small><?=e($first['format_name'])?><?=!empty($first['weight_kg'])?' · '.e($int($req*(float)$first['weight_kg'])).' kg demandés':''?></small></div>
<div class="transfer-product__bar" title="<?=e('Acceptés '.$acc.' · en route ou à recevoir '.max(0,$ship-$acc-$rej).' · refusés '.$rej.' sur '.$req)?>"><span class="is-accepted" style="width:<?=$req?round($acc/$req*100,2):0?>%"></span><span class="is-shipped" style="width:<?=$req?round(max(0,$ship-$acc-$rej)/$req*100,2):0?>%"></span><span class="is-rejected" style="width:<?=$req?round($rej/$req*100,2):0?>%"></span></div>
<small class="transfer-product__figures"><?=e($int($acc))?> accepté(s) sur <?=e($int($req))?></small></div>
<div class="table-responsive"><table class="enterprise-table compact-table transfer-lots" data-datatable="false" aria-label="<?=e('Lots · '.$first['product_name'].' '.$first['format_name'])?>"><thead><tr><th>Lot</th><th class="num">Demandés</th><th class="num"><?=$shippable||!$toShip?'À expédier':'Non expédiés'?></th><th class="num">Expédiés</th><th class="num">Acceptés</th><th class="num">Refusés</th></tr></thead><tbody>
<?php foreach($group as $item):?><tr><td>Lot #<?=e($item['source_finished_stock_id'])?><?php if(!empty($item['stock_date'])):?><small>en stock depuis le <?=e($day($item['stock_date']))?></small><?php endif;?></td><td class="num"><?=e($item['requested_bags'])?></td><td class="num"><?=e($closed?0:max(0,$item['reserved_bags']-$item['shipped_bags']))?></td><td class="num"><?=e($item['shipped_bags'])?></td><td class="num"><?=e($item['accepted_bags'])?></td><td class="num<?=$item['rejected_bags']?' is-warning':''?>"><?=e($item['rejected_bags'])?></td></tr><?php endforeach;?>
</tbody></table></div></div>
<?php endforeach;?>
</section>

<section class="transfer-card" aria-labelledby="transfer-shipments-title"><header><h3 id="transfer-shipments-title">Expéditions <span class="pig-ref-count"><?=count($t['shipments'])?></span></h3><small>Chaque départ de camion et sa réception</small></header>
<?php if(!$t['shipments']):?><p class="transfer-card__empty">Aucune expédition pour l’instant.</p><?php else:?><ul class="transfer-rows"><?php foreach($t['shipments'] as $shipment):$sent=array_sum(array_column($shipment['items'],'quantity_bags'));$got=array_sum(array_column($shipment['items'],'received_bags'));?>
<li><span class="transfer-rows__main"><strong><?=e($shipment['shipment_number'])?></strong><small><?=e($date($shipment['dispatched_at']).' · '.($shipment['vehicle_reference']?:'véhicule non renseigné'))?></small></span><span class="transfer-rows__figure"><strong><?=e($int($sent))?></strong><small>sac(s) expédié(s)</small></span><span class="transfer-rows__figure"><strong><?=e($int($got))?></strong><small>reçu(s)</small></span><span><span class="ui-status ui-status--<?=in_array($shipment['status'],['received','returned'],true)?'success':($shipment['status']==='return_pending'?'warning':'progress')?>"><?=e($shipmentLabels[$shipment['status']]??$shipment['status'])?></span></span></li>
<?php endforeach;?></ul><?php endif;?>
</section>

<?php if($t['returns']||$t['non_conformities']):?>
<section class="transfer-card" aria-labelledby="transfer-gaps-title"><header><h3 id="transfer-gaps-title">Écarts et retours</h3><small>Sacs refusés à la réception et leur retour au départ</small></header>
<ul class="transfer-rows">
<?php foreach($t['non_conformities'] as $nc):?><li><span class="transfer-rows__main"><strong><?=e($nc['reference'])?></strong><small><?=e($nc['description'])?></small></span><span class="transfer-rows__figure"><strong><?=e($int($nc['rejected_bags']))?></strong><small>refusé(s) sur <?=e($int($nc['expected_bags']))?></small></span><span></span><span><span class="ui-status ui-status--<?=$nc['status']==='open'?'warning':'success'?>"><?=e(['open'=>'Écart ouvert','resolved'=>'Résolu','closed'=>'Clôturé'][$nc['status']]??$nc['status'])?></span></span></li><?php endforeach;?>
<?php foreach($t['returns'] as $r):?><li><span class="transfer-rows__main"><strong><?=e($r['return_number'])?></strong><small><?=e('Prévu le '.$date($r['planned_at']).($r['dispatched_at']?' · parti le '.$date($r['dispatched_at']):'').($r['received_at']?' · reçu le '.$date($r['received_at']):''))?></small></span><span class="transfer-rows__figure"><strong><?=e($int($r['bags']??0))?></strong><small>sac(s) retourné(s)</small></span><span></span><span><span class="ui-status ui-status--<?=$r['status']==='received'?'success':'warning'?>"><?=e($returnLabels[$r['status']]??$r['status'])?></span></span></li><?php endforeach;?>
</ul></section>
<?php endif;?>
</div>

<aside class="transfer-page__side">
<section class="transfer-card" aria-labelledby="transfer-facts-title"><header><h3 id="transfer-facts-title">En bref</h3></header>
<dl class="transfer-facts"><?php foreach(['Départ'=>$t['source_code'].' · '.$t['source_name'],'Destination'=>$t['destination_code'].' · '.$t['destination_name'],'Demandé par'=>$t['creator_name'].' · '.$date($t['requested_at']),'Validé par'=>$t['approver_name']?$t['approver_name'].' · '.$date($t['approved_at']):'—']+($t['closed_at']?['Clôturé le'=>$date($t['closed_at'])]:[]) as $label=>$value):?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?></dl>
<?php if($t['notes']):?><p class="transfer-facts__notes"><?=nl2br(e($t['notes']))?></p><?php endif;?>
</section>
<?php if(!empty($t['documents'])):?><section class="transfer-card" aria-labelledby="transfer-documents-title"><header><h3 id="transfer-documents-title">Documents <span class="pig-ref-count"><?=count($t['documents'])?></span></h3></header>
<ul class="transfer-documents"><?php foreach($t['documents'] as $document):?><li><a href="<?=e(base_url('documents/'.$document['id']))?>"><i class="bi bi-file-earmark-text" aria-hidden="true"></i><span><strong><?=e($document['document_number'])?></strong><small><?=e($document['type_name'])?></small></span></a></li><?php endforeach;?></ul></section><?php endif;?>
<section class="transfer-card" aria-labelledby="transfer-history-title"><header><h3 id="transfer-history-title">Historique des opérations</h3></header>
<?php if(!$t['history']):?><p class="transfer-card__empty">Aucune opération enregistrée.</p><?php else:?><ol class="transfer-history"><?php foreach($t['history'] as $h):?><li><strong><?=e($actionLabels[$h['action']]??$h['action'])?></strong><small><?=e($date($h['created_at']).' · '.$h['actor_name'])?></small><?php if(!empty($h['reason'])):?><small class="transfer-history__note"><?=e($h['reason'])?></small><?php endif;?></li><?php endforeach;?></ol><?php endif;?>
</section>
</aside>
</div>
<?php
ob_start();?><div class="feed-form-body"><dl class="butchery-history-details"><?php foreach(['Départ'=>$t['source_code'].' · '.$t['source_name'],'Destination'=>$t['destination_code'].' · '.$t['destination_name'],'Demandé le'=>$date($t['requested_at']),'Créé par'=>$t['creator_name'],'Soumis le'=>$date($t['submitted_at']),'Approuvé par'=>$t['approver_name']?:'—','Approuvé le'=>$date($t['approved_at']),'Clôturé le'=>$date($t['closed_at'])] as $label=>$value):?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?></dl><?php if($t['notes']):?><div class="transfer-notes"><strong>Notes</strong><p><?=nl2br(e($t['notes']))?></p></div><?php endif;?></div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button></footer><?php $modal('transfer-info','Informations du transfert',ob_get_clean());
require __DIR__.'/operation_modals.php';
echo implode('',$modals);
?>
</div>
