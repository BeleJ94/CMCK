<?php
// Client drawer: creation when $c is null, otherwise edition of $c. Expects $flowViews and $returnPath.
$editing=$c!==null;$formId=$editing?'customer-edit-'.$c['id']:'customer-new';
$value=static fn($key,$default='')=>$editing?($c[$key]??$default):$default;$type=$value('customer_type','company');
$drawerId=$formId;$drawerTitle=$editing?'Modifier '.$c['code']:'Nouveau client';require $flowViews.'/drawer_start.php';
$action=$editing?'customers/'.$c['id']:'customers';$confirm=$editing?'Enregistrer les modifications de cette fiche ?':'Créer cette fiche client ?';require $flowViews.'/form_start.php';
?>
<?php if($editing): ?><input type="hidden" name="version" value="<?=e($c['version'])?>"><?php endif; ?>
<div class="form-grid" data-customer-form>
<fieldset class="form-wide pig-ref-toggles customer-types"><legend>Type de client *</legend>
<?php foreach([['company','bi-building','Société, commerce ou institution : identifiants légaux et contact.'],['individual','bi-person','Particulier régulier qui souhaite un compte. Les ventes ponctuelles se font au comptoir sans fiche.']] as [$key,$icon,$help]): ?><label class="pig-ref-toggle"><input type="radio" name="customer_type" value="<?=e($key)?>" required<?=$type===$key?' checked':''?>><span class="pig-ref-toggle__icon"><i class="bi <?=e($icon)?>" aria-hidden="true"></i></span><span class="pig-ref-toggle__text"><strong><?=e(CustomerService::TYPES[$key])?></strong><small><?=e($help)?></small></span></label><?php endforeach; ?>
</fieldset>
<label><span data-customer-name-label>Raison sociale *</span><input name="name" maxlength="180" required value="<?=e($value('name'))?>" autocomplete="off"></label>
<label data-company-only><span>Nom commercial</span><input name="trade_name" maxlength="180" value="<?=e($value('trade_name'))?>" placeholder="Sigle ou enseigne"></label>
<fieldset class="form-wide customer-legal" data-company-only><legend>Identifiants légaux</legend><div class="form-grid">
<label><span>RCCM</span><input name="rccm" maxlength="100" value="<?=e($value('rccm'))?>" autocapitalize="characters"></label>
<label><span>Id. Nat.</span><input name="id_nat" maxlength="100" value="<?=e($value('id_nat'))?>" autocapitalize="characters"></label>
<label><span>NIF</span><input name="nif" maxlength="100" value="<?=e($value('nif'))?>" autocapitalize="characters"></label>
</div><small>Facultatifs. Chaque identifiant ne peut appartenir qu’à un seul client.</small></fieldset>
<label data-company-only><span>Personne de contact</span><input name="contact_name" maxlength="150" value="<?=e($value('contact_name'))?>"></label>
<label data-company-only><span>Fonction</span><input name="contact_role" maxlength="100" value="<?=e($value('contact_role'))?>" placeholder="Ex. responsable des achats"></label>
<label><span>Téléphone *</span><input name="phone" type="tel" maxlength="50" required value="<?=e($value('phone'))?>" placeholder="+243 …" inputmode="tel"></label>
<label><span>E-mail</span><input name="email" type="email" maxlength="190" value="<?=e($value('email'))?>"></label>
<label><span>Adresse</span><input name="address" maxlength="255" value="<?=e($value('address'))?>" placeholder="Avenue, numéro, quartier"></label>
<label><span>Ville ou commune</span><input name="city" maxlength="120" value="<?=e($value('city'))?>"></label>
<label><span>Devise de facturation *</span><select name="billing_currency" required><?php foreach(CustomerService::CURRENCIES as $currency): ?><option value="<?=e($currency)?>"<?=$value('billing_currency','USD')===$currency?' selected':''?>><?=e($currency)?></option><?php endforeach; ?></select><small>Devise des montants de ses commandes et relevés.</small></label>
<label class="form-wide"><span>Observations</span><textarea name="notes" maxlength="500" rows="2" placeholder="Conditions particulières, horaires de livraison…"><?=e($value('notes'))?></textarea></label>
</div>
<?php $submitLabel=$editing?'Enregistrer les modifications':'Créer le client';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php'; ?>
