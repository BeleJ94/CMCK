<section class="page-heading"><div><p class="page-kicker">Administration · Gestion des silos</p><h2><?= e($title) ?></h2><p><?= $id === null ? 'Le nouveau silo sera actif avec un stock initial de zéro.' : 'Les changements de site ou de produit nécessitent un silo vide.' ?></p></div></section>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="form-panel">
    <form method="post" action="<?= e(base_url($id === null ? 'silo-administration' : 'silo-administration/' . $id . '/update')) ?>" class="enterprise-form" data-validate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <label><span>Code unique</span><input name="code" value="<?= e($silo['code'] ?? '') ?>" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9_\-]{0,79}" data-uppercase placeholder="SILO-03"></label>
            <label><span>Nom du silo</span><input name="name" value="<?= e($silo['name'] ?? '') ?>" required maxlength="120" placeholder="Silo maïs 3"></label>
            <label><span>Site de rattachement</span><select name="site_id" required><option value="">Choisir un site</option>
                <?php foreach ($sites as $site): ?><option value="<?= e($site['id']) ?>" <?= (string) ($silo['site_id'] ?? '') === (string) $site['id'] ? 'selected' : '' ?>><?= e($site['code'] . ' — ' . $site['name']) ?></option><?php endforeach; ?>
            </select></label>
            <label><span>Produit stocké</span><select name="product_id"><option value="">Non affecté</option>
                <?php if (!empty($silo['product_id']) && !in_array((string) $silo['product_id'], array_map('strval', array_column($products, 'id')), true)): ?><option value="<?= e($silo['product_id']) ?>" selected>Produit actuel indisponible</option><?php endif; ?>
                <?php foreach ($products as $product): ?><option value="<?= e($product['id']) ?>" <?= (string) ($silo['product_id'] ?? '') === (string) $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?></option><?php endforeach; ?>
            </select></label>
            <label><span>Capacité (kg)</span><input type="number" name="capacity_kg" value="<?= e($silo['capacity_kg'] ?? '') ?>" required min="0.001" max="999999999.999" step="0.001"></label>
            <label><span>Seuil d’alerte de stock bas (kg)</span><input type="number" name="alert_threshold_kg" value="<?= e($silo['alert_threshold_kg'] ?? '0') ?>" required min="0" max="999999999.999" step="0.001"><small>Indiquez zéro pour désactiver l’alerte de stock bas.</small></label>
        </div>
        <?php if ($id !== null): ?><p>Stock actuel : <strong><?= e(number_format((float) ($silo['current_stock_kg'] ?? 0), 3, ',', ' ')) ?> kg</strong>. Les quantités sont mises à jour par les opérations métier.</p><?php endif; ?>
        <div class="form-actions"><a class="btn-secondary" href="<?= e(base_url('silo-administration')) ?>">Retour</a><button class="btn-primary" type="submit" <?= !$sites ? 'disabled' : '' ?>><?= $id === null ? 'Créer le silo' : 'Enregistrer les modifications' ?></button></div>
    </form>
</section>
