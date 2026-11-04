<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-plus-circle"></i></span>
    <div>
        <p class="section-label">Nouvelle operation</p>
        <h2>Creer un emballage</h2>
        <p>Sélectionnez la farine en vrac et un format de sac : le poids total est calculé et la farine est prélevée sur les lots les plus anciens.</p>
    </div>
    <a href="<?= e(base_url('packaging')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
</section>

<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Veuillez corriger les champs indiques.</span></div><?php endif; ?>

<?php if (empty($availableProducts)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-info-circle"></i><span>Aucune farine en vrac disponible pour emballage.</span></div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" action="<?= e(base_url('packaging')) ?>" class="enterprise-form" data-validate data-packaging-form>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label class="form-wide">
                <span>Farine en vrac</span>
                <select name="stock_key" required data-packaging-batch <?= empty($availableProducts) ? 'disabled' : '' ?>>
                    <option value="">Sélectionner la farine</option>
                    <?php foreach ($availableProducts as $stock): $key = Packaging::stockKey($stock['site_id'], $stock['product_id']); ?>
                        <option
                            value="<?= e($key) ?>"
                            data-site="<?= e($stock['site_id']) ?>" data-code="<?= e($stock['product_code']) ?>" data-product="<?= e($stock['product_name']) ?>"
                            data-available="<?= e($stock['available_quantity_kg']) ?>"
                            <?= (string) $packaging['stock_key'] === $key ? 'selected' : '' ?>
                        >
                            <?= e($stock['product_name'] . ' · ' . $stock['site_code'] . ' — ' . number_format((float) $stock['available_quantity_kg'], 0, ',', ' ') . ' kg disponibles') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Stock global en vrac. La farine est prélevée automatiquement sur les lots les plus anciens.</small>
                <?php if (!empty($errors['stock_key'])): ?><small><?= e($errors['stock_key']) ?></small><?php endif; ?>
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
                <select name="packaging_item_id" required data-bag-format>
                    <option value="">Selectionner un format</option>
                    <?php foreach ($bagFormats as $format): ?>
                        <option value="<?= e($format['packaging_item_id']) ?>" data-weight="<?= e($format['weight_kg']) ?>" <?= (string) $packaging['packaging_item_id'] === (string) $format['packaging_item_id'] ? 'selected' : '' ?>>
                            <?= e($format['code'].' — '.$format['name'] . ' - ' . number_format((float) $format['weight_kg'], 0, ',', ' ') . ' kg') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['packaging_item_id'])): ?><small><?= e($errors['packaging_item_id']) ?></small><?php endif; ?>
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
            <button type="submit" class="btn-primary" <?= empty($availableProducts) ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i><span>Valider emballage</span></button>
        </div>
    </form>
</section>
