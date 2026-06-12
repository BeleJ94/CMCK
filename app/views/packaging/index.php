<?php
$availableTotal = array_sum(array_map(function ($batch) {
    return (float) $batch['available_quantity_kg'];
}, $availableBatches));
$packagedTotal = array_sum(array_map(function ($row) {
    return (float) $row['total_weight_kg'];
}, $history));
$bagsTotal = array_sum(array_map(function ($row) {
    return (int) $row['bags_count'];
}, $history));
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-box-seam"></i></span>
    <div>
        <p class="section-label">Emballage</p>
        <h2>Stock produit fini</h2>
        <p>Lots disponibles pour emballage, formats sacs et mouvements d entree stock.</p>
    </div>
    <a href="<?= e(base_url('packaging/create')) ?>" class="page-action"><i class="bi bi-plus-circle"></i><span>Nouvel emballage</span></a>
</section>

<?php if (!empty($success)): ?><div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Disponible a emballer</span><span class="metric-icon tone-blue"><i class="bi bi-box"></i></span></div><strong><?= e(number_format($availableTotal, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Lots disponibles</span><span class="metric-icon tone-orange"><i class="bi bi-list-check"></i></span></div><strong><?= e(count($availableBatches)) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Poids emballe</span><span class="metric-icon tone-green"><i class="bi bi-check2-circle"></i></span></div><strong><?= e(number_format($packagedTotal, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Sacs produits</span><span class="metric-icon tone-red"><i class="bi bi-bag-check"></i></span></div><strong><?= e(number_format($bagsTotal, 0, ',', ' ')) ?></strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Lots disponibles</h3><p>Quantite produite, deja emballee et disponible.</p></div></div>
    <div class="table-responsive">
        <table id="packagingAvailableTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Lot</th>
                    <th>Produit</th>
                    <th>Machine</th>
                    <th>Produit</th>
                    <th>Emballe</th>
                    <th>Disponible</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($availableBatches as $batch): ?>
                    <tr>
                        <td><strong><?= e($batch['batch_number']) ?></strong></td>
                        <td><?= e($batch['product_name']) ?></td>
                        <td><?= e($batch['machine_name']) ?></td>
                        <td><?= e(number_format((float) $batch['output_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $batch['packaged_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><strong><?= e(number_format((float) $batch['available_quantity_kg'], 0, ',', ' ')) ?> kg</strong></td>
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
        jQuery('#packagingAvailableTable').DataTable({
            pageLength: 10,
            language: {
                search: 'Recherche',
                lengthMenu: 'Afficher _MENU_ lignes',
                info: 'Affichage _START_ a _END_ sur _TOTAL_ lots',
                paginate: { previous: 'Precedent', next: 'Suivant' },
                zeroRecords: 'Aucun lot disponible'
            }
        });
    }
});
</script>
