<?php
$basePath = $type === 'waste' ? 'reports/waste' : ($type === 'yield' ? 'reports/yield' : 'reports/production');
$query = http_build_query(array_merge($filters, ['type' => $type]));
$period = date('d/m/Y', strtotime($filters['start_date'])) . ' - ' . date('d/m/Y', strtotime($filters['end_date']));
$totalInput = array_sum(array_map(function ($row) {
    return (float) $row['input_quantity_kg'];
}, $rows));
$totalOutput = array_sum(array_map(function ($row) {
    return (float) $row['output_quantity_kg'];
}, $rows));
$totalWaste = array_sum(array_map(function ($row) use ($type) {
    return $type === 'waste' ? 0 : (float) ($row['waste_quantity_kg'] ?? 0);
}, $rows));
$yield = $totalInput > 0 ? ($totalOutput / $totalInput) * 100 : 0;
?>

<section class="dashboard-hero report-print-header">
    <span class="hero-icon"><i class="bi bi-gear-wide-connected"></i></span>
    <div>
        <p class="section-label">Rapports</p>
        <h2><?= e($title) ?></h2>
        <p>Periode <?= e($period) ?>. Genere le <?= e($generatedAt ?? date('d/m/Y H:i')) ?>.</p>
    </div>
</section>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Entree</span><span class="metric-icon tone-blue"><i class="bi bi-arrow-down"></i></span></div><strong><?= e(number_format($totalInput, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Sortie</span><span class="metric-icon tone-green"><i class="bi bi-arrow-up"></i></span></div><strong><?= e(number_format($totalOutput, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Dechets</span><span class="metric-icon tone-red"><i class="bi bi-recycle"></i></span></div><strong><?= e(number_format($totalWaste, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Rendement</span><span class="metric-icon tone-orange"><i class="bi bi-speedometer2"></i></span></div><strong><?= e(number_format($yield, 1, ',', ' ')) ?>%</strong></article>
</section>

<section class="table-panel print-hidden">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-funnel"></i></span><div><h3>Filtres</h3><p>Periode et machine.</p></div></div>
    <form method="get" action="<?= e(base_url($basePath)) ?>" class="enterprise-form">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="form-grid">
            <label><span>Date debut</span><input type="date" name="start_date" value="<?= e($filters['start_date']) ?>"></label>
            <label><span>Date fin</span><input type="date" name="end_date" value="<?= e($filters['end_date']) ?>"></label>
            <label><span>Machine</span><select name="machine_id"><option value="">Toutes</option><?php foreach ($references['machines'] as $machine): ?><option value="<?= e($machine['id']) ?>" <?= (string) $filters['machine_id'] === (string) $machine['id'] ? 'selected' : '' ?>><?= e($machine['name']) ?> - <?= e($machine['machine_type']) ?></option><?php endforeach; ?></select></label>
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
                    <?php if ($type === 'waste'): ?>
                        <th>Date</th><th>Machine</th><th>Lot source</th><th>Dechets traites</th><th>Aliment betail</th><th>Rendement</th><th>Agent</th>
                    <?php elseif ($type === 'yield'): ?>
                        <th>Machine</th><th>Lots</th><th>Entree</th><th>Sortie</th><th>Dechets</th><th>Rendement</th>
                    <?php else: ?>
                        <th>Date debut</th><th>Date fin</th><th>Lot</th><th>Machine</th><th>Produit</th><th>Traite</th><th>Bon produit</th><th>Dechets</th><th>Rendement</th><th>Statut</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php if ($type === 'waste'): ?>
                            <td><?= e($row['processed_at']) ?></td>
                            <td><?= e($row['machine_name'] ?: '-') ?></td>
                            <td><?= e($row['batch_number'] ?: '-') ?></td>
                            <td><?= e(number_format((float) $row['input_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['output_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['yield_rate'], 1, ',', ' ')) ?>%</td>
                            <td><?= e($row['agent_name'] ?: '-') ?></td>
                        <?php elseif ($type === 'yield'): ?>
                            <td><strong><?= e($row['machine_name']) ?></strong></td>
                            <td><?= e(number_format((int) $row['batches_count'], 0, ',', ' ')) ?></td>
                            <td><?= e(number_format((float) $row['input_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['output_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['waste_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['yield_rate'], 1, ',', ' ')) ?>%</td>
                        <?php else: ?>
                            <td><?= e($row['started_at']) ?></td>
                            <td><?= e($row['ended_at'] ?: '-') ?></td>
                            <td><strong><?= e($row['batch_number']) ?></strong></td>
                            <td><?= e($row['machine_name']) ?></td>
                            <td><?= e($row['product_name']) ?></td>
                            <td><?= e(number_format((float) $row['input_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['output_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['waste_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                            <td><?= e(number_format((float) $row['yield_rate'], 1, ',', ' ')) ?>%</td>
                            <td><?= e($row['status']) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (!empty($printMode)): ?><script>window.print();</script><?php endif; ?>
