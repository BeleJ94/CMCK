<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#12324a">
    <title><?= e($title ?? 'Application') ?> - <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="app-body">
    <a class="skip-link" href="#mainContent">Aller au contenu principal</a>
    <div class="app-shell">
        <?php require view_path('layouts.sidebar'); ?>
        <div class="sidebar-backdrop" data-sidebar-close></div>

        <div class="main-area">
            <?php require view_path('layouts.header'); ?>

            <main class="content-area" id="mainContent" tabindex="-1">
                <?php
                $consumedFlashes = $GLOBALS['dagril_consumed_flashes'] ?? [];
                $responseFlashKind = (!empty($consumedFlashes['error']) || !empty($consumedFlashes['errors'])) ? 'error' : (!empty($consumedFlashes['success']) ? 'success' : '');
                $responseFlashValue = $responseFlashKind === 'error'
                    ? ($consumedFlashes['error'] ?? 'Veuillez corriger les informations indiquées.')
                    : ($consumedFlashes['success'] ?? '');
                $responseFlashMessage = is_scalar($responseFlashValue) ? (string) $responseFlashValue : 'Veuillez corriger les informations indiquées.';
                ?>
                <?php if ($responseFlashKind !== ''): ?><span hidden data-response-flash="<?= e($responseFlashKind) ?>" data-response-message="<?= e($responseFlashMessage) ?>"></span><?php endif; ?>
                <?= $content ?>
            </main>
        </div>
    </div>

    <div class="ui-progress" data-ui-progress aria-hidden="true"><span></span></div>
    <div class="toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>

    <div class="action-dialog-backdrop" data-action-dialog-close></div>
    <section class="action-dialog" role="dialog" aria-modal="true" aria-labelledby="actionDialogTitle" aria-describedby="actionDialogDescription" aria-hidden="true" data-action-dialog>
        <div class="action-dialog-head">
            <span class="action-dialog-icon" aria-hidden="true"><i class="bi bi-check2-square"></i></span>
            <div>
                <p class="section-label">Confirmation de l’opération</p>
                <h2 id="actionDialogTitle" data-action-dialog-title>Confirmer</h2>
            </div>
            <button type="button" class="modal-close" aria-label="Fermer" data-action-dialog-close><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="action-dialog-body">
            <p id="actionDialogDescription" data-action-dialog-description>Vérifiez les informations avant de continuer.</p>
            <dl class="operation-summary" data-operation-summary></dl>
            <div class="action-dialog-notice"><i class="bi bi-shield-check"></i><span>L’autorisation, le site et le jeton de sécurité seront vérifiés par le serveur.</span></div>
        </div>
        <div class="action-dialog-actions">
            <button type="button" class="btn-secondary" data-action-dialog-close>Retour</button>
            <button type="button" class="btn-primary" data-action-dialog-confirm><i class="bi bi-check2"></i><span>Confirmer et exécuter</span></button>
        </div>
    </section>

    <section class="command-palette" role="dialog" aria-modal="true" aria-labelledby="commandPaletteTitle" aria-hidden="true" data-command-palette>
        <div class="command-palette-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="sr-only" for="globalCommandSearch">Rechercher une fonction</label>
            <input id="globalCommandSearch" type="search" placeholder="Rechercher une fonction, un module..." autocomplete="off" data-command-input>
            <kbd>Esc</kbd>
        </div>
        <div class="command-palette-body">
            <p class="command-palette-label">Navigation autorisée</p>
            <div class="command-results" data-command-results></div>
            <p class="command-empty" data-command-empty hidden>Aucun module correspondant.</p>
        </div>
    </section>
    <div class="command-palette-backdrop" data-command-close></div>

    <script src="<?= e(asset_url('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
    <script src="<?= e(asset_url('js/works.js')) ?>"></script>
    <script src="<?= e(asset_url('js/campaigns.js')) ?>"></script>
    <script src="<?= e(asset_url('js/plots.js')) ?>"></script>
    <script src="<?= e(asset_url('js/depots.js')) ?>"></script>
    <script src="<?= e(asset_url('js/machine-feeds.js')) ?>"></script>
    <script src="<?= e(asset_url('js/machines.js')) ?>"></script>
    <script src="<?= e(asset_url('js/production.js')) ?>"></script>
    <script src="<?= e(asset_url('js/waste.js')) ?>"></script>
    <script src="<?= e(asset_url('js/pelletization.js')) ?>"></script>
    <script src="<?= e(asset_url('js/audit.js')) ?>"></script>
    <script src="<?= e(asset_url('js/empty-packaging.js')) ?>"></script>
    <script src="<?= e(asset_url('js/packaging.js')) ?>"></script>
    <script src="<?= e(asset_url('js/finished-stocks.js')) ?>"></script>
    <script src="<?= e(asset_url('js/livestock.js')) ?>"></script>
    <script src="<?= e(asset_url('js/butchery.js')) ?>"></script>
    <script src="<?= e(asset_url('js/fuel-logistics.js')) ?>"></script>
    <script src="<?= e(asset_url('js/distributions.js')) ?>"></script>
    <script src="<?= e(asset_url('js/transfers.js')) ?>"></script>
    <script src="<?= e(asset_url('js/app.js')) ?>"></script>
</body>
</html>
