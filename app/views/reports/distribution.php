<?php
$basePath = $type === 'packaging' ? 'reports/packaging' : 'reports/distribution';
$query = http_build_query(array_merge($filters, ['type' => $type]));
$period = date('d/m/Y', strtotime($filters['start_date'])) . ' - ' . date('d/m/Y', strtotime($filters['end_date']));
$totalKg = array_sum(array_map(function ($row) { return (float) $row['total_weight_kg']; }, $rows));
$totalBags = array_sum(array_map(function ($row) use ($type) {
    return (int) ($type === 'packaging' ? $row['bags_count'] : $row['quantity_bags']);
}, $rows));
?>

<section class="dashboard-hero report-print-header">
    <span class="hero-icon"><i class="bi <?= $type === 'packaging' ? 'bi-box-seam' : 'bi-send-check' ?>"></i></span>
    <div>
        <p class="section-label">Rapports</p>
        <h2><?= e($title) ?></h2>
        <p>Periode <?= e($period) ?>. Genere le <?= e($generatedAt ?? date('d/m/Y H:i')) ?>.</p>
    </div>
</section>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Poids total</span><span class="metric-icon tone-green"><i class="bi bi-bar-chart"></i></span></div><strong><?= e(number_format($totalKg, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Sacs</span><span class="metric-icon tone-blue"><i class="bi bi-bag-check"></i></span></div><strong><?= e(number_format($totalBags, 0, ',', ' ')) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Operations</span><span class="metric-icon tone-orange"><i class="bi bi-list-check"></i></span></div><strong><?= e(count($rows)) ?></strong></article>
</section>

<section class="table-panel print-hidden">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-funnel"></i></span><div><h3>Filtres</h3><p>Periode.</p></div></div>
    <form method="get" action="<?= e(base_url($basePath)) ?>" class="enterprise-form">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="form-grid">
            <label><span>Date debut</span><input type="date" name="start_date" value="<?= e($filters['start_date']) ?>"></label>
            <label><span>Date fin</span><input type="date" name="end_date" value="<?= e($filters['end_date']) ?>"></label>
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
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3><?= e($title) ?></h3><p>Details operationnels.</p></div></div>
    <div class="table-responsive">
        <table class="enterprise-table">
            <thead>
                <tr>
                    <?php if ($type === 'packaging'): ?>
                        <th>Date</th><th>Lot</th><th>Produit</th><th>Format</th><th>Sacs</th><th>Poids</th><th>Agent</th><th>Statut</th>
                    <?php else: ?>
                        <th>Date</th><th>Bon</th><th>Destination</th><th>Transporteur</th><th>Produit</th><th>Format</th><th>Sacs</th><th>Poids</th><th>Agent</th><th>Statut</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php if ($type === 'packaging'): ?>
                            <td><?= e($row['packaged_at']) ?></td>
                            <td><strong><?= e($row['batch_number']) ?></strong></td>
                            <td><?= e($row['product_name']) ?></td>
                            <td><?= e($row['format_name']) ?></td>
                            <td><?= e(number_format((int) $row['bags_count'], 0, ',', ' ')) ?></td>
                            <td><?= e(number_format((float) $row['total_weight_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e($row['agent_name'] ?: '-') ?></td>
                            <td><?= e($row['status']) ?></td>
                        <?php else: ?>
                            <td><?= e($row['distributed_at']) ?></td>
                            <td><strong><?= e($row['exit_voucher']) ?></strong></td>
                            <td><?= e($row['recipient_name']) ?></td>
                            <td><?= e($row['transporter'] ?: '-') ?></td>
                            <td><?= e($row['product_name']) ?></td>
                            <td><?= e($row['format_name']) ?></td>
                            <td><?= e(number_format((int) $row['quantity_bags'], 0, ',', ' ')) ?></td>
                            <td><?= e(number_format((float) $row['total_weight_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e($row['agent_name'] ?: '-') ?></td>
                            <td><?= e($row['status']) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (!empty($printMode)): ?><script>window.print();</script><?php endif; ?>
