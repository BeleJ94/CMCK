<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-plus-circle"></i></span>
    <div>
        <p class="section-label">Nouvelle operation</p>
        <h2>Creer un emballage</h2>
        <p>Selectionnez un lot, un format sac et le poids total sera calcule automatiquement.</p>
    </div>
    <a href="<?= e(base_url('packaging')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
</section>

<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Veuillez corriger les champs indiques.</span></div><?php endif; ?>

<?php if (empty($availableBatches)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-info-circle"></i><span>Aucun lot de production disponible pour emballage.</span></div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" action="<?= e(base_url('packaging')) ?>" class="enterprise-form" data-validate data-packaging-form>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label class="form-wide">
                <span>Lot production</span>
                <select name="production_batch_id" required data-packaging-batch <?= empty($availableBatches) ? 'disabled' : '' ?>>
                    <option value="">Selectionner un lot</option>
                    <?php foreach ($availableBatches as $batch): ?>
                        <option
                            value="<?= e($batch['id']) ?>"
                            data-product="<?= e($batch['product_name']) ?>"
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
                <span>Quantite disponible</span>
                <input type="text" value="" disabled data-packaging-available>
            </label>
            <label>
                <span>Format sac</span>
                <select name="bag_format_id" required data-bag-format>
                    <option value="">Selectionner un format</option>
                    <?php foreach ($bagFormats as $format): ?>
                        <option value="<?= e($format['id']) ?>" data-weight="<?= e($format['weight_kg']) ?>" <?= (string) $packaging['bag_format_id'] === (string) $format['id'] ? 'selected' : '' ?>>
                            <?= e($format['name'] . ' - ' . number_format((float) $format['weight_kg'], 0, ',', ' ') . ' kg') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['bag_format_id'])): ?><small><?= e($errors['bag_format_id']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Nombre sacs</span>
                <input type="number" name="bags_count" min="1" step="1" value="<?= e($packaging['bags_count']) ?>" required data-bags-count>
                <?php if (!empty($errors['bags_count'])): ?><small><?= e($errors['bags_count']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Poids total calcule</span>
                <input type="text" value="" disabled data-packaging-total>
            </label>
            <label>
                <span>Date emballage</span>
                <input type="datetime-local" name="packaged_at" value="<?= e($packaging['packaged_at']) ?>" required>
                <?php if (!empty($errors['packaged_at'])): ?><small><?= e($errors['packaged_at']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Agent</span>
                <input type="text" value="<?= e(Auth::user()['name'] ?? '') ?>" disabled>
            </label>
        </div>
        <div class="form-actions">
            <a href="<?= e(base_url('packaging')) ?>" class="btn-secondary"><i class="bi bi-arrow-left"></i><span>Annuler</span></a>
            <button type="submit" class="btn-primary" <?= empty($availableBatches) ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i><span>Valider emballage</span></button>
        </div>
    </form>
</section>
