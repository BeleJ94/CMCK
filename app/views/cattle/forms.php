<?php $flowViews=dirname(__DIR__).'/porcine_flow'; ?>
<?php if($section==='lots'&&$canReceive): $drawerId='cattle-receive';$drawerTitle='Réceptionner des bœufs';require $flowViews.'/drawer_start.php';$action='livestock/cattle/receipts';$confirm='Enregistrer cette réception ? Un nouveau lot de bovins est créé dans le bâtiment.';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Une réception correspond à une livraison d’un fournisseur. Elle crée un lot de bovins dans le bâtiment choisi.</p>
<div class="form-grid" data-cattle-receive-form>
<label><span>Ferme *</span><select name="site_id" required><?php foreach($farms as $farm): ?><option value="<?=e($farm['id'])?>"<?=Auth::currentSiteId()===(int)$farm['id']?' selected':''?>><?=e($farm['name'])?></option><?php endforeach; ?></select></label>
<label><span>Fournisseur *</span><select name="supplier_id" required><option value="">Choisir le fournisseur</option><?php foreach($suppliers as $supplier): ?><option value="<?=e($supplier['id'])?>"><?=e($supplier['name'])?></option><?php endforeach; ?></select><small><a href="<?=e(base_url('suppliers'))?>">Gérer les fournisseurs</a></small></label>
<label><span>Bâtiment *</span><select name="facility_id" required><option value="">Choisir le bâtiment</option><?php foreach($facilities as $f):$free=$f['capacity_heads']===null?null:max(0,(int)$f['capacity_heads']-(int)$f['occupied_heads']); ?><option value="<?=e($f['id'])?>" data-site="<?=e($f['site_id'])?>" data-free="<?=$free===null?'':(int)$free?>"><?=e($f['name'].($free===null?'':' · '.$free.' place(s) libre(s)'))?></option><?php endforeach; ?></select></label>
<label><span>Date et heure d’arrivée *</span><input name="received_at" type="datetime-local" value="<?=e(date('Y-m-d\TH:i'))?>" max="<?=e(date('Y-m-d\TH:i',strtotime('+5 minutes')))?>" required></label>
<label><span>Nombre de bovins *</span><input name="heads" type="number" min="1" step="1" inputmode="numeric" required></label>
<label><span>Bon de livraison</span><input name="delivery_reference" maxlength="120" placeholder="Référence du fournisseur"></label>
<?php $weighingId='cattle-receive-weight';$weighingLabel='Poids vif à l’arrivée';$weighingOptional=true;$weighingTareHint='Sur pont-bascule : camion ou bétaillère';$weighingTareOptions=['Camion','Bétaillère','Camion et bétaillère'];require $flowViews.'/weighing_fields.php'; ?>
<label class="form-wide"><span>Observations</span><textarea name="notes" maxlength="500" rows="2" placeholder="État des animaux, documents sanitaires, remarques du transporteur (facultatif)"></textarea></label>
<p class="livestock-guide form-wide" data-cattle-receive-note aria-live="polite">Choisissez le bâtiment pour voir les places disponibles.</p>
</div>
<?php $submitLabel='Enregistrer la réception';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>

<?php if($slaughterable): $drawerId='cattle-slaughter';$drawerTitle='Enregistrer un abattage';require $flowViews.'/drawer_start.php';$action='livestock/cattle/slaughters';$confirm='Enregistrer cet abattage pour validation ?';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Le poids demandé est celui des carcasses obtenues après abattage. Un autre utilisateur autorisé valide ensuite l’abattage.</p>
<div class="form-grid" data-cattle-slaughter-form data-shelf-life="<?=(int)$shelfLifeDays?>">
<label class="form-wide"><span>Lot abattu *</span><select name="batch_id" required><option value="">Choisir le lot</option><?php foreach($slaughterable as $l): ?><option value="<?=e($l['id'])?>" data-available="<?=(int)$l['available_heads']?>" data-started="<?=e($l['started_at'])?>" data-average="<?=e((string)$l['average_live_kg'])?>"><?=e(($l['supplier_name']?:'Lot antérieur').' · '.$l['facility_name'].' — '.$l['batch_number'].' · '.$int($l['available_heads']).' disponible(s)')?></option><?php endforeach; ?></select></label>
<label><span>Bovins abattus *</span><input name="heads" type="number" min="1" step="1" inputmode="numeric" required></label>
<label><span>Date et heure de l’abattage *</span><input name="slaughtered_at" type="datetime-local" value="<?=e(date('Y-m-d\TH:i'))?>" max="<?=e(date('Y-m-d\TH:i',strtotime('+5 minutes')))?>" required></label>
<?php $weighingId='cattle-slaughter-weight';$weighingLabel='Poids des carcasses';$weighingTareHint='Avec crochets ou bacs';$weighingTareOptions=['Crochets','Bacs','Crochets et bacs'];require $flowViews.'/weighing_fields.php'; ?>
<label><span>DLC des carcasses *</span><input name="expiry_date" type="date" min="<?=e(date('Y-m-d'))?>" value="<?=e(date('Y-m-d',strtotime('+'.(int)$shelfLifeDays.' days')))?>" required><small>Proposée à <?=(int)$shelfLifeDays?> jours après l’abattage.</small></label>
<p class="livestock-guide form-wide" data-cattle-slaughter-note aria-live="polite">Choisissez le lot pour voir les bovins disponibles.</p>
</div>
<?php $submitLabel='Enregistrer pour validation';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>

<?php if($section==='slaughters'): $stocks=array_values(array_filter($slaughters,static fn($s)=>$s['status']==='validated'&&$s['weight_stock_id']&&in_array($s['stock_status'],['available','reserved'],true)&&(float)$s['stock_kg']>(float)$s['stock_reserved_kg']&&$s['expiry_date']>=date('Y-m-d')&&Auth::can('livestock','create',(int)$s['site_id']))); ?>
<?php if($stocks): $drawerId='cattle-shipment';$drawerTitle='Préparer un envoi de carcasses';require $flowViews.'/drawer_start.php';$action='livestock/cattle/shipments';$confirm='Préparer cet envoi pour approbation ?';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Le poids envoyé est celui pesé à la ferme. La boucherie pèsera de nouveau les carcasses à leur arrivée.</p>
<div class="form-grid" data-cattle-shipment-form><label class="form-wide"><span>Carcasses à envoyer *</span><select name="weight_stock_id" required><option value="">Choisir le stock de carcasses</option><?php foreach($stocks as $s):$availableKg=round((float)$s['stock_kg']-(float)$s['stock_reserved_kg'],3); ?><option value="<?=e($s['weight_stock_id'])?>" data-max="<?=e($availableKg)?>"><?=e($s['conversion_number'].' · '.$kg($availableKg).' kg disponibles · DLC '.date('d/m/Y',strtotime($s['expiry_date'])))?></option><?php endforeach; ?></select></label>
<label><span>Quantité à envoyer (kg) *</span><input name="quantity_kg" type="number" min="0.001" step="0.001" inputmode="decimal" required></label><label><span>Immatriculation *</span><input name="truck_plate" maxlength="40" required></label><label><span>Chauffeur *</span><input name="driver_name" maxlength="150" required></label></div>
<?php $submitLabel='Préparer l’envoi';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>
<?php endif; ?>
<div class="entity-modal-backdrop workspace-modal-backdrop" data-workspace-modal-close></div>
