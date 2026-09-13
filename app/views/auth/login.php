<?php $appName = config('app.name', 'DAGRIL ERP'); ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#092f3b">
    <title><?= e($title ?? 'Connexion') ?> - <?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="auth-body auth-body-executive">
    <main class="auth-shell auth-shell-executive">
        <section class="auth-panel auth-login-panel" aria-labelledby="loginTitle">
            <header class="auth-brand-row">
                <a class="brand-lockup" href="<?= e(base_url('login')) ?>" aria-label="<?= e($appName) ?> — Connexion">
                    <span class="brand-mark"><i class="bi bi-flower1" aria-hidden="true"></i></span>
                    <span><span class="brand-kicker">Système intégré de gestion</span><strong><?= e($appName) ?></strong></span>
                </a>
                <span class="auth-environment"><i class="bi bi-shield-check" aria-hidden="true"></i> Accès sécurisé</span>
            </header>

            <div class="auth-login-content">
                <div class="auth-copy">
                    <p class="auth-eyebrow">ESPACE PROFESSIONNEL</p>
                    <h1 id="loginTitle">Bienvenue</h1>
                    <p>Connectez-vous pour accéder à votre espace de travail, vos sites et vos opérations autorisées.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error auth-error" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span><?= e($error) ?></span></div>
                <?php endif; ?>

                <form method="post" action="<?= e(base_url('login')) ?>" class="auth-form" data-login-form>
                    <?= csrf_field() ?>
                    <label for="email">Adresse e-mail</label>
                    <div class="input-shell"><i class="bi bi-envelope" aria-hidden="true"></i><input id="email" name="email" type="email" value="<?= e($email ?? '') ?>" placeholder="nom@dagril.com" autocomplete="username" inputmode="email" required autofocus></div>

                    <div class="auth-field-heading"><label for="password">Mot de passe</label><span>Compte DAGRIL</span></div>
                    <div class="input-shell auth-password-shell"><i class="bi bi-lock" aria-hidden="true"></i><input id="password" name="password" type="password" placeholder="Saisissez votre mot de passe" autocomplete="current-password" required><button type="button" class="auth-password-toggle" data-password-toggle aria-label="Afficher le mot de passe" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button></div>

                    <button type="submit" class="auth-submit"><span>Accéder à DAGRIL ERP</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                </form>

                <div class="auth-support-note"><i class="bi bi-info-circle" aria-hidden="true"></i><p><strong>Besoin d’accès ?</strong><span>Contactez l’administrateur DAGRIL pour votre compte, vos rôles et vos sites.</span></p></div>
            </div>

            <footer class="auth-panel-footer"><span>© <?= e(date('Y')) ?> DAGRIL</span><span>Accès contrôlé · Activités journalisées</span></footer>
        </section>

        <aside class="auth-aside auth-executive-aside" aria-label="Présentation de DAGRIL ERP">
            <div class="auth-aside-top"><span class="auth-suite-label"><i class="bi bi-grid-1x2" aria-hidden="true"></i> Une plateforme, toute la chaîne de valeur</span><span class="auth-live-status"><i aria-hidden="true"></i> Système opérationnel</span></div>
            <div class="auth-aside-content">
                <p class="auth-aside-kicker">PILOTAGE MULTI-SITES</p>
                <h2>Du champ au client,<br><span>une traçabilité continue.</span></h2>
                <p>Planifiez, produisez, contrôlez et distribuez dans un environnement unifié, sécurisé et adapté aux responsabilités de chaque équipe.</p>
                <div class="auth-module-grid" aria-label="Domaines fonctionnels disponibles">
                    <article><i class="bi bi-flower1"></i><span><strong>Gestion agricole</strong><small>Campagnes, parcelles, récoltes</small></span></article>
                    <article><i class="bi bi-box-arrow-in-down"></i><span><strong>Approvisionnements</strong><small>Transport, pesée, silos</small></span></article>
                    <article><i class="bi bi-gear-wide-connected"></i><span><strong>Production</strong><small>ROOF, déchets, pelletisation</small></span></article>
                    <article><i class="bi bi-boxes"></i><span><strong>Stocks & emballage</strong><small>Vrac, sacs, produits finis</small></span></article>
                    <article><i class="bi bi-heart-pulse"></i><span><strong>Élevage & boucherie</strong><small>Lots, sanitaire, transformation</small></span></article>
                    <article><i class="bi bi-truck"></i><span><strong>Distribution & logistique</strong><small>Transferts, carburant, missions</small></span></article>
                    <article><i class="bi bi-pie-chart"></i><span><strong>Budget & performance</strong><small>Engagements, coûts, indicateurs</small></span></article>
                    <article><i class="bi bi-shield-check"></i><span><strong>Conformité</strong><small>Documents, audit, traçabilité</small></span></article>
                </div>
            </div>
            <div class="auth-aside-footer"><span><i class="bi bi-geo-alt" aria-hidden="true"></i> Multi-sites</span><span><i class="bi bi-person-lock" aria-hidden="true"></i> Accès par rôle</span><span><i class="bi bi-clock-history" aria-hidden="true"></i> Audit complet</span></div>
        </aside>
    </main>

    <script>
    (function(){var toggle=document.querySelector('[data-password-toggle]');var input=document.getElementById('password');if(!toggle||!input)return;toggle.addEventListener('click',function(){var visible=input.type==='text';input.type=visible?'password':'text';toggle.setAttribute('aria-pressed',visible?'false':'true');toggle.setAttribute('aria-label',visible?'Afficher le mot de passe':'Masquer le mot de passe');var icon=toggle.querySelector('i');if(icon)icon.className=visible?'bi bi-eye':'bi bi-eye-slash';input.focus({preventScroll:true});});})();
    </script>
</body>
</html>
