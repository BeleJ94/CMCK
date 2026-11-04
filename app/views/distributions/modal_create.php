<?php
// Exit without an order: a registered client or a free recipient, and a quantity of a product (global free stock of a site).
// The bags are taken from the oldest free lots; the preview shows which ones before confirming.
$productsBySite=[];foreach($availableProducts as $product)$productsBySite[$product['site_name']][]=$product;
$drawerId='distribution-create';$drawerTitle='Sortie sans commande';require $flowViews.'/drawer_start.php'; ?>
<form method="post" action="<?=e(base_url('distributions'))?>" class="enterprise-form" data-distribution-editor data-native-submit><?=csrf_field()?><input type="hidden" name="exit_voucher" value="">
<div class="feed-form-body transfer-op delivery-op"><p class="app-alert app-alert-error" data-distribution-error role="alert" hidden></p>
<p class="livestock-guide">Pour remettre des sacs hors commande : vente au comptoir, don, usage interne. Seul le stock libre (non réservé aux commandes) peut sortir.</p>
<?php if(!$availableProducts): ?><p class="flow-empty">Aucun stock libre pour une sortie.</p><?php endif; ?>
<fieldset class="delivery-step"><legend><span class="delivery-step__num">1</span> À qui ?</legend>
<div class="form-grid">
<?php if($customers): ?><label class="form-wide"><span>Client enregistré</span><select name="customer_id" data-exit-customer><option value="">— Autre destinataire —</option><?php foreach($customers as $c): ?><option value="<?=e($c['id'])?>"><?=e($c['name'].' · '.$c['code'])?></option><?php endforeach; ?></select></label><?php endif; ?>
<label class="form-wide" data-exit-recipient><span>Destinataire *</span><input name="recipient_name" required maxlength="150" autocomplete="off" placeholder="Nom du client, du service ou de la personne"></label>
</div></fieldset>
<fieldset class="delivery-step"><legend><span class="delivery-step__num">2</span> Quoi ?</legend>
<div class="form-grid">
<label class="form-wide"><span>Produit *</span><select name="stock_key" required<?=!$availableProducts?' disabled':''?>><option value="">Choisir un produit…</option>
<?php foreach($productsBySite as $site=>$siteProducts): ?><optgroup label="<?=e($site)?>"><?php foreach($siteProducts as $product):$lotCount=count($product['lots']); ?><option value="<?=e($product['key'])?>" data-bags="<?=e($product['free_bags'])?>" data-weight="<?=e($product['weight_kg'])?>" data-label="<?=e($product['product_name'].' · '.$product['format_name'])?>" data-site="<?=e($site)?>" data-lots="<?=e(json_encode($product['lots']))?>"><?=e($product['product_name'].' · '.$product['format_name'].' — '.number_format($product['free_bags'],0,',',' ').' sac(s) libres'.($lotCount>1?' ('.$lotCount.' lots)':''))?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
</select><small>Stock libre du site, tous lots confondus. Les sacs les plus anciens partent en premier.</small></label>
<label><span>Quantité (sacs) *</span><span class="transfer-op__qty exit-qty"><input type="number" name="quantity_bags" min="1" step="1" required inputmode="numeric"><span><button type="button" class="btn-secondary" data-exit-fill title="Tout le stock libre de ce produit">Tout</button></span></span></label>
<label><span>Date de sortie *</span><input type="datetime-local" name="distributed_at" value="<?=e(date('Y-m-d\TH:i'))?>" required></label>
</div>
<p class="transfer-op__summary distribution-stock-summary" data-distribution-summary aria-live="polite" hidden></p>
<div class="exit-lots" data-exit-lots hidden><p class="exit-lots__title"><i class="bi bi-layers" aria-hidden="true"></i> Sacs prélevés automatiquement</p><ul data-exit-lots-list></ul></div>
</fieldset>
<fieldset class="delivery-step"><legend><span class="delivery-step__num">3</span> Transport</legend>
<div class="form-grid"><label class="form-wide"><span>Transporteur ou véhicule</span><input name="transporter" maxlength="150" autocomplete="off" placeholder="Facultatif"></label></div>
</fieldset>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary"<?=!$availableProducts?' disabled':''?>><i class="bi bi-check2" aria-hidden="true"></i> Vérifier et sortir</button></footer>
</form>
<?php require $flowViews.'/drawer_end.php'; ?>
