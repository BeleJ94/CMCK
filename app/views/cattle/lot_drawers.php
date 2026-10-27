<?php
// Read-only sheet of each cattle lot, opened from the row action menu.
$flowViews=dirname(__DIR__).'/porcine_flow';
$sheetFact=static fn($label,$value,$note='')=>'<div><dt>'.e($label).'</dt><dd>'.e($value).($note!==''?'<small>'.e($note).'</small>':'').'</dd></div>';
?>
<?php foreach($lots as $l):
$lotSlaughters=array_values(array_filter($slaughters,static fn($s)=>(int)$s['batch_id']===(int)$l['id']&&$s['status']!=='cancelled'));
$active=$l['status']==='active';
?>
<?php $drawerId='cattle-lot-detail-'.$l['id'];$drawerTitle=($l['supplier_name']?:'Lot de bovins').' · '.date('d/m/Y',strtotime($l['received_at']??$l['started_at']));require $flowViews.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--<?=$active?'progress':'success'?>"><?=$active?'En élevage':'Lot terminé'?></span><code><?=e($l['batch_number'])?></code></div>
<div class="fish-lot-score"><div><span>Vivants</span><strong><?=$int($l['current_heads'])?> / <?=$int($l['initial_heads'])?></strong></div><div><span>Carcasses</span><strong><?=$kg($l['carcass_kg'])?> kg</strong></div><div><span>Rendement carcasse</span><strong><?=$l['carcass_yield_pct']!==null?e(number_format($l['carcass_yield_pct'],1,',',' ')).' %':'—'?></strong></div></div>
<h3>Réception</h3>
<?php if($l['receipt_id']): ?>
<dl class="detail-sheet__grid"><?=$sheetFact('Fournisseur',$l['supplier_name'],$l['delivery_reference']?'Bon de livraison '.$l['delivery_reference']:'Sans bon de livraison')?><?=$sheetFact('Arrivée',date('d/m/Y à H:i',strtotime($l['received_at'])),$l['receiver_name']?'Réceptionné par '.$l['receiver_name']:'')?><?=$sheetFact('Bâtiment',$l['facility_name'],$l['site_name'])?><?=$sheetFact('Poids vif',$l['live_weight_kg']!==null?$kg($l['live_weight_kg']).' kg':'Non pesé',$l['live_weight_kg']!==null?((float)$l['live_tare_kg']>0?'Brut '.$kg($l['live_gross_kg']).' − tare '.$kg($l['live_tare_kg']).($l['tare_note']?' ('.$l['tare_note'].')':''):'Poids net').' · '.number_format((float)$l['average_live_kg'],1,',',' ').' kg / tête':'')?><?php if($l['notes']): ?><?=$sheetFact('Observations',$l['notes'])?><?php endif; ?><?=$sheetFact('Référence',$l['receipt_number'])?></dl>
<?php else: ?><p class="flow-empty">Lot créé avant le suivi des réceptions fournisseurs : son origine n’est pas renseignée.</p><dl class="detail-sheet__grid"><?=$sheetFact('Mise en élevage',date('d/m/Y',strtotime($l['started_at'])))?><?=$sheetFact('Bâtiment',$l['facility_name'],$l['site_name'])?></dl><?php endif; ?>
<h3>Effectifs</h3><dl class="detail-sheet__grid"><?=$sheetFact('Reçus',$int($l['initial_heads']))?><?=$sheetFact('Vivants',$int($l['current_heads']),(int)$l['pending_heads']?$int($l['pending_heads']).' en attente de validation d’abattage':'')?><?=$sheetFact('Abattus à la ferme',$int($l['slaughtered_heads']))?><?php if($l['other_exits']||(int)$l['dead_heads']): ?><?=$sheetFact('Autres sorties',$int($l['other_exits']+(int)$l['dead_heads']),($l['dead_heads']?$int($l['dead_heads']).' mort(s) · ':'').'Transferts vivants antérieurs inclus')?><?php endif; ?></dl>
<h3>Abattages <span class="pig-ref-count"><?=count($lotSlaughters)?></span></h3>
<?php if(!$lotSlaughters): ?><p class="flow-empty">Aucun abattage pour ce lot.</p><?php else: ?>
<ul class="fish-lot-lines"><?php foreach($lotSlaughters as $s): ?><li><span><strong><?=e(date('d/m/Y',strtotime($s['converted_at'])))?></strong><?php if($s['status']==='submitted'): ?> <span class="tag-chip">À valider</span><?php endif; ?></span><span><?=$int($s['input_heads'])?> tête(s)</span><span><?=$kg($s['net_weight_kg'])?> kg</span></li><?php endforeach; ?></ul>
<?php endif; ?>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><a class="btn-secondary" href="<?=e(base_url('livestock/cattle/slaughters'))?>">Abattages et envois</a>
<?php if($active&&$l['available_heads']>0&&Auth::can('livestock','create',(int)$l['site_id'])): ?><button type="button" class="btn-primary" data-workspace-modal-open="cattle-slaughter" data-preselect-field="batch_id" data-preselect-value="<?=e($l['id'])?>" data-workspace-return-focus="cattle-lot-menu-<?=e($l['id'])?>-toggle"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Abattre des bovins</button><?php endif; ?>
</footer>
<?php require $flowViews.'/drawer_end.php'; ?>
<?php endforeach; ?>
