<?php
$availableBags = array_sum(array_map(function ($row) {
    return (int) $row['quantity_bags'];
}, $availableStocks));
$availableKg = array_sum(array_map(function ($row) {
    return (float) $row['total_weight_kg'];
}, $availableStocks));
$distributedKg = array_sum(array_map(function ($row) {
    return (float) $row['total_weight_kg'];
}, $distributions));
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-send-check"></i></span>
    <div>
        <p class="section-label">Distribution</p>
        <h2>Sorties produits finis</h2>
        <p>Creation des bons de sortie, diminution du stock fini et historique des distributions.</p>
    </div>
    <a href="<?= e(base_url('distributions/create')) ?>" class="page-action"><i class="bi bi-plus-circle"></i><span>Nouvelle sortie</span></a>
</section>

<?php if (!empty($success)): ?><div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Stock disponible</span><span class="metric-icon tone-green"><i class="bi bi-boxes"></i></span></div><strong><?= e(number_format($availableKg, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Sacs disponibles</span><span class="metric-icon tone-blue"><i class="bi bi-bag-check"></i></span></div><strong><?= e(number_format($availableBags, 0, ',', ' ')) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Distribue</span><span class="metric-icon tone-orange"><i class="bi bi-arrow-up-circle"></i></span></div><strong><?= e(number_format($distributedKg, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Bons de sortie</span><span class="metric-icon tone-red"><i class="bi bi-receipt"></i></span></div><strong><?= e(count($distributions)) ?></strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Historique distributions</h3><p>Bons de sortie, destinations, transporteurs et quantites sorties.</p></div></div>
    <div class="table-responsive">
        <table id="distributionsTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Bon</th>
                    <th>Destination</th>
                    <th>Produit</th>
                    <th>Format</th>
                    <th>Sacs</th>
                    <th>Poids</th>
                    <th>Transporteur</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($distributions as $row): ?>
                    <tr>
                        <td><?= e($row['distributed_at']) ?></td>
                        <td><strong><?= e($row['exit_voucher']) ?></strong></td>
                        <td><?= e($row['recipient_name']) ?></td>
                        <td><?= e($row['product_name']) ?></td>
                        <td><?= e($row['format_name']) ?></td>
                        <td><?= e(number_format((int) $row['quantity_bags'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['total_weight_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e($row['transporter'] ?: '-') ?></td>
                        <td><a class="icon-button" href="<?= e(base_url('distributions/' . $row['id'])) ?>" title="Voir"><i class="bi bi-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
