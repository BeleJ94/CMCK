<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-box-arrow-up-right"></i></span>
    <div>
        <p class="section-label">Etape 2</p>
        <h2>Pesee sortie</h2>
        <p>Validation apres dechargement, calcul du poids net et entree silo.</p>
    </div>
</section>

<?php if (!empty($errors)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span>Veuillez corriger les champs indiques.</span></div>
<?php endif; ?>

<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-hourglass-split"></i></span>
        <div>
            <h3>Camions en attente</h3>
            <p>Selectionnez un camion pour encoder la tare et valider la livraison.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table id="pendingWeighingsTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Camion</th>
                    <th>Fournisseur</th>
                    <th>Produit</th>
                    <th>Poids brut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $item): ?>
                    <tr>
                        <td><strong><?= e($item['reference']) ?></strong></td>
                        <td><?= e($item['plate_number']) ?></td>
                        <td><?= e($item['supplier_name']) ?></td>
                        <td><?= e($item['product_name']) ?></td>
                        <td><?= e(number_format((float) $item['poids_brut'], 0, ',', ' ')) ?> kg</td>
                        <td><a class="icon-button" href="<?= e(base_url('weighings/' . $item['id'] . '/exit')) ?>"><i class="bi bi-pencil-square"></i></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (!empty($weighing)): ?>
    <section class="form-panel">
        <div class="panel-heading">
            <span class="panel-icon"><i class="bi bi-calculator"></i></span>
            <div>
                <h3>Validation livraison <?= e($weighing['reference']) ?></h3>
                <p><?= e($weighing['plate_number']) ?> - <?= e($weighing['supplier_name']) ?></p>
            </div>
        </div>

        <form method="post" action="<?= e(base_url('weighings/' . $weighing['id'] . '/exit')) ?>" class="enterprise-form" data-validate data-weighing-exit>
            <?= csrf_field() ?>
            <div class="form-grid">
                <label>
                    <span>Poids brut kg</span>
                    <input type="number" value="<?= e($weighing['poids_brut']) ?>" data-poids-brut disabled>
                </label>
                <label>
                    <span>Poids tare kg</span>
                    <input type="number" step="0.001" min="0" max="<?= e($weighing['poids_brut']) ?>" name="poids_tare" value="<?= e($exit['poids_tare']) ?>" data-poids-tare required>
                    <?php if (!empty($errors['poids_tare'])): ?><small><?= e($errors['poids_tare']) ?></small><?php endif; ?>
                </label>
                <label>
                    <span>Poids net kg</span>
                    <input type="text" value="0" data-poids-net disabled>
                </label>
                <label>
                    <span>Silo destination</span>
                    <select name="silo_id" required>
                        <option value="">Selectionner un silo</option>
                        <?php foreach ($silos as $silo): ?>
                            <option value="<?= e($silo['id']) ?>" <?= (string) $exit['silo_id'] === (string) $silo['id'] ? 'selected' : '' ?>>
                                <?= e($silo['name'] . ' (' . $silo['code'] . ') - stock ' . number_format((float) $silo['current_stock_kg'], 0, ',', ' ') . ' kg') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['silo_id'])): ?><small><?= e($errors['silo_id']) ?></small><?php endif; ?>
                </label>
            </div>
            <div class="form-actions">
                <a href="<?= e(base_url('weighings/exit')) ?>" class="btn-secondary"><i class="bi bi-arrow-left"></i><span>Annuler</span></a>
                <button type="submit" class="btn-primary"><i class="bi bi-check2-circle"></i><span>Valider livraison</span></button>
            </div>
        </form>
    </section>
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && jQuery.fn.DataTable) {
            jQuery('#pendingWeighingsTable').DataTable({
                pageLength: 10,
                language: {
                    search: 'Recherche camion',
                    lengthMenu: 'Afficher _MENU_ lignes',
                    info: 'Affichage _START_ a _END_ sur _TOTAL_ camions',
                    paginate: { previous: 'Precedent', next: 'Suivant' },
                    zeroRecords: 'Aucun camion en attente'
                }
            });
        }
    });
</script>
