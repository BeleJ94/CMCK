<?php
$isEdit = !empty($machine['id']);
$action = $isEdit ? base_url('machines/' . $machine['id'] . '/update') : base_url('machines');
$statusOptions = ['active', 'inactive', 'pending', 'validated', 'cancelled'];
$typeOptions = ['main' => 'Machine principale', 'waste' => 'Machine dechets'];
?>

<?php if (!empty($errors)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Veuillez corriger les champs indiques.</span></div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" action="<?= e($action) ?>" class="enterprise-form" data-validate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>
                <span>Nom</span>
                <input type="text" name="name" value="<?= e($machine['name'] ?? '') ?>" required minlength="2">
                <?php if (!empty($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Type machine</span>
                <select name="machine_type" required>
                    <?php foreach ($typeOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($machine['machine_type'] ?? 'main') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['machine_type'])): ?><small><?= e($errors['machine_type']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Capacite horaire kg/h</span>
                <input type="number" name="capacity_kg_hour" value="<?= e($machine['capacity_kg_hour'] ?? '') ?>" min="0" step="0.001">
                <?php if (!empty($errors['capacity_kg_hour'])): ?><small><?= e($errors['capacity_kg_hour']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Statut</span>
                <select name="status" required>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($machine['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['status'])): ?><small><?= e($errors['status']) ?></small><?php endif; ?>
            </label>
        </div>
        <div class="form-actions">
            <a href="<?= e(base_url('machines')) ?>" class="btn-secondary"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
            <button type="submit" class="btn-primary"><i class="bi bi-save2"></i><span><?= $isEdit ? 'Enregistrer les modifications' : 'Ajouter la machine' ?></span></button>
        </div>
    </form>
</section>
