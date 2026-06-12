<?php
$processedTotal = array_sum(array_map(function ($row) {
    return (float) $row['input_quantity_kg'];
}, $history));
$feedTotal = array_sum(array_map(function ($row) {
    return (float) $row['output_quantity_kg'];
}, $history));
$yield = $processedTotal > 0 ? ($feedTotal / $processedTotal) * 100 : 0;
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-clock-history"></i></span>
    <div>
        <p class="section-label">Historique</p>
        <h2>Traitements dechets</h2>
        <p>Operations validees, rendements et aliment betail produit.</p>
    </div>
    <a href="<?= e(base_url('waste/process')) ?>" class="page-action"><i class="bi bi-plus-circle"></i><span>Nouveau traitement</span></a>
</section>

<?php if (!empty($success)): ?><div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Stock restant</span><span class="metric-icon tone-orange"><i class="bi bi-recycle"></i></span></div><strong><?= e(number_format($availableStock, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Dechets traites</span><span class="metric-icon tone-blue"><i class="bi bi-arrow-down-up"></i></span></div><strong><?= e(number_format($processedTotal, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Aliment betail</span><span class="metric-icon tone-green"><i class="bi bi-basket2"></i></span></div><strong><?= e(number_format($feedTotal, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Rendement moyen</span><span class="metric-icon tone-red"><i class="bi bi-speedometer2"></i></span></div><strong><?= e(number_format($yield, 1, ',', ' ')) ?>%</strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Historique traitements</h3><p>Suivi par lot source, machine, date et agent.</p></div></div>
    <div class="table-responsive">
        <table id="wasteHistoryTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lot source</th>
                    <th>Machine</th>
                    <th>Dechets traites</th>
                    <th>Aliment betail</th>
                    <th>Rendement</th>
                    <th>Agent</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                    <?php $rowYield = (float) $row['input_quantity_kg'] > 0 ? ((float) $row['output_quantity_kg'] / (float) $row['input_quantity_kg']) * 100 : 0; ?>
                    <tr>
                        <td><?= e($row['processed_at']) ?></td>
                        <td><strong><?= e($row['batch_number'] ?: 'Stock dechets') ?></strong></td>
                        <td><?= e($row['machine_name'] ?: '-') ?></td>
                        <td><?= e(number_format((float) $row['input_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $row['output_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format($rowYield, 1, ',', ' ')) ?>%</td>
                        <td><?= e($row['agent_name'] ?: '-') ?></td>
                        <td><span class="status-badge status-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#wasteHistoryTable').DataTable({
            pageLength: 10,
            order: [[0, 'desc']],
            language: {
                search: 'Recherche',
                lengthMenu: 'Afficher _MENU_ lignes',
                info: 'Affichage _START_ a _END_ sur _TOTAL_ traitements',
                paginate: { previous: 'Precedent', next: 'Suivant' },
                zeroRecords: 'Aucun traitement trouve'
            }
        });
    }
});
</script>
