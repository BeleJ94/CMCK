<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-plus-circle"></i></span>
    <div>
        <p class="section-label">Nouvelle sortie</p>
        <h2>Creer une distribution</h2>
        <p>Selectionnez un stock disponible, encodez les sacs a sortir et le poids total sera calcule automatiquement.</p>
    </div>
    <a href="<?= e(base_url('distributions')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
</section>

<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Veuillez corriger les champs indiques.</span></div><?php endif; ?>
<?php if (empty($availableStocks)): ?><div class="app-alert app-alert-error"><i class="bi bi-info-circle"></i><span>Aucun stock produit fini disponible pour distribution.</span></div><?php endif; ?>

<section class="form-panel">
    <form method="post" action="<?= e(base_url('distributions')) ?>" class="enterprise-form" data-validate data-distribution-form>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label class="form-wide">
                <span>Stock produit fini</span>
                <select name="finished_stock_id" required data-distribution-stock <?= empty($availableStocks) ? 'disabled' : '' ?>>
                    <option value="">Selectionner le produit et format</option>
                    <?php foreach ($availableStocks as $stock): ?>
                        <option
                            value="<?= e($stock['id']) ?>"
                            data-product="<?= e($stock['product_name']) ?>"
                            data-format="<?= e($stock['format_name']) ?>"
                            data-weight="<?= e($stock['weight_kg']) ?>"
                            data-bags="<?= e($stock['quantity_bags']) ?>"
                            data-kg="<?= e($stock['total_weight_kg']) ?>"
                            <?= (string) $distribution['finished_stock_id'] === (string) $stock['id'] ? 'selected' : '' ?>
                        >
                            <?= e($stock['product_name'] . ' - ' . $stock['format_name'] . ' - ' . number_format((int) $stock['quantity_bags'], 0, ',', ' ') . ' sacs') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['finished_stock_id'])): ?><small><?= e($errors['finished_stock_id']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Produit</span>
                <input type="text" value="" disabled data-distribution-product>
            </label>
            <label>
                <span>Format</span>
                <input type="text" value="" disabled data-distribution-format>
            </label>
            <label>
                <span>Stock disponible</span>
                <input type="text" value="" disabled data-distribution-available>
            </label>
            <label>
                <span>Nombre sacs</span>
                <input type="number" name="quantity_bags" min="1" step="1" value="<?= e($distribution['quantity_bags']) ?>" required data-distribution-bags>
                <?php if (!empty($errors['quantity_bags'])): ?><small><?= e($errors['quantity_bags']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Quantite totale calculee</span>
                <input type="text" value="" disabled data-distribution-total>
            </label>
            <label>
                <span>Client ou destination</span>
                <input type="text" name="recipient_name" value="<?= e($distribution['recipient_name']) ?>" required>
                <?php if (!empty($errors['recipient_name'])): ?><small><?= e($errors['recipient_name']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Camion / transporteur</span>
                <input type="text" name="transporter" value="<?= e($distribution['transporter']) ?>">
            </label>
            <label>
                <span>Bon de sortie</span>
                <input type="text" name="exit_voucher" value="<?= e($distribution['exit_voucher']) ?>" data-uppercase>
            </label>
            <label>
                <span>Date sortie</span>
                <input type="datetime-local" name="distributed_at" value="<?= e($distribution['distributed_at']) ?>" required>
                <?php if (!empty($errors['distributed_at'])): ?><small><?= e($errors['distributed_at']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Agent</span>
                <input type="text" value="<?= e(Auth::user()['name'] ?? '') ?>" disabled>
            </label>
        </div>
        <div class="form-actions">
            <a href="<?= e(base_url('distributions')) ?>" class="btn-secondary"><i class="bi bi-arrow-left"></i><span>Annuler</span></a>
            <button type="submit" class="btn-primary" <?= empty($availableStocks) ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i><span>Creer sortie stock</span></button>
        </div>
    </form>
</section>
