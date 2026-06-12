<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-gear-wide-connected"></i></span>
    <div>
        <p class="section-label">Production</p>
        <h2>Machines</h2>
        <p>Referentiel machines et suivi de performance operationnelle.</p>
    </div>
    <a href="<?= e(base_url('machines/create')) ?>" class="page-action"><i class="bi bi-plus-circle"></i><span>Nouvelle machine</span></a>
</section>

<?php if (!empty($success)): ?>
    <div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div>
<?php endif; ?>

<section class="metric-grid">
    <?php
        $activeCount = count(array_filter($machines, function ($machine) { return $machine['status'] === 'active'; }));
        $fedTotal = array_sum(array_map(function ($machine) { return (float) $machine['fed_quantity_kg']; }, $machines));
        $outputTotal = array_sum(array_map(function ($machine) { return (float) $machine['output_quantity_kg']; }, $machines));
    ?>
    <article class="metric-card"><div class="metric-card-top"><span>Machines actives</span><span class="metric-icon tone-green"><i class="bi bi-check2-circle"></i></span></div><strong><?= e($activeCount) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Quantite alimentee</span><span class="metric-icon tone-blue"><i class="bi bi-arrow-down-up"></i></span></div><strong><?= e(number_format($fedTotal, 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Production sortie</span><span class="metric-icon tone-orange"><i class="bi bi-box-seam"></i></span></div><strong><?= e(number_format($outputTotal, 0, ',', ' ')) ?> kg</strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-table"></i></span>
        <div>
            <h3>Liste machines</h3>
            <p>Activation, modification et performance par machine.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table id="machinesTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Type</th>
                    <th>Capacite horaire</th>
                    <th>Alimente</th>
                    <th>Production</th>
                    <th>Rendement</th>
                    <th>Lots</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($machines as $machine): ?>
                    <tr>
                        <td><strong><?= e($machine['name']) ?></strong><br><small><?= e($machine['code']) ?></small></td>
                        <td><?= e($machine['machine_type'] === 'waste' ? 'Machine dechets' : 'Machine principale') ?></td>
                        <td><?= e(number_format((float) $machine['capacity_kg_hour'], 0, ',', ' ')) ?> kg/h</td>
                        <td><?= e(number_format((float) $machine['fed_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $machine['output_quantity_kg'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $machine['yield_rate'], 1, ',', ' ')) ?>%</td>
                        <td><?= e((int) $machine['batches_count']) ?></td>
                        <td><span class="status-badge status-<?= e($machine['status']) ?>"><?= e($machine['status']) ?></span></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= e(base_url('machines/' . $machine['id'] . '/edit')) ?>" class="icon-button" title="Modifier"><i class="bi bi-pencil-square"></i></a>
                                <?php if (Auth::hasRole(['administrateur', 'direction'])): ?>
                                    <form method="post" action="<?= e(base_url('machines/' . $machine['id'] . '/toggle')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-button" title="Activer/desactiver"><i class="bi bi-power"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
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
            jQuery('#machinesTable').DataTable({
                pageLength: 10,
                language: {
                    search: 'Recherche',
                    lengthMenu: 'Afficher _MENU_ lignes',
                    info: 'Affichage _START_ a _END_ sur _TOTAL_ machines',
                    paginate: { previous: 'Precedent', next: 'Suivant' },
                    zeroRecords: 'Aucune machine trouvee'
                }
            });
        }
    });
</script>
