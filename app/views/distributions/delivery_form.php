<?php
// Delivery of one order ($o): where the bags leave from, how many, and who receives them. Expects the helpers of index.php.
$first=array_key_first($o['options']);$lines=array_values(array_filter($o['lines'],static fn($l)=>(int)$l['to_deliver']>0));
// Default place: the first one (the office when bags are ready there); every bag ready there is proposed.
$place=$o['options'][$first];$defaults=[];$totalBags=0;$totalKg=0.0;$totalUsd=0.0;
foreach($lines as $l){$n=min((int)($place['lines'][$l['id']]??0),(int)$l['to_deliver']);$defaults[$l['id']]=$n;$totalBags+=$n;$totalKg+=$n*(float)$l['weight_kg'];$totalUsd+=$n*(float)$l['unit_price_usd'];}
$drawerId='deliver-'.$o['id'];$drawerTitle='Livrer la commande '.$o['order_number'];$drawerClass='delivery-drawer';require $flowViews.'/drawer_start.php'; ?>
<form method="post" action="<?=e(base_url('distributions/deliveries'))?>" class="enterprise-form delivery-form" data-delivery-form data-native-submit data-order="<?=e($o['order_number'])?>" data-customer="<?=e($o['customer_name'])?>" data-currency="<?=e($o['currency'])?>" data-rate="<?=e((float)$o['exchange_rate'])?>" data-to-deliver="<?=e($o['to_deliver'])?>"><?=csrf_field()?>
<input type="hidden" name="order_id" value="<?=e($o['id'])?>">
<div class="feed-form-body transfer-op delivery-op"><p class="app-alert app-alert-error" data-delivery-error role="alert" hidden></p>
<div class="transfer-op__context"><span><i class="bi bi-person-vcard" aria-hidden="true"></i> <?=e($o['customer_name'].' · '.$o['customer_code'])?></span><span><i class="bi bi-receipt" aria-hidden="true"></i> <?=e($o['order_number'])?> · reste <?=$int($o['to_deliver'])?> sac(s) à livrer</span></div>

<div class="delivery-op__layout"><div class="delivery-op__main">
<fieldset class="delivery-step"><legend><span class="delivery-step__num">1</span> D’où partent les sacs ?</legend>
<div class="delivery-choices">
<?php foreach($o['options'] as $key=>$opt): ?><label class="delivery-choice"><input type="radio" name="source" value="<?=e($key)?>" required<?=$key===$first?' checked':''?> data-site="<?=e($opt['site_name'])?>" data-stage="<?=e($stages[$opt['stage']])?>" data-ready='<?=e(json_encode((object)$opt['lines']))?>'><span><i class="bi <?=$opt['stage']==='office'?'bi-shop-window':'bi-building-gear'?>" aria-hidden="true"></i><strong><?=e($opt['site_name'])?></strong><small><?=e($opt['stage']==='office'?'Bureau principal':'Directement du site de production')?> · <?=$int($opt['bags'])?> sac(s) prêt(s)</small></span></label><?php endforeach; ?>
</div></fieldset>

<fieldset class="delivery-step"><legend><span class="delivery-step__num">2</span> Combien de sacs remettre ?</legend>
<p class="delivery-step__hint">Tout ce qui est prêt à l’endroit choisi est proposé. Réduisez la quantité pour une livraison partielle.</p>
<?php foreach($lines as $l):$here=(int)($place['lines'][$l['id']]??0);$n=$defaults[$l['id']];$inputId='deliver-'.$o['id'].'-bags-'.$l['id']; ?>
<div class="delivery-line<?=$n?'':' is-empty'?>" data-delivery-line="<?=e($l['id'])?>" data-weight="<?=e((float)$l['weight_kg'])?>" data-price="<?=e((float)$l['unit_price_usd'])?>" data-label="<?=e($l['product_name'].' · '.$l['format_name'])?>" data-to-deliver="<?=e($l['to_deliver'])?>">
<div class="delivery-line__product"><strong><?=e($l['product_name'])?></strong><span><?=e($l['format_name'])?> · <?=e($money($l['unit_price_usd']))?> USD le sac</span></div>
<ul class="delivery-line__facts">
<li>Commandé <strong><?=$int($l['quantity_bags'])?></strong></li>
<li>Déjà livré <strong><?=$int($l['delivered_bags'])?></strong></li>
<li>Reste à livrer <strong><?=$int($l['to_deliver'])?></strong></li>
<li class="is-ready"><span data-line-place>Prêts à <?=e($place['site_name'])?></span> <strong data-line-ready><?=$int($here)?></strong></li>
</ul>
<div class="delivery-line__qty">
<div class="delivery-line__qty-head"><label for="<?=e($inputId)?>">Sacs à livrer</label><button type="button" class="btn-secondary delivery-line__all" data-line-fill="all"<?=$n?'':' disabled'?>>Tout (<span data-line-max><?=$int($n)?></span>)</button></div>
<div class="delivery-stepper"><button type="button" class="btn-secondary" data-line-step="-1" aria-label="<?=e('Un sac de moins : '.$l['format_name'])?>"<?=$n?'':' disabled'?>><i class="bi bi-dash-lg" aria-hidden="true"></i></button><input type="number" id="<?=e($inputId)?>" name="bags[<?=e($l['id'])?>]" min="0" max="<?=e($n)?>" step="1" value="<?=e($n)?>" inputmode="numeric" data-line-input<?=$n?'':' disabled'?>><button type="button" class="btn-secondary" data-line-step="1" aria-label="<?=e('Un sac de plus : '.$l['format_name'])?>"<?=$n?'':' disabled'?>><i class="bi bi-plus-lg" aria-hidden="true"></i></button></div>
<span class="delivery-line__total" data-line-total><?=e($n?$kg($n*(float)$l['weight_kg']).' · '.$money($n*(float)$l['unit_price_usd']).' USD':'Rien de prêt ici pour ce produit')?></span>
</div>
</div>
<?php endforeach; ?>
<p class="transfer-op__summary" data-delivery-summary aria-live="polite"><?=e($totalBags?'Total à livrer : '.$int($totalBags).' sac(s) · '.$kg($totalKg).' · '.$money($totalUsd).' USD':'Indiquez les sacs à remettre au client.')?></p>
</fieldset></div>

<fieldset class="delivery-step"><legend><span class="delivery-step__num">3</span> Remise au client</legend>
<div class="delivery-modes" role="radiogroup" aria-label="Mode de livraison"><?php foreach($modes as $value=>$label): ?><label class="delivery-mode"><input type="radio" name="delivery_mode" value="<?=e($value)?>" required<?=$value==='pickup'?' checked':''?>><span><i class="bi <?=$value==='pickup'?'bi-person-walking':'bi-truck'?>" aria-hidden="true"></i> <?=e($label)?></span></label><?php endforeach; ?></div>
<div class="form-grid">
<label><span data-vehicle-label>Véhicule du client</span><input name="vehicle_reference" maxlength="60" autocomplete="off" placeholder="Immatriculation (facultatif)" data-vehicle></label>
<label><span data-driver-label>Chauffeur</span><input name="driver_name" maxlength="150" autocomplete="off" placeholder="Facultatif" data-driver></label>
<label><span>Reçu par *</span><input name="receiver_name" maxlength="150" required autocomplete="off" placeholder="Nom de la personne qui reçoit"></label>
<label><span>Téléphone</span><input name="receiver_phone" maxlength="50" inputmode="tel" autocomplete="off" placeholder="Facultatif"></label>
<label><span>Date et heure de remise *</span><input type="datetime-local" name="delivered_at" value="<?=e(date('Y-m-d\TH:i'))?>" max="<?=e(date('Y-m-d\TH:i',strtotime('+5 minutes')))?>" required></label>
<label><span>Observations</span><input name="notes" maxlength="500" placeholder="Facultatif"></label>
</div></fieldset></div>
</div>
<footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary" data-delivery-submit><i class="bi bi-check2" aria-hidden="true"></i> Vérifier et livrer</button></footer>
</form>
<?php require $flowViews.'/drawer_end.php'; ?>
