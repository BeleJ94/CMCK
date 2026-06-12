<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-arrow-down-up"></i></span>
    <div>
        <p class="section-label">Production</p>
        <h2>Alimentation machines</h2>
        <p>Sorties silos vers machines principales et lots en attente production.</p>
    </div>
    <a href="<?= e(base_url('machine-feeds/create')) ?>" class="page-action"><i class="bi bi-plus-circle"></i><span>Nouvelle alimentation</span></a>
</section>

<?php if (!empty($success)): ?><div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Historique alimentations</h3><p>Suivi des quantites envoyees et lots crees.</p></div></div>
    <div class="table-responsive">
        <table id="feedsTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Debut</th>
                    <th>Fin</th>
                    <th>Silo</th>
                    <th>Machine</th>
                    <th>Produit</th>
                    <th>Quantite</th>
                    <th>Lot</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feeds as $feed): ?>
                    <tr>
                        <td><?= e($feed['fed_at']) ?></td>
                        <td><?= e($feed['ended_at'] ?: '-') ?></td>
                        <td><?= e($feed['silo_name']) ?></td>
                        <td><?= e($feed['machine_name']) ?></td>
                        <td><?= e($feed['product_name']) ?></td>
                        <td><?= e(number_format((float) $feed['quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><strong><?= e($feed['batch_number'] ?: '-') ?></strong></td>
                        <td><span class="status-badge status-<?= e($feed['status']) ?>"><?= $feed['status'] === 'pending' ? 'En attente production' : e($feed['status']) ?></span></td>
                        <td><a class="icon-button" href="<?= e(base_url('machine-feeds/' . $feed['id'])) ?>"><i class="bi bi-eye"></i></a></td>
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
        jQuery('#feedsTable').DataTable({
            pageLength: 10,
            order: [[0, 'desc']],
            language: {
                search: 'Recherche',
                lengthMenu: 'Afficher _MENU_ lignes',
                info: 'Affichage _START_ a _END_ sur _TOTAL_ alimentations',
                paginate: { previous: 'Precedent', next: 'Suivant' },
                zeroRecords: 'Aucune alimentation trouvee'
            }
        });
    }
});
</script>
