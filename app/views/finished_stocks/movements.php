<?php
$entriesKg = array_sum(array_map(function ($row) {
    return $row['movement_type'] === 'in' ? (float) $row['quantity_kg'] : 0;
}, $movements));
$outputsKg = array_sum(array_map(function ($row) {
    return $row['movement_type'] === 'out' ? (float) $row['quantity_kg'] : 0;
}, $movements));
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-clock-history"></i></span>
    <div>
        <p class="section-label">Historique</p>
        <h2>Mouvements stock produits finis</h2>
        <p>Traçabilite complete des entrees emballage, sorties distribution et soldes.</p>
    </div>
    <a href="<?= e(base_url('finished-stocks')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour stock</span></a>
</section>

<?php foreach ($alerts as $alert): ?>
    <div class="app-alert <?= $alert['severity'] === 'danger' ? 'app-alert-error' : 'app-alert-success' ?>"><i class="bi <?= $alert['severity'] === 'danger' ? 'bi-exclamation-triangle' : 'bi-info-circle' ?>"></i><span><strong><?= e($alert['title']) ?></strong> - <?= e($alert['message']) ?></span></div>
<?php endforeach; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Mouvements</span><span class="metric-icon tone-blue"><i class="bi bi-list-check"></i></span></div><strong><?= e(count($movements)) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Total entrees</span><span class="metric-icon tone-green"><i class="bi bi-arrow-down-circle"></i></span></div><strong><?= e(number_format($entriesKg, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Total sorties</span><span class="metric-icon tone-red"><i class="bi bi-arrow-up-circle"></i></span></div><strong><?= e(number_format($outputsKg, 0, ',', ' ')) ?> kg</strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Historique mouvements</h3><p>Produit, format, type mouvement, quantite et solde apres operation.</p></div></div>
    <div class="table-responsive">
        <table id="finishedMovementsTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Produit</th>
                    <th>Format</th>
                    <th>Type</th>
                    <th>Sacs</th>
                    <th>Quantite</th>
                    <th>Stock avant</th>
                    <th>Stock apres</th>
                    <th>Reference</th>
                    <th>Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movements as $row): ?>
                    <tr>
                        <td><?= e($row['movement_at']) ?></td>
                        <td><strong><?= e($row['product_name']) ?></strong></td>
                        <td><?= e($row['format_name'] ?: '-') ?></td>
                        <td><span class="status-badge status-<?= $row['movement_type'] === 'in' ? 'validated' : 'cancelled' ?>"><?= $row['movement_type'] === 'in' ? 'Entree' : ($row['movement_type'] === 'out' ? 'Sortie' : 'Ajustement') ?></span></td>
                        <td><?= e(number_format((int) $row['quantity_bags'], 0, ',', ' ')) ?></td>
                        <td><?= e(number_format((float) $row['quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $row['stock_before_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><strong><?= e(number_format((float) $row['stock_after_kg'], 0, ',', ' ')) ?> kg</strong></td>
                        <td><?= $row['distribution_id'] ? e($row['recipient_name']) : ($row['packaging_id'] ? 'Emballage #' . e($row['packaging_id']) : '-') ?></td>
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
        jQuery('#finishedMovementsTable').DataTable({
            pageLength: 10,
            order: [[0, 'desc']],
            language: {
                search: 'Recherche',
                lengthMenu: 'Afficher _MENU_ lignes',
                info: 'Affichage _START_ a _END_ sur _TOTAL_ mouvements',
                paginate: { previous: 'Precedent', next: 'Suivant' },
                zeroRecords: 'Aucun mouvement trouve'
            }
        });
    }
});
</script>
