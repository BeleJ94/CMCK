<?php
$severityIcons = [
    'info' => 'bi-info-circle',
    'warning' => 'bi-exclamation-triangle',
    'danger' => 'bi-x-octagon',
    'success' => 'bi-check2-circle',
];
$severityLabels = [
    'info' => 'Info',
    'warning' => 'Warning',
    'danger' => 'Danger',
    'success' => 'Success',
];
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-bell"></i></span>
    <div>
        <p class="section-label">Supervision</p>
        <h2>Centre d alertes</h2>
        <p>Suivi des alertes silos, stocks finis, rendement, pesees et ecarts de production.</p>
    </div>
</section>

<?php if ($success): ?>
    <div class="app-alert app-alert-success"><i class="bi bi-check2-circle"></i><span><?= e($success) ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="app-alert app-alert-error"><i class="bi bi-exclamation-triangle"></i><span><?= e($error) ?></span></div>
<?php endif; ?>

<section class="metric-grid">
    <article class="metric-card">
        <div class="metric-card-top"><span>Total alertes</span><span class="metric-icon tone-blue"><i class="bi bi-bell"></i></span></div>
        <strong><?= e($stats['total']) ?></strong>
    </article>
    <article class="metric-card">
        <div class="metric-card-top"><span>Non lues</span><span class="metric-icon tone-orange"><i class="bi bi-eye"></i></span></div>
        <strong><?= e($stats['unread']) ?></strong>
    </article>
    <article class="metric-card">
        <div class="metric-card-top"><span>Danger</span><span class="metric-icon tone-red"><i class="bi bi-x-octagon"></i></span></div>
        <strong><?= e($stats['danger']) ?></strong>
    </article>
    <article class="metric-card">
        <div class="metric-card-top"><span>Warning</span><span class="metric-icon tone-green"><i class="bi bi-exclamation-triangle"></i></span></div>
        <strong><?= e($stats['warning']) ?></strong>
    </article>
</section>

<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-funnel"></i></span>
        <div>
            <h3>Filtres</h3>
            <p>Filtrer par type d alerte et niveau de criticite.</p>
        </div>
    </div>
    <form method="get" action="<?= e(base_url('alerts')) ?>" class="enterprise-form">
        <div class="form-grid">
            <label>
                <span>Type</span>
                <select name="type">
                    <option value="">Tous les types</option>
                    <?php foreach ($types as $key => $type): ?>
                        <option value="<?= e($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= e($type['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Niveau</span>
                <select name="level">
                    <option value="">Tous les niveaux</option>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?= e($level) ?>" <?= $filters['level'] === $level ? 'selected' : '' ?>><?= e($severityLabels[$level] ?? $level) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-actions">
            <a class="btn-secondary" href="<?= e(base_url('alerts')) ?>"><i class="bi bi-arrow-counterclockwise"></i><span>Reinitialiser</span></a>
            <button class="btn-primary" type="submit"><i class="bi bi-search"></i><span>Filtrer</span></button>
        </div>
    </form>
</section>

<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-list-check"></i></span>
        <div>
            <h3>Alertes actives</h3>
            <p><?= e(count($alerts)) ?> resultat(s) selon les filtres actifs.</p>
        </div>
        <?php if (!empty($alerts)): ?>
            <form method="post" action="<?= e(base_url('alerts/mark-all-read')) ?>" class="inline-action-form">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="<?= e($filters['type']) ?>">
                <input type="hidden" name="level" value="<?= e($filters['level']) ?>">
                <button class="btn-secondary" type="submit"><i class="bi bi-check2-all"></i><span>Tout marquer lu</span></button>
            </form>
        <?php endif; ?>
    </div>

    <div class="alert-list alert-list-page">
        <?php if (empty($alerts)): ?>
            <div class="alert-row alert-row-empty">
                <i class="bi bi-check2-circle"></i>
                <div>
                    <strong>Aucune alerte</strong>
                    <span>Aucun signal ne correspond aux filtres selectionnes.</span>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($alerts as $alert): ?>
            <?php
                $severity = $alert['severity'];
                $isRead = !empty($alert['read_at']);
                $icon = $severityIcons[$severity] ?? 'bi-info-circle';
            ?>
            <article class="alert-row severity-<?= e($severity) ?> <?= $isRead ? 'alert-row-read' : '' ?>">
                <i class="bi <?= e($icon) ?>"></i>
                <div>
                    <div class="alert-row-title">
                        <strong><?= e($alert['title']) ?></strong>
                        <span class="status-badge status-<?= $isRead ? 'inactive' : 'active' ?>"><?= $isRead ? 'Lue' : 'Non lue' ?></span>
                    </div>
                    <span><?= e($alert['message']) ?></span>
                    <small><?= e($alert['type_label']) ?> · <?= e($severityLabels[$severity] ?? $severity) ?> · <?= e($alert['created_at']) ?></small>
                </div>
                <?php if (!$isRead): ?>
                    <form method="post" action="<?= e(base_url('alerts/' . $alert['id'] . '/read')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="<?= e($filters['type']) ?>">
                        <input type="hidden" name="level" value="<?= e($filters['level']) ?>">
                        <button class="icon-button" type="submit" title="Marquer comme lue"><i class="bi bi-check2"></i></button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
