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
            <?= csrf_field() ?><input type="hidden" name="_return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
            <label for="sidebarSiteContext"><i class="bi bi-geo-alt"></i><span class="sidebar-text">Site courant</span></label>
            <select id="sidebarSiteContext" name="site_id" data-site-context-select>
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
                    <?php
                    // Keep Auth's permission-filtered links flat; group only their presentation.
                    $entries = [];
                    foreach ($group['items'] as $menuItem) {
                        if (empty($menuItem['submenu'])) { $entries[] = ['item' => $menuItem]; continue; }
                        $key = 'submenu:'.$menuItem['submenu'];
                        if (!isset($entries[$key])) $entries[$key] = ['label' => $menuItem['submenu'], 'icon' => $menuItem['submenu_icon'] ?? 'bi-folder2', 'items' => []];
                        $entries[$key]['items'][] = $menuItem;
                    }
                    foreach ($entries as $entry):
                        if (isset($entry['item'])):
                            $item = $entry['item']; require view_path('partials.sidebar_link');
                        else:
                            $submenuActive = false;
                            foreach ($entry['items'] as $child) {
                                if ($currentPath === $child['path'] || strpos($currentPath, $child['path'].'/') === 0) { $submenuActive = true; break; }
                            }
                    ?>
                        <details class="sidebar-submenu" data-menu-subgroup <?= $submenuActive ? 'open' : '' ?>>
                            <summary title="<?= e($entry['label']) ?>">
                                <span class="nav-icon"><i class="bi <?= e($entry['icon']) ?>" aria-hidden="true"></i></span>
                                <span class="sidebar-text"><?= e($entry['label']) ?></span>
                                <i class="bi bi-chevron-down sidebar-submenu-chevron" aria-hidden="true"></i>
                            </summary>
                            <div class="sidebar-submenu-links">
                                <?php foreach ($entry['items'] as $item): require view_path('partials.sidebar_link'); endforeach; ?>
                            </div>
                        </details>
                    <?php endif; endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </nav>
</aside>
