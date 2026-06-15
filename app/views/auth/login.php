<?php $appName = config('app.name', 'DAGRIL ERP'); ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Connexion') ?> - <?= e($appName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-panel">
            <div class="brand-lockup">
                <div class="brand-mark"><i class="bi bi-buildings"></i></div>
                <div>
                    <p class="brand-kicker">Plateforme industrielle</p>
                    <h1><?= e($appName) ?></h1>
                </div>
            </div>

            <div class="auth-copy">
                <h2>Connexion</h2>
                <p>Accedez a votre espace de pilotage DAGRIL.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(base_url('login')) ?>" class="auth-form">
                <?= csrf_field() ?>

                <label for="email">Email</label>
                <div class="input-shell">
                    <i class="bi bi-envelope"></i>
                    <input id="email" name="email" type="email" value="<?= e($email ?? '') ?>" autocomplete="email" required autofocus>
                </div>

                <label for="password">Mot de passe</label>
                <div class="input-shell">
                    <i class="bi bi-lock"></i>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <button type="submit">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Se connecter</span>
                </button>
            </form>
        </section>

        <aside class="auth-aside">
            <div class="auth-aside-content">
                <span class="status-pill"><i class="bi bi-shield-check"></i> Acces securise</span>
                <h2>DAGRIL ERP</h2>
                <p>Operations, stocks, production et distribution dans un meme environnement de travail.</p>
                <div class="auth-highlights" aria-label="Modules principaux">
                    <span><i class="bi bi-truck"></i> Pont-bascule</span>
                    <span><i class="bi bi-database"></i> Silos</span>
                    <span><i class="bi bi-gear-wide-connected"></i> Production</span>
                    <span><i class="bi bi-send-check"></i> Distribution</span>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>
