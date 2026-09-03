<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-box-arrow-in-down"></i></span>
    <div>
        <p class="section-label">Etape 1</p>
        <h2>Pesee entree</h2>
        <p>Enregistrement du poids brut et mise en attente de dechargement.</p>
    </div>
</section>

<?php if (!empty($errors)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Veuillez corriger les champs indiques.</span></div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" action="<?= e(base_url('weighings/entry')) ?>" class="enterprise-form" data-validate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>
                <span>BT en transit</span>
                <select name="transport_id" required>
                    <option value="">Selectionner un BT</option>
                    <?php foreach ($transports as $transport): ?>
                        <option value="<?= e($transport['id']) ?>" <?= (string) $entry['transport_id'] === (string) $transport['id'] ? 'selected' : '' ?>><?= e(($transport['bt_number']?:$transport['transport_reference']).' — '.$transport['plate_number'].' — '.$transport['product_name'].' — '.number_format((float)$transport['shipped_quantity_kg'],0,',',' ').' kg') ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['transport_id'])): ?><small><?= e($errors['transport_id']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Poids brut kg</span>
                <input type="number" step="0.001" min="0.001" name="poids_brut" value="<?= e($entry['poids_brut']) ?>" required>
                <?php if (!empty($errors['poids_brut'])): ?><small><?= e($errors['poids_brut']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Date / heure</span>
                <input type="text" value="<?= e(date('Y-m-d H:i')) ?>" disabled>
            </label>

            <label>
                <span>Agent</span>
                <input type="text" value="<?= e(Auth::user()['name'] ?? '') ?>" disabled>
            </label>

            <label>
                <span>Statut</span>
                <input type="text" value="En attente de dechargement" disabled>
            </label>
        </div>

        <div class="form-actions">
            <a href="<?=e(base_url('weighbridge-transports'))?>" class="btn-secondary"><i class="bi bi-truck"></i><span>Nouveau BT</span></a>
            <a href="<?= e(base_url('weighings')) ?>" class="btn-secondary"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
            <button type="submit" class="btn-primary"><i class="bi bi-save2"></i><span>Enregistrer entree</span></button>
        </div>
    </form>
</section>
