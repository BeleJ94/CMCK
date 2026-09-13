<?php
$selectedTransport=null;
foreach($transports as$transport){if((string)$entry['transport_id']===(string)$transport['id'])$selectedTransport=$transport;}
$entryContext=function($transport){return [
    'plate'=>$transport['plate_number']??'—',
    'origin'=>($transport['origin_site_name']??'')?:($transport['supplier_name']??'—'),
    'product'=>$transport['product_name']??'—',
    'quantity'=>$transport?number_format((float)$transport['shipped_quantity_kg'],3,',',' ').' kg':'—',
];};
$context=$entryContext($selectedTransport);
?>
<div class="weighing-entry-workspace">
<header class="weighing-entry-heading"><div><p class="page-kicker">PONT-BASCULE · ENTRÉE</p><h2>Réceptionner un BT</h2><p>Choisissez le transport, puis saisissez le poids du camion chargé.</p></div><a class="btn-secondary" href="<?=e(base_url('weighings'))?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Toutes les pesées</a></header>
<nav class="weighing-entry-steps" aria-label="Parcours de réception"><span aria-current="step"><b>1</b> Pesée d’entrée</span><span><b>2</b> Déchargement</span><span><b>3</b> Sortie et validation</span></nav>
<?php if(!empty($error)):?><div class="app-alert app-alert-error" role="alert"><?=e($error)?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="app-alert app-alert-error" role="alert">L’entrée n’a pas été enregistrée. Vérifiez les champs indiqués.</div><?php endif;?>
<?php if(empty($currentSiteId)):?>
<section class="form-panel weighing-entry-site">
<h3>Choisissez le site de réception</h3>
<p>Vous consultez tous les sites. Sélectionnez le site où le camion sera pesé pour enregistrer son entrée. Pour les BT destinés aux silos, choisissez <strong>SILO · Silos</strong>.</p>
<form method="post" action="<?=e(base_url('context/site'))?>" data-ajax="false">
<?=csrf_field()?><input type="hidden" name="_return_to" value="weighings/entry">
<label for="entryReceptionSite">Site de réception</label><select id="entryReceptionSite" name="site_id" required><option value="">Sélectionner un site</option><?php foreach($receptionSites as$site):?><option value="<?=e($site['id'])?>"><?=e($site['code'].' · '.$site['name'])?></option><?php endforeach;?></select>
<button type="submit" class="btn-primary">Continuer vers la réception</button>
</form></section>
<?php elseif(!$transports):?>
<section class="form-panel weighing-entry-empty"><i class="bi bi-truck" aria-hidden="true"></i><h3>Aucun BT en attente de réception</h3><p>Les BT agricoles apparaissent ici après leur expédition depuis la ferme. Vérifiez que le site de réception est sélectionné.</p><a href="<?=e(base_url('weighbridge-transports'))?>" class="btn-secondary">Enregistrer un transport fournisseur</a></section>
<?php else:?>
<section class="form-panel weighing-entry-panel">
<form method="post" action="<?=e(base_url('weighings/entry'))?>" class="enterprise-form" data-validate data-weighing-entry>
<?=csrf_field()?>
<div class="weighing-entry-fields">
<section class="weighing-entry-transport" aria-labelledby="entryTransportTitle">
<h3 id="entryTransportTitle">Transport à réceptionner <span><?=e(count($transports))?> disponible(s)</span></h3>
<label for="entryTransport" data-entry-select-fallback><span>BT en transit *</span><select id="entryTransport" name="transport_id" required data-entry-transport aria-describedby="entryTransportHelp<?=!empty($errors['transport_id'])?' entryTransportError':''?>" <?=!empty($errors['transport_id'])?'aria-invalid="true"':''?>>
<option value="">Sélectionner un bon de transport</option>
<?php foreach($transports as$transport):$details=$entryContext($transport);?>
<option value="<?=e($transport['id'])?>" <?=(string)$entry['transport_id']===(string)$transport['id']?'selected':''?> <?php foreach($details as$key=>$value):?> data-entry-<?=e($key)?>="<?=e($value)?>"<?php endforeach;?>><?=e(($transport['bt_number']?:$transport['transport_reference']).' — '.$transport['plate_number'])?></option>
<?php endforeach;?>
</select><small id="entryTransportHelp">Les BT des fermes sont transmis automatiquement après expédition.</small>
<?php if(!empty($errors['transport_id'])):?><small id="entryTransportError" class="weighing-entry-error"><?=e($errors['transport_id'])?></small><?php endif;?></label>
<div class="entry-bt-picker" data-entry-picker hidden>
<label for="entryTransportDisplay">BT en transit *</label>
<div class="entry-bt-picker-field"><input id="entryTransportDisplay" data-entry-display readonly placeholder="Rechercher un bon de transport" aria-describedby="entryPickerHelp"><button type="button" data-entry-picker-open aria-label="Rechercher un BT en transit" aria-haspopup="dialog" aria-controls="entryBtDialog"><i class="bi bi-search" aria-hidden="true"></i></button></div>
<small id="entryPickerHelp">Recherchez un BT par sa référence, son camion ou son origine.</small>
<p class="weighing-entry-error" data-entry-picker-error role="alert" hidden>Sélectionnez un BT en transit avant d’enregistrer l’entrée.</p>
<?php if(!empty($errors['transport_id'])):?><p class="weighing-entry-error" role="alert"><?=e($errors['transport_id'])?></p><?php endif;?>
</div>
<dl class="weighing-entry-context" aria-live="polite" aria-atomic="true">
<?php foreach(['plate'=>'Camion','origin'=>'Origine','product'=>'Produit','quantity'=>'Marchandise annoncée'] as$key=>$label):?><div><dt><?=e($label)?></dt><dd data-entry-context="<?=e($key)?>"><?=e($context[$key])?></dd></div><?php endforeach;?>
</dl>
</section>
<section class="weighing-entry-weight" aria-labelledby="entryWeightTitle"><h3 id="entryWeightTitle">Pesée du camion chargé</h3>
<label for="entryGross"><span>Poids brut (kg) *</span><input id="entryGross" type="number" inputmode="decimal" step="0.001" min="0.001" name="poids_brut" value="<?=e($entry['poids_brut'])?>" placeholder="Ex. 8 350" required aria-describedby="entryGrossHelp<?=!empty($errors['poids_brut'])?' entryGrossError':''?>" <?=!empty($errors['poids_brut'])?'aria-invalid="true"':''?>><small id="entryGrossHelp">Relevez le poids au pont-bascule : camion + chargement. Il est différent de la quantité annoncée sur le BT.</small>
<?php if(!empty($errors['poids_brut'])):?><small id="entryGrossError" class="weighing-entry-error"><?=e($errors['poids_brut'])?></small><?php endif;?></label>
<p class="weighing-entry-next"><i class="bi bi-info-circle" aria-hidden="true"></i> Après l’entrée, le camion passe en attente de déchargement. Le stock silo sera crédité lors de la validation de sortie acceptée.</p>
</section>
</div>
<footer class="weighing-entry-footer"><div><span>Agent : <strong><?=e(Auth::user()['name']??'—')?></strong></span><small>Date et heure enregistrées automatiquement.</small></div><button type="submit" class="btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> Enregistrer l’entrée</button></footer>
</form>
</section>
<dialog id="entryBtDialog" class="entry-bt-dialog" aria-labelledby="entryBtDialogTitle">
<header><div><h3 id="entryBtDialogTitle">Choisir un BT en transit</h3><p>Sélectionnez le transport à réceptionner sur ce site.</p></div><button type="button" data-entry-picker-close aria-label="Fermer la recherche"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
<div class="entry-bt-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" data-entry-picker-search aria-label="Rechercher par BT, plaque, origine ou produit" placeholder="BT, plaque, origine, produit…"></div>
<p class="entry-bt-count" data-entry-picker-count role="status"></p>
<div class="entry-bt-results">
<?php foreach($transports as$transport):$details=$entryContext($transport);?>
<button type="button" class="entry-bt-option" data-entry-choice="<?=e($transport['id'])?>" aria-pressed="false">
<span class="entry-bt-option-top"><strong><?=e($transport['bt_number']?:$transport['transport_reference'])?></strong><span class="entry-bt-selected" hidden>✓ Sélectionné</span></span>
<span class="entry-bt-option-details"><span><small>Camion</small><?=e($details['plate'])?></span><span><small>Origine</small><?=e($details['origin'])?></span><span><small>Produit</small><?=e($details['product'])?></span><span><small>Quantité annoncée</small><?=e($details['quantity'])?></span></span>
</button>
<?php endforeach;?>
<p class="entry-bt-no-results" data-entry-picker-empty hidden>Aucun BT ne correspond à votre recherche. Essayez une autre référence, plaque ou origine.</p>
</div>
<footer><button type="button" class="btn-secondary" data-entry-picker-close>Annuler</button></footer>
</dialog>
<p class="weighing-entry-supplier">Un arrivage fournisseur sans BT ? <a href="<?=e(base_url('weighbridge-transports'))?>">Enregistrer un transport fournisseur</a></p>
<?php endif;?>
</div>
