<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-database"></i></span>
    <div>
        <p class="section-label">Stock matiere</p>
        <h2>Silos</h2>
        <p>Niveaux de remplissage, alertes et suivi des stocks en temps reel.</p>
    </div>
    <a href="<?= e(base_url('silos/movements')) ?>" class="page-action"><i class="bi bi-clock-history"></i><span>Historique mouvements</span></a>
</section>

<?php if (!empty($error)): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div>
<?php endif; ?>

<?php if (!empty($alerts)): ?>
    <section class="alerts-panel">
        <div class="panel-heading">
            <span class="panel-icon alert-panel-icon"><i class="bi bi-exclamation-triangle"></i></span>
            <div>
                <h3>Alertes silos</h3>
                <p>Stock bas, capacité dépassée ou silo presque plein.</p>
            </div>
        </div>
        <div class="alert-list">
            <?php foreach ($alerts as $alert): ?>
                <?php
                    $fill = (float) $alert['fill_rate'];
                    $isOver = (float) $alert['capacity_kg'] > 0 && (float) $alert['current_stock_kg'] > (float) $alert['capacity_kg'];
                    $isLow = (float) $alert['alert_threshold_kg'] > 0 && (float) $alert['current_stock_kg'] <= (float) $alert['alert_threshold_kg'];
                    $severity = $isOver ? 'danger' : ($isLow ? 'warning' : 'info');
                    $message = $isOver ? 'Capacite depassee' : ($isLow ? 'Stock bas' : 'Silo presque plein');
                ?>
                <a class="alert-row severity-<?= e($severity) ?>" href="<?= e(base_url('silos/' . $alert['id'])) ?>">
                    <i class="bi <?= $severity === 'danger' ? 'bi-x-octagon' : 'bi-exclamation-triangle' ?>"></i>
                    <div>
                        <strong><?= e($alert['name']) ?> - <?= e($message) ?></strong>
                        <span><?= e(number_format((float) $alert['current_stock_kg'], 0, ',', ' ')) ?> <?= e($alert['unit']) ?> / <?= e(number_format((float) $alert['capacity_kg'], 0, ',', ' ')) ?> <?= e($alert['unit']) ?> (<?= e(number_format($fill, 1, ',', ' ')) ?>%)</span>
                    </div>
                    <span class="alert-row-action"><i class="bi bi-chevron-right"></i></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="silo-grid">
    <?php foreach ($silos as $silo): ?>
        <?php
            $fill = min(max((float) $silo['fill_rate'], 0), 100);
            $tone = $fill >= 100 ? 'red' : ($fill >= 90 ? 'orange' : (((float) $silo['alert_threshold_kg'] > 0 && (float) $silo['current_stock_kg'] <= (float) $silo['alert_threshold_kg']) ? 'orange' : 'green'));
        ?>
        <article class="silo-card">
            <div class="silo-card-head">
                <div>
                    <p class="section-label"><?= e($silo['code']) ?></p>
                    <h3><?= e($silo['name']) ?></h3>
                    <span><?= e($silo['product_name'] ?: 'Produit non affecte') ?></span>
                </div>
                <span class="metric-icon tone-<?= e($tone) ?>"><i class="bi bi-database-check"></i></span>
            </div>

            <div class="silo-level">
                <div class="silo-level-bar">
                    <span class="silo-fill tone-<?= e($tone) ?>" style="width: <?= e($fill) ?>%"></span>
                </div>
                <strong><?= e(number_format($fill, 1, ',', ' ')) ?>%</strong>
            </div>

            <div class="silo-card-stats">
                <div><span>Stock actuel</span><strong><?= e(number_format((float) $silo['current_stock_kg'], 0, ',', ' ')) ?> <?= e($silo['unit']) ?></strong></div>
                <div><span>Capacite</span><strong><?= e(number_format((float) $silo['capacity_kg'], 0, ',', ' ')) ?> <?= e($silo['unit']) ?></strong></div>
                <div><span>Seuil alerte</span><strong><?= e(number_format((float) $silo['alert_threshold_kg'], 0, ',', ' ')) ?> <?= e($silo['unit']) ?></strong></div>
            </div>

            <a href="<?= e(base_url('silos/' . $silo['id'])) ?>" class="btn-secondary silo-link"><i class="bi bi-eye"></i><span>Voir detail</span></a>
        </article>
    <?php endforeach; ?>
</section>
