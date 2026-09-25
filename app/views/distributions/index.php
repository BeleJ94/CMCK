<?php
$labels=['validated'=>'Validée','cancelled'=>'Annulée','draft'=>'Brouillon'];
$number=static function($v,$decimals=0){return number_format((float)$v,$decimals,',',' ');};
$date=static function($v){return $v?date('d/m/Y H:i',strtotime($v)):'—';};
$validated=array_values(array_filter($distributions,static function($r){return $r['status']==='validated';}));
$metrics=[['Stock disponible',$number(array_sum(array_column($availableStocks,'total_weight_kg')),3).' kg','stock'],['Sacs disponibles',$number(array_sum(array_column($availableStocks,'quantity_bags'))),'stock'],['Distribué · validé',$number(array_sum(array_column($validated,'total_weight_kg')),3).' kg','validated'],['Bons de sortie',$number(count($distributions)),'all']];
$modals=[];$modal=static function($id,$title,$body,$footer=null)use(&$modals){ob_start();?>
<section id="<?=e($id)?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor butchery-kpi-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?=e($id)?>-title"><header><h2 id="<?=e($id)?>-title"><?=e($title)?></h2><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header><div class="feed-form-body"><?=$body?></div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><?=$footer??''?></footer></section>
<?php $modals[]=ob_get_clean();};
?>
<div class="butchery-workspace transfer-workspace distribution-workspace">
<header class="butchery-heading"><span class="butchery-heading-icon"><i class="bi bi-send-check" aria-hidden="true"></i></span><div><p>Distribution</p><h2>Sorties de produits finis</h2></div></header>
<?php if($success):?><div class="app-alert app-alert-success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="app-alert app-alert-error"><?=e($error)?></div><?php endif;?>
<div class="butchery-metrics"><?php foreach($metrics as $metric):?><button type="button" class="butchery-kpi" data-workspace-modal-open="distribution-kpi-<?=e($metric[2])?>"><strong><?=e($metric[1])?></strong><span><?=e($metric[0])?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></button><?php endforeach;?></div>
<section class="form-panel butchery-slaughter-actions"><div class="butchery-slaughter-toolbar"><h3>Bons de sortie</h3><button type="button" class="btn-primary" data-workspace-modal-open="distribution-create"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouvelle sortie</button></div></section>
<section class="table-panel butchery-history"><header class="butchery-history-heading"><h3>Historique des distributions</h3></header><?php $rows=$distributions;$filters=true;require __DIR__.'/table.php';?></section>
<?php
foreach(['all'=>['Bons de sortie',$distributions],'validated'=>['Distributions validées',$validated]] as $key=>$group){ob_start();$rows=$group[1];$filters=false;require __DIR__.'/table.php';$modal('distribution-kpi-'.$key,$group[0],ob_get_clean());}
ob_start();?>
<?php if(!$availableStocks):?><p class="butchery-empty">Aucun stock disponible.</p><?php else:?><table class="enterprise-table"><thead><tr><th>Site / lot</th><th>Produit / format</th><th>Sacs disponibles</th><th>Poids disponible</th></tr></thead><tbody><?php foreach($availableStocks as $stock):?><tr><td><?=e($stock['site_code'])?><small>Lot #<?=e($stock['id'])?></small></td><td><?=e($stock['product_name'])?><small><?=e($stock['format_name'])?></small></td><td><?=e($number($stock['quantity_bags']))?></td><td><?=e($number($stock['total_weight_kg'],3))?> kg</td></tr><?php endforeach;?></tbody></table><?php endif;?>
<?php $modal('distribution-kpi-stock','Stocks disponibles · hors réservations',ob_get_clean());
foreach($distributions as $row):ob_start();?>
<div class="butchery-detail-banner"><div><small><?=e($date($row['distributed_at']))?></small><strong><?=e($row['official_document_number']?:$row['exit_voucher'])?></strong></div><span class="butchery-status"><?=e($labels[$row['status']]??$row['status'])?></span></div>
<dl class="butchery-history-details"><?php foreach(['Site'=>$row['site_code'],'Destination'=>$row['recipient_name'],'Produit'=>$row['product_name'],'Format'=>$row['format_name'],'Quantité'=>$number($row['quantity_bags']).' sacs','Poids'=>$number($row['total_weight_kg'],3).' kg','Lot source'=>'#'.$row['finished_stock_id'],'Transporteur'=>$row['transporter']?:'—','Référence interne'=>$row['exit_voucher'],'Créé par'=>$row['agent_name']?:'—','Validé par'=>$row['validator_name']?:'—'] as $key=>$value):?><div><dt><?=e($key)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?></dl>
<?php $body=ob_get_clean();$footer='<a class="btn-secondary" href="'.e(base_url('distributions/'.$row['id'])).'">Ouvrir le dossier</a><a class="btn-primary" href="'.e(base_url('distributions/'.$row['id'].'/print?export=pdf')).'">Bon PDF</a>';$modal('distribution-detail-'.$row['id'],'Détail de la distribution',$body,$footer);endforeach;
echo implode('',$modals);require __DIR__.'/modal_create.php';?>
</div>
