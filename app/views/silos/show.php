<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-database-check"></i></span>
    <div>
        <p class="section-label"><?= e($silo['code']) ?></p>
        <h2><?= e($silo['name']) ?></h2>
        <p><?= e($silo['product_name'] ?: 'Produit non affecte') ?> - stock detaille et mouvements recents.</p>
    </div>
    <a href="<?= e(base_url('silos')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour</span></a>
</section>

<?php if ($lowStock): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i>Stock bas: le niveau est sous le seuil d alerte.</div>
<?php endif; ?>

<?php if ($almostFull): ?>
    <div class="app-alert app-alert-success"><i class="bi bi-info-circle"></i>Silo presque plein: le taux de remplissage depasse 90%.</div>
<?php endif; ?>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Stock actuel</span><span class="metric-icon tone-green"><i class="bi bi-boxes"></i></span></div><strong><?= e(number_format((float) $silo['current_stock_kg'], 0, ',', ' ')) ?> <?= e($silo['unit']) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Capacite maximale</span><span class="metric-icon tone-blue"><i class="bi bi-database"></i></span></div><strong><?= e(number_format((float) $silo['capacity_kg'], 0, ',', ' ')) ?> <?= e($silo['unit']) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Seuil alerte</span><span class="metric-icon tone-orange"><i class="bi bi-bell"></i></span></div><strong><?= e(number_format((float) $silo['alert_threshold_kg'], 0, ',', ' ')) ?> <?= e($silo['unit']) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Remplissage</span><span class="metric-icon tone-blue"><i class="bi bi-activity"></i></span></div><strong><?= e(number_format((float) $silo['fill_rate'], 1, ',', ' ')) ?>%</strong></article>
</section>

<section class="dashboard-charts">
    <article class="chart-panel">
        <div class="panel-heading"><span class="panel-icon"><i class="bi bi-box-arrow-in-down"></i></span><div><h3>Entrees par livraison</h3><p>Dernieres livraisons validees vers ce silo.</p></div></div>
        <?php $rows = $entries; require view_path('silos.table_entries'); ?>
    </article>
    <article class="chart-panel">
        <div class="panel-heading"><span class="panel-icon"><i class="bi bi-gear-wide-connected"></i></span><div><h3>Sorties vers machines</h3><p>Dernieres alimentations machine depuis ce silo.</p></div></div>
        <?php $rows = $exits; require view_path('silos.table_exits'); ?>
    </article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-clock-history"></i></span><div><h3>Historique mouvements</h3><p>Derniers mouvements de stock du silo.</p></div></div>
    <?php $rows = $movements; require view_path('silos.table_movements'); ?>
</section>
