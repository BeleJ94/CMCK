<?php
$activeLines = array_filter($stockLines, function ($line) {
    return (float) $line['quantity_kg'] > 0 && $line['status'] === 'active';
});
$processedTotal = array_sum(array_map(function ($row) {
    return (float) $row['input_quantity_kg'];
}, $history));
$feedTotal = array_sum(array_map(function ($row) {
    return (float) $row['output_quantity_kg'];
}, $history));
$yield = $processedTotal > 0 ? ($feedTotal / $processedTotal) * 100 : 0;
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-recycle"></i></span>
    <div>
        <p class="section-label">Production</p>
        <h2>Module dechets</h2>
        <p>Stock dechets disponible, traitements vers machine dechets et production aliment betail.</p>
    </div>
    <a href="<?= e(base_url('waste/process')) ?>" class="page-action"><i class="bi bi-plus-circle"></i><span>Traiter des dechets</span></a>
</section>

<?php if (!empty($success)): ?><div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Stock disponible</span><span class="metric-icon tone-orange"><i class="bi bi-recycle"></i></span></div><strong><?= e(number_format($availableStock, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Lignes actives</span><span class="metric-icon tone-blue"><i class="bi bi-list-check"></i></span></div><strong><?= e(count($activeLines)) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Dechets traites</span><span class="metric-icon tone-green"><i class="bi bi-gear-wide-connected"></i></span></div><strong><?= e(number_format($processedTotal, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Rendement moyen</span><span class="metric-icon tone-red"><i class="bi bi-speedometer2"></i></span></div><strong><?= e(number_format($yield, 1, ',', ' ')) ?>%</strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-database"></i></span><div><h3>Stock dechets disponible</h3><p>Lignes de stock issues des productions farine.</p></div></div>
    <div class="table-responsive">
        <table id="wasteStockTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Origine</th>
                    <th>Produit</th>
                    <th>Quantite disponible</th>
                    <th>Statut</th>
                    <th>Date creation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stockLines as $line): ?>
                    <tr>
                        <td><strong><?= e($line['batch_number'] ?: 'Stock dechets') ?></strong></td>
                        <td><?= e($line['product_name']) ?></td>
                        <td><?= e(number_format((float) $line['quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><span class="status-badge status-<?= e($line['status']) ?>"><?= e($line['status']) ?></span></td>
                        <td><?= e($line['created_at']) ?></td>
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
        jQuery('#wasteStockTable').DataTable({
            pageLength: 10,
            order: [[4, 'asc']],
            language: {
                search: 'Recherche',
                lengthMenu: 'Afficher _MENU_ lignes',
                info: 'Affichage _START_ a _END_ sur _TOTAL_ stocks',
                paginate: { previous: 'Precedent', next: 'Suivant' },
                zeroRecords: 'Aucun stock dechets trouve'
            }
        });
    }
});
</script>
