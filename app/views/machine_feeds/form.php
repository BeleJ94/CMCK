<form method="post" action="<?=e(base_url('machine-feeds'))?>" class="enterprise-form" data-validate data-feed-form data-confirm="Confirmez le chargement : le stock du silo sera déduit, un BSS validé sera émis et un lot de production sera démarré.">
<?=csrf_field()?>
<div class="feed-form-body">
<div class="app-alert app-alert-error" data-feed-error role="alert" <?=empty($errors)?'hidden':''?>><?=e(implode(' ', $errors??[]))?></div>
<?php if(!$silos||!$machines):?><p class="app-alert app-alert-error">Aucune <?=!$machines?'machine principale active sur ce périmètre. Sélectionnez un site de production dans le sélecteur de sites.':'source silo active accessible. Vérifiez les silos et vos accès.'?></p><?php endif;?>
<div class="form-grid">
<label><span>Silo source *</span><select name="silo_id" required><option value="">Choisir le silo</option><?php foreach($silos as$s):?><option value="<?=e($s['id'])?>" data-stock="<?=e($s['current_stock_kg'])?>" data-product="<?=e($s['product_name'])?>" <?=(string)$feed['silo_id']===(string)$s['id']?'selected':''?>><?=e($s['name'].' · '.number_format((float)$s['current_stock_kg'],3,',',' ').' kg')?></option><?php endforeach;?></select></label>
<label><span>Machine destinataire *</span><select name="machine_id" required><option value="">Choisir la machine</option><?php foreach($machines as$m):?><option value="<?=e($m['id'])?>" data-site="<?=e($sites[$m['site_id']]['name']??'')?>" <?=(string)$feed['machine_id']===(string)$m['id']?'selected':''?>><?=e($m['name'].' · '.($sites[$m['site_id']]['name']??''))?></option><?php endforeach;?></select><small>Le site de production est celui de la machine.</small></label>
<aside class="feed-context form-wide" data-feed-context hidden aria-live="polite"></aside>
<label><span>Quantité autorisée sur le BSS (kg) *</span><input type="number" name="authorized_quantity_kg" min="0.001" max="999999999.999" step="0.001" value="<?=e($feed['authorized_quantity_kg'])?>" required></label>
<label><span>Quantité réellement chargée (kg) *</span><input type="number" name="quantity_kg" min="0.001" max="999999999.999" step="0.001" value="<?=e($feed['quantity_kg'])?>" required><small>Seule cette quantité est déduite du silo.</small></label>
<label><span>Date et heure de début *</span><input type="datetime-local" name="fed_at" step="60" value="<?=e($feed['fed_at'])?>" required></label>
<label><span>Date et heure de fin</span><input type="datetime-local" name="ended_at" step="60" value="<?=e($feed['ended_at'])?>"><small>Facultatif si le chargement n’est pas terminé.</small></label>
<label class="form-wide"><span>Observation</span><textarea name="observation" rows="3" maxlength="5000" placeholder="Précision utile sur ce chargement…"><?=e($feed['observation'])?></textarea></label>
</div><p class="feed-agent">Enregistré par <?=e(Auth::user()['name']??'')?> · * Champs obligatoires</p>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary" <?=!$silos||!$machines?'disabled':''?>>Créer l’alimentation</button></footer>
</form>
