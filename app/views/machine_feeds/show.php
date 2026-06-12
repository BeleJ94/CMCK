<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-clipboard-check"></i></span>
    <div>
        <p class="section-label">Detail alimentation</p>
        <h2><?= e($feed['batch_number'] ?: 'Lot en attente') ?></h2>
        <p><?= e($feed['machine_name']) ?> depuis <?= e($feed['silo_name']) ?>.</p>
    </div>
    <a href="<?= e(base_url('machine-feeds')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
</section>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Quantite envoyee</span><span class="metric-icon tone-blue"><i class="bi bi-arrow-down-up"></i></span></div><strong><?= e(number_format((float) $feed['quantity_kg'], 0, ',', ' ')) ?> kg</strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Statut alimentation</span><span class="metric-icon tone-orange"><i class="bi bi-hourglass-split"></i></span></div><strong><?= $feed['status'] === 'pending' ? 'En attente production' : e($feed['status']) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Statut lot</span><span class="metric-icon tone-green"><i class="bi bi-box-seam"></i></span></div><strong><?= e($feed['batch_status'] ?: '-') ?></strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-info-circle"></i></span><div><h3>Informations operation</h3><p>Traçabilite silo, machine, agent et lot cree.</p></div></div>
    <div class="table-responsive">
        <table class="enterprise-table">
            <tbody>
                <tr><th>Silo source</th><td><?= e($feed['silo_name'] . ' (' . $feed['silo_code'] . ')') ?></td></tr>
                <tr><th>Machine</th><td><?= e($feed['machine_name']) ?></td></tr>
                <tr><th>Produit</th><td><?= e($feed['product_name']) ?></td></tr>
                <tr><th>Heure debut</th><td><?= e($feed['fed_at']) ?></td></tr>
                <tr><th>Heure fin</th><td><?= e($feed['ended_at'] ?: '-') ?></td></tr>
                <tr><th>Agent responsable</th><td><?= e($feed['agent_name'] ?: '-') ?></td></tr>
                <tr><th>Observation</th><td><?= e($feed['observation'] ?: '-') ?></td></tr>
                <tr><th>Lot traitement</th><td><strong><?= e($feed['batch_number'] ?: '-') ?></strong></td></tr>
            </tbody>
        </table>
    </div>
</section>
