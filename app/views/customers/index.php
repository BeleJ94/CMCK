<?php
$flowViews=dirname(__DIR__).'/porcine_flow';$partials=dirname(__DIR__).'/partials';$returnPath='customers';$drawerKicker='DISTRIBUTION · CLIENTS';
$canCreate=$ready&&Auth::can('distributions','create');$canUpdate=$ready&&Auth::can('distributions','update');$canValidate=$ready&&Auth::can('distributions','validate');
$active=array_values(array_filter($customers,static fn($c)=>$c['status']==='active'));$blocked=count($customers)-count($active);
$sheetFact=static fn($label,$value,$note='')=>'<div><dt>'.e($label).'</dt><dd>'.e($value).($note!==''?'<small>'.e($note).'</small>':'').'</dd></div>';
$stamp=static fn($at)=>date('d/m/Y H:i',strtotime($at));
$actionLabels=['customer_create'=>['Création','success'],'customer_update'=>['Modification','progress'],'customer_block'=>['Blocage','warning'],'customer_unblock'=>['Déblocage','success']];
$identifiers=static fn(array $c)=>array_filter(['NIF'=>$c['nif'],'RCCM'=>$c['rccm'],'Id. Nat.'=>$c['id_nat']]);
?>
<div class="customers-page" data-customers-page>
<header class="ui-page-header"><span class="ui-page-header__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span><div class="ui-page-header__content"><p class="section-label">DISTRIBUTION · CLIENTS</p><h2>Clients</h2><p>Fiches des clients entreprises et des particuliers réguliers. Les commandes, les livraisons et les relevés s’y rattacheront.</p></div><div class="ui-page-header__actions"><a class="btn-secondary" href="<?=e(base_url('sales/settings'))?>"><i class="bi bi-currency-exchange" aria-hidden="true"></i> Tarifs et taux</a><?php if($canCreate): ?><button type="button" class="btn-primary" data-workspace-modal-open="customer-new"><i class="bi bi-person-plus" aria-hidden="true"></i> Nouveau client</button><?php endif; ?></div></header>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?><?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if(!$ready): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Le répertoire des clients n’est pas encore activé : appliquez la migration <code>047_add_customers_and_sales_settings.sql</code>.</p>
<?php else: ?>
<div class="ui-kpi-grid fish-kpis" aria-label="Synthèse des clients">
<?php foreach([
    ['Clients actifs',count($active),'bi-person-check','','Peuvent passer commande'],
    ['Entreprises',count(array_filter($active,static fn($c)=>$c['customer_type']==='company')),'bi-building','','Clients actifs'],
    ['Particuliers',count(array_filter($active,static fn($c)=>$c['customer_type']==='individual')),'bi-person','','Avec un compte client'],
    ['Bloqués',$blocked,'bi-slash-circle',$blocked?'warning':'',$blocked?'Aucune nouvelle commande possible':'Aucun client bloqué'],
] as [$label,$value,$icon,$tone,$meta]): ?>
<section class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e((string)$value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></section>
<?php endforeach; ?>
</div>

<section class="fish-section" aria-labelledby="customers-title"><header class="pig-ref-section-head"><div><h3 id="customers-title">Répertoire des clients</h3><p>Recherche par nom, code, téléphone, ville ou identifiant légal.</p></div></header>
<?php if(!$customers): ?><p class="flow-empty">Aucun client enregistré.<?=$canCreate?' Créez la première fiche avec « Nouveau client ».':''?></p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Clients" data-search-placeholder="Rechercher un client, un code, un téléphone, un NIF…"><thead><tr><th>Client</th><th>Type</th><th>Contact</th><th>Ville</th><th>Identifiants</th><th>Devise</th><th>État</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($customers as $c):$ids=$identifiers($c); ?>
<tr data-customer-id="<?=e($c['id'])?>">
<td data-label="Client" class="cell--lead cell--wide" data-order="<?=e($c['name'])?>"><strong class="cell-main" title="<?=e($c['name'])?>"><?=e($c['name'])?></strong><small class="cell-ref"><?=e($c['code'].($c['trade_name']?' · '.$c['trade_name']:''))?></small></td>
<td data-label="Type"><?=e(CustomerService::TYPES[$c['customer_type']])?></td>
<td data-label="Contact" class="cell--wide"><span class="cell-main"><?=e($c['contact_name']?:($c['customer_type']==='individual'?$c['name']:'—'))?></span><small><?=e($c['phone'])?></small></td>
<td data-label="Ville"><?=e($c['city']?:'—')?></td>
<td data-label="Identifiants"><?php if($ids): foreach($ids as $label=>$number): ?><small class="customer-id"><?=e($label.' '.$number)?></small><?php endforeach; else: ?><span class="cell-muted">—</span><?php endif; ?></td>
<td data-label="Devise"><?=e($c['billing_currency'])?></td>
<td data-label="État" class="cell--state"><span class="ui-status ui-status--<?=$c['status']==='active'?'success':'warning'?>"><?=$c['status']==='active'?'Actif':'Bloqué'?></span><?php if($c['status']==='blocked'): ?><small title="<?=e($c['blocked_reason'])?>"><?=e($c['blocked_reason'])?></small><?php endif; ?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canUpdate): ?><button type="button" class="btn-secondary row-action-button" data-workspace-modal-open="customer-edit-<?=e($c['id'])?>"><i class="bi bi-pencil" aria-hidden="true"></i><span>Modifier</span></button><?php endif; ?>
<?php $menuId='customer-menu-'.$c['id'];$menuLabel=$c['code'].' · '.$c['name'];$menuItems=[['Consulter la fiche','bi-eye','customer-detail-'.$c['id'],'',false]];
if($canValidate)$menuItems[]=$c['status']==='active'?['Bloquer le client','bi-slash-circle','customer-block-'.$c['id'],'danger',false]:['Débloquer le client','bi-check2-circle','customer-unblock-'.$c['id'],'',false];
require $partials.'/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<?php if($history): ?>
<section class="pig-ref-panel pig-ref-history fish-section" aria-labelledby="customers-history-title"><header class="pig-ref-section-head"><div><h3 id="customers-history-title">Dernières modifications</h3><p>Chaque modification est enregistrée dans l’audit du système.</p></div></header>
<ol><?php foreach($history as $entry):[$actionLabel,$tone]=$actionLabels[$entry['action']]??[$entry['action'],'neutral']; ?><li><span class="ui-status ui-status--<?=e($tone)?>"><?=e($actionLabel)?></span><span><?=e($entry['description'])?></span><small><?=e(($entry['user_name']?:'Système').' · '.$stamp($entry['created_at']))?></small></li><?php endforeach; ?></ol></section>
<?php endif; ?>

<?php foreach($customers as $c):$ids=$identifiers($c); ?>
<?php $drawerId='customer-detail-'.$c['id'];$drawerTitle=$c['name'];require $flowViews.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--<?=$c['status']==='active'?'success':'warning'?>"><?=$c['status']==='active'?'Actif':'Bloqué'?></span><code><?=e($c['code'])?></code></div>
<?php if($c['status']==='blocked'): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-slash-circle" aria-hidden="true"></i> Bloqué le <?=e($stamp($c['blocked_at']))?> par <?=e($c['blocker_name']?:'—')?> : <?=e($c['blocked_reason'])?></p><?php endif; ?>
<h3>Identité</h3><dl class="detail-sheet__grid"><?=$sheetFact('Type',CustomerService::TYPES[$c['customer_type']])?><?=$sheetFact($c['customer_type']==='company'?'Raison sociale':'Nom',$c['name'],(string)$c['trade_name'])?><?=$sheetFact('Devise de facturation',$c['billing_currency'])?><?php foreach($ids as $label=>$number): ?><?=$sheetFact($label,$number)?><?php endforeach; ?></dl>
<h3>Contact</h3><dl class="detail-sheet__grid"><?php if($c['contact_name']): ?><?=$sheetFact('Personne de contact',$c['contact_name'],(string)$c['contact_role'])?><?php endif; ?><?=$sheetFact('Téléphone',$c['phone'])?><?=$sheetFact('E-mail',$c['email']?:'—')?><?=$sheetFact('Adresse',$c['address']?:'—',(string)$c['city'])?></dl>
<?php if($c['notes']): ?><h3>Observations</h3><p class="customer-notes"><?=e($c['notes'])?></p><?php endif; ?>
<h3>Commandes <span class="pig-ref-count"><?=count($c['orders'])?></span></h3>
<?php if(!$c['orders']): ?><p class="flow-empty">Aucune commande pour ce client.</p><?php else: ?><ul class="fish-lot-lines"><?php foreach(array_slice($c['orders'],0,8) as $order):$state=['draft'=>['Brouillon','neutral'],'validated'=>['En cours','progress'],'closed'=>['Soldée','success'],'cancelled'=>['Annulée','neutral']][$order['status']]; ?><li><span><a href="<?=e(base_url('sales/orders'))?>"><strong><?=e($order['order_number'])?></strong></a> <small><?=e(date('d/m/Y',strtotime($order['created_at'])))?></small></span><span><?=e(number_format((int)$order['bags'],0,',',' '))?> sac(s) · <?=e(number_format((float)$order['amount_usd'],2,',',' '))?> USD</span><span><span class="ui-status ui-status--<?=e($state[1])?>"><?=e($state[0])?></span></span></li><?php endforeach; ?></ul><?php endif; ?>
<h3>Suivi</h3><dl class="detail-sheet__grid"><?=$sheetFact('Créée par',$c['creator_name'],$stamp($c['created_at']))?><?=$sheetFact('Modifiée par',$c['editor_name']?:'—',$c['updated_at']?$stamp($c['updated_at']):'')?></dl>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><?php if($canUpdate): ?><button type="button" class="btn-primary" data-workspace-modal-open="customer-edit-<?=e($c['id'])?>"><i class="bi bi-pencil" aria-hidden="true"></i> Modifier</button><?php endif; ?></footer>
<?php require $flowViews.'/drawer_end.php'; ?>
<?php if($canUpdate)require __DIR__.'/form.php'; ?>
<?php if($canValidate&&$c['status']==='active'): $drawerId='customer-block-'.$c['id'];$drawerTitle='Bloquer '.$c['code'];require $flowViews.'/drawer_start.php';$action='customers/'.$c['id'].'/status';$confirm='Bloquer ce client ? Il ne pourra plus passer de nouvelle commande.';require $flowViews.'/form_start.php'; ?>
<input type="hidden" name="status" value="blocked"><p class="livestock-guide"><?=e($c['code'].' · '.$c['name'])?>. Sa fiche et son historique sont conservés ; il ne pourra plus passer de nouvelle commande.</p>
<div class="form-grid"><label class="form-wide"><span>Motif du blocage *</span><textarea name="reason" maxlength="255" rows="3" required placeholder="Ex. impayés, litige en cours"></textarea></label></div>
<?php $submitLabel='Bloquer le client';$destructiveSubmit=true;require $flowViews.'/form_end.php';unset($destructiveSubmit);require $flowViews.'/drawer_end.php'; ?>
<?php elseif($canValidate): $drawerId='customer-unblock-'.$c['id'];$drawerTitle='Débloquer '.$c['code'];require $flowViews.'/drawer_start.php';$action='customers/'.$c['id'].'/status';$confirm='Débloquer ce client ? Il pourra de nouveau passer commande.';require $flowViews.'/form_start.php'; ?>
<input type="hidden" name="status" value="active"><p class="livestock-guide"><?=e($c['code'].' · '.$c['name'])?> est bloqué depuis le <?=e($stamp($c['blocked_at']))?> : <?=e($c['blocked_reason'])?></p>
<?php $submitLabel='Débloquer le client';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php'; ?>
<?php endif; ?>
<?php endforeach; ?>
<?php if($canCreate){$c=null;require __DIR__.'/form.php';} ?>
<?php endif; ?>
<div class="entity-modal-backdrop workspace-modal-backdrop" data-workspace-modal-close></div>
</div>
