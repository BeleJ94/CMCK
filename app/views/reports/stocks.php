<?php
$query = http_build_query($filters);
$totalSiloStock = array_sum(array_map(function ($row) { return (float) $row['current_stock_kg']; }, $silos));
$totalSiloCapacity = array_sum(array_map(function ($row) { return (float) $row['capacity_kg']; }, $silos));
$totalFinishedWeight = array_sum(array_map(function ($row) { return (float) $row['total_weight_kg']; }, $finishedStocks));
$fillRate = $totalSiloCapacity > 0 ? ($totalSiloStock / $totalSiloCapacity) * 100 : 0;
?>

<section class="dashboard-hero report-print-header">
    <span class="hero-icon"><i class="bi bi-database"></i></span>
    <div>
        <p class="section-label">Rapports</p>
        <h2><?= e($title) ?></h2>
        <p>Etat instantane des stocks. Genere le <?= e($generatedAt ?? date('d/m/Y H:i')) ?>.</p>
    </div>
</section>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Stock silos</span><span class="metric-icon tone-green"><i class="bi bi-database-check"></i></span></div><strong><?= e(number_format($totalSiloStock, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Capacite silos</span><span class="metric-icon tone-blue"><i class="bi bi-diagram-3"></i></span></div><strong><?= e(number_format($totalSiloCapacity, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Remplissage</span><span class="metric-icon tone-orange"><i class="bi bi-speedometer2"></i></span></div><strong><?= e(number_format($fillRate, 1, ',', ' ')) ?>%</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Stock fini</span><span class="metric-icon tone-red"><i class="bi bi-boxes"></i></span></div><strong><?= e(number_format($totalFinishedWeight, 0, ',', ' ')) ?> kg</strong></article>
</section>

<section class="table-panel print-hidden">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-download"></i></span><div><h3>Export</h3><p>Generer une sortie Excel, PDF ou impression.</p></div></div>
    <div class="form-actions">
        <a class="btn-secondary" href="<?= e(base_url('reports/stocks?export=excel&' . $query)) ?>"><i class="bi bi-file-earmark-spreadsheet"></i><span>Excel</span></a>
        <a class="btn-secondary" href="<?= e(base_url('reports/stocks?export=pdf&' . $query)) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i><span>PDF</span></a>
        <a class="btn-secondary" href="<?= e(base_url('reports/stocks?export=print&' . $query)) ?>" target="_blank"><i class="bi bi-printer"></i><span>Imprimer</span></a>
    </div>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-database"></i></span><div><h3>Stock silos</h3><p>Capacite, stock actuel, taux de remplissage et seuils.</p></div></div>
    <div class="table-responsive">
        <table class="enterprise-table">
            <thead><tr><th>Silo</th><th>Code</th><th>Produit</th><th>Capacite</th><th>Stock</th><th>Remplissage</th><th>Seuil</th><th>Statut</th></tr></thead>
            <tbody>
                <?php foreach ($silos as $row): ?>
                    <tr>
                        <td><strong><?= e($row['name']) ?></strong></td>
                        <td><?= e($row['code']) ?></td>
                        <td><?= e($row['product_name'] ?: '-') ?></td>
                        <td><?= e(number_format((float) $row['capacity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $row['current_stock_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $row['fill_rate'], 1, ',', ' ')) ?>%</td>
                        <td><?= e(number_format((float) $row['alert_threshold_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e($row['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-boxes"></i></span><div><h3>Stock produits finis</h3><p>Soldes disponibles par produit et format.</p></div></div>
    <div class="table-responsive">
        <table class="enterprise-table">
            <thead><tr><th>Produit</th><th>Format</th><th>Sacs</th><th>Poids total</th></tr></thead>
            <tbody>
                <?php foreach ($finishedStocks as $row): ?>
                    <tr>
                        <td><strong><?= e($row['product_name']) ?></strong></td>
                        <td><?= e($row['format_name']) ?></td>
                        <td><?= e(number_format((int) $row['quantity_bags'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['total_weight_kg'], 0, ',', ' ')) ?> kg</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (!empty($printMode)): ?><script>window.print();</script><?php endif; ?>
