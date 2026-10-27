<?php
$kg=static fn($v)=>number_format((float)$v,3,',',' ');$int=static fn($v)=>number_format((int)$v,0,',',' ');
$drawerKicker='POISSONS · FERME';$farmSpace=true;
$activeLots=$available?array_values(array_filter($lots,static fn($l)=>$l['status']==='active')):[];
$freeParcels=[];
if($available){$occupied=array_column($activeLots,'parcel_id');foreach($parcels as $p)if($p['status']==='active'&&!in_array($p['id'],$occupied))$freeParcels[]=$p;}
$pondNames=[];if($available)foreach($ponds as $pond)$pondNames[$pond['id']]=$pond['name'];
$guides=['ponds'=>'Chaque étang comporte une ou plusieurs parcelles. Une parcelle reçoit un lot d’alevins à une date donnée, jusqu’à sa pêche finale.','harvests'=>'Les poissons survivants sont comptés et pesés à la pêche, puis envoyés à la boucherie où ils sont pesés de nouveau.'];
?>
<header class="ui-page-header fish-header"><span class="ui-page-header__icon"><i class="bi bi-water" aria-hidden="true"></i></span><div class="ui-page-header__content"><p class="section-label">ÉLEVAGE · POISSONS</p><h2><?=$section==='ponds'?'Étangs et lots':'Pêches et envois'?></h2><p><?=e($guides[$section])?></p></div><div class="ui-page-header__actions">
<?php if($available&&$section==='ponds'): ?><?php if(Auth::can('livestock','administer')): ?><a class="btn-secondary" href="<?=e(base_url('livestock/facilities'))?>"><i class="bi bi-building" aria-hidden="true"></i> Gérer les étangs</a><?php endif; ?><?php if($freeParcels&&Auth::can('livestock','create')): ?><button type="button" class="btn-primary" data-workspace-modal-open="fish-stock"><i class="bi bi-plus-circle" aria-hidden="true"></i> Mettre en charge</button><?php endif; ?>
<?php elseif($available): ?><?php if($activeLots&&Auth::can('livestock','create')): ?><button type="button" class="btn-primary" data-workspace-modal-open="fish-harvest"><i class="bi bi-basket" aria-hidden="true"></i> Enregistrer une pêche</button><?php endif; ?><?php endif; ?>
</div></header>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?><?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if(!$available): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Le suivi piscicole par étang et parcelle n’est pas encore activé : appliquez la migration <code>042_add_fish_ponds_and_transit_weighing.sql</code>.</p><?php endif; ?>
