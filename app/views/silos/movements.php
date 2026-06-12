<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-clock-history"></i></span>
    <div>
        <p class="section-label">Historique</p>
        <h2>Mouvements silos</h2>
        <p>Vue consolidee des entrees livraison, sorties machines et ajustements.</p>
    </div>
    <a href="<?= e(base_url('silos')) ?>" class="page-action"><i class="bi bi-arrow-left"></i><span>Retour silos</span></a>
</section>

<section class="dashboard-charts">
    <article class="chart-panel">
        <div class="panel-heading"><span class="panel-icon"><i class="bi bi-box-arrow-in-down"></i></span><div><h3>Entrees par livraison</h3><p>Livraisons pont-bascule validees vers les silos.</p></div></div>
        <?php $rows = $entries; require view_path('silos.table_entries'); ?>
    </article>
    <article class="chart-panel">
        <div class="panel-heading"><span class="panel-icon"><i class="bi bi-gear-wide-connected"></i></span><div><h3>Sorties vers machines</h3><p>Alimentations machines depuis les silos.</p></div></div>
        <?php $rows = $exits; require view_path('silos.table_exits'); ?>
    </article>
</section>

<section class="table-panel">
    <div class="panel-heading"><span class="panel-icon"><i class="bi bi-table"></i></span><div><h3>Tous les mouvements</h3><p>Entrees, sorties et ajustements de stock.</p></div></div>
    <?php $rows = $movements; require view_path('silos.table_movements'); ?>
</section>
