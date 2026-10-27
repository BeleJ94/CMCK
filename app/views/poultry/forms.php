<?php $flowViews=dirname(__DIR__).'/porcine_flow';$nowInput=date('Y-m-d\TH:i');$maxInput=date('Y-m-d\TH:i',strtotime('+5 minutes'));
$houseOptions=static function(array $houses){foreach($houses as $f){$free=$f['capacity_heads']===null?null:max(0,(int)$f['capacity_heads']-(int)$f['occupied_heads']);echo '<option value="'.e($f['id']).'" data-site="'.e($f['site_id']).'" data-free="'.($free===null?'':(int)$free).'">'.e($f['name'].($free===null?'':' · '.number_format($free,0,',',' ').' place(s) libre(s)')).'</option>';}};
$lotLabel=static fn($l)=>$l['facility_name'].' · '.($l['flock_type']==='layer'?'Pondeuses':'Chair').' — '.$l['batch_number'].' · '.number_format($l['available_heads'],0,',',' ').' disponible(s)';
?>
<?php if($section==='lots'&&$canReceive): $drawerId='poultry-receive';$drawerTitle='Réceptionner des volailles';require $flowViews.'/drawer_start.php';$action='livestock/poultry/receipts';$confirm='Enregistrer cette réception ? Un nouveau lot de volailles est créé dans le poulailler.';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Une réception correspond à une livraison d’un fournisseur : poussins d’un jour pour la chair, ou poules pondeuses.</p>
<div class="form-grid" data-poultry-receive-form>
<fieldset class="form-wide poultry-type-choice" role="radiogroup" aria-label="Type de volailles"><legend>Type de volailles *</legend>
<label><input type="radio" name="flock_type" value="broiler" checked required><span><strong><i class="bi bi-egg" aria-hidden="true"></i> Poulets de chair</strong><small>Élevés pour l’abattage ou la vente sur pied</small></span></label>
<label><input type="radio" name="flock_type" value="layer"><span><strong><i class="bi bi-egg-fill" aria-hidden="true"></i> Pondeuses</strong><small>Œufs destinés à la reproduction</small></span></label>
</fieldset>
<label><span>Ferme *</span><select name="site_id" required><?php foreach($farms as $farm): ?><option value="<?=e($farm['id'])?>"<?=Auth::currentSiteId()===(int)$farm['id']?' selected':''?>><?=e($farm['name'])?></option><?php endforeach; ?></select></label>
<label><span>Fournisseur *</span><select name="supplier_id" required><option value="">Choisir le fournisseur</option><?php foreach($suppliers as $supplier): ?><option value="<?=e($supplier['id'])?>"><?=e($supplier['name'])?></option><?php endforeach; ?></select><small><a href="<?=e(base_url('suppliers'))?>">Gérer les fournisseurs</a></small></label>
<label><span>Poulailler *</span><select name="facility_id" required><option value="">Choisir le poulailler</option><?php $houseOptions($houses); ?></select></label>
<label><span>Date et heure d’arrivée *</span><input name="received_at" type="datetime-local" value="<?=e($nowInput)?>" max="<?=e($maxInput)?>" required></label>
<label><span>Nombre d’oiseaux *</span><input name="heads" type="number" min="1" step="1" inputmode="numeric" required></label>
<label><span>Âge à l’arrivée (jours)</span><input name="age_days" type="number" min="0" max="3650" step="1" inputmode="numeric" placeholder="Ex. 1 pour des poussins d’un jour"></label>
<label><span>Bon de livraison</span><input name="delivery_reference" maxlength="120" placeholder="Référence du fournisseur"></label>
<?php $weighingId='poultry-receive-weight';$weighingLabel='Poids vif à l’arrivée';$weighingOptional=true;$weighingTareHint='Avec cages ou caisses de transport';$weighingTareOptions=['Cages','Caisses','Camion'];require $flowViews.'/weighing_fields.php'; ?>
<label class="form-wide"><span>Observations</span><textarea name="notes" maxlength="500" rows="2" placeholder="État des oiseaux, vaccinations, remarques du transporteur (facultatif)"></textarea></label>
<p class="livestock-guide form-wide" data-poultry-receive-note aria-live="polite">Choisissez le poulailler pour voir les places disponibles.</p>
</div>
<?php $submitLabel='Enregistrer la réception';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>

<?php if($section==='lots'&&$mothers&&$houses): $drawerId='poultry-hatch';$drawerTitle='Enregistrer une éclosion';require $flowViews.'/drawer_start.php';$action='livestock/poultry/hatchings';$confirm='Enregistrer cette éclosion ? Les poussins forment un nouveau lot de poulets de chair.';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Les œufs des pondeuses servent à la reproduction. Les poussins éclos forment un nouveau lot de poulets de chair, rattaché à leurs pondeuses.</p>
<div class="form-grid" data-poultry-hatch-form>
<label class="form-wide"><span>Pondeuses d’origine *</span><select name="mother_batch_id" required><option value="">Choisir le lot de pondeuses</option><?php foreach($mothers as $l): ?><option value="<?=e($l['id'])?>" data-site="<?=e($l['site_id'])?>" data-started="<?=e($l['started_at'])?>"><?=e($l['facility_name'].' — '.$l['batch_number'].' · '.number_format((int)$l['eggs'],0,',',' ').' œuf(s) ramassé(s)')?></option><?php endforeach; ?></select></label>
<label><span>Poulailler des poussins *</span><select name="facility_id" required><option value="">Choisir le poulailler</option><?php $houseOptions($houses); ?></select></label>
<label><span>Date et heure d’éclosion *</span><input name="hatched_at" type="datetime-local" value="<?=e($nowInput)?>" max="<?=e($maxInput)?>" required></label>
<label><span>Poussins éclos *</span><input name="heads" type="number" min="1" step="1" inputmode="numeric" required></label>
<label class="form-wide"><span>Observations</span><textarea name="notes" maxlength="500" rows="2" placeholder="Taux d’éclosion, état des poussins (facultatif)"></textarea></label>
<p class="livestock-guide form-wide" data-poultry-hatch-note aria-live="polite">Choisissez les pondeuses puis le poulailler des poussins, sur la même ferme.</p>
</div>
<?php $submitLabel='Enregistrer l’éclosion';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>

<?php if($section==='lots'&&$recordable): $drawerId='poultry-record';$drawerTitle='Saisie du jour';require $flowViews.'/drawer_start.php';$action='livestock/poultry/records';$confirm='Enregistrer cette saisie ? Les oiseaux morts sont retirés du lot.';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Saisissez les œufs ramassés (pondeuses) et les oiseaux morts. Vous pouvez faire plusieurs saisies dans la journée : elles s’additionnent.</p>
<div class="form-grid" data-poultry-record-form>
<label class="form-wide"><span>Lot *</span><select name="batch_id" required><option value="">Choisir le lot</option><?php foreach($recordable as $l): ?><option value="<?=e($l['id'])?>" data-type="<?=e($l['flock_type'])?>" data-available="<?=(int)$l['available_heads']?>" data-started="<?=e($l['started_at'])?>" data-eggs-today="<?=(int)$l['eggs_today']?>" data-dead-today="<?=(int)$l['dead_today']?>"><?=e($l['facility_name'].' · '.($l['flock_type']==='layer'?'Pondeuses':'Chair').' — '.$l['batch_number'].' · '.number_format((int)$l['current_heads'],0,',',' ').' oiseau(x)')?></option><?php endforeach; ?></select></label>
<label><span>Date *</span><input name="recorded_on" type="date" value="<?=e(date('Y-m-d'))?>" max="<?=e(date('Y-m-d'))?>" required></label>
<label data-poultry-eggs><span>Œufs ramassés</span><input name="eggs_count" type="number" min="0" step="1" inputmode="numeric" value="0"></label>
<label><span>Oiseaux morts</span><input name="mortality_heads" type="number" min="0" step="1" inputmode="numeric" value="0"></label>
<label class="form-wide"><span>Observation</span><input name="notes" maxlength="255" placeholder="Ex. chaleur, maladie suspectée, œufs cassés (facultatif)"></label>
<p class="livestock-guide form-wide" data-poultry-record-note aria-live="polite">Choisissez le lot.</p>
</div>
<?php $submitLabel='Enregistrer la saisie';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>

<?php if($slaughterable): $drawerId='poultry-slaughter';$drawerTitle='Enregistrer un abattage';require $flowViews.'/drawer_start.php';$action='livestock/poultry/slaughters';$confirm='Enregistrer cet abattage pour validation ?';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Comptez les carcasses et pesez-les. Les oiseaux trouvés morts lors de l’abattage sont déclarés à part. Un autre utilisateur autorisé valide ensuite l’abattage.</p>
<div class="form-grid" data-poultry-slaughter-form data-shelf-broiler="<?=(int)$shelfLife['broiler']?>" data-shelf-layer="<?=(int)$shelfLife['layer']?>">
<label class="form-wide"><span>Lot abattu *</span><select name="batch_id" required><option value="">Choisir le lot</option><?php foreach($slaughterable as $l): ?><option value="<?=e($l['id'])?>" data-type="<?=e($l['flock_type'])?>" data-available="<?=(int)$l['available_heads']?>" data-started="<?=e($l['started_at'])?>"><?=e($lotLabel($l))?></option><?php endforeach; ?></select></label>
<label><span>Carcasses obtenues *</span><input name="heads" type="number" min="1" step="1" inputmode="numeric" required></label>
<label><span>Oiseaux morts à l’abattage</span><input name="dead_heads" type="number" min="0" step="1" inputmode="numeric" value="0"></label>
<label><span>Date et heure de l’abattage *</span><input name="slaughtered_at" type="datetime-local" value="<?=e($nowInput)?>" max="<?=e($maxInput)?>" required></label>
<label><span>DLC des carcasses *</span><input name="expiry_date" type="date" min="<?=e(date('Y-m-d'))?>" value="<?=e(date('Y-m-d',strtotime('+'.(int)$shelfLife['broiler'].' days')))?>" required><small data-poultry-shelf>Proposée selon l’article de carcasse.</small></label>
<?php $weighingId='poultry-slaughter-weight';$weighingLabel='Poids des carcasses';$weighingTareHint='Avec bacs ou caisses';$weighingTareOptions=['Bacs','Caisses','Crochets'];require $flowViews.'/weighing_fields.php'; ?>
<p class="livestock-guide form-wide" data-poultry-slaughter-note aria-live="polite">Choisissez le lot pour voir les oiseaux disponibles.</p>
</div>
<?php $submitLabel='Enregistrer pour validation';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>

<?php if($liveShippable): $drawerId='poultry-live-shipment';$drawerTitle='Envoyer des poulets vivants';require $flowViews.'/drawer_start.php';$action='livestock/poultry/live-shipments';$confirm='Préparer cet envoi de poulets vivants pour approbation ?';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Les poulets de chair vendus sur pied partent vivants à la boucherie. Comptez-les et pesez-les dans leurs cages : la boucherie les comptera et les pèsera de nouveau.</p>
<div class="form-grid" data-poultry-live-form>
<label class="form-wide"><span>Lot de poulets *</span><select name="batch_id" required><option value="">Choisir le lot</option><?php foreach($liveShippable as $l): ?><option value="<?=e($l['id'])?>" data-available="<?=(int)$l['available_heads']?>"><?=e($lotLabel($l))?></option><?php endforeach; ?></select></label>
<label><span>Poulets envoyés *</span><input name="heads" type="number" min="1" step="1" inputmode="numeric" required></label>
<?php $weighingId='poultry-live-weight';$weighingLabel='Poids vif au départ';$weighingTareHint='Avec les cages de transport';$weighingTareOptions=['Cages','Caisses'];require $flowViews.'/weighing_fields.php'; ?>
<label><span>Immatriculation *</span><input name="truck_plate" maxlength="40" required></label>
<label><span>Chauffeur *</span><input name="driver_name" maxlength="150" required></label>
<p class="livestock-guide form-wide" data-poultry-live-note aria-live="polite">Choisissez le lot pour voir les poulets disponibles.</p>
</div>
<?php $submitLabel='Préparer l’envoi';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>

<?php if($section==='slaughters'): $stocks=array_values(array_filter($slaughters,static fn($s)=>$s['status']==='validated'&&$s['weight_stock_id']&&in_array($s['stock_status'],['available','reserved'],true)&&$s['available_pieces']>0&&$s['available_kg']>0&&$s['expiry_date']>=date('Y-m-d')&&Auth::can('livestock','create',(int)$s['site_id']))); ?>
<?php if($stocks): $drawerId='poultry-shipment';$drawerTitle='Préparer un envoi de carcasses';require $flowViews.'/drawer_start.php';$action='livestock/poultry/shipments';$confirm='Préparer cet envoi pour approbation ?';require $flowViews.'/form_start.php'; ?>
<p class="livestock-guide">Indiquez le nombre de carcasses : le poids est proposé à partir de la pesée de l’abattage. Les dernières carcasses d’un stock emportent tout son poids restant.</p>
<div class="form-grid" data-poultry-shipment-form><label class="form-wide"><span>Carcasses à envoyer *</span><select name="weight_stock_id" required><option value="">Choisir le stock de carcasses</option><?php foreach($stocks as $s): ?><option value="<?=e($s['weight_stock_id'])?>" data-kg="<?=e($s['available_kg'])?>" data-pieces="<?=(int)$s['available_pieces']?>"><?=e($s['item_name'].' · '.$s['conversion_number'].' · '.$s['available_pieces'].' pièce(s), '.$kg($s['available_kg']).' kg · DLC '.date('d/m/Y',strtotime($s['expiry_date'])))?></option><?php endforeach; ?></select></label>
<label><span>Carcasses envoyées *</span><input name="quantity_pieces" type="number" min="1" step="1" inputmode="numeric" required></label>
<label><span>Poids envoyé (kg) *</span><input name="quantity_kg" type="number" min="0.001" step="0.001" inputmode="decimal" required><small data-poultry-shipment-hint>Proposé selon le poids moyen des carcasses.</small></label>
<label><span>Immatriculation *</span><input name="truck_plate" maxlength="40" required></label><label><span>Chauffeur *</span><input name="driver_name" maxlength="150" required></label></div>
<?php $submitLabel='Préparer l’envoi';require $flowViews.'/form_end.php';require $flowViews.'/drawer_end.php';endif; ?>
<?php endif; ?>
<div class="entity-modal-backdrop workspace-modal-backdrop" data-workspace-modal-close></div>
