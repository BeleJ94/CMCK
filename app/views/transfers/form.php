<form method="post" action="<?=e(base_url('transfers'))?>" data-transfer-create-form data-native-submit>
<?=csrf_field()?>
<div class="feed-form-body">
<p class="app-alert app-alert-error" data-transfer-error role="alert" hidden></p>
<?php require __DIR__.'/site_balances.php';?>
<div class="form-grid">
<label><span>Type *</span><select name="transfer_type" required><option value="inter_site">Inter-site</option><option value="internal">Interne</option></select></label>
<label><span>Départ *</span><select name="source_site_id" required><option value="">Choisir…</option><?php foreach($sites as $site):if(!Auth::canAccessSite($site['id']))continue;?><option value="<?=e($site['id'])?>" <?=(int)Auth::currentSiteId()===(int)$site['id']?'selected':''?>><?=e($site['code'].' — '.$site['name'])?></option><?php endforeach;?></select></label>
<label><span>Destination *</span><select name="destination_site_id" required><option value="">Choisir…</option><?php foreach($sites as $site):?><option value="<?=e($site['id'])?>"><?=e($site['code'].' — '.$site['name'])?></option><?php endforeach;?></select></label>
<label><span>Lot *</span><select name="finished_stock_id" required aria-describedby="transfer-stock-help"><option value="">Choisir un lot…</option><?php foreach($stocks as $stock):if(!Auth::canAccessSite($stock['site_id']))continue;?><option value="<?=e($stock['id'])?>" data-site="<?=e($stock['site_id'])?>" data-stock-kind="<?=e(($stock['product_id']??'').':'.($stock['bag_format_id']??''))?>" data-available="<?=max(0,(int)$stock['quantity_bags']-(int)$stock['reserved_bags'])?>" data-physical="<?=e($stock['quantity_bags'])?>" data-reserved="<?=e($stock['reserved_bags'])?>" data-product="<?=e($stock['product_name'])?>" data-format="<?=e($stock['format_name'])?>"><?=e('#'.$stock['id'].' · '.$stock['product_name'].' · '.$stock['format_name'])?></option><?php endforeach;?></select></label>
</div>
<p id="transfer-stock-help" class="butchery-detail-note" data-transfer-help aria-live="polite" hidden></p>
<div class="transfer-stock-preview" data-transfer-preview hidden aria-live="polite"><dl class="butchery-history-details"><div><dt>Disponible</dt><dd data-transfer-available></dd></div><div><dt>Solde prévu</dt><dd data-transfer-balance></dd></div></dl></div>
<div class="form-grid"><label><span>Quantité (sacs) *</span><input name="quantity_bags" type="number" min="1" step="1" required placeholder="Ex. 20" inputmode="numeric"></label></div><details class="transfer-optional"><summary>Ajouter une note</summary><label><span class="sr-only">Note facultative</span><textarea name="notes" rows="2" placeholder="Votre note…"></textarea></label></details>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary">Créer la demande</button></footer>
</form>
