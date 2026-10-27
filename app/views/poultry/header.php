<?php
$kg=static fn($v)=>number_format((float)$v,3,',',' ');$int=static fn($v)=>number_format((int)$v,0,',',' ');
$drawerKicker='VOLAILLES · FERME';$farmSpace=true;
$types=['broiler'=>'Chair','layer'=>'Ponte'];$typeNames=['broiler'=>'Poulets de chair','layer'=>'Pondeuses'];
$activeLots=$available?array_values(array_filter($lots,static fn($l)=>$l['status']==='active')):[];
$canCreate=static fn($l)=>Auth::can('livestock','create',(int)$l['site_id']);
$slaughterable=array_values(array_filter($activeLots,static fn($l)=>$l['available_heads']>0&&$canCreate($l)));
$liveShippable=array_values(array_filter($slaughterable,static fn($l)=>$l['flock_type']==='broiler'));
$recordable=array_values(array_filter($activeLots,static fn($l)=>Auth::can('livestock','update',(int)$l['site_id'])));
$mothers=$available?array_values(array_filter($lots,static fn($l)=>$l['flock_type']==='layer'&&$canCreate($l))):[];
$farms=array_values(array_filter(Auth::sites(),static fn($s)=>in_array($s['code'],['FARM-MUT','FARM-DIK'],true)&&(Auth::currentSiteId()===null||Auth::currentSiteId()===(int)$s['id'])&&Auth::can('livestock','create',(int)$s['id'])));
$canReceive=$available&&$farms&&$houses&&$suppliers;
// A lot is named after its origin: supplier, hatching of a laying lot, or former lot.
$origin=static fn($l)=>$l['origin']==='supplier'?($l['supplier_name']?:'Fournisseur'):($l['origin']==='hatch'?'Éclosion · '.($l['mother_facility']?:'pondeuses'):'Lot antérieur');
$guides=['lots'=>'Poulets de chair et pondeuses, reçus d’un fournisseur ou éclos à la ferme. Saisissez chaque jour les œufs ramassés et les oiseaux morts.','slaughters'=>'Les volailles sont abattues à la ferme, comptées et pesées, ou envoyées vivantes. La boucherie compte et pèse de nouveau à l’arrivée.'];
?>
<header class="ui-page-header poultry-header"><span class="ui-page-header__icon"><i class="bi bi-egg" aria-hidden="true"></i></span><div class="ui-page-header__content"><p class="section-label">ÉLEVAGE · VOLAILLES</p><h2><?=$section==='lots'?'Lots et suivi':'Abattages et envois'?></h2><p><?=e($guides[$section])?></p></div><div class="ui-page-header__actions">
<?php if($available&&$section==='lots'): ?><?php if($mothers&&$houses): ?><button type="button" class="btn-secondary" data-workspace-modal-open="poultry-hatch"><i class="bi bi-egg-fried" aria-hidden="true"></i> Enregistrer une éclosion</button><?php endif; ?><?php if($recordable): ?><button type="button" class="btn-secondary" data-workspace-modal-open="poultry-record"><i class="bi bi-journal-check" aria-hidden="true"></i> Saisie du jour</button><?php endif; ?><?php if($canReceive): ?><button type="button" class="btn-primary" data-workspace-modal-open="poultry-receive"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Réceptionner des volailles</button><?php endif; ?>
<?php elseif($available): ?><?php if($liveShippable): ?><button type="button" class="btn-secondary" data-workspace-modal-open="poultry-live-shipment"><i class="bi bi-truck" aria-hidden="true"></i> Envoyer des poulets vivants</button><?php endif; ?><?php if($slaughterable): ?><button type="button" class="btn-primary" data-workspace-modal-open="poultry-slaughter"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Enregistrer un abattage</button><?php endif; ?><?php endif; ?>
</div></header>
<?php if($success): ?><p class="app-alert app-alert-success" role="status"><?=e($success)?></p><?php endif; ?><?php if($error): ?><p class="app-alert app-alert-error" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if(!$available): ?><p class="app-alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> Le suivi des volailles n’est pas encore activé : appliquez la migration <code>044_add_poultry_flow.sql</code>.</p><?php endif; ?>
<?php if($available&&$section==='lots'&&!$canReceive&&$farms): ?><p class="app-alert alert-info" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> <?=!$suppliers?'Ajoutez d’abord un fournisseur actif pour réceptionner des volailles.':'Créez d’abord un poulailler actif dans « Bâtiments et étangs ».'?></p><?php endif; ?>
