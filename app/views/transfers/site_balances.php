<?php
$balances=$references['balances']??[];$stockKinds=[];
foreach($balances as $balance){$stockKinds[$balance['product_id'].':'.$balance['bag_format_id']]=$balance['product_name'].' · '.$balance['format_name'];}
?>
<details class="transfer-site-balances" data-site-balances open>
<summary>Disponibilités par site <small>en sacs</small></summary>
<?php if(!$stockKinds):?><p class="butchery-detail-note">Aucun stock à comparer sur les sites accessibles.</p><?php else:?>
<label class="transfer-stock-filter"><span class="sr-only">Produit / format</span><select data-stock-kind-filter aria-label="Produit / format"><?php foreach($stockKinds as $key=>$label):?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach;?></select></label>
<div class="transfer-balance-scroll" data-stock-balances="<?=e(json_encode($balances,JSON_UNESCAPED_UNICODE))?>"><table data-datatable="false"><thead><tr><th>Site / départ</th><th>Stock</th><th>Réservés</th><th>Disponibles</th></tr></thead><tbody><?php foreach($sites as $balanceSite):if(!Auth::canAccessSite($balanceSite['id']))continue;?><tr data-balance-site="<?=e($balanceSite['id'])?>"><td><button type="button" data-select-source="<?=e($balanceSite['id'])?>" title="<?=e($balanceSite['name'])?>" aria-label="<?=e('Départ : '.$balanceSite['name'])?>"><?=e($balanceSite['code'])?></button></td><td data-site-physical>0</td><td data-site-reserved>0</td><td><strong data-site-available>0</strong></td></tr><?php endforeach;?></tbody></table></div>
<?php endif;?>
</details>
