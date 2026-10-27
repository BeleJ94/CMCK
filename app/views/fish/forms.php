<?php
$flowViews=dirname(__DIR__).'/porcine_flow';
$harvestable=array_values(array_filter($activeLots,static fn($l)=>!(int)$l['pending_final']&&Auth::can('livestock','create',(int)$l['site_id'])));
?>
<?php if($harvestable): $drawerId='fish-harvest';$drawerTitle='Enregistrer une pêche';require $flowViews.'/drawer_start.php';$action='livestock/fish/harvests';$confirm='Enregistrer cette pêche pour validation ?';require $flowViews.'/form_start.php'; ?>
<div class="form-grid" data-fish-harvest-form data-shelf-life="<?=(int)$shelfLifeDays?>">
<label class="form-wide"><span>Lot pêché *</span><select name="batch_id" required><option value="">Choisir le lot</option><?php foreach($harvestable as $l): ?><option value="<?=e($l['id'])?>" data-remaining="<?=(int)$l['remaining_heads']?>" data-initial="<?=(int)$l['initial_heads']?>" data-harvested="<?=(int)$l['harvested_heads']?>" data-started="<?=e($l['started_at'])?>"><?=e($l['pond_name'].' · '.$l['parcel_code'].' — '.$l['batch_number'].' · '.$int($l['remaining_heads']).' restants')?></option><?php endforeach; ?></select></label>
<label><span>Poissons pêchés (comptés) *</span><input name="heads" type="number" min="1" step="1" inputmode="numeric" required><small>Les poissons comptés à la pêche sont les survivants.</small></label>
<label><span>Date et heure de la pêche *</span><input name="caught_at" type="datetime-local" value="<?=e(date('Y-m-d\TH:i'))?>" max="<?=e(date('Y-m-d\TH:i',strtotime('+5 minutes')))?>" required></label>
<?php $weighingId='fish-harvest-weight';$weighingLabel='Poids pêché à la ferme';require $flowViews.'/weighing_fields.php'; ?>
<label><span>DLC du poisson frais *</span><input name="expiry_date" type="date" min="<?=e(date('Y-m-d'))?>" value="<?=e(date('Y-m-d',strtotime('+'.(int)$shelfLifeDays.' days')))?>" required><small>Proposée à <?=(int)$shelfLifeDays?> jours après la pêche.</small></label>
<label class="form-wide fish-final-toggle"><input type="checkbox" name="final_harvest" value="1"><span><strong><i class="bi bi-flag" aria-hidden="true"></i> Pêche finale (vidange de la parcelle)</strong><small>Le lot est clôturé : les poissons non pêchés sont comptés comme morts et la parcelle redevient libre.</small></span></label>
<p class="livestock-guide form-wide" data-fish-harvest-note aria-live="polite">Choisissez le lot pour voir les poissons restants.</p>
</div>
<?php $submitLabel='Enregistrer pour validation';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>

<?php if($section==='ponds'): ?>
<?php if($freeParcels&&Auth::can('livestock','create')): $drawerId='fish-stock';$drawerTitle='Mettre des alevins en charge';require $flowViews.'/drawer_start.php';$action='livestock/fish/stockings';$confirm='Enregistrer cette mise en charge ? Un nouveau lot commence dans la parcelle.';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Un lot correspond aux alevins mis dans une parcelle à une date. La parcelle reste occupée jusqu’à la pêche finale.</p>
<div class="form-grid">
<label class="form-wide"><span>Parcelle *</span><select name="parcel_id" required><option value="">Choisir une parcelle libre</option><?php $byPond=[];foreach($freeParcels as $p)$byPond[$p['facility_id']][]=$p;foreach($byPond as $pondId=>$list): ?><optgroup label="<?=e($pondNames[$pondId]??'Étang')?>"><?php foreach($list as $p): ?><option value="<?=e($p['id'])?>"><?=e($p['code'].' · '.$p['name'].($p['area_m2']!==null?' · '.$int($p['area_m2']).' m²':''))?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
<label><span>Espèce *</span><select name="species_id" required><?php foreach($species as $sp): ?><option value="<?=e($sp['id'])?>"><?=e($sp['name'])?></option><?php endforeach; ?></select></label>
<label><span>Date de mise en charge *</span><input name="stocked_on" type="date" max="<?=e(date('Y-m-d'))?>" value="<?=e(date('Y-m-d'))?>" required></label>
<label><span>Nombre d’alevins *</span><input name="heads" type="number" min="1" step="1" inputmode="numeric" required></label>
<label><span>Poids total des alevins (kg)</span><input name="stocked_weight_kg" type="number" min="0.001" step="0.001" inputmode="decimal"><small>Facultatif.</small></label>
</div>
<?php $submitLabel='Mettre en charge';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>

<?php foreach($ponds as $pond): if(!Auth::can('livestock','administer',(int)$pond['site_id']))continue;$pondParcels=$parcelsByPond[$pond['id']]??[];$free=$pond['area_m2']===null?null:(float)$pond['area_m2']-array_sum(array_map(static fn($p)=>$p['status']==='active'?(float)$p['area_m2']:0,$pondParcels)); ?>
<?php $drawerId='fish-parcel-new-'.$pond['id'];$drawerTitle='Ajouter une parcelle · '.$pond['name'];require $flowViews.'/drawer_start.php';$action='livestock/fish/parcels';$confirm='Ajouter cette parcelle à l’étang '.$pond['name'].' ?';require $flowViews.'/form_start.php'; ?>
<input type="hidden" name="facility_id" value="<?=e($pond['id'])?>">
<p class="livestock-guide">Le code est attribué automatiquement (P<?=count($pondParcels)+1?>, P<?=count($pondParcels)+2?>…). <?=$free!==null?'Surface encore disponible : '.e($int(max(0,$free))).' m².':''?></p>
<div class="form-grid"><label><span>Nom</span><input name="name" maxlength="120" placeholder="Parcelle <?=count($pondParcels)+1?>"><small>Laissé vide : « Parcelle <?=count($pondParcels)+1?> ».</small></label><label><span>Surface (m²)</span><input name="area_m2" type="number" min="0.001" step="0.001" inputmode="decimal"<?=$free!==null&&!$pondParcels?' value="'.e((float)$pond['area_m2']).'"':''?>><small>Sert au rendement en kg/m².</small></label></div>
<?php $submitLabel='Ajouter la parcelle';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php'; ?>
<?php foreach($pondParcels as $p): $drawerId='fish-parcel-edit-'.$p['id'];$drawerTitle='Modifier la parcelle '.$p['code'];require $flowViews.'/drawer_start.php';$action='livestock/fish/parcels/'.$p['id'].'/update';$confirm='Enregistrer les modifications de la parcelle '.$p['code'].' ?';require $flowViews.'/form_start.php'; ?>
<div class="form-grid"><label><span>Nom *</span><input name="name" maxlength="120" required value="<?=e($p['name'])?>"></label><label><span>Surface (m²)</span><input name="area_m2" type="number" min="0.001" step="0.001" inputmode="decimal" value="<?=$p['area_m2']!==null?e((float)$p['area_m2']):''?>"></label><label><span>Statut</span><select name="status"><option value="active"<?=$p['status']==='active'?' selected':''?>>Active</option><option value="inactive"<?=$p['status']!=='active'?' selected':''?>>Désactivée</option></select><small>Une parcelle en élevage ne peut pas être désactivée.</small></label></div>
<?php $submitLabel='Enregistrer';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>

<?php if($section==='harvests'): $shippable=array_values(array_filter($harvests,static fn($h)=>$h['status']==='validated'&&$h['weight_stock_id']&&in_array($h['stock_status'],['available','reserved'],true)&&(float)$h['stock_kg']>(float)$h['stock_reserved_kg']&&$h['expiry_date']>=date('Y-m-d')&&Auth::can('livestock','create',(int)$h['site_id']))); ?>
<?php if($shippable): $drawerId='fish-shipment';$drawerTitle='Préparer un envoi de poisson';require $flowViews.'/drawer_start.php';$action='livestock/fish/shipments';$confirm='Préparer cet envoi pour approbation ?';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Le poids envoyé est celui pesé à la ferme. La boucherie pèsera de nouveau le poisson à son arrivée.</p>
<div class="form-grid" data-fish-shipment-form><label class="form-wide"><span>Pêche à envoyer *</span><select name="weight_stock_id" required><option value="">Choisir le poisson pesé</option><?php foreach($shippable as $h):$availableKg=round((float)$h['stock_kg']-(float)$h['stock_reserved_kg'],3); ?><option value="<?=e($h['weight_stock_id'])?>" data-max="<?=e($availableKg)?>"><?=e($h['pond_name'].' · '.$h['parcel_code'].' — '.$h['catch_number'].' · '.$kg($availableKg).' kg disponibles · DLC '.date('d/m/Y',strtotime($h['expiry_date'])))?></option><?php endforeach; ?></select></label>
<label><span>Quantité à envoyer (kg) *</span><input name="quantity_kg" type="number" min="0.001" step="0.001" inputmode="decimal" required></label><label><span>Immatriculation *</span><input name="truck_plate" maxlength="40" required></label><label><span>Chauffeur *</span><input name="driver_name" maxlength="150" required></label></div>
<?php $submitLabel='Préparer l’envoi';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>
<?php endif; ?>
<div class="entity-modal-backdrop workspace-modal-backdrop" data-workspace-modal-close></div>
