<?php
// Live poultry received from the farm: sold at the counter by the piece or the kilo, or slaughtered here into carcasses.
$liveLots=array_values(array_filter($finished,static fn($l)=>!empty($l['is_live'])&&(int)$l['quantity_pieces']>0));
$poultrySlaughters=$poultrySlaughters??[];
if(!$liveLots&&!$poultrySlaughters)return;
$canSlaughter=$canOperate&&Auth::can('butchery','create',$site);$canSupply=$canOperate&&Auth::can('butchery','update',$site);
$slaughterable=array_values(array_filter($liveLots,static fn($l)=>in_array($l['status'],['available','reserved'],true)&&$l['expiry_date']>=date('Y-m-d')&&(int)$l['cold_available_pieces']>0));
$partials=dirname(__DIR__).'/partials';$count=static fn($v)=>number_format((int)$v,0,',',' ');
?>
<section class="poultry-live-section compact-page" aria-labelledby="poultry-live-title">
<header class="pig-ref-section-head"><div><h3 id="poultry-live-title">Poulets vivants</h3><p>Reçus vivants de la ferme : vendus au comptoir, ou abattus ici. L’abattage crée des carcasses en chambre froide, à transformer comme les autres.</p></div><?php if($canSlaughter&&$slaughterable):?><button type="button" class="btn-primary" data-workspace-modal-open="poultry-butchery-slaughter"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Abattre des poulets</button><?php endif;?></header>
<?php if($liveLots):?>
<div class="table-responsive"><table class="enterprise-table compact-table" data-datatable="false" aria-label="Lots de poulets vivants"><thead><tr><th>Lot</th><th>Reçu le</th><th class="num">En réserve</th><th class="num">Au comptoir</th><th>À vendre avant</th><th class="cell-actions-head">Actions</th></tr></thead><tbody>
<?php foreach($liveLots as $l):$usable=in_array($l['status'],['available','reserved'],true)&&$l['expiry_date']>=date('Y-m-d');?>
<tr>
<td data-label="Lot" class="cell--lead"><strong class="cell-main"><?=e($l['item_name'])?></strong><small class="cell-ref" title="<?=e($l['lot_number'])?>"><?=e($l['lot_number'])?></small></td>
<td data-label="Reçu le"><span class="cell-main"><?=e(date('d/m/Y',strtotime($l['production_date'])))?></span></td>
<td data-label="En réserve" class="num"><strong><?=$count($l['cold_pieces'])?></strong><small><?=$kg($l['cold_kg'])?> kg</small></td>
<td data-label="Au comptoir" class="num"><strong><?=$count($l['counter_pieces'])?></strong><small><?=$kg($l['counter_kg'])?> kg</small></td>
<td data-label="À vendre avant"><span class="cell-main"><?=e(date('d/m/Y',strtotime($l['expiry_date'])))?></span><?php if(!$usable):?><small class="is-warning">Vente bloquée</small><?php endif;?></td>
<td data-label="Actions" class="cell-actions"><div class="row-actions">
<?php if($canSlaughter&&$usable&&(int)$l['cold_available_pieces']>0):?><button type="button" class="btn-primary row-action-button" data-workspace-modal-open="poultry-butchery-slaughter" data-preselect-field="live_lot_id" data-preselect-value="<?=e($l['id'])?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Abattre</span></button><?php endif;?>
<?php if($canSupply&&$usable&&(int)$l['cold_available_pieces']>0):?><button type="button" class="btn-secondary row-action-button" data-workspace-modal-open="flow-supply" data-preselect-field="finished_lot_id" data-preselect-value="<?=e($l['id'])?>"><i class="bi bi-shop" aria-hidden="true"></i><span>Au comptoir</span></button><?php endif;?>
</div></td></tr>
<?php endforeach;?>
</tbody></table></div>
<?php endif;?>
<?php if($poultrySlaughters):?>
<h4 class="poultry-live-subtitle">Derniers abattages en boucherie</h4>
<div class="table-responsive"><table class="enterprise-table compact-table" data-datatable="false" aria-label="Abattages de poulets à la boucherie"><thead><tr><th>Abattage</th><th class="num">Poulets</th><th class="num">Carcasses</th><th>Lot de carcasses</th><th>Saisi par</th></tr></thead><tbody>
<?php foreach($poultrySlaughters as $s):?>
<tr>
<td data-label="Abattage" class="cell--lead"><strong class="cell-main"><?=e(date('d/m/Y · H:i',strtotime($s['slaughtered_at'])))?></strong><small class="cell-ref"><?=e($s['slaughter_number'])?></small></td>
<td data-label="Poulets" class="num"><strong><?=$count($s['heads'])?></strong><small><?=(int)$s['dead_heads']?$count($s['dead_heads']).' mort(s) · ':''?><?=$kg($s['live_kg'])?> kg vifs</small></td>
<td data-label="Carcasses" class="num"><strong><?=$kg($s['net_weight_kg'])?></strong><small>kg · rendement <?=e(number_format((float)$s['live_kg']>0?(float)$s['net_weight_kg']/(float)$s['live_kg']*100:0,1,',',' '))?> %</small></td>
<td data-label="Lot de carcasses"><span class="cell-main"><?=e($s['raw_lot_number'])?></span><small>DLC <?=e(date('d/m/Y',strtotime($s['expiry_date'])))?></small></td>
<td data-label="Saisi par"><span class="cell-main"><?=e($s['author']?:'—')?></span></td>
</tr>
<?php endforeach;?>
</tbody></table></div>
<?php endif;?>
</section>
<?php if($canSlaughter&&$slaughterable):$drawerId='poultry-butchery-slaughter';$drawerTitle='Abattre des poulets vivants';$drawerKicker='BOUCHERIE · VOLAILLES';require __DIR__.'/drawer_start.php';unset($drawerKicker);$action='butchery/poultry/slaughters';$confirm='Enregistrer cet abattage ? Les carcasses entrent en chambre froide.';require __DIR__.'/form_start.php';?>
<p class="livestock-guide">Les poulets abattus quittent le lot vivant (hors comptoir). Comptez et pesez les carcasses : elles entrent en chambre froide, prêtes à vendre entières ou à découper.</p>
<div class="form-grid" data-poultry-butchery-form data-shelf-life="<?=(int)($carcassShelfLife??4)?>">
<label class="form-wide"><span>Lot de poulets vivants *</span><select name="live_lot_id" required><option value="">Choisir le lot</option><?php foreach($slaughterable as $l):?><option value="<?=e($l['id'])?>" data-available="<?=(int)$l['cold_available_pieces']?>" data-kg="<?=e($l['cold_available_kg'])?>" data-received="<?=e($l['production_date'])?>"><?=e($l['lot_number'].' · '.$count($l['cold_available_pieces']).' poulet(s) hors comptoir')?></option><?php endforeach;?></select></label>
<label><span>Poulets abattus *</span><input name="heads" type="number" min="1" step="1" inputmode="numeric" required></label>
<label><span>Poulets trouvés morts</span><input name="dead_heads" type="number" min="0" step="1" inputmode="numeric" value="0"></label>
<label><span>Date et heure *</span><input name="slaughtered_at" type="datetime-local" value="<?=e(date('Y-m-d\TH:i'))?>" max="<?=e(date('Y-m-d\TH:i',strtotime('+5 minutes')))?>" required></label>
<label><span>DLC des carcasses *</span><input name="expiry_date" type="date" min="<?=e(date('Y-m-d'))?>" value="<?=e(date('Y-m-d',strtotime('+'.(int)($carcassShelfLife??4).' days')))?>" required></label>
<?php $weighingId='poultry-butchery-weight';$weighingLabel='Poids des carcasses';$weighingTareHint='Avec bacs ou caisses';$weighingTareOptions=['Bacs','Caisses'];require __DIR__.'/weighing_fields.php';?>
<p class="livestock-guide form-wide" data-poultry-butchery-note aria-live="polite">Choisissez le lot pour voir les poulets disponibles.</p>
</div>
<?php $submitLabel='Enregistrer l’abattage';require __DIR__.'/form_end.php';require __DIR__.'/drawer_end.php';endif;?>
<?php if($canSupply)require __DIR__.'/location_forms.php';?>
