<?php
// Read-only sheet of each poultry lot, opened from the row action menu.
$flowViews=dirname(__DIR__).'/porcine_flow';
$sheetFact=static fn($label,$value,$note='')=>'<div><dt>'.e($label).'</dt><dd>'.e($value).($note!==''?'<small>'.e($note).'</small>':'').'</dd></div>';
$originLabels=['supplier'=>'Réception fournisseur','hatch'=>'Éclosion à la ferme','existing'=>'Lot antérieur'];
?>
<?php foreach($lots as $l):
$lotSlaughters=array_values(array_filter($slaughters,static fn($s)=>(int)$s['batch_id']===(int)$l['id']&&$s['status']!=='cancelled'));
$lotRecords=array_slice(array_values(array_filter($records,static fn($r)=>(int)$r['batch_id']===(int)$l['id'])),0,7);
$active=$l['status']==='active';$layer=$l['flock_type']==='layer';
?>
<?php $drawerId='poultry-lot-detail-'.$l['id'];$drawerTitle=$typeNames[$l['flock_type']].' · '.$l['facility_name'];require $flowViews.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--<?=$active?'progress':'success'?>"><?=$active?($layer?'En ponte':'En élevage'):'Lot terminé'?></span><code><?=e($l['batch_number'])?></code></div>
<div class="fish-lot-score"><div><span>Vivants</span><strong><?=$int($l['current_heads'])?> / <?=$int($l['initial_heads'])?></strong></div><div><span>Mortalité</span><strong><?=e(number_format($l['mortality_pct'],1,',',' '))?> %</strong></div><div><span><?=$layer?'Œufs ramassés':'Carcasses'?></span><strong><?=$layer?$int($l['eggs']):$kg($l['carcass_kg']).' kg'?></strong></div></div>
<h3>Origine</h3>
<dl class="detail-sheet__grid"><?=$sheetFact('Origine',$originLabels[$l['origin']],$l['origin']==='supplier'?($l['supplier_name']?:''):($l['origin']==='hatch'?'Pondeuses '.$l['mother_number']:'Créé avant le suivi des volailles'))?><?=$sheetFact($l['origin']==='hatch'?'Éclosion':'Arrivée',date('d/m/Y à H:i',strtotime($l['received_at'])),$l['receiver_name']?'Saisi par '.$l['receiver_name']:'')?><?=$sheetFact('Poulailler',$l['facility_name'],$l['site_name'])?><?=$sheetFact('Âge',$l['age_now']!==null?$int($l['age_now']).' jour(s)':'Non renseigné',$l['age_days']!==null?$int($l['age_days']).' jour(s) à l’arrivée':'')?>
<?php if($l['origin']==='supplier'): ?><?=$sheetFact('Bon de livraison',$l['delivery_reference']?:'—',$l['receipt_number']?:'')?><?=$sheetFact('Poids vif',$l['live_weight_kg']!==null?$kg($l['live_weight_kg']).' kg':'Non pesé',$l['live_weight_kg']!==null?((float)$l['live_tare_kg']>0?'Brut '.$kg($l['live_gross_kg']).' − tare '.$kg($l['live_tare_kg']).($l['tare_note']?' ('.$l['tare_note'].')':''):'Poids net').' · '.number_format((float)$l['average_live_kg'],3,',',' ').' kg par oiseau':'')?><?php endif; ?>
<?php if($l['notes']): ?><?=$sheetFact('Observations',$l['notes'])?><?php endif; ?></dl>
<h3>Effectifs</h3><dl class="detail-sheet__grid"><?=$sheetFact($l['origin']==='hatch'?'Éclos':'Reçus',$int($l['initial_heads']))?><?=$sheetFact('Vivants',$int($l['current_heads']),(int)$l['pending_heads']||(int)$l['shipping_heads']?$int((int)$l['pending_heads']+(int)$l['shipping_heads']).' engagé(s) dans un abattage ou un envoi en attente':'')?><?=$sheetFact('Morts',$int($l['dead_heads']),'Saisies du jour et abattages')?><?=$sheetFact('Abattus à la ferme',$int($l['slaughtered_heads']),$l['average_carcass_kg']!==null?number_format($l['average_carcass_kg'],3,',',' ').' kg par carcasse':'')?><?php if(!$layer): ?><?=$sheetFact('Envoyés vivants',$int($l['live_shipped_heads']))?><?php endif; ?><?php if($l['other_exits']): ?><?=$sheetFact('Autres sorties',$int($l['other_exits']),'Ventes du suivi quotidien antérieur')?><?php endif; ?><?php if($layer): ?><?=$sheetFact('Poussins éclos',$int($l['hatched_heads']),'Lots de chair issus de ces pondeuses')?><?php endif; ?></dl>
<h3>Dernières saisies <span class="pig-ref-count"><?=count($lotRecords)?></span></h3>
<?php if(!$lotRecords): ?><p class="flow-empty">Aucune saisie pour ce lot.</p><?php else: ?>
<ul class="fish-lot-lines"><?php foreach($lotRecords as $r): ?><li><span><strong><?=e(date('d/m/Y',strtotime($r['recorded_on'])))?></strong><?php if($r['notes']): ?> <small><?=e($r['notes'])?></small><?php endif; ?></span><span><?=$layer?$int($r['eggs_count']).' œuf(s)':''?></span><span><?=$int($r['mortality_heads'])?> mort(s)</span></li><?php endforeach; ?></ul>
<?php endif; ?>
<h3>Abattages <span class="pig-ref-count"><?=count($lotSlaughters)?></span></h3>
<?php if(!$lotSlaughters): ?><p class="flow-empty">Aucun abattage pour ce lot.</p><?php else: ?>
<ul class="fish-lot-lines"><?php foreach($lotSlaughters as $s): ?><li><span><strong><?=e(date('d/m/Y',strtotime($s['converted_at'])))?></strong><?php if($s['status']==='submitted'): ?> <span class="tag-chip">À valider</span><?php endif; ?></span><span><?=$int($s['input_heads'])?> carcasse(s)</span><span><?=$kg($s['net_weight_kg'])?> kg</span></li><?php endforeach; ?></ul>
<?php endif; ?>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><a class="btn-secondary" href="<?=e(base_url('livestock/poultry/slaughters'))?>">Abattages et envois</a>
<?php if($active&&Auth::can('livestock','update',(int)$l['site_id'])): ?><button type="button" class="btn-primary" data-workspace-modal-open="poultry-record" data-preselect-field="batch_id" data-preselect-value="<?=e($l['id'])?>" data-workspace-return-focus="poultry-lot-menu-<?=e($l['id'])?>-toggle"><i class="bi bi-journal-check" aria-hidden="true"></i> Saisie du jour</button><?php endif; ?>
</footer>
<?php require $flowViews.'/drawer_end.php'; ?>
<?php endforeach; ?>
