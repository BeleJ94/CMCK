<section id="packagingEditor" class="entity-modal workspace-entity-modal feed-editor packaging-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="packagingEditorTitle">
<header><div><p class="page-kicker">CONDITIONNEMENT</p><h2 id="packagingEditorTitle"><i class="bi bi-bag-plus"></i> Nouvel emballage</h2></div><button type="button" class="modal-close" data-workspace-modal-close aria-label="Fermer">×</button></header>
    <form method="post" action="<?= e(base_url('packaging')) ?>" class="enterprise-form" data-validate data-packaging-form data-pack-editor-form data-confirm="Confirmer le conditionnement ? La farine et les sacs vides de l’atelier seront consommés, et les sacs remplis seront ajoutés au stock fini.">
        <?= csrf_field() ?>
        <div class="feed-form-body"><p class="app-alert app-alert-error" data-feed-error hidden role="alert"></p><p class="pack-help">Choisissez un lot, puis le format et le nombre de sacs. Les sacs vides doivent avoir été délivrés à l’atelier.</p><?php if (!$availableBatches): ?><p class="app-alert">Aucun lot disponible. Validez une production avant de créer un conditionnement.</p><?php endif; ?>
<script type="application/json" data-pack-stocks><?= json_encode($workshopStocks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="form-grid">
            <label class="form-wide">
                <span>Lot de production *</span>
                <select name="production_batch_id" required data-packaging-batch <?= empty($availableBatches) ? 'disabled' : '' ?>>
                    <option value="">Sélectionner un lot</option>
                    <?php foreach ($availableBatches as $batch): ?>
                        <option
                            value="<?= e($batch['id']) ?>"
                            data-site="<?= e($batch['site_id']) ?>" data-code="<?= e($batch['product_code']) ?>" data-product="<?= e($batch['product_name']) ?>"
                            data-available="<?= e($batch['available_quantity_kg']) ?>"
                            <?= (string) $packaging['production_batch_id'] === (string) $batch['id'] ? 'selected' : '' ?>
                        >
                            <?= e($batch['batch_number'] . ' - ' . $batch['product_name'] . ' - disponible ' . number_format((float) $batch['available_quantity_kg'], 0, ',', ' ') . ' kg') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['production_batch_id'])): ?><small><?= e($errors['production_batch_id']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Produit</span>
                <input type="text" value="" disabled data-packaging-product>
            </label>
            <label>
                <span>Quantité disponible</span>
                <input type="text" value="" disabled data-packaging-available>
            </label>
            <label>
                <span>Format de sac *</span>
                <select name="packaging_item_id" required data-bag-format>
                    <option value="">Sélectionner un format</option>
                    <?php foreach ($bagFormats as $format): ?>
                        <option value="<?= e($format['packaging_item_id']) ?>" data-target="<?= e($format['target_product_code']) ?>" data-weight="<?= e($format['weight_kg']) ?>" <?= (string) $packaging['packaging_item_id'] === (string) $format['packaging_item_id'] ? 'selected' : '' ?>>
                            <?= e($format['code'].' — '.$format['name'] . ' - ' . number_format((float) $format['weight_kg'], 0, ',', ' ') . ' kg') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['packaging_item_id'])): ?><small><?= e($errors['packaging_item_id']) ?></small><?php endif; ?>
            </label>
            <div class="form-wide pack-stock-context" aria-live="polite"><p data-pack-stock-message>Sélectionnez un lot et un format pour connaître le stock de sacs.</p><a href="<?= e(base_url('empty-packaging')) ?>" target="_blank" rel="noopener">Gérer les sacs vides <i class="bi bi-box-arrow-up-right"></i> (nouvel onglet)</a><button type="button" class="btn-secondary" data-pack-refresh>Actualiser les stocks</button><small>Le magasin stocke les sacs achetés. Seuls les sacs délivrés à l’atelier peuvent être consommés ici.</small></div>
            <label>
                <span>Nombre de sacs *</span>
                <input type="number" name="bags_count" min="1" step="1" value="<?= e($packaging['bags_count']) ?>" required data-bags-count>
                <?php if (!empty($errors['bags_count'])): ?><small><?= e($errors['bags_count']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Poids total calculé</span>
                <input type="text" value="" disabled data-packaging-total>
            </label>
            <label>
                <span>Date et heure *</span>
                <input type="datetime-local" name="packaged_at" value="<?= e($packaging['packaged_at']) ?>" required>
                <?php if (!empty($errors['packaged_at'])): ?><small><?= e($errors['packaged_at']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Agent</span>
                <input type="text" value="<?= e(Auth::user()['name'] ?? '') ?>" disabled>
            </label>
        </div>
        <p class="pack-summary" data-pack-summary aria-live="polite">Sélectionnez un lot et un format pour préparer le conditionnement.</p></div><footer>
            <button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button>
            <button type="submit" class="btn-primary" <?= empty($availableBatches) ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i><span>Enregistrer le conditionnement</span></button>
        </footer>
    </form>

</section>
