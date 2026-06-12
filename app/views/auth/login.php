<?php $appName = config('app.name', 'CMCK MillTrack'); ?>
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
                <p>Accedez au suivi des pesees, silos, productions et distributions.</p>
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
            <div>
                <span class="status-pill"><i class="bi bi-shield-check"></i> Exploitation CMCK</span>
                <h2>Controle fiable des flux du moulin</h2>
                <p>Une interface sobre pour les equipes pont-bascule, silo, production, emballage et distribution.</p>
            </div>
        </aside>
    </main>
</body>
</html>
