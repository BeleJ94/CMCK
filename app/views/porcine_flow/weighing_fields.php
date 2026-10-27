<?php
// Shared weighing input: net weight, or gross minus a tare (ice, crates, hooks…).
// Expects $weighingId and $weighingLabel; $weighingSent (kg) and $weighingTolerance (% or null) enable the live transit gap;
// $weighingOptional leaves the weight blank when the animals were not weighed; $weighingTareHint and $weighingTareOptions describe the tare.
// $weighingPieces (count sent) and $weighingPiecesLabel add the count received, for goods counted as well as weighed.
$weighingPieces=$weighingPieces??null;$weighingPiecesLabel=$weighingPiecesLabel??'Nombre de pièces reçues';
$weighingSent=$weighingSent??null;$weighingTolerance=$weighingTolerance??null;$weighingOptional=!empty($weighingOptional);
$weighingTareHint=$weighingTareHint??'Avec glace, caisses ou crochets';$weighingTareOptions=$weighingTareOptions??['Glace','Caisses','Glace et caisses','Crochets','Bacs'];$weighingKg=static fn($v)=>number_format((float)$v,3,',',' ');
?>
<fieldset class="form-wide weighing-fields" data-weighing<?=$weighingOptional?' data-weighing-optional':''?><?=$weighingSent!==null?' data-weighing-sent="'.e($weighingSent).'" data-weighing-tolerance="'.e($weighingTolerance===null?'':$weighingTolerance).'"':''?>>
<legend><?=e($weighingLabel)?><?=$weighingOptional?' <small>(facultatif)</small>':' *'?></legend>
<?php if($weighingPieces!==null): ?><div class="weighing-pieces"><label><span><?=e($weighingPiecesLabel)?> *</span><input type="number" name="received_pieces" min="1" max="<?=(int)$weighingPieces?>" step="1" inputmode="numeric" required data-sent="<?=(int)$weighingPieces?>"></label><p data-weighing-pieces-note aria-live="polite">Envoi de la ferme : <?=e(number_format($weighingPieces,0,',',' '))?>.</p></div><?php endif; ?>
<div class="weighing-mode" role="radiogroup" aria-label="Mode de pesée">
<label><input type="radio" name="weighing_mode" value="net" checked><span><strong><i class="bi bi-speedometer2" aria-hidden="true"></i> Poids net</strong><small>Pesé sans contenant</small></span></label>
<label><input type="radio" name="weighing_mode" value="gross"><span><strong><i class="bi bi-box-seam" aria-hidden="true"></i> Brut − tare</strong><small><?=e($weighingTareHint)?></small></span></label>
</div>
<div class="weighing-inputs">
<label data-weighing-net><span>Poids net (kg)<?=$weighingOptional?'':' *'?></span><input type="number" name="net_kg" min="0.001" step="0.001" inputmode="decimal"<?=$weighingOptional?'':' required'?>></label>
<label data-weighing-gross hidden><span>Poids brut (kg) *</span><input type="number" name="gross_kg" min="0.001" step="0.001" inputmode="decimal" disabled></label>
<label data-weighing-gross hidden><span>Tare (kg) *</span><input type="number" name="tare_kg" min="0" step="0.001" inputmode="decimal" disabled></label>
<label data-weighing-gross hidden><span>Nature de la tare</span><input name="tare_note" maxlength="120" list="<?=e($weighingId)?>-tares" placeholder="<?=e('Ex. '.mb_strtolower($weighingTareOptions[0]))?>" disabled><datalist id="<?=e($weighingId)?>-tares"><?php foreach($weighingTareOptions as $tareOption): ?><option value="<?=e($tareOption)?>"><?php endforeach; ?></datalist></label>
</div>
<p class="weighing-result" data-weighing-result aria-live="polite" hidden></p>
<?php if($weighingSent!==null): ?>
<div class="weighing-gap" data-weighing-gap aria-live="polite"><div><span>Envoyé par la ferme</span><strong><?=$weighingKg($weighingSent)?> kg</strong></div><div><span>Écart</span><strong data-weighing-gap-value>—</strong></div><p data-weighing-gap-status><?=$weighingTolerance===null?'Aucune tolérance paramétrée pour cette espèce : tout écart devra être justifié.':'Tolérance : '.e(number_format((float)$weighingTolerance,2,',',' ')).' %.'?></p></div>
<label class="weighing-reason" data-weighing-reason hidden><span>Motif de l’écart *</span><textarea name="gap_reason" maxlength="500" rows="2" placeholder="<?=e($weighingPieces!==null?'Ex. mortalité pendant le transport, carcasse écartée, égouttage':'Ex. sang, égouttage, glace fondue pendant le transport')?>" disabled></textarea></label>
<?php endif; ?>
</fieldset>
<?php unset($weighingOptional,$weighingTareHint,$weighingTareOptions,$weighingPieces,$weighingPiecesLabel); ?>
