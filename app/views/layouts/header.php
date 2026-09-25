<?php
$user = Auth::user();
$contextSites = Auth::sites();
$currentSiteId = Auth::currentSiteId();
$requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$pageIcons = [
    'dashboard' => 'bi-grid-1x2', 'direction' => 'bi-graph-up-arrow', 'analytics' => 'bi-bar-chart-line',
    'weighbridge-transports' => 'bi-truck-front', 'weighings' => 'bi-truck', 'silos' => 'bi-database',
    'machine-feeds' => 'bi-arrow-down-square', 'machines' => 'bi-gear-wide-connected',
    'production' => 'bi-boxes', 'empty-packaging' => 'bi-bag-check', 'packaging' => 'bi-box-seam', 'distributions' => 'bi-send-check',
    'transfers' => 'bi-arrow-left-right', 'agriculture' => 'bi-flower1', 'livestock' => 'bi-heart-pulse',
    'butchery' => 'bi-shop', 'budgets' => 'bi-pie-chart', 'fuel-logistics' => 'bi-fuel-pump',
    'pelletization' => 'bi-circle-square', 'waste' => 'bi-recycle', 'finished-stocks' => 'bi-boxes',
    'documents' => 'bi-file-earmark-text', 'traceability' => 'bi-diagram-3', 'reports' => 'bi-file-earmark-bar-graph',
    'cancellations' => 'bi-arrow-counterclockwise', 'access-control' => 'bi-shield-lock', 'sites' => 'bi-buildings', 'alerts' => 'bi-bell',
];
$pageIcon = 'bi-window-stack';
foreach ($pageIcons as $prefix => $icon) {
    if (strpos($requestPath, $prefix) !== false) { $pageIcon = $icon; break; }
}
?>
<header class="topbar">
    <div class="topbar-leading">
        <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Ouvrir le menu" aria-expanded="false"><i class="bi bi-list"></i></button>
        <div class="page-title">
            <span class="page-title-icon"><i class="bi <?= e($pageIcon) ?>"></i></span>
            <div><p class="page-kicker"><?= e($user['role_name'] ?? 'Utilisateur') ?></p><h1><?= e($title ?? 'DAGRIL ERP') ?></h1></div>
        </div>
    </div>

    <button type="button" class="global-search-trigger" data-command-open aria-haspopup="dialog">
        <i class="bi bi-search"></i><span>Rechercher dans DAGRIL ERP</span><kbd>⌘ K</kbd>
    </button>

    <div class="user-menu">
        <?php if ($contextSites): ?>
            <form method="post" action="<?= e(base_url('context/site')) ?>" class="site-context-form" data-ajax="false">
                <?= csrf_field() ?><input type="hidden" name="_return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                <label><span class="sr-only">Site courant</span><i class="bi bi-geo-alt"></i>
                    <select name="site_id" onchange="this.form.elements._return_to.value=window.location.pathname+window.location.search+window.location.hash;this.form.submit()" aria-label="Site courant">
                        <?php if (Auth::canViewConsolidated()): ?><option value="all" <?= $currentSiteId === null ? 'selected' : '' ?>>Tous les sites</option><?php endif; ?>
                        <?php foreach ($contextSites as $contextSite): ?><option value="<?= e($contextSite['id']) ?>" <?= (int) $currentSiteId === (int) $contextSite['id'] ? 'selected' : '' ?>><?= e($contextSite['code']) ?></option><?php endforeach; ?>
                    </select><i class="bi bi-chevron-down"></i>
                </label>
            </form>
        <?php endif; ?>
        <?php if (Auth::can('alerts', 'read')): ?><a href="<?= e(base_url('alerts')) ?>" class="topbar-icon-button" aria-label="Consulter les alertes" title="Alertes"><i class="bi bi-bell"></i></a><?php endif; ?>
        <div class="user-identity"><div class="user-avatar"><?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></div><div class="user-copy"><strong><?= e($user['name'] ?? '') ?></strong><small><?= e($user['email'] ?? '') ?></small></div></div>
        <form method="post" action="<?= e(base_url('logout')) ?>" class="logout-form" data-ajax="false">
            <?= csrf_field() ?><button type="submit" aria-label="Déconnexion" title="Déconnexion"><i class="bi bi-box-arrow-right"></i><span class="logout-label">Déconnexion</span></button>
        </form>
    </div>
</header>
