<?php
$inventory=[];$stockKpis=[[],[],[]];$today=date('Y-m-d');$soon=date('Y-m-d',strtotime('+3 days'));
$stockLabels=['available'=>'Disponible','reserved'=>'Réservé','depleted'=>'Épuisé','expired'=>'DLC dépassée','quarantine'=>'En quarantaine','rejected'=>'Refusé','cancelled'=>'Annulé','in_transit'=>'En transit'];
$kg=static function($value){return number_format((float)$value,3,',',' ').' kg';};
foreach(['raw'=>$raw,'finished'=>$finished] as $kind=>$lots)foreach($lots as $lot){
    $lot['kind']=$kind;$lot['kind_label']=$kind==='raw'?'Matière première':'Produit fini';
    if($kind==='finished'&&($lot['output_type']??'')==='co_product')$lot['kind_label']='Coproduit';
    $lot['available']=$lot['status']==='available'&&$lot['expiry_date']>=$today?max(0,(float)$lot['quantity_kg']-(float)$lot['reserved_kg']):0;
    $usable=(float)$lot['quantity_kg']>0&&in_array($lot['status'],['available','reserved','expired'],true);
    $lot['dlc_state']=!$usable?'none':($lot['expiry_date']<$today?'expired':($lot['expiry_date']<=$soon?'soon':'valid'));
    $inventory[]=$lot;
    if($lot['available']>0)$stockKpis[$kind==='raw'?0:1][]=$lot;
    if($lot['dlc_state']==='soon')$stockKpis[2][]=$lot;
}
usort($inventory,static function($a,$b){return strcmp($a['expiry_date'],$b['expiry_date']);});
?>
<section class="table-panel butchery-history" data-butchery-panel="stocks">
<header class="butchery-history-heading"><div><h3>Inventaire des lots</h3><p>Matières, produits finis et coproduits · Dates limites les plus proches en premier</p></div><span class="butchery-status"><?=count($inventory)?> lots</span></header>
<?php if(!$inventory):?><p class="butchery-empty">Aucun lot enregistré. Les stocks apparaissent après validation des réceptions, abattages ou fabrications.</p><?php else: $stockRows=$inventory;$stockFilters=true;require __DIR__.'/stock_table.php';endif;?>
</section>
<?php foreach($inventory as $lot):
$id='butchery-stock-'.$lot['kind'].'-'.$lot['id'];
$source=$lot['kind']==='raw'?($lot['slaughter_number']?:($lot['receipt_number']?:'—')):($lot['order_number']?:'—');
$fields=['Article'=>$lot['item_name'],'Code article'=>$lot['item_code'],'Type'=>$lot['kind_label'],'Opération source'=>$source,'Date de production'=>date('d/m/Y',strtotime($lot['production_date'])),'Date limite de consommation'=>date('d/m/Y',strtotime($lot['expiry_date'])),'Coût unitaire / kg'=>number_format((float)$lot['unit_cost'],4,',',' '),'Valeur du stock physique'=>number_format((float)$lot['quantity_kg']*(float)$lot['unit_cost'],2,',',' ')];
if($lot['kind']==='raw')$fields+=['Fournisseur'=>$lot['supplier_name']?:'—','Lot d’élevage'=>$lot['batch_number']?:'—'];
?>
<section id="<?=e($id)?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?=e($id)?>-title"><header><h2 id="<?=e($id)?>-title">Détail du lot</h2><button class="modal-close" type="button" data-workspace-modal-close aria-label="Fermer">×</button></header>
<div class="feed-form-body"><div class="butchery-detail-banner"><strong><?=e($lot['lot_number'])?></strong><span class="butchery-status"><?=e($stockLabels[$lot['status']]??$lot['status'])?></span></div>
<div class="butchery-detail-balance"><?php foreach(['Stock physique'=>$lot['quantity_kg'],'Réservé'=>$lot['reserved_kg'],'Disponible pour une opération'=>$lot['available']] as $name=>$value):?><div><span><?=e($name)?></span><strong><?=e($kg($value))?></strong></div><?php endforeach;?></div>
<?php if(in_array($lot['dlc_state'],['soon','expired'],true)):?><p class="butchery-detail-note <?=$lot['dlc_state']==='expired'?'butchery-stock-expired':''?>"><?=$lot['dlc_state']==='expired'?'DLC dépassée : ce lot ne peut plus être utilisé.':'DLC proche : ce lot arrive à échéance dans les trois jours, aujourd’hui inclus.'?></p><?php endif;?>
<dl class="butchery-history-details"><?php foreach($fields as $name=>$value):?><div><dt><?=e($name)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?></dl><p class="butchery-detail-note">Les quantités sont les soldes actuels. Le disponible exclut les réservations et les lots dont le statut ou la DLC bloque l’utilisation.</p></div><footer><button class="btn-secondary" type="button" data-workspace-modal-close>Fermer</button></footer></section>
<?php endforeach;?>
<?php foreach(['Matières disponibles','Produits finis disponibles','DLC sous 3 jours'] as $index=>$title):?>
<section id="butchery-stock-kpi-<?=$index?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor butchery-kpi-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="stock-kpi-title-<?=$index?>"><header><h2 id="stock-kpi-title-<?=$index?>"><?=e($title)?></h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><div class="feed-form-body"><p class="butchery-guide"><?=count($stockKpis[$index])?> lot(s) · <?=e($butcheryMetrics['stocks'][$index][0])?><?=$index===2?' lots à échéance':''?></p><?php if(!$stockKpis[$index]):?><p class="butchery-empty">Aucun lot concerné.</p><?php else:$stockRows=$stockKpis[$index];$stockFilters=false;require __DIR__.'/stock_table.php';endif;?></div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button></footer></section>
<?php endforeach;?>
