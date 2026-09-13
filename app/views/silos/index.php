<?php
$unit = $filters['unit'];
$quantity = function ($kg) use ($unit) { return rtrim(rtrim(number_format((float) $kg / ($unit === 't' ? 1000 : 1), 3, ',', ' '), '0'), ','); };
$percent = function ($value) { return $value === null ? '—' : number_format($value, 1, ',', ' ') . ' %'; };
$clearAlertQuery = array_filter(['q' => $filters['q'], 'product' => $filters['product'], 'status' => $filters['status'], 'unit' => $unit], function ($value) { return $value !== ''; });
$filtered = $filters['q'] !== '' || $filters['product'] !== '' || $filters['status'] !== '' || $filters['alerts'];
?>
<div class="silo-dashboard">
    <header class="silo-heading">
        <div><p class="silo-eyebrow">APPROVISIONNEMENTS & MATIÈRES</p><h2>Pilotage des silos</h2><p><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e($siteLabel) ?> <span class="silo-heading-divider">·</span> Actualisé à <?= e($updatedAt) ?></p></div>
        <div class="silo-heading-actions">
            <?php if (Auth::can('silos', 'administer')): ?><a href="<?= e(base_url('silo-administration')) ?>" class="btn-secondary"><i class="bi bi-sliders" aria-hidden="true"></i> Gérer les silos</a><?php endif; ?>
            <a href="<?= e(base_url('silos/movements')) ?>" class="btn-primary"><i class="bi bi-clock-history" aria-hidden="true"></i> Historique</a>
        </div>
    </header>
    <?php if (!empty($error)): ?><div class="app-alert app-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="app-alert app-alert-success" role="status"><?= e($success) ?></div><?php endif; ?>

    <section class="silo-kpis" aria-label="Synthèse des silos actifs">
        <article class="silo-kpi silo-kpi-primary"><div><span>Stock total actif</span><i class="bi bi-box-seam" aria-hidden="true"></i></div><strong><?= e($quantity($summary['stock'])) ?> <small><?= e($unit) ?></small></strong><p><?= e($summary['active']) ?> silo(s) en service</p></article>
        <article class="silo-kpi"><div><span>Capacité disponible</span><i class="bi bi-arrows-expand" aria-hidden="true"></i></div><strong><?= e($quantity($summary['available'])) ?> <small><?= e($unit) ?></small></strong><p>Pour les prochaines réceptions<?= $summary['unknown_capacity'] ? ' · capacités connues' : '' ?></p></article>
        <article class="silo-kpi"><div><span>Occupation globale</span><i class="bi bi-pie-chart" aria-hidden="true"></i></div><strong><?= e($percent($summary['occupation'])) ?></strong><p><?= $summary['unknown_capacity'] ? 'Capacités à compléter' : 'Sur ' . e($quantity($summary['capacity'])) . ' ' . e($unit) . ' de capacité active' ?></p></article>
        <a class="silo-kpi silo-kpi-filter <?= $summary['alerts'] ? 'silo-kpi-alert' : '' ?>" href="<?= e(base_url('silos') . '?' . http_build_query(['alerts' => '1', 'unit' => $unit])) ?>" <?= $filters['alerts'] ? 'aria-current="true"' : '' ?> aria-label="Afficher les <?= e($summary['alerts']) ?> silos à surveiller"><div><span>Silos à surveiller</span><i class="bi <?= $summary['alerts'] ? 'bi-exclamation-triangle' : 'bi-check2-circle' ?>" aria-hidden="true"></i></div><strong><?= e($summary['alerts']) ?></strong><p><?= $filters['alerts'] ? 'Filtre alertes actif' : ($summary['alerts'] ? 'Afficher les silos en alerte →' : 'Aucune alerte sur les silos actifs') ?></p></a>
    </section>
    <p class="silo-scope-note">Synthèse du périmètre « <?= e($siteLabel) ?> », indépendante des filtres ci-dessous. <?= e($summary['inactive']) ?> silo(s) hors service<?php if ($summary['inactive_stock'] != 0): ?> · <?= e($quantity($summary['inactive_stock'])) ?> <?= e($unit) ?> de stock hors service<?php endif; ?>.</p>

    <section class="silo-directory" aria-labelledby="siloDirectoryTitle">
        <div class="silo-section-heading"><div><h3 id="siloDirectoryTitle">Parc de silos <span class="silo-count"><?= e(count($silos)) ?></span></h3><p>Visualisez les niveaux de stockage et sélectionnez un silo pour consulter son détail.</p></div><span class="silo-result-count"><?= e(count($silos)) ?> sur <?= e($total) ?> silo(s)</span></div>
        <form class="silo-filters" method="get" action="<?= e(base_url('silos')) ?>" role="search" aria-label="Filtrer les silos">
            <label class="silo-search"><span>Rechercher</span><input type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="120" placeholder="Nom, code, site…"></label>
            <label><span>Produit</span><select name="product"><option value="">Tous les produits</option><?php foreach ($products as $key => $name): ?><option value="<?= e($key) ?>" <?= (string) $key === $filters['product'] ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
            <label><span>Statut</span><select name="status"><option value="">Tous les statuts</option><option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>En service</option><option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Hors service</option></select></label>
            <label><span>Unité</span><select name="unit"><option value="kg" <?= $unit === 'kg' ? 'selected' : '' ?>>kg</option><option value="t" <?= $unit === 't' ? 'selected' : '' ?>>Tonnes</option></select></label>
            <?php if ($filters['alerts']): ?>
                <input type="hidden" name="alerts" value="1">
                <a class="silo-alert-chip" href="<?= e(base_url('silos') . '?' . http_build_query($clearAlertQuery)) ?>" aria-label="Retirer le filtre alertes uniquement">Alertes uniquement <span aria-hidden="true">×</span></a>
            <?php endif; ?>
            <button type="submit" class="btn-primary">Appliquer</button>
            <?php if ($filtered): ?><a class="silo-reset" href="<?= e(base_url('silos') . '?' . http_build_query(['unit' => $unit])) ?>">Réinitialiser</a><?php endif; ?>
        </form>
        <div class="silo-overview-grid">
            <?php foreach ($silos as $silo): ?>
                <article class="silo-visual is-<?= e($silo['health']) ?>">
                    <a class="silo-visual-link" href="<?= e(base_url('silos/' . $silo['id'])) ?>" aria-label="Consulter <?= e($silo['name']) ?> — <?= e($silo['health_label']) ?>">
                        <div class="silo-visual-heading"><span class="silo-code"><?= e($silo['code']) ?></span><h4><?= e($silo['name']) ?></h4><p class="silo-location"><?= e($silo['site_name'] ?? 'Site non renseigné') ?> · <?= e($silo['product_name'] ?: 'Produit non affecté') ?></p></div>
                        <span class="silo-health"><span aria-hidden="true">●</span> <?= e($silo['health_label']) ?></span>
                        <?php require __DIR__ . '/illustration.php'; ?>
                        <div class="silo-stock"><span>Stock actuel</span><strong><?= e($quantity($silo['current_stock_kg'])) ?> <small><?= e($unit) ?></small></strong></div>
                        <dl class="silo-capacities"><div><dt>Capacité totale</dt><dd><?= e($quantity($silo['capacity_kg'])) ?> <small><?= e($unit) ?></small></dd></div><div><dt><?= $silo['active'] ? 'Place disponible' : 'Capacité hors service' ?></dt><dd><?= e($quantity($silo['available'])) ?> <small><?= e($unit) ?></small></dd></div></dl>
                        <div class="silo-visual-footer"><span>Seuil bas : <?= (float) $silo['alert_threshold_kg'] > 0 ? e($quantity($silo['alert_threshold_kg']) . ' ' . $unit) : 'désactivé' ?></span><strong>Consulter <span aria-hidden="true">→</span></strong></div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (!$silos): ?><div class="silo-empty"><i class="bi bi-database" aria-hidden="true"></i><h4><?= $total ? 'Aucun silo ne correspond à vos filtres' : 'Aucun silo dans ce périmètre' ?></h4><p><?= $total ? 'Modifiez votre recherche ou réinitialisez les filtres.' : 'Choisissez un autre site ou configurez un premier silo depuis l’administration.' ?></p><?php if ($filtered): ?><a class="btn-secondary" href="<?= e(base_url('silos')) ?>">Afficher tous les silos</a><?php endif; ?></div><?php endif; ?>
    </section>

    <section class="silo-recent" aria-labelledby="siloRecentTitle"><div class="silo-section-heading"><div><h3 id="siloRecentTitle">Derniers mouvements</h3><p>Les six dernières opérations du périmètre « <?= e($siteLabel) ?> ».</p></div><a href="<?= e(base_url('silos/movements')) ?>">Tout l’historique <span aria-hidden="true">→</span></a></div>
        <div class="table-responsive"><table class="enterprise-table" data-datatable="false"><thead><tr><th>Date</th><th>Silo</th><th>Opération</th><th>Quantité (<?= e($unit) ?>)</th><th>Statut</th></tr></thead><tbody>
            <?php foreach ($recentMovements as $movement): $type = $movement['movement_type']; ?>
                <tr><td><?= e(date('d/m/Y · H:i', strtotime($movement['movement_at']))) ?></td><td><a href="<?= e(base_url('silos/' . $movement['silo_id'])) ?>"><?= e($movement['silo_name']) ?></a><small><?= e($movement['silo_code']) ?></small></td><td><span class="silo-movement-type <?= $type === 'in' ? 'is-in' : '' ?>"><i class="bi <?= $type === 'in' ? 'bi-arrow-down-left' : ($type === 'out' ? 'bi-arrow-up-right' : 'bi-arrow-repeat') ?>" aria-hidden="true"></i> <?= e(['in' => 'Entrée', 'out' => 'Sortie', 'adjustment' => 'Ajustement'][$type] ?? $type) ?></span></td><td class="silo-movement-quantity"><?= $type === 'in' ? '+' : ($type === 'out' ? '−' : '') ?><?= e($quantity($movement['quantity_kg'])) ?></td><td><?= e(['validated' => 'Validé', 'cancelled' => 'Annulé', 'active' => 'Actif', 'pending' => 'En attente', 'inactive' => 'Inactif'][$movement['status']] ?? $movement['status']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$recentMovements): ?><tr><td colspan="5">Aucun mouvement enregistré dans ce périmètre.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</div>
