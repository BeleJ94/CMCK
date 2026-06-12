<?php
$availableKg = array_sum(array_map(function ($row) {
    return (float) $row['available_kg'];
}, $productStock));
$availableBags = array_sum(array_map(function ($row) {
    return (int) $row['available_bags'];
}, $productStock));
$entriesKg = array_sum(array_map(function ($row) {
    return (float) $row['entries_kg'];
}, $productStock));
$outputsKg = array_sum(array_map(function ($row) {
    return (float) $row['outputs_kg'];
}, $productStock));
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-boxes"></i></span>
    <div>
        <p class="section-label">Stock</p>
        <h2>Produits finis</h2>
        <p>Farine de mais et aliment pour betail: solde disponible, formats, entrees et sorties.</p>
    </div>
    <a href="<?= e(base_url('finished-stocks/movements')) ?>" class="page-action"><i class="bi bi-clock-history"></i><span>Mouvements</span></a>
</section>

<?php foreach ($alerts as $alert): ?>
    <div class="app-alert <?= $alert['severity'] === 'danger' ? 'app-alert-error' : 'app-alert-success' ?>"><i class="bi <?= $alert['severity'] === 'danger' ? 'bi-exclamation-triangle' : 'bi-info-circle' ?>"></i><span><strong><?= e($alert['title']) ?></strong> - <?= e($alert['message']) ?></span></div>
<?php endforeach; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Solde disponible</span><span class="metric-icon tone-green"><i class="bi bi-box-seam"></i></span></div><strong><?= e(number_format($availableKg, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Sacs disponibles</span><span class="metric-icon tone-blue"><i class="bi bi-bag-check"></i></span></div><strong><?= e(number_format($availableBags, 0, ',', ' ')) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Entrees emballage</span><span class="metric-icon tone-orange"><i class="bi bi-arrow-down-circle"></i></span></div><strong><?= e(number_format($entriesKg, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Sorties distribution</span><span class="metric-icon tone-red"><i class="bi bi-arrow-up-circle"></i></span></div><strong><?= e(number_format($outputsKg, 0, ',', ' ')) ?> kg</strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Stock par produit</h3><p>Solde disponible, entrees emballage et sorties distribution.</p></div></div>
    <div class="table-responsive">
        <table id="finishedProductTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Entrees kg</th>
                    <th>Sorties kg</th>
                    <th>Solde kg</th>
                    <th>Sacs disponibles</th>
                    <th>Alerte</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productStock as $row): ?>
                    <?php $available = (float) $row['available_kg']; ?>
                    <tr>
                        <td><strong><?= e($row['name']) ?></strong><br><small><?= e($row['code']) ?></small></td>
                        <td><?= e(number_format((float) $row['entries_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $row['outputs_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><strong><?= e(number_format($available, 0, ',', ' ')) ?> kg</strong></td>
                        <td><?= e(number_format((int) $row['available_bags'], 0, ',', ' ')) ?></td>
                        <td><span class="status-badge status-<?= $available <= 0 ? 'cancelled' : ($available <= 500 ? 'pending' : 'validated') ?>"><?= $available <= 0 ? 'Rupture' : ($available <= 500 ? 'Stock faible' : 'Disponible') ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-grid-3x3-gap"></i></span><div><h3>Stock par format</h3><p>Repartition des produits finis par format sac.</p></div></div>
    <div class="table-responsive">
        <table id="finishedFormatTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Format</th>
                    <th>Sacs disponibles</th>
                    <th>Solde kg</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($formatStock as $row): ?>
                    <tr>
                        <td><strong><?= e($row['product_name']) ?></strong></td>
                        <td><?= e($row['format_name']) ?></td>
                        <td><?= e(number_format((int) $row['available_bags'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['available_kg'], 0, ',', ' ')) ?> kg</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-arrow-down-circle"></i></span><div><h3>Dernieres entrees emballage</h3><p>Lots emballes et ajoutes au stock produit fini.</p></div></div>
    <div class="table-responsive">
        <table id="finishedEntriesTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lot</th>
                    <th>Produit</th>
                    <th>Format</th>
                    <th>Sacs</th>
                    <th>Poids</th>
                    <th>Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $row): ?>
                    <tr>
                        <td><?= e($row['packaged_at']) ?></td>
                        <td><strong><?= e($row['batch_number']) ?></strong></td>
                        <td><?= e($row['product_name']) ?></td>
                        <td><?= e($row['format_name']) ?></td>
                        <td><?= e(number_format((int) $row['bags_count'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['total_weight_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e($row['agent_name'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-arrow-up-circle"></i></span><div><h3>Dernieres sorties distribution</h3><p>Sorties validees vers les beneficiaires et clients.</p></div></div>
    <div class="table-responsive">
        <table id="finishedOutputsTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Destinataire</th>
                    <th>Produit</th>
                    <th>Format</th>
                    <th>Sacs</th>
                    <th>Poids</th>
                    <th>Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($outputs as $row): ?>
                    <tr>
                        <td><?= e($row['distributed_at']) ?></td>
                        <td><strong><?= e($row['recipient_name']) ?></strong></td>
                        <td><?= e($row['product_name']) ?></td>
                        <td><?= e($row['format_name']) ?></td>
                        <td><?= e(number_format((int) $row['quantity_bags'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['total_weight_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e($row['agent_name'] ?: '-') ?></td>
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
        ['#finishedProductTable', '#finishedFormatTable', '#finishedEntriesTable', '#finishedOutputsTable'].forEach(function (selector) {
            jQuery(selector).DataTable({
                pageLength: 8,
                language: {
                    search: 'Recherche',
                    lengthMenu: 'Afficher _MENU_ lignes',
                    info: 'Affichage _START_ a _END_ sur _TOTAL_ lignes',
                    paginate: { previous: 'Precedent', next: 'Suivant' },
                    zeroRecords: 'Aucune donnee trouvee'
                }
            });
        });
    }
});
</script>
