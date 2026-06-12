<?php
$isEdit = !empty($supplier['id']);
$action = $isEdit ? base_url('suppliers/' . $supplier['id'] . '/update') : base_url('suppliers');
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
                <span>Nom</span>
                <input type="text" name="name" value="<?= e($supplier['name'] ?? '') ?>" required minlength="2">
                <?php if (!empty($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Telephone</span>
                <input type="tel" name="phone" value="<?= e($supplier['phone'] ?? '') ?>" pattern="[0-9 +().-]{6,30}">
                <?php if (!empty($errors['phone'])): ?><small><?= e($errors['phone']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>RCCM</span>
                <input type="text" name="rccm" value="<?= e($supplier['rccm'] ?? '') ?>" required>
                <?php if (!empty($errors['rccm'])): ?><small><?= e($errors['rccm']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>ID Nat</span>
                <input type="text" name="id_nat" value="<?= e($supplier['id_nat'] ?? '') ?>" required>
                <?php if (!empty($errors['id_nat'])): ?><small><?= e($errors['id_nat']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Statut</span>
                <select name="status" required>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($supplier['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
            </label>

            <label class="form-wide">
                <span>Adresse</span>
                <textarea name="address" rows="4" required><?= e($supplier['address'] ?? '') ?></textarea>
                <?php if (!empty($errors['address'])): ?><small><?= e($errors['address']) ?></small><?php endif; ?>
            </label>
        </div>

        <div class="form-actions">
            <a href="<?= e(base_url('suppliers')) ?>" class="btn-secondary">
                <i class="bi bi-arrow-left"></i>
                <span>Retour</span>
            </a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-save2"></i>
                <span><?= $isEdit ? 'Enregistrer les modifications' : 'Ajouter le fournisseur' ?></span>
            </button>
        </div>
    </form>
</section>
