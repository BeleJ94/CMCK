<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-gear-wide-connected"></i></span>
    <div>
        <p class="section-label">Traitement dechets</p>
        <h2>Envoyer vers machine dechets</h2>
        <p>Encodez la quantite traitee et l aliment betail produit. Le rendement est calcule automatiquement.</p>
    </div>
    <a href="<?= e(base_url('waste')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
</section>

<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Veuillez corriger les champs indiques.</span></div><?php endif; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Stock dechets disponible</span><span class="metric-icon tone-orange"><i class="bi bi-recycle"></i></span></div><strong><?= e(number_format($availableStock, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Machines dechets actives</span><span class="metric-icon tone-green"><i class="bi bi-check2-circle"></i></span></div><strong><?= e(count($machines)) ?></strong></article>
</section>

<?php if ($availableStock <= 0): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-info-circle"></i><span>Aucun stock dechets disponible pour traitement.</span></div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" action="<?= e(base_url('waste/process')) ?>" class="enterprise-form" data-validate data-waste-form data-available-stock="<?= e($availableStock) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>
                <span>Machine dechets</span>
                <select name="machine_id" required <?= empty($machines) ? 'disabled' : '' ?>>
                    <option value="">Selectionner une machine</option>
                    <?php foreach ($machines as $machine): ?>
                        <option value="<?= e($machine['id']) ?>" <?= (string) $processing['machine_id'] === (string) $machine['id'] ? 'selected' : '' ?>>
                            <?= e($machine['name'] . ' - ' . number_format((float) $machine['capacity_kg_hour'], 0, ',', ' ') . ' kg/h') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['machine_id'])): ?><small><?= e($errors['machine_id']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Quantite dechets traitee kg</span>
                <input type="number" name="input_quantity_kg" min="0.001" max="<?= e($availableStock) ?>" step="0.001" value="<?= e($processing['input_quantity_kg']) ?>" required data-waste-input>
                <?php if (!empty($errors['input_quantity_kg'])): ?><small><?= e($errors['input_quantity_kg']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Aliment betail produit kg</span>
                <input type="number" name="output_quantity_kg" min="0" step="0.001" value="<?= e($processing['output_quantity_kg']) ?>" required data-waste-output>
                <?php if (!empty($errors['output_quantity_kg'])): ?><small><?= e($errors['output_quantity_kg']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Rendement dechets</span>
                <input type="text" value="0 %" disabled data-waste-yield>
            </label>
            <label>
                <span>Date traitement</span>
                <input type="datetime-local" name="processed_at" value="<?= e($processing['processed_at']) ?>" required>
                <?php if (!empty($errors['processed_at'])): ?><small><?= e($errors['processed_at']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Agent</span>
                <input type="text" value="<?= e(Auth::user()['name'] ?? '') ?>" disabled>
            </label>
        </div>
        <div class="form-actions">
            <a href="<?= e(base_url('waste')) ?>" class="btn-secondary"><i class="bi bi-arrow-left"></i><span>Annuler</span></a>
            <button type="submit" class="btn-primary" <?= ($availableStock <= 0 || empty($machines)) ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i><span>Valider traitement</span></button>
        </div>
    </form>
</section>
