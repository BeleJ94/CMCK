<?php
    $itemPath = trim($item['path'], '/');
    $isActive = $currentPath === $itemPath || (!in_array($itemPath, ['agriculture','livestock','livestock/pigs','livestock/fish','livestock/cattle','livestock/poultry','butchery'], true) && strpos($currentPath, $itemPath . '/') === 0);
    $searchText = strtolower(($group['label'] ?? '') . ' ' . ($item['submenu'] ?? '') . ' ' . ($item['label'] ?? '') . ' ' . ($item['path'] ?? ''));
?>
<a
    class="<?= $isActive ? 'active' : '' ?> <?= !empty($group['quick']) ? 'quick-link' : '' ?>"
    href="<?= e(base_url($item['path'])) ?>"
    title="<?= e($item['label']) ?>"
    data-menu-item
    data-menu-text="<?= e($searchText) ?>"
    data-command-link
    data-command-label="<?= e(isset($item['submenu']) ? $item['submenu'].' · '.$item['label'] : $item['label']) ?>"
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
