<?php
// Read-only result sheet of each finished lot, opened from the row action menu.
$flowViews=dirname(__DIR__).'/porcine_flow';
$shipmentLabels=['draft'=>'À approuver','approved'=>'À expédier','in_transit'=>'En route · à peser','received'=>'Reçu'];
$lotFact=static fn($label,$value,$note='')=>'<div><dt>'.e($label).'</dt><dd>'.e($value).($note!==''?'<small>'.e($note).'</small>':'').'</dd></div>';
?>
<?php foreach($closedLots as $l):
$lotHarvests=array_values(array_filter($harvests,static fn($h)=>(int)$h['batch_id']===(int)$l['id']&&$h['status']==='validated'));
$lotShipments=array_values(array_filter($shipments,static fn($t)=>(int)$t['batch_id']===(int)$l['id']&&$t['status']!=='cancelled'));
$weighedShipments=array_values(array_filter($lotShipments,static fn($t)=>$t['weight_gap_kg']!==null));
$sent=array_sum(array_map(static fn($t)=>(float)$t['quantity_kg'],$lotShipments));$sentWeighed=array_sum(array_map(static fn($t)=>(float)$t['quantity_kg'],$weighedShipments));$received=array_sum(array_map(static fn($t)=>(float)$t['received_weight_kg'],$weighedShipments));
$end=$l['last_harvest_at']?new DateTimeImmutable(substr($l['last_harvest_at'],0,10)):null;$days=$end?(int)(new DateTimeImmutable($l['started_at']))->diff($end)->days:null;
$average=(int)$l['harvested_heads']>0?(float)$l['harvested_kg']/(int)$l['harvested_heads']:null;
?>
<?php $drawerId='fish-lot-detail-'.$l['id'];$drawerTitle='Bilan · '.$l['pond_name'].' · '.$l['parcel_code'];require $flowViews.'/drawer_start.php'; ?>
<div class="feed-form-body detail-sheet">
<div class="detail-sheet__status"><span class="ui-status ui-status--success">Lot terminé</span><span class="fish-tag"><?=e($l['species_name'])?></span><code><?=e($l['batch_number'])?></code></div>
<div class="fish-lot-score"><div><span>Survie</span><strong class="fish-survival <?=e($survivalTone($l['survival_pct']===null?null:(float)$l['survival_pct']))?>"><?=$l['survival_pct']!==null?e(number_format((float)$l['survival_pct'],1,',',' ')).' %':'—'?></strong></div><div><span>Récolté</span><strong><?=$kg($l['harvested_kg'])?> kg</strong></div><div><span>Rendement</span><strong><?=$l['yield_kg_m2']!==null?e(number_format($l['yield_kg_m2'],3,',',' ')).' kg/m²':'—'?></strong></div></div>
<h3>Origine</h3><dl class="detail-sheet__grid"><?=$lotFact('Étang · parcelle',$l['pond_name'].' · '.$l['parcel_code'],$l['parcel_name'].((float)$l['area_m2']>0?' · '.$int($l['area_m2']).' m²':''))?><?=$lotFact('Période',date('d/m/Y',strtotime($l['started_at'])).' → '.($end?$end->format('d/m/Y'):'—'),$days!==null?$days.' jour'.($days>1?'s':'').' d’élevage':'')?></dl>
<h3>Effectifs</h3><dl class="detail-sheet__grid"><?=$lotFact('Alevins mis en charge',$int($l['initial_heads']),(float)$l['stocked_kg']>0?$kg($l['stocked_kg']).' kg':'')?><?=$lotFact('Poissons pêchés',$int($l['harvested_heads']),'Survivants comptés à la pêche')?><?=$lotFact('Morts',$int($l['dead_heads']),'Non pêchés lors de la pêche finale')?><?=$lotFact('Poids moyen pêché',$average!==null?($average<1?number_format($average*1000,0,',',' ').' g':$kg($average).' kg'):'—','Par poisson')?></dl>
<h3>Pêches du lot <span class="pig-ref-count"><?=count($lotHarvests)?></span></h3>
<?php if(!$lotHarvests): ?><p class="flow-empty">Aucune pêche validée enregistrée par ce parcours.</p><?php else: ?>
<ul class="fish-lot-lines"><?php foreach($lotHarvests as $h): ?><li><span><strong><?=e(date('d/m/Y',strtotime($h['caught_at'])))?></strong><?php if((int)$h['final_harvest']): ?> <span class="fish-tag">Finale</span><?php endif; ?></span><span><?=$int($h['heads_caught'])?> poissons</span><span><?=$kg($h['net_weight_kg'])?> kg</span></li><?php endforeach; ?></ul>
<?php endif; ?>
<h3>Envois à la boucherie <span class="pig-ref-count"><?=count($lotShipments)?></span></h3>
<?php if(!$lotShipments): ?><p class="flow-empty">Aucun envoi pour ce lot.</p><?php else: ?>
<dl class="detail-sheet__grid"><?=$lotFact('Envoyé',$kg($sent).' kg','Pesé à la ferme')?><?=$lotFact('Reçu à la boucherie',$weighedShipments?$kg($received).' kg':'—',$weighedShipments?count($weighedShipments).' envoi(s) pesé(s) à l’arrivée':'En attente de pesée')?><?php if($weighedShipments): ?><?=$lotFact('Écart de transport',TransitWeighingService::describe(TransitWeighingService::gap($sentWeighed,$received)),'Sur les envois pesés')?><?php endif; ?></dl>
<ul class="fish-lot-lines"><?php foreach($lotShipments as $t):$gap=$t['weight_gap_kg']===null?null:['kg'=>(float)$t['weight_gap_kg'],'pct'=>(float)$t['weight_gap_pct']]; ?><li><span><strong><?=e(date('d/m/Y',strtotime($t['dispatched_at']??$t['created_at'])))?></strong> <small><?=e($t['truck_plate'])?></small></span><span><?=$kg($t['quantity_kg'])?> kg</span><span><?php if($gap!==null): ?><span class="weighing-gap-chip <?=TransitWeighingService::exceeds($gap,$t['tolerance_pct']===null?null:(float)$t['tolerance_pct'])?'is-exceeded':'is-within'?>"><?=e(TransitWeighingService::describe($gap))?></span><?php else: ?><small><?=e($shipmentLabels[$t['status']]??'—')?></small><?php endif; ?></span></li><?php endforeach; ?></ul>
<?php endif; ?>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><a class="btn-secondary" href="<?=e(base_url('livestock/fish/harvests'))?>">Pêches et envois</a>
<?php if($parcelFree($l)&&Auth::can('livestock','create',(int)$l['site_id'])): ?><button type="button" class="btn-primary" data-workspace-modal-open="fish-stock" data-fish-parcel="<?=e($l['parcel_id'])?>"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Remettre en charge la parcelle</button><?php endif; ?>
</footer>
<?php require $flowViews.'/drawer_end.php'; ?>
<?php endforeach; ?>
