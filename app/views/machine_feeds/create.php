<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-plus-circle"></i></span>
    <div>
        <p class="section-label">Nouvelle operation</p>
        <h2>Alimenter une machine</h2>
        <p>Selectionnez le silo source, la machine principale et la quantite envoyee.</p>
    </div>
</section>

<?php if (!empty($errors)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Veuillez corriger les champs indiques.</span></div><?php endif; ?>

<section class="form-panel">
    <form method="post" action="<?= e(base_url('machine-feeds')) ?>" class="enterprise-form" data-validate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>
                <span>Silo source</span>
                <select name="silo_id" required>
                    <option value="">Selectionner un silo</option>
                    <?php foreach ($silos as $silo): ?>
                        <option value="<?= e($silo['id']) ?>" <?= (string) $feed['silo_id'] === (string) $silo['id'] ? 'selected' : '' ?>>
                            <?= e($silo['name'] . ' (' . $silo['code'] . ') - stock ' . number_format((float) $silo['current_stock_kg'], 0, ',', ' ') . ' kg') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['silo_id'])): ?><small><?= e($errors['silo_id']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Machine principale</span>
                <select name="machine_id" required>
                    <option value="">Selectionner une machine</option>
                    <?php foreach ($machines as $machine): ?>
                        <option value="<?= e($machine['id']) ?>" <?= (string) $feed['machine_id'] === (string) $machine['id'] ? 'selected' : '' ?>>
                            <?= e($machine['name'] . ' - ' . number_format((float) $machine['capacity_kg_hour'], 0, ',', ' ') . ' kg/h') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['machine_id'])): ?><small><?= e($errors['machine_id']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Quantite envoyee kg</span>
                <input type="number" name="quantity_kg" min="0.001" step="0.001" value="<?= e($feed['quantity_kg']) ?>" required>
                <?php if (!empty($errors['quantity_kg'])): ?><small><?= e($errors['quantity_kg']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Heure debut</span>
                <input type="datetime-local" name="fed_at" value="<?= e($feed['fed_at']) ?>" required>
                <?php if (!empty($errors['fed_at'])): ?><small><?= e($errors['fed_at']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Heure fin</span>
                <input type="datetime-local" name="ended_at" value="<?= e($feed['ended_at']) ?>">
                <?php if (!empty($errors['ended_at'])): ?><small><?= e($errors['ended_at']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Agent responsable</span>
                <input type="text" value="<?= e(Auth::user()['name'] ?? '') ?>" disabled>
            </label>
            <label class="form-wide">
                <span>Observation</span>
                <textarea name="observation" rows="4"><?= e($feed['observation']) ?></textarea>
            </label>
        </div>
        <div class="form-actions">
            <a href="<?= e(base_url('machine-feeds')) ?>" class="btn-secondary"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
            <button type="submit" class="btn-primary"><i class="bi bi-check2-circle"></i><span>Creer alimentation et lot</span></button>
        </div>
    </form>
</section>
