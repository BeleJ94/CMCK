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
                <span>Fournisseur</span>
                <select name="supplier_id" required>
                    <option value="">Selectionner</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= e($supplier['id']) ?>" <?= (string) $entry['supplier_id'] === (string) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['supplier_id'])): ?><small><?= e($errors['supplier_id']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Plaque camion</span>
                <input type="text" name="truck_plate_number" value="<?= e($entry['truck_plate_number']) ?>" placeholder="Ex: 1234 AB 07" data-uppercase required>
                <?php if (!empty($errors['truck_plate_number'])): ?><small><?= e($errors['truck_plate_number']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Chauffeur</span>
                <input type="text" name="driver_name" value="<?= e($entry['driver_name']) ?>" placeholder="Nom du chauffeur">
                <?php if (!empty($errors['driver_name'])): ?><small><?= e($errors['driver_name']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Telephone chauffeur</span>
                <input type="text" name="driver_phone" value="<?= e($entry['driver_phone']) ?>" placeholder="+243 ...">
                <?php if (!empty($errors['driver_phone'])): ?><small><?= e($errors['driver_phone']) ?></small><?php endif; ?>
            </label>

            <label>
                <span>Produit</span>
                <select name="product_id" required>
                    <option value="">Selectionner</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= e($product['id']) ?>" <?= (string) $entry['product_id'] === (string) $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['product_id'])): ?><small><?= e($errors['product_id']) ?></small><?php endif; ?>
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
            <a href="<?= e(base_url('weighings')) ?>" class="btn-secondary"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
            <button type="submit" class="btn-primary"><i class="bi bi-save2"></i><span>Enregistrer entree</span></button>
        </div>
    </form>
</section>
