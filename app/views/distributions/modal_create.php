<?php
// Exit without an order: a registered client or a free recipient, and one lot of free stock (grouped by site, oldest first).
$lotsBySite=[];foreach($availableStocks as $stock)$lotsBySite[$stock['site_name']??$stock['site_code']][]=$stock;
foreach($lotsBySite as &$siteLots)usort($siteLots,static fn($a,$b)=>[$a['product_name'],(float)$a['weight_kg'],$a['stock_date']??'',(int)$a['id']]<=>[$b['product_name'],(float)$b['weight_kg'],$b['stock_date']??'',(int)$b['id']]);
unset($siteLots);
$drawerId='distribution-create';$drawerTitle='Sortie sans commande';require $flowViews.'/drawer_start.php'; ?>
<form method="post" action="<?=e(base_url('distributions'))?>" class="enterprise-form" data-distribution-editor data-native-submit><?=csrf_field()?><input type="hidden" name="exit_voucher" value="">
<div class="feed-form-body transfer-op delivery-op"><p class="app-alert app-alert-error" data-distribution-error role="alert" hidden></p>
<p class="livestock-guide">Pour remettre des sacs hors commande : vente au comptoir, don, usage interne. Seul le stock libre (non réservé aux commandes) peut sortir.</p>
<?php if(!$availableStocks): ?><p class="flow-empty">Aucun stock libre pour une sortie.</p><?php endif; ?>
<fieldset class="delivery-step"><legend><span class="delivery-step__num">1</span> À qui ?</legend>
<div class="form-grid">
<?php if($customers): ?><label class="form-wide"><span>Client enregistré</span><select name="customer_id" data-exit-customer><option value="">— Autre destinataire —</option><?php foreach($customers as $c): ?><option value="<?=e($c['id'])?>"><?=e($c['name'].' · '.$c['code'])?></option><?php endforeach; ?></select></label><?php endif; ?>
<label class="form-wide" data-exit-recipient><span>Destinataire *</span><input name="recipient_name" required maxlength="150" autocomplete="off" placeholder="Nom du client, du service ou de la personne"></label>
</div></fieldset>
<fieldset class="delivery-step"><legend><span class="delivery-step__num">2</span> Quoi ?</legend>
<div class="form-grid">
<label class="form-wide"><span>Lot de stock libre *</span><select name="finished_stock_id" required<?=!$availableStocks?' disabled':''?>><option value="">Choisir un produit…</option>
<?php foreach($lotsBySite as $site=>$siteLots): ?><optgroup label="<?=e($site)?>"><?php foreach($siteLots as $stock):$free=max(0,min((int)$stock['quantity_bags'],(int)floor((float)$stock['total_weight_kg']/max(0.001,(float)$stock['weight_kg'])))); ?><option value="<?=e($stock['id'])?>" data-bags="<?=e($free)?>" data-weight="<?=e($stock['weight_kg'])?>" data-label="<?=e($stock['product_name'].' · '.$stock['format_name'])?>" data-lot="<?=e('Lot #'.$stock['id'].(!empty($stock['stock_date'])?' du '.date('d/m/Y',strtotime($stock['stock_date'])):''))?>" data-site="<?=e($site)?>"><?=e($stock['product_name'].' · '.$stock['format_name'].' — '.number_format($free,0,',',' ').' sac(s) · lot #'.$stock['id'].(!empty($stock['stock_date'])?' du '.date('d/m/Y',strtotime($stock['stock_date'])):''))?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
</select></label>
<label><span>Quantité (sacs) *</span><span class="transfer-op__qty exit-qty"><input type="number" name="quantity_bags" min="1" step="1" required inputmode="numeric"><span><button type="button" class="btn-secondary" data-exit-fill title="Tout le lot">Tout</button></span></span></label>
<label><span>Date de sortie *</span><input type="datetime-local" name="distributed_at" value="<?=e(date('Y-m-d\TH:i'))?>" required></label>
</div>
<p class="transfer-op__summary distribution-stock-summary" data-distribution-summary aria-live="polite" hidden></p>
</fieldset>
<fieldset class="delivery-step"><legend><span class="delivery-step__num">3</span> Transport</legend>
<div class="form-grid"><label class="form-wide"><span>Transporteur ou véhicule</span><input name="transporter" maxlength="150" autocomplete="off" placeholder="Facultatif"></label></div>
</fieldset>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary"<?=!$availableStocks?' disabled':''?>><i class="bi bi-check2" aria-hidden="true"></i> Vérifier et sortir</button></footer>
</form>
<?php require $flowViews.'/drawer_end.php'; ?>
