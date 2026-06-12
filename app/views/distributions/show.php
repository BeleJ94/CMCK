<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-receipt"></i></span>
    <div>
        <p class="section-label">Bon de sortie</p>
        <h2><?= e($distribution['exit_voucher']) ?></h2>
        <p><?= e($distribution['recipient_name']) ?> - <?= e($distribution['product_name']) ?> <?= e($distribution['format_name']) ?>.</p>
    </div>
    <a href="<?= e(base_url('distributions/' . $distribution['id'] . '/print')) ?>" class="page-action" target="_blank"><i class="bi bi-printer"></i><span>Imprimer / PDF</span></a>
</section>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Produit</span><span class="metric-icon tone-blue"><i class="bi bi-box-seam"></i></span></div><strong><?= e($distribution['product_name']) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Format</span><span class="metric-icon tone-orange"><i class="bi bi-bag-check"></i></span></div><strong><?= e($distribution['format_name']) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Sacs sortis</span><span class="metric-icon tone-green"><i class="bi bi-send-check"></i></span></div><strong><?= e(number_format((int) $distribution['quantity_bags'], 0, ',', ' ')) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Poids total</span><span class="metric-icon tone-red"><i class="bi bi-speedometer2"></i></span></div><strong><?= e(number_format((float) $distribution['total_weight_kg'], 0, ',', ' ')) ?> kg</strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-info-circle"></i></span><div><h3>Informations distribution</h3><p>Destination, transport, agent et validation.</p></div></div>
    <div class="table-responsive">
        <table class="enterprise-table">
            <tbody>
                <tr><th>Bon de sortie</th><td><strong><?= e($distribution['exit_voucher']) ?></strong></td></tr>
                <tr><th>Client / destination</th><td><?= e($distribution['recipient_name']) ?></td></tr>
                <tr><th>Camion / transporteur</th><td><?= e($distribution['transporter'] ?: '-') ?></td></tr>
                <tr><th>Produit</th><td><?= e($distribution['product_name']) ?></td></tr>
                <tr><th>Format</th><td><?= e($distribution['format_name']) ?></td></tr>
                <tr><th>Nombre sacs</th><td><?= e(number_format((int) $distribution['quantity_bags'], 0, ',', ' ')) ?></td></tr>
                <tr><th>Quantite totale</th><td><?= e(number_format((float) $distribution['total_weight_kg'], 3, ',', ' ')) ?> kg</td></tr>
                <tr><th>Date</th><td><?= e($distribution['distributed_at']) ?></td></tr>
                <tr><th>Agent</th><td><?= e($distribution['agent_name'] ?: '-') ?></td></tr>
                <tr><th>Statut</th><td><span class="status-badge status-<?= e($distribution['status']) ?>"><?= e($distribution['status']) ?></span></td></tr>
            </tbody>
        </table>
    </div>
</section>
