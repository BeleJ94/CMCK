<?php
$flowViews=dirname(__DIR__).'/porcine_flow';$partials=dirname(__DIR__).'/partials';$returnPath='sales/orders';$drawerKicker='DISTRIBUTION · COMMANDES CLIENTS';
$int=static fn($v)=>number_format((int)$v,0,',',' ');$money=static fn($v,$d=2)=>number_format((float)$v,$d,',',' ');$stamp=static fn($at)=>date('d/m/Y H:i',strtotime($at));
$sheetFact=static fn($label,$value,$note='')=>'<div><dt>'.e($label).'</dt><dd>'.e($value).($note!==''?'<small>'.e($note).'</small>':'').'</dd></div>';
$amount=static fn(array $o)=>$money($o['amount_usd']).' USD'.($o['currency']==='CDF'&&$o['exchange_rate']?' · '.$money((float)$o['amount_usd']*(float)$o['exchange_rate'],0).' CDF':'');
$transferStates=['draft'=>'Brouillon','submitted'=>'À approuver','approved'=>'Approuvé','reserved'=>'À expédier','partially_shipped'=>'Expédié en partie','in_transit'=>'En route','partially_received'=>'Reçu en partie','quality_control'=>'Contrôle qualité','partially_accepted'=>'Accepté en partie','accepted'=>'Reçu','rejected'=>'Refusé','return_pending'=>'Retour en attente','returned'=>'Retour reçu','closed'=>'Clôturé','cancelled'=>'Annulé'];
$selfValidate=$ready&&Auth::canSelfValidate(Auth::user()['id'],'distributions');$me=(int)Auth::user()['id'];
$priced=$ready?count(array_filter($catalog,static fn($r)=>$r['price_usd']!==null)):0;
$canOrder=$ready&&$canCreate&&$customers&&$depots&&$priced;
$segments=static function(array $o)use($int){
    $total=max(1,(int)$o['quantity_bags']);$reserved=(int)$o['source_bags']+(int)$o['awaiting_bags'];
    $parts=[['delivered_bags','Livré','is-delivered'],['office_bags','Au bureau','is-office'],['transit_bags','En route','is-transit'],[$reserved,'Réservé','is-reserved'],['remaining_bags','À produire','is-missing']];
    $bar='';$text=[];
    foreach($parts as [$key,$label,$class]){$value=is_int($key)?$key:(int)$o[$key];if($value<=0)continue;$bar.='<span class="'.$class.'" style="width:'.round($value/$total*100,2).'%" title="'.e($label.' : '.$int($value)).'"></span>';$text[]=$label.' '.$int($value);}
    return '<span class="order-progress" aria-hidden="true">'.$bar.'</span><small>'.e($text?implode(' · ',$text):'—').'</small>';
};
?>
<div class="sales-orders-page" data-sales-orders>
<header class="ui-page-header"><span class="ui-page-header__icon"><i class="bi bi-receipt" aria-hidden="true"></i></span><div class="ui-page-header__content"><p class="section-label">DISTRIBUTION · COMMANDES CLIENTS</p><h2>Commandes clients</h2><p>Chaque commande réserve ses sacs au site de production, puis les envoie au bureau principal, où ils restent réservés au client jusqu’à la livraison.</p></div><div class="ui-page-header__actions"><a class="btn-secondary" href="<?=e(base_url('customers'))?>"><i class="bi bi-person-vcard" aria-hidden="true"></i> Clients</a><a class="btn-secondary" href="<?=e(base_url('sales/settings'))?>"><i class="bi bi-currency-exchange" aria-hidden="true"></i> Tarifs et taux</a><?php if($canOrder): ?><button type="button" class="btn-primary" data-workspace-modal-open="order-new"><i class="bi bi-plus-circle" aria-hidden="true"></i> Nouvelle commande</button><?php endif; ?></div></header>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?><?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if(!$ready): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Les commandes clients ne sont pas encore activées : appliquez les migrations <code>047</code> et <code>048_add_customer_orders.sql</code>.</p>
<?php else: ?>
<?php if($canCreate&&!$canOrder): ?><p class="app-alert alert-info" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Avant la première commande : <?=e(implode(', ',array_filter([!$customers?'créez une fiche client active':'',!$priced?'fixez les tarifs entreprises':'',!$depots?'désignez les dépôts du bureau principal':''])))?>.</p><?php endif; ?>
<?php $active=array_values(array_filter($orders,static fn($o)=>$o['status']==='validated')); ?>
<div class="ui-kpi-grid fish-kpis" aria-label="Synthèse des commandes">
<?php foreach([
    ['À valider',$int(count(array_filter($orders,static fn($o)=>$o['status']==='draft'))),'bi-hourglass-split',count(array_filter($orders,static fn($o)=>$o['status']==='draft'))?'warning':'','Validation par un autre utilisateur'],
    ['En cours',$int(count($active)),'bi-receipt','',$int(array_sum(array_column($active,'quantity_bags'))).' sac(s) commandé(s)'],
    ['Au bureau principal',$int(array_sum(array_column($active,'office_bags'))).' sacs','bi-shop-window','','Réservés aux clients, à livrer'],
    ['À produire',$int(array_sum(array_column($active,'remaining_bags'))).' sacs','bi-gear',array_sum(array_column($active,'remaining_bags'))?'warning':'','Réservés dès leur emballage'],
] as [$label,$value,$icon,$tone,$meta]): ?>
<section class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e($value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></section>
<?php endforeach; ?>
</div>

<section class="fish-section" aria-labelledby="orders-title"><header class="pig-ref-section-head"><div><h3 id="orders-title">Commandes</h3><p>Avancement en sacs : au bureau, en route, réservés au site de production, à produire.</p></div></header>
<?php if(!$orders): ?><p class="flow-empty">Aucune commande.<?=$canOrder?' Créez la première avec « Nouvelle commande ».':''?></p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Commandes clients" data-search-placeholder="Rechercher une commande, un client…"><thead><tr><th>Commande</th><th>Client</th><th>Produits</th><th class="num">Montant</th><th>Avancement</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($orders as $o):[$stateLabel,$stateTone]=CustomerOrderService::STATUSES[$o['status']];$draft=$o['status']==='draft';$open=$o['status']==='validated';
$canValidateOrder=$draft&&$canValidate&&((int)$o['created_by']!==$me||$selfValidate);$canEdit=$draft&&$canCreate&&((int)$o['created_by']===$me||Auth::can('distributions','update'));
$canShip=$open&&$canCreate&&$canTransfer&&$o['plannable_bags']>0;$canReserve=$open&&$canCreate&&$o['remaining_bags']>0;$canCancel=in_array($o['status'],['draft','validated'],true)&&$canValidate&&!$o['in_flight']&&!(int)$o['delivered_bags'];
$canDeliver=$open&&$canCreate&&((int)$o['office_bags']+$o['plannable_bags'])>0;$canClose=$open&&$canValidate&&!$o['in_flight']&&(int)$o['delivered_bags']>0; ?>
<tr data-order-id="<?=e($o['id'])?>">
<td data-label="Commande" class="cell--lead" data-order="<?=e($o['created_at'])?>"><strong class="cell-main"><?=e($o['order_number'])?></strong><small class="cell-ref"><?=e($stamp($o['created_at']))?></small></td>
<td data-label="Client" class="cell--wide"><span class="cell-main" title="<?=e($o['customer_name'])?>"><?=e($o['customer_name'])?></span><small><?=e($o['customer_code'].' · '.$o['destination_name'])?></small></td>
<td data-label="Produits" class="cell--wide"><?php foreach(array_slice($o['lines'],0,2) as $l): ?><small class="order-line-chip"><?=e($l['format_name'].' × '.$int($l['quantity_bags']))?></small><?php endforeach; ?><?php if(count($o['lines'])>2): ?><small class="order-line-chip">+<?=count($o['lines'])-2?></small><?php endif; ?></td>
<td data-label="Montant" class="num" data-order="<?=e($o['amount_usd'])?>"><strong><?=e($money($o['amount_usd']))?> USD</strong><?php if($o['currency']==='CDF'&&$o['exchange_rate']): ?><small><?=e($money((float)$o['amount_usd']*(float)$o['exchange_rate'],0))?> CDF</small><?php endif; ?></td>
<td data-label="Avancement" class="order-progress-cell"><?=$open?$segments($o):'<small class="cell-muted">'.($draft?$int($o['quantity_bags']).' sac(s) à réserver après validation':'—').'</small>'?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><?php if($o['status']==='cancelled'): ?><small title="<?=e((string)$o['cancel_reason'])?>"><?=e((string)$o['cancel_reason'])?></small><?php elseif($o['status']==='closed'): ?><small><?=$int($o['delivered_bags'])?> / <?=$int($o['quantity_bags'])?> livrés</small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canValidateOrder):$rowAction='sales/orders/'.$o['id'].'/validate';$rowConfirm='Valider la commande '.$o['order_number'].' ? Les sacs disponibles seront réservés au site de production.';$rowLabel='Valider';$rowIcon='bi-check2-circle';require $partials.'/row_form.php';
elseif($canDeliver&&((int)$o['office_bags']||!$canShip)): ?><a class="btn-primary row-action-button" href="<?=e(base_url('distributions'))?>#deliver-<?=e($o['id'])?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Livrer</span></a>
<?php elseif($canShip): ?><button type="button" class="btn-primary row-action-button" data-workspace-modal-open="order-transfer-<?=e($o['id'])?>"><i class="bi bi-truck" aria-hidden="true"></i><span>Envoyer</span></button><?php endif; ?>
<?php $menuId='order-menu-'.$o['id'];$menuLabel=$o['order_number'];$menuItems=[['Consulter la commande','bi-eye','order-detail-'.$o['id'],'',false]];
if($canEdit)$menuItems[]=['Modifier le brouillon','bi-pencil','order-edit-'.$o['id'],'',false];
if($canReserve)$menuItems[]=['Réserver le disponible','bi-box-seam','order-reserve-'.$o['id'],'',false];
if($canShip&&$canDeliver&&(int)$o['office_bags'])$menuItems[]=['Envoyer au bureau principal','bi-truck','order-transfer-'.$o['id'],'',false];
if($canClose)$menuItems[]=['Clôturer la commande','bi-check2-square','order-close-'.$o['id'],'',false];
if($canCancel)$menuItems[]=['Annuler la commande','bi-x-circle','order-cancel-'.$o['id'],'danger',false];
require $partials.'/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<?php foreach($orders as $o):[$stateLabel,$stateTone]=CustomerOrderService::STATUSES[$o['status']];$open=$o['status']==='validated';
$canShip=$open&&$canCreate&&$canTransfer&&$o['plannable_bags']>0;$canReserve=$open&&$canCreate&&$o['remaining_bags']>0;$canCancel=in_array($o['status'],['draft','validated'],true)&&$canValidate&&!$o['in_flight']&&!(int)$o['delivered_bags'];
$canDeliver=$open&&$canCreate&&((int)$o['office_bags']+$o['plannable_bags'])>0;$canClose=$open&&$canValidate&&!$o['in_flight']&&(int)$o['delivered_bags']>0; ?>
<?php $drawerId='order-detail-'.$o['id'];$drawerTitle='Commande '.$o['order_number'];require $flowViews.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span><code><?=e($o['order_number'])?></code></div>
<?php if($o['status']==='cancelled'): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-x-circle" aria-hidden="true"></i> Annulée le <?=e($stamp($o['cancelled_at']))?> par <?=e($o['canceller_name']?:'—')?> : <?=e((string)$o['cancel_reason'])?></p><?php elseif($o['status']==='closed'&&!empty($o['closed_at'])): ?><p class="app-alert alert-info" role="status"><i class="bi bi-check2-square" aria-hidden="true"></i> Soldée le <?=e($stamp($o['closed_at']))?> par <?=e($o['closer_name']?:'—')?> : <?=e((string)$o['close_reason'])?> · <?=$int($o['delivered_bags'])?> / <?=$int($o['quantity_bags'])?> sac(s) livrés.</p><?php endif; ?>
<h3>Commande</h3><dl class="detail-sheet__grid"><?=$sheetFact('Client',$o['customer_name'],$o['customer_code'])?><?=$sheetFact('Dépôt de destination',$o['destination_name'],'Bureau principal')?><?=$sheetFact('Montant',$amount($o),$o['currency']==='CDF'&&$o['exchange_rate']?'Taux figé : 1 USD = '.$money($o['exchange_rate']).' CDF':'Facturé en '.$o['currency'])?><?=$sheetFact('Livraison souhaitée',$o['requested_date']?date('d/m/Y',strtotime($o['requested_date'])):'—',(string)$o['notes'])?></dl>
<h3>Lignes</h3>
<div class="table-responsive"><table class="enterprise-table compact-table order-lines-detail" data-datatable="false" aria-label="Lignes de <?=e($o['order_number'])?>"><thead><tr><th>Produit</th><th class="num">Commandé</th><th class="num">Prix</th><th class="num">Réservé au site</th><th class="num">En route</th><th class="num">Au bureau</th><th class="num">Livré</th><th class="num">À produire</th></tr></thead><tbody>
<?php foreach($o['lines'] as $l): ?><tr><td><strong><?=e($l['product_name'])?></strong><small><?=e($l['format_name'].' · '.$l['source_name'])?></small></td><td class="num"><?=$int($l['quantity_bags'])?></td><td class="num"><?=e($money($l['unit_price_usd']))?><small>USD / sac</small></td><td class="num"><?=$int((int)$l['source_bags']+(int)$l['awaiting_bags'])?><?php if((int)$l['planned_bags']||(int)$l['awaiting_bags']): ?><small><?=$int((int)$l['planned_bags']+(int)$l['awaiting_bags'])?> en préparation d’envoi</small><?php endif; ?></td><td class="num"><?=$int($l['transit_bags'])?></td><td class="num"><?=$int($l['office_bags'])?></td><td class="num"><?=$int($l['delivered_bags'])?></td><td class="num<?=(int)$l['remaining_bags']&&$open?' is-warning':''?>"><?=$int($l['remaining_bags'])?></td></tr><?php endforeach; ?>
</tbody></table></div>
<h3>Envois vers le bureau <span class="pig-ref-count"><?=count($o['transfers'])?></span></h3>
<?php if(!$o['transfers']): ?><p class="flow-empty">Aucun envoi pour cette commande.</p><?php else: ?><ul class="fish-lot-lines"><?php foreach($o['transfers'] as $t): ?><li><span><a href="<?=e(base_url('transfers/'.$t['id']))?>"><strong><?=e($t['transfer_number'])?></strong></a> <small><?=e($t['source_name'].' → '.$t['destination_name'])?></small></span><span><?=$int($t['bags'])?> sac(s)</span><span><span class="ui-status ui-status--<?=in_array($t['status'],['accepted','closed'],true)?'success':($t['status']==='cancelled'?'neutral':'progress')?>"><?=e($transferStates[$t['status']]??$t['status'])?></span></span></li><?php endforeach; ?></ul><?php endif; ?>
<h3>Journal des sacs</h3>
<?php if(!$o['movements']): ?><p class="flow-empty">Aucun mouvement pour l’instant.</p><?php else: ?><ul class="fish-lot-lines"><?php foreach($o['movements'] as $m): ?><li><span><strong><?=e($stamp($m['created_at']))?></strong> <small><?=e(CustomerOrderService::MOVEMENTS[$m['movement_type']].($m['transfer_number']?' · '.$m['transfer_number']:'').($m['notes']?' · '.$m['notes']:''))?></small></span><span><?=e($m['format_name'])?></span><span><?=$int($m['quantity_bags'])?> sac(s)<?=$m['site_name']?' · '.e($m['site_name']):''?></span></li><?php endforeach; ?></ul><?php endif; ?>
<h3>Suivi</h3><dl class="detail-sheet__grid"><?=$sheetFact('Saisie par',$o['creator_name'],$stamp($o['created_at']))?><?=$sheetFact('Validée par',$o['validator_name']?:'—',$o['validated_at']?$stamp($o['validated_at']):'')?></dl>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><?php if($canReserve):$rowAction='sales/orders/'.$o['id'].'/reserve';$rowConfirm='Réserver les sacs disponibles au site de production pour cette commande ?';$rowLabel='Réserver le disponible';$rowIcon='bi-box-seam';$rowClass='btn-secondary';require $partials.'/row_form.php';endif; ?><?php if($canShip): ?><button type="button" class="<?=$canDeliver&&(int)$o['office_bags']?'btn-secondary':'btn-primary'?>" data-workspace-modal-open="order-transfer-<?=e($o['id'])?>"><i class="bi bi-truck" aria-hidden="true"></i> Envoyer au bureau</button><?php endif; ?><?php if($canDeliver): ?><a class="btn-primary" href="<?=e(base_url('distributions'))?>#deliver-<?=e($o['id'])?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Livrer</a><?php endif; ?></footer>
<?php require $flowViews.'/drawer_end.php'; ?>
<?php if($canShip): $drawerId='order-transfer-'.$o['id'];$drawerTitle='Envoyer au bureau principal';require $flowViews.'/drawer_start.php';$action='sales/orders/'.$o['id'].'/transfer';$confirm='Préparer cet envoi et le soumettre pour approbation ?';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide"><?=e($o['order_number'].' · '.$o['customer_name'])?>. Un envoi par site de production, soumis pour approbation, puis expédié et réceptionné dans Transferts inter-sites. À la réception, les sacs restent réservés au client.</p>
<div class="form-grid"><label class="form-wide"><span>Dépôt de destination *</span><select name="destination_site_id" required><?php foreach($depots as $d): ?><option value="<?=e($d['id'])?>"<?=(int)$d['id']===(int)$o['destination_site_id']?' selected':''?>><?=e($d['name'])?></option><?php endforeach; ?></select></label>
<?php foreach($o['lines'] as $l):$free=max(0,(int)$l['source_bags']-(int)$l['planned_bags']);if(!$free)continue; ?><label><span><?=e($l['product_name'].' · '.$l['format_name'])?></span><input type="number" name="bags[<?=e($l['id'])?>]" min="0" max="<?=e($free)?>" step="1" inputmode="numeric" value="<?=e($free)?>"><small><?=$int($free)?> sac(s) réservé(s) à <?=e($l['source_name'])?>, prêts à partir.</small></label><?php endforeach; ?>
<label class="form-wide"><span>Observations</span><input name="notes" maxlength="200" placeholder="Camion prévu, horaire…"></label></div>
<?php $submitLabel='Préparer l’envoi';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>
<?php if($canReserve): $drawerId='order-reserve-'.$o['id'];$drawerTitle='Réserver le disponible';require $flowViews.'/drawer_start.php'; ?>
<div class="feed-form-body"><p class="livestock-guide"><?=e($o['order_number'])?> attend encore <?=$int($o['remaining_bags'])?> sac(s). Les sacs libres au site de production leur sont réservés, les plus anciens d’abord. Les nouveaux emballages sont réservés automatiquement.</p></div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><?php $rowAction='sales/orders/'.$o['id'].'/reserve';$rowConfirm='Réserver les sacs disponibles pour cette commande ?';$rowLabel='Réserver';$rowIcon='bi-box-seam';require $partials.'/row_form.php'; ?></footer>
<?php require $flowViews.'/drawer_end.php';endif; ?>
<?php if($canClose): $drawerId='order-close-'.$o['id'];$drawerTitle='Clôturer '.$o['order_number'];require $flowViews.'/drawer_start.php';$action='sales/orders/'.$o['id'].'/close';$confirm='Clôturer cette commande ? Le reste non livré ne sera plus attendu et ses réservations seront libérées.';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide"><?=e($o['order_number'].' · '.$o['customer_name'])?> : <?=$int($o['delivered_bags'])?> sac(s) livré(s) sur <?=$int($o['quantity_bags'])?>. La commande est soldée en l’état : les <?=$int((int)$o['quantity_bags']-(int)$o['delivered_bags'])?> sac(s) restants ne sont plus attendus, les sacs réservés au site de production et au bureau redeviennent libres, et les envois non approuvés sont annulés.</p>
<div class="form-grid"><label class="form-wide"><span>Motif de clôture *</span><textarea name="reason" maxlength="255" rows="3" required placeholder="Ex. le client renonce au reste, quantité ajustée"></textarea></label></div>
<?php $submitLabel='Clôturer la commande';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>
<?php if($canCancel): $drawerId='order-cancel-'.$o['id'];$drawerTitle='Annuler '.$o['order_number'];require $flowViews.'/drawer_start.php';$action='sales/orders/'.$o['id'].'/cancel';$confirm='Annuler cette commande ? Toutes ses réservations seront libérées.';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide"><?=e($o['order_number'].' · '.$o['customer_name'])?>. Les sacs réservés au site de production et au bureau principal redeviennent libres ; les envois non approuvés sont annulés.</p>
<div class="form-grid"><label class="form-wide"><span>Motif d’annulation *</span><textarea name="reason" maxlength="255" rows="3" required placeholder="Ex. demande du client, erreur de saisie"></textarea></label></div>
<?php $submitLabel='Annuler la commande';$destructiveSubmit=true;require $flowViews.'/form_end.php';unset($destructiveSubmit);require $flowViews.'/drawer_end.php';endif; ?>
<?php if($o['status']==='draft'&&$canCreate&&((int)$o['created_by']===$me||Auth::can('distributions','update')))require __DIR__.'/order_form.php'; ?>
<?php endforeach; ?>
<?php if($canOrder){$o=null;require __DIR__.'/order_form.php';} ?>
<?php endif; ?>
<div class="entity-modal-backdrop workspace-modal-backdrop" data-workspace-modal-close></div>
</div>
