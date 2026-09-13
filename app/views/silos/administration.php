<?php $siteNames = array_column($sites, 'name', 'id'); ?>
<section class="page-heading">
    <div><p class="page-kicker">Administration</p><h2>Gestion des silos</h2><p>Configurez les capacités, les produits et les seuils d’alerte de vos silos.</p></div>
    <?php if ($sites): ?><a href="<?= e(base_url('silo-administration/create')) ?>" class="btn-primary"><i class="bi bi-plus-lg"></i><span>Nouveau silo</span></a><?php endif; ?>
</section>
<?php if (!empty($success)): ?><div class="app-alert app-alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><?= e($error) ?></div><?php endif; ?>
<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-database"></i></span><div><h3>Répertoire des silos</h3><p>Le stock évolue au fil des réceptions et des sorties validées.</p></div></div>
    <div class="table-responsive"><table data-search-placeholder="Rechercher un silo, un site ou un produit">
        <thead><tr><th>Code</th><th>Nom</th><th>Site</th><th>Produit</th><th>Capacité (kg)</th><th>Stock (kg)</th><th>Seuil d’alerte (kg)</th><th>Statut</th><th data-sortable="false">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($silos as $silo): $active = in_array($silo['status'], ['active', 'validated'], true); ?>
            <tr>
                <td><?= e($silo['code']) ?></td><td><?= e($silo['name']) ?></td><td><?= e($siteNames[$silo['site_id']] ?? '') ?></td><td><?= e($silo['product_name'] ?: 'Non affecté') ?></td>
                <td><?= e(number_format((float) $silo['capacity_kg'], 3, ',', ' ')) ?></td><td><?= e(number_format((float) $silo['current_stock_kg'], 3, ',', ' ')) ?></td><td><?= e(number_format((float) $silo['alert_threshold_kg'], 3, ',', ' ')) ?></td>
                <td><span class="status-badge status-<?= e($silo['status']) ?>"><?= e(['active' => 'Actif', 'validated' => 'Validé', 'inactive' => 'Inactif', 'pending' => 'En attente', 'cancelled' => 'Annulé'][$silo['status']] ?? $silo['status']) ?></span></td>
                <td><div class="table-actions">
                    <a class="btn-secondary" href="<?= e(base_url('silo-administration/' . $silo['id'] . '/edit')) ?>">Modifier</a>
                    <form method="post" action="<?= e(base_url('silo-administration/' . $silo['id'] . '/status')) ?>" data-confirm="<?= e($active ? 'Désactiver ce silo ? Il ne sera plus disponible pour les opérations.' : 'Activer ce silo ?') ?>">
                        <?= csrf_field() ?><input type="hidden" name="status" value="<?= $active ? 'inactive' : 'active' ?>">
                        <button class="btn-secondary" type="submit" <?= $active && (float) $silo['current_stock_kg'] != 0 ? 'disabled title="Videz le silo avant de le désactiver."' : '' ?>><?= $active ? 'Désactiver' : 'Activer' ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$silos): ?><tr><td colspan="9">Aucun silo à administrer dans ce contexte de site.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
