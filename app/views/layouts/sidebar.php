<?php
$user = Auth::user();
$menuGroups = Auth::menuGroups();
$currentPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$roleName = $user['role_name'] ?? 'Utilisateur';
$canSearch = in_array($user['role_slug'] ?? '', ['administrateur', 'direction'], true);
?>
<aside class="sidebar" data-sidebar>
    <div class="sidebar-top">
        <a class="sidebar-brand" href="<?= e(base_url(Auth::homePathFor($user))) ?>">
            <span class="brand-mark small"><i class="bi bi-buildings"></i></span>
            <span class="sidebar-text">
                <strong>CMCK</strong>
                <small>MillTrack</small>
            </span>
        </a>
        <button type="button" class="sidebar-compact-toggle" data-sidebar-compact aria-label="Reduire le menu" aria-pressed="false">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <span class="sidebar-user-avatar"><?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></span>
        <span class="sidebar-text">
            <strong><?= e($user['name'] ?? '') ?></strong>
            <small><?= e($roleName) ?></small>
        </span>
    </div>

    <?php if ($canSearch): ?>
        <label class="sidebar-search">
            <i class="bi bi-search"></i>
            <input type="search" placeholder="Rechercher" data-menu-search>
        </label>
    <?php endif; ?>

    <nav class="sidebar-nav" aria-label="Navigation principale">
        <?php foreach ($menuGroups as $group): ?>
            <section class="sidebar-section" data-menu-section>
                <p class="sidebar-section-title sidebar-text"><?= e($group['label']) ?></p>
                <div class="<?= !empty($group['quick']) ? 'sidebar-quick-grid' : 'sidebar-link-stack' ?>">
                    <?php foreach ($group['items'] as $item): ?>
                        <?php
                            $itemPath = trim($item['path'], '/');
                            $isActive = $currentPath === $itemPath || strpos($currentPath, $itemPath . '/') === 0;
                            $searchText = strtolower(($group['label'] ?? '') . ' ' . ($item['label'] ?? '') . ' ' . ($item['path'] ?? ''));
                        ?>
                        <a
                            class="<?= $isActive ? 'active' : '' ?> <?= !empty($group['quick']) ? 'quick-link' : '' ?>"
                            href="<?= e(base_url($item['path'])) ?>"
                            title="<?= e($item['label']) ?>"
                            data-menu-item
                            data-menu-text="<?= e($searchText) ?>"
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
