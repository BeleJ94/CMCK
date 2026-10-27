<div class="fish-page compact-page" data-fish-page>
<?php require __DIR__.'/header.php'; ?>
<?php if($available):
$returnPath='livestock/fish';
$parcelsByPond=[];foreach($parcels as $p)$parcelsByPond[$p['facility_id']][]=$p;
$lotByParcel=[];foreach($activeLots as $l)$lotByParcel[$l['parcel_id']]=$l;
$closedLots=array_values(array_filter($lots,static fn($l)=>$l['status']==='completed'));
$activeParcels=count(array_filter($parcels,static fn($p)=>$p['status']==='active'));
$pendingHarvests=count(array_filter($harvests,static fn($h)=>$h['status']==='submitted'));
?>
<div class="ui-kpi-grid fish-kpis" aria-label="Synthèse piscicole">
<?php foreach([
    ['Étangs',$int(count($ponds)),'bi-water','',$int($activeParcels).' parcelle(s) active(s)',null],
    ['Parcelles en élevage',$int(count($activeLots)).' / '.$int($activeParcels),'bi-grid-3x3-gap','',$int(count($freeParcels)).' parcelle(s) libre(s)',null],
    ['Poissons en élevage',$int(array_sum(array_column($activeLots,'remaining_heads'))),'bi-droplet','','Alevins mis moins poissons pêchés',null],
    ['Pêches à valider',$int($pendingHarvests),'bi-clipboard-check',$pendingHarvests?'warning':'','Voir les pêches et envois','livestock/fish/harvests'],
] as [$label,$value,$icon,$tone,$meta,$href]):$tag=$href?'a':'section'; ?>
<<?=$tag?> class="ui-kpi<?=$tone?' ui-kpi--'.$tone:''?>"<?=$href?' href="'.e(base_url($href)).'"':''?>><span class="ui-kpi__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($label)?></span><strong class="ui-kpi__value"><?=e($value)?></strong><span class="ui-kpi__meta"><?=e($meta)?></span></<?=$tag?>>
<?php endforeach; ?>
</div>

<?php if(!$ponds): ?>
<div class="fish-empty"><i class="bi bi-water" aria-hidden="true"></i><div><h3>Aucun étang dans ce périmètre</h3><p>Créez d’abord un étang dans « Bâtiments et étangs », avec sa surface.</p><?php if(Auth::can('livestock','administer')): ?><a class="btn-primary" href="<?=e(base_url('livestock/facilities'))?>">Créer un étang</a><?php endif; ?></div></div>
<?php endif; ?>

<?php foreach($ponds as $pond): $pondParcels=$parcelsByPond[$pond['id']]??[];$canAdminPond=Auth::can('livestock','administer',(int)$pond['site_id']);$usedArea=array_sum(array_map(static fn($p)=>$p['status']==='active'?(float)$p['area_m2']:0,$pondParcels)); ?>
<section class="fish-pond" aria-labelledby="fish-pond-<?=e($pond['id'])?>">
<header class="fish-pond__head"><span class="fish-pond__icon"><i class="bi bi-water" aria-hidden="true"></i></span><div><h3 id="fish-pond-<?=e($pond['id'])?>"><?=e($pond['name'])?></h3><p><?=e($pond['site_name'])?><?=$pond['area_m2']!==null?' · '.e($int($pond['area_m2'])).' m²':''?> · <?=count($pondParcels)?> parcelle(s)<?=$pond['area_m2']!==null&&$usedArea>0?' · '.e($int($usedArea)).' m² répartis':''?></p></div>
<?php if($canAdminPond): ?><button type="button" class="btn-secondary" data-workspace-modal-open="fish-parcel-new-<?=e($pond['id'])?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter une parcelle</button><?php endif; ?></header>
<?php if(!$pondParcels): ?><p class="fish-pond__empty">Aucune parcelle. Ajoutez la première parcelle de cet étang pour y mettre des alevins en charge.</p><?php endif; ?>
<div class="fish-parcel-grid"><?php foreach($pondParcels as $parcel){$lot=$lotByParcel[$parcel['id']]??null;require __DIR__.'/parcel_card.php';} ?></div>
</section>
<?php endforeach; ?>

<section class="fish-history" aria-labelledby="fish-history-title"><header class="pig-ref-section-head"><div><h3 id="fish-history-title">Bilan des lots terminés</h3><p>Les survivants sont connus à la pêche : les poissons non pêchés lors de la pêche finale sont comptés comme morts.</p></div></header>
<?php if(!$closedLots): ?><p class="flow-empty">Aucun lot terminé pour l’instant. Le bilan apparaît après la pêche finale d’une parcelle.</p><?php else: ?>
<?php
$parcelFree=static fn($l)=>!isset($lotByParcel[$l['parcel_id']])&&in_array((int)$l['parcel_id'],array_map(static fn($p)=>(int)$p['id'],$freeParcels),true);
$survivalTone=static fn($pct)=>$pct===null?'':($pct>=80?'is-good':($pct>=60?'':'is-warning'));
?>
<div class="table-responsive"><table class="enterprise-table compact-table" aria-label="Bilan des lots de poissons terminés" data-search-placeholder="Rechercher un lot, un étang…"><thead><tr><th>Lot</th><th>Période</th><th class="num">Alevins</th><th class="num">Pêchés</th><th class="num">Survie</th><th class="num">Récolté</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($closedLots as $l):$end=$l['last_harvest_at']?new DateTimeImmutable(substr($l['last_harvest_at'],0,10)):null;$days=$end?(int)(new DateTimeImmutable($l['started_at']))->diff($end)->days:null;$survival=$l['survival_pct']===null?null:(float)$l['survival_pct']; ?>
<tr>
<td data-label="Lot" class="cell--lead"><strong class="cell-main"><?=e($l['pond_name'].' · '.$l['parcel_code'])?></strong><small class="cell-ref" title="<?=e($l['batch_number'])?>"><?=e($l['species_name'].' · '.$l['batch_number'])?></small></td>
<td data-label="Période" class="cell--wide" data-order="<?=e($l['started_at'])?>"><span class="cell-main"><?=e(date('d/m/Y',strtotime($l['started_at'])))?> → <?=$end?e($end->format('d/m/Y')):'—'?></span><small><?=$days!==null?e($days.' jour'.($days>1?'s':'').' d’élevage'):'Durée inconnue'?></small></td>
<td data-label="Alevins" class="num" data-order="<?=(int)$l['initial_heads']?>"><strong><?=$int($l['initial_heads'])?></strong><small><?=(float)$l['stocked_kg']>0?$kg($l['stocked_kg']).' kg':'mis en charge'?></small></td>
<td data-label="Pêchés" class="num" data-order="<?=(int)$l['harvested_heads']?>"><strong><?=$int($l['harvested_heads'])?></strong><small><?=$int($l['dead_heads'])?> mort<?=(int)$l['dead_heads']>1?'s':''?></small></td>
<td data-label="Survie" class="num cell--state" data-order="<?=e((string)$survival)?>"><strong class="fish-survival <?=e($survivalTone($survival))?>"><?=$survival!==null?e(number_format($survival,1,',',' ')).' %':'—'?></strong><small>des alevins</small></td>
<td data-label="Récolté" class="num" data-order="<?=e($l['harvested_kg'])?>"><strong><?=$kg($l['harvested_kg'])?></strong><small>kg<?=$l['yield_kg_m2']!==null?' · '.e(number_format($l['yield_kg_m2'],3,',',' ')).' kg/m²':''?></small></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($parcelFree($l)&&Auth::can('livestock','create',(int)$l['site_id'])): ?><button type="button" class="btn-secondary row-action-button" data-workspace-modal-open="fish-stock" data-fish-parcel="<?=e($l['parcel_id'])?>"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span>Remettre en charge</span></button><?php endif; ?>
<?php $menuId='fish-lot-menu-'.$l['id'];$menuLabel=$l['batch_number'];$menuItems=[['Consulter le bilan','bi-clipboard-data','fish-lot-detail-'.$l['id'],'',false]];require dirname(__DIR__).'/partials/row_menu.php'; ?>
</div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__.'/lot_drawers.php'; ?>
<?php endif; ?>
</section>
<?php require __DIR__.'/forms.php'; ?>
<?php endif; ?>
</div>
