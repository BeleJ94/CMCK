<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-truck"></i></span>
    <div>
        <p class="section-label">Pont-bascule</p>
        <h2>Workflow des pesees</h2>
        <p>Suivi des entrees, sorties, validations et tickets imprimables.</p>
    </div>
    <div class="hero-actions">
        <a href="<?= e(base_url('weighings/entry')) ?>" class="page-action"><i class="bi bi-box-arrow-in-down"></i><span>Pesee entree</span></a>
        <a href="<?= e(base_url('weighings/exit')) ?>" class="page-action"><i class="bi bi-box-arrow-up-right"></i><span>Pesee sortie</span></a>
    </div>
</section>

<?php if (!empty($success)): ?>
    <div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div>
<?php endif; ?>

<section class="metric-grid">
    <article class="metric-card">
        <div class="metric-card-top"><span>Camions en attente</span><span class="metric-icon tone-orange"><i class="bi bi-hourglass-split"></i></span></div>
        <strong><?= e(count($pending)) ?></strong>
    </article>
    <article class="metric-card">
        <div class="metric-card-top"><span>Pesees totales</span><span class="metric-icon tone-blue"><i class="bi bi-list-check"></i></span></div>
        <strong><?= e(count($weighings)) ?></strong>
    </article>
</section>

<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-table"></i></span>
        <div>
            <h3>Historique des pesees</h3>
            <p>Recherche par reference, camion, fournisseur ou statut.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table id="weighingsTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Date entree</th>
                    <th>Fournisseur</th>
                    <th>Camion</th>
                    <th>Produit</th>
                    <th>Brut</th>
                    <th>Tare</th>
                    <th>Net</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weighings as $weighing): ?>
                    <tr>
                        <td><strong><?= e($weighing['reference']) ?></strong></td>
                        <td><?= e($weighing['weighed_at']) ?></td>
                        <td><?= e($weighing['supplier_name']) ?></td>
                        <td><?= e($weighing['plate_number']) ?></td>
                        <td><?= e($weighing['product_name']) ?></td>
                        <td><?= e(number_format((float) $weighing['poids_brut'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $weighing['poids_tare'], 0, ',', ' ')) ?> kg</td>
                        <td><?= e(number_format((float) $weighing['poids_net'], 0, ',', ' ')) ?> kg</td>
                        <td><span class="status-badge status-<?= e($weighing['status']) ?>"><?= $weighing['status'] === 'pending' ? 'En attente de dechargement' : e($weighing['status']) ?></span></td>
                        <td>
                            <div class="table-actions">
                                <?php if ($weighing['status'] === 'pending'): ?>
                                    <a class="icon-button" href="<?= e(base_url('weighings/' . $weighing['id'] . '/exit')) ?>" title="Pesee sortie"><i class="bi bi-box-arrow-up-right"></i></a>
                                <?php endif; ?>
                                <a class="icon-button" href="<?= e(base_url('weighings/' . $weighing['id'] . '/ticket')) ?>" title="Ticket"><i class="bi bi-printer"></i></a>
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
            jQuery('#weighingsTable').DataTable({
                pageLength: 10,
                order: [[1, 'desc']],
                language: {
                    search: 'Recherche',
                    lengthMenu: 'Afficher _MENU_ lignes',
                    info: 'Affichage _START_ a _END_ sur _TOTAL_ pesees',
                    paginate: { previous: 'Precedent', next: 'Suivant' },
                    zeroRecords: 'Aucune pesee trouvee'
                }
            });
        }
    });
</script>
