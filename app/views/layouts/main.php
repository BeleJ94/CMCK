<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Application') ?> - <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="app-body">
    <div class="app-shell">
        <?php require view_path('layouts.sidebar'); ?>
        <div class="sidebar-backdrop" data-sidebar-close></div>

        <div class="main-area">
            <?php require view_path('layouts.header'); ?>

            <main class="content-area">
                <?= $content ?>
            </main>
        </div>
    </div>
    <script src="<?= e(asset_url('js/app.js')) ?>"></script>
</body>
</html>
