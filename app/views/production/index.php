<?php
$totalTreated = 0;
$totalGood = 0;
$totalWaste = 0;
$pendingCount = 0;
$validatedCount = 0;

foreach ($batches as $batch) {
    $totalTreated += (float) $batch['input_quantity_kg'];
    $totalGood += (float) $batch['output_quantity_kg'];
    $totalWaste += (float) $batch['waste_quantity_kg'];
    $pendingCount += $batch['status'] === 'pending' ? 1 : 0;
    $validatedCount += $batch['status'] === 'validated' ? 1 : 0;
}

$averageYield = $totalTreated > 0 ? ($totalGood / $totalTreated) * 100 : 0;
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-gear-wide-connected"></i></span>
    <div>
        <p class="section-label">Production</p>
        <h2>Production farine</h2>
        <p>Validation des lots issus des alimentations machines et mise a jour des stocks farine/dechets.</p>
    </div>
    <a href="<?= e(base_url('production/create')) ?>" class="page-action"><i class="bi bi-plus-circle"></i><span>Nouvelle production</span></a>
</section>

<?php if (!empty($success)): ?><div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Lots en attente</span><span class="metric-icon tone-orange"><i class="bi bi-hourglass-split"></i></span></div><strong><?= e(number_format($pendingCount, 0, ',', ' ')) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Productions validees</span><span class="metric-icon tone-green"><i class="bi bi-check2-circle"></i></span></div><strong><?= e(number_format($validatedCount, 0, ',', ' ')) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Farine produite</span><span class="metric-icon tone-blue"><i class="bi bi-box-seam"></i></span></div><strong><?= e(number_format($totalGood, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Rendement moyen</span><span class="metric-icon tone-red"><i class="bi bi-speedometer2"></i></span></div><strong><?= e(number_format($averageYield, 1, ',', ' ')) ?>%</strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Historique productions</h3><p>Lots de traitement, quantites produites, dechets et rendement.</p></div></div>
    <div class="table-responsive">
        <table id="productionTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Lot</th>
                    <th>Machine</th>
                    <th>Quantite traitee</th>
                    <th>Bon produit</th>
                    <th>Dechets</th>
                    <th>Rendement</th>
                    <th>Date</th>
                    <th>Agent</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $batch): ?>
                    <?php
                    $yield = (float) $batch['input_quantity_kg'] > 0 ? ((float) $batch['output_quantity_kg'] / (float) $batch['input_quantity_kg']) * 100 : 0;
                    ?>
                    <tr>
                        <td><strong><?= e($batch['batch_number']) ?></strong></td>
                        <td><?= e($batch['machine_name']) ?></td>
                        <td><?= e(number_format((float) $batch['input_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $batch['output_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $batch['waste_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format($yield, 1, ',', ' ')) ?>%</td>
                        <td><?= e($batch['ended_at'] ?: $batch['started_at']) ?></td>
                        <td><?= e($batch['agent_name'] ?: '-') ?></td>
                        <td><span class="status-badge status-<?= e($batch['status']) ?>"><?= $batch['status'] === 'pending' ? 'En attente' : e($batch['status']) ?></span></td>
                        <td><a class="icon-button" href="<?= e(base_url('production/' . $batch['id'])) ?>" title="Voir"><i class="bi bi-eye"></i></a></td>
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
        jQuery('#productionTable').DataTable({
            pageLength: 10,
            order: [[6, 'desc']],
            language: {
                search: 'Recherche',
                lengthMenu: 'Afficher _MENU_ lignes',
                info: 'Affichage _START_ a _END_ sur _TOTAL_ productions',
                paginate: { previous: 'Precedent', next: 'Suivant' },
                zeroRecords: 'Aucune production trouvee'
            }
        });
    }
});
</script>
