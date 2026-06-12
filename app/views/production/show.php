<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-clipboard-check"></i></span>
    <div>
        <p class="section-label">Detail production</p>
        <h2><?= e($batch['batch_number']) ?></h2>
        <p><?= e($batch['machine_name']) ?> - <?= $batch['status'] === 'validated' ? 'production validee' : 'en attente de validation' ?>.</p>
    </div>
    <a href="<?= e(base_url('production')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
</section>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Quantite traitee</span><span class="metric-icon tone-blue"><i class="bi bi-arrow-down-up"></i></span></div><strong><?= e(number_format((float) $batch['input_quantity_kg'], 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Bon produit</span><span class="metric-icon tone-green"><i class="bi bi-box-seam"></i></span></div><strong><?= e(number_format((float) $batch['output_quantity_kg'], 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Dechets</span><span class="metric-icon tone-orange"><i class="bi bi-recycle"></i></span></div><strong><?= e(number_format((float) $batch['waste_quantity_kg'], 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Rendement</span><span class="metric-icon tone-red"><i class="bi bi-speedometer2"></i></span></div><strong><?= e(number_format($yield, 1, ',', ' ')) ?>%</strong></article>
</section>

<?php if ($batch['status'] === 'pending'): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-hourglass-split"></i><span>Ce lot est encore en attente. La mise a jour des stocks farine et dechets sera effectuee a la validation.</span></div>
<?php endif; ?>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-info-circle"></i></span><div><h3>Informations production</h3><p>Traçabilite du lot, machine, agent et mouvement de stock.</p></div></div>
    <div class="table-responsive">
        <table class="enterprise-table">
            <tbody>
                <tr><th>Lot traitement</th><td><strong><?= e($batch['batch_number']) ?></strong></td></tr>
                <tr><th>Machine</th><td><?= e($batch['machine_name']) ?></td></tr>
                <tr><th>Produit stocke</th><td><?= e($batch['product_name']) ?></td></tr>
                <tr><th>Quantite traitee</th><td><?= e(number_format((float) $batch['input_quantity_kg'], 3, ',', ' ')) ?> kg</td></tr>
                <tr><th>Quantite bon produit</th><td><?= e(number_format((float) $batch['output_quantity_kg'], 3, ',', ' ')) ?> kg</td></tr>
                <tr><th>Quantite dechets</th><td><?= e(number_format((float) $batch['waste_quantity_kg'], 3, ',', ' ')) ?> kg</td></tr>
                <tr><th>Rendement</th><td><?= e(number_format($yield, 2, ',', ' ')) ?>%</td></tr>
                <tr><th>Date debut</th><td><?= e($batch['started_at'] ?: '-') ?></td></tr>
                <tr><th>Date production</th><td><?= e($batch['ended_at'] ?: '-') ?></td></tr>
                <tr><th>Agent</th><td><?= e($batch['agent_name'] ?: '-') ?></td></tr>
                <tr><th>Validateur</th><td><?= e($batch['validator_name'] ?: '-') ?></td></tr>
                <tr><th>Statut</th><td><span class="status-badge status-<?= e($batch['status']) ?>"><?= $batch['status'] === 'pending' ? 'En attente' : e($batch['status']) ?></span></td></tr>
            </tbody>
        </table>
    </div>
</section>
