<?php $inlineForm=true;$confirm='Annuler cette opération en attente en conservant son historique ?';require __DIR__.'/form_start.php'; ?>
<label><span>Motif d’annulation *</span><input name="reason" maxlength="500" required></label>
<?php $submitLabel='Annuler cette opération';$destructiveSubmit=true;require __DIR__.'/form_end.php';unset($inlineForm,$destructiveSubmit); ?>
