<?php $user = Auth::user(); ?>
<header class="topbar">
    <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Ouvrir le menu">
        <i class="bi bi-list"></i>
    </button>

    <div class="page-title">
        <span class="page-title-icon"><i class="bi bi-shield-check"></i></span>
        <p class="page-kicker"><?= e($user['role_name'] ?? 'Utilisateur') ?></p>
        <h1><?= e($title ?? 'CMCK MillTrack') ?></h1>
    </div>

    <div class="user-menu">
        <div class="user-avatar"><?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></div>
        <div>
            <strong><?= e($user['name'] ?? '') ?></strong>
            <small><?= e($user['email'] ?? '') ?></small>
        </div>
        <form method="post" action="<?= e(base_url('logout')) ?>" class="logout-form">
            <?= csrf_field() ?>
            <button type="submit">
                <i class="bi bi-box-arrow-right"></i>
                <span>Deconnexion</span>
            </button>
        </form>
    </div>
</header>
