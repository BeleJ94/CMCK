<?php
$extra = [
    'inputs' => ['Intrants imputés','Quantités prévues et consommées par parcelle.','inputModal','Imputer un intrant',['Site','Campagne / parcelle','Intrant','Prévu','Réel','Coût total','Date']],
    'works' => ['Travaux agricoles','Interventions, responsables et coûts engagés.','workModal','Enregistrer un travail',['Site','Campagne / parcelle','Travail','Date','Responsable','Coût total','Statut']],
    'stocks' => ['Stocks agricoles','Quantités issues exclusivement des récoltes validées.',null,null,['Site','Récolte','Produit / variété','Physique','Réservé','Disponible','Statut']],
    'workers' => ['Main-d’œuvre','Ressources permanentes et journalières par ferme.','workerModal','Ajouter un travailleur',['Site','Matricule','Nom','Type','Coût journalier','Statut']],
    'equipment' => ['Matériels agricoles','Équipements et coûts horaires par ferme.','equipmentModal','Ajouter un matériel',['Site','Code','Désignation','Coût horaire','Statut']],
];
if(isset($extra[$section])):$config=$extra[$section];
?>
<section class="table-panel agriculture-extra-directory" id="agriExtraDirectory">
 <div class="panel-heading"><span class="panel-icon"><i class="bi bi-list-check"></i></span><div><h3><?=e($config[0])?></h3><p><?=e($config[1])?></p></div><?php if($config[2]):?><button class="panel-action" type="button" data-workspace-modal-open="<?=e($config[2])?>"><i class="bi bi-plus-lg"></i> <?=e($config[3])?></button><?php endif;?></div>
 <div class="table-responsive"><table data-search-placeholder="Rechercher dans ce répertoire"><thead><tr><?php foreach($config[4] as$heading):?><th><?=e($heading)?></th><?php endforeach;?></tr></thead><tbody>
 <?php if($section==='inputs'):foreach($inputAllocations as$x):?><tr><td><?=e($x['site_code'])?></td><td><?=e($x['campaign_code'].' / '.$x['plot_code'])?></td><td><strong><?=e($x['input_name'])?></strong><small class="table-subline"><?=e($x['input_type'])?></small></td><td><?=e(number_format($x['planned_quantity'],3,',',' ').' '.$x['unit'])?></td><td><?=e(number_format($x['actual_quantity'],3,',',' ').' '.$x['unit'])?></td><td><?=e(number_format($x['total_cost'],2,',',' '))?></td><td><?=e($x['applied_at']?date('d/m/Y',strtotime($x['applied_at'])):'—')?></td></tr><?php endforeach;
 elseif($section==='works'):foreach($works as$x):?><tr><td><?=e($x['site_code'])?></td><td><?=e($x['campaign_code'].' / '.$x['plot_code'])?></td><td><strong><?=e($x['work_type'])?></strong><small class="table-subline"><?=e($x['description']?:'—')?></small></td><td><?=e(date('d/m/Y H:i',strtotime($x['worked_at'])))?></td><td><?=e($x['creator_name'])?></td><td><?=e(number_format($x['total_cost'],2,',',' '))?></td><td><?=e($x['status'])?></td></tr><?php endforeach;
 elseif($section==='stocks'):foreach($stocks as$x):?><tr><td><?=e($x['site_code'])?></td><td><strong><?=e($x['harvest_number'])?></strong></td><td><?=e($x['product_name'].' / '.$x['variety_name'])?></td><td><?=e(number_format($x['physical_quantity_kg'],3,',',' '))?> kg</td><td><?=e(number_format($x['reserved_quantity_kg'],3,',',' '))?> kg</td><td><strong><?=e(number_format($x['available_quantity_kg'],3,',',' '))?> kg</strong></td><td><?=e($x['status'])?></td></tr><?php endforeach;
 elseif($section==='workers'):foreach($workers as$x):?><tr><td><?=e($x['site_code'])?></td><td><strong><?=e($x['worker_number'])?></strong></td><td><?=e($x['name'])?></td><td><?=e($x['worker_type']==='daily'?'Journalier':'Permanent')?></td><td><?=e(number_format($x['daily_rate'],2,',',' '))?></td><td><?=e($x['status'])?></td></tr><?php endforeach;
 elseif($section==='equipment'):foreach($equipmentList as$x):?><tr><td><?=e($x['site_code'])?></td><td><strong><?=e($x['code'])?></strong></td><td><?=e($x['name'])?></td><td><?=e(number_format($x['hourly_cost'],2,',',' '))?></td><td><?=e($x['status'])?></td></tr><?php endforeach;endif;?>
 </tbody></table></div>
</section>
<?php endif;?>
