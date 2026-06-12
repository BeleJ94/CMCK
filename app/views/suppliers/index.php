<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-building-check"></i></span>
    <div>
        <p class="section-label">Referentiel</p>
        <h2>Fournisseurs</h2>
        <p>Gestion des partenaires d approvisionnement en mais brut.</p>
    </div>
    <a href="<?= e(base_url('suppliers/create')) ?>" class="page-action">
        <i class="bi bi-plus-circle"></i>
        <span>Nouveau fournisseur</span>
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
            <h3>Liste des fournisseurs</h3>
            <p>Recherche, consultation, modification et suppression logique.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table id="suppliersTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Telephone</th>
                    <th>Adresse</th>
                    <th>RCCM</th>
                    <th>ID Nat</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td><strong><?= e($supplier['name']) ?></strong></td>
                        <td><?= e($supplier['phone'] ?: '-') ?></td>
                        <td><?= e($supplier['address'] ?: '-') ?></td>
                        <td><?= e($supplier['rccm'] ?: '-') ?></td>
                        <td><?= e($supplier['id_nat'] ?: '-') ?></td>
                        <td><span class="status-badge status-<?= e($supplier['status']) ?>"><?= e($supplier['status']) ?></span></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= e(base_url('suppliers/' . $supplier['id'] . '/edit')) ?>" class="icon-button" title="Modifier">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <?php if (Auth::hasRole(['administrateur', 'direction'])): ?>
                                    <form method="post" action="<?= e(base_url('suppliers/' . $supplier['id'] . '/delete')) ?>" data-confirm="Supprimer ce fournisseur ?">
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
            jQuery('#suppliersTable').DataTable({
                pageLength: 10,
                language: {
                    search: 'Recherche',
                    lengthMenu: 'Afficher _MENU_ lignes',
                    info: 'Affichage _START_ a _END_ sur _TOTAL_ fournisseurs',
                    paginate: { previous: 'Precedent', next: 'Suivant' },
                    zeroRecords: 'Aucun fournisseur trouve'
                }
            });
        }
    });
</script>
