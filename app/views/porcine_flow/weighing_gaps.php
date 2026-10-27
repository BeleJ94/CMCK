<?php
$f=$report['filters'];$t=$report['totals'];$kg=static fn($v)=>number_format((float)$v,3,',',' ');$pct=static fn($v)=>number_format((float)$v,2,',',' ');
$exportUrl=base_url('butchery/weighing-gaps?'.http_build_query(array_merge($f,['export'=>'xlsx'])));
$states=['within'=>['Dans la tolérance','success'],'exceeded'=>['Hors tolérance','warning'],'unweighed'=>['Non pesé à l’arrivée','neutral']];
$returnPath='butchery/weighing-gaps';
?>
<div class="weighing-gaps-page">
<header class="ui-page-header"><div class="ui-page-header__content"><p class="section-label">BOUCHERIE · PESÉES</p><h2>Écarts de pesée ferme / boucherie</h2><p>Chaque envoi est pesé au départ de la ferme puis à l’arrivée. L’écart mesure les pertes de transport (sang, égouttage, glace).</p></div><div class="ui-page-header__actions"><a class="btn-secondary" href="<?=e(base_url('butchery/receipts'))?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Réceptions</a></div></header>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?><?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>

<section class="pig-ref-panel weighing-tolerances" aria-labelledby="weighing-tolerances-title">
<header class="pig-ref-section-head"><div><h3 id="weighing-tolerances-title">Tolérances par espèce</h3><p>Au-delà de la tolérance, le motif est obligatoire à la pesée et l’écart doit être validé lors du contrôle. Sans tolérance, tout écart doit être justifié.</p></div></header>
<div class="weighing-tolerance-list">
<?php foreach($settings['species'] as $sp): $configured=$sp['tolerance_pct']!==null; ?>
<article class="weighing-tolerance<?=$configured?'':' is-missing'?>">
<div class="weighing-tolerance__head"><span class="weighing-tolerance__icon"><i class="bi <?=$sp['category']==='fish'?'bi-water':'bi-heart-pulse'?>" aria-hidden="true"></i></span><div><h4><?=e($sp['name'])?></h4><p><?=$configured?'Tolérance '.e($pct($sp['tolerance_pct'])).' % · validation : '.e($sp['role_name']?:'tout validateur boucherie'):'Non paramétrée : tout écart doit être justifié'?></p><?php if($sp['editor_name']): ?><small>Modifié par <?=e($sp['editor_name'])?> le <?=e(date('d/m/Y',strtotime($sp['updated_at'])))?></small><?php endif; ?></div></div>
<?php if($canConfigure): ?>
<form method="post" action="<?=e(base_url('butchery/weighing-gaps/tolerances'))?>" class="weighing-tolerance__form" data-flow-form data-confirm="Enregistrer la tolérance de l’espèce <?=e($sp['name'])?> ? Elle s’appliquera aux prochaines pesées d’arrivée."><?=csrf_field()?><input type="hidden" name="_flow_return" value="<?=e($returnPath)?>"><input type="hidden" name="species_id" value="<?=e($sp['id'])?>">
<p class="app-alert app-alert-error" data-feed-error role="alert" hidden></p>
<label><span>Écart toléré</span><span class="pig-ref-unit"><input type="number" name="tolerance_pct" min="0" max="50" step="0.01" inputmode="decimal" required value="<?=$configured?e((float)$sp['tolerance_pct']):''?>" placeholder="Ex. 3"><span aria-hidden="true">%</span></span></label>
<label><span>Validation d’un dépassement</span><select name="approver_role_id"><option value="">Tout validateur boucherie</option><?php foreach($settings['roles'] as $role): ?><option value="<?=e($role['id'])?>"<?=(int)$sp['approver_role_id']===(int)$role['id']?' selected':''?>><?=e($role['name'])?></option><?php endforeach; ?></select></label>
<button type="submit" class="btn-secondary"><i class="bi bi-check2" aria-hidden="true"></i> Enregistrer</button>
</form>
<?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php if(!$canConfigure): ?><p class="receipt-control-help">Le paramétrage est réservé aux administrateurs de la boucherie.</p><?php endif; ?>
</section>

<form method="get" class="ui-filterbar enterprise-form pos-report-filters" action="<?=e(base_url('butchery/weighing-gaps'))?>" aria-label="Filtres des écarts de pesée">
<?php foreach(['from'=>'Du','to'=>'Au'] as $key=>$label): ?><label><span><?=e($label)?></span><input type="date" name="<?=e($key)?>" value="<?=e($f[$key])?>" required></label><?php endforeach; ?>
<label><span>Espèce</span><select name="species"><option value="">Toutes les espèces</option><?php foreach($settings['species'] as $sp): ?><option value="<?=e($sp['id'])?>"<?=$f['species']===(string)$sp['id']?' selected':''?>><?=e($sp['name'])?></option><?php endforeach; ?></select></label>
<label><span>État</span><select name="status"><option value="">Tous les états</option><?php foreach($states as $value=>[$label]): ?><option value="<?=e($value)?>"<?=$f['status']===$value?' selected':''?>><?=e($label)?></option><?php endforeach; ?></select></label>
<button class="btn-primary" type="submit">Appliquer les filtres</button><a class="btn-secondary" href="<?=e(base_url('butchery/weighing-gaps'))?>">Réinitialiser</a>
</form>

<div class="ui-kpi-grid weighing-kpis" aria-label="Synthèse des écarts">
<?php foreach([
    ['Réceptions pesées',$t['weighed'],'bi-speedometer2','',$t['unweighed']?$t['unweighed'].' réception(s) antérieure(s) non pesée(s)':'Toutes pesées à l’arrivée'],
    ['Pesé à la ferme',$kg($t['sent']).' kg','bi-house','','Total envoyé'],
    ['Pesé à la boucherie',$kg($t['received']).' kg','bi-shop','','Total reçu'],
    ['Écart de transport',$kg($t['gap']).' kg','bi-graph-down-arrow',$t['gap']>0?'warning':'',$pct($t['gap_pct']).' % du poids envoyé'],
    ['Hors tolérance',$t['exceeded'],'bi-exclamation-triangle',$t['exceeded']?'danger':'success',$t['exceeded']?'Écarts justifiés et validés':'Aucun dépassement'],
] as [$label,$value,$icon,$tone,$meta]): ?>
<section class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e((string)$value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></section>
<?php endforeach; ?>
</div>

<div class="pos-report-export"><div><strong><?=count($report['rows'])?> réception(s)</strong><p>Export selon les filtres appliqués, toutes pages confondues.</p></div><div><a class="btn-secondary" data-ajax="false" href="<?=e($exportUrl)?>"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Exporter Excel</a></div></div>

<?php if($report['bySpecies']): ?>
<h3>Synthèse par espèce</h3>
<div class="table-responsive"><table class="enterprise-table" data-searchable="false" aria-label="Écarts par espèce"><thead><tr><th>Espèce</th><th>Réceptions</th><th>Envoyé (kg)</th><th>Reçu (kg)</th><th>Écart (kg)</th><th>Écart (%)</th><th>Hors tolérance</th></tr></thead><tbody>
<?php foreach($report['bySpecies'] as $line): ?><tr><td><?=e($line['species'])?></td><td data-order="<?=$line['receipts']?>"><?=(int)$line['receipts']?></td><td data-order="<?=$line['sent']?>"><?=$kg($line['sent'])?></td><td data-order="<?=$line['received']?>"><?=$kg($line['received'])?></td><td data-order="<?=$line['gap']?>"><?=$kg($line['gap'])?></td><td data-order="<?=$line['gap_pct']?>"><?=$pct($line['gap_pct'])?> %</td><td data-order="<?=$line['exceeded']?>"><?=(int)$line['exceeded']?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<h3>Détail des réceptions</h3>
<?php if(!$report['rows']): ?><p class="flow-empty" role="status">Aucune réception pour ces filtres.</p><?php else: ?>
<div class="table-responsive"><table class="enterprise-table weighing-gap-table" aria-label="Détail des écarts de pesée" data-search-placeholder="Rechercher une réception, un lot, un chauffeur…"><thead><tr><th>Date</th><th>Réception</th><th>Espèce / lot</th><th>Ferme · BTR</th><th>Envoyé (kg)</th><th>Reçu (kg)</th><th>Écart</th><th>État</th><th>Motif</th><th>Pesé par</th><th>Écart validé par</th></tr></thead><tbody>
<?php foreach($report['rows'] as $r):[$stateLabel,$stateTone]=$states[$r['state']]; ?>
<tr><td data-order="<?=e($r['received_at'])?>"><?=e(date('d/m/Y H:i',strtotime($r['received_at'])))?></td><td><?=e($r['receipt_number'])?></td><td><?=e($r['species_name'])?><small><?=e($r['batch_number'])?></small></td><td><?=e($r['source_name'])?><small><?=e($r['transfer_number'].' · '.$r['truck_plate'])?></small></td>
<td data-order="<?=e($r['sent_weight_kg']??$r['quantity_kg'])?>"><?=$kg($r['sent_weight_kg']??$r['quantity_kg'])?></td>
<td data-order="<?=$r['weighed']?e($r['received_weight_kg']):''?>"><?=$r['weighed']?$kg($r['received_weight_kg']):'—'?><?php if($r['weighed']&&(float)$r['received_tare_kg']>0): ?><small>Brut <?=$kg($r['received_gross_kg'])?> − tare <?=$kg($r['received_tare_kg'])?><?=$r['tare_note']?' ('.e($r['tare_note']).')':''?></small><?php endif; ?></td>
<td data-order="<?=$r['weighed']?e($r['weight_gap_pct']):''?>"><?=$r['weighed']?e(TransitWeighingService::describe(['kg'=>(float)$r['weight_gap_kg'],'pct'=>(float)$r['weight_gap_pct']])):'—'?><?php if($r['tolerance_pct']!==null): ?><small>Tolérance <?=e($pct($r['tolerance_pct']))?> %</small><?php endif; ?></td>
<td><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span></td><td><?=e($r['gap_reason']?:'—')?></td><td><?=e($r['weigher_name']?:'—')?></td><td><?=e($r['approver_name']?:'—')?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<p class="butchery-home-note">Édité le <?=e($report['issuedAt'])?>. Les réceptions antérieures à la pesée d’arrivée apparaissent comme non pesées : leur stock était parti du poids de la ferme.</p>
</div>
