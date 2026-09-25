<form method="post" action="<?=e(base_url('cancellations'))?>" data-cancellation-form data-cancellation-create data-native-submit>
<?=csrf_field()?>
<div class="feed-form-body"><p class="app-alert app-alert-error" data-cancellation-error role="alert" hidden></p>
<?php if(!$policies):?><p class="butchery-empty">Aucun type d’opération disponible pour une annulation.</p><?php endif;?>
<div class="form-grid">
<label><span>Opération *</span><select name="entity_type" required><option value="">Choisir le type…</option><?php foreach($policies as $p):?><option value="<?=e($p['entity_type'])?>" data-policy="<?=e(json_encode(['label'=>$p['label'],'stock'=>$p['stock_effects'],'blocking'=>$p['blocking_dependencies'],'documents'=>$p['document_types']?:'Aucun document indiqué','approval'=>(bool)$p['requires_approval_after_validation']],JSON_UNESCAPED_UNICODE))?>"><?=e($p['label'])?></option><?php endforeach;?></select></label>
<label><span>Rechercher l’opération</span><input type="search" data-cancellation-search placeholder="Référence du bon ou site…" disabled></label>
<label class="field-wide"><span>Opération à annuler *</span><select name="entity_id" required disabled data-operations-url="<?=e(base_url('cancellations/operations'))?>"><option value="">Choisissez d’abord le type</option></select><small data-operation-status role="status">Les contrôles métier restent appliqués à la soumission.</small></label>
</div>
<div class="cancellation-preview" data-cancellation-preview hidden><strong data-cancellation-target aria-live="polite"></strong><p class="butchery-detail-note">Les informations de cette opération seront contrôlées à la soumission.</p><details><summary>Effets et conditions</summary><dl><dt>Effet sur le stock</dt><dd data-policy-stock></dd><dt>Dépendances bloquantes</dt><dd data-policy-blocking></dd><dt>Documents concernés</dt><dd data-policy-documents></dd></dl></details></div>
<label class="cancellation-reason"><span>Motif *</span><textarea name="reason" minlength="5" required rows="3" placeholder="Expliquez l’erreur et pourquoi l’opération doit être annulée." aria-describedby="cancellation-reason-help"></textarea><small id="cancellation-reason-help">Ce motif sera conservé dans l’historique.</small></label>
<p class="butchery-detail-note">Une opération non validée peut être annulée immédiatement. Une opération validée nécessite l’approbation d’une autre personne.</p>
</div><footer><button type="button" class="btn-secondary" data-workspace-modal-close>Fermer</button><button type="submit" class="btn-primary" <?=!$policies?'disabled':''?>>Soumettre la demande</button></footer>
</form>
