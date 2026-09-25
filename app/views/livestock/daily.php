<?php
$dailyLabels=['feedings'=>'Alimentation','weighings'=>'Pesée de suivi','poultry'=>'Suivi avicole'];
$dailyNumber=static fn($n)=>number_format((float)$n,3,',',' ');
$dailyLots=array_map(static function($b){$b['can_update']=Auth::can('livestock','update',(int)$b['site_id']);return $b;},$batches);
?>
<section data-livestock-space="daily" class="form-panel daily-workspace">
<div class="daily-selectors">
<label>Site<select data-daily-site><option value="">Choisir un site</option><?php foreach(Auth::sites() as $site): if(!in_array($site['code'],['FARM-MUT','FARM-DIK'],true)||(Auth::currentSiteId()&&Auth::currentSiteId()!==(int)$site['id']))continue; ?><option value="<?=e($site['id'])?>" <?=Auth::currentSiteId()===(int)$site['id']?'selected':''?>><?=e($site['name'])?></option><?php endforeach; ?></select></label>
<label>Lot à suivre<select data-daily-lot><option value="">Choisir un lot</option></select></label>
</div>
<p class="daily-context" data-daily-context aria-live="polite">Sélectionnez un site et un lot pour consulter son suivi.</p>
<div class="daily-actions" data-daily-actions hidden>
<button type="button" class="btn-secondary" data-daily-action="feedings" data-workspace-modal-open="livestock-feedings"><i class="bi bi-basket" aria-hidden="true"></i> Alimenter</button>
<button type="button" class="btn-secondary" data-daily-action="weighings" data-workspace-modal-open="livestock-weighings"><i class="bi bi-speedometer2" aria-hidden="true"></i> Peser</button>
<button type="button" class="btn-secondary" data-daily-action="poultry" data-workspace-modal-open="livestock-poultry"><i class="bi bi-egg" aria-hidden="true"></i> Suivi avicole</button>
</div>
<div data-daily-history hidden><h3>Historique du lot</h3>
<table class="enterprise-table" data-daily-table data-search-placeholder="Rechercher dans ce lot"><thead><tr><th>Date</th><th>Opération</th><th>Résultat enregistré</th><th>Auteur</th><th>Actions</th></tr></thead><tbody>
<?php foreach($dailyHistory as $event):
$key=$event['kind'].'-'.$event['id'];
if($event['kind']==='feedings')$result=$dailyNumber($event['quantity_kg']).' kg distribués';
elseif($event['kind']==='weighings')$result=$dailyNumber($event['average_weight_kg']).' kg / animal';
else $result=$event['eggs_count'].' œufs · '.$event['mortality_heads'].' décès · '.$event['sold_heads'].' vendus';
$dailyHistoryDetails[$key]=[$event,$result];
?><tr data-batch="<?=e($event['batch_id'])?>"><td><?=e(date($event['kind']==='poultry'?'d/m/Y':'d/m/Y H:i',strtotime($event['event_at'])))?></td><td><?=e($dailyLabels[$event['kind']])?></td><td><?=e($result)?></td><td><?=e($event['author']??'—')?></td><td><button type="button" class="btn-secondary" data-workspace-modal-open="daily-event-<?=e($key)?>"><i class="bi bi-eye" aria-hidden="true"></i> Consulter</button></td></tr><?php endforeach; ?>
</tbody></table></div>
<script type="application/json" data-daily-lots><?=json_encode($dailyLots,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
</section>
<?php foreach($dailyHistoryDetails??[] as $key=>[$event,$result]): ?>
<section id="daily-event-<?=e($key)?>" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="daily-title-<?=e($key)?>">
<header><div><p class="page-kicker">SUIVI QUOTIDIEN</p><h2 id="daily-title-<?=e($key)?>"><?=e($dailyLabels[$event['kind']])?></h2></div><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header>
<div class="feed-form-body"><dl class="conversion-facts"><div><dt>Lot</dt><dd><?=e($event['batch_number'])?></dd></div><div><dt>Date</dt><dd><?=e(date($event['kind']==='poultry'?'d/m/Y':'d/m/Y H:i',strtotime($event['event_at'])))?></dd></div><div><dt>Enregistré par</dt><dd><?=e($event['author']??'—')?></dd></div><div><dt>Résultat</dt><dd><?=e($result)?></dd></div></dl>
<?php if($event['kind']==='feedings'): ?><p class="livestock-guide">Quantité distribuée au lot. Cette saisie ne prélève pas automatiquement le stock de produits finis.</p>
<?php elseif($event['kind']==='weighings'): ?><p class="livestock-guide"><?=e($event['sample_heads'])?> animaux pesés · poids total : <?=e($dailyNumber($event['total_weight_kg']))?> kg. Cette pesée ne modifie pas l’effectif.</p>
<?php else: ?><p class="livestock-guide"><?=e((int)$event['mortality_heads']+(int)$event['sold_heads'])?> volailles retirées de l’effectif lors de cet enregistrement.</p><?php endif; ?>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button></footer>
</section><?php endforeach; ?>
