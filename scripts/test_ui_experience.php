<?php

/**
 * Static UI contract checks for the shared DAGRIL experience layer.
 * This script never connects to a database and never changes application data.
 */
$root = dirname(__DIR__);
$failures = [];
$checks = 0;

function ui_check($condition, $message)
{
    global $failures, $checks;
    $checks++;
    echo ($condition ? '[OK] ' : '[ECHEC] ') . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

function ui_read($path)
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Lecture impossible: ' . $path);
    }
    return $contents;
}

$main = ui_read($root . '/app/views/layouts/main.php');
$header = ui_read($root . '/app/views/layouts/header.php');
$sidebar = ui_read($root . '/app/views/layouts/sidebar.php');
$helpers = ui_read($root . '/app/helpers/functions.php');
$javascript = ui_read($root . '/public/assets/js/app.js');
$css = ui_read($root . '/public/assets/css/app.css');
$analytics = ui_read($root . '/app/views/analytics/index.php');
$analyticsJavascript = ui_read($root . '/public/assets/js/analytics-dashboard.js');
$auth = ui_read($root . '/app/core/Auth.php');
$loginView = ui_read($root . '/app/views/auth/login.php');
$usersView = ui_read($root . '/app/views/users/index.php');
$router = ui_read($root . '/public/index.php');

ui_check(strpos($loginView, 'auth-shell-executive') !== false && strpos($css, 'Executive authentication experience') !== false, 'La connexion utilise le nouveau rendu exécutif responsive.');
ui_check(substr_count($loginView, '<article>') === 8, 'La connexion présente les huit domaines fonctionnels actualisés.');
ui_check(strpos($loginView, 'data-password-toggle') !== false && strpos($loginView, 'aria-pressed="false"') !== false, 'Le mot de passe peut être affiché avec un contrôle accessible.');
ui_check(strpos($loginView, 'csrf_field()') !== false && strpos($loginView, 'autocomplete="current-password"') !== false, 'La connexion conserve le CSRF et la compatibilité des gestionnaires de mots de passe.');

ui_check(strpos($main, 'data-action-dialog') !== false, 'La modale de confirmation globale est rendue par le layout.');
ui_check(strpos($main, 'data-ui-progress') !== false, 'La progression AJAX globale est rendue par le layout.');
ui_check(strpos($main, 'aria-live="polite"') !== false, 'Les notifications disposent d une zone aria-live.');
ui_check(strpos($main, 'class="skip-link"') !== false, 'Le lien d evitement clavier est present.');
ui_check(strpos($header, 'data-command-open') !== false, 'La recherche globale est disponible dans la barre superieure.');
ui_check(strpos($sidebar, 'data-section-toggle') !== false, 'Les groupes de navigation sont repliables.');
ui_check(strpos($sidebar, 'sidebar-site-form') !== false, 'Le changement de site reste disponible dans le menu mobile.');
ui_check(strpos($sidebar, 'aria-current="page"') !== false, 'La page active est exposee aux technologies d assistance.');
ui_check(strpos($javascript, 'function enhanceTables') !== false, 'La DataTable commune est centralisee.');
ui_check(strpos($javascript, 'function refreshBusinessForms') !== false, 'Les calculateurs metier sont rehydrates apres un retour AJAX.');
ui_check(strpos($javascript, "fetch(form.action") !== false, 'Les formulaires progressifs utilisent fetch et FormData.');
ui_check(strpos($javascript, 'requestInFlight') !== false, 'Une soumission simultanee est bloquee cote interface.');
ui_check(strpos($javascript, 'Aucune relance automatique') !== false, 'Les erreurs reseau ne provoquent pas de nouvelle operation automatique.');
ui_check(strpos($javascript, 'function trapFocus') !== false, 'Les dialogues piegent le focus clavier.');
ui_check(strpos($css, ':focus-visible') !== false, 'Un focus visible est defini.');
ui_check(strpos($css, 'prefers-reduced-motion') !== false, 'Les preferences de mouvement reduit sont respectees.');
ui_check(strpos($css, '@media (max-width: 760px)') !== false, 'Une adaptation mobile compacte est definie.');
ui_check(strpos($css, 'visibility: hidden') !== false, 'Les dialogues fermes sortent de la navigation clavier.');
ui_check(strpos($main, 'data-response-flash') !== false && strpos($helpers, 'dagril_consumed_flashes') !== false, 'Le resultat serveur pilote le retour AJAX sans succes presume.');
ui_check(strpos($analytics, 'data-analytics-modal') !== false && strpos($analytics, 'data-analytics-mini') !== false, 'Le dashboard expose les mini-graphiques et le modal analytique.');
ui_check(strpos($analyticsJavascript, "event.key === 'Escape'") !== false, 'Le modal analytique se ferme au clavier.');
ui_check(strpos($analyticsJavascript, 'devicePixelRatio') !== false, 'Les graphiques restent nets sur les écrans haute densité.');
foreach (['Ferme agricole','Approvisionnements & matières','Production industrielle','Conditionnement & stocks','Élevage & boucherie','Distribution & logistique','Pilotage & conformité','Administration'] as $menuGroup) {
    ui_check(strpos($auth, "'label' => '" . $menuGroup . "'") !== false, 'Parcours menu disponible : ' . $menuGroup . '.');
}
$menuPaths = ['dashboard','agriculture','suppliers','trucks','weighings','silos','machine-feeds','machines','production','waste','pelletization','empty-packaging','packaging','finished-stocks','livestock','butchery','transfers','distributions','fuel-logistics','budgets','reports','traceability','documents','cancellations','alerts','activity-logs','users','sites','access-control'];
foreach ($menuPaths as $menuPath) {
    ui_check(substr_count($auth, "'path' => '" . $menuPath . "'") === 1, 'Destination menu unique : ' . $menuPath . '.');
}
ui_check(strpos($auth, "'label' => 'Tableau de bord KPI'") === false && strpos($auth, "'path' => 'analytics'") === false, 'Le tableau KPI n’est plus dupliqué dans Pilotage & conformité.');
ui_check(strpos($sidebar, 'activeGroupIndex') !== false && strpos($sidebar, 'is-current') !== false, 'Le parcours de la page active est ouvert et signalé.');
ui_check(strpos($javascript, 'menuSearchSnapshot') !== false, 'La recherche restaure l état des groupes après filtrage.');
ui_check(strpos($javascript, "section.classList.toggle('is-current', isCurrent)") !== false && strpos($javascript, "toggle.setAttribute('aria-expanded', isCurrent ? 'true' : 'false')") !== false, 'La navigation AJAX synchronise le groupe de menu actif et son ouverture.');
ui_check(strpos($javascript, 'function syncActiveMenuGroup()') !== false && strpos($javascript, 'syncActiveMenuGroup();') !== false, 'Le chargement initial ferme les groupes inactifs et ouvre uniquement celui de la page courante.');
ui_check(strpos($router, "'/users', 'User@index'") !== false && strpos($router, "'/users', 'Dashboard@placeholder'") === false, 'La page utilisateurs utilise son contrôleur dédié.');
ui_check(strpos($usersView, 'data-user-modal') !== false && strpos($usersView, 'data-user-form') !== false, 'Les formulaires utilisateurs sont présentés dans un modal accessible.');
ui_check(strpos($usersView, '<table data-search-placeholder=') !== false, 'Le répertoire utilisateurs active la DataTable commune.');
$sitesView = ui_read($root . '/app/views/sites/index.php');
ui_check(substr_count($sitesView, 'role="tab"') === 5 && strpos($sitesView, 'data-site-tabs') !== false, 'Les cinq répertoires multi-sites sont organisés en onglets accessibles.');
ui_check(strpos($javascript, 'function enhanceSiteTabs') !== false && strpos($javascript, "'ArrowRight'") !== false, 'Les onglets multi-sites prennent en charge la navigation clavier.');
$agricultureView = ui_read($root . '/app/views/agriculture/index.php');
ui_check(substr_count($agricultureView, 'data-business-tab=') === 4, 'Le parcours agricole répartit les données dans quatre onglets métier.');
ui_check(substr_count($agricultureView, "agro_modal_start('") === 9, 'Les neuf saisies agricoles sont présentées dans des modales dédiées.');
ui_check(strpos($javascript, 'function enhanceBusinessTabs') !== false && strpos($javascript, 'function openWorkspaceModal') !== false, 'Les onglets et modales métier sont réhydratés après AJAX.');
ui_check(strpos($agricultureView, 'data-campaign-site') !== false && strpos($agricultureView, 'data-suggested-code') !== false, 'La création de campagne demande explicitement une ferme autorisée.');
ui_check(strpos($javascript, 'function syncCampaignSuggestedCode') !== false, 'Le code de campagne proposé suit la ferme sélectionnée.');
ui_check(strpos($agricultureView, 'Répertoire des parcelles') !== false && substr_count($agricultureView, 'data-workspace-modal-open="plotModal"') >= 2, 'Le tableau des parcelles et son action de création sont directement visibles dans l’onglet.');
ui_check(strpos($javascript, 'function syncPlotSuggestedCode') !== false && strpos($agricultureView, 'data-plot-site') !== false, 'La création de parcelle fonctionne aussi depuis la vue consolidée.');
ui_check(strpos($agricultureView, 'data-plan-plot=') !== false, 'Chaque ligne de parcelle propose une planification directe.');
ui_check(strpos($javascript, 'function syncPlanSite') !== false && strpos($agricultureView, 'data-plan-plot-select') !== false, 'La modale filtre les campagnes et parcelles par ferme.');
ui_check(strpos($agricultureView, 'data-plot-context') !== false && strpos($javascript, 'function updatePlotContext') !== false, 'La modale explique la superficie et l’historique de la parcelle sélectionnée.');
$agricultureModel = ui_read($root . '/app/models/Agriculture.php');
ui_check(strpos($agricultureModel, '$this->farmSite($row[\'site_id\'])') !== false, 'Les opérations liées à une campagne/parcelle dérivent leur ferme du référentiel sélectionné.');
ui_check(strpos($auth, "'label' => 'Ferme agricole'") !== false && strpos($auth, "'path' => 'agriculture/plots'") !== false, 'Le module Ferme agricole possède des sous-menus spécialisés.');
ui_check(strpos($router, "'/agriculture/planning','Agriculture@planning'") !== false, 'Les répertoires agricoles disposent de routes GET dédiées.');
ui_check(strpos($javascript, 'panelMap={campaigns:') !== false, 'Chaque route agricole n’affiche que son répertoire métier.');
ui_check(strpos($auth, "'label' => 'Intrants'") !== false && strpos($auth, "'label' => 'Matériels'") !== false, 'Les référentiels et opérations agricoles sont accessibles depuis le menu.');
ui_check(strpos($agricultureView, "extra_directories.php") !== false, 'Les nouveaux sous-menus utilisent un répertoire sobre partagé.');
ui_check(strpos($css, '.content-area>.agriculture-page{animation:none!important;transform:none!important}') !== false, 'Le conteneur agricole ne décale plus ses modales fixes.');
ui_check(strpos($css, '.agriculture-page .workspace-entity-modal{position:fixed!important') !== false, 'Les modales agricoles utilisent un repère viewport uniforme.');
ui_check(substr_count($agricultureView, 'data-farm-code-site') === 2 && strpos($javascript, 'function syncFarmSuggestedCode') !== false, 'Les travailleurs et matériels peuvent être créés depuis la vue consolidée avec une ferme explicite.');
ui_check(strpos($agricultureView, 'Créer le BT en brouillon') !== false && strpos($agricultureModel, '$this->farmSite($stock[\'site_id\'])') !== false, 'Le BT agricole dérive sa ferme du stock sélectionné depuis la vue consolidée.');
ui_check(strpos($agricultureModel, "!Auth::hasRole(['administrateur'])") !== false && strpos($agricultureModel, "'administrative_override'=>") !== false, 'L’auto-validation des récoltes est réservée à l’administrateur et explicitement auditée.');
ui_check(substr_count($agricultureModel, "!Auth::hasRole(['administrateur'])") >= 2 && strpos($agricultureModel, "'approve_farm_transport'") !== false, 'L’administrateur peut valider son propre BT avec une trace d’audit explicite.');
ui_check(strpos($agricultureView, 'value="agriculture/harvests"') !== false, 'La validation AJAX reste dans le répertoire des récoltes.');
ui_check(strpos($javascript, "showToast('Opération réussie'") !== false && strpos($javascript, 'currentDirectory.replaceWith(incomingDirectory)') !== false, 'Le retour agricole actualise uniquement le tableau avec une alerte de succès.');
ui_check(substr_count($agricultureView, 'name="_return_to" value="agriculture/transports"') >= 2 && strpos($agricultureView, 'Cette validation réservera immédiatement') !== false && strpos($agricultureView, 'Cette expédition diminuera le stock physique') !== false, 'Les actions BT reviennent en AJAX au tableau des transports avec une confirmation métier explicite.');
ui_check(substr_count($agricultureView, "suggestedCodes['") === 4, 'Les quatre codes agricoles éditables sont préremplis par le serveur.');
ui_check(substr_count($agricultureView, 'data-action-menu-toggle=') === 2, 'Les opérations et référentiels agricoles utilisent deux menus sobres.');
ui_check(strpos($javascript, 'function toggleActionMenu') !== false, 'Les menus d’action exécutifs sont pilotés sans navigation supplémentaire.');
ui_check(strrpos($css, '.action-dialog{z-index:1410}') > strrpos($css, '.action-dialog,'), 'La garde finale place la confirmation au-dessus des modales métier.');
ui_check(strpos($css, '/* DAGRIL modal system') !== false, 'Les fenêtres utilisent le système modal unifié.');
ui_check(strpos($css, '.entity-modal .form-grid > label') !== false, 'Les champs du formulaire utilisateur gardent un alignement vertical explicite.');
ui_check(strpos($css, 'grid-template-rows: minmax(0, 1fr) auto') !== false, 'Le corps des formulaires défile sans masquer les actions.');
ui_check(strpos($css, '.action-dialog { z-index: 1410; }') !== false, 'La confirmation reste au-dessus des formulaires métier.');
ui_check(strpos($css, '100dvh') !== false, 'La hauteur des fenêtres suit le viewport dynamique mobile.');
ui_check(substr_count($css, 'animation: none !important;') >= 2, 'Les animations de page ne peuvent pas décentrer les fenêtres et leurs arrière-plans.');
ui_check(strpos($javascript, "trapFocus(event, qs('[data-notification-modal]'))") !== false, 'Le détail des notifications conserve le focus clavier.');
ui_check(strpos($javascript, 'detailReturnFocus.focus') !== false, 'La fermeture du détail restitue le focus au déclencheur.');
ui_check(strpos($analyticsJavascript, 'document.contains(returnFocus)') !== false, 'Le modal analytique restitue le focus uniquement à un élément encore présent.');

$viewFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/views'));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $viewFiles[] = $file->getPathname();
    }
}

$postForms = 0;
$csrfFields = 0;
$tables = 0;
$remoteDataTables = [];
foreach ($viewFiles as $file) {
    $contents = ui_read($file);
    $postForms += preg_match_all('/<form\b[^>]*\bmethod\s*=\s*["\']post["\']/i', $contents, $unused);
    $csrfFields += substr_count($contents, 'csrf_field()');
    $tables += preg_match_all('/<table\b/i', $contents, $unused);
    if (stripos($contents, 'cdn.datatables.net') !== false || stripos($contents, 'code.jquery.com') !== false) {
        $remoteDataTables[] = str_replace($root . '/', '', $file);
    }
}

ui_check($postForms > 0, 'Les formulaires POST ont ete inventories (' . $postForms . ').');
ui_check($csrfFields >= $postForms, 'Chaque formulaire POST conserve au moins un jeton CSRF.');
ui_check($tables > 0, 'Les tableaux applicatifs ont ete inventories (' . $tables . ').');
ui_check(count($remoteDataTables) === 0, 'Aucun chargement DataTables/jQuery distant ne subsiste dans les vues.');

echo PHP_EOL . $checks . ' verification(s), ' . count($failures) . ' echec(s).' . PHP_EOL;
if ($failures) {
    echo 'Fichiers CDN restants: ' . implode(', ', $remoteDataTables) . PHP_EOL;
    exit(1);
}
