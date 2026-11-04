<?php
$availableTotal = array_sum(array_map(function ($batch) {
    return (float) $batch['available_quantity_kg'];
}, $availableProducts));
$packagedTotal = array_sum(array_map(function ($row) {
    return in_array($row['status'], ['validated', 'active'], true) ? (float) $row['total_weight_kg'] : 0;
}, $history));
$bagsTotal = array_sum(array_map(function ($row) {
    return in_array($row['status'], ['validated', 'active'], true) ? (int) $row['bags_count'] : 0;
}, $history));
?>

<div data-pack-directory class="pack-directory">
<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-box-seam"></i></span>
    <div>
        <p class="section-label">Emballage</p>
        <h2>Conditionnement</h2>
        <p>Conditionnez les lots disponibles et retrouvez les opérations enregistrées.</p>
    </div>
    <?php if(Auth::can('packaging','create')): ?><button type="button" class="page-action" data-workspace-modal-open="packagingEditor"><i class="bi bi-plus-circle"></i><span>Nouvel emballage</span></button><?php endif; ?>
    <a href="<?=e(base_url('empty-packaging'))?>" class="page-action"><i class="bi bi-bag"></i><span>Sacs vides</span></a>
</section>

<?php if (!empty($success)): ?><div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>

<section class="metric-grid pack-metrics">
    <article class="metric-card"><div class="metric-card-top"><span>Disponible à emballer</span><span class="metric-icon tone-blue"><i class="bi bi-box"></i></span></div><strong><?= e(number_format($availableTotal, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Lots en vrac</span><span class="metric-icon tone-orange"><i class="bi bi-list-check"></i></span></div><strong><?= e(array_sum(array_map(function ($s) { return (int) $s['lots_count']; }, $availableProducts))) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Poids conditionné (historique)</span><span class="metric-icon tone-green"><i class="bi bi-check2-circle"></i></span></div><strong><?= e(number_format($packagedTotal, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Sacs produits</span><span class="metric-icon tone-red"><i class="bi bi-bag-check"></i></span></div><strong><?= e(number_format($bagsTotal, 0, ',', ' ')) ?></strong></article>
</section>

<nav class="pellet-tabs" aria-label="Conditionnement"><button type="button" data-pack-tab="available" aria-pressed="true"><i class="bi bi-box-seam"></i> Farine disponible</button><button type="button" data-pack-tab="history" aria-pressed="false"><i class="bi bi-clock-history"></i> Historique</button></nav>
<section class="table-panel" data-pack-space="available">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Farine en vrac disponible</h3><p>Stock global par produit et par site. Le conditionnement prélève automatiquement les lots les plus anciens.</p></div></div>
    <div class="table-responsive">
        <table id="packagingAvailableTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Site</th>
                    <th>Lots en vrac</th>
                    <th>Plus ancien lot</th>
                    <th>Disponible</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($availableProducts as $stock): ?>
                    <tr>
                        <td><strong><?= e($stock['product_name']) ?></strong></td>
                        <td><?= e($stock['site_code']) ?></td>
                        <td><?= e((int) $stock['lots_count']) ?></td>
                        <td><?= e($stock['oldest_at'] ? date('d/m/Y', strtotime($stock['oldest_at'])) : '—') ?></td>
                        <td><strong><?= e(number_format((float) $stock['available_quantity_kg'], 0, ',', ' ')) ?> kg</strong></td>
                        <td><?php if(Auth::can('packaging','create')): ?><button type="button" class="btn-secondary" data-workspace-modal-open="packagingEditor" data-pack-lot="<?=e(Packaging::stockKey($stock['site_id'], $stock['product_id']))?>"><i class="bi bi-bag-plus"></i> Conditionner</button><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="table-panel" data-pack-space="history" hidden>
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Historique emballage</h3><p>Recherchez un lot, un produit, une date ou un agent.</p></div></div>
    <div class="table-responsive">
        <table id="packagingHistoryTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lot</th>
                    <th>Produit</th>
                    <th>Format</th>
                    <th>Sacs</th>
                    <th>Poids total</th>
                    <th>Agent</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                    <tr>
                        <td><?= e($row['packaged_at']) ?></td>
                        <td><strong><?= e($row['batch_number']) ?></strong></td>
                        <td><?= e($row['product_name']) ?></td>
                        <td><?= e($row['format_name']) ?></td>
                        <td><?= e(number_format((int) $row['bags_count'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['total_weight_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e($row['agent_name'] ?: '-') ?></td>
                        <td><span class="status-badge status-<?= e($row['status']) ?>"><?= e(['validated'=>'Validé','active'=>'Actif','cancelled'=>'Annulé'][$row['status']] ?? $row['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if(Auth::can('packaging','create')) require __DIR__.'/modal.php'; ?>
</div>
