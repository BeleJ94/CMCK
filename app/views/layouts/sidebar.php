<?php
$user = Auth::user();
$menuGroups = Auth::menuGroups();
$requestPath = trim(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)), '/');
$basePath = trim(rawurldecode((string) parse_url(base_url(), PHP_URL_PATH)), '/');
$currentPath = $requestPath;
if ($basePath !== '' && ($requestPath === $basePath || strpos($requestPath, $basePath . '/') === 0)) {
    $currentPath = ltrim(substr($requestPath, strlen($basePath)), '/');
}
$roleName = $user['role_name'] ?? 'Utilisateur';
$canSearch = in_array($user['role_slug'] ?? '', ['administrateur', 'direction'], true);
$contextSites = Auth::sites();
$currentSiteId = Auth::currentSiteId();
$activeGroupIndex = null;
foreach ($menuGroups as $groupIndex => $menuGroup) {
    foreach ($menuGroup['items'] as $menuItem) {
        $candidatePath = trim($menuItem['path'], '/');
        if ($currentPath === $candidatePath || strpos($currentPath, $candidatePath . '/') === 0) {
            $activeGroupIndex = $groupIndex;
            break 2;
        }
    }
}
?>
<aside class="sidebar" data-sidebar>
    <div class="sidebar-top">
        <a class="sidebar-brand" href="<?= e(base_url(Auth::homePathFor($user))) ?>">
            <span class="brand-mark small"><i class="bi bi-flower1"></i></span>
            <span class="sidebar-text">
                <strong>DAGRIL</strong>
                <small>Enterprise Resource Planning</small>
            </span>
        </a>
        <button type="button" class="sidebar-compact-toggle" data-sidebar-compact aria-label="Reduire le menu" aria-pressed="false">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
    </div>

    <div class="sidebar-context sidebar-text">
        <span class="sidebar-context-dot"></span>
        <div><strong>Espace operationnel</strong><small><?= e($roleName) ?></small></div>
    </div>

    <?php if ($contextSites): ?>
        <form method="post" action="<?= e(base_url('context/site')) ?>" class="sidebar-site-form" data-ajax="false">
            <?= csrf_field() ?>
            <label for="sidebarSiteContext"><i class="bi bi-geo-alt"></i><span class="sidebar-text">Site courant</span></label>
            <select id="sidebarSiteContext" name="site_id" onchange="this.form.submit()">
                <?php if (Auth::canViewConsolidated()): ?><option value="all" <?= $currentSiteId === null ? 'selected' : '' ?>>Tous les sites</option><?php endif; ?>
                <?php foreach ($contextSites as $contextSite): ?><option value="<?= e($contextSite['id']) ?>" <?= (int) $currentSiteId === (int) $contextSite['id'] ? 'selected' : '' ?>><?= e($contextSite['code'] . ' — ' . $contextSite['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>

    <?php if ($canSearch): ?>
        <label class="sidebar-search">
            <i class="bi bi-search"></i>
            <input type="search" placeholder="Rechercher" data-menu-search>
        </label>
    <?php endif; ?>

    <nav class="sidebar-nav" aria-label="Navigation principale">
        <?php foreach ($menuGroups as $groupIndex => $group): ?>
            <?php $isExpanded = $activeGroupIndex === $groupIndex || ($activeGroupIndex === null && $groupIndex === 0); ?>
            <section class="sidebar-section <?= $activeGroupIndex === $groupIndex ? 'is-current' : '' ?>" data-menu-section>
                <button type="button" class="sidebar-section-title sidebar-text" data-section-toggle aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>">
                    <span class="sidebar-section-name"><i class="bi <?= e($group['icon'] ?? 'bi-folder2') ?>"></i><span><?= e($group['label']) ?></span></span><i class="bi bi-chevron-down group-chevron"></i>
                </button>
                <div class="<?= !empty($group['quick']) ? 'sidebar-quick-grid' : 'sidebar-link-stack' ?>" data-section-content>
                    <?php foreach ($group['items'] as $item): ?>
                        <?php
                            $itemPath = trim($item['path'], '/');
                            $isActive = $currentPath === $itemPath || ($itemPath !== 'agriculture' && strpos($currentPath, $itemPath . '/') === 0);
                            $searchText = strtolower(($group['label'] ?? '') . ' ' . ($item['label'] ?? '') . ' ' . ($item['path'] ?? ''));
                        ?>
                        <a
                            class="<?= $isActive ? 'active' : '' ?> <?= !empty($group['quick']) ? 'quick-link' : '' ?>"
                            href="<?= e(base_url($item['path'])) ?>"
                            title="<?= e($item['label']) ?>"
                            data-menu-item
                            data-menu-text="<?= e($searchText) ?>"
                            data-command-link
                            data-command-label="<?= e($item['label']) ?>"
                            data-command-group="<?= e($group['label']) ?>"
                            data-command-icon="<?= e($item['icon'] ?? 'bi-circle') ?>"
                            <?= $isActive ? 'aria-current="page"' : '' ?>
                        >
                            <span class="nav-icon"><i class="bi <?= e($item['icon'] ?? 'bi-circle') ?>"></i></span>
                            <span class="nav-label sidebar-text"><?= e($item['label']) ?></span>
                            <?php if (!empty($item['badge_value'])): ?>
                                <span class="nav-badge"><?= e($item['badge_value']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </nav>
</aside>
