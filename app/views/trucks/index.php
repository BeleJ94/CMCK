<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-truck-front"></i></span>
    <div>
        <p class="section-label">Logistique</p>
        <h2>Camions</h2>
        <p>Gestion des camions, chauffeurs et rattachement aux fournisseurs.</p>
    </div>
    <a href="<?= e(base_url('trucks/create')) ?>" class="page-action">
        <i class="bi bi-plus-circle"></i>
        <span>Nouveau camion</span>
    </a>
</section>

<?php if (!empty($success)): ?>
    <div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div>
<?php endif; ?>

<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-table"></i></span>
        <div>
            <h3>Liste des camions</h3>
            <p>Recherche rapide par plaque, chauffeur ou fournisseur.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table id="trucksTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Plaque</th>
                    <th>Chauffeur</th>
                    <th>Telephone chauffeur</th>
                    <th>Fournisseur associe</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trucks as $truck): ?>
                    <tr>
                        <td><strong><?= e($truck['plate_number']) ?></strong></td>
                        <td><?= e($truck['driver_name'] ?: '-') ?></td>
                        <td><?= e($truck['driver_phone'] ?: '-') ?></td>
                        <td><?= e($truck['supplier_name'] ?: '-') ?></td>
                        <td><span class="status-badge status-<?= e($truck['status']) ?>"><?= e($truck['status']) ?></span></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= e(base_url('trucks/' . $truck['id'] . '/edit')) ?>" class="icon-button" title="Modifier">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <?php if (Auth::hasRole(['administrateur', 'direction'])): ?>
                                    <form method="post" action="<?= e(base_url('trucks/' . $truck['id'] . '/delete')) ?>" data-confirm="Supprimer ce camion ?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-button danger" title="Supprimer">
                                            <i class="bi bi-trash3"></i>
                                        </button>
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
            jQuery('#trucksTable').DataTable({
                pageLength: 10,
                order: [[0, 'asc']],
                language: {
                    search: 'Recherche plaque',
                    lengthMenu: 'Afficher _MENU_ lignes',
                    info: 'Affichage _START_ a _END_ sur _TOTAL_ camions',
                    paginate: { previous: 'Precedent', next: 'Suivant' },
                    zeroRecords: 'Aucun camion trouve'
                }
            });
        }
    });
</script>
