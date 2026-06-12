<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-plus-circle"></i></span>
    <div>
        <p class="section-label">Nouvelle operation</p>
        <h2>Valider une production farine</h2>
        <p>Selectionnez un lot alimente, encodez le bon produit, les dechets et le rendement sera calcule automatiquement.</p>
    </div>
    <a href="<?= e(base_url('production')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
</section>

<?php if (!empty($errors)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Veuillez corriger les champs indiques.</span></div><?php endif; ?>

<?php if (empty($pendingBatches)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-info-circle"></i><span>Aucun lot en attente production. Creez d'abord une alimentation machine.</span></div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" action="<?= e(base_url('production')) ?>" class="enterprise-form" data-validate data-production-form>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label class="form-wide">
                <span>Lot traitement</span>
                <select name="production_batch_id" required data-production-batch <?= empty($pendingBatches) ? 'disabled' : '' ?>>
                    <option value="">Selectionner un lot</option>
                    <?php foreach ($pendingBatches as $batch): ?>
                        <option
                            value="<?= e($batch['id']) ?>"
                            data-quantity="<?= e($batch['input_quantity_kg']) ?>"
                            data-machine="<?= e($batch['machine_name']) ?>"
                            <?= (string) $production['production_batch_id'] === (string) $batch['id'] ? 'selected' : '' ?>
                        >
                            <?= e($batch['batch_number'] . ' - ' . $batch['machine_name'] . ' - ' . number_format((float) $batch['input_quantity_kg'], 0, ',', ' ') . ' kg') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['production_batch_id'])): ?><small><?= e($errors['production_batch_id']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Machine</span>
                <input type="text" value="" disabled data-machine-name>
            </label>
            <label>
                <span>Quantite traitee</span>
                <input type="text" value="" disabled data-treated-quantity>
            </label>
            <label>
                <span>Quantite bon produit kg</span>
                <input type="number" name="output_quantity_kg" min="0" step="0.001" value="<?= e($production['output_quantity_kg']) ?>" required data-good-quantity>
                <?php if (!empty($errors['output_quantity_kg'])): ?><small><?= e($errors['output_quantity_kg']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Quantite dechets kg</span>
                <input type="number" name="waste_quantity_kg" min="0" step="0.001" value="<?= e($production['waste_quantity_kg']) ?>" readonly required data-waste-quantity>
                <?php if (!empty($errors['waste_quantity_kg'])): ?><small><?= e($errors['waste_quantity_kg']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Rendement</span>
                <input type="text" value="0 %" disabled data-yield-rate>
            </label>
            <label>
                <span>Date production</span>
                <input type="datetime-local" name="ended_at" value="<?= e($production['ended_at']) ?>" required>
                <?php if (!empty($errors['ended_at'])): ?><small><?= e($errors['ended_at']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Agent</span>
                <input type="text" value="<?= e(Auth::user()['name'] ?? '') ?>" disabled>
            </label>
        </div>
        <div class="form-actions">
            <a href="<?= e(base_url('production')) ?>" class="btn-secondary"><i class="bi bi-arrow-left"></i><span>Annuler</span></a>
            <button type="submit" class="btn-primary" <?= empty($pendingBatches) ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i><span>Valider production</span></button>
        </div>
    </form>
</section>
