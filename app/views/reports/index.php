<?php
$query = http_build_query($filters);
$period = date('d/m/Y', strtotime($filters['start_date'])) . ' - ' . date('d/m/Y', strtotime($filters['end_date']));
$cards = [
    ['label' => 'Mais recu', 'value' => $summary['received_kg'], 'icon' => 'bi-truck', 'tone' => 'green'],
    ['label' => 'Production', 'value' => $summary['produced_kg'], 'icon' => 'bi-gear-wide-connected', 'tone' => 'blue'],
    ['label' => 'Emballage', 'value' => $summary['packaged_kg'], 'icon' => 'bi-box-seam', 'tone' => 'orange'],
    ['label' => 'Distribution', 'value' => $summary['distributed_kg'], 'icon' => 'bi-send-check', 'tone' => 'red'],
    ['label' => 'Rendement moyen', 'value' => $summary['average_yield'], 'icon' => 'bi-speedometer2', 'tone' => 'green', 'percent' => true],
    ['label' => 'Flux net stock fini', 'value' => $summary['net_finished_flow_kg'], 'icon' => 'bi-arrow-left-right', 'tone' => $summary['net_finished_flow_kg'] >= 0 ? 'green' : 'red'],
];
$reports = [
    ['title' => 'Rapport journalier reception', 'href' => 'reports/daily', 'icon' => 'bi-truck', 'tone' => 'green'],
    ['title' => 'Rapport journalier production', 'href' => 'reports/production', 'icon' => 'bi-gear-wide-connected', 'tone' => 'blue'],
    ['title' => 'Rapport stock silos', 'href' => 'reports/stocks', 'icon' => 'bi-database', 'tone' => 'orange'],
    ['title' => 'Rapport dechets', 'href' => 'reports/waste', 'icon' => 'bi-recycle', 'tone' => 'red'],
    ['title' => 'Rapport emballage', 'href' => 'reports/packaging', 'icon' => 'bi-box-seam', 'tone' => 'green'],
    ['title' => 'Rapport distribution', 'href' => 'reports/distribution', 'icon' => 'bi-send-check', 'tone' => 'blue'],
    ['title' => 'Rapport rendement machine', 'href' => 'reports/yield', 'icon' => 'bi-speedometer2', 'tone' => 'orange'],
    ['title' => 'Rapport fournisseur', 'href' => 'reports/supplier', 'icon' => 'bi-building-check', 'tone' => 'green'],
];
?>

<section class="dashboard-hero report-print-header">
    <span class="hero-icon"><i class="bi bi-file-earmark-bar-graph"></i></span>
    <div>
        <p class="section-label">Direction</p>
        <h2><?= e($title) ?></h2>
        <p>Periode <?= e($period) ?>. Genere le <?= e($generatedAt ?? date('d/m/Y H:i')) ?>.</p>
    </div>
</section>

<section class="metric-grid">
    <?php foreach ($cards as $card): ?>
        <article class="metric-card">
            <div class="metric-card-top">
                <span><?= e($card['label']) ?></span>
                <span class="metric-icon tone-<?= e($card['tone']) ?>"><i class="bi <?= e($card['icon']) ?>"></i></span>
            </div>
            <strong>
                <?php if (!empty($card['percent'])): ?>
                    <?= e(number_format((float) $card['value'], 1, ',', ' ')) ?>%
                <?php else: ?>
                    <?= e(number_format((float) $card['value'], 0, ',', ' ')) ?> kg
                <?php endif; ?>
            </strong>
        </article>
    <?php endforeach; ?>
</section>

<?php if (!empty($executiveSummary)): ?>
    <section class="executive-summary">
        <article class="executive-main tone-<?= e($executiveSummary['risk_level']) ?>">
            <span>Resume executif</span>
            <h3><?= e($executiveSummary['key_message']) ?></h3>
            <p><?= e($executiveSummary['risk_text']) ?></p>
        </article>
        <article>
            <span>Periode analysee</span>
            <strong><?= e((int) $executiveSummary['period_days']) ?> jour(s)</strong>
            <small>Du <?= e(date('d/m/Y', strtotime($filters['start_date']))) ?> au <?= e(date('d/m/Y', strtotime($filters['end_date']))) ?></small>
        </article>
        <article>
            <span>Moyenne reception</span>
            <strong><?= e(number_format((float) $executiveSummary['daily_received_kg'], 0, ',', ' ')) ?> kg/j</strong>
            <small>Volume moyen entrant</small>
        </article>
        <article>
            <span>Moyenne production</span>
            <strong><?= e(number_format((float) $executiveSummary['daily_produced_kg'], 0, ',', ' ')) ?> kg/j</strong>
            <small>Bon produit valide</small>
        </article>
        <article>
            <span>Moyenne distribution</span>
            <strong><?= e(number_format((float) $executiveSummary['daily_distributed_kg'], 0, ',', ' ')) ?> kg/j</strong>
            <small>Sorties validees</small>
        </article>
    </section>
<?php endif; ?>

<section class="table-panel print-hidden">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-funnel"></i></span>
        <div><h3>Filtres rapport periodique global</h3><p>Les filtres s appliquent au rapport direction et aux liens ci-dessous.</p></div>
    </div>
    <form method="get" action="<?= e(base_url('reports')) ?>" class="enterprise-form">
        <div class="form-grid">
            <label><span>Date debut</span><input type="date" name="start_date" value="<?= e($filters['start_date']) ?>"></label>
            <label><span>Date fin</span><input type="date" name="end_date" value="<?= e($filters['end_date']) ?>"></label>
            <label><span>Fournisseur</span><select name="supplier_id"><option value="">Tous</option><?php foreach ($references['suppliers'] as $supplier): ?><option value="<?= e($supplier['id']) ?>" <?= (string) $filters['supplier_id'] === (string) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name']) ?></option><?php endforeach; ?></select></label>
            <label><span>Machine</span><select name="machine_id"><option value="">Toutes</option><?php foreach ($references['machines'] as $machine): ?><option value="<?= e($machine['id']) ?>" <?= (string) $filters['machine_id'] === (string) $machine['id'] ? 'selected' : '' ?>><?= e($machine['name']) ?> - <?= e($machine['machine_type']) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="form-actions">
            <button class="btn-primary" type="submit"><i class="bi bi-search"></i><span>Appliquer</span></button>
            <a class="btn-secondary" href="<?= e(base_url('reports?export=excel&' . $query)) ?>"><i class="bi bi-file-earmark-spreadsheet"></i><span>Excel</span></a>
            <a class="btn-secondary" href="<?= e(base_url('reports?export=pdf&' . $query)) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i><span>PDF</span></a>
            <a class="btn-secondary" href="<?= e(base_url('reports?export=print&' . $query)) ?>" target="_blank"><i class="bi bi-printer"></i><span>Imprimer</span></a>
        </div>
    </form>
</section>

<?php if (!empty($highlights)): ?>
    <section class="report-insights">
        <article class="table-panel">
            <div class="panel-heading">
                <span class="panel-icon"><i class="bi bi-building-check"></i></span>
                <div><h3>Top fournisseurs</h3><p>Volumes receptionnes sur la periode.</p></div>
            </div>
            <div class="table-responsive">
                <table class="enterprise-table">
                    <thead><tr><th>Fournisseur</th><th>Camions</th><th>Poids net</th></tr></thead>
                    <tbody>
                        <?php foreach ($highlights['topSuppliers'] as $row): ?>
                            <tr>
                                <td><strong><?= e($row['supplier_name']) ?></strong></td>
                                <td><?= e(number_format((int) $row['trucks_count'], 0, ',', ' ')) ?></td>
                                <td><?= e(number_format((float) $row['net_weight_kg'], 0, ',', ' ')) ?> kg</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($highlights['topSuppliers'])): ?>
                            <tr><td colspan="3">Aucune reception sur la periode.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="table-panel">
            <div class="panel-heading">
                <span class="panel-icon"><i class="bi bi-speedometer2"></i></span>
                <div><h3>Rendement machines</h3><p>Machines avec production sur la periode.</p></div>
            </div>
            <div class="table-responsive">
                <table class="enterprise-table">
                    <thead><tr><th>Machine</th><th>Lots</th><th>Rendement</th></tr></thead>
                    <tbody>
                        <?php foreach ($highlights['machineYields'] as $row): ?>
                            <tr>
                                <td><strong><?= e($row['machine_name']) ?></strong></td>
                                <td><?= e(number_format((int) $row['batches_count'], 0, ',', ' ')) ?></td>
                                <td><?= e(number_format((float) $row['yield_rate'], 1, ',', ' ')) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($highlights['machineYields'])): ?>
                            <tr><td colspan="3">Aucune production sur la periode.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
<?php endif; ?>

<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-clipboard-data"></i></span>
        <div><h3>Rapport direction</h3><p>Synthese operationnelle sur la periode.</p></div>
    </div>
    <div class="table-responsive">
        <table class="enterprise-table">
            <thead><tr><th>Indicateur</th><th>Operations</th><th>Quantite</th></tr></thead>
            <tbody>
                <?php foreach ($directionRows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['label']) ?></strong></td>
                        <td><?= e(number_format((int) $row['count'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['quantity'], 0, ',', ' ')) ?> kg</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="metric-grid print-hidden">
    <?php foreach ($reports as $report): ?>
        <?php $separator = strpos($report['href'], '?') === false ? '?' : '&'; ?>
        <a class="metric-card" href="<?= e(base_url($report['href'] . $separator . $query)) ?>">
            <div class="metric-card-top">
                <span><?= e($report['title']) ?></span>
                <span class="metric-icon tone-<?= e($report['tone']) ?>"><i class="bi <?= e($report['icon']) ?>"></i></span>
            </div>
            <strong>Ouvrir</strong>
        </a>
    <?php endforeach; ?>
</section>

<?php if (!empty($printMode)): ?><script>window.print();</script><?php endif; ?>
