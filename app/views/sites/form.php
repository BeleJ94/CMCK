<?php $site = $site ?? []; ?>
<section class="page-heading">
    <div><p class="section-label">Administration</p><h2><?= e($title) ?></h2><p>Referentiel organisationnel DAGRIL.</p></div>
</section>
<section class="form-panel">
    <form method="post" action="<?= e($action) ?>" class="enterprise-form" data-validate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label><span>Type de site</span><select name="site_type_id" required><option value="">Selectionner</option><?php foreach ($types as $type): ?><option value="<?= e($type['id']) ?>" <?= (string) ($site['site_type_id'] ?? '') === (string) $type['id'] ? 'selected' : '' ?>><?= e($type['name']) ?></option><?php endforeach; ?></select><?php if (!empty($errors['site_type_id'])): ?><small><?= e($errors['site_type_id']) ?></small><?php endif; ?></label>
            <label><span>Code</span><input type="text" name="code" value="<?= e($site['code'] ?? '') ?>" maxlength="50" required data-uppercase><?php if (!empty($errors['code'])): ?><small><?= e($errors['code']) ?></small><?php endif; ?></label>
            <label><span>Nom</span><input type="text" name="name" value="<?= e($site['name'] ?? '') ?>" maxlength="180" required><?php if (!empty($errors['name'])): ?><small><?= e($errors['name']) ?></small><?php endif; ?></label>
            <label><span>Statut</span><select name="status"><option value="active" <?= ($site['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Actif</option><option value="inactive" <?= ($site['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif</option></select></label>
            <label class="form-span-2"><span>Description</span><textarea name="description" rows="4"><?= e($site['description'] ?? '') ?></textarea></label>
        </div>
        <div class="form-actions"><a href="<?= e(base_url('sites')) ?>" class="btn-secondary">Retour</a><button type="submit" class="btn-primary"><i class="bi bi-save2"></i><span>Enregistrer</span></button></div>
    </form>
</section>
