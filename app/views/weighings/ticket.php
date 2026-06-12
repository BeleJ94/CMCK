<section class="dashboard-hero print-hidden">
    <span class="hero-icon"><i class="bi bi-printer"></i></span>
    <div>
        <p class="section-label">Ticket</p>
        <h2>Ticket de pesee</h2>
        <p>Document imprimable pour la livraison <?= e($weighing['reference']) ?>.</p>
    </div>
    <a class="page-action" href="<?= e(base_url('weighings/' . $weighing['id'] . '/ticket?export=pdf')) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i><span>PDF</span></a>
    <button type="button" class="page-action" onclick="window.print()"><i class="bi bi-printer"></i><span>Imprimer</span></button>
</section>

<section class="ticket-card">
    <div class="ticket-header">
        <div>
            <p>CMCK MillTrack</p>
            <h2>Ticket de pesee</h2>
        </div>
        <strong><?= e($weighing['reference']) ?></strong>
    </div>

    <div class="ticket-grid">
        <div><span>Fournisseur</span><strong><?= e($weighing['supplier_name']) ?></strong></div>
        <div><span>Camion</span><strong><?= e($weighing['plate_number']) ?></strong></div>
        <div><span>Chauffeur</span><strong><?= e($weighing['driver_name'] ?: '-') ?></strong></div>
        <div><span>Produit</span><strong><?= e($weighing['product_name']) ?></strong></div>
        <div><span>Date entree</span><strong><?= e($weighing['weighed_at']) ?></strong></div>
        <div><span>Silo destination</span><strong><?= e(($weighing['silo_name'] ?: '-') . ($weighing['silo_code'] ? ' (' . $weighing['silo_code'] . ')' : '')) ?></strong></div>
    </div>

    <div class="ticket-weights">
        <div><span>Poids brut</span><strong><?= e(number_format((float) $weighing['poids_brut'], 0, ',', ' ')) ?> kg</strong></div>
        <div><span>Poids tare</span><strong><?= e(number_format((float) $weighing['poids_tare'], 0, ',', ' ')) ?> kg</strong></div>
        <div><span>Poids net</span><strong><?= e(number_format((float) $weighing['poids_net'], 0, ',', ' ')) ?> kg</strong></div>
    </div>

    <div class="ticket-footer">
        <div><span>Agent entree</span><strong><?= e($weighing['agent_name'] ?: '-') ?></strong></div>
        <div><span>Validation</span><strong><?= e($weighing['validator_name'] ?: '-') ?></strong></div>
        <div><span>Statut</span><strong><?= $weighing['status'] === 'pending' ? 'En attente de dechargement' : e($weighing['status']) ?></strong></div>
    </div>
</section>
