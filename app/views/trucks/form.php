<?php
$isEdit = !empty($truck['id']);
$action = $isEdit ? base_url('trucks/' . $truck['id'] . '/update') : base_url('trucks');
$statusOptions = ['active', 'inactive', 'pending', 'validated', 'cancelled'];
?>

<?php if (!empty($errors)): ?>
    <div class="app-alert app-alert-error">
        <i class="bi bi-exclamation-triangle"></i>
        <span>Veuillez corriger les champs indiques.</span>
    </div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" action="<?= e($action) ?>" class="enterprise-form" data-validate>
        <?= csrf_field() ?>

        <div class="form-grid">
            <label>
                <span>Plaque</span>
                <input type="text" name="plate_number" value="<?= e($truck['plate_number'] ?? '') ?>" required minlength="3" data-uppercase>
                <?php if (!empty($errors['plate_number'])): ?><small><?= e($errors['plate_number']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Fournisseur associe</span>
                <select name="supplier_id" required>
                    <option value="">Selectionner un fournisseur</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= e($supplier['id']) ?>" <?= (string) ($truck['supplier_id'] ?? '') === (string) $supplier['id'] ? 'selected' : '' ?>>
                            <?= e($supplier['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['supplier_id'])): ?><small><?= e($errors['supplier_id']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Chauffeur</span>
                <input type="text" name="driver_name" value="<?= e($truck['driver_name'] ?? '') ?>" required minlength="2">
                <?php if (!empty($errors['driver_name'])): ?><small><?= e($errors['driver_name']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Telephone chauffeur</span>
                <input type="tel" name="driver_phone" value="<?= e($truck['driver_phone'] ?? '') ?>" pattern="[0-9 +().-]{6,30}">
                <?php if (!empty($errors['driver_phone'])): ?><small><?= e($errors['driver_phone']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Statut</span>
                <select name="status" required>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($truck['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
            </label>
        </div>

        <div class="form-actions">
            <a href="<?= e(base_url('trucks')) ?>" class="btn-secondary">
                <i class="bi bi-arrow-left"></i>
                <span>Retour</span>
            </a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save2"></i>
                <span><?= $isEdit ? 'Enregistrer les modifications' : 'Ajouter le camion' ?></span>
            </button>
        </div>
    </form>
</section>
