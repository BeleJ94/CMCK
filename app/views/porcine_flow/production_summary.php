<?php
$productionStatuses=['in_progress'=>'En cours','results_submitted'=>'À valider','validated'=>'Validée','cancelled'=>'Annulée'];
$productionPending=array_filter($orders,static fn($o)=>$o['status']==='results_submitted');
$productionValidated=array_filter($orders,static fn($o)=>$o['status']==='validated');
$productionKpis=[
 'pending'=>['title'=>'Transformations à valider','value'=>count($productionPending),'icon'=>'bi-clipboard-check','meta'=>'Voir les validations','tone'=>'warning','orders'=>$productionPending,'note'=>'Transformations dont les résultats sont enregistrés et restent à valider. Les produits ne sont pas encore disponibles.'],
 'validated'=>['title'=>'Transformations validées','value'=>count($productionValidated),'icon'=>'bi-check2-circle','meta'=>'Voir les transformations','tone'=>'success','orders'=>$productionValidated,'note'=>'Transformations validées : les matières ont été consommées et les produits créés en stock.'],
 'input'=>['title'=>'Matière transformée','value'=>$kg(array_sum(array_column($productionValidated,'actual_input_kg'))).' kg','icon'=>'bi-box-seam','meta'=>'Transformations validées uniquement','tone'=>'','orders'=>$productionValidated,'note'=>'Poids total de matière consommée par les transformations validées. Les transformations en attente et annulées sont exclues.'],
 'output'=>['title'=>'Produits obtenus','value'=>$kg(array_sum(array_map(static fn($o)=>(float)$o['output_kg']+(float)$o['co_product_kg'],$productionValidated))).' kg','icon'=>'bi-gear','meta'=>'Transformations validées uniquement','tone'=>'','orders'=>$productionValidated,'note'=>'Poids total des produits et coproduits issus des transformations validées, avant les mouvements et ventes ultérieurs. Ce chiffre représente la production, pas le stock restant.'],
];
?>
<div class="flow-summary ui-kpi-grid" aria-label="Suivi des transformations">
<?php foreach($productionKpis as$key=>$kpi):?><button type="button" class="ui-kpi<?=$kpi['tone']?' ui-kpi--'.e($kpi['tone']):''?>" data-workspace-modal-open="production-kpi-<?=e($key)?>" aria-haspopup="dialog" aria-controls="production-kpi-<?=e($key)?>"><span class="ui-kpi__icon"><i class="bi <?=e($kpi['icon'])?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($kpi['title'])?></span><strong class="ui-kpi__value"><?=e($kpi['value'])?></strong><span class="ui-kpi__meta"><?=e($kpi['meta'])?> <i class="bi bi-arrow-right" aria-hidden="true"></i></span></button><?php endforeach;?>
</div>
