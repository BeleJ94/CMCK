<?php
if (!function_exists('activity_value')) {
function activity_value($value) {
    if ($value === null || $value === '') {
        return '-';
    }

    $decoded = json_decode($value, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    return $value;
}
}
?>

<section class="dashboard-hero">
    <span class="hero-icon"><i class="bi bi-clock-history"></i></span>
    <div>
        <p class="section-label">Audit</p>
        <h2>Journal d activite</h2>
        <p>Historique des connexions, operations metier, validations et mouvements de stock.</p>
    </div>
</section>

<section class="metric-grid">
    <article class="metric-card"><div class="metric-card-top"><span>Total traces</span><span class="metric-icon tone-blue"><i class="bi bi-list-check"></i></span></div><strong><?= e($stats['total']) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Aujourd hui</span><span class="metric-icon tone-green"><i class="bi bi-calendar-check"></i></span></div><strong><?= e($stats['today']) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Connexions</span><span class="metric-icon tone-orange"><i class="bi bi-box-arrow-in-right"></i></span></div><strong><?= e($stats['logins']) ?></strong></article>
    <article class="metric-card"><div class="metric-card-top"><span>Operations</span><span class="metric-icon tone-red"><i class="bi bi-activity"></i></span></div><strong><?= e($stats['operations']) ?></strong></article>
</section>

<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-funnel"></i></span>
        <div><h3>Filtres</h3><p>Filtrer par periode, action et module.</p></div>
    </div>
    <form method="get" action="<?= e(base_url('activity-logs')) ?>" class="enterprise-form">
        <div class="form-grid">
            <label><span>Date debut</span><input type="date" name="start_date" value="<?= e($filters['start_date']) ?>"></label>
            <label><span>Date fin</span><input type="date" name="end_date" value="<?= e($filters['end_date']) ?>"></label>
            <label>
                <span>Action</span>
                <select name="action">
                    <option value="">Toutes</option>
                    <?php foreach ($actions as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['action'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Module</span>
                <select name="module">
                    <option value="">Tous</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= e($module['module']) ?>" <?= $filters['module'] === $module['module'] ? 'selected' : '' ?>><?= e($module['module']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-actions">
            <a class="btn-secondary" href="<?= e(base_url('activity-logs')) ?>"><i class="bi bi-arrow-counterclockwise"></i><span>Reinitialiser</span></a>
            <button class="btn-primary" type="submit"><i class="bi bi-search"></i><span>Filtrer</span></button>
        </div>
    </form>
</section>

<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-table"></i></span>
        <div><h3>Traces recentes</h3><p><?= e(count($logs)) ?> ligne(s), limitees aux 300 dernieres traces.</p></div>
    </div>
    <div class="table-responsive">
        <table class="enterprise-table activity-log-table">
            <thead>
                <tr>
                    <th>Date / heure</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Description</th>
                    <th>Ancienne valeur</th>
                    <th>Nouvelle valeur</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="8">Aucune trace ne correspond aux filtres.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['created_at']) ?></td>
                        <td><strong><?= e($log['user_name'] ?: 'Systeme') ?></strong><br><small><?= e($log['user_email'] ?: '-') ?></small></td>
                        <td><span class="status-badge status-active"><?= e($actions[$log['action']] ?? $log['action']) ?></span></td>
                        <td><?= e($log['module'] ?: '-') ?></td>
                        <td><?= e($log['description'] ?: '-') ?></td>
                        <td><pre class="activity-value"><?= e(activity_value($log['old_values'] ?? null)) ?></pre></td>
                        <td><pre class="activity-value"><?= e(activity_value($log['new_values'] ?? null)) ?></pre></td>
                        <td><?= e($log['ip_address'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
