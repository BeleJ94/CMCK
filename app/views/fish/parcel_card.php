<?php
$parcelActive=$parcel['status']==='active';
[$stateLabel,$stateTone]=!$parcelActive?['Désactivée','neutral']:(!$lot?['Libre','success']:((int)$lot['pending_final']?['Pêche finale à valider','warning']:['En élevage','progress']));
?>
<article class="fish-parcel<?=$lot?' is-occupied':''?><?=$parcelActive?'':' is-inactive'?>" data-fish-parcel="<?=e($parcel['id'])?>">
<header><span class="fish-parcel__code"><?=e($parcel['code'])?></span><div><h4><?=e($parcel['name'])?></h4><small><?=$parcel['area_m2']!==null?e($int($parcel['area_m2'])).' m²':'Surface non renseignée'?></small></div><span class="ui-status ui-status--<?=e($stateTone)?>"><?=e($stateLabel)?></span></header>
<?php if($lot): $months=intdiv($lot['age_days'],30); ?>
<p class="fish-parcel__lot"><i class="bi bi-tag" aria-hidden="true"></i> <?=e($lot['batch_number'])?> · <?=e($lot['species_name'])?></p>
<dl class="fish-parcel__facts">
<div><dt>Mise en charge</dt><dd><?=e(date('d/m/Y',strtotime($lot['started_at'])))?><small><?=e($lot['age_days'].' jour'.($lot['age_days']>1?'s':'').($months?' (≈ '.$months.' mois)':''))?></small></dd></div>
<div><dt>Alevins mis</dt><dd><?=$int($lot['initial_heads'])?><?php if((float)$lot['stocked_kg']>0): ?><small><?=$kg($lot['stocked_kg'])?> kg</small><?php endif; ?></dd></div>
<div><dt>Déjà pêchés</dt><dd><?=$int($lot['harvested_heads'])?><small><?=$kg($lot['harvested_kg'])?> kg</small></dd></div>
<div><dt>Restants</dt><dd><?=$int($lot['remaining_heads'])?><small><?=(int)$lot['pending_harvests']?(int)$lot['pending_harvests'].' pêche(s) à valider':'Estimation avant comptage'?></small></dd></div>
</dl>
<?php elseif($parcelActive): ?><p class="fish-parcel__free"><i class="bi bi-droplet" aria-hidden="true"></i> Parcelle prête à recevoir un lot d’alevins.</p>
<?php else: ?><p class="fish-parcel__free">Parcelle désactivée : elle ne reçoit plus de lot.</p><?php endif; ?>
<footer>
<?php if($lot&&!(int)$lot['pending_final']&&Auth::can('livestock','create',(int)$lot['site_id'])): ?><button type="button" class="btn-primary" data-workspace-modal-open="fish-harvest" data-fish-lot="<?=e($lot['id'])?>"><i class="bi bi-basket" aria-hidden="true"></i> Pêcher</button><?php endif; ?>
<?php if(!$lot&&$parcelActive&&Auth::can('livestock','create',(int)$pond['site_id'])): ?><button type="button" class="btn-primary" data-workspace-modal-open="fish-stock" data-fish-parcel="<?=e($parcel['id'])?>"><i class="bi bi-plus-circle" aria-hidden="true"></i> Mettre en charge</button><?php endif; ?>
<?php if($lot&&((int)$lot['pending_harvests']||(int)$lot['harvested_heads'])): ?><a class="btn-ghost" href="<?=e(base_url('livestock/fish/harvests'))?>">Pêches et envois <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
<?php if($canAdminPond): ?><button type="button" class="btn-ghost" data-workspace-modal-open="fish-parcel-edit-<?=e($parcel['id'])?>" aria-label="Modifier la parcelle <?=e($parcel['code'])?>"><i class="bi bi-pencil" aria-hidden="true"></i> Modifier</button><?php endif; ?>
</footer>
</article>
