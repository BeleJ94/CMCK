<?php
$basePath = $type === 'supplier' ? 'reports/supplier' : 'reports/daily';
$query = http_build_query(array_merge($filters, ['type' => $type]));
$period = date('d/m/Y', strtotime($filters['start_date'])) . ' - ' . date('d/m/Y', strtotime($filters['end_date']));
$totalKg = array_sum(array_map(function ($row) use ($type) {
    return $type === 'supplier' ? (float) $row['net_weight_kg'] : (float) $row['poids_net'];
}, $rows));
$totalTrucks = array_sum(array_map(function ($row) use ($type) {
    return $type === 'supplier' ? (int) $row['trucks_count'] : 1;
}, $rows));
?>

<section class="dashboard-hero report-print-header">
    <span class="hero-icon"><i class="bi bi-truck"></i></span>
    <div>
        <p class="section-label">Rapports</p>
        <h2><?= e($title) ?></h2>
        <p>Periode <?= e($period) ?>. Genere le <?= e($generatedAt ?? date('d/m/Y H:i')) ?>.</p>
    </div>
</section>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Total net</span><span class="metric-icon tone-green"><i class="bi bi-bar-chart"></i></span></div><strong><?= e(number_format($totalKg, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Camions</span><span class="metric-icon tone-blue"><i class="bi bi-truck-front"></i></span></div><strong><?= e(number_format($totalTrucks, 0, ',', ' ')) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Lignes</span><span class="metric-icon tone-orange"><i class="bi bi-list-check"></i></span></div><strong><?= e(count($rows)) ?></strong></article>
</section>

<section class="table-panel print-hidden">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-funnel"></i></span><div><h3>Filtres</h3><p>Periode et fournisseur.</p></div></div>
    <form method="get" action="<?= e(base_url($basePath)) ?>" class="enterprise-form">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="form-grid">
            <label><span>Date debut</span><input type="date" name="start_date" value="<?= e($filters['start_date']) ?>"></label>
            <label><span>Date fin</span><input type="date" name="end_date" value="<?= e($filters['end_date']) ?>"></label>
            <label><span>Fournisseur</span><select name="supplier_id"><option value="">Tous</option><?php foreach ($references['suppliers'] as $supplier): ?><option value="<?= e($supplier['id']) ?>" <?= (string) $filters['supplier_id'] === (string) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name']) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="form-actions">
            <button class="btn-primary"><i class="bi bi-search"></i><span>Filtrer</span></button>
            <a class="btn-secondary" href="<?= e(base_url($basePath . '?export=excel&' . $query)) ?>"><i class="bi bi-file-earmark-spreadsheet"></i><span>Excel</span></a>
            <a class="btn-secondary" href="<?= e(base_url($basePath . '?export=pdf&' . $query)) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i><span>PDF</span></a>
            <a class="btn-secondary" href="<?= e(base_url($basePath . '?export=print&' . $query)) ?>" target="_blank"><i class="bi bi-printer"></i><span>Imprimer</span></a>
        </div>
    </form>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3><?= e($title) ?></h3><p>Resultats consolides.</p></div></div>
    <div class="table-responsive">
        <table class="enterprise-table">
            <thead>
                <tr>
                    <?php if ($type === 'supplier'): ?>
                        <th>Fournisseur</th><th>Camions</th><th>Poids net</th>
                    <?php else: ?>
                        <th>Date</th><th>Reference</th><th>Fournisseur</th><th>Camion</th><th>Chauffeur</th><th>Produit</th><th>Brut</th><th>Tare</th><th>Net</th><th>Statut</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php if ($type === 'supplier'): ?>
                            <td><strong><?= e($row['supplier_name']) ?></strong></td>
                            <td><?= e(number_format((int) $row['trucks_count'], 0, ',', ' ')) ?></td>
                            <td><?= e(number_format((float) $row['net_weight_kg'], 0, ',', ' ')) ?> kg</td>
                        <?php else: ?>
                            <td><?= e($row['weighed_at']) ?></td>
                            <td><strong><?= e($row['reference']) ?></strong></td>
                            <td><?= e($row['supplier_name']) ?></td>
                            <td><?= e($row['plate_number']) ?></td>
                            <td><?= e($row['driver_name'] ?: '-') ?></td>
                            <td><?= e($row['product_name']) ?></td>
                            <td><?= e(number_format((float) $row['poids_brut'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['poids_tare'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['poids_net'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e($row['status']) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (!empty($printMode)): ?><script>window.print();</script><?php endif; ?>
