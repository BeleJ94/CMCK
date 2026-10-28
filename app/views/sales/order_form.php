<?php
// Order drawer: creation when $o is null, otherwise edition of the draft $o. Expects $flowViews, $customers, $depots, $catalog, $rate.
$editing=$o!==null;$formId=$editing?'order-edit-'.$o['id']:'order-new';
$drawerId=$formId;$drawerTitle=$editing?'Modifier '.$o['order_number']:'Nouvelle commande';require $flowViews.'/drawer_start.php';
$action=$editing?'sales/orders/'.$o['id']:'sales/orders';$confirm=$editing?'Enregistrer ce brouillon ? Les prix et le taux sont mis à jour.':'Enregistrer cette commande en brouillon ?';require $flowViews.'/form_start.php';
$orderLines=$editing?$o['lines']:[null];$lineIndex=0;
$lineRow=static function($index,?array $line)use($catalog){
    $key=$line?$line['product_id'].'-'.$line['bag_format_id']:'';
    $options='<option value="">Choisir le produit et le format</option>';
    foreach($catalog as $row){$value=$row['product_id'].'-'.$row['bag_format_id'];$options.='<option value="'.e($value).'" data-price="'.e((string)$row['price_usd']).'" data-free="'.e($row['free_bags']).'" data-source="'.e($row['source_name']).'"'.($row['price_usd']===null?' disabled':'').($value===$key?' selected':'').'>'.e($row['product_name'].' · '.$row['format_name'].($row['price_usd']===null?' (tarif à fixer)':' · '.number_format((float)$row['price_usd'],2,',',' ').' USD')).'</option>';}
    return '<tr data-order-line><td><label><span class="sr-only">Produit</span><select name="lines['.e($index).'][product_key]" required>'.$options.'</select></label><small data-order-line-hint></small></td><td><label><span class="sr-only">Sacs</span><input type="number" name="lines['.e($index).'][quantity_bags]" min="1" max="100000" step="1" inputmode="numeric" required value="'.e($line['quantity_bags']??'').'"></label></td><td class="num" data-order-line-amount>—</td><td><button type="button" class="btn-secondary" data-order-line-remove aria-label="Retirer cette ligne"><i class="bi bi-trash" aria-hidden="true"></i></button></td></tr>';
};
?>
<div class="form-grid" data-order-form data-rate="<?=e((string)$rate)?>">
<label><span>Client *</span><select name="customer_id" required><option value="">Choisir le client</option><?php foreach($customers as $c): ?><option value="<?=e($c['id'])?>" data-currency="<?=e($c['billing_currency'])?>"<?=$editing&&(int)$o['customer_id']===(int)$c['id']?' selected':''?>><?=e($c['name'].' · '.$c['code'].' · '.$c['billing_currency'])?></option><?php endforeach; ?></select><small>Seuls les clients actifs peuvent commander.</small></label>
<label><span>Dépôt du bureau principal *</span><select name="destination_site_id" required><?php foreach($depots as $d): ?><option value="<?=e($d['id'])?>"<?=$editing&&(int)$o['destination_site_id']===(int)$d['id']?' selected':''?>><?=e($d['name'])?></option><?php endforeach; ?></select><small>Où arriveront les sacs du client.</small></label>
<label><span>Livraison souhaitée</span><input type="date" name="requested_date" min="<?=date('Y-m-d')?>" value="<?=e($editing?(string)$o['requested_date']:'')?>"></label>
<label><span>Observations</span><input name="notes" maxlength="500" value="<?=e($editing?(string)$o['notes']:'')?>" placeholder="Référence du client, conditions…"></label>
<div class="form-wide order-lines"><table class="enterprise-table compact-table" data-datatable="false" aria-label="Lignes de la commande"><thead><tr><th>Produit et format *</th><th>Sacs *</th><th class="num">Montant</th><th><span class="sr-only">Retirer</span></th></tr></thead><tbody data-order-lines><?php foreach($orderLines as $line)echo $lineRow($lineIndex++,$line); ?></tbody></table>
<template data-order-line-template><?=$lineRow('__INDEX__',null)?></template>
<button type="button" class="btn-secondary" data-order-line-add data-next-index="<?=e($lineIndex)?>"><i class="bi bi-plus-circle" aria-hidden="true"></i> Ajouter une ligne</button></div>
<p class="form-wide order-total" data-order-total aria-live="polite">Ajoutez les produits commandés.</p>
</div>
<p class="livestock-guide">Le prix est celui du tarif entreprises au moment de l’enregistrement. À la validation, les sacs disponibles sont réservés au site de production ; le reste sera réservé dès son emballage.</p>
<?php $submitLabel=$editing?'Enregistrer le brouillon':'Enregistrer la commande';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php'; ?>
