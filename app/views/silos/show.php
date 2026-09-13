<?php
$quantity = function ($kg) use ($unit) { return rtrim(rtrim(number_format((float) $kg / ($unit === 't' ? 1000 : 1), 3, ',', ' '), '0'), ','); };
$percent = function ($value) { return $value === null ? '—' : number_format($value, 1, ',', ' ') . ' %'; };
$date = function ($value) { return $value ? date('d/m/Y · H:i', strtotime($value)) : '—'; };
$tabs = ['movements' => 'Mouvements de stock', 'entries' => 'Livraisons reçues', 'exits' => 'Alimentations machines'];
$statuses = ['validated' => 'Validé', 'active' => 'Actif', 'pending' => 'En attente', 'inactive' => 'Inactif', 'cancelled' => 'Annulé', 'in_progress' => 'En cours', 'authorized' => 'Autorisé', 'loaded' => 'Chargé'];
$notice = ['normal' => 'Le niveau de stockage est dans la plage normale.', 'inactive' => 'Ce silo est hors service. Son stock et son historique restent consultables.'];
if ($silo['health_label'] === 'Capacité dépassée') { $notice[$silo['health']] = 'La capacité est dépassée de ' . $quantity((float) $silo['current_stock_kg'] - (float) $silo['capacity_kg']) . ' ' . $unit . '. Vérifiez le stock avant toute nouvelle réception.'; }
elseif ($silo['health_label'] === 'Stock bas') { $notice[$silo['health']] = 'Le stock a atteint le seuil d’alerte ou se situe en dessous. Anticipez le réapprovisionnement.'; }
elseif ($silo['health_label'] === 'Presque plein') { $notice[$silo['health']] = 'Le silo est rempli à au moins 90 %. Vérifiez la place disponible avant la prochaine livraison.'; }
elseif ($silo['occupation'] === null) { $notice[$silo['health']] = 'La capacité doit être renseignée pour calculer le taux de remplissage.'; }
?>
<div class="silo-dashboard silo-detail">
    <a class="silo-back" href="<?= e(base_url('silos') . '?' . http_build_query(['unit' => $unit])) ?>"><span aria-hidden="true">←</span> Parc de silos</a>
    <header class="silo-heading">
        <div><p class="silo-eyebrow"><?= e($silo['code']) ?> · FICHE SILO</p><h2><?= e($silo['name']) ?></h2><p><?= e($silo['site_name'] ?: 'Site non renseigné') ?> · <?= e($silo['product_name'] ?: 'Produit non affecté') ?> <span class="silo-heading-divider">·</span> Actualisé à <?= e($updatedAt) ?></p></div>
        <div class="silo-heading-actions">
            <nav class="silo-unit-switch" aria-label="Unité des quantités"><?php foreach (['kg' => 'kg', 't' => 'Tonnes'] as $value => $label): ?><a href="<?= e(base_url('silos/' . $silo['id']) . '?' . http_build_query(['unit' => $value, 'tab' => $tab])) ?>" <?= $unit === $value ? 'aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
            <?php if (Auth::can('silos', 'administer', $silo['site_id']) && Auth::canAccessSite($silo['site_id'])): ?><a class="btn-secondary" href="<?= e(base_url('silo-administration/' . $silo['id'] . '/edit')) ?>"><i class="bi bi-sliders" aria-hidden="true"></i> Modifier le silo</a><?php endif; ?>
        </div>
    </header>

    <section class="silo-detail-overview" aria-label="État du silo">
        <div class="silo-visual is-<?= e($silo['health']) ?>"><span class="silo-health"><span aria-hidden="true">●</span> <?= e($silo['health_label']) ?></span><?php require __DIR__ . '/illustration.php'; ?></div>
        <div class="silo-detail-summary">
            <div class="silo-section-heading"><div><h3>Situation du stock</h3><p>Les quantités évoluent à chaque mouvement enregistré.</p></div><span class="silo-detail-occupation"><?= e($percent($silo['occupation'])) ?> <small>d’occupation</small></span></div>
            <div class="silo-kpis">
                <article class="silo-kpi silo-kpi-primary"><div><span>Stock actuel</span></div><strong><?= e($quantity($silo['current_stock_kg'])) ?> <small><?= e($unit) ?></small></strong></article>
                <article class="silo-kpi"><div><span><?= $silo['active'] ? 'Place disponible' : 'Capacité libre hors service' ?></span></div><strong><?= e($quantity($silo['available'])) ?> <small><?= e($unit) ?></small></strong></article>
                <article class="silo-kpi"><div><span>Capacité totale</span></div><strong><?= e($quantity($silo['capacity_kg'])) ?> <small><?= e($unit) ?></small></strong></article>
                <article class="silo-kpi"><div><span>Seuil de stock bas</span></div><strong><?= (float) $silo['alert_threshold_kg'] > 0 ? e($quantity($silo['alert_threshold_kg'])) . ' <small>' . e($unit) . '</small>' : 'Désactivé' ?></strong></article>
            </div>
            <div class="silo-detail-notice is-<?= e($silo['health']) ?>"><strong><?= e($silo['health_label']) ?></strong><p><?= e($notice[$silo['health']] ?? '') ?></p></div>
            <p class="silo-detail-last"><i class="bi bi-clock-history" aria-hidden="true"></i> Dernier mouvement : <strong><?= $latestMovement ? e($date($latestMovement['movement_at'])) : 'aucun mouvement enregistré' ?></strong></p>
        </div>
    </section>

    <section class="silo-detail-history" aria-labelledby="siloHistoryTitle">
        <div class="silo-section-heading"><div><h3 id="siloHistoryTitle">Activité du silo</h3><p>Les 10 dernières opérations de chaque catégorie. Les annulations restent visibles.</p></div></div>
        <nav class="silo-history-nav" aria-label="Catégorie d’activité"><?php foreach ($tabs as $key => $label): ?><a href="<?= e(base_url('silos/' . $silo['id']) . '?' . http_build_query(['tab' => $key, 'unit' => $unit]) . '#siloHistoryTitle') ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
        <?php if (!$rows): ?>
            <div class="silo-empty"><i class="bi bi-clock-history" aria-hidden="true"></i><h4>Aucune opération à afficher</h4><p><?= e(['movements' => 'Aucun mouvement de stock enregistré pour ce silo.', 'entries' => 'Aucune livraison reçue dans ce silo.', 'exits' => 'Aucune alimentation de machine enregistrée depuis ce silo.'][$tab]) ?></p></div>
        <?php else: ?>
            <div class="table-responsive"><table class="enterprise-table" data-search-placeholder="Rechercher dans les 10 dernières opérations">
                <caption class="sr-only"><?= e($tabs[$tab]) ?> de <?= e($silo['name']) ?> — quantités en <?= e($unit) ?></caption>
                <thead><tr><th>Date</th>
                    <?php if ($tab === 'movements'): ?><th>Opération</th><th>Quantité (<?= e($unit) ?>)</th><th>Stock avant (<?= e($unit) ?>)</th><th>Stock après (<?= e($unit) ?>)</th><th>Référence</th>
                    <?php elseif ($tab === 'entries'): ?><th>Référence</th><th>Fournisseur</th><th>Camion</th><th>Quantité (<?= e($unit) ?>)</th>
                    <?php else: ?><th>Machine</th><th>Produit</th><th>Quantité (<?= e($unit) ?>)</th><th>Agent</th><?php endif; ?>
                    <th>Statut</th></tr></thead>
                <tbody><?php foreach ($rows as $row): ?><tr>
                    <td><?= e($date($row[$tab === 'exits' ? 'fed_at' : 'movement_at'])) ?></td>
                    <?php if ($tab === 'movements'): ?>
                        <td><span class="silo-movement-type <?= $row['movement_type'] === 'in' ? 'is-in' : '' ?>"><?= e(['in' => 'Entrée', 'out' => 'Sortie', 'adjustment' => 'Ajustement'][$row['movement_type']] ?? $row['movement_type']) ?></span></td>
                        <td class="silo-movement-quantity"><?= $row['movement_type'] === 'in' ? '+' : ($row['movement_type'] === 'out' ? '−' : '') ?><?= e($quantity($row['quantity_kg'])) ?></td><td><?= e($quantity($row['stock_before_kg'])) ?></td><td class="silo-movement-quantity"><?= e($quantity($row['stock_after_kg'])) ?></td><td><?= e($row['weighing_reference'] ?: '—') ?></td>
                    <?php elseif ($tab === 'entries'): ?>
                        <td><strong><?= e($row['weighing_reference'] ?: '—') ?></strong></td><td><?= e($row['supplier_name'] ?: '—') ?></td><td><?= e($row['plate_number'] ?: '—') ?></td><td class="silo-movement-quantity"><?= e($quantity($row['quantity_kg'])) ?></td>
                    <?php else: ?>
                        <td><strong><?= e($row['machine_name']) ?></strong></td><td><?= e($row['product_name']) ?></td><td class="silo-movement-quantity"><?= e($quantity($row['quantity_kg'])) ?></td><td><?= e($row['created_by_name'] ?: '—') ?></td>
                    <?php endif; ?>
                    <td><span class="status-badge status-<?= e($row['status']) ?>"><?= e($statuses[$row['status']] ?? $row['status']) ?></span></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>
