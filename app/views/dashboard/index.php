<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-graph-up-arrow"></i></span>
    <div>
        <p class="section-label">Direction generale</p>
        <h2><?= e($title ?? 'Dashboard Direction') ?></h2>
        <p><?= e($subtitle ?? '') ?></p>
    </div>
    <a class="page-action print-hidden" href="<?= e(base_url('reports')) ?>">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <span>Rapport direction</span>
    </a>
</section>

<?php if (!empty($decisionSummary)): ?>
    <section class="decision-strip">
        <?php foreach ($decisionSummary as $item): ?>
            <article class="decision-item tone-<?= e($item['tone']) ?>">
                <span><?= e($item['label']) ?></span>
                <strong><?= e($item['value']) ?></strong>
                <small><?= e($item['text']) ?></small>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="metric-grid direction-metrics">
    <?php foreach ($cards as $index => $card): ?>
        <button type="button" class="metric-card metric-card-button" data-kpi-open data-kpi-index="<?= e($index) ?>">
            <div class="metric-card-top">
                <span><?= e($card['label']) ?></span>
                <span class="metric-icon tone-<?= e($card['tone'] ?? 'blue') ?>"><i class="bi <?= e($card['icon'] ?? 'bi-activity') ?>"></i></span>
            </div>
            <strong><?= e($card['value']) ?></strong>
            <?php if (!empty($card['trend'])): ?>
                <small class="kpi-trend tone-<?= e($card['trend']['tone']) ?>"><i class="bi <?= $card['trend']['tone'] === 'red' ? 'bi-arrow-down-right' : 'bi-arrow-up-right' ?>"></i><?= e($card['trend']['label']) ?></small>
            <?php elseif (!empty($card['hint'])): ?>
                <small class="kpi-hint"><?= e($card['hint']) ?></small>
            <?php else: ?>
                <small class="detail-hint"><i class="bi bi-box-arrow-up-right"></i> Voir details</small>
            <?php endif; ?>
        </button>
    <?php endforeach; ?>
</section>

<section class="dashboard-charts">
    <article class="chart-panel chart-panel-wide" data-chart-open data-chart-key="productionSevenDays">
        <div class="panel-heading">
            <span class="panel-icon"><i class="bi bi-bar-chart-line"></i></span>
            <div>
                <h3>Production sur 7 jours</h3>
                <p>Sortie totale des lots valides en kilogrammes.</p>
            </div>
            <button type="button" class="panel-action"><i class="bi bi-box-arrow-up-right"></i> Details</button>
        </div>
        <div class="chart-box">
            <canvas id="productionSevenDaysChart"></canvas>
        </div>
    </article>

    <article class="chart-panel" data-chart-open data-chart-key="distributionSevenDays">
        <div class="panel-heading">
            <span class="panel-icon"><i class="bi bi-send-check"></i></span>
            <div>
                <h3>Distribution sur 7 jours</h3>
                <p>Sorties produits finis validees.</p>
            </div>
            <button type="button" class="panel-action"><i class="bi bi-box-arrow-up-right"></i> Details</button>
        </div>
        <div class="chart-box">
            <canvas id="distributionSevenDaysChart"></canvas>
        </div>
    </article>

    <article class="chart-panel" data-chart-open data-chart-key="yieldByMachine">
        <div class="panel-heading">
            <span class="panel-icon"><i class="bi bi-speedometer2"></i></span>
            <div>
                <h3>Rendement par machine</h3>
                <p>Moyenne de rendement sur la periode.</p>
            </div>
            <button type="button" class="panel-action"><i class="bi bi-box-arrow-up-right"></i> Details</button>
        </div>
        <div class="chart-box">
            <canvas id="yieldByMachineChart"></canvas>
        </div>
    </article>

    <article class="chart-panel" data-chart-open data-chart-key="receptionBySupplier">
        <div class="panel-heading">
            <span class="panel-icon"><i class="bi bi-building-check"></i></span>
            <div>
                <h3>Reception par fournisseur</h3>
                <p>Mais brut recu sur les 7 derniers jours.</p>
            </div>
            <button type="button" class="panel-action"><i class="bi bi-box-arrow-up-right"></i> Details</button>
        </div>
        <div class="chart-box">
            <canvas id="receptionBySupplierChart"></canvas>
        </div>
    </article>
</section>

<section class="alerts-panel">
    <div class="panel-heading">
        <span class="panel-icon alert-panel-icon"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            <h3>Alertes importantes</h3>
            <p>Points de vigilance actifs pour la direction.</p>
        </div>
    </div>

    <div class="alert-list">
        <?php if (empty($alerts)): ?>
            <div class="alert-row alert-row-empty">
                <i class="bi bi-check2-circle"></i>
                <div>
                    <strong>Aucune alerte critique</strong>
                    <span>Tous les indicateurs prioritaires sont sous controle.</span>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($alerts as $index => $alert): ?>
            <button type="button" class="alert-row alert-row-button severity-<?= e($alert['severity']) ?>" data-notification-open data-notification-index="<?= e($index) ?>">
                <i class="bi <?= $alert['severity'] === 'danger' ? 'bi-x-octagon' : ($alert['severity'] === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle') ?>"></i>
                <div>
                    <strong><?= e($alert['title']) ?></strong>
                    <span><?= e($alert['message']) ?></span>
                </div>
                <span class="alert-row-action"><i class="bi bi-chevron-right"></i></span>
            </button>
        <?php endforeach; ?>
    </div>
</section>

<div class="modal-backdrop" data-modal-backdrop></div>
<section class="notification-modal" role="dialog" aria-modal="true" aria-labelledby="notificationModalTitle" aria-hidden="true" data-notification-modal>
    <div class="modal-header">
        <span class="modal-icon" data-modal-icon><i class="bi bi-info-circle"></i></span>
        <div>
            <p class="section-label" data-modal-severity>Notification</p>
            <h3 id="notificationModalTitle" data-modal-title>Detail notification</h3>
        </div>
        <button type="button" class="modal-close" aria-label="Fermer" data-modal-close>
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="modal-content">
        <div class="modal-field">
            <span data-modal-summary-label>Message</span>
            <strong data-modal-message></strong>
        </div>
        <div class="modal-field">
            <span data-modal-date-label>Date</span>
            <strong data-modal-date></strong>
        </div>
        <div class="modal-field">
            <span data-modal-level-label>Niveau</span>
            <strong data-modal-level></strong>
        </div>
        <div class="modal-table-wrap" data-modal-table-wrap></div>
    </div>

    <div class="modal-actions">
        <button type="button" class="btn-export btn-export-pdf" data-export-pdf>
            <i class="bi bi-filetype-pdf"></i>
            <span>Exporter PDF</span>
        </button>
        <button type="button" class="btn-export btn-export-excel" data-export-excel>
            <i class="bi bi-file-earmark-excel"></i>
            <span>Exporter Excel</span>
        </button>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    window.cmckDashboard = <?= json_encode($charts ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.cmckKpis = <?= json_encode(array_values($cards ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.cmckNotifications = <?= json_encode(array_values($alerts ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script>
    (function () {
        if (!window.Chart || !window.cmckDashboard || !window.cmckDashboard.productionSevenDays) {
            return;
        }

        var colors = {
            blue: '#174064',
            green: '#15803d',
            orange: '#c77700',
            red: '#c24132',
            grid: '#d9e1ea'
        };

        function emptyAware(values) {
            return values && values.length ? values : [0];
        }

        function labels(labels, fallback) {
            return labels && labels.length ? labels : [fallback];
        }

        new Chart(document.getElementById('productionSevenDaysChart'), {
            type: 'line',
            data: {
                labels: labels(window.cmckDashboard.productionSevenDays.labels, 'Aucune donnee'),
                datasets: [{
                    label: 'Production kg',
                    data: emptyAware(window.cmckDashboard.productionSevenDays.values),
                    borderColor: colors.green,
                    backgroundColor: 'rgba(21, 128, 61, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4
                }]
            },
            options: chartOptions('kg')
        });

        new Chart(document.getElementById('distributionSevenDaysChart'), {
            type: 'line',
            data: {
                labels: labels(window.cmckDashboard.distributionSevenDays.labels, 'Aucune sortie'),
                datasets: [{
                    label: 'Distribution kg',
                    data: emptyAware(window.cmckDashboard.distributionSevenDays.values),
                    borderColor: colors.blue,
                    backgroundColor: 'rgba(23, 64, 100, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4
                }]
            },
            options: chartOptions('kg')
        });

        new Chart(document.getElementById('yieldByMachineChart'), {
            type: 'bar',
            data: {
                labels: labels(window.cmckDashboard.yieldByMachine.labels, 'Aucune machine'),
                datasets: [{
                    label: 'Rendement %',
                    data: emptyAware(window.cmckDashboard.yieldByMachine.values),
                    backgroundColor: colors.blue,
                    borderRadius: 6
                }]
            },
            options: chartOptions('%')
        });

        new Chart(document.getElementById('receptionBySupplierChart'), {
            type: 'doughnut',
            data: {
                labels: labels(window.cmckDashboard.receptionBySupplier.labels, 'Aucun fournisseur'),
                datasets: [{
                    label: 'Reception kg',
                    data: emptyAware(window.cmckDashboard.receptionBySupplier.values),
                    backgroundColor: [colors.green, colors.blue, colors.orange, colors.red, '#64748b', '#0f766e'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, color: '#344054', font: { weight: '700' } }
                    }
                }
            }
        });

        function chartOptions(unit) {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#667085', font: { weight: '700' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: colors.grid },
                        ticks: {
                            color: '#667085',
                            callback: function (value) {
                                return value + ' ' + unit;
                            }
                        }
                    }
                }
            };
        }
    })();
</script>
