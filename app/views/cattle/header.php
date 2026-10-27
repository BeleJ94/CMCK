<?php
$kg=static fn($v)=>number_format((float)$v,3,',',' ');$int=static fn($v)=>number_format((int)$v,0,',',' ');
$drawerKicker='BŒUFS · FERME';$farmSpace=true;
$activeLots=$available?array_values(array_filter($lots,static fn($l)=>$l['status']==='active')):[];
$slaughterable=array_values(array_filter($activeLots,static fn($l)=>$l['available_heads']>0&&Auth::can('livestock','create',(int)$l['site_id'])));
$farms=array_values(array_filter(Auth::sites(),static fn($s)=>in_array($s['code'],['FARM-MUT','FARM-DIK'],true)&&(Auth::currentSiteId()===null||Auth::currentSiteId()===(int)$s['id'])&&Auth::can('livestock','create',(int)$s['id'])));
$canReceive=$available&&$farms&&$facilities&&$suppliers;
$guides=['lots'=>'Chaque réception de bovins d’un fournisseur crée un lot dans un bâtiment de la ferme, jusqu’à l’abattage de tous ses animaux.','slaughters'=>'Les bovins sont abattus à la ferme, les carcasses pesées puis envoyées à la boucherie, où elles sont pesées de nouveau.'];
?>
<header class="ui-page-header cattle-header"><span class="ui-page-header__icon"><i class="bi bi-truck-flatbed" aria-hidden="true"></i></span><div class="ui-page-header__content"><p class="section-label">ÉLEVAGE · BŒUFS</p><h2><?=$section==='lots'?'Réceptions et lots':'Abattages et envois'?></h2><p><?=e($guides[$section])?></p></div><div class="ui-page-header__actions">
<?php if($available&&$section==='lots'): ?><a class="btn-secondary" href="<?=e(base_url('suppliers'))?>"><i class="bi bi-building-check" aria-hidden="true"></i> Fournisseurs</a><?php if($canReceive): ?><button type="button" class="btn-primary" data-workspace-modal-open="cattle-receive"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Réceptionner des bœufs</button><?php endif; ?>
<?php elseif($available&&$slaughterable): ?><button type="button" class="btn-primary" data-workspace-modal-open="cattle-slaughter"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Enregistrer un abattage</button><?php endif; ?>
</div></header>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?><?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if(!$available): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Le suivi des bovins n’est pas encore activé : appliquez la migration <code>043_add_cattle_flow.sql</code>.</p><?php endif; ?>
<?php if($available&&$section==='lots'&&!$canReceive&&$farms): ?><p class="app-alert alert-info" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> <?=!$suppliers?'Ajoutez d’abord un fournisseur actif pour réceptionner des bœufs.':'Créez d’abord un bâtiment d’élevage actif dans « Bâtiments et étangs ».'?></p><?php endif; ?>
