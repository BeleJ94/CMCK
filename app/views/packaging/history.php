<?php
$packagedTotal = array_sum(array_map(function ($row) {
    return (float) $row['total_weight_kg'];
}, $history));
$bagsTotal = array_sum(array_map(function ($row) {
    return (int) $row['bags_count'];
}, $history));
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-clock-history"></i></span>
    <div>
        <p class="section-label">Historique</p>
        <h2>Emballages</h2>
        <p>Operations validees, formats sacs, poids total et agent responsable.</p>
    </div>
    <a href="<?= e(base_url('packaging/create')) ?>" class="page-action"><i class="bi bi-plus-circle"></i><span>Nouvel emballage</span></a>
</section>

<?php if (!empty($success)): ?><div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Poids emballe</span><span class="metric-icon tone-green"><i class="bi bi-box-seam"></i></span></div><strong><?= e(number_format($packagedTotal, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Sacs produits</span><span class="metric-icon tone-blue"><i class="bi bi-bag-check"></i></span></div><strong><?= e(number_format($bagsTotal, 0, ',', ' ')) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Operations</span><span class="metric-icon tone-orange"><i class="bi bi-list-check"></i></span></div><strong><?= e(count($history)) ?></strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Historique emballage</h3><p>Suivi par lot, produit, format et agent.</p></div></div>
    <div class="table-responsive">
        <table id="packagingHistoryTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lot</th>
                    <th>Produit</th>
                    <th>Format</th>
                    <th>Sacs</th>
                    <th>Poids total</th>
                    <th>Agent</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                    <tr>
                        <td><?= e($row['packaged_at']) ?></td>
                        <td><strong><?= e($row['batch_number']) ?></strong></td>
                        <td><?= e($row['product_name']) ?></td>
                        <td><?= e($row['format_name']) ?></td>
                        <td><?= e(number_format((int) $row['bags_count'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['total_weight_kg'], 0, ',', ' ')) ?> kg</td>
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
        jQuery('#packagingHistoryTable').DataTable({
            pageLength: 10,
            order: [[0, 'desc']],
            language: {
                search: 'Recherche',
                lengthMenu: 'Afficher _MENU_ lignes',
                info: 'Affichage _START_ a _END_ sur _TOTAL_ emballages',
                paginate: { previous: 'Precedent', next: 'Suivant' },
                zeroRecords: 'Aucun emballage trouve'
            }
        });
    }
});
</script>
